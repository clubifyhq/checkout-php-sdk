<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Core\Cache\CacheStrategies;
use Clubify\Checkout\Core\Performance\PerformanceOptimizer;

/**
 * Enhanced Public Offer Service with Performance Optimizations
 *
 * Responsável pela gestão de ofertas acessíveis publicamente com otimizações:
 * - Acesso por slug público com cache agressivo
 * - Dados otimizados para performance <200ms
 * - Cache inteligente com múltiplas estratégias
 * - SEO e metadados com preloading
 * - Analytics de visualização batch
 * - Dados sanitizados para segurança
 * - Lazy loading para recursos opcionais
 * - Compression para dados grandes
 * - Memory optimization
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas acesso público
 * - O: Open/Closed - Extensível via tipos de oferta
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos para público
 * - D: Dependency Inversion - Depende de abstrações
 */
class PublicOfferService extends BaseService implements ServiceInterface
{
    // Performance optimized cache durations based on CacheStrategies
    private const CACHE_TTL_OFFER = CacheStrategies::PUBLIC_OFFER_CACHE; // 2 hours
    private const CACHE_TTL_THEME = CacheStrategies::PUBLIC_OFFER_THEME_CACHE; // 4 hours
    private const CACHE_TTL_LAYOUT = CacheStrategies::PUBLIC_OFFER_LAYOUT_CACHE; // 4 hours
    private const CACHE_TTL_SEO = CacheStrategies::SEO_DATA_CACHE; // 2 hours
    private const CACHE_TTL_ANALYTICS = CacheStrategies::ANALYTICS_CACHE; // 10 minutes

    private PerformanceOptimizer $performanceOptimizer;
    private array $preloadedData = [];
    private bool $batchAnalytics = false;
    private array $analyticsQueue = [];

    public function __construct(
        BaseService $baseService,
        PerformanceOptimizer $performanceOptimizer = null
    ) {
        parent::__construct(
            $baseService->config,
            $baseService->logger,
            $baseService->httpClient,
            $baseService->cache,
            $baseService->eventDispatcher
        );

        $this->performanceOptimizer = $performanceOptimizer ?? new PerformanceOptimizer($this->cache, $this->logger);
    }
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'public_offer';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Obtém oferta pública por slug com cache e performance otimizados
     * Endpoint: GET /offers/public/:slug
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->performanceOptimizer->monitor("get_public_offer:{$slug}", function() use ($slug) {
            $this->performanceOptimizer->takeMemorySnapshot("offer_fetch_start_{$slug}");

            $offer = $this->performanceOptimizer->lazyLoad(
                "public_offer:{$slug}",
                fn() => $this->fetchPublicOfferBySlug($slug),
                ['ttl' => self::CACHE_TTL_OFFER]
            );

            if ($offer) {
                // Preload related data for better user experience
                $this->preloadRelatedData($slug);
            }

            $this->performanceOptimizer->takeMemorySnapshot("offer_fetch_end_{$slug}");
            return $offer;
        });
    }

    /**
     * Obtém dados de SEO da oferta com cache otimizado
     */
    public function getSeoData(string $slug): ?array
    {
        return $this->performanceOptimizer->lazyLoad(
            "public_offer_seo:{$slug}",
            fn() => $this->fetchSeoData($slug),
            ['ttl' => self::CACHE_TTL_SEO]
        );
    }

    /**
     * Busca dados SEO via API
     */
    private function fetchSeoData(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/offers/public/{$slug}/seo");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Obtém configuração de checkout público
     */
    public function getCheckoutConfig(string $slug): ?array
    {
        return $this->executeWithMetrics('get_public_checkout_config', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/checkout-config");
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
     * Obtém tema público da oferta com cache otimizado
     */
    public function getTheme(string $slug): ?array
    {
        return $this->performanceOptimizer->lazyLoad(
            "public_offer_theme:{$slug}",
            fn() => $this->fetchPublicOfferTheme($slug),
            ['ttl' => self::CACHE_TTL_THEME]
        );
    }

    /**
     * Obtém layout público da oferta com cache otimizado
     */
    public function getLayout(string $slug): ?array
    {
        return $this->performanceOptimizer->lazyLoad(
            "public_offer_layout:{$slug}",
            fn() => $this->fetchPublicOfferLayout($slug),
            ['ttl' => self::CACHE_TTL_LAYOUT]
        );
    }

    /**
     * Obtém upsells públicos da oferta
     */
    public function getUpsells(string $slug): array
    {
        return $this->executeWithMetrics('get_public_offer_upsells', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/upsells");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém planos de assinatura públicos
     */
    public function getSubscriptionPlans(string $slug): array
    {
        return $this->executeWithMetrics('get_public_subscription_plans', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/subscription-plans");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Registra visualização da oferta com batching otimizado
     */
    public function trackView(string $slug, array $analytics = []): bool
    {
        if ($this->batchAnalytics) {
            return $this->addToAnalyticsQueue('view', $slug, $analytics);
        }

        return $this->performanceOptimizer->monitor('track_offer_view', function() use ($slug, $analytics) {
            try {
                $data = array_merge($analytics, [
                    'timestamp' => date('Y-m-d H:i:s'),
                    'source' => 'sdk'
                ]);

                $response = $this->httpClient->post("/offers/public/{$slug}/track-view", $data);

                // Dispatch evento
                $this->dispatch('public_offer.view_tracked', [
                    'slug' => $slug,
                    'analytics' => $analytics
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error('Failed to track offer view', [
                    'slug' => $slug,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Registra interação com a oferta com batching otimizado
     */
    public function trackInteraction(string $slug, string $action, array $data = []): bool
    {
        if ($this->batchAnalytics) {
            return $this->addToAnalyticsQueue('interaction', $slug, array_merge($data, ['action' => $action]));
        }

        return $this->performanceOptimizer->monitor('track_offer_interaction', function() use ($slug, $action, $data) {
            try {
                $payload = array_merge($data, [
                    'action' => $action,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'source' => 'sdk'
                ]);

                $response = $this->httpClient->post("/offers/public/{$slug}/track-interaction", $payload);

                // Dispatch evento
                $this->dispatch('public_offer.interaction_tracked', [
                    'slug' => $slug,
                    'action' => $action,
                    'data' => $data
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error('Failed to track offer interaction', [
                    'slug' => $slug,
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Verifica disponibilidade da oferta
     */
    public function checkAvailability(string $slug): array
    {
        return $this->executeWithMetrics('check_offer_availability', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/availability");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return ['available' => false, 'reason' => 'not_found'];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém estatísticas públicas da oferta
     */
    public function getPublicStats(string $slug): array
    {
        return $this->executeWithMetrics('get_public_offer_stats', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/stats");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém recomendações relacionadas
     */
    public function getRecommendations(string $slug, int $limit = 5): array
    {
        return $this->executeWithMetrics('get_offer_recommendations', function () use ($slug, $limit) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/recommendations", [
                    'query' => ['limit' => $limit]
                ]);
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém testimonials da oferta
     */
    public function getTestimonials(string $slug): array
    {
        return $this->executeWithMetrics('get_offer_testimonials', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/testimonials");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém FAQ da oferta
     */
    public function getFaq(string $slug): array
    {
        return $this->executeWithMetrics('get_offer_faq', function () use ($slug) {
            try {
                $response = $this->httpClient->get("/offers/public/{$slug}/faq");
                return ResponseHelper::getData($response) ?? [];
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return [];
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém dados completos da oferta (otimizado)
     */
    public function getComplete(string $slug, array $includes = []): ?array
    {
        return $this->executeWithMetrics('get_complete_public_offer', function () use ($slug, $includes) {
            try {
                $query = [];
                if (!empty($includes)) {
                    $query['include'] = implode(',', $includes);
                }

                $response = $this->httpClient->get("/offers/public/{$slug}/complete", [
                    'query' => $query
                ]);

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
     * Pré-carrega dados da oferta no cache com otimizações
     */
    public function preload(string $slug): bool
    {
        return $this->performanceOptimizer->monitor('preload_public_offer', function() use ($slug) {
            try {
                $this->performanceOptimizer->takeMemorySnapshot("preload_start_{$slug}");

                // Carregar dados principais
                $offer = $this->getBySlug($slug);
                if (!$offer) {
                    return false;
                }

                // Preload complete data set
                $this->preloadCompleteOfferData($slug);

                $this->performanceOptimizer->takeMemorySnapshot("preload_end_{$slug}");

                $this->logger->info('Public offer preloaded successfully', [
                    'slug' => $slug
                ]);

                return true;
            } catch (\Exception $e) {
                $this->logger->error('Failed to preload public offer', [
                    'slug' => $slug,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    // ===============================
    // PERFORMANCE OPTIMIZATION METHODS
    // ===============================

    /**
     * Preload complete offer data set
     */
    private function preloadCompleteOfferData(string $slug): void
    {
        $predictions = [
            "public_offer_theme:{$slug}" => [
                'probability' => 0.9,
                'loader' => fn() => $this->fetchPublicOfferTheme($slug),
                'options' => ['ttl' => self::CACHE_TTL_THEME]
            ],
            "public_offer_layout:{$slug}" => [
                'probability' => 0.9,
                'loader' => fn() => $this->fetchPublicOfferLayout($slug),
                'options' => ['ttl' => self::CACHE_TTL_LAYOUT]
            ],
            "public_offer_seo:{$slug}" => [
                'probability' => 0.8,
                'loader' => fn() => $this->fetchSeoData($slug),
                'options' => ['ttl' => self::CACHE_TTL_SEO]
            ]
        ];

        $this->performanceOptimizer->preload($predictions);
    }

    /**
     * Preload related data for user experience optimization
     */
    private function preloadRelatedData(string $slug): void
    {
        // Store in preloaded data to avoid duplicate loading
        if (isset($this->preloadedData[$slug])) {
            return;
        }

        $this->preloadedData[$slug] = true;

        // Background preload (non-blocking)
        $this->preloadCompleteOfferData($slug);
    }

    /**
     * Enable batch analytics processing
     */
    public function enableBatchAnalytics(): void
    {
        $this->batchAnalytics = true;
        $this->analyticsQueue = [];
    }

    /**
     * Add analytics event to batch queue
     */
    private function addToAnalyticsQueue(string $type, string $slug, array $data): bool
    {
        $this->analyticsQueue[] = [
            'type' => $type,
            'slug' => $slug,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];

        return true;
    }

    /**
     * Process analytics batch queue
     */
    public function processAnalyticsBatch(): array
    {
        if (empty($this->analyticsQueue)) {
            return ['processed' => 0, 'errors' => 0];
        }

        return $this->performanceOptimizer->monitor('process_analytics_batch', function() {
            $processed = 0;
            $errors = 0;

            // Group by slug for batch processing
            $groupedAnalytics = [];
            foreach ($this->analyticsQueue as $event) {
                $slug = $event['slug'];
                if (!isset($groupedAnalytics[$slug])) {
                    $groupedAnalytics[$slug] = [];
                }
                $groupedAnalytics[$slug][] = $event;
            }

            // Process each slug's analytics in batch
            foreach ($groupedAnalytics as $slug => $events) {
                try {
                    $batchData = [
                        'slug' => $slug,
                        'events' => $events,
                        'source' => 'sdk_batch'
                    ];

                    $response = $this->httpClient->post("/offers/public/{$slug}/track-batch", $batchData);

                    if ($response->getStatusCode() === 200) {
                        $processed += count($events);
                    } else {
                        $errors += count($events);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Failed to process analytics batch', [
                        'slug' => $slug,
                        'events_count' => count($events),
                        'error' => $e->getMessage()
                    ]);
                    $errors += count($events);
                }
            }

            // Clear queue after processing
            $this->analyticsQueue = [];
            $this->batchAnalytics = false;

            return ['processed' => $processed, 'errors' => $errors];
        });
    }

    /**
     * Bulk preload multiple offers
     */
    public function bulkPreload(array $slugs): array
    {
        return $this->performanceOptimizer->monitor('bulk_preload_offers', function() use ($slugs) {
            $results = [];

            foreach (array_chunk($slugs, 10) as $chunk) {
                foreach ($chunk as $slug) {
                    try {
                        $success = $this->preload($slug);
                        $results[$slug] = $success ? 'success' : 'failed';
                    } catch (\Exception $e) {
                        $results[$slug] = 'error: ' . $e->getMessage();
                    }
                }

                // Memory cleanup between chunks
                if (count($results) % 20 === 0) {
                    $this->performanceOptimizer->cleanupMemory();
                }
            }

            return $results;
        });
    }

    /**
     * Get complete offer data with all related information
     */
    public function getCompleteOptimized(string $slug, array $includes = []): ?array
    {
        return $this->performanceOptimizer->monitor('get_complete_optimized', function() use ($slug, $includes) {
            $this->performanceOptimizer->takeMemorySnapshot("complete_offer_start_{$slug}");

            // Load main offer data
            $offer = $this->getBySlug($slug);
            if (!$offer) {
                return null;
            }

            // Load related data based on includes or default set
            $defaultIncludes = ['theme', 'layout', 'seo'];
            $requestedIncludes = !empty($includes) ? $includes : $defaultIncludes;

            $completeData = ['offer' => $offer];

            foreach ($requestedIncludes as $include) {
                switch ($include) {
                    case 'theme':
                        $completeData['theme'] = $this->getTheme($slug);
                        break;
                    case 'layout':
                        $completeData['layout'] = $this->getLayout($slug);
                        break;
                    case 'seo':
                        $completeData['seo'] = $this->getSeoData($slug);
                        break;
                    case 'upsells':
                        $completeData['upsells'] = $this->getUpsells($slug);
                        break;
                    case 'recommendations':
                        $completeData['recommendations'] = $this->getRecommendations($slug);
                        break;
                }
            }

            $this->performanceOptimizer->takeMemorySnapshot("complete_offer_end_{$slug}");

            return $completeData;
        });
    }

    /**
     * Warm up cache for popular offers
     */
    public function warmUpPopularOffers(array $slugs = []): array
    {
        if (empty($slugs)) {
            $slugs = $this->getPopularOfferSlugs();
        }

        $warmers = [];
        foreach ($slugs as $slug) {
            $warmers["public_offer:{$slug}"] = [
                'callback' => fn() => $this->fetchPublicOfferBySlug($slug),
                'ttl' => self::CACHE_TTL_OFFER
            ];
            $warmers["public_offer_theme:{$slug}"] = [
                'callback' => fn() => $this->fetchPublicOfferTheme($slug),
                'ttl' => self::CACHE_TTL_THEME
            ];
            $warmers["public_offer_layout:{$slug}"] = [
                'callback' => fn() => $this->fetchPublicOfferLayout($slug),
                'ttl' => self::CACHE_TTL_LAYOUT
            ];
        }

        return $this->cache->warm($warmers);
    }

    /**
     * Get popular offer slugs for warming
     */
    private function getPopularOfferSlugs(int $limit = 20): array
    {
        return $this->performanceOptimizer->lazyLoad(
            'popular_offer_slugs',
            function() use ($limit) {
                try {
                    $response = $this->httpClient->get("/offers/public/popular", [
                        'query' => ['limit' => $limit]
                    ]);
                    return array_column(ResponseHelper::getData($response) ?? [], 'slug');
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to get popular offer slugs', [
                        'error' => $e->getMessage()
                    ]);
                    return [];
                }
            },
            ['ttl' => 1800] // 30 minutes
        );
    }

    /**
     * Clear cache for specific offer
     */
    public function clearOfferCache(string $slug): int
    {
        $patterns = [
            "public_offer:{$slug}",
            "public_offer_theme:{$slug}",
            "public_offer_layout:{$slug}",
            "public_offer_seo:{$slug}"
        ];

        $cleared = 0;
        foreach ($patterns as $pattern) {
            if ($this->cache->has($pattern)) {
                $this->cache->delete($pattern);
                $cleared++;
            }
        }

        return $cleared;
    }

    /**
     * Get performance report for public offers
     */
    public function getPerformanceReport(): array
    {
        $baseReport = $this->performanceOptimizer->getPerformanceReport();

        return array_merge($baseReport, [
            'public_offer_metrics' => [
                'cache_strategies' => [
                    'offer_ttl' => self::CACHE_TTL_OFFER,
                    'theme_ttl' => self::CACHE_TTL_THEME,
                    'layout_ttl' => self::CACHE_TTL_LAYOUT,
                    'seo_ttl' => self::CACHE_TTL_SEO,
                    'analytics_ttl' => self::CACHE_TTL_ANALYTICS
                ],
                'preloaded_offers' => count($this->preloadedData),
                'analytics_queue_size' => count($this->analyticsQueue),
                'batch_analytics_enabled' => $this->batchAnalytics
            ]
        ]);
    }

    /**
     * Busca oferta pública por slug via API
     */
    private function fetchPublicOfferBySlug(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/offers/public/{$slug}");
            $data = ResponseHelper::getData($response);

            // Dispatch evento de visualização
            $this->dispatch('public_offer.fetched', [
                'slug' => $slug,
                'offer_id' => $data['id'] ?? null
            ]);

            return $data;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca tema público da oferta
     */
    private function fetchPublicOfferTheme(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/offers/public/{$slug}/theme");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca layout público da oferta
     */
    private function fetchPublicOfferLayout(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/offers/public/{$slug}/layout");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }
}