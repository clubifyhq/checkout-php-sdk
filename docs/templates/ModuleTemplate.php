<?php

/**
 * Template para Novos Módulos - Clubify Checkout SDK
 *
 * Este template deve ser usado como base para criar novos módulos seguindo
 * a arquitetura híbrida (Repository + Factory Pattern).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua {ModuleName} pelo nome do módulo (ex: OrderManagement)
 * 2. Substitua {Entity} pelo nome da entidade principal (ex: Order)
 * 3. Substitua {entity} pela versão lowercase (ex: order)
 * 4. Ajuste os métodos específicos do domínio
 * 5. Implemente os métodos abstratos
 *
 * EXEMPLO:
 * - {ModuleName} = OrderManagement
 * - {Entity} = Order
 * - {entity} = order
 */

namespace Clubify\Checkout\Modules\{ModuleName};

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\{ModuleName}\Factories\{ModuleName}ServiceFactory;
use Clubify\Checkout\Modules\{ModuleName}\Services\{Entity}Service;

/**
 * {ModuleName} Module
 *
 * Responsável por gerenciar todas as operações relacionadas a {Entity}s:
 * - CRUD operations para {Entity}s
 * - Business logic específica do domínio
 * - Integração com APIs externas
 * - Caching e performance optimization
 * - Event dispatching para auditoria
 *
 * @package Clubify\Checkout\Modules\{ModuleName}
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class {ModuleName}Module implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?{ModuleName}ServiceFactory $factory = null;

    // Services (lazy loading)
    private ?{Entity}Service ${entity}Service = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {}

    /**
     * Initialize the module with configuration and logger
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('{ModuleName} module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'tenant_id' => $this->config->getTenantId()
        ]);
    }

    /**
     * Check if module is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get module name
     */
    public function getName(): string
    {
        return '{entity}_management';
    }

    /**
     * Get module version
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Get module dependencies
     */
    public function getDependencies(): array
    {
        return []; // Add dependencies if needed
    }

    /**
     * Check if module is available
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
    }

    /**
     * Get module status information
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                '{entity}' => $this->{entity}Service !== null,
            ],
            'factory_stats' => $this->factory?->getStats(),
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup module resources
     */
    public function cleanup(): void
    {
        $this->{entity}Service = null;
        $this->factory = null;
        $this->initialized = false;
        $this->logger?->info('{ModuleName} module cleaned up');
    }

    /**
     * Check if module is healthy
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized &&
                   ($this->{entity}Service === null || $this->{entity}Service->isHealthy());
        } catch (\Exception $e) {
            $this->logger?->error('{ModuleName}Module health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get module statistics
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'services' => [
                '{entity}' => $this->{entity}Service?->getMetrics(),
            ],
            'factory_stats' => $this->factory?->getStats(),
            'timestamp' => time()
        ];
    }

    // ==============================================
    // BUSINESS METHODS - Customize for your domain
    // ==============================================

    /**
     * Create a new {entity}
     *
     * @param array ${entity}Data {Entity} data
     * @return array Result with success status and {entity} data
     * @throws {Entity}ValidationException When validation fails
     * @throws \Exception When creation fails
     */
    public function create{Entity}(array ${entity}Data): array
    {
        $this->requireInitialized();
        return $this->get{Entity}Service()->create{Entity}(${entity}Data);
    }

    /**
     * Get {entity} by ID
     *
     * @param string ${entity}Id {Entity} ID
     * @return array Result with success status and {entity} data
     * @throws {Entity}NotFoundException When {entity} not found
     * @throws \Exception When retrieval fails
     */
    public function get{Entity}(string ${entity}Id): array
    {
        $this->requireInitialized();
        return $this->get{Entity}Service()->get{Entity}(${entity}Id);
    }

    /**
     * Update existing {entity}
     *
     * @param string ${entity}Id {Entity} ID
     * @param array ${entity}Data Updated {entity} data
     * @return array Result with success status and updated {entity} data
     * @throws {Entity}NotFoundException When {entity} not found
     * @throws {Entity}ValidationException When validation fails
     * @throws \Exception When update fails
     */
    public function update{Entity}(string ${entity}Id, array ${entity}Data): array
    {
        $this->requireInitialized();
        return $this->get{Entity}Service()->update{Entity}(${entity}Id, ${entity}Data);
    }

    /**
     * Delete {entity}
     *
     * @param string ${entity}Id {Entity} ID
     * @return array Result with success status and deletion timestamp
     * @throws {Entity}NotFoundException When {entity} not found
     * @throws \Exception When deletion fails
     */
    public function delete{Entity}(string ${entity}Id): array
    {
        $this->requireInitialized();
        return $this->get{Entity}Service()->delete{Entity}(${entity}Id);
    }

    /**
     * List {entity}s with optional filters
     *
     * @param array $filters Optional filters
     * @return array Result with success status, {entity}s array and pagination
     * @throws \Exception When listing fails
     */
    public function list{Entity}s(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->get{Entity}Service()->list{Entity}s($filters);
    }

    // Add more domain-specific methods here...

    // ==============================================
    // PRIVATE METHODS - Factory and Service Management
    // ==============================================

    /**
     * Get or create the factory instance
     */
    private function getFactory(): {ModuleName}ServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->create{ModuleName}ServiceFactory();
        }
        return $this->factory;
    }

    /**
     * Get or create the {entity} service instance
     */
    private function get{Entity}Service(): {Entity}Service
    {
        if ($this->{entity}Service === null) {
            $this->{entity}Service = $this->getFactory()->create('{entity}');
        }
        return $this->{entity}Service;
    }

    /**
     * Ensure module is initialized before operations
     *
     * @throws \RuntimeException When module is not initialized
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('{ModuleName} module is not initialized');
        }
    }
}