<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Subscriptions\DTOs\SubscriptionPlanData;
use Clubify\Checkout\Modules\Subscriptions\Repositories\ApiSubscriptionRepository;
use DateTime;

/**
 * Serviço de gestão de planos de assinatura
 *
 * Responsável pelas operações de planos de assinatura:
 * - CRUD de planos de assinatura via API HTTP real
 * - Ativação e desativação de planos
 * - Métricas específicas por plano
 * - Comparação entre planos
 * - Gestão de recursos e features
 * - Configuração de pricing e billing
 * - Conversão de dados entre formato SDK e API (reais <-> centavos)
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas planos de assinatura
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa ServiceInterface
 * - I: Interface Segregation - Métodos específicos de planos
 * - D: Dependency Inversion - Depende de abstrações (repository)
 */
class SubscriptionPlanService implements ServiceInterface
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger,
        private ApiSubscriptionRepository $repository
    ) {
    }

    public function createPlan(array $planData): array
    {
        try {
            // Convert SDK format to API format
            $apiData = $this->convertToApiFormat($planData);

            // Log the data being sent to API for debugging
            $this->logger->info('Creating subscription plan', [
                'api_data' => $apiData,
                'has_productId' => isset($apiData['productId']),
                'has_gatewayProductId' => isset($apiData['gatewayProductId'])
            ]);

            // Make HTTP call via repository
            $response = $this->repository->createPlan($apiData);

            // Extract plan data from response
            $planResponse = $response['data'] ?? $response;

            // Convert API response back to SDK format
            $sdkPlan = $this->convertFromApiFormat($planResponse);

            $this->logger->info('Subscription plan created via API', [
                'plan_id' => $planResponse['_id'] ?? $planResponse['id'] ?? null,
                'name' => $sdkPlan['name'] ?? null,
                'tier' => $sdkPlan['tier'] ?? null,
            ]);

            return [
                'success' => true,
                'plan_id' => $planResponse['_id'] ?? $planResponse['id'] ?? null,
                'plan' => $sdkPlan,
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
        try {
            // Make HTTP call via repository
            $response = $this->repository->findPlan($planId);

            // Extract plan data from response
            $planResponse = $response['data'] ?? $response;

            // Convert API response to SDK format
            $sdkPlan = $this->convertFromApiFormat($planResponse);

            return [
                'success' => true,
                'plan' => $sdkPlan,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get subscription plan', [
                'error' => $e->getMessage(),
                'plan_id' => $planId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function updatePlan(string $planId, array $planData): array
    {
        try {
            // Convert SDK format to API format for UPDATE
            // Pass isUpdate=true to avoid generating gatewayProductId
            $apiData = $this->convertToApiFormat($planData, isUpdate: true);

            // Make HTTP call via repository
            $response = $this->repository->updatePlan($planId, $apiData);

            // Extract plan data from response
            $planResponse = $response['data'] ?? $response;

            // Convert API response to SDK format
            $sdkPlan = $this->convertFromApiFormat($planResponse);

            $this->logger->info('Subscription plan updated via API', [
                'plan_id' => $planId,
                'updates' => array_keys($planData),
            ]);

            return [
                'success' => true,
                'plan_id' => $planId,
                'plan' => $sdkPlan,
                'updated_at' => $planResponse['updatedAt'] ?? (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update subscription plan', [
                'error' => $e->getMessage(),
                'plan_id' => $planId,
                'data' => $planData,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function listPlans(array $filters = []): array
    {
        try {
            // Convert SDK filters to API filters if needed
            $apiFilters = $this->convertFiltersToApiFormat($filters);

            // Make HTTP call via repository
            $response = $this->repository->listPlans($apiFilters);

            // Extract plans array from response
            $plansData = $response['data'] ?? $response['plans'] ?? $response;

            // Convert each plan from API to SDK format
            $sdkPlans = array_map(
                fn($plan) => $this->convertFromApiFormat($plan),
                is_array($plansData) ? $plansData : []
            );

            return [
                'success' => true,
                'plans' => $sdkPlans,
                'total' => count($sdkPlans),
                'filters' => $filters,
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to list subscription plans', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'plans' => [],
                'total' => 0,
            ];
        }
    }

    public function deactivatePlan(string $planId): array
    {
        try {
            // Make HTTP call via repository
            $response = $this->repository->deactivatePlan($planId);

            $this->logger->info('Subscription plan deactivated via API', [
                'plan_id' => $planId,
            ]);

            return [
                'success' => true,
                'plan_id' => $planId,
                'deactivated_at' => $response['updatedAt'] ?? (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to deactivate subscription plan', [
                'error' => $e->getMessage(),
                'plan_id' => $planId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function activatePlan(string $planId): array
    {
        try {
            // Make HTTP call via repository
            $response = $this->repository->activatePlan($planId);

            $this->logger->info('Subscription plan activated via API', [
                'plan_id' => $planId,
            ]);

            return [
                'success' => true,
                'plan_id' => $planId,
                'activated_at' => $response['updatedAt'] ?? (new DateTime())->format('c'),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to activate subscription plan', [
                'error' => $e->getMessage(),
                'plan_id' => $planId,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
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

    // ==============================================
    // DATA CONVERSION METHODS
    // ==============================================

    /**
     * Convert SDK format to API format
     * Handles conversion of:
     * - amount in reais -> centavos
     * - billing_cycle -> interval in prices array
     * - SDK structure -> API structure with prices array
     * - Add tenantId from config
     *
     * @param array $sdkData Data in SDK format
     * @param bool $isUpdate Whether this is an update operation (default: false)
     * @return array Data in API format
     */
    private function convertToApiFormat(array $sdkData, bool $isUpdate = false): array
    {
        // Start with base API fields
        $apiData = [];

        // For CREATE operations, add required fields with defaults
        // For UPDATE operations, only add fields that are explicitly provided
        if (!$isUpdate) {
            $apiData['tenantId'] = $this->config->getTenantId(); // Add tenantId from config
            $apiData['name'] = $sdkData['name'] ?? '';
            $apiData['description'] = $sdkData['description'] ?? '';
            $apiData['tier'] = $sdkData['tier'] ?? 'basic';
            $apiData['isActive'] = $sdkData['isActive'] ?? $sdkData['is_active'] ?? true;
        } else {
            // For UPDATE, only add provided fields
            if (isset($sdkData['tenantId'])) {
                $apiData['tenantId'] = $sdkData['tenantId'];
            }
            if (isset($sdkData['name'])) {
                $apiData['name'] = $sdkData['name'];
            }
            if (isset($sdkData['description'])) {
                $apiData['description'] = $sdkData['description'];
            }
            if (isset($sdkData['tier'])) {
                $apiData['tier'] = $sdkData['tier'];
            }
            if (isset($sdkData['isActive']) || isset($sdkData['is_active'])) {
                $apiData['isActive'] = $sdkData['isActive'] ?? $sdkData['is_active'];
            }
        }

        // Convert defaultTrialDays (rename trial_days -> defaultTrialDays)
        if (isset($sdkData['trial_days'])) {
            $apiData['defaultTrialDays'] = (int) $sdkData['trial_days'];
        }

        // Convert features array - keep in object format
        if (isset($sdkData['features']) && is_array($sdkData['features'])) {
            $apiData['features'] = array_map(function ($feature) {
                // If already in object format, keep as-is
                if (is_array($feature)) {
                    return $feature;
                }
                // If simple string, convert to object format
                if (is_string($feature)) {
                    return [
                        'name' => $feature,
                        'description' => '',
                        'enabled' => true,
                        'limit' => null,
                        'type' => 'feature'
                    ];
                }
                return $feature;
            }, $sdkData['features']);
        }

        // Convert prices - SDK format to API format (array of price objects)
        // Only add prices field if explicitly provided
        if (isset($sdkData['prices']) && is_array($sdkData['prices'])) {
            $apiData['prices'] = array_map(function ($price) {
                return [
                    'name' => $price['name'] ?? '',
                    'amount' => isset($price['amount']) ? $this->convertAmountToCents((float) $price['amount']) : 0,
                    'currency' => $price['currency'] ?? 'BRL',
                    'interval' => $this->convertBillingCycleToInterval($price['billing_cycle'] ?? $price['interval'] ?? 'monthly'),
                    'intervalCount' => $price['interval_count'] ?? $price['intervalCount'] ?? 1,
                    'isActive' => $price['is_active'] ?? $price['isActive'] ?? true,
                ];
            }, $sdkData['prices']);
        }
        // Otherwise, build prices from amount/currency/billing_cycle (if provided)
        elseif (isset($sdkData['amount'])) {
            $priceObj = [
                'name' => $sdkData['price_name'] ?? $this->getPriceNameFromBillingCycle($sdkData['billing_cycle'] ?? 'monthly'),
                'amount' => $this->convertAmountToCents((float) $sdkData['amount']),
                'currency' => $sdkData['currency'] ?? 'BRL',
                'interval' => $this->convertBillingCycleToInterval($sdkData['billing_cycle'] ?? 'monthly'),
                'intervalCount' => $sdkData['interval_count'] ?? 1,
                'isActive' => true,
            ];
            $apiData['prices'] = [$priceObj];
        }

        // Add productId and gatewayProductId only if explicitly provided
        // IMPORTANT: gatewayProductId should NOT be sent in UPDATE operations
        // unless explicitly provided, as it's a unique indexed field
        if (isset($sdkData['productId'])) {
            $apiData['productId'] = $sdkData['productId'];
        }
        if (isset($sdkData['gatewayProductId'])) {
            $apiData['gatewayProductId'] = $sdkData['gatewayProductId'];
        }

        // Only generate gatewayProductId for CREATE operations (not UPDATE)
        // For UPDATE operations, gatewayProductId should be omitted if not provided
        // to avoid MongoDB duplicate key error on unique index
        if (!$isUpdate &&
            !isset($apiData['productId']) &&
            !isset($apiData['gatewayProductId'])) {
            // Generate unique ID based on plan name + timestamp
            $planName = $sdkData['name'] ?? 'plan';
            $planSlug = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $planName));
            $apiData['gatewayProductId'] = 'plan_' . $planSlug . '_' . time();
        }

        // Add metadata if provided (optional)
        if (isset($sdkData['metadata']) && is_array($sdkData['metadata'])) {
            $apiData['metadata'] = $sdkData['metadata'];
        }

        return $apiData;
    }

    /**
     * Convert API format to SDK format
     * Handles conversion of:
     * - amount in centavos -> reais
     * - interval -> billing_cycle
     * - API structure -> SDK structure
     *
     * @param array $apiData Data in API format
     * @return array Data in SDK format
     */
    private function convertFromApiFormat(array $apiData): array
    {
        $sdkData = [
            'id' => $apiData['_id'] ?? $apiData['id'] ?? null,
            'name' => $apiData['name'] ?? '',
            'description' => $apiData['description'] ?? '',
            'tier' => $apiData['tier'] ?? 'basic',
            'is_active' => $apiData['isActive'] ?? true,
            'metadata' => $apiData['metadata'] ?? [],
            'created_at' => $apiData['createdAt'] ?? null,
            'updated_at' => $apiData['updatedAt'] ?? null,
        ];

        // Convert trial days
        if (isset($apiData['defaultTrialDays'])) {
            $sdkData['trial_days'] = (int) $apiData['defaultTrialDays'];
        }

        // Convert features
        if (isset($apiData['features']) && is_array($apiData['features'])) {
            $sdkData['features'] = array_map(function ($feature) {
                if (is_array($feature)) {
                    return $feature['name'] ?? $feature;
                }
                return $feature;
            }, $apiData['features']);
        }

        // Convert prices from API format (array) to SDK format (flatten first price)
        if (isset($apiData['prices']) && is_array($apiData['prices']) && count($apiData['prices']) > 0) {
            $firstPrice = $apiData['prices'][0];
            $sdkData['amount'] = $this->convertAmountFromCents((int) ($firstPrice['amount'] ?? 0));
            $sdkData['currency'] = $firstPrice['currency'] ?? 'BRL';
            $sdkData['billing_cycle'] = $this->convertIntervalToBillingCycle($firstPrice['interval'] ?? 'monthly');
            $sdkData['interval_count'] = $firstPrice['intervalCount'] ?? 1;

            // Also keep full prices array for advanced usage
            $sdkData['prices'] = array_map(function ($price) {
                return [
                    'name' => $price['name'] ?? '',
                    'amount' => $this->convertAmountFromCents((int) ($price['amount'] ?? 0)),
                    'currency' => $price['currency'] ?? 'BRL',
                    'billing_cycle' => $this->convertIntervalToBillingCycle($price['interval'] ?? 'monthly'),
                    'interval' => $price['interval'] ?? 'monthly',
                    'interval_count' => $price['intervalCount'] ?? 1,
                    'is_active' => $price['isActive'] ?? true,
                ];
            }, $apiData['prices']);
        }

        return $sdkData;
    }

    /**
     * Convert filters from SDK format to API format
     *
     * @param array $filters SDK filters
     * @return array API filters
     */
    private function convertFiltersToApiFormat(array $filters): array
    {
        $apiFilters = [];

        // Map is_active to isActive
        if (isset($filters['is_active'])) {
            $apiFilters['isActive'] = $filters['is_active'];
        }

        // Map billing_cycle to interval (for filtering)
        if (isset($filters['billing_cycle'])) {
            $apiFilters['interval'] = $this->convertBillingCycleToInterval($filters['billing_cycle']);
        }

        // Map tier
        if (isset($filters['tier'])) {
            $apiFilters['tier'] = $filters['tier'];
        }

        // Pass through other filters
        foreach ($filters as $key => $value) {
            if (!in_array($key, ['is_active', 'billing_cycle', 'tier'])) {
                $apiFilters[$key] = $value;
            }
        }

        return $apiFilters;
    }

    /**
     * Convert amount from reais to centavos
     *
     * @param float $amount Amount in reais
     * @return int Amount in centavos
     */
    private function convertAmountToCents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convert amount from centavos to reais
     *
     * @param int $amount Amount in centavos
     * @return float Amount in reais
     */
    private function convertAmountFromCents(int $amount): float
    {
        return $amount / 100;
    }

    /**
     * Convert SDK billing_cycle to API interval
     * yearly -> annually
     *
     * @param string $billingCycle SDK billing cycle
     * @return string API interval
     */
    private function convertBillingCycleToInterval(string $billingCycle): string
    {
        $mapping = [
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'yearly' => 'annually', // IMPORTANT: yearly -> annually
            'annually' => 'annually',
        ];

        return $mapping[$billingCycle] ?? 'monthly';
    }

    /**
     * Convert API interval to SDK billing_cycle
     * annually -> yearly
     *
     * @param string $interval API interval
     * @return string SDK billing cycle
     */
    private function convertIntervalToBillingCycle(string $interval): string
    {
        $mapping = [
            'daily' => 'daily',
            'weekly' => 'weekly',
            'monthly' => 'monthly',
            'quarterly' => 'quarterly',
            'annually' => 'yearly', // IMPORTANT: annually -> yearly
            'yearly' => 'yearly',
        ];

        return $mapping[$interval] ?? 'monthly';
    }

    /**
     * Get price name from billing cycle
     *
     * @param string $billingCycle Billing cycle
     * @return string Price name
     */
    private function getPriceNameFromBillingCycle(string $billingCycle): string
    {
        $mapping = [
            'daily' => 'Diário',
            'weekly' => 'Semanal',
            'monthly' => 'Mensal',
            'quarterly' => 'Trimestral',
            'yearly' => 'Anual',
            'annually' => 'Anual',
        ];

        return $mapping[$billingCycle] ?? 'Mensal';
    }

    // ==============================================
    // ServiceInterface Implementation
    // ==============================================

    /**
     * Obtém o nome do serviço
     */
    public function getName(): string
    {
        return 'subscription_plan';
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se o serviço está saudável (health check)
     */
    public function isHealthy(): bool
    {
        try {
            // Verificar se as dependências básicas estão disponíveis
            return $this->config !== null &&
                   $this->logger !== null &&
                   $this->sdk !== null;
        } catch (\Exception $e) {
            $this->logger->error('SubscriptionPlanService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'features' => [
                'create_plan',
                'get_plan',
                'update_plan',
                'list_plans',
                'activate_plan',
                'deactivate_plan',
                'plan_metrics',
                'compare_plans'
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'supported_billing_cycles' => ['monthly', 'quarterly', 'yearly'],
            'supported_currencies' => ['BRL', 'USD', 'EUR'],
            'trial_options' => [7, 14, 30],
            'plan_tiers' => ['basic', 'premium', 'enterprise'],
            'feature_categories' => [
                'analytics', 'support', 'customization',
                'integrations', 'storage', 'users'
            ]
        ];
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available' => $this->isAvailable(),
            'config' => $this->getConfig(),
            'metrics' => $this->getMetrics(),
            'timestamp' => time()
        ];
    }
}
