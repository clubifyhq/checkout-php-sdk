<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
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
class ProductService extends BaseService implements ServiceInterface
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

            // Gerar slug se não fornecido (para cache)
            if (empty($productData['slug'])) {
                $productData['slug'] = $this->generateSlug($productData['name']);
            }

            // Gerar SKU se não fornecido (para cache)
            if (empty($productData['sku'])) {
                $productData['sku'] = $this->generateSku($productData);
            }

            // Nota: Não verificamos unicidade mais pois esses campos não vão para a API
            // A API vai gerar seus próprios IDs e controlar a unicidade

            // Preparar dados essenciais do produto (apenas campos mínimos que a API aceita)
            $data = [
                'name' => (string) $productData['name'],
                'type' => (string) $productData['type']
            ];

            // Adicionar campos opcionais SOMENTE se a API aceitar (testando um por vez)
            if (isset($productData['description'])) {
                $data['description'] = (string) $productData['description'];
            }

            // Campos rejeitados pela API: price, status, slug, sku
            // Vamos armazenar esses dados no cache para uso posterior
            $rejectedFields = [];

            if (isset($productData['price'])) {
                $rejectedFields['price'] = (float) $productData['price'];
            }

            if (isset($productData['status'])) {
                $rejectedFields['status'] = $productData['status'];
            }

            if (!empty($productData['slug'])) {
                $rejectedFields['slug'] = $productData['slug'];
            }

            if (!empty($productData['sku'])) {
                $rejectedFields['sku'] = $productData['sku'];
            }

            // Criar produto via API
            $product = $this->makeHttpRequest('POST', '/products', ['json' => $data]);

            // Cache do produto com campos rejeitados incluídos
            if (isset($product['id'])) {
                // Merge do produto retornado pela API com campos rejeitados
                $enrichedProduct = array_merge($product, $rejectedFields);

                $this->cache->set($this->getCacheKey("product:{$product['id']}"), $enrichedProduct, 3600);

                // Cache por slug se disponível
                if (!empty($rejectedFields['slug'])) {
                    $this->cache->set($this->getCacheKey("product_slug:{$rejectedFields['slug']}"), $enrichedProduct, 3600);
                }

                // Cache por SKU se disponível
                if (!empty($rejectedFields['sku'])) {
                    $this->cache->set($this->getCacheKey("product_sku:{$rejectedFields['sku']}"), $enrichedProduct, 3600);
                }

                // Dispatch evento
                $this->dispatch('product.created', [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'type' => $product['type'],
                    'price' => $rejectedFields['price'] ?? null
                ]);

                $this->logger->info('Product created successfully', [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'sku' => $rejectedFields['sku'] ?? null,
                    'api_fields' => array_keys($data),
                    'cached_fields' => array_keys($rejectedFields)
                ]);

                // Retornar o produto enriquecido
                return $enrichedProduct;
            }

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

            // CORREÇÃO: Não adicionar updated_at - a API gerencia timestamps automaticamente
            // $data['updated_at'] = date('Y-m-d H:i:s');

            $product = $this->makeHttpRequest('PATCH', "/products/{$productId}", ['json' => $data]);

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
     * Lista produtos com filtros (filtragem no lado cliente para parâmetros não aceitos)
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_products', function () use ($filters, $page, $limit) {
            // Separar filtros aceitos pela API dos que precisam ser filtrados no cliente
            $apiFilters = [];
            $clientFilters = [];

            // Lista de filtros que sabemos que a API NÃO aceita
            $unsupportedFilters = ['search', 'q', 'slug', 'sku'];

            foreach ($filters as $key => $value) {
                if (in_array($key, $unsupportedFilters)) {
                    $clientFilters[$key] = $value;
                } else {
                    $apiFilters[$key] = $value;
                }
            }

            $queryParams = array_merge($apiFilters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->makeHttpRequest('GET', '/products', [
                'query' => $queryParams
            ]);

            $data = $response['data'] ?? $response ?? [];
            $products = is_array($data) ? $data : [];

            // Aplicar filtros do lado cliente se necessário
            if (!empty($clientFilters)) {
                $products = $this->filterProductsClientSide($products, $clientFilters);
            }

            return ['data' => $products];
        });
    }

    /**
     * Busca produtos por texto (filtrando no lado cliente)
     */
    public function search(string $query, array $filters = []): array
    {
        return $this->executeWithMetrics('search_products', function () use ($query, $filters) {
            // Buscar todos os produtos com filtros básicos
            $queryParams = array_merge($filters, ['limit' => 100]);

            $response = $this->makeHttpRequest('GET', '/products', [
                'query' => $queryParams
            ]);

            $data = $response['data'] ?? $response ?? [];
            $products = is_array($data) ? $data : [];

            // Filtrar produtos que contêm a query no nome, descrição ou SKU
            $filteredProducts = [];
            $queryLower = strtolower($query);

            foreach ($products as $product) {
                $searchableText = strtolower(implode(' ', [
                    $product['name'] ?? '',
                    $product['description'] ?? '',
                    $product['sku'] ?? '',
                    $product['slug'] ?? ''
                ]));

                if (strpos($searchableText, $queryLower) !== false) {
                    $filteredProducts[] = $product;
                }
            }

            return $filteredProducts;
        });
    }

    /**
     * Obtém produtos por categoria
     */
    public function getByCategory(string $categoryId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_products_by_category', function () use ($categoryId, $filters) {
            $queryParams = array_merge($filters, ['category_id' => $categoryId]);

            $response = $this->makeHttpRequest('GET', '/products', [
                'query' => $queryParams
            ]);

            // makeHttpRequest já retorna dados decodificados
            return $response['data'] ?? $response ?? [];
        });
    }

    /**
     * Obtém produtos em destaque
     */
    public function getFeatured(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_featured_products', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/products/featured', [
                'query' => ['limit' => $limit]
            ]);

            // makeHttpRequest já retorna dados decodificados
            return $response['data'] ?? $response ?? [];
        });
    }

    /**
     * Obtém produtos mais vendidos
     */
    public function getBestSellers(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_best_seller_products', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/products/best-sellers', [
                'query' => ['limit' => $limit]
            ]);

            // makeHttpRequest já retorna dados decodificados
            return $response['data'] ?? $response ?? [];
        });
    }

    /**
     * Obtém produtos relacionados
     */
    public function getRelated(string $productId, int $limit = 5): array
    {
        return $this->executeWithMetrics('get_related_products', function () use ($productId, $limit) {
            $response = $this->makeHttpRequest('GET', "/products/{$productId}/related", [
                'query' => ['limit' => $limit]
            ]);

            // makeHttpRequest já retorna dados decodificados
            return $response['data'] ?? $response ?? [];
        });
    }

    /**
     * Atualiza preço de produto
     * Endpoint: PATCH /products/:id
     *
     * @param string $productId ID do produto
     * @param float|int $price Preço em centavos (ex: 2999 = R$ 29,99)
     * @param string $currency Código da moeda (USD, BRL, etc)
     * @param array $additionalPricing Dados adicionais de pricing (compareAtPrice, costPerItem, taxInclusive)
     * @return array Produto atualizado
     */
    public function updatePrice(
        string $productId,
        $price,
        string $currency = 'BRL',
        array $additionalPricing = []
    ): array {
        return $this->executeWithMetrics('update_product_price', function () use ($productId, $price, $currency, $additionalPricing) {
            // Validar preço
            if (!is_numeric($price) || $price < 0) {
                throw new ValidationException('Price must be a positive number');
            }

            // Validar moeda
            $allowedCurrencies = ['USD', 'BRL', 'EUR', 'GBP'];
            if (!in_array(strtoupper($currency), $allowedCurrencies)) {
                throw new ValidationException("Invalid currency: {$currency}. Allowed: " . implode(', ', $allowedCurrencies));
            }

            // Construir payload de pricing
            $pricingData = [
                'price' => (int) $price,
                'currency' => strtoupper($currency)
            ];

            // Adicionar campos opcionais se fornecidos
            if (isset($additionalPricing['compareAtPrice']) && is_numeric($additionalPricing['compareAtPrice'])) {
                $pricingData['compareAtPrice'] = (int) $additionalPricing['compareAtPrice'];
            }

            if (isset($additionalPricing['costPerItem']) && is_numeric($additionalPricing['costPerItem'])) {
                $pricingData['costPerItem'] = (int) $additionalPricing['costPerItem'];
            }

            if (isset($additionalPricing['taxInclusive'])) {
                $pricingData['taxInclusive'] = (bool) $additionalPricing['taxInclusive'];
            }

            $this->logger->info('Updating product price', [
                'product_id' => $productId,
                'new_price' => $price,
                'currency' => $currency
            ]);

            // Atualizar produto com novo pricing
            $product = $this->makeHttpRequest('PATCH', "/products/{$productId}", [
                'json' => ['pricing' => $pricingData]
            ]);

            // Invalidar cache do produto
            $this->invalidateProductCache($productId);

            // Dispatch evento
            $this->dispatch('product.price_updated', [
                'product_id' => $productId,
                'old_price' => null, // Poderia buscar do cache se necessário
                'new_price' => $price,
                'currency' => $currency
            ]);

            $this->logger->info('Product price updated successfully', [
                'product_id' => $productId,
                'price' => $price,
                'currency' => $currency
            ]);

            return $product;
        });
    }

    /**
     * Atualiza estoque de produto
     * Endpoint: PATCH /products/:id/inventory
     *
     * @param string $productId ID do produto
     * @param int $quantity Quantidade para ajustar
     * @param string $operation Operação: 'set' (define), 'add' (adiciona), 'subtract' (subtrai)
     * @return bool Sucesso da operação
     */
    public function updateStock(string $productId, int $quantity, string $operation = 'set'): bool
    {
        return $this->executeWithMetrics('update_product_stock', function () use ($productId, $quantity, $operation) {
            $allowedOperations = ['set', 'add', 'subtract'];
            if (!in_array($operation, $allowedOperations)) {
                throw new ValidationException("Invalid stock operation: {$operation}");
            }

            // Calcular quantityChange baseado na operação
            // A API espera quantityChange: número positivo para adicionar, negativo para subtrair
            $quantityChange = match ($operation) {
                'set' => $quantity, // Para 'set', a API deve receber a quantidade absoluta
                'add' => $quantity,
                'subtract' => -$quantity,
                default => $quantity
            };

            $data = $this->makeHttpRequest('PATCH', "/products/{$productId}/inventory", ['json' => [
                'quantityChange' => $quantityChange
            ]]);

            // Invalidar cache do produto
            $this->invalidateProductCache($productId);

            // Dispatch evento
            $this->dispatch('product.stock_updated', [
                'product_id' => $productId,
                'quantity' => $quantity,
                'operation' => $operation,
                'quantityChange' => $quantityChange
            ]);

            return $data['success'] ?? true; // Assumir sucesso se não há indicação contrária
        });
    }

    /**
     * Verifica disponibilidade de estoque
     */
    public function checkStock(string $productId, int $quantity = 1): bool
    {
        return $this->executeWithMetrics('check_product_stock', function () use ($productId, $quantity) {
            $data = $this->makeHttpRequest('GET', "/products/{$productId}/stock", [
                'query' => ['quantity' => $quantity]
            ]);

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
            $product = $this->makeHttpRequest('POST', "/products/{$productId}/duplicate", ['json' => $overrideData]);

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
            $data = $this->makeHttpRequest('GET', "/products/{$productId}/sales-stats");
            // makeHttpRequest já retorna dados decodificados
            return $data['data'] ?? $data ?? [];
        });
    }

    /**
     * Obtém histórico de preços
     */
    public function getPriceHistory(string $productId): array
    {
        return $this->executeWithMetrics('get_product_price_history', function () use ($productId) {
            $data = $this->makeHttpRequest('GET', "/products/{$productId}/price-history");
            // makeHttpRequest já retorna dados decodificados
            return $data['data'] ?? $data ?? [];
        });
    }

    /**
     * Obtém variações do produto
     */
    public function getVariations(string $productId): array
    {
        return $this->executeWithMetrics('get_product_variations', function () use ($productId) {
            $response = $this->makeHttpRequest('GET', "/products/{$productId}/variations");
            // makeHttpRequest já retorna dados decodificados
            return $response['data'] ?? $response ?? [];
        });
    }

    /**
     * Remove produto
     */
    public function delete(string $productId): bool
    {
        return $this->executeWithMetrics('delete_product', function () use ($productId) {
            try {
                $data = $this->makeHttpRequest('DELETE', "/products/{$productId}");

                // Invalidar cache
                $this->invalidateProductCache($productId);

                // Dispatch evento
                $this->dispatch('product.deleted', [
                    'product_id' => $productId
                ]);

                return $data['success'] ?? true; // Assumir sucesso se não há erro
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
            $data = $this->makeHttpRequest('GET', '/products/count', [
                'query' => $filters
            ]);
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
            return $this->makeHttpRequest('GET', "/products/{$productId}");
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca produto por slug via API (listando todos e filtrando)
     */
    private function fetchProductBySlug(string $slug): ?array
    {
        try {
            // Usar busca genérica e filtrar pelo slug no lado cliente
            $data = $this->makeHttpRequest('GET', '/products', [
                'query' => ['limit' => 100] // Buscar mais produtos para encontrar o slug
            ]);

            $products = $data['data'] ?? $data ?? [];

            // Filtrar pelo slug no lado cliente
            if (is_array($products) && !empty($products)) {
                foreach ($products as $product) {
                    if (isset($product['slug']) && $product['slug'] === $slug) {
                        return $product;
                    }
                }
            }

            return null;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca produto por SKU via API (listando todos e filtrando)
     */
    private function fetchProductBySku(string $sku): ?array
    {
        try {
            // Usar busca genérica e filtrar pelo SKU no lado cliente
            $data = $this->makeHttpRequest('GET', '/products', [
                'query' => ['limit' => 100] // Buscar mais produtos para encontrar o SKU
            ]);

            $products = $data['data'] ?? $data ?? [];

            // Filtrar pelo SKU no lado cliente
            if (is_array($products) && !empty($products)) {
                foreach ($products as $product) {
                    if (isset($product['sku']) && $product['sku'] === $sku) {
                        return $product;
                    }
                }
            }

            return null;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do produto
     * Endpoint: PATCH /products/:id
     *
     * @param string $productId ID do produto
     * @param string $status Status: 'active' (ativo), 'inactive' (inativo), 'archived' (arquivado)
     * @return bool Sucesso da operação
     */
    private function updateStatus(string $productId, string $status): bool
    {
        return $this->executeWithMetrics("update_product_status_{$status}", function () use ($productId, $status) {
            try {
                // Mapear status para isActive (a API usa isActive: boolean)
                // active -> isActive: true
                // inactive -> isActive: false
                // archived -> isActive: false
                $isActive = ($status === 'active');

                $data = $this->makeHttpRequest('PATCH', "/products/{$productId}", ['json' => [
                    'isActive' => $isActive
                ]]);

                // Invalidar cache
                $this->invalidateProductCache($productId);

                // Dispatch evento
                $this->dispatch('product.status_changed', [
                    'product_id' => $productId,
                    'new_status' => $status,
                    'isActive' => $isActive
                ]);

                return $data['success'] ?? true; // Assumir sucesso se não há erro
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
     * Valida dados do produto (apenas campos que serão enviados à API)
     */
    private function validateProductData(array $data): void
    {
        // Apenas campos que realmente vão para a API são obrigatórios
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new ValidationException("Field '{$field}' is required for product creation");
            }
        }

        // Validar que name é string
        if (!is_string($data['name'])) {
            throw new ValidationException('Field "name" must be a string');
        }

        // Verificar tipos válidos (pode ser que a API tenha tipos específicos)
        $allowedTypes = ['product', 'service', 'digital', 'physical', 'subscription'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid product type: {$data['type']}. Allowed types: " . implode(', ', $allowedTypes));
        }

        // Validações opcionais para campos que não vão para API mas ficam no cache
        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new ValidationException('Price must be a positive number');
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

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            // Log do payload para debugging
            if ($method === 'POST' && isset($options['json'])) {
                $this->logger->debug("Product API Request Payload", [
                    'method' => $method,
                    'uri' => $uri,
                    'payload' => $options['json']
                ]);
            } elseif ($method === 'POST' && !isset($options['json']) && !isset($options['query'])) {
                // Se não tem query nem json, usar os dados como json
                $options['json'] = $options;
                unset($options['method'], $options['uri']);

                $this->logger->debug("Product API Request Payload (converted to json)", [
                    'method' => $method,
                    'uri' => $uri,
                    'payload' => $options['json']
                ]);
            }

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
                'options' => $options,
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

    /**
     * Filtra produtos no lado cliente
     */
    private function filterProductsClientSide(array $products, array $filters): array
    {
        $filteredProducts = [];

        foreach ($products as $product) {
            $matches = true;

            foreach ($filters as $key => $value) {
                switch ($key) {
                    case 'search':
                        $searchableText = strtolower(implode(' ', [
                            $product['name'] ?? '',
                            $product['description'] ?? '',
                            $product['sku'] ?? '',
                            $product['slug'] ?? ''
                        ]));
                        if (strpos($searchableText, strtolower($value)) === false) {
                            $matches = false;
                        }
                        break;
                    case 'slug':
                        if (($product['slug'] ?? '') !== $value) {
                            $matches = false;
                        }
                        break;
                    case 'sku':
                        if (($product['sku'] ?? '') !== $value) {
                            $matches = false;
                        }
                        break;
                    case 'q':
                        $searchableText = strtolower(implode(' ', [
                            $product['name'] ?? '',
                            $product['description'] ?? '',
                            $product['sku'] ?? ''
                        ]));
                        if (strpos($searchableText, strtolower($value)) === false) {
                            $matches = false;
                        }
                        break;
                }

                if (!$matches) {
                    break;
                }
            }

            if ($matches) {
                $filteredProducts[] = $product;
            }
        }

        return $filteredProducts;
    }

}
