<?php

namespace Clubify\Checkout\Modules\Subscriptions\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;
// Services
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Subscriptions\Services\BillingService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionMetricsService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionLifecycleService;
// Repositories
use Clubify\Checkout\Modules\Subscriptions\Repositories\ApiSubscriptionRepository;

/**
 * Subscriptions Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Subscriptions module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'subscription': Main subscription management service
 * - 'subscription_plan': Plan management service
 * - 'billing': Billing and payment management service
 * - 'subscription_metrics': Analytics and metrics service
 * - 'subscription_lifecycle': Lifecycle management service (pause, cancel, upgrade)
 *
 * Repository types automatically created:
 * - 'subscription': ApiSubscriptionRepository
 *
 * @package Clubify\Checkout\Modules\Subscriptions\Factories
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class SubscriptionsServiceFactory implements FactoryInterface
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
        private EventDispatcherInterface $eventDispatcher,
        private ClubifyCheckoutSDK $sdk
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
            $this->logger->debug('Subscriptions service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Subscriptions service', [
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

            $this->logger->info('Subscriptions service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Subscriptions service', [
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
            'subscription',
            'subscription_plan',
            'billing',
            'subscription_metrics',
            'subscription_lifecycle'
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

        $this->logger->info('SubscriptionsServiceFactory cache cleared');
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
            case 'subscription':
                return $this->createSubscriptionService($config);

            case 'subscription_plan':
                return $this->createSubscriptionPlanService($config);

            case 'billing':
                return $this->createBillingService($config);

            case 'subscription_metrics':
                return $this->createSubscriptionMetricsService($config);

            case 'subscription_lifecycle':
                return $this->createSubscriptionLifecycleService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Subscription service with dependencies
     *
     * @param array $config Service configuration
     * @return SubscriptionService Configured service instance
     */
    private function createSubscriptionService(array $config): SubscriptionService
    {
        return new SubscriptionService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create SubscriptionPlan service with dependencies
     *
     * @param array $config Service configuration
     * @return SubscriptionPlanService Configured service instance
     */
    private function createSubscriptionPlanService(array $config): SubscriptionPlanService
    {
        return new SubscriptionPlanService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create Billing service with dependencies
     *
     * @param array $config Service configuration
     * @return BillingService Configured service instance
     */
    private function createBillingService(array $config): BillingService
    {
        return new BillingService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create SubscriptionMetrics service with dependencies
     *
     * @param array $config Service configuration
     * @return SubscriptionMetricsService Configured service instance
     */
    private function createSubscriptionMetricsService(array $config): SubscriptionMetricsService
    {
        return new SubscriptionMetricsService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create SubscriptionLifecycle service with dependencies
     *
     * @param array $config Service configuration
     * @return SubscriptionLifecycleService Configured service instance
     */
    private function createSubscriptionLifecycleService(array $config): SubscriptionLifecycleService
    {
        return new SubscriptionLifecycleService(
            $this->sdk,
            $this->config,
            $this->logger
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
            'subscription' => ApiSubscriptionRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only 'subscription' is available."
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
