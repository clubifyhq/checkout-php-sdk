<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Modules\Checkout\Contracts\CartRepositoryInterface;
use ClubifyCheckout\Modules\Checkout\DTOs\CartData;
use ClubifyCheckout\Modules\Checkout\DTOs\ItemData;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de Carrinho
 *
 * Gerencia operações de carrinho incluindo itens, cupons,
 * cálculos de preços, frete e conversão.
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
    private const CACHE_TTL = 1800; // 30 minutos
    private const MAX_ITEMS = 50;
    private const ABANDONED_HOURS = 24;

    public function __construct(
        private CartRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
    }

    /**
     * Cria novo carrinho
     */
    public function create(string $sessionId, array $data = []): array
    {
        return $this->executeWithMetrics('cart_create', function () use ($sessionId, $data) {
            $cartData = CartData::forCreation($sessionId, $data);
            $cartData->validate();

            $cart = $this->repository->create($cartData->toArray());

            // Cache do carrinho
            $this->cacheCart($cart['id'], $cart);

            $this->logger->info('Carrinho criado', [
                'cart_id' => $cart['id'],
                'session_id' => $sessionId
            ]);

            return $cart;
        });
    }

    /**
     * Busca carrinho por ID
     */
    public function find(string $id): ?array
    {
        return $this->getCachedOrExecute("cart_{$id}", function () use ($id) {
            return $this->repository->find($id);
        }, self::CACHE_TTL);
    }

    /**
     * Busca carrinho por sessão
     */
    public function findBySession(string $sessionId): ?array
    {
        return $this->getCachedOrExecute("cart_session_{$sessionId}", function () use ($sessionId) {
            return $this->repository->findBySession($sessionId);
        }, self::CACHE_TTL);
    }

    /**
     * Busca carrinho por cliente
     */
    public function findByCustomer(string $customerId): ?array
    {
        return $this->repository->findByCustomer($customerId);
    }

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

            // Verifica se item já existe no carrinho
            $existingItems = $this->repository->getItems($cartId);
            foreach ($existingItems as $existingItem) {
                if ($existingItem['product_id'] === $item->product_id) {
                    // Se existe, atualiza quantidade
                    $newQuantity = $existingItem['quantity'] + $item->quantity;
                    return $this->updateItem($cartId, $existingItem['id'], $newQuantity);
                }
            }

            // Adiciona novo item
            $cart = $this->repository->addItem($cartId, $item->toArray());

            // Recalcula totais
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item adicionado ao carrinho', [
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

            // Recalcula totais
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item removido do carrinho', [
                'cart_id' => $cartId,
                'item_id' => $itemId
            ]);

            return $cart;
        });
    }

    /**
     * Atualiza item do carrinho
     */
    public function updateItem(string $cartId, string $itemId, int $quantity): array
    {
        return $this->executeWithMetrics('cart_update_item', function () use ($cartId, $itemId, $quantity) {
            if ($quantity <= 0) {
                return $this->removeItem($cartId, $itemId);
            }

            $cart = $this->repository->updateItem($cartId, $itemId, ['quantity' => $quantity]);

            // Recalcula totais
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Item do carrinho atualizado', [
                'cart_id' => $cartId,
                'item_id' => $itemId,
                'new_quantity' => $quantity
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

            // Recalcula totais
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Carrinho limpo', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    /**
     * Obtém itens do carrinho
     */
    public function getItems(string $cartId): array
    {
        return $this->getCachedOrExecute("cart_items_{$cartId}", function () use ($cartId) {
            return $this->repository->getItems($cartId);
        }, self::CACHE_TTL);
    }

    /**
     * Conta itens do carrinho
     */
    public function countItems(string $cartId): int
    {
        return $this->repository->countItems($cartId);
    }

    /**
     * Aplica cupom de desconto
     */
    public function applyCoupon(string $cartId, string $couponCode): array
    {
        return $this->executeWithMetrics('cart_apply_coupon', function () use ($cartId, $couponCode) {
            // Valida cupom (aqui seria integração com serviço de cupons)
            $couponData = $this->validateCoupon($couponCode, $cartId);

            if (!$couponData['valid']) {
                throw new \InvalidArgumentException($couponData['error'] ?? 'Cupom inválido');
            }

            $cart = $this->repository->applyCoupon($cartId, $couponCode);

            // Recalcula totais com desconto
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Cupom aplicado ao carrinho', [
                'cart_id' => $cartId,
                'coupon_code' => $couponCode,
                'discount_amount' => $couponData['discount_amount'] ?? 0
            ]);

            return $cart;
        });
    }

    /**
     * Remove cupom de desconto
     */
    public function removeCoupon(string $cartId): array
    {
        return $this->executeWithMetrics('cart_remove_coupon', function () use ($cartId) {
            $cart = $this->repository->removeCoupon($cartId);

            // Recalcula totais sem desconto
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Cupom removido do carrinho', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

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

            // Calcula subtotal
            $subtotal = $this->repository->calculateSubtotal($cartId);

            // Calcula desconto
            $discount = $this->repository->calculateDiscount($cartId);

            // Calcula taxas
            $taxes = $this->repository->calculateTaxes($cartId);

            // Calcula frete
            $shipping = $this->repository->calculateShipping($cartId, $cart['shipping_data'] ?? []);

            // Calcula total
            $total = $subtotal - $discount + $taxes + $shipping;

            // Atualiza totais no carrinho
            $totals = [
                'subtotal' => $subtotal,
                'discount' => $discount,
                'taxes' => $taxes,
                'shipping' => $shipping,
                'total' => $total
            ];

            $cart = $this->repository->update($cartId, ['totals' => $totals]);

            $this->logger->debug('Totais do carrinho calculados', [
                'cart_id' => $cartId,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'taxes' => $taxes,
                'shipping' => $shipping,
                'total' => $total
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

    /**
     * Atualiza dados de frete
     */
    public function updateShipping(string $cartId, array $shippingData): array
    {
        return $this->executeWithMetrics('cart_update_shipping', function () use ($cartId, $shippingData) {
            $cart = $this->repository->updateShipping($cartId, $shippingData);

            // Recalcula totais com novo frete
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Dados de frete atualizados', [
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

            // Recalcula totais (podem afetar taxas)
            $cart = $this->calculateTotals($cartId);

            // Atualiza cache
            $this->cacheCart($cartId, $cart);

            $this->logger->info('Dados de cobrança atualizados', [
                'cart_id' => $cartId
            ]);

            return $cart;
        });
    }

    /**
     * Marca carrinho como abandonado
     */
    public function markAsAbandoned(string $cartId): array
    {
        return $this->executeWithMetrics('cart_abandon', function () use ($cartId) {
            $cart = $this->repository->markAsAbandoned($cartId);

            // Remove do cache
            $this->clearCacheByPattern("cart_{$cartId}*");

            $this->logger->info('Carrinho marcado como abandonado', [
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

            $this->logger->info('Carrinho convertido em pedido', [
                'cart_id' => $cartId,
                'order_id' => $cart['order_id'] ?? null
            ]);

            return $cart;
        });
    }

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

            $this->logger->info('Carrinhos abandonados limpos', [
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

            // Recalcula totais
            $newCart = $this->calculateTotals($newCart['id']);

            $this->logger->info('Carrinho duplicado', [
                'original_cart_id' => $cartId,
                'new_cart_id' => $newCart['id'],
                'new_session_id' => $newSessionId
            ]);

            return $newCart;
        });
    }

    /**
     * Valida cupom de desconto
     */
    private function validateCoupon(string $couponCode, string $cartId): array
    {
        // Implementação básica - em produção seria integração com serviço de cupons
        $cart = $this->find($cartId);
        $total = $cart['totals']['subtotal'] ?? 0;

        // Validações básicas
        if (empty($couponCode)) {
            return ['valid' => false, 'error' => 'Código do cupom é obrigatório'];
        }

        if ($total <= 0) {
            return ['valid' => false, 'error' => 'Carrinho vazio'];
        }

        // Simula validação (em produção seria consulta ao banco/API)
        $validCoupons = [
            'DESCONTO10' => ['type' => 'percentage', 'value' => 10, 'min_amount' => 0],
            'FRETE_GRATIS' => ['type' => 'shipping', 'value' => 0, 'min_amount' => 50],
            'SAVE20' => ['type' => 'fixed', 'value' => 20, 'min_amount' => 100]
        ];

        if (!isset($validCoupons[$couponCode])) {
            return ['valid' => false, 'error' => 'Cupom não encontrado'];
        }

        $coupon = $validCoupons[$couponCode];

        if ($total < $coupon['min_amount']) {
            return [
                'valid' => false,
                'error' => "Valor mínimo de R$ {$coupon['min_amount']} não atingido"
            ];
        }

        // Calcula desconto
        $discountAmount = 0;
        switch ($coupon['type']) {
            case 'percentage':
                $discountAmount = $total * ($coupon['value'] / 100);
                break;
            case 'fixed':
                $discountAmount = min($coupon['value'], $total);
                break;
            case 'shipping':
                $discountAmount = 0; // Seria calculado baseado no frete
                break;
        }

        return [
            'valid' => true,
            'coupon' => $coupon,
            'discount_amount' => $discountAmount
        ];
    }

    /**
     * Cache do carrinho
     */
    private function cacheCart(string $cartId, array $cart): void
    {
        $this->setCacheItem("cart_{$cartId}", $cart, self::CACHE_TTL);

        // Cache também por sessão se existir
        if (!empty($cart['session_id'])) {
            $this->setCacheItem("cart_session_{$cart['session_id']}", $cart, self::CACHE_TTL);
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge(parent::getMetrics(), [
            'max_items' => self::MAX_ITEMS,
            'abandoned_hours' => self::ABANDONED_HOURS,
            'cache_ttl' => self::CACHE_TTL
        ]);
    }
}