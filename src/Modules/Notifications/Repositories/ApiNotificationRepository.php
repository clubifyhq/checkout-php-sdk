<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Notification pelo nome da entidade (ex: Order)
 * 2. Substitua notification pela versão lowercase (ex: order)
 * 3. Substitua Notifications pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Notification = Order
 * - notification = order
 * - Notifications = OrderManagement
 */

namespace Clubify\Checkout\Modules\Notifications\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Notifications\Contracts\NotificationRepositoryInterface;
use Clubify\Checkout\Modules\Notifications\Exceptions\NotificationNotFoundException;
use Clubify\Checkout\Modules\Notifications\Exceptions\NotificationValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Notification Repository
 *
 * Implementa o NotificationRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    notifications                 - List notifications
 * - POST   notifications                 - Create notification
 * - GET    notifications/{id}           - Get notification by ID
 * - PUT    notifications/{id}           - Update notification
 * - DELETE notifications/{id}           - Delete notification
 * - GET    notifications/search         - Search notifications
 * - PATCH  notifications/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Notifications\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiNotificationRepository extends BaseRepository implements NotificationRepositoryInterface
{
    /**
     * Get API endpoint for notifications
     */
    protected function getEndpoint(): string
    {
        return 'notifications';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'notification';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'notification-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from NotificationRepositoryInterface
    // ==============================================

    /**
     * Find notification by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("notification:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find notification by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['notifications'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find notifications by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update notification status
     */
    public function updateStatus(string $notificationId, string $status): bool
    {
        return $this->executeWithMetrics('update_notification_status', function () use ($notificationId, $status) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$notificationId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($notificationId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Notification.StatusUpdated', [
                    'notification_id' => $notificationId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get notification statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("notification:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    throw new HttpException(
                        "Failed to get notification stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create notifications
     */
    public function bulkCreate(array $notificationsData): array
    {
        return $this->executeWithMetrics('bulk_create_notifications', function () use ($notificationsData) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'notifications' => $notificationsData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create notifications: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Notification.BulkCreated', [
                'count' => count($notificationsData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update notifications
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_notifications', function () use ($updates) {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update notifications: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Clear cache for updated notifications
            foreach (array_keys($updates) as $notificationId) {
                $this->invalidateCache($notificationId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Notification.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search notifications with advanced criteria
     */
    public function search(array $criteria, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("notification:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search notifications: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive notification
     */
    public function archive(string $notificationId): bool
    {
        return $this->executeWithMetrics('archive_notification', function () use ($notificationId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$notificationId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($notificationId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Notification.Archived', [
                    'notification_id' => $notificationId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived notification
     */
    public function restore(string $notificationId): bool
    {
        return $this->executeWithMetrics('restore_notification', function () use ($notificationId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$notificationId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($notificationId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Notification.Restored', [
                    'notification_id' => $notificationId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get notification history
     */
    public function getHistory(string $notificationId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("notification:history:{$notificationId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($notificationId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$notificationId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    if ($response->getStatusCode() === 404) {
                        throw new NotificationNotFoundException("No history found for notification ID: {$notificationId}");
                    }
                    throw new HttpException(
                        "Failed to get notification history: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            900 // 15 minutes cache for history
        );
    }

    // ==============================================
    // RELATIONSHIP METHODS
    // ==============================================

    /**
     * Get related entities
     */
    public function getRelated(string $notificationId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("notification:related:{$notificationId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($notificationId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$notificationId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    throw new HttpException(
                        "Failed to get related {$relationType}: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            300 // 5 minutes cache for relationships
        );
    }

    /**
     * Add relationship
     */
    public function addRelationship(string $notificationId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($notificationId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$notificationId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("notification:related:{$notificationId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $notificationId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($notificationId, $relatedId, $relationType) {
            $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$notificationId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("notification:related:{$notificationId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate notification cache
     */
    public function invalidateCache(string $notificationId): void
    {
        $patterns = [
            $this->getCacheKey("notification:{$notificationId}"),
            $this->getCacheKey("notification:*:{$notificationId}"),
            $this->getCacheKey("notification:related:{$notificationId}:*"),
            $this->getCacheKey("notification:history:{$notificationId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for notification
     */
    public function warmCache(string $notificationId): void
    {
        try {
            // Preload notification data
            $this->findById($notificationId);

            // Preload common relationships
            // $this->getRelated($notificationId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for notification', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all notification caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('notification:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All notification caches cleared');
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * Método para verificar resposta HTTP (compatibilidade)
     */
    protected function isSuccessfulResponse($response): bool
    {
        return ResponseHelper::isSuccessful($response);
    }

    /**
     * Método para extrair dados da resposta (compatibilidade)
     */
    protected function extractResponseData($response): ?array
    {
        return ResponseHelper::getData($response);
    }

}
