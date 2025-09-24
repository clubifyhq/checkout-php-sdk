<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Services;

use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Modules\Cart\DTOs\ItemData;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

/**
 * Serviço de Gerenciamento de Itens de Carrinho
 *
 * Especializado em operações relacionadas aos itens do carrinho,
 * incluindo validações, transformações e lógica de negócio específica.
 *
 * Responsabilidades:
 * - Adição, remoção e atualização de itens
 * - Validações específicas de itens
 * - Cálculos por item
 * - Lógica de quantities e variações
 * - Gestão de produtos relacionados
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de itens
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class ItemService
{
    private const CACHE_TTL = 600; // 10 minutos
    private const MAX_QUANTITY_PER_ITEM = 999;
    private const MIN_QUANTITY_PER_ITEM = 1;

    public function __construct(
        private CartRepositoryInterface $repository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    // ===========================================
    // OPERAÇÕES PRINCIPAIS DE ITENS
    // ===========================================

    /**
     * Adiciona item ao carrinho com validações completas
     */
    public function addToCart(string $cartId, array $itemData): array
    {
        $this->logger->info('Adding item to cart', [
            'cart_id' => $cartId,
            'product_id' => $itemData['product_id'] ?? null
        ]);

        // Valida dados do item
        $item = new ItemData($itemData);
        $item->validate();

        // Validações específicas de negócio
        $this->validateItemForAddition($cartId, $item);

        // Verifica se item já existe no carrinho
        $existingItems = $this->repository->getItems($cartId);
        $existingItem = $this->findItemInCart($existingItems, $item);

        if ($existingItem) {
            // Se existe, atualiza quantidade
            $newQuantity = $existingItem['quantity'] + $item->quantity;
            return $this->updateInCart($cartId, $existingItem['id'], [
                'quantity' => $newQuantity
            ]);
        }

        // Adiciona novo item
        $cart = $this->repository->addItem($cartId, $item->toArray());

        // Dispara evento
        $this->eventDispatcher->emit('cart.item.added', [
            'cart_id' => $cartId,
            'item' => $item->toArray()
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('Item added to cart successfully', [
            'cart_id' => $cartId,
            'product_id' => $item->product_id,
            'quantity' => $item->quantity
        ]);

        return $cart;
    }

    /**
     * Remove item do carrinho
     */
    public function removeFromCart(string $cartId, string $itemId): array
    {
        $this->logger->info('Removing item from cart', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        // Busca item antes de remover para log
        $items = $this->repository->getItems($cartId);
        $itemToRemove = $this->findItemById($items, $itemId);

        $cart = $this->repository->removeItem($cartId, $itemId);

        // Dispara evento
        $this->eventDispatcher->emit('cart.item.removed', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'removed_item' => $itemToRemove
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('Item removed from cart successfully', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        return $cart;
    }

    /**
     * Atualiza item no carrinho
     */
    public function updateInCart(string $cartId, string $itemId, array $updates): array
    {
        $this->logger->info('Updating item in cart', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'updates' => $updates
        ]);

        // Se quantidade for 0 ou negativa, remove o item
        if (isset($updates['quantity']) && $updates['quantity'] <= 0) {
            return $this->removeFromCart($cartId, $itemId);
        }

        // Validações específicas de atualização
        $this->validateItemUpdates($cartId, $itemId, $updates);

        $cart = $this->repository->updateItem($cartId, $itemId, $updates);

        // Dispara evento
        $this->eventDispatcher->emit('cart.item.updated', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'updates' => $updates
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('Item updated in cart successfully', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        return $cart;
    }

    /**
     * Atualiza quantidade de um item específico
     */
    public function updateQuantity(string $cartId, string $itemId, int $quantity): array
    {
        $this->logger->info('Updating item quantity', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'new_quantity' => $quantity
        ]);

        return $this->updateInCart($cartId, $itemId, ['quantity' => $quantity]);
    }

    /**
     * Incrementa quantidade de um item
     */
    public function incrementQuantity(string $cartId, string $itemId, int $increment = 1): array
    {
        $this->logger->debug('Incrementing item quantity', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'increment' => $increment
        ]);

        $items = $this->repository->getItems($cartId);
        $item = $this->findItemById($items, $itemId);

        if (!$item) {
            throw new \InvalidArgumentException('Item not found in cart');
        }

        $newQuantity = $item['quantity'] + $increment;
        return $this->updateQuantity($cartId, $itemId, $newQuantity);
    }

    /**
     * Decrementa quantidade de um item
     */
    public function decrementQuantity(string $cartId, string $itemId, int $decrement = 1): array
    {
        $this->logger->debug('Decrementing item quantity', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'decrement' => $decrement
        ]);

        $items = $this->repository->getItems($cartId);
        $item = $this->findItemById($items, $itemId);

        if (!$item) {
            throw new \InvalidArgumentException('Item not found in cart');
        }

        $newQuantity = $item['quantity'] - $decrement;
        return $this->updateQuantity($cartId, $itemId, $newQuantity);
    }

    // ===========================================
    // OPERAÇÕES DE CONSULTA E ANÁLISE
    // ===========================================

    /**
     * Obtém itens do carrinho com cache
     */
    public function getItems(string $cartId): array
    {
        $cacheKey = "cart_items_{$cartId}";

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $items = $this->repository->getItems($cartId);

        $cacheItem = $this->cache->getItem($cacheKey);
        $cacheItem->set($items);
        $cacheItem->expiresAfter(self::CACHE_TTL);
        $this->cache->save($cacheItem);

        return $items;
    }

    /**
     * Busca item específico no carrinho
     */
    public function findItem(string $cartId, string $itemId): ?array
    {
        $items = $this->getItems($cartId);
        return $this->findItemById($items, $itemId);
    }

    /**
     * Verifica se produto já existe no carrinho
     */
    public function hasProduct(string $cartId, string $productId): bool
    {
        $items = $this->getItems($cartId);

        foreach ($items as $item) {
            if ($item['product_id'] === $productId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Conta total de itens únicos
     */
    public function countUniqueItems(string $cartId): int
    {
        return $this->repository->countItems($cartId);
    }

    /**
     * Conta quantidade total de produtos
     */
    public function countTotalQuantity(string $cartId): int
    {
        $items = $this->getItems($cartId);

        return array_sum(array_column($items, 'quantity'));
    }

    /**
     * Calcula peso total dos itens
     */
    public function calculateTotalWeight(string $cartId): float
    {
        $items = $this->getItems($cartId);
        $totalWeight = 0.0;

        foreach ($items as $item) {
            $weight = (float) ($item['weight'] ?? 0.0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $totalWeight += $weight * $quantity;
        }

        return $totalWeight;
    }

    /**
     * Calcula subtotal apenas dos itens
     */
    public function calculateItemsSubtotal(string $cartId): float
    {
        $items = $this->getItems($cartId);
        $subtotal = 0.0;

        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0.0);
            $quantity = (int) ($item['quantity'] ?? 1);
            $subtotal += $price * $quantity;
        }

        return $subtotal;
    }

    // ===========================================
    // OPERAÇÕES DE VALIDAÇÃO E FILTROS
    // ===========================================

    /**
     * Filtra itens que requerem envio
     */
    public function getShippableItems(string $cartId): array
    {
        $items = $this->getItems($cartId);

        return array_filter($items, function ($item) {
            return $item['requires_shipping'] ?? true;
        });
    }

    /**
     * Filtra itens digitais (não requerem envio)
     */
    public function getDigitalItems(string $cartId): array
    {
        $items = $this->getItems($cartId);

        return array_filter($items, function ($item) {
            return !($item['requires_shipping'] ?? true);
        });
    }

    /**
     * Agrupa itens por categoria
     */
    public function groupItemsByCategory(string $cartId): array
    {
        $items = $this->getItems($cartId);
        $groups = [];

        foreach ($items as $item) {
            $category = $item['category'] ?? 'uncategorized';

            if (!isset($groups[$category])) {
                $groups[$category] = [];
            }

            $groups[$category][] = $item;
        }

        return $groups;
    }

    // ===========================================
    // MÉTODOS PRIVADOS DE VALIDAÇÃO
    // ===========================================

    /**
     * Valida item para adição
     */
    private function validateItemForAddition(string $cartId, ItemData $item): void
    {
        // Valida quantidade
        if ($item->quantity < self::MIN_QUANTITY_PER_ITEM) {
            throw new \InvalidArgumentException(
                "Quantidade mínima é " . self::MIN_QUANTITY_PER_ITEM
            );
        }

        if ($item->quantity > self::MAX_QUANTITY_PER_ITEM) {
            throw new \InvalidArgumentException(
                "Quantidade máxima é " . self::MAX_QUANTITY_PER_ITEM
            );
        }

        // Valida preço
        if ($item->price < 0) {
            throw new \InvalidArgumentException('Preço não pode ser negativo');
        }

        // Validações adicionais de negócio podem ser adicionadas aqui
        $this->validateProductAvailability($item->product_id, $item->quantity);
    }

    /**
     * Valida atualizações de item
     */
    private function validateItemUpdates(string $cartId, string $itemId, array $updates): void
    {
        if (isset($updates['quantity'])) {
            $quantity = (int) $updates['quantity'];

            if ($quantity < 0) {
                throw new \InvalidArgumentException('Quantidade não pode ser negativa');
            }

            if ($quantity > self::MAX_QUANTITY_PER_ITEM) {
                throw new \InvalidArgumentException(
                    "Quantidade máxima é " . self::MAX_QUANTITY_PER_ITEM
                );
            }
        }

        if (isset($updates['price'])) {
            $price = (float) $updates['price'];

            if ($price < 0) {
                throw new \InvalidArgumentException('Preço não pode ser negativo');
            }
        }
    }

    /**
     * Valida disponibilidade do produto
     */
    private function validateProductAvailability(string $productId, int $quantity): void
    {
        // Aqui seria integração com serviço de produtos/estoque
        // Por ora, apenas log da validação
        $this->logger->debug('Validating product availability', [
            'product_id' => $productId,
            'requested_quantity' => $quantity
        ]);

        // Implementação futura:
        // - Verificar estoque
        // - Validar status do produto
        // - Verificar se produto está ativo
        // - Validar preço atual vs preço informado
    }

    // ===========================================
    // MÉTODOS UTILITÁRIOS
    // ===========================================

    /**
     * Busca item existente no carrinho por produto
     */
    private function findItemInCart(array $items, ItemData $newItem): ?array
    {
        foreach ($items as $item) {
            if ($item['product_id'] === $newItem->product_id) {
                // Verifica se variações são iguais
                $itemVariations = $item['variations'] ?? [];
                $newVariations = $newItem->variations ?? [];

                if ($this->variationsMatch($itemVariations, $newVariations)) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Busca item por ID
     */
    private function findItemById(array $items, string $itemId): ?array
    {
        foreach ($items as $item) {
            if ($item['id'] === $itemId) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Compara se variações são iguais
     */
    private function variationsMatch(array $variations1, array $variations2): bool
    {
        if (count($variations1) !== count($variations2)) {
            return false;
        }

        ksort($variations1);
        ksort($variations2);

        return $variations1 === $variations2;
    }

    /**
     * Invalida cache relacionado ao carrinho
     */
    private function invalidateCartCache(string $cartId): void
    {
        $cacheKeys = [
            "cart_{$cartId}",
            "cart_items_{$cartId}",
            "cart_totals_{$cartId}"
        ];

        foreach ($cacheKeys as $key) {
            if ($this->cache->hasItem($key)) {
                $this->cache->deleteItem($key);
            }
        }
    }

    /**
     * Obtém estatísticas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => 'ItemService',
            'max_quantity_per_item' => self::MAX_QUANTITY_PER_ITEM,
            'min_quantity_per_item' => self::MIN_QUANTITY_PER_ITEM,
            'cache_ttl' => self::CACHE_TTL
        ];
    }
}