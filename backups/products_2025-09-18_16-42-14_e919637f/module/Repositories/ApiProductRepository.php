<?php

namespace Clubify\Checkout\Modules\Products\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Product Repository
 *
 * Implementa repository para produtos usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * @package Clubify\Checkout\Modules\Products\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiProductRepository extends BaseRepository
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

    /**
     * Find product by slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("product:slug:{$slug}"),
            function () use ($slug) {
                try {
                    $response = $this->httpClient->get("{$this->getEndpoint()}/slug/{$slug}");

                    if (!$response->isSuccessful()) {
                        if ($response->getStatusCode() === 404) {
                            return null;
                        }
                        throw new HttpException(
                            "Failed to fetch product by slug: {$slug}",
                            $response->getStatusCode()
                        );
                    }

                    return $response->getData();
                } catch (HttpException $e) {
                    $this->logger->error('Failed to fetch product by slug', [
                        'slug' => $slug,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            },
            3600
        );
    }

    /**
     * Find product by SKU
     */
    public function findBySku(string $sku): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("product:sku:{$sku}"),
            function () use ($sku) {
                try {
                    $response = $this->httpClient->get("{$this->getEndpoint()}/sku/{$sku}");

                    if (!$response->isSuccessful()) {
                        if ($response->getStatusCode() === 404) {
                            return null;
                        }
                        throw new HttpException(
                            "Failed to fetch product by SKU: {$sku}",
                            $response->getStatusCode()
                        );
                    }

                    return $response->getData();
                } catch (HttpException $e) {
                    $this->logger->error('Failed to fetch product by SKU', [
                        'sku' => $sku,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
            },
            1800
        );
    }

    /**
     * Search products
     */
    public function search(string $query, array $filters = []): array
    {
        return $this->executeWithMetrics('search_products', function () use ($query, $filters) {
            try {
                $queryParams = array_merge($filters, ['q' => $query]);
                $response = $this->httpClient->get("{$this->getEndpoint()}/search", [
                    'query' => $queryParams
                ]);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to search products: {$query}",
                        $response->getStatusCode()
                    );
                }

                return $response->getData() ?? [];
            } catch (HttpException $e) {
                $this->logger->error('Failed to search products', [
                    'query' => $query,
                    'filters' => $filters,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Update product stock
     */
    public function updateStock(string $productId, int $quantity, string $operation = 'set'): bool
    {
        return $this->executeWithMetrics('update_stock', function () use ($productId, $quantity, $operation) {
            try {
                $response = $this->httpClient->put("{$this->getEndpoint()}/{$productId}/stock", [
                    'quantity' => $quantity,
                    'operation' => $operation
                ]);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to update stock for product: {$productId}",
                        $response->getStatusCode()
                    );
                }

                return true;
            } catch (HttpException $e) {
                $this->logger->error('Failed to update product stock', [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'operation' => $operation,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }
}