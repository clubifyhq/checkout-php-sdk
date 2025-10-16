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
use Clubify\Checkout\Modules\Webhooks\Exceptions\WebhookException;
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
 * - GET    webhooks/configurations  - List webhooks (filtered by headers)
 * - POST   webhooks/configurations  - Create webhook
 * - GET    webhooks/configurations/{id} - Get webhook by ID
 * - PUT    webhooks/configurations/{id} - Update webhook
 * - DELETE webhooks/configurations/{id} - Delete webhook
 * - PATCH  webhooks/configurations/{id}/status - Update status
 *
 * NOTE: All endpoints rely on X-Organization-Id and X-Tenant-Id headers for filtering
 *
 * @package Clubify\Checkout\Modules\Webhooks\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiWebhookRepository extends BaseRepository implements WebhookRepositoryInterface
{
    /**
     * Get API endpoint for webhooks
     * CRITICAL FIX: Use notification-service endpoint for webhook configurations
     * Note: Do NOT include /api/v1 prefix as it's already in base_url
     * This will be used by BaseRepository for:
     * - POST {base_url}/{endpoint} -> POST /api/v1/webhooks/configurations
     * - GET {base_url}/{endpoint}/{id} -> GET /api/v1/webhooks/configurations/{id}
     * - PUT {base_url}/{endpoint}/{id} -> PUT /api/v1/webhooks/configurations/{id}
     * - DELETE {base_url}/{endpoint}/{id} -> DELETE /api/v1/webhooks/configurations/{id}
     */
    protected function getEndpoint(): string
    {
        return 'webhooks/configurations';
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
            // FIX: Wrap data in 'json' option for POST request body
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/validate-url", [
                'json' => ['url' => $url]
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
     * Get current tenant ID from SDK configuration
     */
    protected function getTenantId(): ?string
    {
        // Tentar obter tenant ID da configuração via SDK
        $tenantId = $this->config->get('credentials.tenant_id');
        if ($tenantId) {
            return $tenantId;
        }

        // Fallback: tentar obter do contexto HTTP (header X-Tenant-Id)
        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            return $_SERVER['HTTP_X_TENANT_ID'];
        }

        return null;
    }

    /**
     * Find webhooks by event type
     * FIXED: Use general endpoint with query parameters
     */
    public function findByEvent(string $eventType): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:event:{$eventType}"),
            function () use ($eventType) {
                // FIXED: Use general endpoint - API filters by headers
                $endpoint = $this->getEndpoint();

                $response = $this->makeHttpRequest('GET', $endpoint . '?' . http_build_query([
                    'event_type' => $eventType,
                    'active' => true
                ]));

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to find webhooks by event: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);

                // Handle different response formats
                if (isset($data['configurations'])) {
                    return $data['configurations'];
                }

                return $data['webhooks'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhooks by organization
     * FIXED: Use general /configurations endpoint with headers (X-Organization-Id, X-Tenant-Id)
     * The API filters by headers, not by URL path
     */
    public function findByOrganization(string $organizationId): array
    {
        // Cache by organization_id since that's what the API uses in tenantId field
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:org:{$organizationId}"),
            function () use ($organizationId) {

                // FIXED: Use general endpoint - API filters by headers
                // Headers are set automatically by BaseRepository from SDK config
                $endpoint = $this->getEndpoint(); // Just 'webhooks/configurations'

                $this->logger->info('Fetching webhook configurations by organization', [
                    'organization_id' => $organizationId,
                    'endpoint' => $endpoint,
                    'full_url' => $this->config->get('base_url') . '/' . $endpoint
                ]);

                $response = $this->makeHttpRequest('GET', $endpoint);

                $statusCode = $response->getStatusCode();
                $this->logger->info('Webhook configurations API response received', [
                    'status_code' => $statusCode,
                    'is_successful' => ResponseHelper::isSuccessful($response)
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    $this->logger->error('Failed to fetch webhook configurations', [
                        'status_code' => $statusCode,
                        'organization_id' => $organizationId,
                        'endpoint' => $endpoint
                    ]);
                    throw new HttpException(
                        "Failed to find webhooks by organization: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);

                $this->logger->debug('Raw API response data', [
                    'data_keys' => array_keys($data),
                    'has_configurations' => isset($data['configurations']),
                    'has_config' => isset($data['config']),
                    'has_webhooks' => isset($data['webhooks']),
                    'total' => $data['total'] ?? 0,
                    'data_structure' => json_encode($data, JSON_PRETTY_PRINT)
                ]);

                // FIXED: API returns { configurations: [...], total: X, page: Y, pages: Z }
                if (isset($data['configurations'])) {
                    $configs = $data['configurations'];
                    $this->logger->info('Webhook configurations found', [
                        'organization_id' => $organizationId,
                        'config_count' => count($configs),
                        'total' => $data['total'] ?? count($configs)
                    ]);
                    return $configs;
                }

                // Fallback: single config wrapped (backward compatibility)
                if (isset($data['config'])) {
                    $this->logger->info('Single webhook configuration found', [
                        'organization_id' => $organizationId,
                        'config_id' => $data['config']['_id'] ?? 'unknown',
                        'config_name' => $data['config']['name'] ?? 'unknown',
                        'endpoint_count' => count($data['config']['endpoints'] ?? [])
                    ]);
                    return [$data['config']];
                }

                // Fallback: old webhooks format (backward compatibility)
                if (isset($data['webhooks'])) {
                    $this->logger->info('Using fallback webhooks format', [
                        'organization_id' => $organizationId,
                        'webhook_count' => count($data['webhooks'])
                    ]);
                    return $data['webhooks'];
                }

                // No configs found
                $this->logger->info('No webhook configurations found', [
                    'organization_id' => $organizationId
                ]);
                return [];
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
            // FIX: Wrap data in 'json' option for PATCH request body
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/last-delivery", ['json' => $deliveryData]);

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
                        throw WebhookException::notFound($webhookId);
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
     * FIXED: Use general endpoint with query parameters
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:email:{$fieldValue}"),
            function () use ($fieldValue) {
                // FIXED: Use general endpoint - API filters by headers
                $endpoint = $this->getEndpoint();

                $response = $this->makeHttpRequest('GET', $endpoint . '?' . http_build_query([
                    'email' => $fieldValue
                ]));

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

                // Handle different response formats
                if (isset($data['configurations']) && !empty($data['configurations'])) {
                    return $data['configurations'][0];
                }

                return $data['webhooks'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find webhook by URL
     * FIXED: Use general endpoint with query parameters
     */
    public function findByUrl(string $url): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:url:" . md5($url)),
            function () use ($url) {
                // FIXED: Use general endpoint - API filters by headers
                $endpoint = $this->getEndpoint();

                $response = $this->makeHttpRequest('GET', $endpoint . '?' . http_build_query([
                    'url' => $url
                ]));

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

                // Handle different response formats
                if (isset($data['configurations']) && !empty($data['configurations'])) {
                    return $data['configurations'][0];
                }

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
            // FIX: Wrap data in 'json' option for PATCH request body
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$webhookId}/status", [
                'json' => ['status' => $status]
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($webhookId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Webhook.StatusUpdated', [
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
            // FIX: Wrap data in 'json' option for POST request body
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'json' => ['webhooks' => $webhooksData]
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create webhooks: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Webhook.BulkCreated', [
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
            // FIX: Wrap data in 'json' option for PUT request body
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'json' => ['updates' => $updates]
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
            $this->eventDispatcher?->emit('Clubify.Checkout.Webhook.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search webhooks with advanced criteria
     * FIXED: Use general endpoint with query parameters
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("webhook:search:" . md5(serialize($filters + $sort + [$limit, $offset])));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters, $sort, $limit, $offset) {
                // FIXED: Use general endpoint - API filters by headers
                $endpoint = $this->getEndpoint();

                // Use GET with query parameters
                $queryParams = array_merge($filters, $sort, [
                    'limit' => $limit,
                    'offset' => $offset
                ]);
                $response = $this->makeHttpRequest('GET', $endpoint . '?' . http_build_query($queryParams));

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search webhooks: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);

                // Handle different response formats
                if (isset($data['configurations'])) {
                    return [
                        'configurations' => $data['configurations'],
                        'total' => $data['total'] ?? count($data['configurations']),
                        'page' => $data['page'] ?? 1,
                        'pages' => $data['pages'] ?? 1
                    ];
                }

                return $data;
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
                $this->eventDispatcher?->emit('Clubify.Checkout.Webhook.Archived', [
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
                $this->eventDispatcher?->emit('Clubify.Checkout.Webhook.Restored', [
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
                        throw WebhookException::notFound($webhookId);
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
            // FIX: Wrap data in 'json' option for POST request body
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$webhookId}/{$relationType}", [
                'json' => [
                    'related_id' => $relatedId,
                    'metadata' => $metadata
                ]
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

    // ==============================================
    // ENDPOINT MANAGEMENT METHODS (NEW API)
    // ==============================================

    /**
     * Normalize endpoint data to ensure it meets API validation requirements
     *
     * @param array $endpoint Endpoint data
     * @return array Normalized endpoint data
     */
    private function normalizeEndpoint(array $endpoint): array
    {
        // Ensure secret field is valid: at least 8 chars, alphanumeric + hyphens/underscores
        if (!isset($endpoint['secret']) || !is_string($endpoint['secret']) || strlen($endpoint['secret']) < 8) {
            // Generate a valid secret if missing or invalid
            $endpoint['secret'] = bin2hex(random_bytes(16)); // 32 chars hex string
        } else {
            // Validate the secret contains only allowed characters
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $endpoint['secret'])) {
                // Secret contains invalid characters, generate a new one
                $this->logger->warning('Invalid secret detected, regenerating', [
                    'eventType' => $endpoint['eventType'] ?? 'unknown',
                    'old_secret_length' => strlen($endpoint['secret'])
                ]);
                $endpoint['secret'] = bin2hex(random_bytes(16));
            }
        }

        // Ensure headers field is a JSON object {}, not an array []
        // PHP's empty array [] serializes to JSON array [], but API expects object {}
        if (!isset($endpoint['headers']) || !is_array($endpoint['headers']) || empty($endpoint['headers'])) {
            // Use stdClass to force JSON object serialization
            $endpoint['headers'] = new \stdClass();
        } else {
            // Check if it's a sequential/indexed array (not associative)
            $keys = array_keys($endpoint['headers']);
            $isSequential = $keys === range(0, count($endpoint['headers']) - 1);

            if ($isSequential) {
                // Sequential array would serialize to JSON array, convert to object
                $endpoint['headers'] = new \stdClass();
            }
            // else: it's an associative array, which will serialize to JSON object correctly
        }

        // Ensure other required fields have valid defaults
        if (!isset($endpoint['httpMethod'])) {
            $endpoint['httpMethod'] = 'POST';
        }

        if (!isset($endpoint['isActive'])) {
            $endpoint['isActive'] = true;
        }

        // Ensure retryConfig is properly structured
        if (!isset($endpoint['retryConfig']) || !is_array($endpoint['retryConfig'])) {
            $endpoint['retryConfig'] = [
                'maxRetries' => 3,
                'timeout' => 30000,
                'backoffStrategy' => 'exponential'
            ];
        } else {
            // Ensure required retry config fields
            $endpoint['retryConfig']['maxRetries'] = $endpoint['retryConfig']['maxRetries'] ?? 3;
            $endpoint['retryConfig']['timeout'] = $endpoint['retryConfig']['timeout'] ?? 30000;
            $endpoint['retryConfig']['backoffStrategy'] = $endpoint['retryConfig']['backoffStrategy'] ?? 'exponential';
        }

        // Debug log to verify JSON serialization
        $this->logger->debug('Endpoint normalized', [
            'eventType' => $endpoint['eventType'] ?? 'unknown',
            'headers_type' => gettype($endpoint['headers']),
            'headers_json' => json_encode($endpoint['headers']),
            'secret_length' => strlen($endpoint['secret'] ?? '')
        ]);

        return $endpoint;
    }

    /**
     * Add endpoint to existing webhook configuration
     *
     * NOTE: The API does not have a dedicated endpoint for adding endpoints.
     * This method retrieves the full configuration, modifies the endpoints array,
     * and updates the entire configuration via PUT.
     *
     * @param string $organizationId Organization/Tenant ID (NOTE: Despite param name, API requires tenant_id in path)
     * @param string $configName Configuration name
     * @param array $endpointData Endpoint data
     * @return array Updated configuration
     */
    public function addEndpoint(string $organizationId, string $configName, array $endpointData): array
    {
        return $this->executeWithMetrics('add_endpoint', function () use ($organizationId, $configName, $endpointData) {
            $tenantId = $this->config->get('credentials.tenant_id');
            if (!$tenantId) {
                throw new \RuntimeException("tenant_id is required but not configured");
            }

            // Step 1: Get the current configuration
            $configs = $this->findByTenantId($tenantId);

            if (empty($configs)) {
                throw new WebhookException("No webhook configuration found for tenant {$tenantId}");
            }

            $config = $configs[0]; // Get the first (and should be only) configuration

            if (!isset($config['_id'])) {
                throw new WebhookException("Invalid configuration structure: missing _id");
            }

            // Step 2: Check if endpoint for this event type already exists
            $existingEndpoints = $config['endpoints'] ?? [];
            foreach ($existingEndpoints as $endpoint) {
                if ($endpoint['eventType'] === $endpointData['eventType']) {
                    throw new WebhookException(
                        "Endpoint for event type {$endpointData['eventType']} already exists"
                    );
                }
            }

            // Step 3: Normalize ALL existing endpoints to ensure they pass API validation
            $normalizedEndpoints = array_map(
                fn($endpoint) => $this->normalizeEndpoint($endpoint),
                $existingEndpoints
            );

            // Step 4: Normalize the new endpoint and add it to the array
            $normalizedEndpoints[] = $this->normalizeEndpoint($endpointData);

            // Step 5: Update the entire configuration using PUT
            $configId = $config['_id'];
            $endpoint = "webhooks/configurations/{$configId}";

            // Debug log the payload being sent
            $this->logger->debug('Sending endpoints to API', [
                'config_id' => $configId,
                'endpoint_count' => count($normalizedEndpoints),
                'payload_json' => json_encode(['endpoints' => $normalizedEndpoints])
            ]);

            $response = $this->makeHttpRequest('PUT', $endpoint, [
                'json' => [
                    'endpoints' => $normalizedEndpoints
                ]
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("webhook:org:{$organizationId}"));
            $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
            $this->cache?->delete($this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"));

            $this->logger->info('Endpoint added to webhook configuration', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'config_id' => $configId,
                'event_type' => $endpointData['eventType'] ?? 'unknown'
            ]);

            return ResponseHelper::getData($response);
        });
    }

    /**
     * Remove endpoint from webhook configuration
     *
     * NOTE: The API does not have a dedicated endpoint for removing endpoints.
     * This method retrieves the full configuration, removes the endpoint from the array,
     * and updates the entire configuration via PUT.
     *
     * @param string $organizationId Organization/Tenant ID (NOTE: Despite param name, API requires tenant_id in path)
     * @param string $configName Configuration name
     * @param string $eventType Event type to remove
     * @return bool Success
     */
    public function removeEndpoint(string $organizationId, string $configName, string $eventType): bool
    {
        return $this->executeWithMetrics('remove_endpoint', function () use ($organizationId, $configName, $eventType) {
            $tenantId = $this->config->get('credentials.tenant_id');
            if (!$tenantId) {
                throw new \RuntimeException("tenant_id is required but not configured");
            }

            // CRITICAL FIX: Clear cache BEFORE fetching to ensure we get fresh data
            // This prevents stale cache from being read after addEndpoint() was just called
            $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
            $this->cache?->delete($this->getCacheKey("webhook:org:{$organizationId}"));
            $this->cache?->delete($this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"));

            // Step 1: Get the current configuration (will fetch fresh since cache was cleared)
            $configs = $this->findByTenantId($tenantId);

            if (empty($configs)) {
                throw new WebhookException("No webhook configuration found for tenant {$tenantId}");
            }

            $config = $configs[0]; // Get the first (and should be only) configuration

            if (!isset($config['_id'])) {
                throw new WebhookException("Invalid configuration structure: missing _id");
            }

            // Step 2: Remove the endpoint from the array
            $existingEndpoints = $config['endpoints'] ?? [];
            $filteredEndpoints = array_filter($existingEndpoints, function($endpoint) use ($eventType) {
                return $endpoint['eventType'] !== $eventType;
            });

            // Re-index the array to maintain proper JSON array structure
            $filteredEndpoints = array_values($filteredEndpoints);

            // Step 3: Normalize remaining endpoints to ensure they pass API validation
            $normalizedEndpoints = array_map(
                fn($endpoint) => $this->normalizeEndpoint($endpoint),
                $filteredEndpoints
            );

            // Step 4: Update the entire configuration using PUT
            $configId = $config['_id'];
            $endpoint = "webhooks/configurations/{$configId}";

            // Debug log the payload being sent
            $this->logger->debug('Sending endpoints to API (removeEndpoint)', [
                'config_id' => $configId,
                'endpoint_count' => count($normalizedEndpoints),
                'payload_json' => json_encode(['endpoints' => $normalizedEndpoints])
            ]);

            $this->makeHttpRequest('PUT', $endpoint, [
                'json' => [
                    'endpoints' => $normalizedEndpoints
                ]
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("webhook:org:{$organizationId}"));
            $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
            $this->cache?->delete($this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"));

            $this->logger->info('Endpoint removed from webhook configuration', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'config_id' => $configId,
                'event_type' => $eventType
            ]);

            return true;
        });
    }

    /**
     * List all endpoints for a configuration
     *
     * NOTE: The API does not have a dedicated endpoint for listing endpoints.
     * This method retrieves the full configuration and returns just the endpoints array.
     *
     * @param string $organizationId Organization/Tenant ID (NOTE: Despite param name, API requires tenant_id in path)
     * @param string $configName Configuration name
     * @return array List of endpoints
     */
    public function listEndpoints(string $organizationId, string $configName): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"),
            function () use ($organizationId, $configName) {
                $tenantId = $this->config->get('credentials.tenant_id');
                if (!$tenantId) {
                    throw new \RuntimeException("tenant_id is required but not configured");
                }

                // Get the full configuration
                $configs = $this->findByTenantId($tenantId);

                if (empty($configs)) {
                    $this->logger->info('No webhook configuration found for tenant', [
                        'tenant_id' => $tenantId,
                    ]);
                    return [];
                }

                $config = $configs[0]; // Get the first (and should be only) configuration
                $endpoints = $config['endpoints'] ?? [];

                $this->logger->info('Endpoints listed for webhook configuration', [
                    'organization_id' => $organizationId,
                    'tenant_id' => $tenantId,
                    'config_name' => $configName,
                    'count' => count($endpoints)
                ]);

                return $endpoints;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Update an existing endpoint
     *
     * NOTE: The API does not have a dedicated endpoint for updating endpoints.
     * This method retrieves the full configuration, modifies the specific endpoint,
     * and updates the entire configuration via PUT.
     *
     * @param string $organizationId Organization/Tenant ID (NOTE: Despite param name, API requires tenant_id in path)
     * @param string $configName Configuration name
     * @param string $eventType Event type
     * @param array $updates Fields to update
     * @return array Updated configuration
     */
    public function updateEndpoint(string $organizationId, string $configName, string $eventType, array $updates): array
    {
        return $this->executeWithMetrics('update_endpoint', function () use ($organizationId, $configName, $eventType, $updates) {
            $tenantId = $this->config->get('credentials.tenant_id');
            if (!$tenantId) {
                throw new \RuntimeException("tenant_id is required but not configured");
            }

            // CRITICAL FIX: Clear cache BEFORE fetching to ensure we get fresh data
            // This prevents stale cache from being read after addEndpoint() was just called
            $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
            $this->cache?->delete($this->getCacheKey("webhook:org:{$organizationId}"));
            $this->cache?->delete($this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"));

            // CRITICAL FIX: Retry mechanism to handle eventual consistency in the API backend
            // The API may have replication lag or caching that causes read-after-write inconsistency
            $maxRetries = 5;
            $retryDelay = 200000; // 200ms in microseconds (increased from 100ms)
            $configs = null;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                // Step 1: Get the current configuration (will fetch fresh since cache was cleared)
                $configs = $this->findByTenantId($tenantId);

                if (empty($configs)) {
                    throw new WebhookException("No webhook configuration found for tenant {$tenantId}");
                }

                $config = $configs[0];
                $existingEndpoints = $config['endpoints'] ?? [];

                // Check if the endpoint we're looking for exists
                $endpointExists = false;
                foreach ($existingEndpoints as $endpoint) {
                    if ($endpoint['eventType'] === $eventType) {
                        $endpointExists = true;
                        break;
                    }
                }

                if ($endpointExists) {
                    // Found it! Break out of retry loop
                    break;
                }

                if ($attempt < $maxRetries) {
                    $this->logger->info('updateEndpoint: Endpoint not found, retrying...', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'event_type' => $eventType,
                        'retry_delay_ms' => $retryDelay / 1000
                    ]);

                    // Clear cache again and wait before retrying
                    $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
                    usleep($retryDelay);

                    // Increase delay for next attempt (exponential backoff)
                    $retryDelay *= 2;
                }
            }

            // After retries, fetch one more time for the actual update logic
            $configs = $this->findByTenantId($tenantId);

            if (empty($configs)) {
                throw new WebhookException("No webhook configuration found for tenant {$tenantId}");
            }

            $config = $configs[0]; // Get the first (and should be only) configuration

            if (!isset($config['_id'])) {
                throw new WebhookException("Invalid configuration structure: missing _id");
            }

            // Step 2: Find and update the specific endpoint
            $existingEndpoints = $config['endpoints'] ?? [];
            $endpointFound = false;

            // DEBUG: Log what endpoints we have
            $this->logger->warning('updateEndpoint: Looking for endpoint to update after retries', [
                'config_id' => $config['_id'],
                'endpoint_count' => count($existingEndpoints),
                'event_types_found' => array_map(fn($e) => $e['eventType'] ?? 'unknown', $existingEndpoints),
                'looking_for' => $eventType,
                'note' => 'If endpoint was just added via addEndpoint(), API may have replication lag'
            ]);

            foreach ($existingEndpoints as $index => $endpoint) {
                if ($endpoint['eventType'] === $eventType) {
                    // Merge the updates with the existing endpoint
                    $existingEndpoints[$index] = array_merge($endpoint, $updates);
                    $endpointFound = true;
                    break;
                }
            }

            if (!$endpointFound) {
                throw new WebhookException(
                    "Endpoint for event type {$eventType} not found in configuration after {$maxRetries} retries. " .
                    "This may be due to API backend replication lag. Available endpoints: " .
                    implode(', ', array_map(fn($e) => $e['eventType'] ?? 'unknown', $existingEndpoints))
                );
            }

            // Step 3: Normalize ALL endpoints to ensure they pass API validation
            $normalizedEndpoints = array_map(
                fn($endpoint) => $this->normalizeEndpoint($endpoint),
                $existingEndpoints
            );

            // Step 4: Update the entire configuration using PUT
            $configId = $config['_id'];
            $endpoint = "webhooks/configurations/{$configId}";

            // Debug log the payload being sent
            $this->logger->debug('Sending endpoints to API (updateEndpoint)', [
                'config_id' => $configId,
                'endpoint_count' => count($normalizedEndpoints),
                'payload_json' => json_encode(['endpoints' => $normalizedEndpoints])
            ]);

            $response = $this->makeHttpRequest('PUT', $endpoint, [
                'json' => [
                    'endpoints' => $normalizedEndpoints
                ]
            ]);

            // Invalidate cache
            $this->cache?->delete($this->getCacheKey("webhook:org:{$organizationId}"));
            $this->cache?->delete($this->getCacheKey("webhook:tenant:{$tenantId}"));
            $this->cache?->delete($this->getCacheKey("webhook:endpoints:{$organizationId}:{$configName}"));

            $this->logger->info('Endpoint updated in webhook configuration', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'config_id' => $configId,
                'event_type' => $eventType
            ]);

            return ResponseHelper::getData($response);
        });
    }

    /**
     * Find webhook configurations by tenant ID (new primary method)
     * FIXED: Use general endpoint - API filters by X-Tenant-Id header
     *
     * @param string $tenantId Tenant ID
     * @return array
     */
    public function findByTenantId(string $tenantId): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("webhook:tenant:{$tenantId}"),
            function () use ($tenantId) {
                // FIXED: Use general endpoint - API filters by headers
                // Note: The X-Tenant-Id header is set automatically by BaseRepository
                $endpoint = $this->getEndpoint();

                try {
                    $response = $this->makeHttpRequest('GET', $endpoint);

                    if (!ResponseHelper::isSuccessful($response)) {
                        if ($response->getStatusCode() === 404) {
                            return [];
                        }
                        throw new HttpException(
                            "Failed to find webhooks by tenant ID: " . "HTTP request failed",
                            $response->getStatusCode()
                        );
                    }

                    $data = ResponseHelper::getData($response);

                    // FIXED: API returns { configurations: [...], total: X, page: Y, pages: Z }
                    if (isset($data['configurations'])) {
                        return $data['configurations'];
                    }

                    // Fallback: single config wrapped
                    if (isset($data['config'])) {
                        return [$data['config']];
                    }

                    // Fallback: old configs format
                    if (isset($data['configs'])) {
                        return $data['configs'];
                    }

                    // Return as is if already array
                    return is_array($data) ? $data : [$data];

                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), '404') !== false) {
                        $this->logger->info('No webhook configuration found for tenant', [
                            'tenant_id' => $tenantId
                        ]);
                        return [];
                    }
                    throw $e;
                }
            },
            300 // 5 minutes cache
        );
    }

    /**
     * @deprecated Use findByTenantId() instead. Will be removed in v3.0.0
     */
    public function findByPartnerId(string $partnerId): array
    {
        error_log("[DEPRECATED] ApiWebhookRepository::findByPartnerId() is deprecated. Use findByTenantId() instead.");
        // Internally delegates to findByTenantId for backward compatibility
        return $this->findByTenantId($partnerId);
    }

}
