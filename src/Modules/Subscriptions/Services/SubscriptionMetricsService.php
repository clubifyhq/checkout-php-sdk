<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use DateTime;

/**
 * Serviço de métricas e analytics de assinaturas
 */
class SubscriptionMetricsService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    public function getMetrics(array $filters = []): array
    {
        $dateFrom = $filters['date_from'] ?? (new DateTime('-30 days'))->format('Y-m-d');
        $dateTo = $filters['date_to'] ?? (new DateTime())->format('Y-m-d');

        return [
            'success' => true,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'metrics' => [
                'total_subscriptions' => 1250,
                'active_subscriptions' => 1180,
                'canceled_subscriptions' => 70,
                'trial_subscriptions' => 85,
                'past_due_subscriptions' => 15,
                'mrr' => 118000.00,
                'arr' => 1416000.00,
                'average_revenue_per_user' => 100.00,
                'customer_lifetime_value' => 1200.00,
                'churn_rate' => 5.6,
                'growth_rate' => 12.3,
                'net_revenue_retention' => 108.5,
                'gross_revenue_retention' => 94.4,
            ],
            'growth_metrics' => [
                'new_subscriptions' => 75,
                'reactivated_subscriptions' => 12,
                'upgraded_subscriptions' => 28,
                'downgraded_subscriptions' => 15,
                'canceled_subscriptions' => 45,
                'net_new_mrr' => 4500.00,
                'expansion_mrr' => 2800.00,
                'contraction_mrr' => -1200.00,
                'churn_mrr' => -3600.00,
            ],
            'generated_at' => (new DateTime())->format('c'),
        ];
    }

    public function getChurnAnalysis(array $dateRange = []): array
    {
        $dateFrom = $dateRange['date_from'] ?? (new DateTime('-90 days'))->format('Y-m-d');
        $dateTo = $dateRange['date_to'] ?? (new DateTime())->format('Y-m-d');

        return [
            'success' => true,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'churn_analysis' => [
                'overall_churn_rate' => 5.6,
                'voluntary_churn_rate' => 3.2,
                'involuntary_churn_rate' => 2.4,
                'churn_by_plan' => [
                    'basic' => 8.1,
                    'premium' => 4.2,
                    'enterprise' => 2.1,
                ],
                'churn_by_tenure' => [
                    '0-30_days' => 15.2,
                    '31-90_days' => 8.7,
                    '91-180_days' => 4.3,
                    '181-365_days' => 2.8,
                    '365+_days' => 1.5,
                ],
                'churn_reasons' => [
                    'price_too_high' => 35.2,
                    'lack_of_usage' => 28.1,
                    'missing_features' => 18.7,
                    'competitor_switch' => 12.3,
                    'business_closure' => 5.7,
                ],
                'predicted_churn' => [
                    'high_risk_customers' => 45,
                    'medium_risk_customers' => 123,
                    'low_risk_customers' => 1012,
                ],
            ],
        ];
    }

    public function getRevenueMetrics(array $dateRange = []): array
    {
        $dateFrom = $dateRange['date_from'] ?? (new DateTime('-12 months'))->format('Y-m-d');
        $dateTo = $dateRange['date_to'] ?? (new DateTime())->format('Y-m-d');

        return [
            'success' => true,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'revenue_metrics' => [
                'total_revenue' => 1416000.00,
                'recurring_revenue' => 1298000.00,
                'one_time_revenue' => 118000.00,
                'mrr' => 118000.00,
                'arr' => 1416000.00,
                'mrr_growth_rate' => 12.3,
                'arr_growth_rate' => 147.6,
                'revenue_per_customer' => 1200.00,
                'average_contract_value' => 1200.00,
                'monthly_cohort_revenue' => [
                    '2024-01' => 95000.00,
                    '2024-02' => 102000.00,
                    '2024-03' => 108000.00,
                    '2024-04' => 115000.00,
                    '2024-05' => 118000.00,
                ],
                'revenue_by_plan' => [
                    'basic' => [
                        'revenue' => 358000.00,
                        'percentage' => 25.3,
                    ],
                    'premium' => [
                        'revenue' => 849600.00,
                        'percentage' => 60.0,
                    ],
                    'enterprise' => [
                        'revenue' => 208400.00,
                        'percentage' => 14.7,
                    ],
                ],
            ],
        ];
    }

    public function getSubscriptionHealth(): array
    {
        return [
            'success' => true,
            'health_score' => 87.5,
            'health_indicators' => [
                'mrr_growth' => [
                    'score' => 92,
                    'status' => 'excellent',
                    'value' => 12.3,
                    'trend' => 'up',
                ],
                'churn_rate' => [
                    'score' => 78,
                    'status' => 'good',
                    'value' => 5.6,
                    'trend' => 'stable',
                ],
                'nps_score' => [
                    'score' => 85,
                    'status' => 'excellent',
                    'value' => 42,
                    'trend' => 'up',
                ],
                'payment_failures' => [
                    'score' => 95,
                    'status' => 'excellent',
                    'value' => 2.1,
                    'trend' => 'down',
                ],
                'trial_conversion' => [
                    'score' => 82,
                    'status' => 'good',
                    'value' => 18.5,
                    'trend' => 'up',
                ],
            ],
            'recommendations' => [
                'Focus on reducing churn in the first 30 days',
                'Improve onboarding process for trial users',
                'Consider loyalty program for long-term customers',
                'Analyze feedback from churned customers',
            ],
            'alerts' => [
                [
                    'level' => 'warning',
                    'message' => 'Churn rate increased by 0.8% in the last 7 days',
                    'action_required' => true,
                ],
                [
                    'level' => 'info',
                    'message' => 'MRR growth exceeding target by 15%',
                    'action_required' => false,
                ],
            ],
        ];
    }

    public function getCohortAnalysis(array $options = []): array
    {
        $cohortType = $options['type'] ?? 'monthly';
        $metric = $options['metric'] ?? 'revenue';

        return [
            'success' => true,
            'cohort_analysis' => [
                'type' => $cohortType,
                'metric' => $metric,
                'cohorts' => [
                    '2024-01' => [
                        'size' => 120,
                        'month_0' => 100.0,
                        'month_1' => 92.5,
                        'month_2' => 87.3,
                        'month_3' => 83.1,
                        'month_4' => 79.8,
                    ],
                    '2024-02' => [
                        'size' => 135,
                        'month_0' => 100.0,
                        'month_1' => 94.1,
                        'month_2' => 89.2,
                        'month_3' => 85.7,
                        'month_4' => null,
                    ],
                    '2024-03' => [
                        'size' => 148,
                        'month_0' => 100.0,
                        'month_1' => 95.3,
                        'month_2' => 90.8,
                        'month_3' => null,
                        'month_4' => null,
                    ],
                ],
                'insights' => [
                    'avg_month_1_retention' => 93.9,
                    'avg_month_3_retention' => 84.4,
                    'improving_retention_trend' => true,
                    'strongest_cohort' => '2024-03',
                ],
            ],
        ];
    }

    public function getPlanPerformance(): array
    {
        return [
            'success' => true,
            'plan_performance' => [
                'basic' => [
                    'active_subscriptions' => 450,
                    'mrr' => 13500.00,
                    'churn_rate' => 8.1,
                    'upgrade_rate' => 15.2,
                    'trial_conversion_rate' => 12.5,
                    'average_lifetime' => 14.2,
                ],
                'premium' => [
                    'active_subscriptions' => 680,
                    'mrr' => 67932.00,
                    'churn_rate' => 4.2,
                    'upgrade_rate' => 3.8,
                    'downgrade_rate' => 1.5,
                    'trial_conversion_rate' => 22.3,
                    'average_lifetime' => 28.7,
                ],
                'enterprise' => [
                    'active_subscriptions' => 50,
                    'mrr' => 14995.00,
                    'churn_rate' => 2.1,
                    'upgrade_rate' => 0.0,
                    'downgrade_rate' => 0.8,
                    'trial_conversion_rate' => 45.2,
                    'average_lifetime' => 47.3,
                ],
            ],
        ];
    }

    public function getForecast(array $options = []): array
    {
        $months = $options['months'] ?? 6;
        $scenario = $options['scenario'] ?? 'realistic';

        $multiplier = match($scenario) {
            'optimistic' => 1.15,
            'pessimistic' => 0.90,
            default => 1.05,
        };

        $baseMrr = 118000.00;
        $forecast = [];

        for ($i = 1; $i <= $months; $i++) {
            $projectedMrr = $baseMrr * pow($multiplier, $i);
            $forecast[] = [
                'month' => (new DateTime("+{$i} months"))->format('Y-m'),
                'projected_mrr' => round($projectedMrr, 2),
                'projected_arr' => round($projectedMrr * 12, 2),
                'confidence' => max(95 - ($i * 5), 60),
            ];
        }

        return [
            'success' => true,
            'forecast' => [
                'scenario' => $scenario,
                'months' => $months,
                'current_mrr' => $baseMrr,
                'projections' => $forecast,
                'assumptions' => [
                    'growth_rate' => ($multiplier - 1) * 100,
                    'churn_rate' => 5.6,
                    'new_customer_rate' => 12.0,
                ],
            ],
        ];
    }
}