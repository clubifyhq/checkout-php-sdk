# 🎭 ClubifyCheckout Facade - Documentação Completa

## Visão Geral

A **ClubifyCheckout Facade** oferece uma interface elegante e Laravel-friendly para acessar todas as funcionalidades do SDK. Com mais de 25 métodos utilitários, a facade simplifica o uso do SDK em aplicações Laravel, oferecendo shortcuts para operações comuns e integração nativa com recursos do framework.

### 🎯 Funcionalidades Principais

- **Acesso Simplificado**: Interface fluente para todas as operações do SDK
- **Métodos de Conveniência**: Shortcuts para operações mais comuns
- **Integração Laravel**: Métodos específicos para cache, eventos, logs e jobs
- **Debug e Testing**: Ferramentas para desenvolvimento e debug
- **Type Safety**: Documentação PHPDoc completa para IntelliSense
- **Performance**: Cache automático e otimizações Laravel-specific

### 🏗️ Estrutura da Facade

```
ClubifyCheckout Facade
├── Core Methods (10)
│   ├── make()
│   ├── initialize()
│   ├── isInitialized()
│   ├── getStats()
│   ├── setDebugMode()
│   ├── healthCheck()
│   ├── getVersion()
│   ├── getConfiguration()
│   ├── clearCache()
│   └── reset()
├── Module Accessors (6)
│   ├── organization()
│   ├── products()
│   ├── checkout()
│   ├── payments()
│   ├── customers()
│   └── webhooks()
├── Convenience Methods (6)
│   ├── setupOrganization()
│   ├── createProduct()
│   ├── processPayment()
│   ├── createSession()
│   ├── findOrCreateCustomer()
│   └── setupWebhook()
├── Laravel Integration (5)
│   ├── cached()
│   ├── queue()
│   ├── event()
│   ├── log()
│   └── validate()
└── Development Helpers (3)
    ├── fake()
    ├── debug()
    └── [testing methods]
```

## 📚 Métodos Core

### `make(array $config = []): ClubifyCheckoutSDK`

Cria uma nova instância do SDK com configuração específica.

```php
use ClubifyCheckout;

// Usar instância padrão (configuração do container)
$sdk = ClubifyCheckout::make();

// Criar com configuração específica
$customSdk = ClubifyCheckout::make([
    'api_key' => 'custom_key',
    'environment' => 'sandbox'
]);

// Usar configuração diferente para tenant específico
$tenantSdk = ClubifyCheckout::make([
    'tenant_id' => 'tenant_123',
    'api_key' => 'tenant_specific_key'
]);
```

### `initialize(): bool`

Inicializa o SDK manualmente.

```php
$initialized = ClubifyCheckout::initialize();

if ($initialized) {
    echo "SDK inicializado com sucesso";
} else {
    echo "Falha na inicialização do SDK";
}
```

### `isInitialized(): bool`

Verifica se o SDK está inicializado.

```php
if (ClubifyCheckout::isInitialized()) {
    $stats = ClubifyCheckout::getStats();
} else {
    ClubifyCheckout::initialize();
}
```

### `getStats(): array`

Obtém estatísticas gerais do SDK.

```php
$stats = ClubifyCheckout::getStats();

echo "Módulos carregados: " . count($stats['modules']) . "\n";
echo "Operações realizadas: " . $stats['total_operations'] . "\n";
echo "Cache hits: " . $stats['cache_hits'] . "\n";
echo "Tempo médio de resposta: " . $stats['avg_response_time'] . "ms\n";
```

### `setDebugMode(bool $debug = true): ClubifyCheckoutSDK`

Ativa/desativa modo debug.

```php
// Ativar debug
ClubifyCheckout::setDebugMode(true);

// Desativar debug
ClubifyCheckout::setDebugMode(false);

// Modo debug condicional
ClubifyCheckout::setDebugMode(app()->environment('local', 'testing'));
```

### `healthCheck(): array`

Verifica saúde de todos os módulos.

```php
$health = ClubifyCheckout::healthCheck();

foreach ($health['modules'] as $module => $status) {
    echo "Módulo {$module}: " . ($status['healthy'] ? '✅' : '❌') . "\n";
}

if ($health['overall_healthy']) {
    echo "✅ Sistema saudável";
} else {
    echo "❌ Sistema com problemas";
}
```

### `getVersion(): string`

Obtém versão do SDK.

```php
$version = ClubifyCheckout::getVersion();
echo "SDK Version: {$version}";
```

### `getConfiguration(): array`

Obtém configuração atual do SDK.

```php
$config = ClubifyCheckout::getConfiguration();

echo "Environment: " . $config['environment'] . "\n";
echo "Base URL: " . $config['base_url'] . "\n";
echo "Modules enabled: " . implode(', ', array_keys($config['modules'])) . "\n";
```

### `clearCache(): void`

Limpa cache do SDK.

```php
// Limpar cache do SDK
ClubifyCheckout::clearCache();

// Combinar com cache do Laravel
cache()->flush();
ClubifyCheckout::clearCache();
```

### `reset(): void`

Reseta o SDK para estado inicial.

```php
// Reset completo (útil em testes)
ClubifyCheckout::reset();
ClubifyCheckout::initialize();
```

## 🎛️ Acessadores de Módulos

### `organization(): OrganizationModule`

Acessa o módulo de organizações.

```php
// Acesso direto ao módulo
$orgModule = ClubifyCheckout::organization();

// Setup de organização
$org = ClubifyCheckout::organization()->setupOrganization($data);

// Gestão de tenants
$tenant = ClubifyCheckout::organization()->tenant()->create($tenantData);

// Gestão de admins
$admin = ClubifyCheckout::organization()->admin()->create($adminData);
```

### `products(): ProductsModule`

Acessa o módulo de produtos.

```php
// Criar produto completo
$product = ClubifyCheckout::products()->setupProduct($productData, $offerData);

// Gestão de ofertas
$offer = ClubifyCheckout::products()->getOfferService()->create($offerData);

// Order bumps
$orderBump = ClubifyCheckout::products()->getOrderBumpService()->create($bumpData);

// Flows de vendas
$flow = ClubifyCheckout::products()->setupSalesFlow($flowData);
```

### `checkout(): CheckoutModule`

Acessa o módulo de checkout.

```php
// Criar sessão
$session = ClubifyCheckout::checkout()->createSession($organizationId, $sessionData);

// Gestão de carrinho
$cart = ClubifyCheckout::checkout()->createCart($sessionId);
ClubifyCheckout::checkout()->addToCart($cartId, $item);

// One-click checkout
$oneClick = ClubifyCheckout::checkout()->initiateOneClick($orgId, $productData, $customerData);

// Flow navigation
$flow = ClubifyCheckout::checkout()->createFlow($orgId, $flowConfig);
```

### `payments(): PaymentsModule`

Acessa o módulo de pagamentos.

```php
// Processar pagamento
$payment = ClubifyCheckout::payments()->processPayment($paymentData);

// Gestão de cartões
$token = ClubifyCheckout::payments()->tokenizeCard($cardData);
$savedCard = ClubifyCheckout::payments()->saveCard($customerId, $cardData);

// Operações de pagamento
$refund = ClubifyCheckout::payments()->refundPayment($paymentId, $amount);
$capture = ClubifyCheckout::payments()->capturePayment($paymentId);
```

### `customers(): CustomersModule`

Acessa o módulo de clientes.

```php
// Gestão de clientes
$customer = ClubifyCheckout::customers()->createCustomer($customerData);
$existing = ClubifyCheckout::customers()->findOrCreateCustomer($customerData);

// Analytics e perfil
$clv = ClubifyCheckout::customers()->calculateCustomerLifetimeValue($customerId);
$segments = ClubifyCheckout::customers()->segmentCustomers($criteria);

// Histórico
$history = ClubifyCheckout::customers()->getCustomerHistory($customerId);
```

### `webhooks(): WebhooksModule`

Acessa o módulo de webhooks.

```php
// Setup de webhook
$webhook = ClubifyCheckout::webhooks()->setupWebhook($webhookData);

// Entrega de eventos
$result = ClubifyCheckout::webhooks()->deliverEvent($eventType, $eventData);

// Sistema de retry
$retryResults = ClubifyCheckout::webhooks()->processRetries(100);

// Validação
$isValid = ClubifyCheckout::webhooks()->validateSignature($payload, $signature, $secret);
```

## 🚀 Métodos de Conveniência

### `setupOrganization(array $organizationData): array`

Shortcut para setup completo de organização.

```php
$org = ClubifyCheckout::setupOrganization([
    'name' => 'Minha Empresa',
    'admin_name' => 'João Silva',
    'admin_email' => 'joao@empresa.com',
    'subdomain' => 'minha-empresa'
]);

// Equivale a:
// ClubifyCheckout::organization()->setupOrganization($data);
```

### `createProduct(array $productData): array`

Shortcut para criação de produto.

```php
$product = ClubifyCheckout::createProduct([
    'name' => 'Curso de Laravel',
    'description' => 'Aprenda Laravel do zero',
    'price' => 19900,
    'type' => 'digital'
]);
```

### `processPayment(array $paymentData): array`

Shortcut para processamento de pagamento.

```php
$payment = ClubifyCheckout::processPayment([
    'amount' => 9900,
    'currency' => 'BRL',
    'method' => 'credit_card',
    'customer_id' => 'cust_123',
    'card' => ['token' => 'card_token_456']
]);
```

### `createSession(array $sessionData): array`

Shortcut para criação de sessão de checkout.

```php
$session = ClubifyCheckout::createSession([
    'organization_id' => 'org_123',
    'customer_data' => ['email' => 'cliente@exemplo.com'],
    'source' => 'website'
]);
```

### `findOrCreateCustomer(array $customerData): array`

Shortcut para buscar ou criar cliente.

```php
$customer = ClubifyCheckout::findOrCreateCustomer([
    'name' => 'Maria Silva',
    'email' => 'maria@exemplo.com',
    'phone' => '+5511999999999'
]);
```

### `setupWebhook(array $webhookData): array`

Shortcut para configuração de webhook.

```php
$webhook = ClubifyCheckout::setupWebhook([
    'url' => 'https://meusite.com/webhook',
    'events' => ['order.created', 'payment.approved'],
    'secret' => 'webhook_secret_123'
]);
```

## 🔧 Integração Laravel

### `cached(string $key, callable $callback, int $ttl = 3600): mixed`

Integração com cache do Laravel.

```php
// Cache automático de operações custosas
$expensiveData = ClubifyCheckout::cached('customer_analytics_123', function () {
    return ClubifyCheckout::customers()->getCustomerBehaviorAnalysis('123');
}, 3600);

// Cache de estatísticas
$stats = ClubifyCheckout::cached('daily_stats', function () {
    return ClubifyCheckout::getStats();
}, 86400);
```

### `queue(string $method, array $params = []): void`

Execução assíncrona via Laravel Queue.

```php
// Processar pagamento em background
ClubifyCheckout::queue('processPayment', [$paymentData]);

// Enviar webhook em background
ClubifyCheckout::queue('deliverEvent', ['order.created', $orderData]);

// Sincronizar cliente em background
ClubifyCheckout::queue('findOrCreateCustomer', [$customerData]);
```

### `event(string $event, array $data = []): void`

Integração com sistema de eventos do Laravel.

```php
// Disparar evento Laravel
ClubifyCheckout::event('payment.processed', [
    'payment_id' => $payment['id'],
    'amount' => $payment['amount'],
    'customer_id' => $payment['customer_id']
]);

// Listener no Laravel
Event::listen('clubify.checkout.payment.processed', function ($data) {
    // Enviar email de confirmação
    // Atualizar dashboard em tempo real
    // Integrar com sistema CRM
});
```

### `log(string $level, string $message, array $context = []): void`

Integração com sistema de logging do Laravel.

```php
// Logs estruturados
ClubifyCheckout::log('info', 'Payment processed successfully', [
    'payment_id' => $payment['id'],
    'amount' => $payment['amount'],
    'method' => $payment['method']
]);

ClubifyCheckout::log('error', 'Payment failed', [
    'error' => $error['message'],
    'payment_data' => $paymentData
]);

ClubifyCheckout::log('debug', 'Webhook delivered', [
    'webhook_id' => $webhook['id'],
    'response_time' => $responseTime
]);
```

### `validate(array $data, array $rules): array`

Integração com Laravel Validator.

```php
// Validação usando Laravel
$validatedData = ClubifyCheckout::validate($request->all(), [
    'customer.name' => 'required|string|max:255',
    'customer.email' => 'required|email',
    'payment.amount' => 'required|numeric|min:1',
    'payment.method' => 'required|in:credit_card,pix,boleto'
]);

// Usar dados validados
$result = ClubifyCheckout::processPayment($validatedData['payment']);
```

## 🛠️ Helpers de Desenvolvimento

### `fake(): ClubifyCheckoutSDK`

Modo fake para testing e desenvolvimento.

```php
// Apenas em ambiente de teste/local
if (app()->environment('testing')) {
    $fakeSdk = ClubifyCheckout::fake();

    // Todas as operações retornam dados simulados
    $fakePayment = $fakeSdk->processPayment($paymentData);
    // Retorna dados fake realistas para testes
}
```

### `debug(): array`

Informações completas de debug.

```php
$debugInfo = ClubifyCheckout::debug();

/*
Array retornado:
[
    'sdk_version' => '1.0.0',
    'configuration' => [...],
    'stats' => [...],
    'health' => [...],
    'environment' => 'local',
    'laravel_version' => '10.x'
]
*/

// Útil para troubleshooting
if (app()->environment('local')) {
    dump(ClubifyCheckout::debug());
}
```

## 💡 Exemplos Práticos

### E-commerce Completo

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout;
use Illuminate\Http\Request;

class EcommerceController extends Controller
{
    public function checkout(Request $request)
    {
        // Validação integrada
        $validated = ClubifyCheckout::validate($request->all(), [
            'products' => 'required|array',
            'customer.email' => 'required|email',
            'customer.name' => 'required|string'
        ]);

        // Buscar ou criar cliente
        $customer = ClubifyCheckout::findOrCreateCustomer($validated['customer']);

        // Criar sessão de checkout
        $session = ClubifyCheckout::createSession([
            'organization_id' => config('clubify-checkout.tenant_id'),
            'customer_id' => $customer['id'],
            'source' => 'website'
        ]);

        // Adicionar produtos ao carrinho
        foreach ($validated['products'] as $product) {
            ClubifyCheckout::checkout()->addToCart($session['cart_id'], $product);
        }

        // Cache dos totais
        $totals = ClubifyCheckout::cached("cart_totals_{$session['cart_id']}", function () use ($session) {
            return ClubifyCheckout::checkout()->calculateCartTotals($session['cart_id']);
        }, 300); // 5 minutos

        return response()->json([
            'session' => $session,
            'customer' => $customer,
            'totals' => $totals
        ]);
    }

    public function payment(Request $request)
    {
        $validated = ClubifyCheckout::validate($request->all(), [
            'session_id' => 'required|string',
            'payment_method' => 'required|in:credit_card,pix,boleto',
            'card_token' => 'required_if:payment_method,credit_card'
        ]);

        try {
            // Processar pagamento
            $payment = ClubifyCheckout::processPayment([
                'session_id' => $validated['session_id'],
                'method' => $validated['payment_method'],
                'card' => ['token' => $validated['card_token'] ?? null]
            ]);

            // Log da operação
            ClubifyCheckout::log('info', 'Payment processed', [
                'payment_id' => $payment['id'],
                'amount' => $payment['amount'],
                'method' => $payment['method'],
                'status' => $payment['status']
            ]);

            // Disparar evento Laravel
            ClubifyCheckout::event('payment.completed', [
                'payment' => $payment,
                'session_id' => $validated['session_id']
            ]);

            return response()->json(['payment' => $payment]);

        } catch (\Exception $e) {
            ClubifyCheckout::log('error', 'Payment failed', [
                'error' => $e->getMessage(),
                'session_id' => $validated['session_id']
            ]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### Dashboard Analytics

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout;

class DashboardController extends Controller
{
    public function analytics()
    {
        // Cache de analytics pesado
        $analytics = ClubifyCheckout::cached('dashboard_analytics', function () {
            return [
                'payments_today' => $this->getPaymentsToday(),
                'top_customers' => $this->getTopCustomers(),
                'conversion_rates' => $this->getConversionRates(),
                'webhook_health' => $this->getWebhookHealth()
            ];
        }, 900); // 15 minutos

        return view('dashboard.analytics', compact('analytics'));
    }

    private function getPaymentsToday()
    {
        $stats = ClubifyCheckout::payments()->getPaymentStats([
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString()
        ]);

        return [
            'count' => $stats['total_count'],
            'amount' => $stats['total_amount'],
            'success_rate' => $stats['success_rate']
        ];
    }

    private function getTopCustomers()
    {
        return ClubifyCheckout::customers()->segmentCustomers([
            'criteria' => [
                'lifetime_value' => ['min' => 50000] // R$ 500+
            ],
            'limit' => 10,
            'order_by' => 'lifetime_value',
            'order_direction' => 'desc'
        ]);
    }

    private function getConversionRates()
    {
        $checkoutStats = ClubifyCheckout::checkout()->getStats();

        return [
            'session_to_payment' => $checkoutStats['conversion_rate'],
            'cart_abandonment' => $checkoutStats['abandonment_rate']
        ];
    }

    private function getWebhookHealth()
    {
        return ClubifyCheckout::webhooks()->getStats();
    }
}
```

### Command Line Interface

```php
<?php

namespace App\Console\Commands;

use ClubifyCheckout;
use Illuminate\Console\Command;

class ClubifyHealthCheckCommand extends Command
{
    protected $signature = 'clubify:health-check';
    protected $description = 'Check Clubify Checkout SDK health';

    public function handle()
    {
        $this->info('Checking Clubify Checkout SDK health...');

        // Debug completo
        $debug = ClubifyCheckout::debug();

        $this->table(['Metric', 'Value'], [
            ['SDK Version', $debug['sdk_version']],
            ['Environment', $debug['environment']],
            ['Laravel Version', $debug['laravel_version']]
        ]);

        // Health check
        $health = ClubifyCheckout::healthCheck();

        if ($health['overall_healthy']) {
            $this->info('✅ All systems healthy');
        } else {
            $this->error('❌ Some systems are unhealthy');
        }

        // Status dos módulos
        $this->info('Module Status:');
        foreach ($health['modules'] as $module => $status) {
            $icon = $status['healthy'] ? '✅' : '❌';
            $this->line("  {$icon} {$module}");
        }

        // Estatísticas
        $stats = ClubifyCheckout::getStats();
        $this->info("Total operations: {$stats['total_operations']}");
        $this->info("Cache hit rate: {$stats['cache_hit_rate']}%");
        $this->info("Average response time: {$stats['avg_response_time']}ms");

        return 0;
    }
}
```

## 🔍 Debug e Troubleshooting

### Verificar Facade

```php
// Verificar se facade está registrada
if (class_exists(ClubifyCheckout::class)) {
    echo "✅ Facade registrada\n";
} else {
    echo "❌ Facade não registrada\n";
}

// Verificar binding no container
if (app()->bound(ClubifyCheckoutSDK::class)) {
    echo "✅ SDK registrado no container\n";
} else {
    echo "❌ SDK não registrado\n";
}
```

### Debug de Configuração

```php
// Debug completo
$debug = ClubifyCheckout::debug();
dd($debug);

// Verificar configuração específica
$config = ClubifyCheckout::getConfiguration();
echo "API Key: " . (isset($config['api_key']) ? 'Configurada' : 'Não configurada') . "\n";
echo "Environment: " . $config['environment'] . "\n";
```

### Performance Monitoring

```php
// Monitorar performance da facade
$startTime = microtime(true);

$result = ClubifyCheckout::processPayment($paymentData);

$duration = microtime(true) - $startTime;
ClubifyCheckout::log('debug', 'Facade operation completed', [
    'operation' => 'processPayment',
    'duration_ms' => round($duration * 1000, 2),
    'result_status' => $result['status'] ?? 'unknown'
]);
```

---

**Desenvolvido com ❤️ seguindo os padrões Laravel Facade e melhores práticas de developer experience.**