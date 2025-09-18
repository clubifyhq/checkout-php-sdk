<?php

namespace Clubify\Checkout\Modules\Notifications\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;
// Services
use Clubify\Checkout\Modules\Notifications\Services\NotificationService;
use Clubify\Checkout\Modules\Notifications\Services\NotificationLogService;
use Clubify\Checkout\Modules\Notifications\Services\NotificationStatsService;
use Clubify\Checkout\Modules\Notifications\Services\WebhookConfigService;

/**
 * Notifications Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Notifications module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages service instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'notification': Main notification management service
 * - 'notification_log': Notification logs management service
 * - 'notification_stats': Notification statistics service
 * - 'webhook_config': Webhook configuration service
 *
 * @package Clubify\Checkout\Modules\Notifications\Factories
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class NotificationsServiceFactory implements FactoryInterface
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
            $this->logger->debug('Notifications service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Notifications service', [
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

            $this->logger->info('Notifications service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Notifications service', [
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
            'notification',
            'notification_log',
            'notification_stats',
            'webhook_config'
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

        $this->logger->info('NotificationsServiceFactory cache cleared');
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
            case 'notification':
                return $this->createNotificationService($config);

            case 'notification_log':
                return $this->createNotificationLogService($config);

            case 'notification_stats':
                return $this->createNotificationStatsService($config);

            case 'webhook_config':
                return $this->createWebhookConfigService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Notification service with dependencies
     *
     * @param array $config Service configuration
     * @return NotificationService Configured service instance
     */
    private function createNotificationService(array $config): NotificationService
    {
        return new NotificationService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create NotificationLog service with dependencies
     *
     * @param array $config Service configuration
     * @return NotificationLogService Configured service instance
     */
    private function createNotificationLogService(array $config): NotificationLogService
    {
        return new NotificationLogService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create NotificationStats service with dependencies
     *
     * @param array $config Service configuration
     * @return NotificationStatsService Configured service instance
     */
    private function createNotificationStatsService(array $config): NotificationStatsService
    {
        return new NotificationStatsService(
            $this->sdk,
            $this->config,
            $this->logger
        );
    }

    /**
     * Create WebhookConfig service with dependencies
     *
     * @param array $config Service configuration
     * @return WebhookConfigService Configured service instance
     */
    private function createWebhookConfigService(array $config): WebhookConfigService
    {
        return new WebhookConfigService(
            $this->sdk,
            $this->config,
            $this->logger
        );
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
