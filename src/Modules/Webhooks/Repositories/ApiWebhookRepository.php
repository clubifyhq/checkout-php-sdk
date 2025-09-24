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

use Clubify\Checkout\Core\Http\ResponseHelper;
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
     * Validate webhook URL
     */
    public function validateUrl(string $url): array
    {
        return $this->executeWithMetrics('validate_url', function () use ($url) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/validate-url", [
                'url' => $url
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                // If API validation fails, do basic validation
                return [
                    'url' => $url,
                    'accessible' => false,
                    'reachable' => false,
                    'response_code' => null,
                    'response_time' => null,
                    'error' => 'API validation failed: ' . "HTTP request failed",
                    'ssl_valid' => false,
                ];
            }

            return ResponseHelper::getData($response);
        });
    }

    /**
     * Find webhooks by event type
     */
    public function findByEvent(string $eventType): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:event:{$eventType}"),
            function () use ($eventType) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'event_type' => $eventType,
                    'active' => true
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to find webhooks by event: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['webhooks'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhooks by organization
     */
    public function findByOrganization(string $organizationId): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:org:{$organizationId}"),
            function () use ($organizationId) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'organization_id' => $organizationId,
                    'active' => true
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to find webhooks by organization: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['webhooks'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Reset failure count for webhook
     */
    public function resetFailureCount(string $webhookId): bool
    {
        return $this->executeWithMetrics('reset_failure_count', function () use ($webhookId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/reset-failures");

            if (ResponseHelper::isSuccessful($response)) {
                $this->invalidateCache($webhookId);
                return true;
            }

            return false;
        });
    }

    /**
     * Increment failure count for webhook
     */
    public function incrementFailureCount(string $webhookId): bool
    {
        return $this->executeWithMetrics('increment_failure_count', function () use ($webhookId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/increment-failures");

            if (ResponseHelper::isSuccessful($response)) {
                $this->invalidateCache($webhookId);
                return true;
            }

            return false;
        });
    }

    /**
     * Update last delivery information
     */
    public function updateLastDelivery(string $webhookId, array $deliveryData): bool
    {
        return $this->executeWithMetrics('update_last_delivery', function () use ($webhookId, $deliveryData) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/last-delivery", $deliveryData);

            if (ResponseHelper::isSuccessful($response)) {
                $this->invalidateCache($webhookId);
                return true;
            }

            return false;
        });
    }

    /**
     * Activate webhook
     */
    public function activate(string $webhookId): bool
    {
        return $this->updateStatus($webhookId, 'active');
    }

    /**
     * Deactivate webhook
     */
    public function deactivate(string $webhookId): bool
    {
        return $this->updateStatus($webhookId, 'inactive');
    }

    /**
     * Get webhook statistics
     */
    public function getWebhookStats(string $webhookId): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:stats:{$webhookId}"),
            function () use ($webhookId) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$webhookId}/stats");

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        throw new WebhookNotFoundException("Webhook not found: {$webhookId}");
                    }
                    throw new HttpException(
                        "Failed to get webhook stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Get delivery logs for webhook
     */
    public function findDeliveryLogs(string $webhookId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("webhook:logs:{$webhookId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($webhookId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$webhookId}/delivery-logs";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequest('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get delivery logs: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['logs'] ?? [];
            },
            120 // 2 minutes cache for logs
        );
    }

    /**
     * Find failed deliveries
     */
    public function findFailedDeliveries(string $period = '24 hours', array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("webhook:failed:{$period}:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($period, $filters) {
                $params = array_merge($filters, [
                    'status' => 'failed',
                    'period' => $period
                ]);

                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/delivery-logs?" . http_build_query($params));

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get failed deliveries: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['logs'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhook by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find webhook by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['webhooks'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhook by URL
     */
    public function findByUrl(string $url): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:url:" . md5($url)),
            function () use ($url) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'url' => $url
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find webhook by URL: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
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

                $response = $this->makeHttpRequest('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get webhook stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'webhooks' => $webhooksData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create webhooks: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

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
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update webhooks: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

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
                $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search webhooks: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
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
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
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

                $response = $this->makeHttpRequest('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        throw new WebhookNotFoundException("No history found for webhook ID: {$webhookId}");
                    }
                    throw new HttpException(
                        "Failed to get webhook history: " . "HTTP request failed",
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

                $response = $this->makeHttpRequest('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
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
    public function addRelationship(string $webhookId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($webhookId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$webhookId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
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
            $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$webhookId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
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
