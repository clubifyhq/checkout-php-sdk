<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
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

    // Services (lazy loading)
    private ?SubscriptionService $subscriptionService = null;
    private ?SubscriptionPlanService $subscriptionPlanService = null;
    private ?BillingService $billingService = null;
    private ?SubscriptionMetricsService $subscriptionMetricsService = null;
    private ?SubscriptionLifecycleService $subscriptionLifecycleService = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {}

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
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                'subscription' => $this->subscriptionService !== null,
                'subscription_plan' => $this->subscriptionPlanService !== null,
                'billing' => $this->billingService !== null,
                'metrics' => $this->subscriptionMetricsService !== null,
                'lifecycle' => $this->subscriptionLifecycleService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->subscriptionService = null;
        $this->subscriptionPlanService = null;
        $this->billingService = null;
        $this->subscriptionMetricsService = null;
        $this->subscriptionLifecycleService = null;
        $this->initialized = false;
        $this->logger?->info('Subscriptions module cleaned up');
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
     * Métricas e analytics
     */
    public function getSubscriptionMetrics(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getSubscriptionMetricsService()->getMetrics($filters);
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
     * Lazy loading dos services
     */
    private function getSubscriptionService(): SubscriptionService
    {
        if ($this->subscriptionService === null) {
            $this->subscriptionService = new SubscriptionService($this->sdk, $this->config, $this->logger);
        }
        return $this->subscriptionService;
    }

    private function getSubscriptionPlanService(): SubscriptionPlanService
    {
        if ($this->subscriptionPlanService === null) {
            $this->subscriptionPlanService = new SubscriptionPlanService($this->sdk, $this->config, $this->logger);
        }
        return $this->subscriptionPlanService;
    }

    private function getBillingService(): BillingService
    {
        if ($this->billingService === null) {
            $this->billingService = new BillingService($this->sdk, $this->config, $this->logger);
        }
        return $this->billingService;
    }

    private function getSubscriptionMetricsService(): SubscriptionMetricsService
    {
        if ($this->subscriptionMetricsService === null) {
            $this->subscriptionMetricsService = new SubscriptionMetricsService($this->sdk, $this->config, $this->logger);
        }
        return $this->subscriptionMetricsService;
    }

    private function getSubscriptionLifecycleService(): SubscriptionLifecycleService
    {
        if ($this->subscriptionLifecycleService === null) {
            $this->subscriptionLifecycleService = new SubscriptionLifecycleService($this->sdk, $this->config, $this->logger);
        }
        return $this->subscriptionLifecycleService;
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
