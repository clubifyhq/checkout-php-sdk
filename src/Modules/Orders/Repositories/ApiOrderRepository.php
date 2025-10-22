<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Order pelo nome da entidade (ex: Order)
 * 2. Substitua order pela versão lowercase (ex: order)
 * 3. Substitua Orders pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Order = Order
 * - order = order
 * - Orders = OrderManagement
 */

namespace Clubify\Checkout\Modules\Orders\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Orders\Contracts\OrderRepositoryInterface;
use Clubify\Checkout\Modules\Orders\Exceptions\OrderNotFoundException;
use Clubify\Checkout\Modules\Orders\Exceptions\OrderValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Order Repository
 *
 * Implementa o OrderRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    orders                 - List orders
 * - POST   orders                 - Create order
 * - GET    orders/{id}           - Get order by ID
 * - PUT    orders/{id}           - Update order
 * - DELETE orders/{id}           - Delete order
 * - GET    orders/search         - Search orders
 * - PATCH  orders/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Orders\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiOrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    /**
     * Get API endpoint for orders
     */
    protected function getEndpoint(): string
    {
        return 'orders';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'order';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'order-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from OrderRepositoryInterface
    // ==============================================

    /**
     * Find order by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("order:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequestAndExtractData('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find order by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['orders'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find orders by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update order status
     */
    public function updateStatus(string $orderId, string $status): bool
    {
        return $this->executeWithMetrics('update_order_status', function () use ($orderId, $status) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$orderId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($orderId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Order.StatusUpdated', [
                    'order_id' => $orderId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get order statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("order:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint); if (!$response) {
                    throw new HttpException(
                        "Failed to get order stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create orders
     */
    public function bulkCreate(array $ordersData): array
    {
        return $this->executeWithMetrics('bulk_create_orders', function () use ($ordersData) {
            $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/bulk", [
                'orders' => $ordersData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create orders: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Order.BulkCreated', [
                'count' => count($ordersData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update orders
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_orders', function () use ($updates) {
            $response = $this->makeHttpRequestAndExtractData('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update orders: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Clear cache for updated orders
            foreach (array_keys($updates) as $orderId) {
                $this->invalidateCache($orderId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Order.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search orders with advanced criteria
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

        $cacheKey = $this->getCacheKey("order:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search orders: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive order
     */
    public function archive(string $orderId): bool
    {
        return $this->executeWithMetrics('archive_order', function () use ($orderId) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$orderId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($orderId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Order.Archived', [
                    'order_id' => $orderId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived order
     */
    public function restore(string $orderId): bool
    {
        return $this->executeWithMetrics('restore_order', function () use ($orderId) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$orderId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($orderId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Order.Restored', [
                    'order_id' => $orderId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get order history
     */
    public function getHistory(string $orderId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("order:history:{$orderId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($orderId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$orderId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint); if (!$response) {
                    if ($response->getStatusCode() === 404) {
                        throw new OrderNotFoundException("No history found for order ID: {$orderId}");
                    }
                    throw new HttpException(
                        "Failed to get order history: " . "HTTP request failed",
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
    public function getRelated(string $orderId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("order:related:{$orderId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($orderId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$orderId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint); if (!$response) {
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
    public function addRelationship(string $orderId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($orderId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/{$orderId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("order:related:{$orderId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $orderId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($orderId, $relatedId, $relationType) {
            $response = $this->makeHttpRequestAndExtractData('DELETE', "{$this->getEndpoint()}/{$orderId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("order:related:{$orderId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate order cache
     */
    public function invalidateCache(string $orderId): void
    {
        $patterns = [
            $this->getCacheKey("order:{$orderId}"),
            $this->getCacheKey("order:*:{$orderId}"),
            $this->getCacheKey("order:related:{$orderId}:*"),
            $this->getCacheKey("order:history:{$orderId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for order
     */
    public function warmCache(string $orderId): void
    {
        try {
            // Preload order data
            $this->findById($orderId);

            // Preload common relationships
            // $this->getRelated($orderId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for order', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all order caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('order:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All order caches cleared');
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequestAndExtractData(string $method, string $uri, array $options = []): array
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
