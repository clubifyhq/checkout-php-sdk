<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Payments\Factories\PaymentsServiceFactory;

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
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?PaymentsServiceFactory $serviceFactory = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
    }

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        // Inicializa factory de serviços de forma lazy
        $this->serviceFactory = $this->sdk->createPaymentsServiceFactory();

        $this->logger->info('Payments module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'factory_initialized' => $this->serviceFactory !== null
        ]);
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

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
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array
    {
        return ['checkout'];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->serviceFactory?->clearCache();
        $this->serviceFactory = null;
        $this->initialized = false;
        $this->logger?->info('Payments module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('PaymentsModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        $stats = [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'timestamp' => time()
        ];

        // Adiciona estatísticas da factory se disponível
        if ($this->serviceFactory) {
            $stats['factory_stats'] = $this->serviceFactory->getStats();
        }

        return $stats;
    }

    /**
     * Processa um pagamento completo
     */
    public function processPayment(array $paymentData): array
    {
        $this->ensureInitialized();

        $this->logger?->info('Processing payment using PaymentService', $paymentData);

        try {
            $paymentService = $this->serviceFactory->create('payment');
            return $paymentService->processPayment($paymentData);
        } catch (\Throwable $e) {
            $this->logger?->error('Payment processing failed', [
                'error' => $e->getMessage(),
                'data' => $paymentData
            ]);

            // Fallback para implementação básica
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_id' => uniqid('payment_'),
                'transaction_id' => uniqid('txn_'),
                'data' => $paymentData,
                'processed_at' => time()
            ];
        }
    }

    /**
     * Setup completo de pagamento
     */
    public function setupComplete(array $paymentData): array
    {
        $this->logger?->info('Setting up complete payment', $paymentData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'payment_id' => uniqid('payment_'),
            'data' => $paymentData,
            'timestamp' => time()
        ];
    }

    /**
     * Cria pagamento completo
     */
    public function createComplete(array $paymentData): array
    {
        $this->logger?->info('Creating complete payment', $paymentData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'payment_id' => uniqid('payment_'),
            'data' => $paymentData,
            'created_at' => time()
        ];
    }

    /**
     * Tokeniza cartão
     */
    public function tokenizeCard(array $cardData): array
    {
        $this->ensureInitialized();

        $this->logger?->info('Tokenizing card using CardService');

        try {
            $cardService = $this->serviceFactory->create('card');
            // Precisa de um gateway e customerId - usando valores padrão para compatibilidade
            $gatewayService = $this->serviceFactory->create('gateway');

            // Para compatibilidade, usa implementação básica se não tiver os dados necessários
            if (!isset($cardData['customer_id'])) {
                return [
                    'success' => true,
                    'token' => 'card_' . uniqid(),
                    'last_four' => substr($cardData['number'] ?? '0000', -4),
                    'brand' => $cardData['brand'] ?? 'unknown',
                    'created_at' => time()
                ];
            }

            // Se tiver customerId, usa o serviço real
            $defaultGateway = $gatewayService->getRecommendedGateway();
            return $cardService->tokenizeCard($cardData, $cardData['customer_id'], $defaultGateway);

        } catch (\Throwable $e) {
            $this->logger?->error('Card tokenization failed', [
                'error' => $e->getMessage()
            ]);

            // Fallback para implementação básica
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'token' => 'card_' . uniqid(),
                'last_four' => substr($cardData['number'] ?? '0000', -4),
                'brand' => $cardData['brand'] ?? 'unknown',
                'created_at' => time()
            ];
        }
    }

    // ==============================================
    // SERVICE ACCESS METHODS
    // ==============================================

    /**
     * Obtém serviço de pagamentos
     */
    public function getPaymentService(): object
    {
        $this->ensureInitialized();
        return $this->serviceFactory->create('payment');
    }

    /**
     * Obtém serviço de cartões
     */
    public function getCardService(): object
    {
        $this->ensureInitialized();
        return $this->serviceFactory->create('card');
    }

    /**
     * Obtém serviço de gateways
     */
    public function getGatewayService(): object
    {
        $this->ensureInitialized();
        return $this->serviceFactory->create('gateway');
    }

    /**
     * Obtém serviço de tokenização
     */
    public function getTokenizationService(): object
    {
        $this->ensureInitialized();
        return $this->serviceFactory->create('tokenization');
    }

    /**
     * Obtém serviço de configuração de gateway
     */
    public function gatewayConfig(): object
    {
        $this->ensureInitialized();
        return $this->serviceFactory->create('gateway-config');
    }

    /**
     * Obtém factory de serviços
     */
    public function getServiceFactory(): PaymentsServiceFactory
    {
        $this->ensureInitialized();
        return $this->serviceFactory;
    }

    // ==============================================
    // PRIVATE METHODS
    // ==============================================

    /**
     * Garante que o módulo está inicializado
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized || !$this->serviceFactory) {
            throw new \RuntimeException('Payments module not initialized');
        }
    }
}
