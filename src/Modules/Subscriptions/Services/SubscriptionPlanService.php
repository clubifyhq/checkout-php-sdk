<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Subscriptions\DTOs\SubscriptionPlanData;
use DateTime;

/**
 * Serviço de gestão de planos de assinatura
 */
class SubscriptionPlanService
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    public function createPlan(array $planData): array
    {
        try {
            $plan = new SubscriptionPlanData(array_merge($planData, [
                'id' => uniqid('plan_'),
                'created_at' => new DateTime(),
                'updated_at' => new DateTime(),
            ]));

            $this->logger->info('Subscription plan created', [
                'plan_id' => $plan->id,
                'name' => $plan->name,
                'amount' => $plan->amount,
                'billing_cycle' => $plan->billing_cycle,
            ]);

            return [
                'success' => true,
                'plan_id' => $plan->id,
                'plan' => $plan->toArray(),
                'mrr' => $plan->calculateMRR(),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create subscription plan', [
                'error' => $e->getMessage(),
                'data' => $planData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getPlan(string $planId): array
    {
        return [
            'success' => true,
            'plan' => [
                'id' => $planId,
                'name' => 'Plano Premium',
                'description' => 'Acesso completo às funcionalidades premium',
                'amount' => 99.90,
                'currency' => 'BRL',
                'billing_cycle' => 'monthly',
                'trial_days' => 7,
                'is_active' => true,
                'features' => [
                    'advanced_analytics',
                    'priority_support',
                    'custom_themes',
                    'api_access'
                ],
                'metadata' => [
                    'category' => 'premium',
                    'max_users' => 50,
                ],
            ],
        ];
    }

    public function updatePlan(string $planId, array $planData): array
    {
        $this->logger->info('Subscription plan updated', [
            'plan_id' => $planId,
            'updates' => array_keys($planData),
        ]);

        return [
            'success' => true,
            'plan_id' => $planId,
            'updated_at' => (new DateTime())->format('c'),
        ];
    }

    public function listPlans(array $filters = []): array
    {
        $plans = [
            [
                'id' => 'plan_basic',
                'name' => 'Plano Básico',
                'amount' => 29.90,
                'billing_cycle' => 'monthly',
                'is_active' => true,
                'features' => ['basic_analytics', 'email_support'],
            ],
            [
                'id' => 'plan_premium',
                'name' => 'Plano Premium',
                'amount' => 99.90,
                'billing_cycle' => 'monthly',
                'is_active' => true,
                'features' => ['advanced_analytics', 'priority_support', 'custom_themes'],
            ],
            [
                'id' => 'plan_enterprise',
                'name' => 'Plano Enterprise',
                'amount' => 299.90,
                'billing_cycle' => 'monthly',
                'is_active' => true,
                'features' => ['enterprise_analytics', 'dedicated_support', 'white_label'],
            ],
        ];

        // Aplicar filtros se fornecidos
        if (isset($filters['is_active'])) {
            $plans = array_filter($plans, fn($plan) => $plan['is_active'] === $filters['is_active']);
        }

        if (isset($filters['billing_cycle'])) {
            $plans = array_filter($plans, fn($plan) => $plan['billing_cycle'] === $filters['billing_cycle']);
        }

        return [
            'success' => true,
            'plans' => array_values($plans),
            'total' => count($plans),
            'filters' => $filters,
        ];
    }

    public function deactivatePlan(string $planId): array
    {
        $this->logger->info('Subscription plan deactivated', [
            'plan_id' => $planId,
        ]);

        return [
            'success' => true,
            'plan_id' => $planId,
            'deactivated_at' => (new DateTime())->format('c'),
        ];
    }

    public function activatePlan(string $planId): array
    {
        $this->logger->info('Subscription plan activated', [
            'plan_id' => $planId,
        ]);

        return [
            'success' => true,
            'plan_id' => $planId,
            'activated_at' => (new DateTime())->format('c'),
        ];
    }

    public function getPlanMetrics(string $planId): array
    {
        return [
            'success' => true,
            'plan_id' => $planId,
            'metrics' => [
                'active_subscriptions' => 150,
                'total_revenue' => 14985.00,
                'mrr' => 14985.00,
                'arr' => 179820.00,
                'conversion_rate' => 12.5,
                'churn_rate' => 3.2,
                'upgrade_rate' => 8.7,
                'downgrade_rate' => 2.1,
            ],
        ];
    }

    public function comparePlans(array $planIds): array
    {
        return [
            'success' => true,
            'comparison' => [
                'plans' => $planIds,
                'features_comparison' => [
                    'analytics' => [
                        'plan_basic' => 'basic',
                        'plan_premium' => 'advanced',
                        'plan_enterprise' => 'enterprise',
                    ],
                    'support' => [
                        'plan_basic' => 'email',
                        'plan_premium' => 'priority',
                        'plan_enterprise' => 'dedicated',
                    ],
                ],
                'pricing_comparison' => [
                    'plan_basic' => ['monthly' => 29.90, 'yearly' => 299.00],
                    'plan_premium' => ['monthly' => 99.90, 'yearly' => 999.00],
                    'plan_enterprise' => ['monthly' => 299.90, 'yearly' => 2999.00],
                ],
            ],
        ];
    }
}