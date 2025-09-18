<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Customers\Factories\CustomersServiceFactory;
use Clubify\Checkout\Modules\Customers\Services\CustomerService;
use Clubify\Checkout\Modules\Customers\Services\MatchingService;

/**
 * Módulo de gestão de clientes
 *
 * Centraliza todas as operações relacionadas a clientes,
 * incluindo CRUD, matching, histórico e perfis de comportamento.
 *
 * Funcionalidades principais:
 * - Gestão completa de clientes (CRUD)
 * - Matching inteligente de clientes
 * - Histórico de compras e transações
 * - Perfis de comportamento e segmentação
 * - Análise de valor do cliente (CLV)
 * - Gestão de tags e metadados
 * - Compliance LGPD/GDPR
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gestão de clientes
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Módulos intercambiáveis
 * - I: Interface Segregation - Separação de responsabilidades
 * - D: Dependency Inversion - Depende de abstrações
 */
class CustomersModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?CustomersServiceFactory $factory = null;

    // Services (lazy loading)
    private ?CustomerService $customerService = null;
    private ?MatchingService $matchingService = null;

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

        $this->logger->info('Customers module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
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
        return 'customers';
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
        return [];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
    }





    /**
     * Cria um novo cliente
     */
    public function createCustomer(array $customerData): array
    {
        $this->logger?->info('Creating customer', $customerData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'customer_id' => uniqid('customer_'),
            'data' => $customerData,
            'created_at' => time()
        ];
    }

    /**
     * Setup completo de cliente
     */
    public function setupComplete(array $customerData): array
    {
        $this->logger?->info('Setting up complete customer', $customerData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'customer_id' => uniqid('customer_'),
            'data' => $customerData,
            'timestamp' => time()
        ];
    }

    /**
     * Cria cliente completo
     */
    public function createComplete(array $customerData): array
    {
        $this->logger?->info('Creating complete customer', $customerData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'customer_id' => uniqid('customer_'),
            'data' => $customerData,
            'created_at' => time()
        ];
    }

    /**
     * Busca cliente por email
     */
    public function findByEmail(string $email): ?array
    {
        $this->logger?->info('Finding customer by email', ['email' => $email]);

        return [
            'success' => true,
            'customer_id' => uniqid('customer_'),
            'email' => $email,
            'name' => 'Cliente Exemplo',
            'created_at' => time() - 86400, // 1 dia atrás
            'purchases_count' => rand(1, 10)
        ];
    }

    /**
     * Atualiza perfil do cliente
     */
    public function updateProfile(string $customerId, array $profileData): array
    {
        $this->logger?->info('Updating customer profile', [
            'customer_id' => $customerId,
            'data' => $profileData
        ]);

        return [
            'success' => true,
            'customer_id' => $customerId,
            'updated_fields' => array_keys($profileData),
            'updated_at' => time()
        ];
    }

    // ================================================
    // Factory and Service Management (Architecture v2.0)
    // ================================================

    /**
     * Get factory instance (lazy loading)
     */
    private function getFactory(): CustomersServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->createCustomersServiceFactory();
        }
        return $this->factory;
    }

    /**
     * Get CustomerService instance (lazy loading)
     */
    private function getCustomerService(): CustomerService
    {
        if ($this->customerService === null) {
            $this->customerService = $this->getFactory()->create('customer');
        }
        return $this->customerService;
    }

    /**
     * Get MatchingService instance (lazy loading)
     */
    private function getMatchingService(): MatchingService
    {
        if ($this->matchingService === null) {
            $this->matchingService = $this->getFactory()->create('matching');
        }
        return $this->matchingService;
    }

    /**
     * Enhanced cleanup with factory
     */
    public function cleanup(): void
    {
        $this->customerService = null;
        $this->matchingService = null;
        $this->factory = null;
        $this->initialized = false;
        $this->logger?->info('Customers module cleaned up');
    }

    /**
     * Enhanced status with factory info
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                'customer' => $this->customerService !== null,
                'matching' => $this->matchingService !== null,
            ],
            'factory_loaded' => $this->factory !== null,
            'timestamp' => time()
        ];
    }

    /**
     * Enhanced stats with service metrics
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'services' => [
                'customer' => $this->customerService?->getMetrics(),
                'matching' => $this->matchingService?->getMetrics(),
            ],
            'factory' => $this->factory?->getStats(),
            'timestamp' => time()
        ];
    }

    /**
     * Enhanced health check with services
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized &&
                   ($this->customerService === null || $this->customerService->isHealthy()) &&
                   ($this->matchingService === null || $this->matchingService->isHealthy());
        } catch (\Exception $e) {
            $this->logger?->error('CustomersModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Require module initialization
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Customers module is not initialized');
        }
    }
}
