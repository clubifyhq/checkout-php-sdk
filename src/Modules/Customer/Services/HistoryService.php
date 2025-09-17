<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customer\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Modules\Customer\Repositories\CustomerRepositoryInterface;
use ClubifyCheckout\Modules\Customer\DTOs\HistoryData;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de histórico de clientes
 *
 * Gerencia o histórico completo de transações, comportamentos
 * e interações do cliente, incluindo análises preditivas,
 * segmentação comportamental e insights de negócio.
 */
class HistoryService extends BaseService
{
    private const CACHE_PREFIX = 'customer_history:';
    private const CACHE_TTL = 1800; // 30 minutos

    private array $metrics = [
        'histories_generated' => 0,
        'analyses_performed' => 0,
        'avg_processing_time' => 0.0,
        'cache_hit_ratio' => 0.0,
    ];

    public function __construct(
        private CustomerRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Gera histórico completo do cliente
     */
    public function generateHistory(string $customerId, array $options = []): HistoryData
    {
        return $this->withCache(
            self::CACHE_PREFIX . $customerId,
            function () use ($customerId, $options) {
                $startTime = microtime(true);

                try {
                    // Busca dados base do cliente
                    $customer = $this->repository->findById($customerId);
                    if (!$customer) {
                        throw new \InvalidArgumentException("Cliente não encontrado: {$customerId}");
                    }

                    // Coleta dados históricos
                    $transactionHistory = $this->getTransactionHistory($customerId, $options);
                    $behaviorHistory = $this->getBehaviorHistory($customerId, $options);
                    $interactionHistory = $this->getInteractionHistory($customerId, $options);

                    // Gera análises
                    $analytics = $this->generateAnalytics($customerId, $transactionHistory, $behaviorHistory);
                    $insights = $this->generateInsights($customerId, $analytics);
                    $predictions = $this->generatePredictions($customerId, $analytics);

                    // Cria histórico completo
                    $history = HistoryData::fromArray([
                        'customer_id' => $customerId,
                        'period_start' => $options['period_start'] ?? date('Y-m-d', strtotime('-1 year')),
                        'period_end' => $options['period_end'] ?? date('Y-m-d'),
                        'transaction_history' => $transactionHistory,
                        'behavior_history' => $behaviorHistory,
                        'interaction_history' => $interactionHistory,
                        'analytics' => $analytics,
                        'insights' => $insights,
                        'predictions' => $predictions,
                        'generated_at' => date('Y-m-d H:i:s'),
                    ]);

                    $this->updateMetrics('generateHistory', microtime(true) - $startTime, true);

                    $this->logger->info('Histórico de cliente gerado com sucesso', [
                        'customer_id' => $customerId,
                        'processing_time' => microtime(true) - $startTime,
                        'transactions_count' => count($transactionHistory),
                        'interactions_count' => count($interactionHistory),
                    ]);

                    return $history;

                } catch (\Exception $e) {
                    $this->updateMetrics('generateHistory', microtime(true) - $startTime, false);

                    $this->logger->error('Erro ao gerar histórico de cliente', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            },
            self::CACHE_TTL
        );
    }

    /**
     * Analisa padrões de compra
     */
    public function analyzePurchasePatterns(string $customerId, array $options = []): array
    {
        $history = $this->generateHistory($customerId, $options);

        return $this->executeWithMetrics('analyzePurchasePatterns', function () use ($history) {
            $transactions = $history->transactionHistory;

            if (empty($transactions)) {
                return [
                    'patterns' => [],
                    'frequency' => 'unknown',
                    'seasonality' => [],
                    'preferences' => [],
                ];
            }

            $patterns = [
                'frequency' => $this->calculatePurchaseFrequency($transactions),
                'seasonality' => $this->analyzeSeasonality($transactions),
                'timing' => $this->analyzeTimingPatterns($transactions),
                'amount_patterns' => $this->analyzeAmountPatterns($transactions),
                'product_preferences' => $this->analyzeProductPreferences($transactions),
                'payment_preferences' => $this->analyzePaymentPreferences($transactions),
                'channel_preferences' => $this->analyzeChannelPreferences($transactions),
            ];

            return $patterns;
        });
    }

    /**
     * Gera insights comportamentais
     */
    public function generateBehavioralInsights(string $customerId, array $options = []): array
    {
        $history = $this->generateHistory($customerId, $options);

        return $this->executeWithMetrics('generateBehavioralInsights', function () use ($history) {
            $insights = [];

            // Análise de fidelidade
            $insights['loyalty'] = $this->analyzeLoyalty($history);

            // Análise de valor do cliente
            $insights['value'] = $this->analyzeCustomerValue($history);

            // Análise de risco
            $insights['risk'] = $this->analyzeRisk($history);

            // Análise de oportunidades
            $insights['opportunities'] = $this->analyzeOpportunities($history);

            // Análise de engajamento
            $insights['engagement'] = $this->analyzeEngagement($history);

            return $insights;
        });
    }

    /**
     * Prediz comportamento futuro
     */
    public function predictFutureBehavior(string $customerId, array $options = []): array
    {
        $history = $this->generateHistory($customerId, $options);

        return $this->executeWithMetrics('predictFutureBehavior', function () use ($history) {
            $predictions = [];

            // Predição de próxima compra
            $predictions['next_purchase'] = $this->predictNextPurchase($history);

            // Predição de churn
            $predictions['churn_risk'] = $this->predictChurnRisk($history);

            // Predição de valor futuro
            $predictions['future_value'] = $this->predictFutureValue($history);

            // Recomendações de produtos
            $predictions['product_recommendations'] = $this->generateProductRecommendations($history);

            // Melhor canal de comunicação
            $predictions['preferred_channel'] = $this->predictPreferredChannel($history);

            return $predictions;
        });
    }

    /**
     * Compara históricos de clientes
     */
    public function compareCustomers(array $customerIds, array $options = []): array
    {
        return $this->executeWithMetrics('compareCustomers', function () use ($customerIds, $options) {
            $comparisons = [];

            foreach ($customerIds as $customerId) {
                $history = $this->generateHistory($customerId, $options);
                $comparisons[$customerId] = [
                    'summary' => $history->getSummary(),
                    'analytics' => $history->analytics,
                    'insights' => $history->insights,
                ];
            }

            return [
                'customers' => $comparisons,
                'comparative_analysis' => $this->generateComparativeAnalysis($comparisons),
                'rankings' => $this->generateCustomerRankings($comparisons),
            ];
        });
    }

    /**
     * Obtém histórico de transações
     */
    private function getTransactionHistory(string $customerId, array $options): array
    {
        // Em uma implementação real, buscaria do banco de dados
        // Por simplicidade, retornamos dados simulados
        return [
            [
                'id' => 'tx_001',
                'amount' => 299.99,
                'currency' => 'BRL',
                'status' => 'completed',
                'payment_method' => 'credit_card',
                'products' => ['prod_001', 'prod_002'],
                'channel' => 'website',
                'created_at' => '2024-01-15 10:30:00',
            ],
            [
                'id' => 'tx_002',
                'amount' => 149.90,
                'currency' => 'BRL',
                'status' => 'completed',
                'payment_method' => 'pix',
                'products' => ['prod_003'],
                'channel' => 'mobile_app',
                'created_at' => '2024-02-20 14:15:00',
            ],
        ];
    }

    /**
     * Obtém histórico de comportamento
     */
    private function getBehaviorHistory(string $customerId, array $options): array
    {
        return [
            [
                'type' => 'page_view',
                'page' => '/products/category/electronics',
                'duration' => 120,
                'timestamp' => '2024-01-15 10:25:00',
            ],
            [
                'type' => 'cart_abandonment',
                'cart_value' => 199.99,
                'products_count' => 2,
                'timestamp' => '2024-01-18 16:45:00',
            ],
        ];
    }

    /**
     * Obtém histórico de interações
     */
    private function getInteractionHistory(string $customerId, array $options): array
    {
        return [
            [
                'type' => 'email_open',
                'campaign' => 'weekly_newsletter',
                'timestamp' => '2024-01-16 09:00:00',
            ],
            [
                'type' => 'support_ticket',
                'subject' => 'Problema com entrega',
                'status' => 'resolved',
                'timestamp' => '2024-01-22 11:30:00',
            ],
        ];
    }

    /**
     * Gera analytics do cliente
     */
    private function generateAnalytics(string $customerId, array $transactions, array $behaviors): array
    {
        $totalValue = array_sum(array_column($transactions, 'amount'));
        $transactionCount = count($transactions);
        $avgOrderValue = $transactionCount > 0 ? $totalValue / $transactionCount : 0;

        return [
            'total_transactions' => $transactionCount,
            'total_value' => $totalValue,
            'average_order_value' => $avgOrderValue,
            'purchase_frequency' => $this->calculatePurchaseFrequency($transactions),
            'preferred_payment_method' => $this->getPreferredPaymentMethod($transactions),
            'preferred_channel' => $this->getPreferredChannel($transactions),
            'engagement_score' => $this->calculateEngagementScore($behaviors),
            'loyalty_score' => $this->calculateLoyaltyScore($transactions),
        ];
    }

    /**
     * Gera insights do cliente
     */
    private function generateInsights(string $customerId, array $analytics): array
    {
        $insights = [];

        // Classificação de valor
        if ($analytics['total_value'] > 1000) {
            $insights[] = 'Cliente de alto valor';
        } elseif ($analytics['total_value'] > 500) {
            $insights[] = 'Cliente de valor médio';
        } else {
            $insights[] = 'Cliente de baixo valor';
        }

        // Análise de frequência
        if ($analytics['purchase_frequency'] > 0.5) {
            $insights[] = 'Cliente frequente';
        } else {
            $insights[] = 'Cliente ocasional';
        }

        // Análise de engajamento
        if ($analytics['engagement_score'] > 7) {
            $insights[] = 'Altamente engajado';
        } elseif ($analytics['engagement_score'] > 4) {
            $insights[] = 'Moderadamente engajado';
        } else {
            $insights[] = 'Baixo engajamento';
        }

        return $insights;
    }

    /**
     * Gera predições para o cliente
     */
    private function generatePredictions(string $customerId, array $analytics): array
    {
        return [
            'next_purchase_probability' => min(1.0, $analytics['purchase_frequency'] * 0.8),
            'next_purchase_window' => '30-45 dias',
            'churn_risk' => $analytics['engagement_score'] < 3 ? 'high' : 'low',
            'recommended_products' => ['prod_004', 'prod_005'],
            'optimal_contact_time' => 'terça-feira 10h-12h',
        ];
    }

    /**
     * Calcula frequência de compra
     */
    private function calculatePurchaseFrequency(array $transactions): float
    {
        if (empty($transactions)) {
            return 0.0;
        }

        $dates = array_map(fn($tx) => strtotime($tx['created_at']), $transactions);
        $firstPurchase = min($dates);
        $lastPurchase = max($dates);
        $daysDiff = max(1, ($lastPurchase - $firstPurchase) / 86400);

        return count($transactions) / $daysDiff;
    }

    /**
     * Analisa sazonalidade
     */
    private function analyzeSeasonality(array $transactions): array
    {
        $monthlyData = [];

        foreach ($transactions as $transaction) {
            $month = date('n', strtotime($transaction['created_at']));
            $monthlyData[$month] = ($monthlyData[$month] ?? 0) + 1;
        }

        return $monthlyData;
    }

    /**
     * Analisa padrões de timing
     */
    private function analyzeTimingPatterns(array $transactions): array
    {
        $hourlyData = [];
        $weeklyData = [];

        foreach ($transactions as $transaction) {
            $hour = (int) date('G', strtotime($transaction['created_at']));
            $weekday = (int) date('N', strtotime($transaction['created_at']));

            $hourlyData[$hour] = ($hourlyData[$hour] ?? 0) + 1;
            $weeklyData[$weekday] = ($weeklyData[$weekday] ?? 0) + 1;
        }

        return [
            'preferred_hours' => $hourlyData,
            'preferred_weekdays' => $weeklyData,
        ];
    }

    /**
     * Analisa padrões de valor
     */
    private function analyzeAmountPatterns(array $transactions): array
    {
        $amounts = array_column($transactions, 'amount');

        if (empty($amounts)) {
            return [];
        }

        return [
            'min_amount' => min($amounts),
            'max_amount' => max($amounts),
            'avg_amount' => array_sum($amounts) / count($amounts),
            'median_amount' => $this->calculateMedian($amounts),
        ];
    }

    /**
     * Analisa preferências de produtos
     */
    private function analyzeProductPreferences(array $transactions): array
    {
        $productCounts = [];

        foreach ($transactions as $transaction) {
            foreach ($transaction['products'] as $product) {
                $productCounts[$product] = ($productCounts[$product] ?? 0) + 1;
            }
        }

        arsort($productCounts);

        return array_slice($productCounts, 0, 5, true);
    }

    /**
     * Analisa preferências de pagamento
     */
    private function analyzePaymentPreferences(array $transactions): array
    {
        $paymentCounts = [];

        foreach ($transactions as $transaction) {
            $method = $transaction['payment_method'];
            $paymentCounts[$method] = ($paymentCounts[$method] ?? 0) + 1;
        }

        arsort($paymentCounts);

        return $paymentCounts;
    }

    /**
     * Analisa preferências de canal
     */
    private function analyzeChannelPreferences(array $transactions): array
    {
        $channelCounts = [];

        foreach ($transactions as $transaction) {
            $channel = $transaction['channel'];
            $channelCounts[$channel] = ($channelCounts[$channel] ?? 0) + 1;
        }

        arsort($channelCounts);

        return $channelCounts;
    }

    /**
     * Obtém método de pagamento preferido
     */
    private function getPreferredPaymentMethod(array $transactions): string
    {
        $preferences = $this->analyzePaymentPreferences($transactions);
        return key($preferences) ?: 'unknown';
    }

    /**
     * Obtém canal preferido
     */
    private function getPreferredChannel(array $transactions): string
    {
        $preferences = $this->analyzeChannelPreferences($transactions);
        return key($preferences) ?: 'unknown';
    }

    /**
     * Calcula score de engajamento
     */
    private function calculateEngagementScore(array $behaviors): float
    {
        // Simula cálculo baseado em interações
        $score = count($behaviors) * 0.5;
        return min(10.0, $score);
    }

    /**
     * Calcula score de fidelidade
     */
    private function calculateLoyaltyScore(array $transactions): float
    {
        $transactionCount = count($transactions);
        $totalValue = array_sum(array_column($transactions, 'amount'));

        // Simula cálculo baseado em quantidade e valor
        $score = ($transactionCount * 0.3) + ($totalValue / 1000);
        return min(10.0, $score);
    }

    /**
     * Calcula mediana
     */
    private function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0.0;
        }

        $middle = floor($count / 2);

        if ($count % 2 === 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        }

        return (float) $values[$middle];
    }

    /**
     * Métodos de análise específicos (simplificados para o exemplo)
     */

    private function analyzeLoyalty(HistoryData $history): array
    {
        return [
            'score' => $history->analytics['loyalty_score'] ?? 0,
            'level' => 'medium',
            'factors' => ['purchase_frequency', 'engagement'],
        ];
    }

    private function analyzeCustomerValue(HistoryData $history): array
    {
        return [
            'current_value' => $history->analytics['total_value'] ?? 0,
            'potential_value' => 1500.00,
            'value_segment' => 'medium',
        ];
    }

    private function analyzeRisk(HistoryData $history): array
    {
        return [
            'churn_risk' => 'low',
            'fraud_risk' => 'low',
            'payment_risk' => 'low',
        ];
    }

    private function analyzeOpportunities(HistoryData $history): array
    {
        return [
            'upsell' => ['prod_006', 'prod_007'],
            'cross_sell' => ['prod_008'],
            'retention' => 'newsletter_subscription',
        ];
    }

    private function analyzeEngagement(HistoryData $history): array
    {
        return [
            'score' => $history->analytics['engagement_score'] ?? 0,
            'channels' => ['email', 'website'],
            'frequency' => 'weekly',
        ];
    }

    private function predictNextPurchase(HistoryData $history): array
    {
        return [
            'probability' => 0.75,
            'window' => '30-45 days',
            'expected_value' => 250.00,
        ];
    }

    private function predictChurnRisk(HistoryData $history): array
    {
        return [
            'risk_level' => 'low',
            'probability' => 0.15,
            'indicators' => [],
        ];
    }

    private function predictFutureValue(HistoryData $history): array
    {
        return [
            'next_month' => 300.00,
            'next_quarter' => 800.00,
            'next_year' => 2400.00,
        ];
    }

    private function generateProductRecommendations(HistoryData $history): array
    {
        return [
            ['id' => 'prod_009', 'score' => 0.9, 'reason' => 'similar_purchases'],
            ['id' => 'prod_010', 'score' => 0.8, 'reason' => 'complementary'],
        ];
    }

    private function predictPreferredChannel(HistoryData $history): array
    {
        return [
            'channel' => 'email',
            'confidence' => 0.85,
            'best_time' => '10:00-12:00',
        ];
    }

    private function generateComparativeAnalysis(array $comparisons): array
    {
        return [
            'top_performer' => 'customer_001',
            'avg_order_value' => 225.50,
            'common_patterns' => ['mobile_preference'],
        ];
    }

    private function generateCustomerRankings(array $comparisons): array
    {
        return [
            'by_value' => ['customer_001', 'customer_002'],
            'by_frequency' => ['customer_002', 'customer_001'],
            'by_engagement' => ['customer_001', 'customer_002'],
        ];
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}