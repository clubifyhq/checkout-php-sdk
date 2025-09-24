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

use Clubify\Checkout\Core\Http\ResponseHelper;
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
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("subscription:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find subscription by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
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

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    throw new HttpException(
                        "Failed to get subscription stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'subscriptions' => $subscriptionsData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create subscriptions: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

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
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update subscriptions: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

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
                $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search subscriptions: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
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

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    if ($response->getStatusCode() === 404) {
                        throw new SubscriptionNotFoundException("No history found for subscription ID: {$subscriptionId}");
                    }
                    throw new HttpException(
                        "Failed to get subscription history: " . "HTTP request failed",
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
    public function addRelationship(string $subscriptionId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($subscriptionId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$subscriptionId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
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
            $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$subscriptionId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
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
