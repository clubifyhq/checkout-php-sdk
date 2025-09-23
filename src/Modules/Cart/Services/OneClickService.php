<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Services;

use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

/**
 * Serviço de Checkout One-Click
 *
 * Especializado em operações de checkout one-click, permitindo
 * compras rápidas com dados de pagamento salvos.
 *
 * Funcionalidades:
 * - Processamento de checkout one-click
 * - Validação de dados de pagamento
 * - Gestão de métodos de pagamento salvos
 * - Processamento seguro de transações
 * - Analytics de conversão one-click
 *
 * Endpoints utilizados:
 * - POST /api/v1/cart/:id/one-click
 * - POST /api/v1/cart/:id/one-click/validate
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações one-click
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OneClickService
{
    private const CACHE_TTL = 300; // 5 minutos
    private const VALIDATION_TIMEOUT = 30; // 30 segundos

    // Status de processamento
    private const STATUS_PENDING = 'pending';
    private const STATUS_PROCESSING = 'processing';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_FAILED = 'failed';
    private const STATUS_CANCELLED = 'cancelled';

    // Métodos de pagamento suportados
    private const SUPPORTED_PAYMENT_METHODS = [
        'credit_card' => 'Cartão de Crédito',
        'debit_card' => 'Cartão de Débito',
        'pix' => 'PIX',
        'digital_wallet' => 'Carteira Digital',
        'bank_transfer' => 'Transferência Bancária',
        'crypto' => 'Criptomoeda'
    ];

    public function __construct(
        private CartRepositoryInterface $repository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private Configuration $config,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    // ===========================================
    // OPERAÇÕES PRINCIPAIS ONE-CLICK
    // ===========================================

    /**
     * Processa checkout one-click
     */
    public function process(string $cartId, array $paymentData): array
    {
        $this->logger->info('Processing one-click checkout', [
            'cart_id' => $cartId,
            'payment_method' => $paymentData['payment_method'] ?? null,
            'customer_id' => $paymentData['customer_id'] ?? null
        ]);

        // Validações pré-processamento
        $this->validateOneClickData($cartId, $paymentData);

        try {
            // Dispara evento de início
            $this->eventDispatcher->dispatch('cart.oneclick.started', [
                'cart_id' => $cartId,
                'payment_data' => $this->sanitizePaymentData($paymentData)
            ]);

            // Processa via API
            $result = $this->repository->processOneClick($cartId, $paymentData);

            // Verifica resultado
            if (!($result['success'] ?? false)) {
                throw new \Exception(
                    $result['error'] ?? 'Falha no processamento do checkout'
                );
            }

            // Cache do resultado
            $this->cacheTransactionResult($cartId, $result);

            // Dispara evento de sucesso
            $this->eventDispatcher->dispatch('cart.oneclick.completed', [
                'cart_id' => $cartId,
                'transaction_id' => $result['transaction_id'] ?? null,
                'order_id' => $result['order_id'] ?? null,
                'amount' => $result['amount'] ?? null
            ]);

            $this->logger->info('One-click checkout completed successfully', [
                'cart_id' => $cartId,
                'transaction_id' => $result['transaction_id'] ?? null,
                'order_id' => $result['order_id'] ?? null
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('One-click checkout failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
                'payment_method' => $paymentData['payment_method'] ?? null
            ]);

            // Dispara evento de falha
            $this->eventDispatcher->dispatch('cart.oneclick.failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage(),
                'payment_data' => $this->sanitizePaymentData($paymentData)
            ]);

            throw $e;
        }
    }

    /**
     * Valida dados para checkout one-click
     */
    public function validate(string $cartId, array $paymentData): array
    {
        $this->logger->debug('Validating one-click data', [
            'cart_id' => $cartId,
            'payment_method' => $paymentData['payment_method'] ?? null
        ]);

        // Verifica cache de validação
        $cacheKey = "oneclick_validation_{$cartId}";
        if ($this->cache->hasItem($cacheKey)) {
            $cachedResult = $this->cache->getItem($cacheKey)->get();
            if ($this->isValidationCacheValid($cachedResult, $paymentData)) {
                return $cachedResult;
            }
        }

        try {
            // Validações locais primeiro
            $this->validateOneClickData($cartId, $paymentData);

            // Valida via API
            $result = $this->repository->validateOneClick($cartId, $paymentData);

            // Adiciona validações locais ao resultado
            $result['local_validations'] = [
                'cart_exists' => true,
                'payment_method_supported' => $this->isPaymentMethodSupported(
                    $paymentData['payment_method'] ?? ''
                ),
                'customer_authenticated' => !empty($paymentData['customer_id']),
                'timestamp' => time()
            ];

            // Cache do resultado
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($result);
            $cacheItem->expiresAfter(self::VALIDATION_TIMEOUT);
            $this->cache->save($cacheItem);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('One-click validation failed', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    // ===========================================
    // OPERAÇÕES DE CONSULTA E STATUS
    // ===========================================

    /**
     * Verifica se carrinho é elegível para one-click
     */
    public function isEligible(string $cartId, string $customerId = null): bool
    {
        $this->logger->debug('Checking one-click eligibility', [
            'cart_id' => $cartId,
            'customer_id' => $customerId
        ]);

        try {
            // Busca carrinho
            $cart = $this->repository->find($cartId);
            if (!$cart) {
                return false;
            }

            // Verifica se carrinho não está vazio
            if (empty($cart['items']) || count($cart['items']) === 0) {
                return false;
            }

            // Verifica se carrinho está ativo
            if (($cart['status'] ?? '') !== 'active') {
                return false;
            }

            // Verifica se tem total válido
            $total = (float) ($cart['totals']['total'] ?? 0);
            if ($total <= 0) {
                return false;
            }

            // Se customer_id fornecido, verifica métodos salvos
            if ($customerId) {
                return $this->hasValidPaymentMethods($customerId);
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error checking one-click eligibility', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Obtém métodos de pagamento disponíveis para one-click
     */
    public function getAvailablePaymentMethods(string $customerId): array
    {
        $this->logger->debug('Fetching available payment methods', [
            'customer_id' => $customerId
        ]);

        $cacheKey = "oneclick_payment_methods_{$customerId}";

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        try {
            // Por ora, retorna métodos mockados até API estar disponível
            $availableMethods = $this->getMockPaymentMethods($customerId);

            // Cache do resultado
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($availableMethods);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $availableMethods;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching payment methods', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Obtém status de transação one-click
     */
    public function getTransactionStatus(string $transactionId): array
    {
        $this->logger->debug('Fetching transaction status', [
            'transaction_id' => $transactionId
        ]);

        $cacheKey = "oneclick_transaction_{$transactionId}";

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        try {
            // Busca via API específica ou através do carrinho
            // Por ora, retorna status básico
            $status = [
                'transaction_id' => $transactionId,
                'status' => self::STATUS_PROCESSING,
                'timestamp' => time()
            ];

            return $status;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching transaction status', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'transaction_id' => $transactionId,
                'status' => self::STATUS_FAILED,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    // ===========================================
    // OPERAÇÕES DE CONFIGURAÇÃO
    // ===========================================

    /**
     * Obtém configurações de one-click para organização
     */
    public function getOneClickConfig(): array
    {
        return [
            'enabled' => $this->config->get('one_click.enabled', true),
            'supported_methods' => self::SUPPORTED_PAYMENT_METHODS,
            'validation_timeout' => self::VALIDATION_TIMEOUT,
            'require_cvv' => $this->config->get('one_click.require_cvv', true),
            'require_address' => $this->config->get('one_click.require_address', false),
            'max_amount' => $this->config->get('one_click.max_amount', 10000.00),
            'fraud_checks' => $this->config->get('one_click.fraud_checks', true)
        ];
    }

    /**
     * Verifica se método de pagamento é suportado
     */
    public function isPaymentMethodSupported(string $paymentMethod): bool
    {
        return array_key_exists($paymentMethod, self::SUPPORTED_PAYMENT_METHODS);
    }

    /**
     * Obtém limites de one-click
     */
    public function getOneClickLimits(string $customerId = null): array
    {
        $config = $this->getOneClickConfig();

        return [
            'max_amount' => $config['max_amount'],
            'daily_limit' => $this->config->get('one_click.daily_limit', 50000.00),
            'monthly_limit' => $this->config->get('one_click.monthly_limit', 200000.00),
            'transaction_limit' => $this->config->get('one_click.transaction_limit', 10),
            'customer_specific' => $this->getCustomerSpecificLimits($customerId)
        ];
    }

    // ===========================================
    // MÉTODOS PRIVADOS DE VALIDAÇÃO
    // ===========================================

    /**
     * Valida dados completos para one-click
     */
    private function validateOneClickData(string $cartId, array $paymentData): void
    {
        // Verifica se carrinho existe
        $cart = $this->repository->find($cartId);
        if (!$cart) {
            throw new \InvalidArgumentException('Carrinho não encontrado');
        }

        // Verifica método de pagamento
        $paymentMethod = $paymentData['payment_method'] ?? '';
        if (!$this->isPaymentMethodSupported($paymentMethod)) {
            throw new \InvalidArgumentException('Método de pagamento não suportado');
        }

        // Verifica customer_id
        if (empty($paymentData['customer_id'])) {
            throw new \InvalidArgumentException('ID do cliente é obrigatório');
        }

        // Verifica se carrinho está elegível
        if (!$this->isEligible($cartId, $paymentData['customer_id'])) {
            throw new \InvalidArgumentException('Carrinho não elegível para one-click');
        }

        // Verifica limites
        $this->validateTransactionLimits($cart, $paymentData['customer_id']);
    }

    /**
     * Valida limites de transação
     */
    private function validateTransactionLimits(array $cart, string $customerId): void
    {
        $total = (float) ($cart['totals']['total'] ?? 0);
        $limits = $this->getOneClickLimits($customerId);

        if ($total > $limits['max_amount']) {
            throw new \InvalidArgumentException(
                "Valor excede limite de R$ " . number_format($limits['max_amount'], 2, ',', '.')
            );
        }

        // Outras validações de limite podem ser adicionadas aqui
    }

    /**
     * Verifica se cliente tem métodos de pagamento válidos
     */
    private function hasValidPaymentMethods(string $customerId): bool
    {
        $paymentMethods = $this->getAvailablePaymentMethods($customerId);
        return !empty($paymentMethods);
    }

    /**
     * Verifica se cache de validação ainda é válido
     */
    private function isValidationCacheValid(array $cachedResult, array $currentData): bool
    {
        $cacheTime = $cachedResult['timestamp'] ?? 0;
        $currentTime = time();

        // Cache expirou?
        if (($currentTime - $cacheTime) > self::VALIDATION_TIMEOUT) {
            return false;
        }

        // Método de pagamento mudou?
        $cachedMethod = $cachedResult['payment_data']['payment_method'] ?? '';
        $currentMethod = $currentData['payment_method'] ?? '';

        return $cachedMethod === $currentMethod;
    }

    // ===========================================
    // MÉTODOS UTILITÁRIOS
    // ===========================================

    /**
     * Sanitiza dados de pagamento para logs
     */
    private function sanitizePaymentData(array $paymentData): array
    {
        $sanitized = $paymentData;

        // Remove dados sensíveis
        unset($sanitized['card_number']);
        unset($sanitized['cvv']);
        unset($sanitized['card_token']);
        unset($sanitized['bank_account']);

        return $sanitized;
    }

    /**
     * Cache do resultado da transação
     */
    private function cacheTransactionResult(string $cartId, array $result): void
    {
        if (empty($result['transaction_id'])) {
            return;
        }

        $cacheKey = "oneclick_transaction_{$result['transaction_id']}";

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($result);
        $cacheItem->expiresAfter(3600); // 1 hora
        $this->cache->save($cacheItem);
    }

    /**
     * Obtém métodos de pagamento mockados (temporário)
     */
    private function getMockPaymentMethods(string $customerId): array
    {
        return [
            [
                'id' => 'card_1',
                'type' => 'credit_card',
                'brand' => 'visa',
                'last4' => '4242',
                'expires_at' => '12/25',
                'is_default' => true
            ],
            [
                'id' => 'pix_1',
                'type' => 'pix',
                'key' => 'customer@email.com',
                'is_default' => false
            ]
        ];
    }

    /**
     * Obtém limites específicos do cliente
     */
    private function getCustomerSpecificLimits(string $customerId = null): array
    {
        if (!$customerId) {
            return [];
        }

        // Por ora, retorna limites padrão
        return [
            'vip_status' => false,
            'increased_limits' => false
        ];
    }

    /**
     * Obtém estatísticas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => 'OneClickService',
            'cache_ttl' => self::CACHE_TTL,
            'validation_timeout' => self::VALIDATION_TIMEOUT,
            'supported_methods' => array_keys(self::SUPPORTED_PAYMENT_METHODS),
            'supported_statuses' => [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
                self::STATUS_COMPLETED,
                self::STATUS_FAILED,
                self::STATUS_CANCELLED
            ]
        ];
    }
}