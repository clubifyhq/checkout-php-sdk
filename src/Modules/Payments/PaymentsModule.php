<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Payments;

use ClubifyCheckout\Contracts\ModuleInterface;
use ClubifyCheckout\Modules\Payments\Contracts\PaymentRepositoryInterface;
use ClubifyCheckout\Modules\Payments\Contracts\CardRepositoryInterface;
use ClubifyCheckout\Modules\Payments\Services\PaymentService;
use ClubifyCheckout\Modules\Payments\Services\CardService;
use ClubifyCheckout\Modules\Payments\Services\TokenizationService;
use ClubifyCheckout\Modules\Payments\Services\GatewayService;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Módulo de Pagamentos
 *
 * Gerencia processamento de pagamentos, gateways múltiplos,
 * tokenização de cartões e segurança PCI-DSS.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Orquestra apenas operações de pagamento
 * - O: Open/Closed - Extensível via novos gateways
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Interfaces específicas por funcionalidade
 * - D: Dependency Inversion - Depende de abstrações
 */
class PaymentsModule implements ModuleInterface
{
    private ?PaymentService $paymentService = null;
    private ?CardService $cardService = null;
    private ?TokenizationService $tokenizationService = null;
    private ?GatewayService $gatewayService = null;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository,
        private CardRepositoryInterface $cardRepository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private array $config = []
    ) {}

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'payments';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se o módulo está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Obtém dependências do módulo
     */
    public function getDependencies(): array
    {
        return ['checkout', 'customers'];
    }

    /**
     * Inicializa o módulo
     */
    public function initialize(): void
    {
        $this->logger->info('Inicializando PaymentsModule', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
        ]);

        // Configurações específicas do módulo
        $this->configureServices();

        // Valida configurações dos gateways
        $this->validateGatewayConfigs();

        $this->logger->info('PaymentsModule inicializado com sucesso');
    }

    /**
     * Configura os serviços do módulo
     */
    private function configureServices(): void
    {
        // Configurações específicas para cada serviço
        $paymentConfig = $this->config['payment'] ?? [];
        $cardConfig = $this->config['card'] ?? [];
        $tokenizationConfig = $this->config['tokenization'] ?? [];
        $gatewayConfig = $this->config['gateways'] ?? [];

        $this->logger->debug('Serviços do PaymentsModule configurados', [
            'payment_config' => !empty($paymentConfig),
            'card_config' => !empty($cardConfig),
            'tokenization_config' => !empty($tokenizationConfig),
            'gateway_config' => !empty($gatewayConfig),
            'available_gateways' => array_keys($gatewayConfig)
        ]);
    }

    /**
     * Valida configurações dos gateways
     */
    private function validateGatewayConfigs(): void
    {
        $gateways = $this->config['gateways'] ?? [];

        if (empty($gateways)) {
            throw new \InvalidArgumentException('Pelo menos um gateway deve ser configurado');
        }

        foreach ($gateways as $name => $config) {
            if (empty($config['enabled']) || !$config['enabled']) {
                continue;
            }

            // Valida configurações obrigatórias
            $required = ['api_key', 'environment'];
            foreach ($required as $field) {
                if (empty($config[$field])) {
                    throw new \InvalidArgumentException("Configuração '{$field}' obrigatória para gateway '{$name}'");
                }
            }
        }
    }

    /**
     * Obtém o serviço de pagamentos (lazy loading)
     */
    public function payments(): PaymentService
    {
        if ($this->paymentService === null) {
            $this->paymentService = new PaymentService(
                $this->paymentRepository,
                $this->gateways(),
                $this->logger,
                $this->cache,
                $this->config['payment'] ?? []
            );
        }

        return $this->paymentService;
    }

    /**
     * Obtém o serviço de cartões (lazy loading)
     */
    public function cards(): CardService
    {
        if ($this->cardService === null) {
            $this->cardService = new CardService(
                $this->cardRepository,
                $this->tokenization(),
                $this->logger,
                $this->cache,
                $this->config['card'] ?? []
            );
        }

        return $this->cardService;
    }

    /**
     * Obtém o serviço de tokenização (lazy loading)
     */
    public function tokenization(): TokenizationService
    {
        if ($this->tokenizationService === null) {
            $this->tokenizationService = new TokenizationService(
                $this->logger,
                $this->cache,
                $this->config['tokenization'] ?? []
            );
        }

        return $this->tokenizationService;
    }

    /**
     * Obtém o serviço de gateways (lazy loading)
     */
    public function gateways(): GatewayService
    {
        if ($this->gatewayService === null) {
            $this->gatewayService = new GatewayService(
                $this->logger,
                $this->cache,
                $this->config['gateways'] ?? []
            );
        }

        return $this->gatewayService;
    }

    /**
     * Processa um pagamento
     */
    public function processPayment(array $paymentData): array
    {
        $this->logger->info('Processando pagamento', [
            'amount' => $paymentData['amount'] ?? null,
            'currency' => $paymentData['currency'] ?? null,
            'method' => $paymentData['method'] ?? null
        ]);

        return $this->payments()->process($paymentData);
    }

    /**
     * Tokeniza cartão de crédito
     */
    public function tokenizeCard(array $cardData): array
    {
        $this->logger->info('Tokenizando cartão', [
            'last_four' => substr($cardData['number'] ?? '', -4),
            'brand' => $cardData['brand'] ?? null
        ]);

        return $this->tokenization()->tokenizeCard($cardData);
    }

    /**
     * Salva cartão do cliente
     */
    public function saveCard(string $customerId, array $cardData): array
    {
        $this->logger->info('Salvando cartão do cliente', [
            'customer_id' => $customerId,
            'last_four' => substr($cardData['number'] ?? '', -4)
        ]);

        return $this->cards()->save($customerId, $cardData);
    }

    /**
     * Lista cartões do cliente
     */
    public function getCustomerCards(string $customerId): array
    {
        return $this->cards()->findByCustomer($customerId);
    }

    /**
     * Remove cartão do cliente
     */
    public function removeCard(string $cardId): bool
    {
        $this->logger->info('Removendo cartão', [
            'card_id' => $cardId
        ]);

        return $this->cards()->delete($cardId);
    }

    /**
     * Estorna um pagamento
     */
    public function refundPayment(string $paymentId, ?float $amount = null, string $reason = ''): array
    {
        $this->logger->info('Estornando pagamento', [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'reason' => $reason
        ]);

        return $this->payments()->refund($paymentId, $amount, $reason);
    }

    /**
     * Captura um pagamento autorizado
     */
    public function capturePayment(string $paymentId, ?float $amount = null): array
    {
        $this->logger->info('Capturando pagamento', [
            'payment_id' => $paymentId,
            'amount' => $amount
        ]);

        return $this->payments()->capture($paymentId, $amount);
    }

    /**
     * Cancela um pagamento autorizado
     */
    public function cancelPayment(string $paymentId, string $reason = ''): array
    {
        $this->logger->info('Cancelando pagamento', [
            'payment_id' => $paymentId,
            'reason' => $reason
        ]);

        return $this->payments()->cancel($paymentId, $reason);
    }

    /**
     * Busca pagamento por ID
     */
    public function getPayment(string $paymentId): ?array
    {
        return $this->payments()->find($paymentId);
    }

    /**
     * Lista pagamentos por filtros
     */
    public function listPayments(array $filters = []): array
    {
        return $this->payments()->list($filters);
    }

    /**
     * Obtém gateways disponíveis
     */
    public function getAvailableGateways(): array
    {
        return $this->gateways()->getAvailable();
    }

    /**
     * Obtém gateway padrão
     */
    public function getDefaultGateway(): string
    {
        return $this->gateways()->getDefault();
    }

    /**
     * Testa conectividade com gateway
     */
    public function testGateway(string $gatewayName): array
    {
        $this->logger->info('Testando gateway', [
            'gateway' => $gatewayName
        ]);

        return $this->gateways()->test($gatewayName);
    }

    /**
     * Obtém métodos de pagamento suportados
     */
    public function getSupportedPaymentMethods(): array
    {
        return $this->gateways()->getSupportedMethods();
    }

    /**
     * Valida dados de cartão
     */
    public function validateCard(array $cardData): array
    {
        return $this->cards()->validate($cardData);
    }

    /**
     * Detecta bandeira do cartão
     */
    public function detectCardBrand(string $cardNumber): string
    {
        return $this->cards()->detectBrand($cardNumber);
    }

    /**
     * Obtém configurações de gateway
     */
    public function getGatewayConfig(string $gatewayName): array
    {
        return $this->gateways()->getConfig($gatewayName);
    }

    /**
     * Atualiza configurações de gateway
     */
    public function updateGatewayConfig(string $gatewayName, array $config): bool
    {
        $this->logger->info('Atualizando configuração de gateway', [
            'gateway' => $gatewayName
        ]);

        return $this->gateways()->updateConfig($gatewayName, $config);
    }

    /**
     * Obtém estatísticas de pagamentos
     */
    public function getPaymentStats(array $filters = []): array
    {
        return $this->payments()->getStatistics($filters);
    }

    /**
     * Obtém relatório de transações
     */
    public function getTransactionReport(array $filters = []): array
    {
        return $this->payments()->getTransactionReport($filters);
    }

    /**
     * Webhook de gateway
     */
    public function handleWebhook(string $gatewayName, array $payload, array $headers = []): array
    {
        $this->logger->info('Processando webhook de gateway', [
            'gateway' => $gatewayName,
            'payload_keys' => array_keys($payload)
        ]);

        return $this->gateways()->handleWebhook($gatewayName, $payload, $headers);
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'enabled' => $this->isEnabled(),
            'services' => [
                'payment_service' => $this->paymentService !== null,
                'card_service' => $this->cardService !== null,
                'tokenization_service' => $this->tokenizationService !== null,
                'gateway_service' => $this->gatewayService !== null
            ],
            'dependencies' => $this->getDependencies(),
            'gateways' => [
                'available' => $this->getAvailableGateways(),
                'default' => $this->getDefaultGateway(),
                'supported_methods' => $this->getSupportedPaymentMethods()
            ]
        ];
    }

    /**
     * Limpa cache do módulo
     */
    public function clearCache(): bool
    {
        try {
            $this->cache->clear();

            $this->logger->info('Cache do PaymentsModule limpo com sucesso');

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erro ao limpar cache do PaymentsModule', [
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtém configuração do módulo
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Atualiza configuração do módulo
     */
    public function updateConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);

        $this->logger->info('Configuração do PaymentsModule atualizada', [
            'config_keys' => array_keys($config)
        ]);

        // Reconfigura serviços com nova configuração
        $this->configureServices();

        // Revalida configurações dos gateways
        $this->validateGatewayConfigs();
    }
}