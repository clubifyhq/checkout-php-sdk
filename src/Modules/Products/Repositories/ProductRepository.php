<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Repositories\BaseRepository;
use Clubify\Checkout\Modules\Products\Repositories\ProductRepositoryInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Repositório de produtos
 *
 * Implementa operações de persistência para produtos via API HTTP.
 * Fornece funcionalidades específicas para gestão de produtos.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de produtos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseRepository
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações HTTP
 */
class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    /**
     * Obtém o endpoint base para produtos
     */
    protected function getEndpoint(): string
    {
        return '/products';
    }

    /**
     * Busca produtos por categoria
     */
    public function findByCategory(string $categoryId, array $filters = []): array
    {
        try {
            $queryParams = array_merge($filters, ['category_id' => $categoryId]);
            $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
                'query' => $queryParams
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find products by category', [
                'category_id' => $categoryId,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Busca produtos por tipo
     */
    public function findByType(string $type, array $filters = []): array
    {
        try {
            $queryParams = array_merge($filters, ['type' => $type]);
            $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
                'query' => $queryParams
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find products by type', [
                'type' => $type,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Busca produtos por status
     */
    public function findByStatus(string $status): array
    {
        try {
            $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
                'query' => ['status' => $status]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find products by status', [
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Busca produtos ativos
     */
    public function findActive(): array
    {
        return $this->findByStatus('active');
    }

    /**
     * Busca produtos por organização
     */
    public function findByOrganization(string $organizationId): array
    {
        try {
            $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
                'query' => ['organization_id' => $organizationId]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to find products by organization', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Busca produtos por slug
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/slug/{$slug}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca produtos por SKU
     */
    public function findBySku(string $sku): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/sku/{$sku}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca textual em produtos
     */
    public function search(string $query, array $filters = []): array
    {
        try {
            $queryParams = array_merge($filters, ['q' => $query]);
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                'query' => $queryParams
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to search products', [
                'query' => $query,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém produtos em destaque
     */
    public function getFeatured(int $limit = 10): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/featured", [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get featured products', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém produtos mais vendidos
     */
    public function getBestSellers(int $limit = 10): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/best-sellers", [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get best seller products', [
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém produtos relacionados
     */
    public function getRelated(string $productId, int $limit = 5): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$productId}/related", [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get related products', [
                'product_id' => $productId,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Verifica disponibilidade de estoque
     */
    public function checkStock(string $productId, int $quantity = 1): bool
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$productId}/stock", [
                'query' => ['quantity' => $quantity]
            ]);
            $data = ResponseHelper::getData($response);
            return $data['available'] ?? false;
        } catch (HttpException $e) {
            $this->logger->error('Failed to check product stock', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza estoque do produto
     */
    public function updateStock(string $productId, int $quantity, string $operation = 'set'): bool
    {
        try {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/{$productId}/stock", [
                'quantity' => $quantity,
                'operation' => $operation
            ]);
            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            $this->logger->error('Failed to update product stock', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém produtos com estoque baixo
     */
    public function getLowStock(int $threshold = 10): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/low-stock", [
                'query' => ['threshold' => $threshold]
            ]);
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get low stock products', [
                'threshold' => $threshold,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Ativa produto
     */
    public function activate(string $id): bool
    {
        return $this->updateStatus($id, 'active');
    }

    /**
     * Desativa produto
     */
    public function deactivate(string $id): bool
    {
        return $this->updateStatus($id, 'inactive');
    }

    /**
     * Obtém variações de um produto
     */
    public function getVariations(string $productId): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$productId}/variations");
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return [];
            }
            $this->logger->error('Failed to get product variations', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém preços históricos
     */
    public function getPriceHistory(string $productId): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$productId}/price-history");
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get product price history', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém estatísticas de vendas
     */
    public function getSalesStats(string $productId): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$productId}/sales-stats");
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get product sales stats', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Duplica produto
     */
    public function duplicate(string $id, array $overrideData = []): array
    {
        try {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$id}/duplicate", $overrideData);
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            $this->logger->error('Failed to duplicate product', [
                'product_id' => $id,
                'override_data' => $overrideData,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Atualiza status do produto
     */
    private function updateStatus(string $id, string $status): bool
    {
        try {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/{$id}/status", [
                'status' => $status
            ]);
            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            $this->logger->error("Failed to update product status to {$status}", [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validação específica para produtos
     */
    protected function validateData(array $data): void
    {
        parent::validateData($data);

        $required = ['name', 'price', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for product");
            }
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new ValidationException('Price must be a positive number');
        }

        if (isset($data['stock']) && (!is_numeric($data['stock']) || $data['stock'] < 0)) {
            throw new ValidationException('Stock must be a non-negative number');
        }

        $allowedTypes = ['physical', 'digital', 'service', 'subscription'];
        if (isset($data['type']) && !in_array($data['type'], $allowedTypes)) {
            throw new ValidationException('Invalid product type');
        }

        $allowedStatuses = ['active', 'inactive', 'draft', 'archived'];
        if (isset($data['status']) && !in_array($data['status'], $allowedStatuses)) {
            throw new ValidationException('Invalid product status');
        }
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
