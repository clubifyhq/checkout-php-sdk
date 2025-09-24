<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Repositories;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Security\SecurityValidator;
use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Repositório de API para operações de carrinho
 *
 * Implementa todas as operações de carrinho através de
 * chamadas HTTP para os endpoints do Clubify Cart Service.
 *
 * Endpoints implementados:
 * - POST/GET/PUT/DELETE /api/v1/cart
 * - POST/PUT/DELETE /api/v1/cart/:id/items
 * - GET/POST /navigation/flow/:offerId
 * - POST /api/v1/cart/:id/promotions
 * - POST /api/v1/cart/:id/one-click
 */
class ApiCartRepository implements CartRepositoryInterface
{
    private const API_VERSION = 'v1';
    private const BASE_PATH = '/api/v1/cart';
    private const NAVIGATION_PATH = '/navigation/flow';

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient
    ) {
    }

    // ===========================================
    // OPERAÇÕES BÁSICAS DE CARRINHO
    // ===========================================

    /**
     * Cria novo carrinho
     */
    public function create(array $data): array
    {
        // Security: Sanitize input data to prevent XSS and injection attacks
        $data = SecurityValidator::sanitizeInput($data);

        $this->logger->info('Creating cart via API', ['data_keys' => array_keys($data)]);

        $response = $this->makeHttpRequest('POST', self::BASE_PATH, $data);

        $this->logger->info('Cart created successfully', [
            'cart_id' => $response['id'] ?? null
        ]);

        return $response;
    }

    /**
     * Busca carrinho por ID
     */
    public function find(string $id): ?array
    {
        // Security: Validate and sanitize cart ID
        $id = SecurityValidator::sanitizeInput($id);
        if (!SecurityValidator::validateUuid($id)) {
            throw new \InvalidArgumentException('Invalid cart ID format');
        }

        $this->logger->debug('Fetching cart by ID', ['cart_id' => $id]);

        try {
            $response = $this->makeHttpRequest('GET', self::BASE_PATH . "/{$id}");
            return $response;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->logger->debug('Cart not found', ['cart_id' => $id]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca carrinho por sessão
     */
    public function findBySession(string $sessionId): ?array
    {
        // Security: Sanitize session ID
        $sessionId = SecurityValidator::sanitizeInput($sessionId);

        $this->logger->debug('Fetching cart by session', ['session_id' => substr($sessionId, 0, 10) . '...']);

        try {
            $response = $this->makeHttpRequest('GET', self::BASE_PATH, [
                'session_id' => $sessionId
            ]);

            return $response['data'][0] ?? null;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->logger->debug('Cart not found by session', ['session_id' => $sessionId]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca carrinho por cliente
     */
    public function findByCustomer(string $customerId): ?array
    {
        // Security: Validate and sanitize customer ID
        $customerId = SecurityValidator::sanitizeInput($customerId);
        if (!SecurityValidator::validateUuid($customerId)) {
            throw new \InvalidArgumentException('Invalid customer ID format');
        }

        $this->logger->debug('Fetching cart by customer', ['customer_id' => $customerId]);

        try {
            $response = $this->makeHttpRequest('GET', self::BASE_PATH, [
                'customer_id' => $customerId,
                'status' => 'active'
            ]);

            return $response['data'][0] ?? null;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                $this->logger->debug('Cart not found by customer', ['customer_id' => $customerId]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza dados do carrinho
     */
    public function update(string $id, array $data): array
    {
        // Security: Validate and sanitize inputs
        $id = SecurityValidator::sanitizeInput($id);
        if (!SecurityValidator::validateUuid($id)) {
            throw new \InvalidArgumentException('Invalid cart ID format');
        }
        $data = SecurityValidator::sanitizeInput($data);

        $this->logger->info('Updating cart via API', [
            'cart_id' => $id,
            'data_keys' => array_keys($data)
        ]);

        $response = $this->makeHttpRequest('PUT', self::BASE_PATH . "/{$id}", $data);

        $this->logger->info('Cart updated successfully', ['cart_id' => $id]);

        return $response;
    }

    /**
     * Remove carrinho
     */
    public function delete(string $id): bool
    {
        $this->logger->info('Deleting cart via API', ['cart_id' => $id]);

        try {
            $this->makeHttpRequest('DELETE', self::BASE_PATH . "/{$id}");

            $this->logger->info('Cart deleted successfully', ['cart_id' => $id]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete cart', [
                'cart_id' => $id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    // ===========================================
    // OPERAÇÕES DE ITENS
    // ===========================================

    /**
     * Adiciona item ao carrinho
     */
    public function addItem(string $cartId, array $itemData): array
    {
        // Security: Validate and sanitize inputs
        $cartId = SecurityValidator::sanitizeInput($cartId);
        if (!SecurityValidator::validateUuid($cartId)) {
            throw new \InvalidArgumentException('Invalid cart ID format');
        }
        $itemData = SecurityValidator::sanitizeInput($itemData);

        $this->logger->info('Adding item to cart', [
            'cart_id' => $cartId,
            'item_data_keys' => array_keys($itemData)
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/items",
            $itemData
        );

        $this->logger->info('Item added to cart successfully', [
            'cart_id' => $cartId,
            'item_id' => $response['item_id'] ?? null
        ]);

        return $response;
    }

    /**
     * Atualiza item do carrinho
     */
    public function updateItem(string $cartId, string $itemId, array $updates): array
    {
        $this->logger->info('Updating cart item', [
            'cart_id' => $cartId,
            'item_id' => $itemId,
            'updates' => $updates
        ]);

        $response = $this->makeHttpRequest('PUT', 
            self::BASE_PATH . "/{$cartId}/items/{$itemId}",
            $updates
        );

        $this->logger->info('Cart item updated successfully', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        return $response;
    }

    /**
     * Remove item do carrinho
     */
    public function removeItem(string $cartId, string $itemId): array
    {
        $this->logger->info('Removing item from cart', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        $response = $this->makeHttpRequest('DELETE', 
            self::BASE_PATH . "/{$cartId}/items/{$itemId}"
        );

        $this->logger->info('Item removed from cart successfully', [
            'cart_id' => $cartId,
            'item_id' => $itemId
        ]);

        return $response;
    }

    /**
     * Obtém itens do carrinho
     */
    public function getItems(string $cartId): array
    {
        $this->logger->debug('Fetching cart items', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('GET', 
            self::BASE_PATH . "/{$cartId}/items"
        );

        return $response['items'] ?? [];
    }

    /**
     * Conta itens do carrinho
     */
    public function countItems(string $cartId): int
    {
        $items = $this->getItems($cartId);
        return count($items);
    }

    /**
     * Limpa todos os itens do carrinho
     */
    public function clearItems(string $cartId): array
    {
        $this->logger->info('Clearing cart items', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('DELETE', 
            self::BASE_PATH . "/{$cartId}/items"
        );

        $this->logger->info('Cart items cleared successfully', ['cart_id' => $cartId]);

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE CÁLCULOS
    // ===========================================

    /**
     * Calcula subtotal do carrinho
     */
    public function calculateSubtotal(string $cartId): float
    {
        $this->logger->debug('Calculating cart subtotal', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('GET', 
            self::BASE_PATH . "/{$cartId}/totals"
        );

        return (float) ($response['subtotal'] ?? 0.0);
    }

    /**
     * Calcula desconto total
     */
    public function calculateDiscount(string $cartId): float
    {
        $this->logger->debug('Calculating cart discount', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('GET', 
            self::BASE_PATH . "/{$cartId}/totals"
        );

        return (float) ($response['discount'] ?? 0.0);
    }

    /**
     * Calcula taxas
     */
    public function calculateTaxes(string $cartId): float
    {
        $this->logger->debug('Calculating cart taxes', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('GET', 
            self::BASE_PATH . "/{$cartId}/totals"
        );

        return (float) ($response['taxes'] ?? 0.0);
    }

    /**
     * Calcula frete
     */
    public function calculateShipping(string $cartId, array $shippingData): float
    {
        $this->logger->debug('Calculating cart shipping', [
            'cart_id' => $cartId,
            'shipping_data' => $shippingData
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/shipping/calculate",
            $shippingData
        );

        return (float) ($response['shipping_cost'] ?? 0.0);
    }

    /**
     * Obtém resumo de totais
     */
    public function getTotalsSummary(string $cartId): array
    {
        $this->logger->debug('Fetching cart totals summary', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('GET', 
            self::BASE_PATH . "/{$cartId}/totals"
        );

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE PROMOÇÕES
    // ===========================================

    /**
     * Aplica promoção ao carrinho
     */
    public function applyPromotion(string $cartId, string $promotionCode): array
    {
        // Security: Validate and sanitize inputs
        $cartId = SecurityValidator::sanitizeInput($cartId);
        if (!SecurityValidator::validateUuid($cartId)) {
            throw new \InvalidArgumentException('Invalid cart ID format');
        }
        $promotionCode = SecurityValidator::sanitizeInput($promotionCode);

        $this->logger->info('Applying promotion to cart', [
            'cart_id' => $cartId,
            'promotion_code' => substr($promotionCode, 0, 10) . '...'
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/promotions",
            ['code' => $promotionCode]
        );

        $this->logger->info('Promotion applied successfully', [
            'cart_id' => $cartId,
            'promotion_code' => $promotionCode
        ]);

        return $response;
    }

    /**
     * Remove promoção do carrinho
     */
    public function removePromotion(string $cartId): array
    {
        $this->logger->info('Removing promotion from cart', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('DELETE', 
            self::BASE_PATH . "/{$cartId}/promotions"
        );

        $this->logger->info('Promotion removed successfully', ['cart_id' => $cartId]);

        return $response;
    }

    /**
     * Valida promoção
     */
    public function validatePromotion(string $promotionCode, string $cartId): array
    {
        $this->logger->debug('Validating promotion', [
            'promotion_code' => $promotionCode,
            'cart_id' => $cartId
        ]);

        $response = $this->makeHttpRequest('POST', '/api/v1/promotions/validate', [
            'code' => $promotionCode,
            'cart_id' => $cartId
        ]);

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE CUPONS (LEGACY)
    // ===========================================

    /**
     * Aplica cupom de desconto
     */
    public function applyCoupon(string $cartId, string $couponCode): array
    {
        // Redireciona para a funcionalidade de promoções
        return $this->applyPromotion($cartId, $couponCode);
    }

    /**
     * Remove cupom de desconto
     */
    public function removeCoupon(string $cartId): array
    {
        // Redireciona para a funcionalidade de promoções
        return $this->removePromotion($cartId);
    }

    // ===========================================
    // OPERAÇÕES DE NAVEGAÇÃO E FLUXOS
    // ===========================================

    /**
     * Inicia navegação de fluxo
     */
    public function startFlowNavigation(string $offerId, array $context = []): array
    {
        $this->logger->info('Starting flow navigation', [
            'offer_id' => $offerId,
            'context' => $context
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::NAVIGATION_PATH . "/{$offerId}",
            $context
        );

        $this->logger->info('Flow navigation started', [
            'offer_id' => $offerId,
            'navigation_id' => $response['navigation_id'] ?? null
        ]);

        return $response;
    }

    /**
     * Continua navegação de fluxo
     */
    public function continueFlowNavigation(string $navigationId, array $stepData): array
    {
        $this->logger->info('Continuing flow navigation', [
            'navigation_id' => $navigationId,
            'step_data' => $stepData
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::NAVIGATION_PATH . "/navigation/{$navigationId}/continue",
            $stepData
        );

        $this->logger->info('Flow navigation continued', [
            'navigation_id' => $navigationId
        ]);

        return $response;
    }

    /**
     * Obtém dados de navegação
     */
    public function getFlowNavigation(string $navigationId): ?array
    {
        $this->logger->debug('Fetching flow navigation', [
            'navigation_id' => $navigationId
        ]);

        try {
            $response = $this->makeHttpRequest('GET', 
                self::NAVIGATION_PATH . "/navigation/{$navigationId}"
            );

            return $response;
        } catch (\Exception $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Finaliza navegação de fluxo
     */
    public function completeFlowNavigation(string $navigationId): array
    {
        $this->logger->info('Completing flow navigation', [
            'navigation_id' => $navigationId
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::NAVIGATION_PATH . "/navigation/{$navigationId}/complete"
        );

        $this->logger->info('Flow navigation completed', [
            'navigation_id' => $navigationId
        ]);

        return $response;
    }

    // ===========================================
    // OPERAÇÕES ONE-CLICK
    // ===========================================

    /**
     * Processa checkout one-click
     */
    public function processOneClick(string $cartId, array $paymentData): array
    {
        $this->logger->info('Processing one-click checkout', [
            'cart_id' => $cartId,
            'payment_method' => $paymentData['payment_method'] ?? null
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/one-click",
            $paymentData
        );

        $this->logger->info('One-click checkout processed', [
            'cart_id' => $cartId,
            'transaction_id' => $response['transaction_id'] ?? null
        ]);

        return $response;
    }

    /**
     * Valida dados para one-click
     */
    public function validateOneClick(string $cartId, array $paymentData): array
    {
        $this->logger->debug('Validating one-click data', [
            'cart_id' => $cartId
        ]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/one-click/validate",
            $paymentData
        );

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE DADOS ADICIONAIS
    // ===========================================

    /**
     * Atualiza dados de frete
     */
    public function updateShipping(string $cartId, array $shippingData): array
    {
        $this->logger->info('Updating cart shipping data', [
            'cart_id' => $cartId
        ]);

        $response = $this->makeHttpRequest('PUT', 
            self::BASE_PATH . "/{$cartId}/shipping",
            $shippingData
        );

        $this->logger->info('Cart shipping data updated', ['cart_id' => $cartId]);

        return $response;
    }

    /**
     * Atualiza dados de cobrança
     */
    public function updateBilling(string $cartId, array $billingData): array
    {
        $this->logger->info('Updating cart billing data', [
            'cart_id' => $cartId
        ]);

        $response = $this->makeHttpRequest('PUT', 
            self::BASE_PATH . "/{$cartId}/billing",
            $billingData
        );

        $this->logger->info('Cart billing data updated', ['cart_id' => $cartId]);

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE ESTADO
    // ===========================================

    /**
     * Marca carrinho como abandonado
     */
    public function markAsAbandoned(string $cartId): array
    {
        $this->logger->info('Marking cart as abandoned', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('PUT', 
            self::BASE_PATH . "/{$cartId}/abandon"
        );

        $this->logger->info('Cart marked as abandoned', ['cart_id' => $cartId]);

        return $response;
    }

    /**
     * Converte carrinho em pedido
     */
    public function convertToOrder(string $cartId): array
    {
        $this->logger->info('Converting cart to order', ['cart_id' => $cartId]);

        $response = $this->makeHttpRequest('POST', 
            self::BASE_PATH . "/{$cartId}/convert"
        );

        $this->logger->info('Cart converted to order', [
            'cart_id' => $cartId,
            'order_id' => $response['order_id'] ?? null
        ]);

        return $response;
    }

    // ===========================================
    // OPERAÇÕES DE CONSULTA E ANÁLISE
    // ===========================================

    /**
     * Busca carrinhos abandonados
     */
    public function findAbandoned(int $hoursAgo): array
    {
        $this->logger->debug('Fetching abandoned carts', [
            'hours_ago' => $hoursAgo
        ]);

        $response = $this->makeHttpRequest('GET', self::BASE_PATH . '/abandoned', [
            'hours_ago' => $hoursAgo
        ]);

        return $response['data'] ?? [];
    }

    /**
     * Remove carrinhos abandonados antigos
     */
    public function cleanupAbandoned(int $daysAgo): int
    {
        $this->logger->info('Cleaning up abandoned carts', [
            'days_ago' => $daysAgo
        ]);

        $response = $this->makeHttpRequest('DELETE', self::BASE_PATH . '/abandoned', [
            'days_ago' => $daysAgo
        ]);

        $deletedCount = $response['deleted_count'] ?? 0;

        $this->logger->info('Abandoned carts cleaned up', [
            'deleted_count' => $deletedCount
        ]);

        return $deletedCount;
    }

    /**
     * Obtém estatísticas de carrinhos
     */
    public function getStatistics(array $filters = []): array
    {
        $this->logger->debug('Fetching cart statistics', [
            'filters' => $filters
        ]);

        $response = $this->makeHttpRequest('GET', self::BASE_PATH . '/statistics', $filters);

        return $response;
    }

    /**
     * Obtém produtos mais adicionados
     */
    public function getMostAddedProducts(int $limit = 10): array
    {
        $this->logger->debug('Fetching most added products', [
            'limit' => $limit
        ]);

        $response = $this->makeHttpRequest('GET', self::BASE_PATH . '/analytics/products/most-added', [
            'limit' => $limit
        ]);

        return $response['products'] ?? [];
    }

    /**
     * Obtém valor médio do carrinho
     */
    public function getAverageCartValue(): float
    {
        $this->logger->debug('Fetching average cart value');

        $response = $this->makeHttpRequest('GET', self::BASE_PATH . '/analytics/average-value');

        return (float) ($response['average_value'] ?? 0.0);
    }

    /**
     * Obtém taxa de conversão
     */
    public function getConversionRate(): float
    {
        $this->logger->debug('Fetching cart conversion rate');

        $response = $this->makeHttpRequest('GET', self::BASE_PATH . '/analytics/conversion-rate');

        return (float) ($response['conversion_rate'] ?? 0.0);
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
