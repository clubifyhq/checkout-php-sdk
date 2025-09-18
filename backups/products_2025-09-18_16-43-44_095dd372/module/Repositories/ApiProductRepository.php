<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Product pelo nome da entidade (ex: Order)
 * 2. Substitua product pela versão lowercase (ex: order)
 * 3. Substitua Products pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Product = Order
 * - product = order
 * - Products = OrderManagement
 */

namespace Clubify\Checkout\Modules\Products\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Products\Contracts\ProductRepositoryInterface;
use Clubify\Checkout\Modules\Products\Exceptions\ProductNotFoundException;
use Clubify\Checkout\Modules\Products\Exceptions\ProductValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Product Repository
 *
 * Implementa o ProductRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    products                 - List products
 * - POST   products                 - Create product
 * - GET    products/{id}           - Get product by ID
 * - PUT    products/{id}           - Update product
 * - DELETE products/{id}           - Delete product
 * - GET    products/search         - Search products
 * - PATCH  products/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Products\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    /**
     * Get API endpoint for products
     */
    protected function getEndpoint(): string
    {
        return 'products';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'product';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'product-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from ProductRepositoryInterface
    // ==============================================

    /**
     * Find product by specific field
     */
    public function findBy{Field}(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("product:{field}:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->httpClient->get("{$this->getEndpoint()}/search", [
                    '{field}' => $fieldValue
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find product by {field}: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['products'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find products by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update product status
     */
    public function updateStatus(string $productId, string $status): bool
    {
        return $this->executeWithMetrics('update_product_status', function () use ($productId, $status) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$productId}/status", [
                'status' => $status
            ]);

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($productId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Product.StatusUpdated', [
                    'product_id' => $productId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get product statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("product:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get product stats: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create products
     */
    public function bulkCreate(array $productsData): array
    {
        return $this->executeWithMetrics('bulk_create_products', function () use ($productsData) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/bulk", [
                'products' => $productsData
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk create products: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Product.BulkCreated', [
                'count' => count($productsData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update products
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_products', function () use ($updates) {
            $response = $this->httpClient->put("{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk update products: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Clear cache for updated products
            foreach (array_keys($updates) as $productId) {
                $this->invalidateCache($productId);
            }

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Product.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search products with advanced criteria
     */
    public function search(array $criteria, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("product:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                $response = $this->httpClient->post("{$this->getEndpoint()}/search", $payload);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to search products: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive product
     */
    public function archive(string $productId): bool
    {
        return $this->executeWithMetrics('archive_product', function () use ($productId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$productId}/archive");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($productId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Product.Archived', [
                    'product_id' => $productId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived product
     */
    public function restore(string $productId): bool
    {
        return $this->executeWithMetrics('restore_product', function () use ($productId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$productId}/restore");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($productId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Product.Restored', [
                    'product_id' => $productId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get product history
     */
    public function getHistory(string $productId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("product:history:{$productId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($productId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$productId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        throw new ProductNotFoundException("No history found for product ID: {$productId}");
                    }
                    throw new HttpException(
                        "Failed to get product history: " . $response->getError(),
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
    public function getRelated(string $productId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("product:related:{$productId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($productId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$productId}/{$relationType}";
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
    public function addRelationship(string $productId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($productId, $relatedId, $relationType, $metadata) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/{$productId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("product:related:{$productId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $productId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($productId, $relatedId, $relationType) {
            $response = $this->httpClient->delete("{$this->getEndpoint()}/{$productId}/{$relationType}/{$relatedId}");

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("product:related:{$productId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate product cache
     */
    public function invalidateCache(string $productId): void
    {
        $patterns = [
            $this->getCacheKey("product:{$productId}"),
            $this->getCacheKey("product:*:{$productId}"),
            $this->getCacheKey("product:related:{$productId}:*"),
            $this->getCacheKey("product:history:{$productId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for product
     */
    public function warmCache(string $productId): void
    {
        try {
            // Preload product data
            $this->findById($productId);

            // Preload common relationships
            // $this->getRelated($productId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for product', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all product caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('product:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All product caches cleared');
    }
}