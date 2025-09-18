<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de produtos
 *
 * Responsável pelas operações CRUD e lógica de negócio de produtos:
 * - Criação e edição de produtos
 * - Gestão de categorias e classificação
 * - Controle de estoque
 * - Operações de busca e filtros
 * - Relatórios e estatísticas
 * - Validação de dados
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de produto
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de produto
 * - D: Dependency Inversion - Depende de abstrações
 */
class ProductService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'product';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo produto
     */
    public function create(array $productData): array
    {
        return $this->executeWithMetrics('create_product', function () use ($productData) {
            $this->validateProductData($productData);

            // Gerar slug se não fornecido
            if (empty($productData['slug'])) {
                $productData['slug'] = $this->generateSlug($productData['name']);
            }

            // Verificar unicidade do slug
            if ($this->slugExists($productData['slug'])) {
                $productData['slug'] = $this->generateUniqueSlug($productData['slug']);
            }

            // Gerar SKU se não fornecido
            if (empty($productData['sku'])) {
                $productData['sku'] = $this->generateSku($productData);
            }

            // Verificar unicidade do SKU
            if ($this->skuExists($productData['sku'])) {
                throw new ValidationException("SKU '{$productData['sku']}' already exists");
            }

            // Preparar dados do produto
            $data = array_merge($productData, [
                'status' => $productData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => $this->generateProductMetadata($productData)
            ]);

            // Criar produto via API
            $response = $this->httpClient->post('/products', $data);
            $product = $response->getData();

            // Cache do produto
            $this->cache->set($this->getCacheKey("product:{$product['id']}"), $product, 3600);
            $this->cache->set($this->getCacheKey("product_slug:{$product['slug']}"), $product, 3600);

            // Dispatch evento
            $this->dispatch('product.created', [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'type' => $product['type'],
                'price' => $product['price']
            ]);

            $this->logger->info('Product created successfully', [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku']
            ]);

            return $product;
        });
    }

    /**
     * Obtém um produto por ID
     */
    public function get(string $productId): ?array
    {
        return $this->getCachedOrExecute(
            "product:{$productId}",
            fn () => $this->fetchProductById($productId),
            3600
        );
    }

    /**
     * Obtém produto por slug
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->getCachedOrExecute(
            "product_slug:{$slug}",
            fn () => $this->fetchProductBySlug($slug),
            3600
        );
    }

    /**
     * Obtém produto por SKU
     */
    public function getBySku(string $sku): ?array
    {
        return $this->getCachedOrExecute(
            "product_sku:{$sku}",
            fn () => $this->fetchProductBySku($sku),
            1800
        );
    }

    /**
     * Atualiza produto
     */
    public function update(string $productId, array $data): array
    {
        return $this->executeWithMetrics('update_product', function () use ($productId, $data) {
            $this->validateProductUpdateData($data);

            // Verificar se produto existe
            $currentProduct = $this->get($productId);
            if (!$currentProduct) {
                throw new ValidationException("Product not found: {$productId}");
            }

            // Verificar unicidade do slug se alterado
            if (isset($data['slug']) && $data['slug'] !== $currentProduct['slug']) {
                if ($this->slugExists($data['slug'])) {
                    throw new ValidationException("Slug '{$data['slug']}' already exists");
                }
            }

            // Verificar unicidade do SKU se alterado
            if (isset($data['sku']) && $data['sku'] !== $currentProduct['sku']) {
                if ($this->skuExists($data['sku'])) {
                    throw new ValidationException("SKU '{$data['sku']}' already exists");
                }
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/products/{$productId}", $data);
            $product = $response->getData();

            // Invalidar cache
            $this->invalidateProductCache($productId);

            // Dispatch evento
            $this->dispatch('product.updated', [
                'product_id' => $productId,
                'updated_fields' => array_keys($data)
            ]);

            return $product;
        });
    }

    /**
     * Lista produtos com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_products', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/products', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Busca produtos por texto
     */
    public function search(string $query, array $filters = []): array
    {
        return $this->executeWithMetrics('search_products', function () use ($query, $filters) {
            $queryParams = array_merge($filters, ['q' => $query]);

            $response = $this->httpClient->get('/products/search', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém produtos por categoria
     */
    public function getByCategory(string $categoryId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_products_by_category', function () use ($categoryId, $filters) {
            $queryParams = array_merge($filters, ['category_id' => $categoryId]);

            $response = $this->httpClient->get('/products', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém produtos em destaque
     */
    public function getFeatured(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_featured_products', function () use ($limit) {
            $response = $this->httpClient->get('/products/featured', [
                'query' => ['limit' => $limit]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém produtos mais vendidos
     */
    public function getBestSellers(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_best_seller_products', function () use ($limit) {
            $response = $this->httpClient->get('/products/best-sellers', [
                'query' => ['limit' => $limit]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém produtos relacionados
     */
    public function getRelated(string $productId, int $limit = 5): array
    {
        return $this->executeWithMetrics('get_related_products', function () use ($productId, $limit) {
            $response = $this->httpClient->get("/products/{$productId}/related", [
                'query' => ['limit' => $limit]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Atualiza estoque de produto
     */
    public function updateStock(string $productId, int $quantity, string $operation = 'set'): bool
    {
        return $this->executeWithMetrics('update_product_stock', function () use ($productId, $quantity, $operation) {
            $allowedOperations = ['set', 'add', 'subtract'];
            if (!in_array($operation, $allowedOperations)) {
                throw new ValidationException("Invalid stock operation: {$operation}");
            }

            $response = $this->httpClient->put("/products/{$productId}/stock", [
                'quantity' => $quantity,
                'operation' => $operation
            ]);

            // Invalidar cache do produto
            $this->invalidateProductCache($productId);

            // Dispatch evento
            $this->dispatch('product.stock_updated', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'operation' => $operation
            ]);

            return $response->getStatusCode() === 200;
        });
    }

    /**
     * Verifica disponibilidade de estoque
     */
    public function checkStock(string $productId, int $quantity = 1): bool
    {
        return $this->executeWithMetrics('check_product_stock', function () use ($productId, $quantity) {
            $response = $this->httpClient->get("/products/{$productId}/stock", [
                'query' => ['quantity' => $quantity]
            ]);

            $data = $response->getData();
            return $data['available'] ?? false;
        });
    }

    /**
     * Ativa produto
     */
    public function activate(string $productId): bool
    {
        return $this->updateStatus($productId, 'active');
    }

    /**
     * Desativa produto
     */
    public function deactivate(string $productId): bool
    {
        return $this->updateStatus($productId, 'inactive');
    }

    /**
     * Arquiva produto
     */
    public function archive(string $productId): bool
    {
        return $this->updateStatus($productId, 'archived');
    }

    /**
     * Duplica produto
     */
    public function duplicate(string $productId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_product', function () use ($productId, $overrideData) {
            $response = $this->httpClient->post("/products/{$productId}/duplicate", $overrideData);
            $product = $response->getData();

            // Dispatch evento
            $this->dispatch('product.duplicated', [
                'original_id' => $productId,
                'new_id' => $product['id']
            ]);

            return $product;
        });
    }

    /**
     * Obtém estatísticas de vendas do produto
     */
    public function getSalesStats(string $productId): array
    {
        return $this->executeWithMetrics('get_product_sales_stats', function () use ($productId) {
            $response = $this->httpClient->get("/products/{$productId}/sales-stats");
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém histórico de preços
     */
    public function getPriceHistory(string $productId): array
    {
        return $this->executeWithMetrics('get_product_price_history', function () use ($productId) {
            $response = $this->httpClient->get("/products/{$productId}/price-history");
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém variações do produto
     */
    public function getVariations(string $productId): array
    {
        return $this->executeWithMetrics('get_product_variations', function () use ($productId) {
            $response = $this->httpClient->get("/products/{$productId}/variations");
            return $response->getData() ?? [];
        });
    }

    /**
     * Remove produto
     */
    public function delete(string $productId): bool
    {
        return $this->executeWithMetrics('delete_product', function () use ($productId) {
            try {
                $response = $this->httpClient->delete("/products/{$productId}");

                // Invalidar cache
                $this->invalidateProductCache($productId);

                // Dispatch evento
                $this->dispatch('product.deleted', [
                    'product_id' => $productId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete product', [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta total de produtos
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->httpClient->get('/products/count', [
                'query' => $filters
            ]);
            $data = $response->getData();
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count products', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Busca produto por ID via API
     */
    private function fetchProductById(string $productId): ?array
    {
        try {
            $response = $this->httpClient->get("/products/{$productId}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca produto por slug via API
     */
    private function fetchProductBySlug(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/products/slug/{$slug}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca produto por SKU via API
     */
    private function fetchProductBySku(string $sku): ?array
    {
        try {
            $response = $this->httpClient->get("/products/sku/{$sku}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do produto
     */
    private function updateStatus(string $productId, string $status): bool
    {
        return $this->executeWithMetrics("update_product_status_{$status}", function () use ($productId, $status) {
            try {
                $response = $this->httpClient->put("/products/{$productId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateProductCache($productId);

                // Dispatch evento
                $this->dispatch('product.status_changed', [
                    'product_id' => $productId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update product status to {$status}", [
                    'product_id' => $productId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do produto
     */
    private function invalidateProductCache(string $productId): void
    {
        $product = $this->get($productId);

        $this->cache->delete($this->getCacheKey("product:{$productId}"));

        if ($product && isset($product['slug'])) {
            $this->cache->delete($this->getCacheKey("product_slug:{$product['slug']}"));
        }

        if ($product && isset($product['sku'])) {
            $this->cache->delete($this->getCacheKey("product_sku:{$product['sku']}"));
        }
    }

    /**
     * Valida dados do produto
     */
    private function validateProductData(array $data): void
    {
        $required = ['name', 'price', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for product creation");
            }
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new ValidationException('Price must be a positive number');
        }

        $allowedTypes = ['physical', 'digital', 'service', 'subscription'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid product type: {$data['type']}");
        }

        if (isset($data['stock']) && (!is_numeric($data['stock']) || $data['stock'] < 0)) {
            throw new ValidationException('Stock must be a non-negative number');
        }
    }

    /**
     * Valida dados de atualização do produto
     */
    private function validateProductUpdateData(array $data): void
    {
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new ValidationException('Price must be a positive number');
        }

        if (isset($data['type'])) {
            $allowedTypes = ['physical', 'digital', 'service', 'subscription'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid product type: {$data['type']}");
            }
        }

        if (isset($data['stock']) && (!is_numeric($data['stock']) || $data['stock'] < 0)) {
            throw new ValidationException('Stock must be a non-negative number');
        }
    }

    /**
     * Verifica se slug já existe
     */
    private function slugExists(string $slug): bool
    {
        try {
            $product = $this->fetchProductBySlug($slug);
            return $product !== null;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * Verifica se SKU já existe
     */
    private function skuExists(string $sku): bool
    {
        try {
            $product = $this->fetchProductBySku($sku);
            return $product !== null;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    /**
     * Gera slug único
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $counter = 1;
        $slug = $baseSlug;

        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Gera SKU automático
     */
    private function generateSku(array $productData): string
    {
        $prefix = strtoupper(substr($productData['type'], 0, 3));
        $timestamp = substr((string) time(), -6);
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Gera metadados do produto
     */
    private function generateProductMetadata(array $productData): array
    {
        return [
            'created_by' => 'sdk',
            'version' => '1.0',
            'source' => 'api',
            'type_category' => $this->getTypeCategory($productData['type']),
            'searchable_text' => $this->generateSearchableText($productData)
        ];
    }

    /**
     * Obtém categoria do tipo
     */
    private function getTypeCategory(string $type): string
    {
        return match ($type) {
            'physical' => 'goods',
            'digital' => 'digital_goods',
            'service' => 'services',
            'subscription' => 'recurring',
            default => 'other'
        };
    }

    /**
     * Gera texto pesquisável
     */
    private function generateSearchableText(array $productData): string
    {
        $searchableFields = [
            $productData['name'] ?? '',
            $productData['description'] ?? '',
            $productData['tags'] ?? '',
            $productData['category'] ?? ''
        ];

        return strtolower(implode(' ', array_filter($searchableFields)));
    }
}
