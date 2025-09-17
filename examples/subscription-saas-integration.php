<?php

/**
 * Exemplo Completo: Integração SaaS com Assinaturas
 *
 * Este exemplo demonstra como implementar um sistema SaaS completo usando
 * o módulo de assinaturas do Clubify Checkout SDK.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\PaymentMethodException;
use Clubify\Checkout\Exceptions\PlanNotFoundException;

class SubscriptionSaaSExample
{
    private ClubifyCheckoutSDK $sdk;

    public function __construct()
    {
        $this->sdk = new ClubifyCheckoutSDK([
            'api_url' => 'https://api.clubify.com',
            'tenant_id' => 'seu-tenant-id',
            'api_key' => 'sua-api-key',
            'secret_key' => 'sua-secret-key',
            'debug' => true
        ]);
    }

    public function runSaaSFlow()
    {
        try {
            echo "=== INICIANDO FLUXO SAAS COM ASSINATURAS ===\n\n";

            // 1. Criar planos de assinatura
            $plans = $this->createSubscriptionPlans();
            echo "✅ Planos criados: " . count($plans) . " planos disponíveis\n";

            // 2. Registrar cliente
            $customer = $this->registerCustomer();
            echo "✅ Cliente registrado: {$customer['name']} (ID: {$customer['id']})\n";

            // 3. Iniciar trial gratuito
            $trialSubscription = $this->startFreeTrial($customer['id'], $plans['basic']['id']);
            echo "✅ Trial iniciado: {$trialSubscription['status']} até {$trialSubscription['trial_end']}\n";

            // 4. Simular uso durante trial
            $this->simulateTrialUsage($trialSubscription['id']);
            echo "✅ Uso registrado durante trial\n";

            // 5. Converter trial em assinatura paga
            $paidSubscription = $this->convertTrialToPaid($trialSubscription['id'], $customer);
            echo "✅ Trial convertido para assinatura paga: {$paidSubscription['status']}\n";

            // 6. Upgrade de plano
            $upgradedSubscription = $this->upgradeSubscription($paidSubscription['id'], $plans['premium']['id']);
            echo "✅ Upgrade realizado para plano Premium\n";

            // 7. Monitorar health da assinatura
            $this->monitorSubscriptionHealth($upgradedSubscription['id']);
            echo "✅ Monitoramento de health ativo\n";

            echo "\n=== FLUXO SAAS COMPLETO FINALIZADO ===\n";

        } catch (Exception $e) {
            echo "❌ Erro no fluxo SaaS: " . $e->getMessage() . "\n";
            $this->handleSaaSError($e);
        }
    }

    private function createSubscriptionPlans(): array
    {
        $plans = [];

        // Plano Básico
        $basicPlan = $this->sdk->subscriptions()->createPlan([
            'name' => 'Plano Básico',
            'description' => 'Ideal para pequenas empresas',
            'amount' => 2999, // R$ 29,99
            'currency' => 'BRL',
            'interval' => 'monthly',
            'trial_days' => 14,
            'features' => [
                'up_to_100_projects',
                'basic_analytics',
                'email_support',
                '5gb_storage'
            ],
            'limits' => [
                'projects' => 100,
                'storage_gb' => 5,
                'api_calls_per_month' => 10000,
                'team_members' => 3
            ],
            'metadata' => [
                'tier' => 'basic',
                'target_audience' => 'small_business'
            ]
        ]);

        // Plano Premium
        $premiumPlan = $this->sdk->subscriptions()->createPlan([
            'name' => 'Plano Premium',
            'description' => 'Para empresas em crescimento',
            'amount' => 7999, // R$ 79,99
            'currency' => 'BRL',
            'interval' => 'monthly',
            'trial_days' => 14,
            'features' => [
                'unlimited_projects',
                'advanced_analytics',
                'priority_support',
                '50gb_storage',
                'custom_integrations',
                'white_label'
            ],
            'limits' => [
                'projects' => -1, // unlimited
                'storage_gb' => 50,
                'api_calls_per_month' => 100000,
                'team_members' => 15
            ],
            'metadata' => [
                'tier' => 'premium',
                'target_audience' => 'growing_business'
            ]
        ]);

        // Plano Enterprise
        $enterprisePlan = $this->sdk->subscriptions()->createPlan([
            'name' => 'Plano Enterprise',
            'description' => 'Para grandes organizações',
            'amount' => 19999, // R$ 199,99
            'currency' => 'BRL',
            'interval' => 'monthly',
            'trial_days' => 30,
            'features' => [
                'unlimited_everything',
                'enterprise_analytics',
                'dedicated_support',
                'unlimited_storage',
                'sso_integration',
                'advanced_security',
                'custom_onboarding'
            ],
            'limits' => [
                'projects' => -1,
                'storage_gb' => -1,
                'api_calls_per_month' => -1,
                'team_members' => -1
            ],
            'metadata' => [
                'tier' => 'enterprise',
                'target_audience' => 'large_organization'
            ]
        ]);

        return [
            'basic' => $basicPlan,
            'premium' => $premiumPlan,
            'enterprise' => $enterprisePlan
        ];
    }

    private function registerCustomer(): array
    {
        return $this->sdk->customers()->createCustomer([
            'name' => 'TechStart Innovations',
            'email' => 'admin@techstart.com',
            'type' => 'business',
            'company_name' => 'TechStart Innovations Ltda',
            'company_document' => '12345678000190',
            'contact_person' => 'Maria Silva',
            'phone' => '+5511999888777',
            'address' => [
                'street' => 'Av. Inovação, 1000',
                'complement' => 'Sala 501',
                'neighborhood' => 'Tech Hub',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '04567-000',
                'country' => 'BR'
            ],
            'preferences' => [
                'language' => 'pt-BR',
                'currency' => 'BRL',
                'timezone' => 'America/Sao_Paulo',
                'marketing_emails' => true,
                'product_updates' => true
            ],
            'metadata' => [
                'company_size' => '10-50',
                'industry' => 'technology',
                'lead_source' => 'website'
            ]
        ]);
    }

    private function startFreeTrial(string $customerId, string $planId): array
    {
        return $this->sdk->subscriptions()->createSubscription([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'trial_settings' => [
                'trial_days' => 14,
                'require_payment_method' => false,
                'trial_features' => ['full_access'],
                'trial_limits' => [
                    'projects' => 10,
                    'storage_gb' => 1,
                    'api_calls_per_month' => 1000
                ]
            ],
            'metadata' => [
                'trial_source' => 'website_signup',
                'utm_campaign' => 'free_trial_2024'
            ]
        ]);
    }

    private function simulateTrialUsage(string $subscriptionId): void
    {
        echo "📊 Simulando uso durante trial...\n";

        // Registrar diferentes tipos de uso
        $usageEvents = [
            ['metric' => 'projects_created', 'value' => 3],
            ['metric' => 'api_calls', 'value' => 250],
            ['metric' => 'storage_used_mb', 'value' => 150],
            ['metric' => 'team_members_invited', 'value' => 2],
            ['metric' => 'reports_generated', 'value' => 5]
        ];

        foreach ($usageEvents as $event) {
            $this->sdk->analytics()->recordUsage($subscriptionId, [
                'metric' => $event['metric'],
                'value' => $event['value'],
                'timestamp' => time()
            ]);

            echo "   - {$event['metric']}: {$event['value']}\n";
        }

        // Registrar eventos de engajamento
        $engagementEvents = [
            'feature_discovery' => ['analytics_dashboard', 'project_templates', 'team_collaboration'],
            'support_interactions' => ['knowledge_base_view', 'tutorial_completed'],
            'integration_usage' => ['slack_connected', 'github_connected']
        ];

        foreach ($engagementEvents as $type => $events) {
            foreach ($events as $event) {
                $this->sdk->analytics()->recordEvent($subscriptionId, [
                    'event_type' => $type,
                    'event_name' => $event,
                    'timestamp' => time()
                ]);
            }
        }
    }

    private function convertTrialToPaid(string $subscriptionId, array $customer): array
    {
        echo "💳 Convertendo trial para assinatura paga...\n";

        // Primeiro, coletar método de pagamento
        $paymentMethod = $this->collectPaymentMethod($customer);

        // Converter subscription
        return $this->sdk->subscriptions()->convertTrial($subscriptionId, [
            'payment_method' => $paymentMethod['id'],
            'billing_cycle_anchor' => 'now',
            'prorate' => false
        ]);
    }

    private function collectPaymentMethod(array $customer): array
    {
        // Simular coleta de dados do cartão
        return $this->sdk->payments()->tokenizeCard([
            'number' => '4111111111111111',
            'expiry_month' => '12',
            'expiry_year' => '2025',
            'cvv' => '123',
            'holder_name' => $customer['contact_person'],
            'customer_id' => $customer['id']
        ]);
    }

    private function upgradeSubscription(string $subscriptionId, string $newPlanId): array
    {
        echo "⬆️ Realizando upgrade de plano...\n";

        return $this->sdk->subscriptions()->changeSubscriptionPlan($subscriptionId, [
            'new_plan_id' => $newPlanId,
            'prorate' => true,
            'billing_cycle_anchor' => 'unchanged',
            'upgrade_reason' => 'customer_request'
        ]);
    }

    private function monitorSubscriptionHealth(string $subscriptionId): void
    {
        echo "🏥 Iniciando monitoramento de health...\n";

        // Configurar alertas de health
        $this->sdk->subscriptions()->configureHealthAlerts($subscriptionId, [
            'usage_decline' => [
                'threshold' => 50, // 50% de redução no uso
                'period' => '7_days'
            ],
            'payment_issues' => [
                'failed_attempts' => 2
            ],
            'support_activity' => [
                'ticket_threshold' => 3,
                'sentiment_threshold' => 'negative'
            ]
        ]);

        // Verificar health score atual
        $healthScore = $this->sdk->analytics()->getSubscriptionHealthScore($subscriptionId);

        echo "   Health Score: {$healthScore['score']}/100\n";
        echo "   Status: {$healthScore['status']}\n";

        if ($healthScore['score'] < 70) {
            echo "   ⚠️ Health score baixo - acionando estratégias de retenção\n";
            $this->triggerRetentionStrategy($subscriptionId, $healthScore);
        }
    }

    private function triggerRetentionStrategy(string $subscriptionId, array $healthScore): void
    {
        $retentionActions = [];

        // Baseado no health score, definir ações
        if (in_array('low_usage', $healthScore['risk_factors'])) {
            $retentionActions[] = 'send_onboarding_series';
            $retentionActions[] = 'schedule_success_call';
        }

        if (in_array('payment_issues', $healthScore['risk_factors'])) {
            $retentionActions[] = 'update_payment_method';
            $retentionActions[] = 'offer_payment_plan';
        }

        if (in_array('support_negative', $healthScore['risk_factors'])) {
            $retentionActions[] = 'priority_support_escalation';
            $retentionActions[] = 'customer_success_intervention';
        }

        // Executar ações de retenção
        foreach ($retentionActions as $action) {
            $this->executeRetentionAction($subscriptionId, $action);
        }
    }

    private function executeRetentionAction(string $subscriptionId, string $action): void
    {
        switch ($action) {
            case 'send_onboarding_series':
                $this->sdk->notifications()->sendEmail([
                    'subscription_id' => $subscriptionId,
                    'template' => 'onboarding_help',
                    'subject' => 'Vamos te ajudar a aproveitar melhor nossa plataforma!'
                ]);
                break;

            case 'schedule_success_call':
                $this->sdk->subscriptions()->scheduleSuccessCall($subscriptionId, [
                    'priority' => 'high',
                    'preferred_time' => 'business_hours'
                ]);
                break;

            case 'offer_payment_plan':
                $this->sdk->subscriptions()->createPaymentPlan($subscriptionId, [
                    'split_months' => 3,
                    'discount_percentage' => 10
                ]);
                break;

            case 'priority_support_escalation':
                $this->sdk->subscriptions()->escalateSupport($subscriptionId, [
                    'priority' => 'high',
                    'assign_to' => 'senior_team'
                ]);
                break;
        }

        echo "   ✅ Ação executada: {$action}\n";
    }

    private function handleSaaSError(Exception $e): void
    {
        if ($e instanceof PaymentMethodException) {
            // Problema com método de pagamento
            echo "💳 Oferecendo métodos alternativos de pagamento...\n";
            $this->offerAlternativePayment();

        } elseif ($e instanceof PlanNotFoundException) {
            // Plano não encontrado
            echo "📋 Redirecionando para seleção de planos...\n";
            $this->redirectToPlanSelection();

        } else {
            // Erro geral - notificar equipe
            $this->notifyErrorToTeam($e);
        }
    }

    private function offerAlternativePayment(): void
    {
        echo "   - PIX (aprovação instantânea)\n";
        echo "   - Boleto bancário\n";
        echo "   - Transferência bancária\n";
    }

    private function redirectToPlanSelection(): void
    {
        echo "   Redirecionando para: https://app.com/plans\n";
    }

    private function notifyErrorToTeam(Exception $e): void
    {
        echo "🚨 Notificando equipe técnica sobre erro crítico...\n";
        error_log("SaaS Critical Error: " . $e->getMessage());
    }

    public function demonstrateSaaSAnalytics(): void
    {
        echo "\n=== ANALYTICS SAAS ===\n";

        // MRR Report
        $mrr = $this->sdk->subscriptions()->getMRRReport([
            'period' => 'monthly',
            'date_from' => date('Y-m-01'),
            'date_to' => date('Y-m-t')
        ]);

        echo "💰 MRR Atual: R$ " . number_format($mrr['current_mrr'] / 100, 2) . "\n";
        echo "📈 Crescimento MoM: " . $mrr['growth_rate'] . "%\n";

        // Churn Analysis
        $churn = $this->sdk->subscriptions()->getChurnAnalysis([
            'period' => 'monthly'
        ]);

        echo "📉 Taxa de Churn: " . $churn['churn_rate'] . "%\n";
        echo "🔄 Taxa de Retenção: " . (100 - $churn['churn_rate']) . "%\n";

        // Customer Lifetime Value
        $ltv = $this->sdk->analytics()->getLTVAnalysis([
            'segment' => 'all'
        ]);

        echo "💎 LTV Médio: R$ " . number_format($ltv['average_ltv'] / 100, 2) . "\n";
        echo "⏱️ Payback Period: " . $ltv['payback_months'] . " meses\n";

        // Usage Analytics
        $usage = $this->sdk->analytics()->getUsageAnalytics([
            'period' => '30_days',
            'metrics' => ['api_calls', 'storage_used', 'active_users']
        ]);

        echo "📊 Uso dos últimos 30 dias:\n";
        foreach ($usage['metrics'] as $metric => $value) {
            echo "   - " . ucfirst(str_replace('_', ' ', $metric)) . ": " . number_format($value) . "\n";
        }
    }
}

// Executar exemplo
if (php_sapi_name() === 'cli') {
    $example = new SubscriptionSaaSExample();
    $example->runSaaSFlow();
    $example->demonstrateSaaSAnalytics();
}