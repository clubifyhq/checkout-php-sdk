<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Subscriptions\Factories\SubscriptionsServiceFactory;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Subscriptions\Services\BillingService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionMetricsService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionLifecycleService;

/**
 * Módulo de gestão de assinaturas
 *
 * Responsável pela gestão completa de assinaturas e billing:
 * - CRUD de assinaturas
 * - Gestão de planos de assinatura
 * - Lifecycle de assinaturas (criar, pausar, cancelar, upgrade)
 * - Cobrança manual e automática
 * - Métricas e analytics de assinatura
 * - Gestão de ciclos de cobrança
 *
 * Utiliza Factory Pattern para criação de serviços:
 * - SubscriptionsServiceFactory para criação lazy de serviços
 * - Dependency injection automática
 * - Singleton pattern para performance
 * - ServiceInterface compliance para todos os serviços
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de assinatura
 * - O: Open/Closed - Extensível via novos tipos de plano
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de assinatura
 * - D: Dependency Inversion - Depende de abstrações
 */
class SubscriptionsModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Factory for service creation (lazy loading with singleton pattern)
    private ?SubscriptionsServiceFactory $serviceFactory = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
    }

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Subscriptions module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'tenant_id' => $this->config->getTenantId()
        ]);
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'subscriptions';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array
    {
        return ['payments', 'customers'];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array
    {
        $factoryStats = $this->serviceFactory?->getStats() ?? [
            'services_created' => 0,
            'cached_services' => 0,
            'supported_types' => 5
        ];

        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'factory_stats' => $factoryStats,
            'services_available' => [
                'subscription' => $this->serviceFactory?->hasService('subscription') ?? false,
                'subscription_plan' => $this->serviceFactory?->hasService('subscription_plan') ?? false,
                'billing' => $this->serviceFactory?->hasService('billing') ?? false,
                'subscription_metrics' => $this->serviceFactory?->hasService('subscription_metrics') ?? false,
                'subscription_lifecycle' => $this->serviceFactory?->hasService('subscription_lifecycle') ?? false,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->serviceFactory?->clearCache();
        $this->serviceFactory = null;
        $this->initialized = false;
        $this->logger?->info('Subscriptions module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('SubscriptionsModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'timestamp' => time()
        ];
    }

    /**
     * CRUD de assinaturas
     */
    public function createSubscription(array $subscriptionData): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionService()->createSubscription($subscriptionData);
    }

    public function getSubscription(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionService()->getSubscription($subscriptionId);
    }

    public function updateSubscription(string $subscriptionId, array $subscriptionData): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionService()->updateSubscription($subscriptionId, $subscriptionData);
    }

    public function listSubscriptions(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionService()->listSubscriptions($filters);
    }

    /**
     * Lifecycle de assinaturas
     */
    public function pauseSubscription(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionLifecycleService()->pauseSubscription($subscriptionId);
    }

    public function resumeSubscription(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionLifecycleService()->resumeSubscription($subscriptionId);
    }

    public function cancelSubscription(string $subscriptionId, array $options = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionLifecycleService()->cancelSubscription($subscriptionId, $options);
    }

    public function upgradeSubscription(string $subscriptionId, string $newPlanId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionLifecycleService()->upgradeSubscription($subscriptionId, $newPlanId);
    }

    public function downgradeSubscription(string $subscriptionId, string $newPlanId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionLifecycleService()->downgradeSubscription($subscriptionId, $newPlanId);
    }

    /**
     * Gestão de planos
     */
    public function createPlan(array $planData): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionPlanService()->createPlan($planData);
    }

    public function getPlan(string $planId): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionPlanService()->getPlan($planId);
    }

    public function updatePlan(string $planId, array $planData): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionPlanService()->updatePlan($planId, $planData);
    }

    public function listPlans(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionPlanService()->listPlans($filters);
    }

    /**
     * Billing e cobrança
     */
    public function processManualBilling(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getBillingService()->processManualBilling($subscriptionId);
    }

    public function getUpcomingInvoice(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getBillingService()->getUpcomingInvoice($subscriptionId);
    }

    public function getInvoiceHistory(string $subscriptionId): array
    {
        $this->requireInitialized();
        return $this->getBillingService()->getInvoiceHistory($subscriptionId);
    }

    public function updatePaymentMethod(string $subscriptionId, array $paymentMethodData): array
    {
        $this->requireInitialized();
        return $this->getBillingService()->updatePaymentMethod($subscriptionId, $paymentMethodData);
    }

    /**
     * Atualizar billing da assinatura
     */
    public function updateBilling(string $subscriptionId, array $billingData): array
    {
        $this->requireInitialized();
        return $this->getBillingService()->updateBilling($subscriptionId, $billingData);
    }

    /**
     * Métricas e analytics
     */
    public function getSubscriptionMetrics(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionMetricsService()->getSubscriptionMetrics($filters);
    }

    public function getChurnAnalysis(array $dateRange = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionMetricsService()->getChurnAnalysis($dateRange);
    }

    public function getRevenueMetrics(array $dateRange = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionMetricsService()->getRevenueMetrics($dateRange);
    }

    public function getSubscriptionHealth(): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionMetricsService()->getSubscriptionHealth();
    }

    /**
     * Factory-based service creation with lazy loading
     */
    private function getServiceFactory(): SubscriptionsServiceFactory
    {
        if ($this->serviceFactory === null) {
            $this->serviceFactory = $this->sdk->createSubscriptionsServiceFactory();
        }
        return $this->serviceFactory;
    }

    private function getSubscriptionService(): SubscriptionService
    {
        return $this->getServiceFactory()->create('subscription');
    }

    private function getSubscriptionPlanService(): SubscriptionPlanService
    {
        return $this->getServiceFactory()->create('subscription_plan');
    }

    private function getBillingService(): BillingService
    {
        return $this->getServiceFactory()->create('billing');
    }

    private function getSubscriptionMetricsService(): SubscriptionMetricsService
    {
        return $this->getServiceFactory()->create('subscription_metrics');
    }

    private function getSubscriptionLifecycleService(): SubscriptionLifecycleService
    {
        return $this->getServiceFactory()->create('subscription_lifecycle');
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Subscriptions module is not initialized');
        }
    }
}
