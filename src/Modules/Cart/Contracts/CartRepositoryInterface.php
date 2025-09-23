<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Contracts;

/**
 * Interface para repositório de carrinho
 *
 * Define os contratos para todas as operações de carrinho,
 * incluindo CRUD básico, gerenciamento de itens, promoções
 * e integração com APIs do Clubify Cart Service.
 *
 * Endpoints implementados:
 * - POST/GET/PUT/DELETE /api/v1/cart
 * - POST/PUT/DELETE /api/v1/cart/:id/items
 * - GET/POST /navigation/flow/:offerId
 * - POST /api/v1/cart/:id/promotions
 * - POST /api/v1/cart/:id/one-click
 */
interface CartRepositoryInterface
{
    // ===========================================
    // OPERAÇÕES BÁSICAS DE CARRINHO
    // ===========================================

    /**
     * Cria novo carrinho
     *
     * @param array $data Dados do carrinho
     * @return array Carrinho criado
     */
    public function create(array $data): array;

    /**
     * Busca carrinho por ID
     *
     * @param string $id ID do carrinho
     * @return array|null Carrinho encontrado ou null
     */
    public function find(string $id): ?array;

    /**
     * Busca carrinho por sessão
     *
     * @param string $sessionId ID da sessão
     * @return array|null Carrinho encontrado ou null
     */
    public function findBySession(string $sessionId): ?array;

    /**
     * Busca carrinho por cliente
     *
     * @param string $customerId ID do cliente
     * @return array|null Carrinho encontrado ou null
     */
    public function findByCustomer(string $customerId): ?array;

    /**
     * Atualiza dados do carrinho
     *
     * @param string $id ID do carrinho
     * @param array $data Dados para atualizar
     * @return array Carrinho atualizado
     */
    public function update(string $id, array $data): array;

    /**
     * Remove carrinho
     *
     * @param string $id ID do carrinho
     * @return bool Sucesso da operação
     */
    public function delete(string $id): bool;

    // ===========================================
    // OPERAÇÕES DE ITENS
    // ===========================================

    /**
     * Adiciona item ao carrinho
     *
     * @param string $cartId ID do carrinho
     * @param array $itemData Dados do item
     * @return array Carrinho atualizado
     */
    public function addItem(string $cartId, array $itemData): array;

    /**
     * Atualiza item do carrinho
     *
     * @param string $cartId ID do carrinho
     * @param string $itemId ID do item
     * @param array $updates Dados para atualizar
     * @return array Carrinho atualizado
     */
    public function updateItem(string $cartId, string $itemId, array $updates): array;

    /**
     * Remove item do carrinho
     *
     * @param string $cartId ID do carrinho
     * @param string $itemId ID do item
     * @return array Carrinho atualizado
     */
    public function removeItem(string $cartId, string $itemId): array;

    /**
     * Obtém itens do carrinho
     *
     * @param string $cartId ID do carrinho
     * @return array Lista de itens
     */
    public function getItems(string $cartId): array;

    /**
     * Conta itens do carrinho
     *
     * @param string $cartId ID do carrinho
     * @return int Número de itens
     */
    public function countItems(string $cartId): int;

    /**
     * Limpa todos os itens do carrinho
     *
     * @param string $cartId ID do carrinho
     * @return array Carrinho atualizado
     */
    public function clearItems(string $cartId): array;

    // ===========================================
    // OPERAÇÕES DE CÁLCULOS
    // ===========================================

    /**
     * Calcula subtotal do carrinho
     *
     * @param string $cartId ID do carrinho
     * @return float Subtotal
     */
    public function calculateSubtotal(string $cartId): float;

    /**
     * Calcula desconto total
     *
     * @param string $cartId ID do carrinho
     * @return float Desconto
     */
    public function calculateDiscount(string $cartId): float;

    /**
     * Calcula taxas
     *
     * @param string $cartId ID do carrinho
     * @return float Taxas
     */
    public function calculateTaxes(string $cartId): float;

    /**
     * Calcula frete
     *
     * @param string $cartId ID do carrinho
     * @param array $shippingData Dados de envio
     * @return float Frete
     */
    public function calculateShipping(string $cartId, array $shippingData): float;

    /**
     * Obtém resumo de totais
     *
     * @param string $cartId ID do carrinho
     * @return array Resumo de totais
     */
    public function getTotalsSummary(string $cartId): array;

    // ===========================================
    // OPERAÇÕES DE PROMOÇÕES
    // ===========================================

    /**
     * Aplica promoção ao carrinho
     *
     * @param string $cartId ID do carrinho
     * @param string $promotionCode Código da promoção
     * @return array Carrinho atualizado
     */
    public function applyPromotion(string $cartId, string $promotionCode): array;

    /**
     * Remove promoção do carrinho
     *
     * @param string $cartId ID do carrinho
     * @return array Carrinho atualizado
     */
    public function removePromotion(string $cartId): array;

    /**
     * Valida promoção
     *
     * @param string $promotionCode Código da promoção
     * @param string $cartId ID do carrinho
     * @return array Resultado da validação
     */
    public function validatePromotion(string $promotionCode, string $cartId): array;

    // ===========================================
    // OPERAÇÕES DE CUPONS (LEGACY)
    // ===========================================

    /**
     * Aplica cupom de desconto
     *
     * @param string $cartId ID do carrinho
     * @param string $couponCode Código do cupom
     * @return array Carrinho atualizado
     */
    public function applyCoupon(string $cartId, string $couponCode): array;

    /**
     * Remove cupom de desconto
     *
     * @param string $cartId ID do carrinho
     * @return array Carrinho atualizado
     */
    public function removeCoupon(string $cartId): array;

    // ===========================================
    // OPERAÇÕES DE NAVEGAÇÃO E FLUXOS
    // ===========================================

    /**
     * Inicia navegação de fluxo
     *
     * @param string $offerId ID da oferta
     * @param array $context Contexto da navegação
     * @return array Navegação iniciada
     */
    public function startFlowNavigation(string $offerId, array $context = []): array;

    /**
     * Continua navegação de fluxo
     *
     * @param string $navigationId ID da navegação
     * @param array $stepData Dados do passo
     * @return array Navegação atualizada
     */
    public function continueFlowNavigation(string $navigationId, array $stepData): array;

    /**
     * Obtém dados de navegação
     *
     * @param string $navigationId ID da navegação
     * @return array|null Dados da navegação
     */
    public function getFlowNavigation(string $navigationId): ?array;

    /**
     * Finaliza navegação de fluxo
     *
     * @param string $navigationId ID da navegação
     * @return array Resultado da finalização
     */
    public function completeFlowNavigation(string $navigationId): array;

    // ===========================================
    // OPERAÇÕES ONE-CLICK
    // ===========================================

    /**
     * Processa checkout one-click
     *
     * @param string $cartId ID do carrinho
     * @param array $paymentData Dados do pagamento
     * @return array Resultado do processamento
     */
    public function processOneClick(string $cartId, array $paymentData): array;

    /**
     * Valida dados para one-click
     *
     * @param string $cartId ID do carrinho
     * @param array $paymentData Dados do pagamento
     * @return array Resultado da validação
     */
    public function validateOneClick(string $cartId, array $paymentData): array;

    // ===========================================
    // OPERAÇÕES DE DADOS ADICIONAIS
    // ===========================================

    /**
     * Atualiza dados de frete
     *
     * @param string $cartId ID do carrinho
     * @param array $shippingData Dados de frete
     * @return array Carrinho atualizado
     */
    public function updateShipping(string $cartId, array $shippingData): array;

    /**
     * Atualiza dados de cobrança
     *
     * @param string $cartId ID do carrinho
     * @param array $billingData Dados de cobrança
     * @return array Carrinho atualizado
     */
    public function updateBilling(string $cartId, array $billingData): array;

    // ===========================================
    // OPERAÇÕES DE ESTADO
    // ===========================================

    /**
     * Marca carrinho como abandonado
     *
     * @param string $cartId ID do carrinho
     * @return array Carrinho atualizado
     */
    public function markAsAbandoned(string $cartId): array;

    /**
     * Converte carrinho em pedido
     *
     * @param string $cartId ID do carrinho
     * @return array Resultado da conversão
     */
    public function convertToOrder(string $cartId): array;

    // ===========================================
    // OPERAÇÕES DE CONSULTA E ANÁLISE
    // ===========================================

    /**
     * Busca carrinhos abandonados
     *
     * @param int $hoursAgo Horas de abandono
     * @return array Lista de carrinhos abandonados
     */
    public function findAbandoned(int $hoursAgo): array;

    /**
     * Remove carrinhos abandonados antigos
     *
     * @param int $daysAgo Dias de abandono
     * @return int Quantidade removida
     */
    public function cleanupAbandoned(int $daysAgo): int;

    /**
     * Obtém estatísticas de carrinhos
     *
     * @param array $filters Filtros para as estatísticas
     * @return array Estatísticas
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Obtém produtos mais adicionados
     *
     * @param int $limit Limite de resultados
     * @return array Lista de produtos
     */
    public function getMostAddedProducts(int $limit = 10): array;

    /**
     * Obtém valor médio do carrinho
     *
     * @return float Valor médio
     */
    public function getAverageCartValue(): float;

    /**
     * Obtém taxa de conversão
     *
     * @return float Taxa de conversão
     */
    public function getConversionRate(): float;
}