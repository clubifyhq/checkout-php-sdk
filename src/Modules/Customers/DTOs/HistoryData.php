<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\DTOs;

use ClubifyCheckout\Data\BaseData;
use DateTime;

/**
 * DTO para histórico de transações do cliente
 *
 * Representa o histórico completo de compras, transações
 * e interações do cliente com a plataforma, incluindo
 * analytics e insights comportamentais.
 *
 * Funcionalidades principais:
 * - Histórico de pedidos e transações
 * - Análise de comportamento de compra
 * - Padrões de consumo
 * - Métricas de lifetime value
 * - Segmentação comportamental
 * - Recomendações personalizadas
 *
 * Estrutura de dados:
 * - orders: Histórico de pedidos
 * - transactions: Histórico de transações
 * - interactions: Interações com a plataforma
 * - analytics: Métricas e insights
 * - preferences: Preferências de compra
 * - segments: Segmentos comportamentais
 */
class HistoryData extends BaseData
{
    public string $customer_id;
    public array $orders = [];
    public array $transactions = [];
    public array $interactions = [];
    public array $analytics = [];
    public array $preferences = [];
    public array $segments = [];
    public float $lifetime_value = 0.0;
    public float $average_order_value = 0.0;
    public int $total_orders = 0;
    public int $total_transactions = 0;
    public ?DateTime $first_purchase_at = null;
    public ?DateTime $last_purchase_at = null;
    public int $days_since_last_purchase = 0;
    public float $purchase_frequency = 0.0;
    public array $favorite_categories = [];
    public array $payment_methods_used = [];
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;

    /**
     * Regras de validação
     */
    protected function getValidationRules(): array
    {
        return [
            'customer_id' => ['required', 'string'],
            'orders' => ['array'],
            'transactions' => ['array'],
            'interactions' => ['array'],
            'analytics' => ['array'],
            'preferences' => ['array'],
            'segments' => ['array'],
            'lifetime_value' => ['numeric', 'min:0'],
            'average_order_value' => ['numeric', 'min:0'],
            'total_orders' => ['integer', 'min:0'],
            'total_transactions' => ['integer', 'min:0'],
            'first_purchase_at' => ['nullable', 'date'],
            'last_purchase_at' => ['nullable', 'date'],
            'days_since_last_purchase' => ['integer', 'min:0'],
            'purchase_frequency' => ['numeric', 'min:0'],
            'favorite_categories' => ['array'],
            'payment_methods_used' => ['array'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Converte datas
        $dateFields = ['first_purchase_at', 'last_purchase_at', 'created_at', 'updated_at'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = new DateTime($data[$field]);
            }
        }

        // Calcula dias desde última compra
        if (isset($data['last_purchase_at']) && $data['last_purchase_at'] instanceof DateTime) {
            $data['days_since_last_purchase'] = (int) $data['last_purchase_at']->diff(new DateTime())->days;
        }

        return $data;
    }

    /**
     * Adiciona novo pedido ao histórico
     */
    public function addOrder(array $orderData): void
    {
        $order = [
            'id' => $orderData['id'],
            'total' => $orderData['total'],
            'status' => $orderData['status'],
            'items' => $orderData['items'] ?? [],
            'payment_method' => $orderData['payment_method'] ?? null,
            'created_at' => $orderData['created_at'] ?? new DateTime(),
        ];

        $this->orders[] = $order;
        $this->recalculateMetrics();
    }

    /**
     * Adiciona nova transação ao histórico
     */
    public function addTransaction(array $transactionData): void
    {
        $transaction = [
            'id' => $transactionData['id'],
            'type' => $transactionData['type'], // payment, refund, chargeback
            'amount' => $transactionData['amount'],
            'status' => $transactionData['status'],
            'gateway' => $transactionData['gateway'] ?? null,
            'order_id' => $transactionData['order_id'] ?? null,
            'created_at' => $transactionData['created_at'] ?? new DateTime(),
        ];

        $this->transactions[] = $transaction;
        $this->recalculateMetrics();
    }

    /**
     * Adiciona nova interação ao histórico
     */
    public function addInteraction(array $interactionData): void
    {
        $interaction = [
            'type' => $interactionData['type'], // view, click, cart_add, etc.
            'context' => $interactionData['context'] ?? [],
            'metadata' => $interactionData['metadata'] ?? [],
            'created_at' => $interactionData['created_at'] ?? new DateTime(),
        ];

        $this->interactions[] = $interaction;
    }

    /**
     * Obtém pedidos por período
     */
    public function getOrdersByPeriod(DateTime $startDate, DateTime $endDate): array
    {
        return array_filter($this->orders, function ($order) use ($startDate, $endDate) {
            $orderDate = $order['created_at'] instanceof DateTime
                ? $order['created_at']
                : new DateTime($order['created_at']);

            return $orderDate >= $startDate && $orderDate <= $endDate;
        });
    }

    /**
     * Obtém valor total gasto por período
     */
    public function getTotalSpentByPeriod(DateTime $startDate, DateTime $endDate): float
    {
        $orders = $this->getOrdersByPeriod($startDate, $endDate);

        return array_sum(array_column($orders, 'total'));
    }

    /**
     * Obtém frequência de compra (pedidos por mês)
     */
    public function getPurchaseFrequency(): float
    {
        if (!$this->first_purchase_at || !$this->last_purchase_at) {
            return 0.0;
        }

        $monthsDiff = $this->first_purchase_at->diff($this->last_purchase_at)->days / 30;

        if ($monthsDiff <= 0) {
            return 0.0;
        }

        return $this->total_orders / $monthsDiff;
    }

    /**
     * Obtém categorias favoritas baseadas no histórico
     */
    public function getFavoriteCategories(int $limit = 5): array
    {
        $categories = [];

        foreach ($this->orders as $order) {
            if (isset($order['items'])) {
                foreach ($order['items'] as $item) {
                    if (isset($item['category'])) {
                        $category = $item['category'];
                        $categories[$category] = ($categories[$category] ?? 0) + 1;
                    }
                }
            }
        }

        arsort($categories);

        return array_slice(array_keys($categories), 0, $limit);
    }

    /**
     * Obtém métodos de pagamento utilizados
     */
    public function getPaymentMethodsUsed(): array
    {
        $methods = [];

        foreach ($this->orders as $order) {
            if (isset($order['payment_method'])) {
                $method = $order['payment_method'];
                $methods[$method] = ($methods[$method] ?? 0) + 1;
            }
        }

        return $methods;
    }

    /**
     * Calcula Customer Lifetime Value (CLV)
     */
    public function calculateLifetimeValue(): float
    {
        if ($this->total_orders === 0) {
            return 0.0;
        }

        // CLV = AOV × Frequência de Compra × Margem de Lucro × Tempo de Vida
        $aov = $this->average_order_value;
        $frequency = $this->getPurchaseFrequency();
        $profitMargin = 0.3; // 30% de margem
        $lifespan = 24; // 24 meses

        return $aov * $frequency * $profitMargin * $lifespan;
    }

    /**
     * Obtém segmento do cliente baseado no comportamento
     */
    public function getCustomerSegment(): string
    {
        $clv = $this->calculateLifetimeValue();
        $frequency = $this->getPurchaseFrequency();
        $daysSinceLastPurchase = $this->days_since_last_purchase;

        // Segmentação RFM (Recency, Frequency, Monetary)
        if ($clv > 10000 && $frequency > 2 && $daysSinceLastPurchase < 30) {
            return 'vip';
        } elseif ($clv > 5000 && $frequency > 1 && $daysSinceLastPurchase < 60) {
            return 'loyal';
        } elseif ($frequency > 0.5 && $daysSinceLastPurchase < 90) {
            return 'regular';
        } elseif ($daysSinceLastPurchase > 180) {
            return 'at_risk';
        } elseif ($this->total_orders === 1) {
            return 'new';
        } else {
            return 'occasional';
        }
    }

    /**
     * Obtém recomendações baseadas no histórico
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        // Baseado em categorias favoritas
        $favoriteCategories = $this->getFavoriteCategories(3);
        foreach ($favoriteCategories as $category) {
            $recommendations[] = [
                'type' => 'category',
                'value' => $category,
                'reason' => 'Baseado em suas compras anteriores',
                'score' => 0.8,
            ];
        }

        // Baseado no segmento
        $segment = $this->getCustomerSegment();
        switch ($segment) {
            case 'vip':
                $recommendations[] = [
                    'type' => 'exclusive_offer',
                    'reason' => 'Oferta exclusiva para clientes VIP',
                    'score' => 0.9,
                ];
                break;
            case 'at_risk':
                $recommendations[] = [
                    'type' => 'win_back_offer',
                    'reason' => 'Oferta especial para reconquistar você',
                    'score' => 0.95,
                ];
                break;
        }

        return $recommendations;
    }

    /**
     * Obtém insights comportamentais
     */
    public function getBehavioralInsights(): array
    {
        return [
            'segment' => $this->getCustomerSegment(),
            'lifetime_value' => $this->calculateLifetimeValue(),
            'purchase_frequency' => $this->getPurchaseFrequency(),
            'favorite_categories' => $this->getFavoriteCategories(),
            'preferred_payment_methods' => $this->getPaymentMethodsUsed(),
            'shopping_patterns' => $this->getShoppingPatterns(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Recalcula métricas automaticamente
     */
    private function recalculateMetrics(): void
    {
        // Total de pedidos
        $this->total_orders = count($this->orders);

        // Total de transações
        $this->total_transactions = count($this->transactions);

        // Valor total gasto
        $totalSpent = array_sum(array_column($this->orders, 'total'));

        // Valor médio por pedido
        $this->average_order_value = $this->total_orders > 0 ? $totalSpent / $this->total_orders : 0.0;

        // Primeira e última compra
        if (!empty($this->orders)) {
            $dates = array_map(function ($order) {
                return $order['created_at'] instanceof DateTime
                    ? $order['created_at']
                    : new DateTime($order['created_at']);
            }, $this->orders);

            $this->first_purchase_at = min($dates);
            $this->last_purchase_at = max($dates);

            $this->days_since_last_purchase = (int) $this->last_purchase_at->diff(new DateTime())->days;
        }

        // Frequência de compra
        $this->purchase_frequency = $this->getPurchaseFrequency();

        // Lifetime value
        $this->lifetime_value = $this->calculateLifetimeValue();

        // Categorias favoritas
        $this->favorite_categories = $this->getFavoriteCategories();

        // Métodos de pagamento
        $this->payment_methods_used = array_keys($this->getPaymentMethodsUsed());
    }

    /**
     * Obtém padrões de compra (hora, dia da semana, etc.)
     */
    private function getShoppingPatterns(): array
    {
        $patterns = [
            'hours' => [],
            'days_of_week' => [],
            'months' => [],
        ];

        foreach ($this->orders as $order) {
            $date = $order['created_at'] instanceof DateTime
                ? $order['created_at']
                : new DateTime($order['created_at']);

            $hour = (int) $date->format('H');
            $dayOfWeek = (int) $date->format('w'); // 0 = domingo
            $month = (int) $date->format('n');

            $patterns['hours'][$hour] = ($patterns['hours'][$hour] ?? 0) + 1;
            $patterns['days_of_week'][$dayOfWeek] = ($patterns['days_of_week'][$dayOfWeek] ?? 0) + 1;
            $patterns['months'][$month] = ($patterns['months'][$month] ?? 0) + 1;
        }

        return $patterns;
    }
}