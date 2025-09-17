# 🚀 Laravel Service Provider - Documentação Completa

## Visão Geral

O **ClubifyCheckoutServiceProvider** é o coração da integração Laravel, responsável por registrar todos os serviços do SDK, configurar dependency injection, middleware, comandos Artisan e event listeners. Oferece uma integração nativa e transparente com o framework Laravel.

### 🎯 Funcionalidades Principais

- **Dependency Injection Completa**: Registro automático de todos os serviços do SDK
- **Configuração Centralizada**: Sistema unificado de configuração via `config/clubify-checkout.php`
- **Asset Publishing**: Publicação de configurações, traduções e stubs
- **Middleware Integration**: Registro automático de middleware de autenticação e webhook
- **Commands Artisan**: Comandos para instalação, publicação e sincronização
- **Event Listeners**: Integração com sistema de eventos do Laravel
- **Lazy Loading**: Carregamento otimizado de recursos sob demanda

### 🏗️ Arquitetura de Integração

```
ClubifyCheckoutServiceProvider
├── Core Services Registration
│   ├── Configuration
│   ├── Logger
│   ├── Cache Manager
│   ├── Event Dispatcher
│   ├── Auth Manager
│   └── HTTP Client
├── SDK Registration
│   └── ClubifyCheckoutSDK (Singleton)
├── Laravel Integration
│   ├── Middleware Registration
│   ├── Commands Registration
│   ├── Asset Publishing
│   └── Event Listeners
└── Container Aliases
    └── 'clubify-checkout' → ClubifyCheckoutSDK
```

## 📚 Registros de Serviços

### Core Services

#### ConfigurationInterface
```php
// Registrado como singleton
$this->app->singleton(ConfigurationInterface::class, function (Container $app): Configuration {
    $config = $app['config']['clubify-checkout'] ?? [];
    return new Configuration($config);
});
```

**Uso:**
```php
// Via Dependency Injection
public function __construct(ConfigurationInterface $config)
{
    $this->config = $config;
}

// Via Container
$config = app(ConfigurationInterface::class);
$apiKey = $config->getApiKey();
```

#### LoggerInterface
```php
// Integrado com sistema de logging do Laravel
$this->app->singleton(LoggerInterface::class, function (Container $app): Logger {
    $config = $app[ConfigurationInterface::class];
    return new Logger($config->getLoggerConfig());
});
```

**Uso:**
```php
// Via Dependency Injection
public function __construct(LoggerInterface $logger)
{
    $this->logger = $logger;
}

// Via Container
$logger = app(LoggerInterface::class);
$logger->info('Operação realizada', ['data' => $data]);
```

#### CacheManagerInterface
```php
// Integrado com sistema de cache do Laravel
$this->app->singleton(CacheManagerInterface::class, function (Container $app): CacheManager {
    $config = $app[ConfigurationInterface::class];
    return new CacheManager($config->getCacheConfig());
});
```

**Uso:**
```php
// Via Dependency Injection
public function __construct(CacheManagerInterface $cache)
{
    $this->cache = $cache;
}

// Uso direto
$cache = app(CacheManagerInterface::class);
$cache->set('key', $value, 3600);
```

### SDK Principal

#### ClubifyCheckoutSDK
```php
// Registrado como singleton principal
$this->app->singleton(ClubifyCheckoutSDK::class, function (Container $app): ClubifyCheckoutSDK {
    $config = $app[ConfigurationInterface::class];
    $sdk = new ClubifyCheckoutSDK($config->toArray());
    $sdk->initialize();
    return $sdk;
});

// Alias para facilitar uso
$this->app->alias(ClubifyCheckoutSDK::class, 'clubify-checkout');
```

**Uso:**
```php
// Via Dependency Injection (Recomendado)
public function __construct(ClubifyCheckoutSDK $sdk)
{
    $this->sdk = $sdk;
}

// Via Container
$sdk = app(ClubifyCheckoutSDK::class);
$sdk = app('clubify-checkout');

// Via Facade
use ClubifyCheckout;
$result = ClubifyCheckout::processPayment($paymentData);
```

## ⚙️ Configuração do Service Provider

### Arquivo de Configuração

O service provider publica e carrega o arquivo `config/clubify-checkout.php`:

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Clubify Checkout API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('CLUBIFY_CHECKOUT_API_KEY'),
    'api_secret' => env('CLUBIFY_CHECKOUT_API_SECRET'),
    'tenant_id' => env('CLUBIFY_CHECKOUT_TENANT_ID'),
    'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    */
    'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', 'https://api.clubify.com.br'),
    'webhook_base_url' => env('CLUBIFY_CHECKOUT_WEBHOOK_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'driver' => env('CLUBIFY_CHECKOUT_CACHE_DRIVER', 'redis'),
        'prefix' => env('CLUBIFY_CHECKOUT_CACHE_PREFIX', 'clubify_checkout'),
        'ttl' => env('CLUBIFY_CHECKOUT_CACHE_TTL', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channel' => env('CLUBIFY_CHECKOUT_LOG_CHANNEL', 'single'),
        'level' => env('CLUBIFY_CHECKOUT_LOG_LEVEL', 'info'),
        'path' => storage_path('logs/clubify-checkout.log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules Configuration
    |--------------------------------------------------------------------------
    */
    'modules' => [
        'organization' => [
            'cache_ttl' => 3600,
            'max_tenants_per_org' => 100,
        ],
        'products' => [
            'cache_ttl' => 1800,
            'max_products_per_page' => 50,
        ],
        'checkout' => [
            'session_ttl' => 1800,
            'cart_persistence' => true,
        ],
        'payments' => [
            'default_gateway' => env('CLUBIFY_CHECKOUT_DEFAULT_GATEWAY', 'stripe'),
            'retry_attempts' => 3,
        ],
        'customers' => [
            'enable_matching' => true,
            'gdpr_compliance' => true,
        ],
        'webhooks' => [
            'timeout' => 30,
            'max_retries' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Laravel Integration
    |--------------------------------------------------------------------------
    */
    'laravel' => [
        'middleware' => [
            'auth_enabled' => true,
            'webhook_validation' => true,
        ],
        'commands' => [
            'auto_discover' => true,
        ],
        'jobs' => [
            'queue' => env('CLUBIFY_CHECKOUT_QUEUE', 'default'),
            'retry_after' => 300,
        ],
    ],
];
```

### Variáveis de Ambiente (.env)

```env
# Configurações obrigatórias
CLUBIFY_CHECKOUT_API_KEY=your-api-key
CLUBIFY_CHECKOUT_API_SECRET=your-api-secret
CLUBIFY_CHECKOUT_TENANT_ID=your-tenant-id
CLUBIFY_CHECKOUT_ENVIRONMENT=production

# URLs (opcional - padrões inteligentes)
CLUBIFY_CHECKOUT_BASE_URL=https://api.clubify.com.br
CLUBIFY_CHECKOUT_WEBHOOK_BASE_URL=https://seusite.com

# Cache (opcional)
CLUBIFY_CHECKOUT_CACHE_DRIVER=redis
CLUBIFY_CHECKOUT_CACHE_PREFIX=clubify_checkout
CLUBIFY_CHECKOUT_CACHE_TTL=3600

# Logging (opcional)
CLUBIFY_CHECKOUT_LOG_CHANNEL=stack
CLUBIFY_CHECKOUT_LOG_LEVEL=info

# Gateway padrão (opcional)
CLUBIFY_CHECKOUT_DEFAULT_GATEWAY=stripe

# Queue para jobs (opcional)
CLUBIFY_CHECKOUT_QUEUE=clubify_checkout
```

## 🛠️ Asset Publishing

### Publicar Configuração

```bash
# Publicar apenas configuração
php artisan vendor:publish --tag=clubify-checkout-config

# Publicar traduções
php artisan vendor:publish --tag=clubify-checkout-lang

# Publicar stubs/templates
php artisan vendor:publish --tag=clubify-checkout-stubs

# Publicar tudo
php artisan vendor:publish --tag=clubify-checkout
```

### Estrutura Publicada

```
config/
└── clubify-checkout.php

resources/
├── lang/vendor/clubify-checkout/
│   ├── en/
│   │   ├── validation.php
│   │   ├── messages.php
│   │   └── errors.php
│   └── pt-BR/
│       ├── validation.php
│       ├── messages.php
│       └── errors.php
└── stubs/vendor/clubify-checkout/
    ├── controller.stub
    ├── middleware.stub
    └── job.stub
```

## 🔧 Middleware Registration

### Middleware Automático

O service provider registra automaticamente dois middleware:

#### AuthenticateSDK Middleware
```php
// Registrado como 'clubify.auth'
$router->aliasMiddleware('clubify.auth', AuthenticateSDK::class);

// Aplicado automaticamente ao grupo 'web'
$router->pushMiddlewareToGroup('web', AuthenticateSDK::class);
```

**Uso em Rotas:**
```php
// Aplicar explicitamente
Route::middleware('clubify.auth')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'process']);
    Route::post('/payments', [PaymentController::class, 'create']);
});

// Em Controller
public function __construct()
{
    $this->middleware('clubify.auth');
}
```

#### ValidateWebhook Middleware
```php
// Registrado como 'clubify.webhook'
$router->aliasMiddleware('clubify.webhook', ValidateWebhook::class);
```

**Uso para Webhooks:**
```php
// Rota de webhook com validação
Route::post('/webhook/clubify', [WebhookController::class, 'handle'])
    ->middleware('clubify.webhook');
```

## 📡 Event Listeners

### Listeners Automáticos

O service provider registra listeners para integração com eventos do Laravel:

#### SDK Events Listener
```php
// Escuta todos os eventos do SDK
$events->listen('clubify.checkout.*', function (string $event, array $data): void {
    $this->app[LoggerInterface::class]->info("SDK Event: {$event}", $data);
});
```

#### Metrics Listener
```php
// Escuta eventos de métricas
$events->listen('clubify.checkout.metrics.*', function (string $event, array $data): void {
    // Integração com Laravel Telescope ou sistemas de métricas
    $this->app[LoggerInterface::class]->debug("SDK Metrics: {$event}", $data);
});
```

### Eventos Disponíveis

```php
// Eventos de organização
'clubify.checkout.organization.created'
'clubify.checkout.organization.updated'

// Eventos de produtos
'clubify.checkout.product.created'
'clubify.checkout.product.updated'

// Eventos de checkout
'clubify.checkout.session.created'
'clubify.checkout.session.completed'

// Eventos de pagamentos
'clubify.checkout.payment.processed'
'clubify.checkout.payment.approved'
'clubify.checkout.payment.failed'

// Eventos de clientes
'clubify.checkout.customer.created'
'clubify.checkout.customer.updated'

// Eventos de webhooks
'clubify.checkout.webhook.delivered'
'clubify.checkout.webhook.failed'

// Eventos de métricas
'clubify.checkout.metrics.performance'
'clubify.checkout.metrics.error'
```

## 🎨 Exemplos de Uso

### Controller com Dependency Injection

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout\ClubifyCheckoutSDK;
use ClubifyCheckout\Core\Config\ConfigurationInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private ConfigurationInterface $config
    ) {
        // Middleware aplicado automaticamente via service provider
    }

    public function createSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_data' => 'required|array',
            'products' => 'required|array',
        ]);

        try {
            // Criar sessão de checkout
            $session = $this->sdk->checkout()->createSession(
                $this->config->getTenantId(),
                $validated['customer_data']
            );

            // Adicionar produtos ao carrinho
            $cart = $this->sdk->checkout()->createCart($session['id']);

            foreach ($validated['products'] as $product) {
                $this->sdk->checkout()->addToCart($cart['id'], $product);
            }

            return response()->json([
                'success' => true,
                'session' => $session,
                'cart' => $cart
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function processPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_id' => 'required|string',
            'payment_data' => 'required|array',
        ]);

        try {
            $payment = $this->sdk->payments()->processPayment([
                'session_id' => $validated['session_id'],
                ...$validated['payment_data']
            ]);

            // Completar sessão se pagamento aprovado
            if ($payment['status'] === 'approved') {
                $this->sdk->checkout()->completeSession($validated['session_id']);
            }

            return response()->json([
                'success' => true,
                'payment' => $payment
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
```

### Service Class com Container

```php
<?php

namespace App\Services;

use ClubifyCheckout\ClubifyCheckoutSDK;

class SubscriptionService
{
    private ClubifyCheckoutSDK $sdk;

    public function __construct()
    {
        // Resolver do container
        $this->sdk = app('clubify-checkout');
    }

    public function createSubscription(array $subscriptionData): array
    {
        // Criar cliente se não existir
        $customer = $this->sdk->customers()->findOrCreateCustomer([
            'name' => $subscriptionData['customer_name'],
            'email' => $subscriptionData['customer_email'],
        ]);

        // Criar produto de assinatura
        $product = $this->sdk->products()->getProductService()->create([
            'name' => $subscriptionData['plan_name'],
            'type' => 'subscription',
            'price' => $subscriptionData['price'],
            'subscription' => [
                'billing_cycle' => $subscriptionData['billing_cycle'],
                'trial_days' => $subscriptionData['trial_days'] ?? 0
            ]
        ]);

        // Processar primeiro pagamento
        $payment = $this->sdk->payments()->processPayment([
            'amount' => $subscriptionData['price'],
            'currency' => 'BRL',
            'method' => 'credit_card',
            'customer_id' => $customer['id'],
            'product_id' => $product['id'],
            'card' => $subscriptionData['card_data']
        ]);

        return [
            'customer' => $customer,
            'product' => $product,
            'payment' => $payment
        ];
    }
}
```

### Webhook Handler

```php
<?php

namespace App\Http\Controllers;

use ClubifyCheckout\ClubifyCheckoutSDK;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
        // Middleware de validação aplicado via rota
    }

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature');

        // Validação já feita pelo middleware ValidateWebhook

        $event = json_decode($payload, true);

        switch ($event['type']) {
            case 'payment.approved':
                $this->handlePaymentApproved($event['data']);
                break;

            case 'payment.failed':
                $this->handlePaymentFailed($event['data']);
                break;

            case 'subscription.cancelled':
                $this->handleSubscriptionCancelled($event['data']);
                break;
        }

        return response('OK', 200);
    }

    private function handlePaymentApproved(array $paymentData): void
    {
        // Atualizar pedido local
        // Liberar acesso ao produto
        // Enviar email de confirmação

        logger('Payment approved', $paymentData);
    }

    private function handlePaymentFailed(array $paymentData): void
    {
        // Marcar pedido como falhou
        // Notificar cliente
        // Tentar gateway alternativo

        logger('Payment failed', $paymentData);
    }
}
```

## 🔍 Debug e Troubleshooting

### Verificar Registro de Serviços

```php
// Verificar se SDK está registrado
if (app()->bound(ClubifyCheckoutSDK::class)) {
    echo "✅ SDK registrado corretamente\n";
} else {
    echo "❌ SDK não registrado\n";
}

// Verificar configuração
$config = app(ConfigurationInterface::class);
echo "API Key: " . ($config->getApiKey() ? "Configurada" : "Não configurada") . "\n";
echo "Environment: " . $config->getEnvironment() . "\n";

// Testar SDK
try {
    $sdk = app('clubify-checkout');
    $stats = $sdk->getHealthCheck();
    echo "✅ SDK funcionando: " . json_encode($stats) . "\n";
} catch (\Exception $e) {
    echo "❌ Erro no SDK: " . $e->getMessage() . "\n";
}
```

### Verificar Middleware

```php
// Verificar middleware registrado
$router = app('router');
$middleware = $router->getMiddleware();

if (isset($middleware['clubify.auth'])) {
    echo "✅ Middleware de autenticação registrado\n";
}

if (isset($middleware['clubify.webhook'])) {
    echo "✅ Middleware de webhook registrado\n";
}
```

### Logs de Debug

```php
// Ativar logs detalhados no .env
CLUBIFY_CHECKOUT_LOG_LEVEL=debug

// Verificar logs
tail -f storage/logs/clubify-checkout.log
```

## ⚡ Performance e Otimização

### Cache Optimization

```php
// Configurar cache Redis para melhor performance
'cache' => [
    'driver' => 'redis',
    'connection' => 'clubify_checkout',
    'ttl' => 3600,
],

// No config/database.php adicionar conexão dedicada
'redis' => [
    'clubify_checkout' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_CLUBIFY_DB', 2),
    ],
],
```

### Lazy Loading

```php
// Todos os serviços são registrados como singletons
// e só são instanciados quando necessário

// ✅ Eficiente - só carrega quando usado
$sdk = app(ClubifyCheckoutSDK::class);
$result = $sdk->payments()->processPayment($data);

// ❌ Evitar - carrega desnecessariamente
$sdk = app(ClubifyCheckoutSDK::class);
// Não usar $sdk aqui
```

### Memory Management

```php
// O service provider otimiza o uso de memória através de:
// 1. Singletons para evitar múltiplas instâncias
// 2. Lazy loading para carregar apenas quando necessário
// 3. Cache inteligente para reduzir chamadas à API
// 4. Cleanup automático de recursos não utilizados
```

---

**Desenvolvido com ❤️ seguindo os padrões Laravel e melhores práticas de Service Provider.**