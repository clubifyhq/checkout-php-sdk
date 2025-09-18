<?php

namespace Clubify\Checkout\Modules\Orders\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

// Services
use Clubify\Checkout\Modules\Orders\Services\OrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderStatusService;
use Clubify\Checkout\Modules\Orders\Services\UpsellOrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderAnalyticsService;

// Repositories
use Clubify\Checkout\Modules\Orders\Repositories\ApiOrderRepository;

/**
 * Orders Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Orders module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'order': Main order management service
 * - 'order_status': Order status management service
 * - 'upsell_order': Order upsell management service
 * - 'order_analytics': Order analytics and statistics service
 *
 * Repository types automatically created:
 * - 'order': ApiOrderRepository
 *
 * @package Clubify\Checkout\Modules\Orders\Factories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class OrdersServiceFactory implements FactoryInterface
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
            $this->logger->debug('Orders service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Orders service', [
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

            $this->logger->info('Orders service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Orders service', [
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
            'order',
            'order_status',
            'upsell_order',
            'order_analytics'
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

        $this->logger->info('OrdersServiceFactory cache cleared');
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
            case 'order':
                return $this->createOrderService($config);

            case 'order_status':
                return $this->createOrderStatusService($config);

            case 'upsell_order':
                return $this->createUpsellOrderService($config);

            case 'order_analytics':
                return $this->createOrderAnalyticsService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Order service with dependencies
     *
     * @param array $config Service configuration
     * @return OrderService Configured service instance
     */
    private function createOrderService(array $config): OrderService
    {
        return new OrderService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create OrderStatus service with dependencies
     *
     * @param array $config Service configuration
     * @return OrderStatusService Configured service instance
     */
    private function createOrderStatusService(array $config): OrderStatusService
    {
        return new OrderStatusService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create UpsellOrder service with dependencies
     *
     * @param array $config Service configuration
     * @return UpsellOrderService Configured service instance
     */
    private function createUpsellOrderService(array $config): UpsellOrderService
    {
        return new UpsellOrderService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create OrderAnalytics service with dependencies
     *
     * @param array $config Service configuration
     * @return OrderAnalyticsService Configured service instance
     */
    private function createOrderAnalyticsService(array $config): OrderAnalyticsService
    {
        return new OrderAnalyticsService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
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
            'order' => ApiOrderRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only 'order' is available."
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