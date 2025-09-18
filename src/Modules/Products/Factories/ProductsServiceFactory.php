<?php

namespace Clubify\Checkout\Modules\Products\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
// Services
use Clubify\Checkout\Modules\Products\Services\ProductService;
use Clubify\Checkout\Modules\Products\Services\OfferService;
use Clubify\Checkout\Modules\Products\Services\FlowService;
use Clubify\Checkout\Modules\Products\Services\LayoutService;
use Clubify\Checkout\Modules\Products\Services\ThemeService;
use Clubify\Checkout\Modules\Products\Services\OrderBumpService;
use Clubify\Checkout\Modules\Products\Services\PricingService;
use Clubify\Checkout\Modules\Products\Services\UpsellService;
// Repositories
use Clubify\Checkout\Modules\Products\Repositories\ApiProductRepository;

/**
 * Products Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Products module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'product': Main product management service
 * - 'offer': Offer and configuration service
 * - 'flow': Sales flow management service
 * - 'layout': Layout configuration service (special - no BaseService)
 * - 'theme': Theme configuration service (special - no BaseService)
 * - 'order_bump': Order bump conversion service
 * - 'pricing': Pricing strategy service
 * - 'upsell': Upsell management service
 *
 * Repository types automatically created:
 * - 'product': ApiProductRepository
 *
 * @package Clubify\Checkout\Modules\Products\Factories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ProductsServiceFactory implements FactoryInterface
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
    ) {
    }

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
            $this->logger->debug('Products service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Products service', [
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

            $this->logger->info('Products service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Products service', [
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
            'product',
            'offer',
            'flow',
            'layout',
            'theme',
            'order_bump',
            'pricing',
            'upsell'
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

        $this->logger->info('ProductsServiceFactory cache cleared');
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
            case 'product':
                return $this->createProductService($config);

            case 'offer':
                return $this->createOfferService($config);

            case 'flow':
                return $this->createFlowService($config);

            case 'layout':
                return $this->createLayoutService($config);

            case 'theme':
                return $this->createThemeService($config);

            case 'order_bump':
                return $this->createOrderBumpService($config);

            case 'pricing':
                return $this->createPricingService($config);

            case 'upsell':
                return $this->createUpsellService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Product service with dependencies
     *
     * @param array $config Service configuration
     * @return ProductService Configured service instance
     */
    private function createProductService(array $config): ProductService
    {
        return new ProductService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create Offer service with dependencies
     *
     * @param array $config Service configuration
     * @return OfferService Configured service instance
     */
    private function createOfferService(array $config): OfferService
    {
        return new OfferService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create Flow service with dependencies
     *
     * @param array $config Service configuration
     * @return FlowService Configured service instance
     */
    private function createFlowService(array $config): FlowService
    {
        return new FlowService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create Layout service with dependencies (special case - no BaseService)
     *
     * @param array $config Service configuration
     * @return LayoutService Configured service instance
     */
    private function createLayoutService(array $config): LayoutService
    {
        // LayoutService doesn't actually use SDK, so we can pass null
        return new LayoutService(
            null, // SDK not used
            $this->config,
            $this->logger
        );
    }

    /**
     * Create Theme service with dependencies (special case - no BaseService)
     *
     * @param array $config Service configuration
     * @return ThemeService Configured service instance
     */
    private function createThemeService(array $config): ThemeService
    {
        // ThemeService doesn't actually use SDK, so we can pass null
        return new ThemeService(
            null, // SDK not used
            $this->config,
            $this->logger
        );
    }

    /**
     * Create OrderBump service with dependencies
     *
     * @param array $config Service configuration
     * @return OrderBumpService Configured service instance
     */
    private function createOrderBumpService(array $config): OrderBumpService
    {
        return new OrderBumpService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create Pricing service with dependencies
     *
     * @param array $config Service configuration
     * @return PricingService Configured service instance
     */
    private function createPricingService(array $config): PricingService
    {
        return new PricingService(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    /**
     * Create Upsell service with dependencies
     *
     * @param array $config Service configuration
     * @return UpsellService Configured service instance
     */
    private function createUpsellService(array $config): UpsellService
    {
        return new UpsellService(
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
            'product' => ApiProductRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only 'product' is available."
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
