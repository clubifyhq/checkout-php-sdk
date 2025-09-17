# Guia de Uso - Módulo Subscriptions

## Visão Geral
O módulo Subscriptions gerencia assinaturas recorrentes, incluindo planos, billing, ciclos de cobrança e métricas de retenção.

## Inicialização

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'seu-tenant-id',
    'api_key' => 'sua-api-key',
    'secret_key' => 'sua-secret-key'
]);

$subscriptionsModule = $sdk->subscriptions();
```

## Gerenciamento de Planos

### 1. Criar Plano de Assinatura

```php
$planData = [
    'name' => 'Plano Premium',
    'description' => 'Acesso completo a todos os recursos',
    'amount' => 2999, // R$ 29,99 em centavos
    'currency' => 'BRL',
    'interval' => 'monthly', // monthly, quarterly, yearly
    'interval_count' => 1,
    'trial_days' => 7,
    'features' => [
        'unlimited_downloads',
        'priority_support',
        'advanced_analytics'
    ],
    'metadata' => [
        'category' => 'premium',
        'max_users' => 10
    ]
];

$plan = $subscriptionsModule->createPlan($planData);
```

### 2. Listar e Gerenciar Planos

```php
// Listar todos os planos
$plans = $subscriptionsModule->listPlans([
    'status' => 'active',
    'interval' => 'monthly'
]);

// Obter plano específico
$plan = $subscriptionsModule->getPlan('plan_123');

// Atualizar plano
$updatedPlan = $subscriptionsModule->updatePlan('plan_123', [
    'amount' => 3999, // Novo preço
    'trial_days' => 14
]);

// Desativar plano
$subscriptionsModule->deactivatePlan('plan_123');
```

## Gerenciamento de Assinaturas

### 1. Criar Assinatura

```php
$subscriptionData = [
    'customer_id' => 'cust_123',
    'plan_id' => 'plan_premium',
    'payment_method' => 'credit_card',
    'card_token' => 'card_token_456',
    'coupon_code' => 'FIRST30', // Opcional
    'trial_end' => '2024-02-01', // Opcional - sobrescreve trial do plano
    'metadata' => [
        'source' => 'website',
        'campaign' => 'black_friday'
    ]
];

$subscription = $subscriptionsModule->createSubscription($subscriptionData);
```

### 2. Gerenciar Assinatura

```php
// Obter assinatura
$subscription = $subscriptionsModule->getSubscription('sub_123');

// Listar assinaturas do cliente
$customerSubs = $subscriptionsModule->getCustomerSubscriptions('cust_123');

// Pausar assinatura
$subscriptionsModule->pauseSubscription('sub_123', [
    'reason' => 'Customer request',
    'resume_date' => '2024-03-01'
]);

// Reativar assinatura
$subscriptionsModule->resumeSubscription('sub_123');

// Cancelar assinatura
$subscriptionsModule->cancelSubscription('sub_123', [
    'reason' => 'Too expensive',
    'cancel_at_period_end' => true // Cancela apenas no fim do período atual
]);
```

### 3. Mudança de Plano

```php
// Upgrade/downgrade
$planChange = $subscriptionsModule->changeSubscriptionPlan('sub_123', [
    'new_plan_id' => 'plan_enterprise',
    'prorate' => true, // Calcular valor proporcional
    'billing_cycle_anchor' => 'now' // ou 'unchanged'
]);

// Aplicar desconto permanente
$subscriptionsModule->applyDiscount('sub_123', [
    'type' => 'percentage',
    'value' => 20, // 20% de desconto
    'duration' => 'forever' // ou número de ciclos
]);
```

## Billing e Cobrança

### 1. Histórico de Faturas

```php
// Listar faturas da assinatura
$invoices = $subscriptionsModule->getSubscriptionInvoices('sub_123', [
    'status' => 'paid',
    'limit' => 50
]);

// Obter fatura específica
$invoice = $subscriptionsModule->getInvoice('inv_123');

// Gerar prévia da próxima fatura
$preview = $subscriptionsModule->getUpcomingInvoice('sub_123');
```

### 2. Cobrança Manual

```php
// Gerar cobrança extra
$charge = $subscriptionsModule->createUsageCharge('sub_123', [
    'amount' => 1999, // R$ 19,99
    'description' => 'Taxa de uso adicional',
    'metadata' => [
        'usage_type' => 'overage',
        'units' => 100
    ]
]);

// Processar cobrança pendente
$billing = $subscriptionsModule->processInvoice('inv_123');
```

### 3. Configurar Cobrança por Uso

```php
// Adicionar medidor de uso
$usageMeter = $subscriptionsModule->createUsageMeter([
    'name' => 'API Calls',
    'unit' => 'calls',
    'aggregation' => 'sum'
]);

// Registrar uso
$subscriptionsModule->recordUsage('sub_123', [
    'meter_id' => $usageMeter['id'],
    'quantity' => 150,
    'timestamp' => time()
]);
```

## Análise de Churn e Retenção

### 1. Métricas de Churn

```php
// Taxa de churn mensal
$churnRate = $subscriptionsModule->getChurnRate([
    'period' => 'monthly',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31'
]);

// Análise de churn por segmento
$churnBySegment = $subscriptionsModule->getChurnAnalysis([
    'group_by' => 'plan',
    'period' => '30_days'
]);
```

### 2. Campanhas de Retenção

```php
// Identificar assinaturas em risco
$atRiskSubscriptions = $subscriptionsModule->getAtRiskSubscriptions([
    'risk_factors' => ['payment_failures', 'usage_decline', 'support_tickets'],
    'risk_score' => 'high'
]);

// Aplicar campanha de retenção
foreach ($atRiskSubscriptions as $subscription) {
    $subscriptionsModule->applyRetentionOffer($subscription['id'], [
        'type' => 'discount',
        'value' => 50, // 50% de desconto
        'duration' => 3, // 3 meses
        'message' => 'Oferta especial para você!'
    ]);
}
```

### 3. Win-back Campaigns

```php
// Listar assinaturas canceladas recentemente
$cancelledSubs = $subscriptionsModule->getCancelledSubscriptions([
    'cancelled_after' => '2024-01-01',
    'reactivation_eligible' => true
]);

// Enviar oferta de reativação
$subscriptionsModule->sendWinBackOffer('sub_cancelled_123', [
    'discount_percentage' => 30,
    'trial_days' => 14,
    'expires_at' => '2024-02-15'
]);
```

## Webhooks e Eventos

### 1. Configurar Webhooks

```php
$subscriptionsModule->configureWebhook([
    'url' => 'https://seusite.com/webhook/subscriptions',
    'events' => [
        'subscription.created',
        'subscription.updated',
        'subscription.cancelled',
        'invoice.payment_succeeded',
        'invoice.payment_failed',
        'trial.will_end'
    ]
]);
```

### 2. Processar Eventos

```php
// Processar webhook recebido
$event = $subscriptionsModule->processWebhook($_POST, $_SERVER['HTTP_SIGNATURE']);

switch ($event['type']) {
    case 'invoice.payment_failed':
        // Lógica para falha de pagamento
        $this->handlePaymentFailure($event['data']);
        break;

    case 'trial.will_end':
        // Notificar cliente sobre fim do trial
        $this->notifyTrialEnding($event['data']);
        break;
}
```

## Relatórios e Analytics

### 1. MRR (Monthly Recurring Revenue)

```php
$mrrReport = $subscriptionsModule->getMRRReport([
    'period' => 'monthly',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'group_by' => 'plan'
]);

echo "MRR Total: R$ " . number_format($mrrReport['total_mrr'] / 100, 2);
echo "Crescimento MoM: " . $mrrReport['growth_rate'] . "%";
```

### 2. Cohort Analysis

```php
$cohortAnalysis = $subscriptionsModule->getCohortAnalysis([
    'period' => 'monthly',
    'cohort_size' => 12, // 12 meses
    'metric' => 'retention_rate'
]);
```

### 3. LTV (Lifetime Value)

```php
$ltvAnalysis = $subscriptionsModule->getLTVAnalysis([
    'segment' => 'all',
    'period' => '12_months'
]);

echo "LTV Médio: R$ " . number_format($ltvAnalysis['average_ltv'] / 100, 2);
echo "Payback Period: " . $ltvAnalysis['payback_months'] . " meses";
```

## Dunning Management

### 1. Configurar Dunning

```php
$subscriptionsModule->configureDunning([
    'attempts' => 3,
    'intervals' => [3, 7, 14], // dias entre tentativas
    'actions' => [
        'send_email',
        'pause_access',
        'cancel_subscription'
    ]
]);
```

### 2. Retry de Pagamentos

```php
// Retry manual
$retryResult = $subscriptionsModule->retryPayment('sub_123');

// Agendar retry
$subscriptionsModule->schedulePaymentRetry('sub_123', [
    'retry_date' => '2024-01-20',
    'max_attempts' => 2
]);
```

## Tratamento de Erros

```php
try {
    $subscription = $subscriptionsModule->createSubscription($subscriptionData);
} catch (\Clubify\Checkout\Exceptions\PaymentMethodException $e) {
    // Método de pagamento inválido
    echo "Método de pagamento inválido: " . $e->getMessage();
} catch (\Clubify\Checkout\Exceptions\PlanNotFoundException $e) {
    // Plano não encontrado
    echo "Plano não encontrado: " . $e->getPlanId();
} catch (\Clubify\Checkout\Exceptions\SubscriptionException $e) {
    // Erro específico de assinatura
    echo "Erro na assinatura: " . $e->getMessage();
}
```

## Exemplos Avançados

### Assinatura com Múltiplos Produtos

```php
$subscriptionData = [
    'customer_id' => 'cust_123',
    'items' => [
        [
            'plan_id' => 'plan_base',
            'quantity' => 1
        ],
        [
            'plan_id' => 'plan_addon_storage',
            'quantity' => 5 // 5 unidades de storage adicional
        ]
    ],
    'payment_method' => 'credit_card'
];

$subscription = $subscriptionsModule->createSubscription($subscriptionData);
```

### Assinatura com Trial Personalizado

```php
$subscriptionData = [
    'customer_id' => 'cust_123',
    'plan_id' => 'plan_premium',
    'trial_settings' => [
        'trial_days' => 30,
        'require_payment_method' => false,
        'trial_features' => ['full_access'],
        'trial_limits' => [
            'api_calls' => 1000,
            'storage_gb' => 10
        ]
    ]
];

$subscription = $subscriptionsModule->createSubscription($subscriptionData);
```