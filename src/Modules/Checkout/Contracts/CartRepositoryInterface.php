<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout\Contracts;

use ClubifyCheckout\Contracts\RepositoryInterface;

/**
 * Interface para Repository de Carrinho
 *
 * Especializa o RepositoryInterface para operações
 * específicas de carrinho de compras.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de carrinho
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Interface específica para carrinho
 * - D: Dependency Inversion - Abstração para implementações
 */
interface CartRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca carrinho por sessão
     */
    public function findBySession(string $sessionId): ?array;

    /**
     * Busca carrinho por cliente
     */
    public function findByCustomer(string $customerId): ?array;

    /**
     * Busca carrinhos por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca carrinhos abandonados
     */
    public function findAbandoned(int $hoursAgo = 24): array;

    /**
     * Busca carrinhos ativos
     */
    public function findActive(array $filters = []): array;

    /**
     * Busca carrinhos por produto
     */
    public function findByProduct(string $productId, array $filters = []): array;

    /**
     * Adiciona item ao carrinho
     */
    public function addItem(string $cartId, array $item): array;

    /**
     * Remove item do carrinho
     */
    public function removeItem(string $cartId, string $itemId): array;

    /**
     * Atualiza item do carrinho
     */
    public function updateItem(string $cartId, string $itemId, array $data): array;

    /**
     * Limpa carrinho (remove todos os itens)
     */
    public function clearItems(string $cartId): array;

    /**
     * Obtém itens do carrinho
     */
    public function getItems(string $cartId): array;

    /**
     * Conta itens do carrinho
     */
    public function countItems(string $cartId): int;

    /**
     * Aplica cupom de desconto
     */
    public function applyCoupon(string $cartId, string $couponCode): array;

    /**
     * Remove cupom de desconto
     */
    public function removeCoupon(string $cartId): array;

    /**
     * Obtém cupom aplicado
     */
    public function getAppliedCoupon(string $cartId): ?array;

    /**
     * Calcula subtotal do carrinho
     */
    public function calculateSubtotal(string $cartId): float;

    /**
     * Calcula desconto total
     */
    public function calculateDiscount(string $cartId): float;

    /**
     * Calcula taxas do carrinho
     */
    public function calculateTaxes(string $cartId): float;

    /**
     * Calcula frete do carrinho
     */
    public function calculateShipping(string $cartId, array $shippingData = []): float;

    /**
     * Calcula total do carrinho
     */
    public function calculateTotal(string $cartId): float;

    /**
     * Obtém resumo de totais
     */
    public function getTotalsSummary(string $cartId): array;

    /**
     * Atualiza configuração de frete
     */
    public function updateShipping(string $cartId, array $shippingData): array;

    /**
     * Atualiza dados de cobrança
     */
    public function updateBilling(string $cartId, array $billingData): array;

    /**
     * Marca carrinho como abandonado
     */
    public function markAsAbandoned(string $cartId): array;

    /**
     * Marca carrinho como convertido
     */
    public function markAsConverted(string $cartId, string $orderId): array;

    /**
     * Converte carrinho em rascunho de pedido
     */
    public function convertToOrder(string $cartId): array;

    /**
     * Limpa carrinhos abandonados
     */
    public function cleanupAbandoned(int $daysAgo = 30): int;

    /**
     * Obtém estatísticas de carrinho
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Obtém produtos mais adicionados
     */
    public function getMostAddedProducts(int $limit = 10): array;

    /**
     * Obtém valor médio do carrinho
     */
    public function getAverageCartValue(): float;

    /**
     * Obtém taxa de conversão
     */
    public function getConversionRate(): float;
}