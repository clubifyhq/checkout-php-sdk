<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de ofertas
 *
 * Responsável pela gestão completa de ofertas e configurações:
 * - CRUD de ofertas
 * - Configuração de layouts e temas
 * - Gestão de bundles e combos
 * - Configuração de checkout
 * - Templates de ofertas
 * - A/B testing de ofertas
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de oferta
 * - O: Open/Closed - Extensível via tipos de oferta
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de oferta
 * - D: Dependency Inversion - Depende de abstrações
 */
class OfferService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'offer';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria uma nova oferta
     */
    public function create(array $offerData): array
    {
        return $this->executeWithMetrics('create_offer', function () use ($offerData) {
            $this->validateOfferData($offerData);

            // Gerar slug se não fornecido
            if (empty($offerData['slug'])) {
                $offerData['slug'] = $this->generateSlug($offerData['name']);
            }

            // Verificar unicidade do slug
            if ($this->slugExists($offerData['slug'])) {
                $offerData['slug'] = $this->generateUniqueSlug($offerData['slug']);
            }

            // Preparar dados da oferta
            $data = array_merge($offerData, [
                'status' => $offerData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildOfferConfiguration($offerData),
                'metadata' => $this->generateOfferMetadata($offerData)
            ]);

            // Criar oferta via API
            $response = $this->makeHttpRequest('POST', '/offers', $data);
            $offer = ResponseHelper::getData($response);

            // Cache da oferta
            $this->cache->set($this->getCacheKey("offer:{$offer['id']}"), $offer, 3600);
            $this->cache->set($this->getCacheKey("offer_slug:{$offer['slug']}"), $offer, 3600);

            // Dispatch evento
            $this->dispatch('offer.created', [
                'offer_id' => $offer['id'],
                'name' => $offer['name'],
                'type' => $offer['type'],
                'product_count' => count($offer['products'] ?? [])
            ]);

            $this->logger->info('Offer created successfully', [
                'offer_id' => $offer['id'],
                'name' => $offer['name'],
                'slug' => $offer['slug']
            ]);

            return $offer;
        });
    }

    /**
     * Obtém uma oferta por ID
     */
    public function get(string $offerId): ?array
    {
        return $this->getCachedOrExecute(
            "offer:{$offerId}",
            fn () => $this->fetchOfferById($offerId),
            3600
        );
    }

    /**
     * Obtém oferta por slug
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->getCachedOrExecute(
            "offer_slug:{$slug}",
            fn () => $this->fetchOfferBySlug($slug),
            3600
        );
    }

    /**
     * Obtém ofertas por produto
     */
    public function getByProduct(string $productId): array
    {
        return $this->executeWithMetrics('get_offers_by_product', function () use ($productId) {
            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => ['product_id' => $productId]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Atualiza oferta
     */
    public function update(string $offerId, array $data): array
    {
        return $this->executeWithMetrics('update_offer', function () use ($offerId, $data) {
            $this->validateOfferUpdateData($data);

            // Verificar se oferta existe
            $currentOffer = $this->get($offerId);
            if (!$currentOffer) {
                throw new ValidationException("Offer not found: {$offerId}");
            }

            // Verificar unicidade do slug se alterado
            if (isset($data['slug']) && $data['slug'] !== $currentOffer['slug']) {
                if ($this->slugExists($data['slug'])) {
                    throw new ValidationException("Slug '{$data['slug']}' already exists");
                }
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}", $data);
            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.updated', [
                'offer_id' => $offerId,
                'updated_fields' => array_keys($data)
            ]);

            return $offer;
        });
    }

    /**
     * Configura layout da oferta
     */
    public function updateLayout(string $offerId, array $layoutConfig): array
    {
        return $this->executeWithMetrics('update_offer_layout', function () use ($offerId, $layoutConfig) {
            $this->validateLayoutConfig($layoutConfig);

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/layout", [
                'layout' => $layoutConfig
            ]);

            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.layout_updated', [
                'offer_id' => $offerId,
                'layout_type' => $layoutConfig['type'] ?? 'unknown'
            ]);

            return $offer;
        });
    }

    /**
     * Configura tema da oferta
     */
    public function updateTheme(string $offerId, array $themeConfig): array
    {
        return $this->executeWithMetrics('update_offer_theme', function () use ($offerId, $themeConfig) {
            $this->validateThemeConfig($themeConfig);

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/theme", [
                'theme' => $themeConfig
            ]);

            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.theme_updated', [
                'offer_id' => $offerId,
                'theme_name' => $themeConfig['name'] ?? 'custom'
            ]);

            return $offer;
        });
    }

    /**
     * Adiciona produto à oferta
     */
    public function addProduct(string $offerId, array $productConfig): array
    {
        return $this->executeWithMetrics('add_product_to_offer', function () use ($offerId, $productConfig) {
            $this->validateProductConfig($productConfig);

            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/products", $productConfig);
            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.product_added', [
                'offer_id' => $offerId,
                'product_id' => $productConfig['product_id'],
                'quantity' => $productConfig['quantity'] ?? 1
            ]);

            return $offer;
        });
    }

    /**
     * Remove produto da oferta
     */
    public function removeProduct(string $offerId, string $productId): array
    {
        return $this->executeWithMetrics('remove_product_from_offer', function () use ($offerId, $productId) {
            $response = $this->makeHttpRequest('DELETE', "/offers/{$offerId}/products/{$productId}");
            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.product_removed', [
                'offer_id' => $offerId,
                'product_id' => $productId
            ]);

            return $offer;
        });
    }

    /**
     * Configura bundle de produtos
     */
    public function configureBundle(string $offerId, array $bundleConfig): array
    {
        return $this->executeWithMetrics('configure_offer_bundle', function () use ($offerId, $bundleConfig) {
            $this->validateBundleConfig($bundleConfig);

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/bundle", $bundleConfig);
            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.bundle_configured', [
                'offer_id' => $offerId,
                'bundle_type' => $bundleConfig['type'] ?? 'standard',
                'discount_percentage' => $bundleConfig['discount_percentage'] ?? 0
            ]);

            return $offer;
        });
    }

    /**
     * Cria template de oferta
     */
    public function createTemplate(string $offerId, array $templateData): array
    {
        return $this->executeWithMetrics('create_offer_template', function () use ($offerId, $templateData) {
            $offer = $this->get($offerId);
            if (!$offer) {
                throw new ValidationException("Offer not found: {$offerId}");
            }

            $templateConfig = [
                'name' => $templateData['name'],
                'description' => $templateData['description'] ?? '',
                'category' => $templateData['category'] ?? 'custom',
                'layout' => $offer['layout'] ?? [],
                'theme' => $offer['theme'] ?? [],
                'configuration' => $offer['configuration'] ?? [],
                'created_from_offer_id' => $offerId
            ];

            $response = $this->makeHttpRequest('POST', '/offer-templates', $templateConfig);
            $template = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('offer.template_created', [
                'template_id' => $template['id'],
                'offer_id' => $offerId,
                'template_name' => $template['name']
            ]);

            return $template;
        });
    }

    /**
     * Aplica template à oferta
     */
    public function applyTemplate(string $offerId, string $templateId): array
    {
        return $this->executeWithMetrics('apply_template_to_offer', function () use ($offerId, $templateId) {
            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/apply-template", [
                'template_id' => $templateId
            ]);

            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.template_applied', [
                'offer_id' => $offerId,
                'template_id' => $templateId
            ]);

            return $offer;
        });
    }

    /**
     * Configura A/B testing
     */
    public function configureAbTesting(string $offerId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_offer_ab_testing', function () use ($offerId, $testConfig) {
            $this->validateAbTestConfig($testConfig);

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/ab-testing", $testConfig);
            $offer = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.ab_testing_configured', [
                'offer_id' => $offerId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $offer;
        });
    }

    /**
     * Obtém resultados de A/B testing
     */
    public function getAbTestResults(string $offerId): array
    {
        return $this->executeWithMetrics('get_offer_ab_test_results', function () use ($offerId) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/ab-testing/results");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Ativa oferta
     */
    public function activate(string $offerId): bool
    {
        return $this->updateStatus($offerId, 'active');
    }

    /**
     * Desativa oferta
     */
    public function deactivate(string $offerId): bool
    {
        return $this->updateStatus($offerId, 'inactive');
    }

    /**
     * Pausa oferta
     */
    public function pause(string $offerId): bool
    {
        return $this->updateStatus($offerId, 'paused');
    }

    /**
     * Duplica oferta
     */
    public function duplicate(string $offerId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_offer', function () use ($offerId, $overrideData) {
            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/duplicate", $overrideData);
            $offer = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('offer.duplicated', [
                'original_id' => $offerId,
                'new_id' => $offer['id']
            ]);

            return $offer;
        });
    }

    /**
     * Obtém estatísticas da oferta
     */
    public function getStats(string $offerId): array
    {
        return $this->executeWithMetrics('get_offer_stats', function () use ($offerId) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/stats");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém análise de conversão
     */
    public function getConversionAnalysis(string $offerId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_offer_conversion_analysis', function () use ($offerId, $filters) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/conversion-analysis", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_offers', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Remove oferta
     */
    public function delete(string $offerId): bool
    {
        return $this->executeWithMetrics('delete_offer', function () use ($offerId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "/offers/{$offerId}");

                // Invalidar cache
                $this->invalidateOfferCache($offerId);

                // Dispatch evento
                $this->dispatch('offer.deleted', [
                    'offer_id' => $offerId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete offer', [
                    'offer_id' => $offerId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta total de ofertas
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->makeHttpRequest('GET', '/offers/count', [
                'query' => $filters
            ]);
            $data = ResponseHelper::getData($response);
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count offers', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Busca oferta por ID via API
     */
    private function fetchOfferById(string $offerId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca oferta por slug via API
     */
    private function fetchOfferBySlug(string $slug): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/offers/slug/{$slug}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status da oferta
     */
    private function updateStatus(string $offerId, string $status): bool
    {
        return $this->executeWithMetrics("update_offer_status_{$status}", function () use ($offerId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateOfferCache($offerId);

                // Dispatch evento
                $this->dispatch('offer.status_changed', [
                    'offer_id' => $offerId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update offer status to {$status}", [
                    'offer_id' => $offerId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache da oferta
     */
    private function invalidateOfferCache(string $offerId): void
    {
        $offer = $this->get($offerId);

        $this->cache->delete($this->getCacheKey("offer:{$offerId}"));

        if ($offer && isset($offer['slug'])) {
            $this->cache->delete($this->getCacheKey("offer_slug:{$offer['slug']}"));
        }
    }

    /**
     * Valida dados da oferta
     */
    private function validateOfferData(array $data): void
    {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for offer creation");
            }
        }

        $allowedTypes = ['single_product', 'bundle', 'subscription', 'service'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid offer type: {$data['type']}");
        }
    }

    /**
     * Valida dados de atualização da oferta
     */
    private function validateOfferUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['single_product', 'bundle', 'subscription', 'service'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$data['type']}");
            }
        }
    }

    /**
     * Valida configuração de layout
     */
    private function validateLayoutConfig(array $config): void
    {
        $required = ['type'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for layout configuration");
            }
        }

        $allowedTypes = ['single_column', 'two_column', 'custom'];
        if (!in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid layout type: {$config['type']}");
        }
    }

    /**
     * Valida configuração de tema
     */
    private function validateThemeConfig(array $config): void
    {
        if (isset($config['colors']) && !is_array($config['colors'])) {
            throw new ValidationException('Theme colors must be an array');
        }

        if (isset($config['fonts']) && !is_array($config['fonts'])) {
            throw new ValidationException('Theme fonts must be an array');
        }
    }

    /**
     * Valida configuração de produto
     */
    private function validateProductConfig(array $config): void
    {
        $required = ['product_id'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for product configuration");
            }
        }

        if (isset($config['quantity']) && (!is_numeric($config['quantity']) || $config['quantity'] < 1)) {
            throw new ValidationException('Product quantity must be a positive number');
        }
    }

    /**
     * Valida configuração de bundle
     */
    private function validateBundleConfig(array $config): void
    {
        if (isset($config['discount_percentage']) && (!is_numeric($config['discount_percentage']) || $config['discount_percentage'] < 0 || $config['discount_percentage'] > 100)) {
            throw new ValidationException('Bundle discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida configuração de A/B testing
     */
    private function validateAbTestConfig(array $config): void
    {
        $required = ['name', 'variants'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for A/B test configuration");
            }
        }

        if (!is_array($config['variants']) || count($config['variants']) < 2) {
            throw new ValidationException('A/B test must have at least 2 variants');
        }
    }

    /**
     * Verifica se slug já existe
     */
    private function slugExists(string $slug): bool
    {
        try {
            $offer = $this->fetchOfferBySlug($slug);
            return $offer !== null;
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
     * Constrói configuração da oferta
     */
    private function buildOfferConfiguration(array $data): array
    {
        return [
            'checkout_config' => $data['checkout_config'] ?? [],
            'payment_methods' => $data['payment_methods'] ?? ['credit_card', 'pix'],
            'shipping_config' => $data['shipping_config'] ?? [],
            'tax_config' => $data['tax_config'] ?? [],
            'conversion_tools' => $data['conversion_tools'] ?? []
        ];
    }

    /**
     * Gera metadados da oferta
     */
    private function generateOfferMetadata(array $data): array
    {
        return [
            'created_by' => 'sdk',
            'version' => '1.0',
            'source' => 'api',
            'has_multiple_products' => isset($data['products']) && count($data['products']) > 1,
            'estimated_value' => $this->calculateEstimatedValue($data)
        ];
    }

    /**
     * Calcula valor estimado da oferta
     */
    private function calculateEstimatedValue(array $data): float
    {
        if (isset($data['products']) && is_array($data['products'])) {
            $total = 0;
            foreach ($data['products'] as $product) {
                $price = $product['price'] ?? 0;
                $quantity = $product['quantity'] ?? 1;
                $total += $price * $quantity;
            }
            return $total;
        }

        return $data['price'] ?? 0;
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
