<?php

/**
 * Template para Factory Implementation - Clubify Checkout SDK
 *
 * Este template implementa o Factory Pattern para criar e gerenciar services
 * e repositories do módulo usando dependency injection e singleton pattern.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua {ModuleName} pelo nome do módulo (ex: OrderManagement)
 * 2. Substitua {Entity} pelo nome da entidade (ex: Order)
 * 3. Substitua {entity} pela versão lowercase (ex: order)
 * 4. Adicione os tipos de service suportados
 * 5. Implemente o mapping de repositories
 *
 * EXEMPLO:
 * - {ModuleName} = OrderManagement
 * - {Entity} = Order
 * - {entity} = order
 */

namespace Clubify\Checkout\Modules\{ModuleName}\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

// Services
use Clubify\Checkout\Modules\{ModuleName}\Services\{Entity}Service;
// Add more services here as needed

// Repositories
use Clubify\Checkout\Modules\{ModuleName}\Repositories\Api{Entity}Repository;
// Add more repositories here as needed

/**
 * {ModuleName} Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the {ModuleName} module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - '{entity}': Main {entity} management service
 * - Add more service types here
 *
 * Repository types automatically created:
 * - '{entity}': Api{Entity}Repository
 * - Add more repository types here
 *
 * @package Clubify\Checkout\Modules\{ModuleName}\Factories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class {ModuleName}ServiceFactory implements FactoryInterface
{
    /**
     * Cache of created services (singleton pattern)
     */
    private array $services = [];

    /**
     * Cache of created repositories (singleton pattern)
     */
    private array $repositories = [];

    /**
     * Statistics tracking
     */
    private array $stats = [
        'services_created' => 0,
        'repositories_created' => 0,
        'created_service_types' => [],
        'created_repository_types' => []
    ];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Create service by type with dependency injection
     *
     * @param string $type Service type
     * @param array $config Optional service configuration
     * @return object Service instance
     * @throws \InvalidArgumentException When service type not supported
     */
    public function create(string $type, array $config = []): object
    {
        // Return existing service if already created (singleton)
        if (isset($this->services[$type])) {
            $this->logger->debug('{ModuleName} service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating {ModuleName} service', [
            'type' => $type,
            'config' => $config
        ]);

        try {
            // Create service based on type
            $service = $this->createServiceByType($type, $config);

            // Cache service for reuse
            $this->services[$type] = $service;

            // Update statistics
            $this->updateStats('service', $type);

            $this->logger->info('{ModuleName} service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create {ModuleName} service', [
                'type' => $type,
                'error' => $e->getMessage(),
                'config' => $config
            ]);
            throw $e;
        }
    }

    /**
     * Get supported service types
     *
     * @return array Array of supported service types
     */
    public function getSupportedTypes(): array
    {
        return [
            '{entity}',
            // Add more supported service types here
        ];
    }

    /**
     * Check if service type is supported
     *
     * @param string $type Service type to check
     * @return bool True if supported
     */
    public function isTypeSupported(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes());
    }

    /**
     * Clear service cache (useful for testing and cleanup)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->services = [];
        $this->repositories = [];

        $this->logger->info('{ModuleName}ServiceFactory cache cleared');
    }

    /**
     * Get factory statistics
     *
     * @return array Factory statistics and metrics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'supported_types' => count($this->getSupportedTypes()),
            'cached_services' => count($this->services),
            'cached_repositories' => count($this->repositories),
            'types' => $this->getSupportedTypes(),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time()
        ]);
    }

    /**
     * Get service instance without creating if not exists
     *
     * @param string $type Service type
     * @return object|null Service instance or null if not created
     */
    public function getService(string $type): ?object
    {
        return $this->services[$type] ?? null;
    }

    /**
     * Check if service is already created
     *
     * @param string $type Service type
     * @return bool True if service exists in cache
     */
    public function hasService(string $type): bool
    {
        return isset($this->services[$type]);
    }

    /**
     * Destroy specific service instance
     *
     * @param string $type Service type to destroy
     * @return bool True if service was destroyed
     */
    public function destroyService(string $type): bool
    {
        if (isset($this->services[$type])) {
            unset($this->services[$type]);
            $this->logger->debug('{ModuleName} service destroyed', ['type' => $type]);
            return true;
        }
        return false;
    }

    // ==============================================
    // PRIVATE METHODS - Service Creation Logic
    // ==============================================

    /**
     * Create service instance by type
     *
     * @param string $type Service type
     * @param array $config Service configuration
     * @return object Created service instance
     * @throws \InvalidArgumentException When type not supported
     */
    private function createServiceByType(string $type, array $config): object
    {
        switch ($type) {
            case '{entity}':
                return $this->create{Entity}Service($config);

            // Add more service types here:
            // case 'other_service':
            //     return $this->createOtherService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create {Entity} service with dependencies
     *
     * @param array $config Service configuration
     * @return {Entity}Service Configured service instance
     */
    private function create{Entity}Service(array $config): {Entity}Service
    {
        $repository = $this->createRepository('{entity}');
        return new {Entity}Service($repository, $this->logger);
    }

    /**
     * Create repository by type with dependency injection
     *
     * @param string $type Repository type
     * @return object Repository instance
     * @throws \InvalidArgumentException When repository type not supported
     */
    private function createRepository(string $type): object
    {
        // Return existing repository if already created (singleton)
        if (isset($this->repositories[$type])) {
            return $this->repositories[$type];
        }

        $this->logger->debug('Creating repository', [
            'type' => $type,
            'factory' => get_class($this)
        ]);

        try {
            // Get repository class name
            $repositoryClass = $this->resolveRepositoryClass($type);

            // Create repository with dependency injection
            $repository = new $repositoryClass(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            // Cache repository for reuse
            $this->repositories[$type] = $repository;

            // Update statistics
            $this->updateStats('repository', $type);

            $this->logger->debug('Repository created', [
                'type' => $type,
                'class' => $repositoryClass
            ]);

            return $repository;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create repository', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Resolve repository class name from type
     *
     * @param string $type Repository type
     * @return string Repository class name
     * @throws \InvalidArgumentException When type not supported
     */
    private function resolveRepositoryClass(string $type): string
    {
        $mapping = [
            '{entity}' => Api{Entity}Repository::class,
            // Add more repository mappings here:
            // 'other_entity' => ApiOtherEntityRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only '{entity}' is available."
            );
        }

        return $mapping[$type];
    }

    /**
     * Update internal statistics
     *
     * @param string $itemType Type of item ('service' or 'repository')
     * @param string $type Specific type created
     */
    private function updateStats(string $itemType, string $type): void
    {
        if ($itemType === 'service') {
            $this->stats['services_created']++;
            if (!in_array($type, $this->stats['created_service_types'])) {
                $this->stats['created_service_types'][] = $type;
            }
        } elseif ($itemType === 'repository') {
            $this->stats['repositories_created']++;
            if (!in_array($type, $this->stats['created_repository_types'])) {
                $this->stats['created_repository_types'][] = $type;
            }
        }
    }

    /**
     * Get repository instance without creating if not exists
     *
     * @param string $type Repository type
     * @return object|null Repository instance or null
     */
    public function getRepository(string $type): ?object
    {
        return $this->repositories[$type] ?? null;
    }

    /**
     * Get all supported repository types
     *
     * @return array Array of supported repository types
     */
    public function getSupportedRepositoryTypes(): array
    {
        return [
            '{entity}',
            // Add more repository types here
        ];
    }

    /**
     * Validate factory configuration
     *
     * @return bool True if configuration is valid
     */
    public function validateConfiguration(): bool
    {
        try {
            // Check if all required dependencies are available
            if (!$this->config || !$this->logger || !$this->httpClient) {
                return false;
            }

            // Test basic functionality
            $supportedTypes = $this->getSupportedTypes();
            if (empty($supportedTypes)) {
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Factory configuration validation failed', [
                'error' => $e->getMessage(),
                'factory' => get_class($this)
            ]);
            return false;
        }
    }
}