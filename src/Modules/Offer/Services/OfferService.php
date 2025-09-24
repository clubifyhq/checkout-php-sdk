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

            // Preparar dados da oferta
            $data = array_merge($offerData, [
                'status' => $offerData['status'] ?? 'draft',
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
                'type' => $offer['type'] ?? 'single_product',
                'slug' => $offer['slug']
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

            return ResponseHelper::getData($response) ?? [];
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

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/config/theme", [
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
     * Configura layout da oferta
     * Endpoint: PUT /offers/:id/config/layout
     */
    public function updateLayout(string $offerId, array $layoutConfig): array
    {
        return $this->executeWithMetrics('update_offer_layout', function () use ($offerId, $layoutConfig) {
            $this->validateLayoutConfig($layoutConfig);

            $response = $this->makeHttpRequest('PUT', "/offers/{$offerId}/config/layout", [
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

            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/upsells", $upsellData);
            $upsell = ResponseHelper::getData($response);

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

            $response = $this->makeHttpRequest('POST', "/offers/{$offerId}/subscription/plans", $planData);
            $plan = ResponseHelper::getData($response);

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
            $allowedTypes = ['single_product', 'bundle', 'subscription', 'funnel'];
            if (!in_array($type, $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$type}");
            }

            $response = $this->makeHttpRequest('GET', '/offers', [
                'query' => ['type' => $type]
            ]);

            return ResponseHelper::getData($response) ?? [];
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
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for offer creation");
            }
        }

        if (isset($data['type'])) {
            $allowedTypes = ['single_product', 'bundle', 'subscription', 'funnel'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$data['type']}");
            }
        }
    }

    /**
     * Valida dados de atualização da oferta
     */
    private function validateOfferUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['single_product', 'bundle', 'subscription', 'funnel'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid offer type: {$data['type']}");
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

}
