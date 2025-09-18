<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Webhook pelo nome da entidade (ex: Order)
 * 2. Substitua webhook pela versão lowercase (ex: order)
 * 3. Substitua Webhooks pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Webhook = Order
 * - webhook = order
 * - Webhooks = OrderManagement
 */

namespace Clubify\Checkout\Modules\Webhooks\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Webhooks\Contracts\WebhookRepositoryInterface;
use Clubify\Checkout\Modules\Webhooks\Exceptions\WebhookNotFoundException;
use Clubify\Checkout\Modules\Webhooks\Exceptions\WebhookValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Webhook Repository
 *
 * Implementa o WebhookRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    webhooks                 - List webhooks
 * - POST   webhooks                 - Create webhook
 * - GET    webhooks/{id}           - Get webhook by ID
 * - PUT    webhooks/{id}           - Update webhook
 * - DELETE webhooks/{id}           - Delete webhook
 * - GET    webhooks/search         - Search webhooks
 * - PATCH  webhooks/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Webhooks\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiWebhookRepository extends BaseRepository implements WebhookRepositoryInterface
{
    /**
     * Get API endpoint for webhooks
     */
    protected function getEndpoint(): string
    {
        return 'webhooks';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'webhook';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'webhook-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from WebhookRepositoryInterface
    // ==============================================

    /**
     * Find webhook by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->httpClient->get("{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find webhook by email: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['webhooks'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhooks by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update webhook status
     */
    public function updateStatus(string $webhookId, string $status): bool
    {
        return $this->executeWithMetrics('update_webhook_status', function () use ($webhookId, $status) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$webhookId}/status", [
                'status' => $status
            ]);

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($webhookId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Webhook.StatusUpdated', [
                    'webhook_id' => $webhookId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get webhook statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("webhook:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get webhook stats: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create webhooks
     */
    public function bulkCreate(array $webhooksData): array
    {
        return $this->executeWithMetrics('bulk_create_webhooks', function () use ($webhooksData) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/bulk", [
                'webhooks' => $webhooksData
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk create webhooks: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Webhook.BulkCreated', [
                'count' => count($webhooksData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update webhooks
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_webhooks', function () use ($updates) {
            $response = $this->httpClient->put("{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk update webhooks: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Clear cache for updated webhooks
            foreach (array_keys($updates) as $webhookId) {
                $this->invalidateCache($webhookId);
            }

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Webhook.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search webhooks with advanced criteria
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("webhook:search:" . md5(serialize($filters + $sort + [$limit, $offset])));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters, $sort, $limit, $offset) {
                $payload = [
                    'filters' => $filters,
                    'sort' => $sort,
                    'limit' => $limit,
                    'offset' => $offset
                ];
                $response = $this->httpClient->post("{$this->getEndpoint()}/search", $payload);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to search webhooks: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive webhook
     */
    public function archive(string $webhookId): bool
    {
        return $this->executeWithMetrics('archive_webhook', function () use ($webhookId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$webhookId}/archive");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($webhookId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Webhook.Archived', [
                    'webhook_id' => $webhookId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived webhook
     */
    public function restore(string $webhookId): bool
    {
        return $this->executeWithMetrics('restore_webhook', function () use ($webhookId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$webhookId}/restore");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($webhookId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Webhook.Restored', [
                    'webhook_id' => $webhookId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get webhook history
     */
    public function getHistory(string $webhookId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("webhook:history:{$webhookId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($webhookId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$webhookId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        throw new WebhookNotFoundException("No history found for webhook ID: {$webhookId}");
                    }
                    throw new HttpException(
                        "Failed to get webhook history: " . $response->getError(),
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
    public function getRelated(string $webhookId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("webhook:related:{$webhookId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($webhookId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$webhookId}/{$relationType}";
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
    public function addRelationship(string $webhookId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($webhookId, $relatedId, $relationType, $metadata) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/{$webhookId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("webhook:related:{$webhookId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $webhookId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($webhookId, $relatedId, $relationType) {
            $response = $this->httpClient->delete("{$this->getEndpoint()}/{$webhookId}/{$relationType}/{$relatedId}");

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("webhook:related:{$webhookId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate webhook cache
     */
    public function invalidateCache(string $webhookId): void
    {
        $patterns = [
            $this->getCacheKey("webhook:{$webhookId}"),
            $this->getCacheKey("webhook:*:{$webhookId}"),
            $this->getCacheKey("webhook:related:{$webhookId}:*"),
            $this->getCacheKey("webhook:history:{$webhookId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for webhook
     */
    public function warmCache(string $webhookId): void
    {
        try {
            // Preload webhook data
            $this->findById($webhookId);

            // Preload common relationships
            // $this->getRelated($webhookId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for webhook', [
                'webhook_id' => $webhookId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all webhook caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('webhook:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All webhook caches cleared');
    }
}
