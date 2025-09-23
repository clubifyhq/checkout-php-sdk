<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de planos de assinatura
 *
 * Responsável pela gestão completa de planos de assinatura nas ofertas:
 * - CRUD de planos de assinatura
 * - Configuração de intervalos e preços
 * - Gestão de trials e descontos
 * - Upgrades e downgrades
 * - Análise de churn e retenção
 * - Métricas de assinatura
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas planos de assinatura
 * - O: Open/Closed - Extensível via tipos de plano
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de assinatura
 * - D: Dependency Inversion - Depende de abstrações
 */
class SubscriptionPlanService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'subscription_plan';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo plano de assinatura
     */
    public function create(array $planData): array
    {
        return $this->executeWithMetrics('create_subscription_plan', function () use ($planData) {
            $this->validatePlanData($planData);

            // Preparar dados do plano
            $data = array_merge($planData, [
                'status' => $planData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildPlanConfiguration($planData),
                'analytics' => $this->initializePlanAnalytics()
            ]);

            // Criar plano via API
            $response = $this->httpClient->post('/subscription-plans', $data);
            $plan = ResponseHelper::getData($response);

            // Cache do plano
            $this->cache->set($this->getCacheKey("plan:{$plan['id']}"), $plan, 3600);

            // Dispatch evento
            $this->dispatch('subscription_plan.created', [
                'plan_id' => $plan['id'],
                'offer_id' => $plan['offer_id'] ?? null,
                'name' => $plan['name'],
                'price' => $plan['price'],
                'interval' => $plan['interval']
            ]);

            $this->logger->info('Subscription plan created successfully', [
                'plan_id' => $plan['id'],
                'name' => $plan['name'],
                'price' => $plan['price']
            ]);

            return $plan;
        });
    }

    /**
     * Obtém um plano por ID
     */
    public function get(string $planId): ?array
    {
        return $this->getCachedOrExecute(
            "plan:{$planId}",
            fn () => $this->fetchPlanById($planId),
            3600
        );
    }

    /**
     * Obtém planos por oferta
     */
    public function getByOffer(string $offerId): array
    {
        return $this->executeWithMetrics('get_plans_by_offer', function () use ($offerId) {
            $response = $this->httpClient->get("/offers/{$offerId}/subscription/plans");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista planos com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_subscription_plans', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/subscription-plans', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Atualiza plano
     */
    public function update(string $planId, array $data): array
    {
        return $this->executeWithMetrics('update_subscription_plan', function () use ($planId, $data) {
            $this->validatePlanUpdateData($data);

            // Verificar se plano existe
            $currentPlan = $this->get($planId);
            if (!$currentPlan) {
                throw new ValidationException("Subscription plan not found: {$planId}");
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/subscription-plans/{$planId}", $data);
            $plan = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidatePlanCache($planId);

            // Dispatch evento
            $this->dispatch('subscription_plan.updated', [
                'plan_id' => $planId,
                'updated_fields' => array_keys($data)
            ]);

            return $plan;
        });
    }

    /**
     * Exclui plano
     */
    public function delete(string $planId): bool
    {
        return $this->executeWithMetrics('delete_subscription_plan', function () use ($planId) {
            try {
                $response = $this->httpClient->delete("/subscription-plans/{$planId}");

                // Invalidar cache
                $this->invalidatePlanCache($planId);

                // Dispatch evento
                $this->dispatch('subscription_plan.deleted', [
                    'plan_id' => $planId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete subscription plan', [
                    'plan_id' => $planId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Configura trial do plano
     */
    public function configureTrial(string $planId, array $trialConfig): array
    {
        return $this->executeWithMetrics('configure_plan_trial', function () use ($planId, $trialConfig) {
            $this->validateTrialConfig($trialConfig);

            $response = $this->httpClient->put("/subscription-plans/{$planId}/trial", [
                'trial' => $trialConfig
            ]);

            $plan = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidatePlanCache($planId);

            // Dispatch evento
            $this->dispatch('subscription_plan.trial_configured', [
                'plan_id' => $planId,
                'trial_days' => $trialConfig['days'] ?? 0
            ]);

            return $plan;
        });
    }

    /**
     * Configura desconto do plano
     */
    public function configureDiscount(string $planId, array $discountConfig): array
    {
        return $this->executeWithMetrics('configure_plan_discount', function () use ($planId, $discountConfig) {
            $this->validateDiscountConfig($discountConfig);

            $response = $this->httpClient->put("/subscription-plans/{$planId}/discount", [
                'discount' => $discountConfig
            ]);

            $plan = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidatePlanCache($planId);

            // Dispatch evento
            $this->dispatch('subscription_plan.discount_configured', [
                'plan_id' => $planId,
                'discount_type' => $discountConfig['type'],
                'discount_value' => $discountConfig['value']
            ]);

            return $plan;
        });
    }

    /**
     * Configura upgrade path
     */
    public function configureUpgrade(string $planId, array $upgradeConfig): array
    {
        return $this->executeWithMetrics('configure_plan_upgrade', function () use ($planId, $upgradeConfig) {
            $this->validateUpgradeConfig($upgradeConfig);

            $response = $this->httpClient->put("/subscription-plans/{$planId}/upgrade", [
                'upgrade' => $upgradeConfig
            ]);

            $plan = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidatePlanCache($planId);

            // Dispatch evento
            $this->dispatch('subscription_plan.upgrade_configured', [
                'plan_id' => $planId,
                'target_plan_id' => $upgradeConfig['target_plan_id']
            ]);

            return $plan;
        });
    }

    /**
     * Ativa plano
     */
    public function activate(string $planId): bool
    {
        return $this->updateStatus($planId, 'active');
    }

    /**
     * Desativa plano
     */
    public function deactivate(string $planId): bool
    {
        return $this->updateStatus($planId, 'inactive');
    }

    /**
     * Arquiva plano
     */
    public function archive(string $planId): bool
    {
        return $this->updateStatus($planId, 'archived');
    }

    /**
     * Obtém métricas do plano
     */
    public function getMetrics(string $planId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_plan_metrics', function () use ($planId, $filters) {
            $response = $this->httpClient->get("/subscription-plans/{$planId}/metrics", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém análise de churn
     */
    public function getChurnAnalysis(string $planId, array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_plan_churn_analysis', function () use ($planId, $dateRange) {
            $response = $this->httpClient->get("/subscription-plans/{$planId}/churn-analysis", [
                'query' => $dateRange
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém relatório de retenção
     */
    public function getRetentionReport(string $planId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_plan_retention_report', function () use ($planId, $filters) {
            $response = $this->httpClient->get("/subscription-plans/{$planId}/retention-report", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém previsão de receita
     */
    public function getRevenueForecast(string $planId, int $months = 12): array
    {
        return $this->executeWithMetrics('get_plan_revenue_forecast', function () use ($planId, $months) {
            $response = $this->httpClient->get("/subscription-plans/{$planId}/revenue-forecast", [
                'query' => ['months' => $months]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Duplica plano
     */
    public function duplicate(string $planId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_subscription_plan', function () use ($planId, $overrideData) {
            $response = $this->httpClient->post("/subscription-plans/{$planId}/duplicate", $overrideData);
            $plan = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('subscription_plan.duplicated', [
                'original_id' => $planId,
                'new_id' => $plan['id']
            ]);

            return $plan;
        });
    }

    /**
     * Busca plano por ID via API
     */
    private function fetchPlanById(string $planId): ?array
    {
        try {
            $response = $this->httpClient->get("/subscription-plans/{$planId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do plano
     */
    private function updateStatus(string $planId, string $status): bool
    {
        return $this->executeWithMetrics("update_plan_status_{$status}", function () use ($planId, $status) {
            try {
                $response = $this->httpClient->put("/subscription-plans/{$planId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidatePlanCache($planId);

                // Dispatch evento
                $this->dispatch('subscription_plan.status_changed', [
                    'plan_id' => $planId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update plan status to {$status}", [
                    'plan_id' => $planId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do plano
     */
    private function invalidatePlanCache(string $planId): void
    {
        $this->cache->delete($this->getCacheKey("plan:{$planId}"));
    }

    /**
     * Valida dados do plano
     */
    private function validatePlanData(array $data): void
    {
        $required = ['name', 'price', 'interval'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for subscription plan creation");
            }
        }

        $allowedIntervals = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
        if (!in_array($data['interval'], $allowedIntervals)) {
            throw new ValidationException("Invalid subscription interval: {$data['interval']}");
        }

        if (!is_numeric($data['price']) || $data['price'] < 0) {
            throw new ValidationException('Subscription price must be a positive number');
        }
    }

    /**
     * Valida dados de atualização do plano
     */
    private function validatePlanUpdateData(array $data): void
    {
        if (isset($data['interval'])) {
            $allowedIntervals = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
            if (!in_array($data['interval'], $allowedIntervals)) {
                throw new ValidationException("Invalid subscription interval: {$data['interval']}");
            }
        }

        if (isset($data['price']) && (!is_numeric($data['price']) || $data['price'] < 0)) {
            throw new ValidationException('Subscription price must be a positive number');
        }
    }

    /**
     * Valida configuração de trial
     */
    private function validateTrialConfig(array $config): void
    {
        if (isset($config['days']) && (!is_numeric($config['days']) || $config['days'] < 0)) {
            throw new ValidationException('Trial days must be a positive number');
        }
    }

    /**
     * Valida configuração de desconto
     */
    private function validateDiscountConfig(array $config): void
    {
        $required = ['type', 'value'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for discount configuration");
            }
        }

        $allowedTypes = ['percentage', 'fixed_amount'];
        if (!in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid discount type: {$config['type']}");
        }

        if (!is_numeric($config['value']) || $config['value'] < 0) {
            throw new ValidationException('Discount value must be a positive number');
        }

        if ($config['type'] === 'percentage' && $config['value'] > 100) {
            throw new ValidationException('Percentage discount cannot exceed 100%');
        }
    }

    /**
     * Valida configuração de upgrade
     */
    private function validateUpgradeConfig(array $config): void
    {
        $required = ['target_plan_id'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for upgrade configuration");
            }
        }
    }

    /**
     * Constrói configuração do plano
     */
    private function buildPlanConfiguration(array $data): array
    {
        return [
            'billing_settings' => $data['billing_settings'] ?? [],
            'trial_settings' => $data['trial_settings'] ?? [],
            'proration_settings' => $data['proration_settings'] ?? ['enabled' => true],
            'cancellation_settings' => $data['cancellation_settings'] ?? [],
            'upgrade_settings' => $data['upgrade_settings'] ?? [],
            'features' => $data['features'] ?? []
        ];
    }

    /**
     * Inicializa analytics do plano
     */
    private function initializePlanAnalytics(): array
    {
        return [
            'active_subscriptions' => 0,
            'total_revenue' => 0,
            'monthly_recurring_revenue' => 0,
            'churn_rate' => 0,
            'conversion_rate' => 0,
            'lifetime_value' => 0,
            'trial_conversion_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
}