<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Subscription pelo nome da entidade (ex: Order)
 * 2. Substitua subscription pela versão lowercase (ex: order)
 * 3. Substitua Subscriptions pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Subscription = Order
 * - subscription = order
 * - Subscriptions = OrderManagement
 */

namespace Clubify\Checkout\Modules\Subscriptions\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Subscriptions\Contracts\SubscriptionRepositoryInterface;
use Clubify\Checkout\Modules\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Clubify\Checkout\Modules\Subscriptions\Exceptions\SubscriptionValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Subscription Repository
 *
 * Implementa o SubscriptionRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    subscriptions                 - List subscriptions
 * - POST   subscriptions                 - Create subscription
 * - GET    subscriptions/{id}           - Get subscription by ID
 * - PUT    subscriptions/{id}           - Update subscription
 * - DELETE subscriptions/{id}           - Delete subscription
 * - GET    subscriptions/search         - Search subscriptions
 * - PATCH  subscriptions/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Subscriptions\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiSubscriptionRepository extends BaseRepository implements SubscriptionRepositoryInterface
{
    /**
     * Get API endpoint for subscriptions
     */
    protected function getEndpoint(): string
    {
        return 'subscriptions';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'subscription';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'subscription-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from SubscriptionRepositoryInterface
    // ==============================================

    /**
     * Find subscription by specific field
     */
    public function findBy{Field}(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("subscription:{field}:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->httpClient->get("{$this->getEndpoint()}/search", [
                    '{field}' => $fieldValue
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find subscription by {field}: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['subscriptions'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find subscriptions by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update subscription status
     */
    public function updateStatus(string $subscriptionId, string $status): bool
    {
        return $this->executeWithMetrics('update_subscription_status', function () use ($subscriptionId, $status) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$subscriptionId}/status", [
                'status' => $status
            ]);

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($subscriptionId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Subscription.StatusUpdated', [
                    'subscription_id' => $subscriptionId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get subscription statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("subscription:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get subscription stats: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create subscriptions
     */
    public function bulkCreate(array $subscriptionsData): array
    {
        return $this->executeWithMetrics('bulk_create_subscriptions', function () use ($subscriptionsData) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/bulk", [
                'subscriptions' => $subscriptionsData
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk create subscriptions: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Subscription.BulkCreated', [
                'count' => count($subscriptionsData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update subscriptions
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_subscriptions', function () use ($updates) {
            $response = $this->httpClient->put("{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk update subscriptions: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Clear cache for updated subscriptions
            foreach (array_keys($updates) as $subscriptionId) {
                $this->invalidateCache($subscriptionId);
            }

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Subscription.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search subscriptions with advanced criteria
     */
    public function search(array $criteria, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("subscription:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                $response = $this->httpClient->post("{$this->getEndpoint()}/search", $payload);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to search subscriptions: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive subscription
     */
    public function archive(string $subscriptionId): bool
    {
        return $this->executeWithMetrics('archive_subscription', function () use ($subscriptionId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$subscriptionId}/archive");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($subscriptionId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Subscription.Archived', [
                    'subscription_id' => $subscriptionId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived subscription
     */
    public function restore(string $subscriptionId): bool
    {
        return $this->executeWithMetrics('restore_subscription', function () use ($subscriptionId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$subscriptionId}/restore");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($subscriptionId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Subscription.Restored', [
                    'subscription_id' => $subscriptionId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get subscription history
     */
    public function getHistory(string $subscriptionId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("subscription:history:{$subscriptionId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($subscriptionId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$subscriptionId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        throw new SubscriptionNotFoundException("No history found for subscription ID: {$subscriptionId}");
                    }
                    throw new HttpException(
                        "Failed to get subscription history: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
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
    public function getRelated(string $subscriptionId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("subscription:related:{$subscriptionId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($subscriptionId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$subscriptionId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get related {$relationType}: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            300 // 5 minutes cache for relationships
        );
    }

    /**
     * Add relationship
     */
    public function addRelationship(string $subscriptionId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($subscriptionId, $relatedId, $relationType, $metadata) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/{$subscriptionId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("subscription:related:{$subscriptionId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $subscriptionId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($subscriptionId, $relatedId, $relationType) {
            $response = $this->httpClient->delete("{$this->getEndpoint()}/{$subscriptionId}/{$relationType}/{$relatedId}");

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("subscription:related:{$subscriptionId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate subscription cache
     */
    public function invalidateCache(string $subscriptionId): void
    {
        $patterns = [
            $this->getCacheKey("subscription:{$subscriptionId}"),
            $this->getCacheKey("subscription:*:{$subscriptionId}"),
            $this->getCacheKey("subscription:related:{$subscriptionId}:*"),
            $this->getCacheKey("subscription:history:{$subscriptionId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for subscription
     */
    public function warmCache(string $subscriptionId): void
    {
        try {
            // Preload subscription data
            $this->findById($subscriptionId);

            // Preload common relationships
            // $this->getRelated($subscriptionId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for subscription', [
                'subscription_id' => $subscriptionId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all subscription caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('subscription:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All subscription caches cleared');
    }
}