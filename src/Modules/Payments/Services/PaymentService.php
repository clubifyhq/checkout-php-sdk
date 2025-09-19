<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Services;

use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Payments\Contracts\GatewayInterface;
use Clubify\Checkout\Modules\Payments\Contracts\PaymentRepositoryInterface;
use Clubify\Checkout\Modules\Payments\Exceptions\PaymentException;
use Clubify\Checkout\Modules\Payments\Exceptions\GatewayException;
use Clubify\Checkout\Utils\Validators\CreditCardValidator;
use Clubify\Checkout\Utils\Formatters\CurrencyFormatter;
use Clubify\Checkout\Core\Logger\Logger;
use Psr\Cache\CacheItemPoolInterface;
use InvalidArgumentException;

/**
 * Serviço principal para processamento de pagamentos
 *
 * Orquestra operações de pagamento entre diferentes gateways
 * usando Strategy Pattern para flexibilidade e escalabilidade.
 *
 * Implementa resiliência com:
 * - Circuit breaker para falhas de gateway
 * - Retry automático com backoff exponencial
 * - Failover entre gateways
 * - Cache de dados para performance
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas processamento de pagamentos
 * - O: Open/Closed - Extensível via implementações de gateway
 * - L: Liskov Substitution - Gateways intercambiáveis
 * - I: Interface Segregation - Separação de responsabilidades
 * - D: Dependency Inversion - Depende de abstrações
 */
class PaymentService implements ServiceInterface
{
    private array $gateways = [];
    private array $retryConfig = [
        'max_attempts' => 3,
        'base_delay' => 1000, // milliseconds
        'max_delay' => 10000,
        'backoff_factor' => 2.0,
    ];
    private array $circuitBreaker = [];

    public function __construct(
        private PaymentRepositoryInterface $repository,
        private Logger $logger,
        private CreditCardValidator $cardValidator,
        private CurrencyFormatter $currencyFormatter,
        private ?CacheItemPoolInterface $cache = null
    ) {
        // Inicialização simples - nova arquitetura híbrida
    }

    // ==============================================
    // SERVICE INTERFACE METHODS
    // ==============================================

    /**
     * Get service name
     */
    public function getName(): string
    {
        return 'payment_service';
    }

    /**
     * Get service version
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Check if service is healthy
     */
    public function isHealthy(): bool
    {
        try {
            // Verifica se há gateways disponíveis
            $availableGateways = $this->getAvailableGateways();
            if (empty($availableGateways)) {
                return false;
            }

            // Verifica conectividade básica com repositório
            if (!$this->repository) {
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('PaymentService health check failed', [
                'error' => $e->getMessage(),
                'service' => $this->getName()
            ]);
            return false;
        }
    }

    /**
     * Get service metrics and status
     */
    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available_gateways' => count($this->gateways),
            'circuit_breaker_states' => array_map(
                fn ($cb) => $cb['state'],
                $this->circuitBreaker
            ),
            'total_gateways' => count($this->gateways),
            'healthy_gateways' => count(array_filter(
                array_keys($this->gateways),
                fn ($name) => $this->isGatewayAvailable($name)
            )),
            'config' => $this->getConfig(),
            'timestamp' => time()
        ];
    }

    /**
     * Get service configuration
     */
    public function getConfig(): array
    {
        return [
            'retry_config' => $this->retryConfig,
            'registered_gateways' => array_keys($this->gateways),
            'circuit_breaker_config' => array_map(
                fn ($cb) => [
                    'state' => $cb['state'],
                    'threshold' => $cb['threshold'],
                    'timeout' => $cb['timeout'],
                    'failures' => $cb['failures']
                ],
                $this->circuitBreaker
            )
        ];
    }

    /**
     * Check if service is available
     */
    public function isAvailable(): bool
    {
        try {
            return $this->isHealthy() && !empty($this->getAvailableGateways());
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get service status information
     */
    public function getStatus(): array
    {
        $isHealthy = $this->isHealthy();

        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'available' => $isHealthy,
            'last_check' => time(),
            'metrics' => $this->getMetrics()
        ];
    }

    // ==============================================
    // BUSINESS LOGIC METHODS - GATEWAY MANAGEMENT
    // ==============================================

    /**
     * Registra gateway de pagamento
     */
    public function registerGateway(string $name, GatewayInterface $gateway): void
    {
        $this->gateways[$name] = $gateway;
        $this->circuitBreaker[$name] = [
            'failures' => 0,
            'last_failure' => null,
            'state' => 'closed', // closed, open, half-open
            'threshold' => 5,
            'timeout' => 60, // seconds
        ];

        $this->logger->info('Gateway registrado', [
            'gateway' => $name,
            'supported_methods' => $gateway->getSupportedMethods(),
            'supported_currencies' => $gateway->getSupportedCurrencies(),
        ]);
    }

    /**
     * Processa pagamento com gateway específico ou automático
     */
    public function processPayment(array $paymentData, ?string $preferredGateway = null): array
    {
        $this->validatePaymentData($paymentData);

        $gatewayName = $preferredGateway ?? $this->selectGateway($paymentData);
        $gateway = $this->getGateway($gatewayName);

        // Verifica circuit breaker
        if (!$this->isGatewayAvailable($gatewayName)) {
            throw new GatewayException("Gateway {$gatewayName} indisponível");
        }

        $paymentId = $this->generatePaymentId();
        $paymentData['id'] = $paymentId;
        $paymentData['gateway'] = $gatewayName;
        $paymentData['created_at'] = date('Y-m-d H:i:s');

        try {
            // Registra tentativa de pagamento
            $this->repository->create([
                'id' => $paymentId,
                'status' => 'processing',
                'gateway' => $gatewayName,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'BRL',
                'customer_id' => $paymentData['customer_id'] ?? null,
                'order_id' => $paymentData['order_id'] ?? null,
                'payment_data' => $paymentData,
                'created_at' => $paymentData['created_at'],
            ]);

            $result = $this->executeWithRetry(
                fn () => $gateway->processPayment($paymentData),
                $gatewayName
            );

            // Atualiza status do pagamento
            $this->repository->updateStatus($paymentId, $result['status'], $result);

            // Registra sucesso no circuit breaker
            $this->recordSuccess($gatewayName);

            $this->logger->info('Pagamento processado com sucesso', [
                'payment_id' => $paymentId,
                'gateway' => $gatewayName,
                'status' => $result['status'],
                'amount' => $paymentData['amount'],
            ]);

            return $result;

        } catch (\Throwable $e) {
            // Registra falha no circuit breaker
            $this->recordFailure($gatewayName);

            // Atualiza status do pagamento
            $this->repository->markAsFailed($paymentId, $e->getMessage(), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logger->error('Falha no processamento de pagamento', [
                'payment_id' => $paymentId,
                'gateway' => $gatewayName,
                'error' => $e->getMessage(),
                'amount' => $paymentData['amount'],
            ]);

            // Tenta failover se disponível
            if ($this->shouldAttemptFailover($gatewayName, $paymentData)) {
                return $this->attemptFailover($paymentData, $gatewayName);
            }

            throw new PaymentException(
                "Falha no processamento do pagamento: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Autoriza pagamento sem capturar
     */
    public function authorizePayment(array $paymentData, ?string $preferredGateway = null): array
    {
        $this->validatePaymentData($paymentData);

        $gatewayName = $preferredGateway ?? $this->selectGateway($paymentData);
        $gateway = $this->getGateway($gatewayName);

        if (!$this->isGatewayAvailable($gatewayName)) {
            throw new GatewayException("Gateway {$gatewayName} indisponível");
        }

        $paymentId = $this->generatePaymentId();
        $paymentData['id'] = $paymentId;

        try {
            $result = $this->executeWithRetry(
                fn () => $gateway->authorizePayment($paymentData),
                $gatewayName
            );

            // Registra autorização
            $this->repository->create([
                'id' => $paymentId,
                'status' => 'authorized',
                'gateway' => $gatewayName,
                'amount' => $paymentData['amount'],
                'currency' => $paymentData['currency'] ?? 'BRL',
                'authorization_id' => $result['authorization_id'] ?? null,
                'payment_data' => $paymentData,
                'gateway_data' => $result,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $this->recordSuccess($gatewayName);

            return $result;

        } catch (\Throwable $e) {
            $this->recordFailure($gatewayName);
            throw new PaymentException(
                "Falha na autorização do pagamento: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Captura pagamento autorizado
     */
    public function capturePayment(string $paymentId, ?float $amount = null): array
    {
        $payment = $this->repository->findById($paymentId);
        if (!$payment) {
            throw new PaymentException("Pagamento não encontrado: {$paymentId}");
        }

        if ($payment['status'] !== 'authorized') {
            throw new PaymentException("Pagamento não está autorizado para captura");
        }

        $gateway = $this->getGateway($payment['gateway']);
        $authorizationId = $payment['authorization_id'] ?? $payment['gateway_data']['authorization_id'] ?? null;

        if (!$authorizationId) {
            throw new PaymentException("ID de autorização não encontrado");
        }

        try {
            $result = $this->executeWithRetry(
                fn () => $gateway->capturePayment($authorizationId, $amount),
                $payment['gateway']
            );

            // Atualiza status para capturado
            $this->repository->markAsCaptured($paymentId, $result);

            $this->logger->info('Pagamento capturado com sucesso', [
                'payment_id' => $paymentId,
                'authorization_id' => $authorizationId,
                'amount' => $amount ?? $payment['amount'],
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->recordFailure($payment['gateway']);
            throw new PaymentException(
                "Falha na captura do pagamento: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Estorna pagamento
     */
    public function refundPayment(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        $payment = $this->repository->findById($paymentId);
        if (!$payment) {
            throw new PaymentException("Pagamento não encontrado: {$paymentId}");
        }

        if (!in_array($payment['status'], ['captured', 'paid'])) {
            throw new PaymentException("Pagamento não pode ser estornado");
        }

        // Verifica se já foi totalmente estornado
        $totalRefunded = $this->repository->getTotalRefunded($paymentId);
        $refundableAmount = $payment['amount'] - $totalRefunded;

        if ($refundableAmount <= 0) {
            throw new PaymentException("Pagamento já foi totalmente estornado");
        }

        $refundAmount = min($amount ?? $refundableAmount, $refundableAmount);
        $gateway = $this->getGateway($payment['gateway']);

        try {
            $gatewayPaymentId = $payment['gateway_data']['id'] ?? $payment['gateway_data']['payment_id'] ?? null;
            if (!$gatewayPaymentId) {
                throw new PaymentException("ID do pagamento no gateway não encontrado");
            }

            $result = $this->executeWithRetry(
                fn () => $gateway->refundPayment($gatewayPaymentId, $refundAmount, $reason),
                $payment['gateway']
            );

            // Registra refund
            $this->repository->addRefund($paymentId, [
                'amount' => $refundAmount,
                'reason' => $reason,
                'gateway_refund_id' => $result['refund_id'] ?? null,
                'gateway_data' => $result,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Atualiza status se totalmente estornado
            $newTotalRefunded = $totalRefunded + $refundAmount;
            if ($newTotalRefunded >= $payment['amount']) {
                $this->repository->markAsRefunded($paymentId, $newTotalRefunded, 'Totalmente estornado');
            }

            $this->logger->info('Refund processado com sucesso', [
                'payment_id' => $paymentId,
                'refund_amount' => $refundAmount,
                'reason' => $reason,
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->recordFailure($payment['gateway']);
            throw new PaymentException(
                "Falha no estorno do pagamento: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Cancela pagamento autorizado
     */
    public function cancelPayment(string $paymentId, string $reason = ''): array
    {
        $payment = $this->repository->findById($paymentId);
        if (!$payment) {
            throw new PaymentException("Pagamento não encontrado: {$paymentId}");
        }

        if ($payment['status'] !== 'authorized') {
            throw new PaymentException("Apenas pagamentos autorizados podem ser cancelados");
        }

        $gateway = $this->getGateway($payment['gateway']);
        $authorizationId = $payment['authorization_id'] ?? $payment['gateway_data']['authorization_id'] ?? null;

        if (!$authorizationId) {
            throw new PaymentException("ID de autorização não encontrado");
        }

        try {
            $result = $this->executeWithRetry(
                fn () => $gateway->cancelPayment($authorizationId, $reason),
                $payment['gateway']
            );

            // Atualiza status para cancelado
            $this->repository->markAsCancelled($paymentId, $reason);

            $this->logger->info('Pagamento cancelado com sucesso', [
                'payment_id' => $paymentId,
                'authorization_id' => $authorizationId,
                'reason' => $reason,
            ]);

            return $result;

        } catch (\Throwable $e) {
            $this->recordFailure($payment['gateway']);
            throw new PaymentException(
                "Falha no cancelamento do pagamento: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Obtém dados de um pagamento
     */
    public function getPayment(string $paymentId): ?array
    {
        $cacheKey = "payment:{$paymentId}";
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $payment = $this->repository->findById($paymentId);
        if ($payment) {
            $this->setCache($cacheKey, $payment, 300); // 5 minutos
        }

        return $payment;
    }

    /**
     * Lista pagamentos por filtros
     */
    public function listPayments(array $filters = []): array
    {
        return $this->repository->findByFilters($filters);
    }

    /**
     * Verifica status de uma transação no gateway
     */
    public function checkTransactionStatus(string $paymentId): array
    {
        $payment = $this->repository->findById($paymentId);
        if (!$payment) {
            throw new PaymentException("Pagamento não encontrado: {$paymentId}");
        }

        $gateway = $this->getGateway($payment['gateway']);
        $gatewayPaymentId = $payment['gateway_data']['id'] ?? $payment['gateway_data']['payment_id'] ?? null;

        if (!$gatewayPaymentId) {
            throw new PaymentException("ID do pagamento no gateway não encontrado");
        }

        try {
            $result = $gateway->checkTransactionStatus($gatewayPaymentId);

            // Atualiza dados do gateway se houver mudanças
            if ($result['status'] !== $payment['status']) {
                $this->repository->updateGatewayData($paymentId, $result);
                $this->repository->updateStatus($paymentId, $result['status']);
            }

            return $result;

        } catch (\Throwable $e) {
            throw new PaymentException(
                "Falha na verificação do status: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Obtém estatísticas de pagamentos
     */
    public function getPaymentStatistics(array $filters = []): array
    {
        $cacheKey = "payment_stats:" . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached) {
            return $cached;
        }

        $stats = $this->repository->getStatistics($filters);
        $this->setCache($cacheKey, $stats, 600); // 10 minutos

        return $stats;
    }

    /**
     * Obtém gateways disponíveis
     */
    public function getAvailableGateways(): array
    {
        $available = [];
        foreach ($this->gateways as $name => $gateway) {
            if ($this->isGatewayAvailable($name)) {
                $available[$name] = [
                    'name' => $gateway->getName(),
                    'version' => $gateway->getApiVersion(),
                    'methods' => $gateway->getSupportedMethods(),
                    'currencies' => $gateway->getSupportedCurrencies(),
                    'active' => $gateway->isActive(),
                ];
            }
        }
        return $available;
    }

    /**
     * Valida dados de pagamento
     */
    private function validatePaymentData(array $paymentData): void
    {
        $required = ['amount', 'payment_method'];
        foreach ($required as $field) {
            if (!isset($paymentData[$field])) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }
        }

        if ($paymentData['amount'] <= 0) {
            throw new InvalidArgumentException("Valor deve ser maior que zero");
        }

        // Valida dados do cartão se presente
        if ($paymentData['payment_method'] === 'credit_card' && isset($paymentData['card'])) {
            $this->cardValidator->validate($paymentData['card']);
        }
    }

    /**
     * Seleciona gateway automaticamente baseado nos dados
     */
    private function selectGateway(array $paymentData): string
    {
        $method = $paymentData['payment_method'];
        $currency = $paymentData['currency'] ?? 'BRL';

        // Busca gateway que suporte o método e moeda
        foreach ($this->gateways as $name => $gateway) {
            if ($this->isGatewayAvailable($name) &&
                $gateway->supportsMethod($method) &&
                $gateway->supportsCurrency($currency)) {
                return $name;
            }
        }

        throw new GatewayException("Nenhum gateway disponível para {$method} em {$currency}");
    }

    /**
     * Obtém gateway por nome
     */
    private function getGateway(string $name): GatewayInterface
    {
        if (!isset($this->gateways[$name])) {
            throw new GatewayException("Gateway não encontrado: {$name}");
        }

        return $this->gateways[$name];
    }

    /**
     * Verifica se gateway está disponível (circuit breaker)
     */
    private function isGatewayAvailable(string $gatewayName): bool
    {
        $breaker = $this->circuitBreaker[$gatewayName] ?? null;
        if (!$breaker) {
            return true;
        }

        if ($breaker['state'] === 'open') {
            // Verifica se timeout expirou
            if (time() - $breaker['last_failure'] > $breaker['timeout']) {
                $this->circuitBreaker[$gatewayName]['state'] = 'half-open';
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * Registra sucesso no circuit breaker
     */
    private function recordSuccess(string $gatewayName): void
    {
        $this->circuitBreaker[$gatewayName]['failures'] = 0;
        $this->circuitBreaker[$gatewayName]['state'] = 'closed';
    }

    /**
     * Registra falha no circuit breaker
     */
    private function recordFailure(string $gatewayName): void
    {
        $this->circuitBreaker[$gatewayName]['failures']++;
        $this->circuitBreaker[$gatewayName]['last_failure'] = time();

        if ($this->circuitBreaker[$gatewayName]['failures'] >= $this->circuitBreaker[$gatewayName]['threshold']) {
            $this->circuitBreaker[$gatewayName]['state'] = 'open';
        }
    }

    /**
     * Executa operação com retry e backoff exponencial
     */
    private function executeWithRetry(callable $operation, string $gatewayName): array
    {
        $attempts = 0;
        $delay = $this->retryConfig['base_delay'];

        while ($attempts < $this->retryConfig['max_attempts']) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $attempts++;

                if ($attempts >= $this->retryConfig['max_attempts']) {
                    throw $e;
                }

                // Backoff exponencial
                usleep($delay * 1000); // Convert to microseconds
                $delay = min(
                    $delay * $this->retryConfig['backoff_factor'],
                    $this->retryConfig['max_delay']
                );

                $this->logger->warning('Retry de operação', [
                    'gateway' => $gatewayName,
                    'attempt' => $attempts,
                    'delay' => $delay,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw new PaymentException("Falha após {$attempts} tentativas");
    }

    /**
     * Verifica se deve tentar failover
     */
    private function shouldAttemptFailover(string $failedGateway, array $paymentData): bool
    {
        $method = $paymentData['payment_method'];
        $currency = $paymentData['currency'] ?? 'BRL';

        // Busca outro gateway disponível
        foreach ($this->gateways as $name => $gateway) {
            if ($name !== $failedGateway &&
                $this->isGatewayAvailable($name) &&
                $gateway->supportsMethod($method) &&
                $gateway->supportsCurrency($currency)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tenta failover para outro gateway
     */
    private function attemptFailover(array $paymentData, string $failedGateway): array
    {
        $method = $paymentData['payment_method'];
        $currency = $paymentData['currency'] ?? 'BRL';

        foreach ($this->gateways as $name => $gateway) {
            if ($name !== $failedGateway &&
                $this->isGatewayAvailable($name) &&
                $gateway->supportsMethod($method) &&
                $gateway->supportsCurrency($currency)) {

                $this->logger->info('Tentando failover', [
                    'from' => $failedGateway,
                    'to' => $name,
                    'method' => $method,
                    'currency' => $currency,
                ]);

                try {
                    return $this->processPayment($paymentData, $name);
                } catch (\Throwable $e) {
                    $this->logger->error('Falha no failover', [
                        'gateway' => $name,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }
        }

        throw new PaymentException("Failover não foi possível - todos os gateways falharam");
    }

    /**
     * Gera ID único para pagamento
     */
    private function generatePaymentId(): string
    {
        return 'pay_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    // ==============================================
    // UTILITY METHODS FOR CACHE AND HELPERS
    // ==============================================

    /**
     * Get item from cache if available
     */
    private function getFromCache(string $key): mixed
    {
        if (!$this->cache) {
            return null;
        }

        try {
            $item = $this->cache->getItem($key);
            return $item->isHit() ? $item->get() : null;
        } catch (\Throwable $e) {
            $this->logger->warning('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Set item in cache with TTL
     */
    private function setCache(string $key, mixed $value, int $ttl = 300): void
    {
        if (!$this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            $this->cache->save($item);
        } catch (\Throwable $e) {
            $this->logger->warning('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }
}
