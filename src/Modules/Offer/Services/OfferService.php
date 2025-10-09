<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço principal de gestão de ofertas
 *
 * Responsável pela gestão completa de ofertas e configurações:
 * - CRUD de ofertas (POST/GET/PUT/DELETE /offers)
 * - Configuração de temas (PUT /offers/:id/config/theme)
 * - Configuração de layouts (PUT /offers/:id/config/layout)
 * - Gestão de upsells (POST/GET /offers/:offerId/upsells)
 * - Planos de assinatura (GET/POST /offers/:id/subscription/plans)
 * - Ofertas públicas (GET /offers/public/:slug)
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
     * Endpoint: POST /offers
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

            // Preparar dados da oferta - remover campos que não são aceitos pela API
            $data = $offerData;

            // Remover campos internos do SDK que não devem ser enviados para a API
            unset($data['status']); // A API usa 'isActive' ao invés de 'status'
            unset($data['created_at']); // A API gera automaticamente
            unset($data['configuration']); // Não usado pela API

            // Log do payload para debug
            $this->logger->debug('Creating offer with payload', ['payload' => $data]);

            // Criar oferta via API
            $offer = $this->makeHttpRequest('POST', '/offers', ['json' => $data]);

            // Normalizar ID (API retorna _id, mas SDK usa id)
            $offerId = $offer['_id'] ?? $offer['id'] ?? null;
            if ($offerId && !isset($offer['id'])) {
                $offer['id'] = $offerId;
            }

            // Cache da oferta
            if ($offerId) {
                $this->cache->set($this->getCacheKey("offer:{$offerId}"), $offer, 3600);
                if (isset($offer['slug'])) {
                    $this->cache->set($this->getCacheKey("offer_slug:{$offer['slug']}"), $offer, 3600);
                }
            }

            // Dispatch evento
            $this->dispatch('offer.created', [
                'offer_id' => $offerId,
                'name' => $offer['name'] ?? 'unknown',
                'type' => $offer['type'] ?? 'single',
                'slug' => $offer['slug'] ?? 'unknown'
            ]);

            $this->logger->info('Offer created successfully', [
                'offer_id' => $offerId,
                'name' => $offer['name'] ?? 'unknown',
                'slug' => $offer['slug'] ?? 'unknown'
            ]);

            return $offer;
        });
    }

    /**
     * Obtém uma oferta por ID
     * Endpoint: GET /offers/:id
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
     * Endpoint: GET /offers/slug/:slug
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
     * Lista ofertas com filtros
     * Endpoint: GET /offers
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

            return $response ?? [];
        });
    }

    /**
     * Atualiza oferta
     * Endpoint: PUT /offers/:id
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

            $offer = $this->makeHttpRequest('PUT', "/offers/{$offerId}", ['json' => $data]);

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
     * Exclui oferta
     * Endpoint: DELETE /offers/:id
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
     * Configura tema da oferta
     * Endpoint: PUT /offers/:id/config/theme
     */
    public function updateTheme(string $offerId, array $themeConfig): array
    {
        return $this->executeWithMetrics('update_offer_theme', function () use ($offerId, $themeConfig) {
            $this->validateThemeConfig($themeConfig);

            $offer = $this->makeHttpRequest('PUT', "/offers/{$offerId}/config/theme", [
                'json' => ['theme' => $themeConfig]
            ]);

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
     * Configura layout da oferta
     * Endpoint: PUT /offers/:id/config/layout
     */
    public function updateLayout(string $offerId, array $layoutConfig): array
    {
        return $this->executeWithMetrics('update_offer_layout', function () use ($offerId, $layoutConfig) {
            $this->validateLayoutConfig($layoutConfig);

            $offer = $this->makeHttpRequest('PUT', "/offers/{$offerId}/config/layout", [
                'json' => ['layout' => $layoutConfig]
            ]);

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
     * Obtém upsells da oferta
     * Endpoint: GET /offers/:offerId/upsells
     */
    public function getUpsells(string $offerId): array
    {
        return $this->executeWithMetrics('get_offer_upsells', function () use ($offerId) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/upsells");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Adiciona upsell à oferta
     * Endpoint: POST /offers/:offerId/upsells
     */
    public function addUpsell(string $offerId, array $upsellData): array
    {
        return $this->executeWithMetrics('add_offer_upsell', function () use ($offerId, $upsellData) {
            $this->validateUpsellData($upsellData);

            $upsell = $this->makeHttpRequest('POST', "/offers/{$offerId}/upsells", ['json' => $upsellData]);

            // Invalidar cache da oferta
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.upsell_added', [
                'offer_id' => $offerId,
                'upsell_id' => $upsell['id'] ?? null,
                'product_id' => $upsellData['product_id'] ?? null
            ]);

            return $upsell;
        });
    }

    /**
     * Obtém planos de assinatura da oferta
     * Endpoint: GET /offers/:id/subscription/plans
     */
    public function getSubscriptionPlans(string $offerId): array
    {
        return $this->executeWithMetrics('get_offer_subscription_plans', function () use ($offerId) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/subscription/plans");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Adiciona plano de assinatura à oferta
     * Endpoint: POST /offers/:id/subscription/plans
     */
    public function addSubscriptionPlan(string $offerId, array $planData): array
    {
        return $this->executeWithMetrics('add_offer_subscription_plan', function () use ($offerId, $planData) {
            $this->validateSubscriptionPlanData($planData);

            $plan = $this->makeHttpRequest('POST', "/offers/{$offerId}/subscription/plans", ['json' => $planData]);

            // Invalidar cache da oferta
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.subscription_plan_added', [
                'offer_id' => $offerId,
                'plan_id' => $plan['id'] ?? null,
                'plan_name' => $planData['name'] ?? null
            ]);

            return $plan;
        });
    }

    /**
     * Obtém dados públicos da oferta
     * Endpoint: GET /offers/public/:slug
     */
    public function getPublicOffer(string $slug): ?array
    {
        return $this->executeWithMetrics('get_public_offer', function () use ($slug) {
            try {
                $response = $this->makeHttpRequest('GET', "/offers/public/{$slug}");
                return ResponseHelper::getData($response);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return null;
                }
                throw $e;
            }
        });
    }

    /**
     * Ativa/Publica oferta
     * Endpoint: POST /offers/:id/publish
     */
    public function activate(string $offerId): bool
    {
        return $this->publish($offerId);
    }

    /**
     * Desativa/Despublica oferta
     * Endpoint: POST /offers/:id/unpublish
     */
    public function deactivate(string $offerId): bool
    {
        return $this->unpublish($offerId);
    }

    /**
     * Pausa oferta (alias para desativar)
     */
    public function pause(string $offerId): bool
    {
        return $this->unpublish($offerId);
    }

    /**
     * Publica oferta
     * Endpoint: POST /offers/:id/publish
     */
    public function publish(string $offerId): bool
    {
        return $this->executeWithMetrics('publish_offer', function () use ($offerId) {
            try {
                $this->makeHttpRequest('POST', "/offers/{$offerId}/publish");

                // Invalidar cache
                $this->invalidateOfferCache($offerId);

                // Dispatch evento
                $this->dispatch('offer.published', [
                    'offer_id' => $offerId
                ]);

                $this->logger->info('Offer published', ['offer_id' => $offerId]);

                return true;
            } catch (\Exception $e) {
                $this->logger->error('Failed to publish offer', [
                    'offer_id' => $offerId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Despublica oferta
     * Endpoint: POST /offers/:id/unpublish
     */
    public function unpublish(string $offerId): bool
    {
        return $this->executeWithMetrics('unpublish_offer', function () use ($offerId) {
            try {
                $this->makeHttpRequest('POST', "/offers/{$offerId}/unpublish");

                // Invalidar cache
                $this->invalidateOfferCache($offerId);

                // Dispatch evento
                $this->dispatch('offer.unpublished', [
                    'offer_id' => $offerId
                ]);

                $this->logger->info('Offer unpublished', ['offer_id' => $offerId]);

                return true;
            } catch (\Exception $e) {
                $this->logger->error('Failed to unpublish offer', [
                    'offer_id' => $offerId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
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
     * Duplica oferta
     */
    public function duplicate(string $offerId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_offer', function () use ($offerId, $overrideData) {
            $offer = $this->makeHttpRequest('POST', "/offers/{$offerId}/duplicate", ['json' => $overrideData]);

            // Dispatch evento
            $this->dispatch('offer.duplicated', [
                'original_id' => $offerId,
                'new_id' => $offer['id']
            ]);

            return $offer;
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
     * Verifica disponibilidade de slug
     */
    public function verifySlugAvailability(string $slug): array
    {
        return $this->executeWithMetrics('verify_offer_slug_availability', function () use ($slug) {
            $this->logger->info('Verifying offer slug availability', ['slug' => $slug]);

            // Validate slug format
            if (!$this->isValidSlug($slug)) {
                throw new ValidationException('Invalid slug format. Use only letters, numbers, and hyphens.');
            }

            // Check if slug is already taken
            $isAvailable = !$this->slugExists($slug);

            $result = [
                'success' => true,
                'slug' => $slug,
                'available' => $isAvailable,
                'checked_at' => date('c')
            ];

            if (!$isAvailable) {
                $result['message'] = 'Slug is already in use';
                $this->logger->info('Slug not available', ['slug' => $slug]);
            } else {
                $result['message'] = 'Slug is available';
                $this->logger->info('Slug is available', ['slug' => $slug]);
            }

            return $result;
        });
    }

    /**
     * Gera sugestões de slug disponíveis
     */
    public function suggestAvailableSlugs(string $desiredSlug, int $maxSuggestions = 5): array
    {
        return $this->executeWithMetrics('suggest_offer_slugs', function () use ($desiredSlug, $maxSuggestions) {
            $this->logger->info('Generating slug suggestions', [
                'desired_slug' => $desiredSlug,
                'max_suggestions' => $maxSuggestions
            ]);

            // Clean and validate the desired slug
            $cleanSlug = $this->generateSlug($desiredSlug);

            // Check if desired slug is available
            $isAvailable = !$this->slugExists($cleanSlug);

            $suggestions = [];

            if ($isAvailable) {
                return [
                    'success' => true,
                    'desired_slug' => $cleanSlug,
                    'available' => true,
                    'suggestions' => [],
                    'message' => 'Desired slug is available'
                ];
            }

            // Generate suggestions
            $suggestionPatterns = [
                $cleanSlug . '-' . date('Y'),
                $cleanSlug . '-' . date('m-d'),
                $cleanSlug . '-v2',
                $cleanSlug . '-new',
                $cleanSlug . '-' . rand(100, 999)
            ];

            foreach ($suggestionPatterns as $pattern) {
                if (count($suggestions) >= $maxSuggestions) {
                    break;
                }

                if (!$this->slugExists($pattern)) {
                    $suggestions[] = $pattern;
                }
            }

            return [
                'success' => true,
                'desired_slug' => $cleanSlug,
                'available' => false,
                'suggestions' => $suggestions,
                'message' => 'Desired slug is not available, here are some suggestions'
            ];
        });
    }

    /**
     * Lista ofertas agrupadas por categoria
     */
    public function listByCategory(string $category = null): array
    {
        return $this->executeWithMetrics('list_offers_by_category', function () use ($category) {
            $filters = $category ? ['category' => $category] : [];

            $response = $this->makeHttpRequest('GET', '/offers/by-category', [
                'query' => $filters
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas por status
     */
    public function listByStatus(string $status): array
    {
        return $this->executeWithMetrics('list_offers_by_status', function () use ($status) {
            $allowedStatuses = ['draft', 'active', 'inactive', 'paused', 'archived'];
            if (!in_array($status, $allowedStatuses)) {
                throw new ValidationException("Invalid status: {$status}");
            }

            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => ['status' => $status]
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas populares (com mais vendas)
     */
    public function listPopular(int $limit = 10): array
    {
        return $this->executeWithMetrics('list_popular_offers', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/offers/popular', [
                'query' => ['limit' => $limit]
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas recentes
     */
    public function listRecent(int $limit = 10): array
    {
        return $this->executeWithMetrics('list_recent_offers', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/offers/recent', [
                'query' => ['limit' => $limit]
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas por tipo
     */
    public function listByType(string $type): array
    {
        return $this->executeWithMetrics('list_offers_by_type', function () use ($type) {
            // Tipos aceitos pela API: single, combo, subscription, bundle
            $allowedTypes = ['single', 'combo', 'subscription', 'bundle'];
            if (!in_array($type, $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$type}. Allowed types: " . implode(', ', $allowedTypes));
            }

            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => ['type' => $type]
            ]);

            return $response ?? [];
        });
    }

    /**
     * Busca ofertas por texto
     */
    public function search(string $query, array $filters = []): array
    {
        return $this->executeWithMetrics('search_offers', function () use ($query, $filters) {
            $searchParams = array_merge($filters, [
                'q' => $query
            ]);

            $response = $this->makeHttpRequest('GET', '/offers/search', [
                'query' => $searchParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas com filtros avançados
     */
    public function listAdvanced(array $criteria): array
    {
        return $this->executeWithMetrics('list_offers_advanced', function () use ($criteria) {
            $this->validateAdvancedCriteria($criteria);

            $response = $this->makeHttpRequest('GET', '/offers/advanced-search', [
                'query' => $criteria
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém ofertas relacionadas/similares
     */
    public function getRelated(string $offerId, int $limit = 5): array
    {
        return $this->executeWithMetrics('get_related_offers', function () use ($offerId, $limit) {
            $response = $this->makeHttpRequest('GET', "/offers/{$offerId}/related", [
                'query' => ['limit' => $limit]
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Gera URL de checkout para a oferta
     * Endpoint: POST /offers/:id/generate-url
     *
     * @param string $offerId ID da oferta
     * @param array $options Opções de geração (customDomain, slug, utmParams, expirationType, etc)
     * @return array Retorna checkoutUrl, shortUrl, qrCode e metadata
     */
    public function generateCheckoutUrl(string $offerId, array $options = []): array
    {
        return $this->executeWithMetrics('generate_offer_url', function () use ($offerId, $options) {
            $this->logger->info('Generating checkout URL for offer', [
                'offer_id' => $offerId,
                'options' => $options
            ]);

            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/generate-url", [
                'json' => $options
            ]);

            // Dispatch evento
            $this->dispatch('offer.url_generated', [
                'offer_id' => $offerId,
                'checkout_url' => $response['checkoutUrl'] ?? null,
                'short_url' => $response['shortUrl'] ?? null
            ]);

            return $response;
        });
    }

    /**
     * Obtém todas as URLs geradas para a oferta
     * Endpoint: GET /offers/:id/urls
     *
     * @param string $offerId ID da oferta
     * @return array Lista de URLs geradas com metadata
     */
    public function getOfferUrls(string $offerId): array
    {
        return $this->executeWithMetrics('get_offer_urls', function () use ($offerId) {
            return $this->makeHttpRequest('GET', "/offers/{$offerId}/urls");
        });
    }

    /**
     * Adiciona produto à oferta
     * Endpoint: POST /offers/:id/products
     *
     * @param string $offerId ID da oferta
     * @param array $productData Dados do produto a ser adicionado
     * @return array Oferta atualizada com o produto
     */
    public function addProductToOffer(string $offerId, array $productData): array
    {
        return $this->executeWithMetrics('add_product_to_offer', function () use ($offerId, $productData) {
            $this->logger->info('Adding product to offer', [
                'offer_id' => $offerId,
                'product_id' => $productData['productId'] ?? null
            ]);

            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/products", [
                'json' => $productData
            ]);

            // Invalidar cache da oferta
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.product_added', [
                'offer_id' => $offerId,
                'product_id' => $productData['productId'] ?? null
            ]);

            return $response;
        });
    }

    /**
     * Atualiza configuração de preço de um produto na oferta
     * Permite definir preço base e/ou aplicar desconto customizado
     * Endpoint: PUT /offers/:id/products/:productId
     *
     * @param string $offerId ID da oferta
     * @param string $productId ID do produto na oferta
     * @param array $priceConfig Configuração de preço com opções:
     *   - 'basePrice': Preço base do produto em centavos (atualiza productData.price)
     *   - 'discountType': Tipo de desconto ('percentage' ou 'fixed')
     *   - 'discountValue': Valor do desconto (percentual ou valor fixo em centavos)
     *   - Outros campos opcionais: quantity, position, isOptional, etc.
     * @return array Oferta atualizada
     */
    public function updateOfferProductPrice(string $offerId, string $productId, array $priceConfig): array
    {
        return $this->executeWithMetrics('update_offer_product_price', function () use ($offerId, $productId, $priceConfig) {
            $this->logger->info('Updating product price configuration in offer', [
                'offer_id' => $offerId,
                'product_id' => $productId,
                'config' => $priceConfig
            ]);

            $payload = [];

            // 1. Atualizar preço base do produto (se fornecido)
            if (isset($priceConfig['basePrice'])) {
                if (!is_numeric($priceConfig['basePrice']) || $priceConfig['basePrice'] < 0) {
                    throw new ValidationException('Base price must be a positive number');
                }

                $payload['productData'] = $payload['productData'] ?? [];
                $payload['productData']['price'] = (int) $priceConfig['basePrice'];

                // Campos adicionais de productData
                if (isset($priceConfig['currency'])) {
                    $payload['productData']['currency'] = $priceConfig['currency'];
                }
                if (isset($priceConfig['productName'])) {
                    $payload['productData']['name'] = $priceConfig['productName'];
                }
                if (isset($priceConfig['productDescription'])) {
                    $payload['productData']['description'] = $priceConfig['productDescription'];
                }
            }

            // 2. Configurar desconto customizado (se fornecido)
            if (isset($priceConfig['discountType'])) {
                $allowedDiscountTypes = ['percentage', 'fixed'];
                if (!in_array($priceConfig['discountType'], $allowedDiscountTypes)) {
                    throw new ValidationException("Invalid discount type: {$priceConfig['discountType']}. Allowed: percentage, fixed");
                }
                $payload['discountType'] = $priceConfig['discountType'];

                // discountValue é obrigatório quando há discountType
                if (!isset($priceConfig['discountValue'])) {
                    throw new ValidationException('discountValue is required when discountType is provided');
                }
                if (!is_numeric($priceConfig['discountValue']) || $priceConfig['discountValue'] < 0) {
                    throw new ValidationException('Discount value must be a positive number');
                }
                $payload['discountValue'] = $priceConfig['discountType'] === 'percentage'
                    ? (float) $priceConfig['discountValue']
                    : (int) $priceConfig['discountValue'];
            }

            // 3. Outros campos opcionais da oferta
            if (isset($priceConfig['quantity']) && is_numeric($priceConfig['quantity'])) {
                $payload['quantity'] = (int) $priceConfig['quantity'];
            }
            if (isset($priceConfig['position']) && is_numeric($priceConfig['position'])) {
                $payload['position'] = (int) $priceConfig['position'];
            }
            if (isset($priceConfig['isOptional'])) {
                $payload['isOptional'] = (bool) $priceConfig['isOptional'];
            }

            // Validar que pelo menos uma configuração foi fornecida
            if (empty($payload)) {
                throw new ValidationException('No price configuration provided. Use basePrice and/or discountType+discountValue');
            }

            // CRÍTICO: Remover campos que não devem ser enviados
            // O productId é extraído da URL, nunca deve estar no payload
            $protectedFields = ['productId', '_id', 'id'];
            foreach ($protectedFields as $field) {
                unset($payload[$field]);
            }

            // Filtrar valores null/undefined para evitar sobrescrever dados existentes
            $payload = $this->filterNullValues($payload);

            // Fazer requisição para atualizar o produto na oferta
            $offer = $this->makeHttpRequest('PUT', "/offers/{$offerId}/products/{$productId}", [
                'json' => $payload
            ]);

            // Invalidar cache da oferta
            $this->invalidateOfferCache($offerId);

            // Dispatch evento
            $this->dispatch('offer.product_price_updated', [
                'offer_id' => $offerId,
                'product_id' => $productId,
                'base_price' => $priceConfig['basePrice'] ?? null,
                'discount_type' => $priceConfig['discountType'] ?? null,
                'discount_value' => $priceConfig['discountValue'] ?? null
            ]);

            $this->logger->info('Product price configuration updated successfully in offer', [
                'offer_id' => $offerId,
                'product_id' => $productId,
                'payload' => $payload
            ]);

            return $offer;
        });
    }

    /**
     * Aplica desconto customizado a um produto na oferta
     * Atalho para updateOfferProductPrice() focado apenas em desconto
     * Endpoint: PUT /offers/:id/products/:productId
     *
     * @param string $offerId ID da oferta
     * @param string $productId ID do produto na oferta
     * @param string $discountType Tipo de desconto ('percentage' ou 'fixed')
     * @param float $discountValue Valor do desconto (percentual ou valor fixo em centavos)
     * @return array Oferta atualizada
     */
    public function applyProductDiscount(
        string $offerId,
        string $productId,
        string $discountType,
        float $discountValue
    ): array {
        return $this->updateOfferProductPrice($offerId, $productId, [
            'discountType' => $discountType,
            'discountValue' => $discountValue
        ]);
    }

    /**
     * Remove desconto de um produto na oferta
     * Endpoint: PUT /offers/:id/products/:productId
     *
     * @param string $offerId ID da oferta
     * @param string $productId ID do produto na oferta
     * @return array Oferta atualizada
     */
    public function removeProductDiscount(string $offerId, string $productId): array
    {
        return $this->executeWithMetrics('remove_offer_product_discount', function () use ($offerId, $productId) {
            $this->logger->info('Removing product discount in offer', [
                'offer_id' => $offerId,
                'product_id' => $productId
            ]);

            // Remover desconto setando valores nulos
            $offer = $this->makeHttpRequest('PUT', "/offers/{$offerId}/products/{$productId}", [
                'json' => [
                    'discountType' => null,
                    'discountValue' => null
                ]
            ]);

            $this->invalidateOfferCache($offerId);

            $this->dispatch('offer.product_discount_removed', [
                'offer_id' => $offerId,
                'product_id' => $productId
            ]);

            return $offer;
        });
    }

    /**
     * Lista ofertas por faixa de preço
     */
    public function listByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->executeWithMetrics('list_offers_by_price_range', function () use ($minPrice, $maxPrice) {
            if ($minPrice < 0 || $maxPrice < 0 || $minPrice > $maxPrice) {
                throw new ValidationException('Invalid price range');
            }

            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => [
                    'price_min' => $minPrice,
                    'price_max' => $maxPrice
                ]
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista ofertas em promoção
     */
    public function listOnPromotion(): array
    {
        return $this->executeWithMetrics('list_offers_on_promotion', function () {
            $response = $this->makeHttpRequest('GET', '/offers/promotions');
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Busca oferta por ID via API
     */
    private function fetchOfferById(string $offerId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', "/offers/{$offerId}");
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
            $response = $this->httpClient->request('GET', "/offers/slug/{$slug}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
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
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for offer creation");
            }
        }

        if (isset($data['type'])) {
            // Tipos aceitos pela API: single, combo, subscription, bundle
            $allowedTypes = ['single', 'combo', 'subscription', 'bundle'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$data['type']}. Allowed types: " . implode(', ', $allowedTypes));
            }
        }
    }

    /**
     * Valida dados de atualização da oferta
     */
    private function validateOfferUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            // Tipos aceitos pela API: single, combo, subscription, bundle
            $allowedTypes = ['single', 'combo', 'subscription', 'bundle'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$data['type']}. Allowed types: " . implode(', ', $allowedTypes));
            }
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

        $allowedTypes = ['single_column', 'two_column', 'multi_step', 'custom'];
        if (!in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid layout type: {$config['type']}");
        }
    }

    /**
     * Valida dados de upsell
     */
    private function validateUpsellData(array $data): void
    {
        $required = ['product_id', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for upsell");
            }
        }

        $allowedTypes = ['one_time_offer', 'downsell', 'cross_sell', 'subscription_upgrade'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid upsell type: {$data['type']}");
        }
    }

    /**
     * Valida dados de plano de assinatura
     */
    private function validateSubscriptionPlanData(array $data): void
    {
        $required = ['name', 'price', 'interval'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for subscription plan");
            }
        }

        $allowedIntervals = ['daily', 'weekly', 'monthly', 'yearly'];
        if (!in_array($data['interval'], $allowedIntervals)) {
            throw new ValidationException("Invalid subscription interval: {$data['interval']}");
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new ValidationException('Subscription price must be a positive number');
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
        // Converter para minúsculas
        $slug = strtolower($name);

        // Substituir caracteres especiais e espaços por hífens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remover múltiplos hífens consecutivos
        $slug = preg_replace('/-+/', '-', $slug);

        // Remover hífens no início e fim
        $slug = trim($slug, '-');

        return $slug;
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
            'design_config' => $data['design_config'] ?? [],
            'conversion_tools' => $data['conversion_tools'] ?? [],
            'analytics' => $data['analytics'] ?? ['enabled' => true]
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
            'sdk_version' => $this->getServiceVersion()
        ];
    }

    /**
     * Valida se o slug tem formato válido
     */
    private function isValidSlug(string $slug): bool
    {
        // Slug deve conter apenas letras, números e hífens, sem espaços
        return preg_match('/^[a-z0-9-]+$/', $slug) && !str_starts_with($slug, '-') && !str_ends_with($slug, '-');
    }

    /**
     * Valida critérios avançados de busca
     */
    private function validateAdvancedCriteria(array $criteria): void
    {
        $allowedFields = [
            'type', 'status', 'category', 'price_min', 'price_max',
            'created_after', 'created_before', 'has_upsells', 'has_order_bumps',
            'conversion_rate_min', 'conversion_rate_max', 'revenue_min', 'revenue_max'
        ];

        foreach ($criteria as $field => $value) {
            if (!in_array($field, $allowedFields)) {
                throw new ValidationException("Invalid search field: {$field}");
            }

            // Validate specific field types
            if (in_array($field, ['price_min', 'price_max', 'revenue_min', 'revenue_max']) && !is_numeric($value)) {
                throw new ValidationException("Field '{$field}' must be numeric");
            }

            if (in_array($field, ['conversion_rate_min', 'conversion_rate_max']) && (!is_numeric($value) || $value < 0 || $value > 100)) {
                throw new ValidationException("Field '{$field}' must be between 0 and 100");
            }

            if (in_array($field, ['has_upsells', 'has_order_bumps']) && !is_bool($value)) {
                throw new ValidationException("Field '{$field}' must be boolean");
            }
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

    /**
     * Filtra recursivamente valores null de um array
     * Previne sobrescrever dados existentes no banco com valores nulos
     *
     * @param array $data Array a ser filtrado
     * @return array Array sem valores null
     */
    private function filterNullValues(array $data): array
    {
        $filtered = [];

        foreach ($data as $key => $value) {
            // Se for array, filtrar recursivamente
            if (is_array($value)) {
                $filteredValue = $this->filterNullValues($value);
                // Só adicionar se o array não ficou vazio
                if (!empty($filteredValue)) {
                    $filtered[$key] = $filteredValue;
                }
            }
            // Adicionar apenas se não for null
            elseif ($value !== null) {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

}
