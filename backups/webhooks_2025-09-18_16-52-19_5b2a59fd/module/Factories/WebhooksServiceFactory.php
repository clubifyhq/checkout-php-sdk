<?php

namespace Clubify\Checkout\Modules\Webhooks\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

// Services
use Clubify\Checkout\Modules\Webhooks\Services\WebhookService;
use Clubify\Checkout\Modules\Webhooks\Services\ConfigService;
use Clubify\Checkout\Modules\Webhooks\Services\DeliveryService;
use Clubify\Checkout\Modules\Webhooks\Services\RetryService;
use Clubify\Checkout\Modules\Webhooks\Services\TestingService;

// Repositories
use Clubify\Checkout\Modules\Webhooks\Repositories\ApiWebhookRepository;

/**
 * Webhooks Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Webhooks module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'webhook': Main webhook management service
 * - 'config': Configuration management service
 * - 'delivery': Webhook delivery service
 * - 'retry': Retry management service
 * - 'testing': Testing and validation service
 *
 * Repository types automatically created:
 * - 'webhook': ApiWebhookRepository
 *
 * @package Clubify\Checkout\Modules\Webhooks\Factories
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class WebhooksServiceFactory implements FactoryInterface
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
            $this->logger->debug('Webhooks service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Webhooks service', [
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

            $this->logger->info('Webhooks service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Webhooks service', [
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
            'webhook',
            'config',
            'delivery',
            'retry',
            'testing'
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

        $this->logger->info('WebhooksServiceFactory cache cleared');
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
            case 'webhook':
                return $this->createWebhookService($config);

            case 'config':
                return $this->createConfigService($config);

            case 'delivery':
                return $this->createDeliveryService($config);

            case 'retry':
                return $this->createRetryService($config);

            case 'testing':
                return $this->createTestingService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Webhook service with dependencies
     *
     * @param array $config Service configuration
     * @return WebhookService Configured service instance
     */
    private function createWebhookService(array $config): WebhookService
    {
        $repository = $this->createRepository('webhook');

        $service = new WebhookService($repository);

        // Call parent constructor with BaseService dependencies
        $reflection = new \ReflectionClass($service);
        $parentConstructor = $reflection->getParentClass()->getConstructor();
        $parentConstructor->invoke(
            $service,
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $service;
    }

    /**
     * Create Config service with dependencies
     *
     * @param array $config Service configuration
     * @return ConfigService Configured service instance
     */
    private function createConfigService(array $config): ConfigService
    {
        $repository = $this->createRepository('webhook');

        $service = new ConfigService($repository);

        // Call parent constructor with BaseService dependencies
        $reflection = new \ReflectionClass($service);
        $parentConstructor = $reflection->getParentClass()->getConstructor();
        $parentConstructor->invoke(
            $service,
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $service;
    }

    /**
     * Create Delivery service with dependencies
     *
     * @param array $config Service configuration
     * @return DeliveryService Configured service instance
     */
    private function createDeliveryService(array $config): DeliveryService
    {
        $service = new DeliveryService();

        // Call parent constructor with BaseService dependencies
        $reflection = new \ReflectionClass($service);
        $parentConstructor = $reflection->getParentClass()->getConstructor();
        $parentConstructor->invoke(
            $service,
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $service;
    }

    /**
     * Create Retry service with dependencies
     *
     * @param array $config Service configuration
     * @return RetryService Configured service instance
     */
    private function createRetryService(array $config): RetryService
    {
        $deliveryService = $this->getService('delivery') ?? $this->create('delivery');
        $repository = $this->createRepository('webhook');

        $service = new RetryService($deliveryService, $repository);

        // Call parent constructor with BaseService dependencies
        $reflection = new \ReflectionClass($service);
        $parentConstructor = $reflection->getParentClass()->getConstructor();
        $parentConstructor->invoke(
            $service,
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $service;
    }

    /**
     * Create Testing service with dependencies
     *
     * @param array $config Service configuration
     * @return TestingService Configured service instance
     */
    private function createTestingService(array $config): TestingService
    {
        $deliveryService = $this->getService('delivery') ?? $this->create('delivery');
        $repository = $this->createRepository('webhook');

        $service = new TestingService($deliveryService, $repository);

        // Call parent constructor with BaseService dependencies
        $reflection = new \ReflectionClass($service);
        $parentConstructor = $reflection->getParentClass()->getConstructor();
        $parentConstructor->invoke(
            $service,
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $service;
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
            'webhook' => ApiWebhookRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only 'webhook' is available."
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
}