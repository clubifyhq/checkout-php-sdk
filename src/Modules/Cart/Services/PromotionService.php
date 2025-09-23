<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Services;

use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

/**
 * Serviço de Gerenciamento de Promoções
 *
 * Especializado em operações de promoções e descontos para carrinhos,
 * incluindo validação, aplicação e remoção de códigos promocionais.
 *
 * Funcionalidades:
 * - Aplicação e remoção de promoções
 * - Validação de códigos promocionais
 * - Cálculo de descontos
 * - Gestão de múltiplas promoções
 * - Analytics de uso de promoções
 *
 * Endpoints utilizados:
 * - POST /api/v1/cart/:id/promotions
 * - DELETE /api/v1/cart/:id/promotions
 * - POST /api/v1/promotions/validate
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas operações de promoções
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class PromotionService
{
    private const CACHE_TTL = 300; // 5 minutos
    private const MAX_CONCURRENT_PROMOTIONS = 3;

    // Tipos de promoções suportadas
    private const PROMOTION_TYPES = [
        'percentage' => 'Desconto Percentual',
        'fixed' => 'Desconto Fixo',
        'free_shipping' => 'Frete Grátis',
        'bogo' => 'Buy One Get One',
        'bundle' => 'Desconto por Bundle',
        'category' => 'Desconto por Categoria',
        'quantity' => 'Desconto por Quantidade'
    ];

    public function __construct(
        private CartRepositoryInterface $repository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    // ===========================================
    // OPERAÇÕES PRINCIPAIS DE PROMOÇÕES
    // ===========================================

    /**
     * Aplica promoção ao carrinho
     */
    public function apply(string $cartId, string $promotionCode): array
    {
        $this->logger->info('Applying promotion to cart', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode
        ]);

        // Valida código promocional
        $validationResult = $this->validate($promotionCode, $cartId);

        if (!$validationResult['valid']) {
            $this->logger->warning('Promotion validation failed', [
                'cart_id' => $cartId,
                'promotion_code' => $promotionCode,
                'error' => $validationResult['error']
            ]);

            throw new \InvalidArgumentException(
                $validationResult['error'] ?? 'Código promocional inválido'
            );
        }

        // Verifica se carrinho já tem muitas promoções ativas
        $this->validatePromotionLimit($cartId);

        // Aplica promoção via API
        $cart = $this->repository->applyPromotion($cartId, $promotionCode);

        // Dispara evento
        $this->eventDispatcher->dispatch('cart.promotion.applied', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode,
            'promotion_data' => $validationResult['promotion'] ?? null,
            'discount_amount' => $validationResult['discount_amount'] ?? 0
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('Promotion applied successfully', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode,
            'discount_amount' => $validationResult['discount_amount'] ?? 0
        ]);

        return $cart;
    }

    /**
     * Remove promoção do carrinho
     */
    public function remove(string $cartId, string $promotionCode = null): array
    {
        $this->logger->info('Removing promotion from cart', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode
        ]);

        // Remove promoção via API
        $cart = $this->repository->removePromotion($cartId);

        // Dispara evento
        $this->eventDispatcher->dispatch('cart.promotion.removed', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('Promotion removed successfully', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode
        ]);

        return $cart;
    }

    /**
     * Valida código promocional
     */
    public function validate(string $promotionCode, string $cartId): array
    {
        $this->logger->debug('Validating promotion code', [
            'promotion_code' => $promotionCode,
            'cart_id' => $cartId
        ]);

        // Verifica cache de validação
        $cacheKey = "promotion_validation_{$promotionCode}_{$cartId}";
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        try {
            // Valida via API
            $result = $this->repository->validatePromotion($promotionCode, $cartId);

            // Cache do resultado por tempo limitado
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($result);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Promotion validation error', [
                'promotion_code' => $promotionCode,
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'error' => 'Erro ao validar código promocional'
            ];
        }
    }

    // ===========================================
    // OPERAÇÕES DE CONSULTA E ANÁLISE
    // ===========================================

    /**
     * Verifica se carrinho tem promoções ativas
     */
    public function hasActivePromotions(string $cartId): bool
    {
        // Busca dados do carrinho para verificar promoções
        $cart = $this->repository->find($cartId);

        if (!$cart) {
            return false;
        }

        return !empty($cart['promotions']) || !empty($cart['coupon']);
    }

    /**
     * Obtém promoções ativas do carrinho
     */
    public function getActivePromotions(string $cartId): array
    {
        $this->logger->debug('Fetching active promotions', [
            'cart_id' => $cartId
        ]);

        $cart = $this->repository->find($cartId);

        if (!$cart) {
            return [];
        }

        $promotions = [];

        // Verifica promoções modernas
        if (!empty($cart['promotions'])) {
            if (is_array($cart['promotions'])) {
                $promotions = array_merge($promotions, $cart['promotions']);
            } else {
                $promotions[] = $cart['promotions'];
            }
        }

        // Verifica cupom legacy
        if (!empty($cart['coupon'])) {
            $promotions[] = [
                'code' => $cart['coupon']['code'] ?? $cart['coupon'],
                'type' => 'legacy_coupon',
                'discount_amount' => $cart['coupon']['discount_amount'] ?? 0
            ];
        }

        return $promotions;
    }

    /**
     * Calcula desconto total das promoções
     */
    public function calculateTotalDiscount(string $cartId): float
    {
        $activePromotions = $this->getActivePromotions($cartId);
        $totalDiscount = 0.0;

        foreach ($activePromotions as $promotion) {
            $discount = (float) ($promotion['discount_amount'] ?? 0);
            $totalDiscount += $discount;
        }

        return $totalDiscount;
    }

    /**
     * Obtém promoções disponíveis para carrinho
     */
    public function getAvailablePromotions(string $cartId): array
    {
        $this->logger->debug('Fetching available promotions', [
            'cart_id' => $cartId
        ]);

        $cacheKey = "available_promotions_{$cartId}";

        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        try {
            // Busca via API específica para promoções disponíveis
            // Por ora, retorna array vazio até API estar disponível
            $availablePromotions = [];

            // Cache do resultado
            $cacheItem = $this->cache->getItem($cacheKey);
            $cacheItem->set($availablePromotions);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $availablePromotions;

        } catch (\Exception $e) {
            $this->logger->error('Error fetching available promotions', [
                'cart_id' => $cartId,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    // ===========================================
    // OPERAÇÕES DE VALIDAÇÃO ESPECÍFICAS
    // ===========================================

    /**
     * Valida se promoção pode ser combinada com outras
     */
    public function canCombineWithExisting(string $cartId, string $promotionCode): bool
    {
        $activePromotions = $this->getActivePromotions($cartId);

        if (empty($activePromotions)) {
            return true;
        }

        // Valida nova promoção
        $validationResult = $this->validate($promotionCode, $cartId);

        if (!$validationResult['valid']) {
            return false;
        }

        $newPromotion = $validationResult['promotion'] ?? [];

        // Verifica regras de combinação
        foreach ($activePromotions as $activePromotion) {
            if (!$this->canPromotionsCombine($activePromotion, $newPromotion)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se promoção ainda é válida
     */
    public function isPromotionStillValid(string $cartId, string $promotionCode): bool
    {
        $validationResult = $this->validate($promotionCode, $cartId);
        return $validationResult['valid'] ?? false;
    }

    /**
     * Obtém detalhes de desconto por tipo
     */
    public function getDiscountBreakdown(string $cartId): array
    {
        $activePromotions = $this->getActivePromotions($cartId);
        $breakdown = [];

        foreach ($activePromotions as $promotion) {
            $type = $promotion['type'] ?? 'unknown';
            $code = $promotion['code'] ?? 'unknown';
            $amount = (float) ($promotion['discount_amount'] ?? 0);

            $breakdown[] = [
                'code' => $code,
                'type' => $type,
                'type_name' => self::PROMOTION_TYPES[$type] ?? 'Desconhecido',
                'discount_amount' => $amount,
                'formatted_amount' => $this->formatCurrency($amount)
            ];
        }

        return $breakdown;
    }

    // ===========================================
    // OPERAÇÕES DE ADMINISTRAÇÃO
    // ===========================================

    /**
     * Remove todas as promoções do carrinho
     */
    public function removeAll(string $cartId): array
    {
        $this->logger->info('Removing all promotions from cart', [
            'cart_id' => $cartId
        ]);

        $activePromotions = $this->getActivePromotions($cartId);

        if (empty($activePromotions)) {
            // Busca carrinho atual se não há promoções
            return $this->repository->find($cartId) ?? [];
        }

        // Remove todas as promoções
        $cart = $this->repository->removePromotion($cartId);

        // Dispara evento
        $this->eventDispatcher->dispatch('cart.promotions.cleared', [
            'cart_id' => $cartId,
            'removed_promotions' => $activePromotions
        ]);

        // Invalida cache relacionado
        $this->invalidateCartCache($cartId);

        $this->logger->info('All promotions removed successfully', [
            'cart_id' => $cartId,
            'removed_count' => count($activePromotions)
        ]);

        return $cart;
    }

    /**
     * Reaplica promoções após mudanças no carrinho
     */
    public function reapplyPromotions(string $cartId): array
    {
        $this->logger->info('Reapplying promotions to cart', [
            'cart_id' => $cartId
        ]);

        $activePromotions = $this->getActivePromotions($cartId);

        if (empty($activePromotions)) {
            return $this->repository->find($cartId) ?? [];
        }

        // Remove todas primeiro
        $cart = $this->removeAll($cartId);

        // Reaplica uma por uma
        foreach ($activePromotions as $promotion) {
            $code = $promotion['code'] ?? null;
            if ($code) {
                try {
                    $cart = $this->apply($cartId, $code);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to reapply promotion', [
                        'cart_id' => $cartId,
                        'promotion_code' => $code,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        $this->logger->info('Promotions reapplied successfully', [
            'cart_id' => $cartId
        ]);

        return $cart;
    }

    // ===========================================
    // MÉTODOS PRIVADOS DE VALIDAÇÃO
    // ===========================================

    /**
     * Valida limite de promoções concorrentes
     */
    private function validatePromotionLimit(string $cartId): void
    {
        $activePromotions = $this->getActivePromotions($cartId);

        if (count($activePromotions) >= self::MAX_CONCURRENT_PROMOTIONS) {
            throw new \InvalidArgumentException(
                'Limite máximo de ' . self::MAX_CONCURRENT_PROMOTIONS . ' promoções atingido'
            );
        }
    }

    /**
     * Verifica se duas promoções podem ser combinadas
     */
    private function canPromotionsCombine(array $promotion1, array $promotion2): bool
    {
        // Regras básicas de combinação
        $type1 = $promotion1['type'] ?? 'unknown';
        $type2 = $promotion2['type'] ?? 'unknown';

        // Não pode combinar duas promoções percentuais
        if ($type1 === 'percentage' && $type2 === 'percentage') {
            return false;
        }

        // Não pode combinar duas promoções de frete grátis
        if ($type1 === 'free_shipping' && $type2 === 'free_shipping') {
            return false;
        }

        // Por padrão, outras combinações são permitidas
        return true;
    }

    /**
     * Invalida cache relacionado ao carrinho
     */
    private function invalidateCartCache(string $cartId): void
    {
        $cacheKeys = [
            "cart_{$cartId}",
            "cart_totals_{$cartId}",
            "available_promotions_{$cartId}"
        ];

        foreach ($cacheKeys as $key) {
            if ($this->cache->hasItem($key)) {
                $this->cache->deleteItem($key);
            }
        }
    }

    /**
     * Formata valor monetário
     */
    private function formatCurrency(float $amount): string
    {
        return 'R$ ' . number_format($amount, 2, ',', '.');
    }

    /**
     * Obtém estatísticas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => 'PromotionService',
            'max_concurrent_promotions' => self::MAX_CONCURRENT_PROMOTIONS,
            'cache_ttl' => self::CACHE_TTL,
            'supported_types' => array_keys(self::PROMOTION_TYPES)
        ];
    }
}