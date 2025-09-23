<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Modules\Cart\DTOs\CartData;
use Clubify\Checkout\Modules\Cart\DTOs\ItemData;
use Clubify\Checkout\Core\Cache\CacheStrategies;
use Clubify\Checkout\Core\Performance\PerformanceOptimizer;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de Carrinho Aprimorado
 *
 * Extensão do serviço de carrinho original com integração
 * completa ao Clubify Cart Service através do ApiCartRepository.
 *
 * Funcionalidades:
 * - CRUD completo de carrinho
 * - Gerenciamento de itens
 * - Cálculos automáticos
 * - Cache inteligente
 * - Integração com API
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de carrinho
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseService
 * - I: Interface Segregation - Usa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class CartService extends BaseService
{
    private const MAX_ITEMS = 50;
    private const ABANDONED_HOURS = 24;
    private const BATCH_SIZE = 10;

    // Performance optimized cache durations
    private const CACHE_TTL_SESSION = CacheStrategies::CART_SESSION_CACHE; // 30 minutes
    private const CACHE_TTL_ITEMS = CacheStrategies::CART_ITEMS_CACHE; // 30 minutes
    private const CACHE_TTL_TOTALS = CacheStrategies::CART_TOTALS_CACHE; // 5 minutes
    private const CACHE_TTL_CALCULATIONS = CacheStrategies::CART_CALCULATIONS_CACHE; // 10 minutes

    private PerformanceOptimizer $performanceOptimizer;
    private array $batchOperations = [];
    private bool $batchMode = false;

    public function __construct(
        private CartRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        PerformanceOptimizer $performanceOptimizer = null,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
        $this->performanceOptimizer = $performanceOptimizer ?? new PerformanceOptimizer($cache, $logger);
    }

    // ===========================================
    // OPERAÇÕES BÁSICAS DE CARRINHO
    // ===========================================

    /**
     * Cria novo carrinho com otimizações de performance
     */
    public function create(string $sessionId, array $data = []): array
    {
        return $this->performanceOptimizer->monitor('cart_create', function () use ($sessionId, $data) {
            $this->performanceOptimizer->takeMemorySnapshot('cart_create_start');

            $cartData = CartData::forCreation($sessionId, $data);
            $cartData->validate();

            $cart = $this->repository->create($cartData->toArray());

            // Cache otimizado do carrinho
            $this->cacheCartOptimized($cart['id'], $cart);

            // Preload common cart operations
            $this->preloadCartData($cart['id']);

            $this->performanceOptimizer->takeMemorySnapshot('cart_create_end');

            $this->logger->info('Carrinho criado via API', [
                'cart_id' => $cart['id'],
                'session_id' => $sessionId
            ]);

            return $cart;
        });
    }

    /**
     * Busca carrinho por ID com cache otimizado
     */
    public function find(string $id): ?array
    {
        return $this->performanceOptimizer->lazyLoad(
            "cart_{$id}",
            fn() => $this->repository->find($id),
            ['ttl' => self::CACHE_TTL_SESSION]
        );
    }

    /**
     * Busca carrinho por sessão com cache otimizado
     */
    public function findBySession(string $sessionId): ?array
    {
        return $this->performanceOptimizer->lazyLoad(
            "cart_session_{$sessionId}",
            fn() => $this->repository->findBySession($sessionId),
            ['ttl' => self::CACHE_TTL_SESSION]
        );
    }

    /**
     * Busca carrinho por cliente
     */
    public function findByCustomer(string $customerId): ?array
    {
        return $this->repository->findByCustomer($customerId);
    }

    /**
     * Atualiza carrinho
     */
    public function update(string $id, array $data): array
    {
        return $this->executeWithMetrics('cart_update', function () use ($id, $data) {
            $cart = $this->repository->update($id, $data);

            // Atualiza cache
            $this->cacheCart($id, $cart);

            $this->logger->info('Carrinho atualizado via API', [
                'cart_id' => $id
            ]);

            return $cart;
        });
    }

    /**
     * Remove carrinho
     */
    public function delete(string $id): bool
    {
        return $this->executeWithMetrics('cart_delete', function () use ($id) {
            $result = $this->repository->delete($id);

            if ($result) {
                // Remove do cache
                $this->clearCacheByPattern("cart_{$id}*");

                $this->logger->info('Carrinho removido via API', [
                    'cart_id' => $id
                ]);
            }

            return $result;
        });
    }

    // ===========================================
    // OPERAÇÕES DE ITENS
    // ===========================================

    /**
     * Adiciona item ao carrinho
     */
    public function addItem(string $cartId, array $itemData): array
    {
        return $this->executeWithMetrics('cart_add_item', function () use ($cartId, $itemData) {
            // Valida dados do item
            $item = new ItemData($itemData);
            $item->validate();

            // Verifica limite de itens
            $currentItemCount = $this->repository->countItems($cartId);
            if ($currentItemCount >= self::MAX_ITEMS) {
                throw new \InvalidArgumentException("Limite de {$currentItemCount} itens excedido");
            }

            // Adiciona item via API
            $cart = $this->repository->addItem($cartId, $item->toArray());

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item adicionado via API', [
                'cart_id' => $cartId,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price
            ]);

            return $cart;
        });
    }

    /**
     * Remove item do carrinho
     */
    public function removeItem(string $cartId, string $itemId): array
    {
        return $this->executeWithMetrics('cart_remove_item', function () use ($cartId, $itemId) {
            $cart = $this->repository->removeItem($cartId, $itemId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item removido via API', [
                'cart_id' => $cartId,
                'item_id' => $itemId
            ]);

            return $cart;
        });
    }

    /**
     * Atualiza item do carrinho
     */
    public function updateItem(string $cartId, string $itemId, array $updates): array
    {
        return $this->executeWithMetrics('cart_update_item', function () use ($cartId, $itemId, $updates) {
            // Se quantidade for 0 ou negativa, remove o item
            if (isset($updates['quantity']) && $updates['quantity'] <= 0) {
                return $this->removeItem($cartId, $itemId);
            }

            $cart = $this->repository->updateItem($cartId, $itemId, $updates);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item do carrinho atualizado via API', [
                'cart_id' => $cartId,
                'item_id' => $itemId,
                'updates' => $updates
            ]);

            return $cart;
        });
    }

    /**
     * Limpa carrinho (remove todos os itens)
     */
    public function clearItems(string $cartId): array
    {
        return $this->executeWithMetrics('cart_clear', function () use ($cartId) {
            $cart = $this->repository->clearItems($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Itens do carrinho limpos via API', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    /**
     * Obtém itens do carrinho com cache otimizado
     */
    public function getItems(string $cartId): array
    {
        return $this->performanceOptimizer->lazyLoad(
            "cart_items_{$cartId}",
            fn() => $this->repository->getItems($cartId),
            ['ttl' => self::CACHE_TTL_ITEMS]
        );
    }

    /**
     * Conta itens do carrinho com cache
     */
    public function countItems(string $cartId): int
    {
        return $this->performanceOptimizer->lazyLoad(
            "cart_count_{$cartId}",
            fn() => $this->repository->countItems($cartId),
            ['ttl' => self::CACHE_TTL_ITEMS]
        );
    }

    // ===========================================
    // OPERAÇÕES DE CÁLCULOS
    // ===========================================

    /**
     * Calcula totais do carrinho
     */
    public function calculateTotals(string $cartId): array
    {
        return $this->executeWithMetrics('cart_calculate_totals', function () use ($cartId) {
            $cart = $this->repository->find($cartId);
            if (!$cart) {
                throw new \InvalidArgumentException('Carrinho não encontrado');
            }

            // Obtém totais calculados pela API
            $totals = $this->repository->getTotalsSummary($cartId);

            // Atualiza carrinho com totais
            $cart = $this->repository->update($cartId, ['totals' => $totals]);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->debug('Totais do carrinho calculados via API', [
                'cart_id' => $cartId,
                'totals' => $totals
            ]);

            return $cart;
        });
    }

    /**
     * Obtém resumo de totais
     */
    public function getTotalsSummary(string $cartId): array
    {
        return $this->getCachedOrExecute("cart_totals_{$cartId}", function () use ($cartId) {
            return $this->repository->getTotalsSummary($cartId);
        }, 300); // Cache por 5 minutos
    }

    // ===========================================
    // OPERAÇÕES DE CUPONS E PROMOÇÕES
    // ===========================================

    /**
     * Aplica cupom de desconto (legacy - redirecionado para promoções)
     */
    public function applyCoupon(string $cartId, string $couponCode): array
    {
        return $this->applyPromotion($cartId, $couponCode);
    }

    /**
     * Remove cupom de desconto (legacy - redirecionado para promoções)
     */
    public function removeCoupon(string $cartId): array
    {
        return $this->removePromotion($cartId);
    }

    /**
     * Aplica promoção ao carrinho
     */
    public function applyPromotion(string $cartId, string $promotionCode): array
    {
        return $this->executeWithMetrics('cart_apply_promotion', function () use ($cartId, $promotionCode) {
            // Valida promoção
            $validationResult = $this->repository->validatePromotion($promotionCode, $cartId);

            if (!($validationResult['valid'] ?? false)) {
                throw new \InvalidArgumentException(
                    $validationResult['error'] ?? 'Promoção inválida'
                );
            }

            // Aplica promoção
            $cart = $this->repository->applyPromotion($cartId, $promotionCode);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Promoção aplicada via API', [
                'cart_id' => $cartId,
                'promotion_code' => $promotionCode
            ]);

            return $cart;
        });
    }

    /**
     * Remove promoção do carrinho
     */
    public function removePromotion(string $cartId): array
    {
        return $this->executeWithMetrics('cart_remove_promotion', function () use ($cartId) {
            $cart = $this->repository->removePromotion($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Promoção removida via API', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    // ===========================================
    // OPERAÇÕES DE DADOS ADICIONAIS
    // ===========================================

    /**
     * Atualiza dados de frete
     */
    public function updateShipping(string $cartId, array $shippingData): array
    {
        return $this->executeWithMetrics('cart_update_shipping', function () use ($cartId, $shippingData) {
            $cart = $this->repository->updateShipping($cartId, $shippingData);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Dados de frete atualizados via API', [
                'cart_id' => $cartId,
                'shipping_method' => $shippingData['method'] ?? null
            ]);

            return $cart;
        });
    }

    /**
     * Atualiza dados de cobrança
     */
    public function updateBilling(string $cartId, array $billingData): array
    {
        return $this->executeWithMetrics('cart_update_billing', function () use ($cartId, $billingData) {
            $cart = $this->repository->updateBilling($cartId, $billingData);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Dados de cobrança atualizados via API', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    // ===========================================
    // OPERAÇÕES DE ESTADO E CONVERSÃO
    // ===========================================

    /**
     * Marca carrinho como abandonado
     */
    public function markAsAbandoned(string $cartId): array
    {
        return $this->executeWithMetrics('cart_abandon', function () use ($cartId) {
            $cart = $this->repository->markAsAbandoned($cartId);

            // Remove do cache
            $this->clearCacheByPattern("cart_{$cartId}*");

            $this->logger->info('Carrinho marcado como abandonado via API', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    /**
     * Converte carrinho em pedido
     */
    public function convertToOrder(string $cartId): array
    {
        return $this->executeWithMetrics('cart_convert', function () use ($cartId) {
            $cart = $this->repository->convertToOrder($cartId);

            // Remove do cache (carrinho convertido)
            $this->clearCacheByPattern("cart_{$cartId}*");

            $this->logger->info('Carrinho convertido em pedido via API', [
                'cart_id' => $cartId,
                'order_id' => $cart['order_id'] ?? null
            ]);

            return $cart;
        });
    }

    // ===========================================
    // OPERAÇÕES DE CONSULTA E ANÁLISE
    // ===========================================

    /**
     * Busca carrinhos abandonados
     */
    public function findAbandoned(int $hoursAgo = null): array
    {
        $hoursAgo = $hoursAgo ?? self::ABANDONED_HOURS;
        return $this->repository->findAbandoned($hoursAgo);
    }

    /**
     * Limpa carrinhos abandonados
     */
    public function cleanupAbandoned(int $daysAgo = 30): int
    {
        return $this->executeWithMetrics('cart_cleanup', function () use ($daysAgo) {
            $count = $this->repository->cleanupAbandoned($daysAgo);

            $this->logger->info('Carrinhos abandonados limpos via API', [
                'cleaned_count' => $count,
                'days_ago' => $daysAgo
            ]);

            return $count;
        });
    }

    /**
     * Obtém estatísticas de carrinho
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->getCachedOrExecute('cart_statistics', function () use ($filters) {
            return $this->repository->getStatistics($filters);
        }, 300);
    }

    /**
     * Obtém produtos mais adicionados
     */
    public function getMostAddedProducts(int $limit = 10): array
    {
        return $this->getCachedOrExecute('cart_most_added', function () use ($limit) {
            return $this->repository->getMostAddedProducts($limit);
        }, 600); // Cache por 10 minutos
    }

    /**
     * Obtém valor médio do carrinho
     */
    public function getAverageCartValue(): float
    {
        return $this->getCachedOrExecute('cart_average_value', function () {
            return $this->repository->getAverageCartValue();
        }, 600);
    }

    /**
     * Obtém taxa de conversão
     */
    public function getConversionRate(): float
    {
        return $this->getCachedOrExecute('cart_conversion_rate', function () {
            return $this->repository->getConversionRate();
        }, 600);
    }

    // ===========================================
    // OPERAÇÕES AVANÇADAS
    // ===========================================

    /**
     * Duplica carrinho (para A/B testing)
     */
    public function duplicate(string $cartId, string $newSessionId): array
    {
        return $this->executeWithMetrics('cart_duplicate', function () use ($cartId, $newSessionId) {
            $originalCart = $this->find($cartId);
            if (!$originalCart) {
                throw new \InvalidArgumentException('Carrinho original não encontrado');
            }

            // Remove IDs e referências específicas
            $newCartData = $originalCart;
            unset($newCartData['id'], $newCartData['created_at'], $newCartData['updated_at']);
            $newCartData['session_id'] = $newSessionId;
            $newCartData['status'] = 'active';

            $newCart = $this->repository->create($newCartData);

            // Duplica itens
            $items = $this->getItems($cartId);
            foreach ($items as $item) {
                unset($item['id'], $item['cart_id']);
                $this->repository->addItem($newCart['id'], $item);
            }

            // Atualiza cache
            $this->cacheCart($newCart['id'], $newCart);

            $this->logger->info('Carrinho duplicado via API', [
                'original_cart_id' => $cartId,
                'new_cart_id' => $newCart['id'],
                'new_session_id' => $newSessionId
            ]);

            return $newCart;
        });
    }

    // ===========================================
    // MÉTODOS PRIVADOS
    // ===========================================

    /**
     * Cache otimizado do carrinho com múltiplas estratégias
     */
    private function cacheCartOptimized(string $cartId, array $cart): void
    {
        // Cache principal do carrinho
        $this->setCacheItem("cart_{$cartId}", $cart, self::CACHE_TTL_SESSION);

        // Cache por sessão se existir
        if (!empty($cart['session_id'])) {
            $this->setCacheItem("cart_session_{$cart['session_id']}", $cart, self::CACHE_TTL_SESSION);
        }

        // Cache por cliente se existir
        if (!empty($cart['customer_id'])) {
            $this->setCacheItem("cart_customer_{$cart['customer_id']}", $cart, self::CACHE_TTL_SESSION);
        }

        // Cache separado para contagem de itens (acesso frequente)
        if (isset($cart['items_count'])) {
            $this->setCacheItem("cart_count_{$cartId}", $cart['items_count'], self::CACHE_TTL_ITEMS);
        }

        // Cache separado para totais (recalculados frequentemente)
        if (isset($cart['totals'])) {
            $this->setCacheItem("cart_totals_{$cartId}", $cart['totals'], self::CACHE_TTL_TOTALS);
        }
    }

    /**
     * Cache do carrinho (método legado mantido para compatibilidade)
     */
    private function cacheCart(string $cartId, array $cart): void
    {
        $this->cacheCartOptimized($cartId, $cart);
    }

    /**
     * Pré-carrega dados comuns do carrinho
     */
    private function preloadCartData(string $cartId): void
    {
        // Preload em background - não bloqueia a resposta principal
        $predictions = [
            "cart_items_{$cartId}" => [
                'probability' => 0.9,
                'loader' => fn() => $this->repository->getItems($cartId),
                'options' => ['ttl' => self::CACHE_TTL_ITEMS]
            ],
            "cart_totals_{$cartId}" => [
                'probability' => 0.8,
                'loader' => fn() => $this->repository->getTotalsSummary($cartId),
                'options' => ['ttl' => self::CACHE_TTL_TOTALS]
            ]
        ];

        $this->performanceOptimizer->preload($predictions);
    }

    /**
     * Habilita modo batch para operações múltiplas
     */
    public function enableBatchMode(): void
    {
        $this->batchMode = true;
        $this->batchOperations = [];
    }

    /**
     * Executa operações em batch
     */
    public function executeBatch(): array
    {
        if (!$this->batchMode || empty($this->batchOperations)) {
            return [];
        }

        return $this->performanceOptimizer->monitor('cart_batch_execution', function() {
            $results = [];

            // Agrupa operações por tipo
            $groupedOps = [];
            foreach ($this->batchOperations as $operation) {
                $type = $operation['type'];
                if (!isset($groupedOps[$type])) {
                    $groupedOps[$type] = [];
                }
                $groupedOps[$type][] = $operation;
            }

            // Executa em lotes otimizados
            foreach ($groupedOps as $type => $operations) {
                switch ($type) {
                    case 'add_item':
                        $results[$type] = $this->executeBatchAddItems($operations);
                        break;
                    case 'update_item':
                        $results[$type] = $this->executeBatchUpdateItems($operations);
                        break;
                    case 'remove_item':
                        $results[$type] = $this->executeBatchRemoveItems($operations);
                        break;
                }
            }

            $this->batchOperations = [];
            $this->batchMode = false;

            return $results;
        });
    }

    /**
     * Adiciona operação ao batch
     */
    private function addToBatch(string $type, array $params): void
    {
        if ($this->batchMode) {
            $this->batchOperations[] = array_merge(['type' => $type], $params);
        }
    }

    /**
     * Executa batch de adição de itens
     */
    private function executeBatchAddItems(array $operations): array
    {
        $results = [];
        foreach (array_chunk($operations, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $op) {
                try {
                    $result = $this->repository->addItem($op['cart_id'], $op['item_data']);
                    $results[] = ['status' => 'success', 'cart_id' => $op['cart_id'], 'result' => $result];
                } catch (\Exception $e) {
                    $results[] = ['status' => 'error', 'cart_id' => $op['cart_id'], 'error' => $e->getMessage()];
                }
            }
        }
        return $results;
    }

    /**
     * Executa batch de atualização de itens
     */
    private function executeBatchUpdateItems(array $operations): array
    {
        $results = [];
        foreach (array_chunk($operations, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $op) {
                try {
                    $result = $this->repository->updateItem($op['cart_id'], $op['item_id'], $op['updates']);
                    $results[] = ['status' => 'success', 'cart_id' => $op['cart_id'], 'result' => $result];
                } catch (\Exception $e) {
                    $results[] = ['status' => 'error', 'cart_id' => $op['cart_id'], 'error' => $e->getMessage()];
                }
            }
        }
        return $results;
    }

    /**
     * Executa batch de remoção de itens
     */
    private function executeBatchRemoveItems(array $operations): array
    {
        $results = [];
        foreach (array_chunk($operations, self::BATCH_SIZE) as $chunk) {
            foreach ($chunk as $op) {
                try {
                    $result = $this->repository->removeItem($op['cart_id'], $op['item_id']);
                    $results[] = ['status' => 'success', 'cart_id' => $op['cart_id'], 'result' => $result];
                } catch (\Exception $e) {
                    $results[] = ['status' => 'error', 'cart_id' => $op['cart_id'], 'error' => $e->getMessage()];
                }
            }
        }
        return $results;
    }

    /**
     * Otimiza cache baseado em padrões de uso
     */
    public function optimizeCacheForUsage(array $usagePatterns): array
    {
        return $this->performanceOptimizer->optimizeCacheStrategies($usagePatterns);
    }

    /**
     * Obtém relatório de performance do carrinho
     */
    public function getPerformanceReport(): array
    {
        $baseReport = $this->performanceOptimizer->getPerformanceReport();

        return array_merge($baseReport, [
            'cart_specific_metrics' => [
                'max_items_limit' => self::MAX_ITEMS,
                'abandoned_threshold_hours' => self::ABANDONED_HOURS,
                'batch_size' => self::BATCH_SIZE,
                'cache_strategies' => [
                    'session_ttl' => self::CACHE_TTL_SESSION,
                    'items_ttl' => self::CACHE_TTL_ITEMS,
                    'totals_ttl' => self::CACHE_TTL_TOTALS,
                    'calculations_ttl' => self::CACHE_TTL_CALCULATIONS
                ],
                'repository_type' => get_class($this->repository)
            ]
        ]);
    }

    /**
     * Limpa cache relacionado a um carrinho específico
     */
    public function clearCartCache(string $cartId): int
    {
        $patterns = [
            "cart_{$cartId}",
            "cart_items_{$cartId}",
            "cart_totals_{$cartId}",
            "cart_count_{$cartId}",
            "cart_calculations_{$cartId}"
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
     * Warm up cache para carrinhos ativos
     */
    public function warmUpActiveCartsCache(array $cartIds = []): array
    {
        if (empty($cartIds)) {
            // Se não especificado, busca carrinhos ativos recentes
            $cartIds = $this->getActiveCartIds();
        }

        $warmers = [];
        foreach ($cartIds as $cartId) {
            $warmers["cart_{$cartId}"] = [
                'callback' => fn() => $this->repository->find($cartId),
                'ttl' => self::CACHE_TTL_SESSION
            ];
            $warmers["cart_items_{$cartId}"] = [
                'callback' => fn() => $this->repository->getItems($cartId),
                'ttl' => self::CACHE_TTL_ITEMS
            ];
        }

        return $this->cache->warm($warmers);
    }

    /**
     * Obtém IDs de carrinhos ativos para warming
     */
    private function getActiveCartIds(int $limit = 50): array
    {
        // Implementação simplificada - pode ser otimizada conforme necessário
        try {
            return $this->performanceOptimizer->lazyLoad(
                'active_cart_ids',
                fn() => $this->repository->getActiveCartIds($limit),
                ['ttl' => 300] // 5 minutos
            );
        } catch (\Exception $e) {
            $this->logger->warning('Failed to get active cart IDs for warming', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Obtém métricas do serviço com informações de performance
     */
    public function getMetrics(): array
    {
        $performanceMetrics = $this->performanceOptimizer->getPerformanceMetrics();

        return array_merge(parent::getMetrics(), [
            'max_items' => self::MAX_ITEMS,
            'abandoned_hours' => self::ABANDONED_HOURS,
            'batch_size' => self::BATCH_SIZE,
            'cache_strategies' => [
                'session_ttl' => self::CACHE_TTL_SESSION,
                'items_ttl' => self::CACHE_TTL_ITEMS,
                'totals_ttl' => self::CACHE_TTL_TOTALS,
                'calculations_ttl' => self::CACHE_TTL_CALCULATIONS
            ],
            'performance' => [
                'total_operations' => $performanceMetrics['total_operations'] ?? 0,
                'slow_operations' => $performanceMetrics['slow_operations'] ?? 0,
                'performance_score' => $performanceMetrics['performance_score'] ?? 100,
                'average_response_time' => $performanceMetrics['average_response_time'] ?? 0
            ],
            'repository_type' => get_class($this->repository)
        ]);
    }
}