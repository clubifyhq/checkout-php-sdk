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
                $result = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                return $result['subscriptions'][0] ?? null;
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
            $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/status", [
                'status' => $status
            ]);

            // Invalidate cache
            $this->invalidateCache($subscriptionId);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Subscription.StatusUpdated', [
                'subscription_id' => $subscriptionId,
                'status' => $status,
                'timestamp' => time()
            ]);

            return true;
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

                return $this->makeHttpRequest('GET', $endpoint);
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
            $result = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'subscriptions' => $subscriptionsData
            ]);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Subscription.BulkCreated', [
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
            $result = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            // Clear cache for updated subscriptions
            foreach (array_keys($updates) as $subscriptionId) {
                $this->invalidateCache($subscriptionId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Subscription.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search subscriptions with advanced criteria
     * Implements RepositoryInterface::search with standard signature
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        // Adapt parameters to API format
        $criteria = $filters;
        $options = [
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset
        ];

        $cacheKey = $this->getCacheKey("subscription:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                return $this->makeHttpRequest('POST', "{$this->getEndpoint()}/search", $payload);
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
            $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/archive");

            // Invalidate cache
            $this->invalidateCache($subscriptionId);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Subscription.Archived', [
                'subscription_id' => $subscriptionId,
                'timestamp' => time()
            ]);

            return true;
        });
    }

    /**
     * Restore archived subscription
     */
    public function restore(string $subscriptionId): bool
    {
        return $this->executeWithMetrics('restore_subscription', function () use ($subscriptionId) {
            $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$subscriptionId}/restore");

            // Invalidate cache
            $this->invalidateCache($subscriptionId);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Subscription.Restored', [
                'subscription_id' => $subscriptionId,
                'timestamp' => time()
            ]);

            return true;
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

                return $this->makeHttpRequest('GET', $endpoint);
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

                return $this->makeHttpRequest('GET', $endpoint);
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
            $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$subscriptionId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            // Invalidate relationship cache
            $this->cache?->delete($this->getCacheKey("subscription:related:{$subscriptionId}:{$relationType}:*"));

            return true;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $subscriptionId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($subscriptionId, $relatedId, $relationType) {
            $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$subscriptionId}/{$relationType}/{$relatedId}");

            // Invalidate relationship cache
            $this->cache?->delete($this->getCacheKey("subscription:related:{$subscriptionId}:{$relationType}:*"));

            return true;
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

    // ==============================================
    // SUBSCRIPTION PLANS METHODS
    // ==============================================

    /**
     * Create subscription plan
     *
     * @param array $data Plan data (SDK format)
     * @return array API response
     * @throws HttpException
     */
    public function createPlan(array $data): array
    {
        return $this->executeWithMetrics('create_subscription_plan', function () use ($data) {
            $result = $this->makeHttpRequest('POST', 'subscription-plans', [
                'json' => $data
            ]);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.SubscriptionPlan.Created', [
                'plan_id' => $result['_id'] ?? $result['id'] ?? null,
                'name' => $data['name'] ?? null,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Get subscription plan by ID
     *
     * @param string $id Plan ID
     * @return array Plan data
     * @throws HttpException
     */
    public function findPlan(string $id): array
    {
        $cacheKey = $this->getCacheKey("subscription_plan:{$id}");

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($id) {
                return $this->makeHttpRequest('GET', "subscription-plans/{$id}");
            },
            600 // 10 minutes cache
        );
    }

    /**
     * Update subscription plan
     *
     * @param string $id Plan ID
     * @param array $data Plan data to update
     * @return array Updated plan data
     * @throws HttpException
     */
    public function updatePlan(string $id, array $data): array
    {
        return $this->executeWithMetrics('update_subscription_plan', function () use ($id, $data) {
            $result = $this->makeHttpRequest('PUT', "subscription-plans/{$id}", [
                'json' => $data
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("subscription_plan:{$id}"));
            $this->cache?->delete($this->getCacheKey("subscription_plans:list:*"));

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.SubscriptionPlan.Updated', [
                'plan_id' => $id,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Delete (deactivate) subscription plan
     *
     * @param string $id Plan ID
     * @return array Result
     * @throws HttpException
     */
    public function deletePlan(string $id): array
    {
        return $this->executeWithMetrics('delete_subscription_plan', function () use ($id) {
            $result = $this->makeHttpRequest('DELETE', "subscription-plans/{$id}");

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("subscription_plan:{$id}"));
            $this->cache?->delete($this->getCacheKey("subscription_plans:list:*"));

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.SubscriptionPlan.Deleted', [
                'plan_id' => $id,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * List subscription plans with filters
     *
     * @param array $filters Filter parameters
     * @return array List of plans
     * @throws HttpException
     */
    public function listPlans(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("subscription_plans:list:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $endpoint = 'subscription-plans';
                if (!empty($filters)) {
                    $endpoint .= '?' . http_build_query($filters);
                }

                // makeHttpRequest já retorna array e valida a resposta
                return $this->makeHttpRequest('GET', $endpoint);
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Activate subscription plan
     *
     * @param string $id Plan ID
     * @return array Result
     * @throws HttpException
     */
    public function activatePlan(string $id): array
    {
        return $this->executeWithMetrics('activate_subscription_plan', function () use ($id) {
            $result = $this->makeHttpRequest('PATCH', "subscription-plans/{$id}", [
                'json' => ['isActive' => true]
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("subscription_plan:{$id}"));
            $this->cache?->delete($this->getCacheKey("subscription_plans:list:*"));

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.SubscriptionPlan.Activated', [
                'plan_id' => $id,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Deactivate subscription plan
     *
     * @param string $id Plan ID
     * @return array Result
     * @throws HttpException
     */
    public function deactivatePlan(string $id): array
    {
        return $this->executeWithMetrics('deactivate_subscription_plan', function () use ($id) {
            $result = $this->makeHttpRequest('PATCH', "subscription-plans/{$id}", [
                'json' => ['isActive' => false]
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("subscription_plan:{$id}"));
            $this->cache?->delete($this->getCacheKey("subscription_plans:list:*"));

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.SubscriptionPlan.Deactivated', [
                'plan_id' => $id,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

}
