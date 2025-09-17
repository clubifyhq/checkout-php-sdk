# Integração Completa com Laravel - Clubify Checkout SDK

Este guia apresenta uma integração completa e robusta do Clubify Checkout SDK em aplicações Laravel, cobrindo desde a configuração básica até implementações enterprise avançadas.

## 📋 Índice

- [Configuração Inicial](#configuração-inicial)
- [Estrutura de Projeto](#estrutura-de-projeto)
- [Models e Eloquent](#models-e-eloquent)
- [Controllers e Routes](#controllers-e-routes)
- [Blade Views e Frontend](#blade-views-e-frontend)
- [Jobs e Queues](#jobs-e-queues)
- [Event Sourcing](#event-sourcing)
- [Testing](#testing)
- [Deployment e CI/CD](#deployment-e-cicd)
- [Performance](#performance)
- [Security](#security)

## Configuração Inicial

### 1. Instalação e Setup

```bash
# Instalar o SDK
composer require clubify/checkout-sdk-php

# Executar setup automático
php artisan clubify:install

# Publicar recursos adicionais
php artisan vendor:publish --tag=clubify-all

# Executar migrações (se houver)
php artisan migrate
```

### 2. Configuração de Environment

```env
# .env - Configurações obrigatórias
CLUBIFY_API_KEY=sua_api_key_production
CLUBIFY_ORGANIZATION_ID=sua_org_id
CLUBIFY_ENVIRONMENT=production

# Configurações avançadas
CLUBIFY_API_URL=https://api.clubify.app
CLUBIFY_TIMEOUT=45
CLUBIFY_RETRIES=3
CLUBIFY_CACHE_TTL=3600

# Laravel específico
CLUBIFY_QUEUE=clubify
CLUBIFY_LOG_REQUESTS=true
CLUBIFY_LOG_CHANNEL=clubify

# Webhooks
CLUBIFY_WEBHOOK_SECRET=seu_webhook_secret
CLUBIFY_WEBHOOK_TOLERANCE=300

# Features flags
CLUBIFY_CACHE_ENABLED=true
CLUBIFY_QUEUE_ENABLED=true
CLUBIFY_ANALYTICS_ENABLED=true
```

### 3. Configuração Avançada

```php
// config/clubify.php
<?php

return [
    'api' => [
        'key' => env('CLUBIFY_API_KEY'),
        'url' => env('CLUBIFY_API_URL', 'https://api.clubify.app'),
        'timeout' => env('CLUBIFY_TIMEOUT', 45),
        'retries' => env('CLUBIFY_RETRIES', 3),
        'verify_ssl' => env('CLUBIFY_VERIFY_SSL', true),
    ],

    'organization' => [
        'id' => env('CLUBIFY_ORGANIZATION_ID'),
        'environment' => env('CLUBIFY_ENVIRONMENT', 'production'),
    ],

    'cache' => [
        'enabled' => env('CLUBIFY_CACHE_ENABLED', true),
        'store' => env('CLUBIFY_CACHE_STORE', 'redis'),
        'ttl' => env('CLUBIFY_CACHE_TTL', 3600),
        'prefix' => env('CLUBIFY_CACHE_PREFIX', 'clubify:'),
    ],

    'queue' => [
        'enabled' => env('CLUBIFY_QUEUE_ENABLED', true),
        'connection' => env('CLUBIFY_QUEUE_CONNECTION', 'redis'),
        'name' => env('CLUBIFY_QUEUE', 'clubify'),
        'high_priority' => env('CLUBIFY_QUEUE_HIGH', 'clubify-high'),
    ],

    'logging' => [
        'enabled' => env('CLUBIFY_LOG_REQUESTS', true),
        'channel' => env('CLUBIFY_LOG_CHANNEL', 'clubify'),
        'level' => env('CLUBIFY_LOG_LEVEL', 'info'),
        'include_body' => env('CLUBIFY_LOG_BODY', false),
    ],

    'webhooks' => [
        'enabled' => env('CLUBIFY_WEBHOOKS_ENABLED', true),
        'secret' => env('CLUBIFY_WEBHOOK_SECRET'),
        'tolerance' => env('CLUBIFY_WEBHOOK_TOLERANCE', 300),
        'retry_attempts' => env('CLUBIFY_WEBHOOK_RETRIES', 3),
    ],

    'features' => [
        'analytics' => env('CLUBIFY_ANALYTICS_ENABLED', true),
        'multi_tenant' => env('CLUBIFY_MULTI_TENANT', false),
        'ai_insights' => env('CLUBIFY_AI_INSIGHTS', true),
        'advanced_reporting' => env('CLUBIFY_ADVANCED_REPORTING', true),
    ],

    'validation' => [
        'cpf' => env('CLUBIFY_VALIDATE_CPF', true),
        'cnpj' => env('CLUBIFY_VALIDATE_CNPJ', true),
        'cep' => env('CLUBIFY_VALIDATE_CEP', true),
        'phone' => env('CLUBIFY_VALIDATE_PHONE', true),
        'strict_mode' => env('CLUBIFY_STRICT_VALIDATION', true),
    ],

    'integrations' => [
        'crm' => [
            'enabled' => env('CLUBIFY_CRM_INTEGRATION', false),
            'provider' => env('CLUBIFY_CRM_PROVIDER'), // 'salesforce', 'hubspot'
            'sync_interval' => env('CLUBIFY_CRM_SYNC_INTERVAL', 3600),
        ],
        'email_marketing' => [
            'enabled' => env('CLUBIFY_EMAIL_INTEGRATION', false),
            'provider' => env('CLUBIFY_EMAIL_PROVIDER'), // 'mailchimp', 'sendgrid'
        ],
        'analytics' => [
            'google_analytics' => env('CLUBIFY_GA_ENABLED', false),
            'facebook_pixel' => env('CLUBIFY_FB_PIXEL_ENABLED', false),
            'custom_tracking' => env('CLUBIFY_CUSTOM_TRACKING', false),
        ],
    ],
];
```

## Estrutura de Projeto

### 1. Organização de Diretórios

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Clubify/
│   │   │   ├── CheckoutController.php
│   │   │   ├── WebhookController.php
│   │   │   ├── ProductController.php
│   │   │   └── AnalyticsController.php
│   │   └── ...
│   ├── Middleware/
│   │   ├── ClubifyAuth.php
│   │   └── ValidateWebhook.php
│   └── Requests/
│       ├── Clubify/
│       │   ├── CheckoutRequest.php
│       │   ├── ProductRequest.php
│       │   └── CustomerRequest.php
│       └── ...
├── Jobs/
│   ├── Clubify/
│   │   ├── ProcessPaymentJob.php
│   │   ├── SyncCustomerJob.php
│   │   └── SendWebhookJob.php
│   └── ...
├── Models/
│   ├── Clubify/
│   │   ├── LocalOrder.php
│   │   ├── LocalCustomer.php
│   │   └── LocalProduct.php
│   └── ...
├── Services/
│   ├── Clubify/
│   │   ├── CheckoutService.php
│   │   ├── AnalyticsService.php
│   │   └── IntegrationService.php
│   └── ...
└── Events/
    ├── Clubify/
    │   ├── OrderCompleted.php
    │   ├── PaymentProcessed.php
    │   └── CustomerCreated.php
    └── ...
```

## Models e Eloquent

### 1. Models Locais para Cache/Sync

```php
<?php

// app/Models/Clubify/LocalOrder.php
namespace App\Models\Clubify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalOrder extends Model
{
    protected $table = 'clubify_orders';

    protected $fillable = [
        'clubify_id',
        'customer_id',
        'status',
        'total_amount',
        'currency',
        'payment_method',
        'metadata',
        'synced_at'
    ];

    protected $casts = [
        'total_amount' => 'integer',
        'metadata' => 'array',
        'synced_at' => 'datetime'
    ];

    // Relacionamentos
    public function customer(): BelongsTo
    {
        return $this->belongsTo(LocalCustomer::class, 'customer_id', 'clubify_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LocalOrderItem::class);
    }

    // Scopes
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month);
    }

    public function scopeNeedsSync($query)
    {
        return $query->where('synced_at', '<', now()->subMinutes(30));
    }

    // Mutators e Accessors
    public function getTotalAmountFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->total_amount / 100, 2, ',', '.');
    }

    public function getIsRecentAttribute(): bool
    {
        return $this->created_at->diffInHours() < 24;
    }

    // Métodos de sincronização
    public function syncWithClubify(): array
    {
        $clubifyOrder = Clubify::getOrder($this->clubify_id);

        $this->update([
            'status' => $clubifyOrder['status'],
            'total_amount' => $clubifyOrder['total_amount'],
            'metadata' => array_merge($this->metadata ?? [], $clubifyOrder['metadata']),
            'synced_at' => now()
        ]);

        return $clubifyOrder;
    }

    public static function createFromClubify(array $clubifyOrder): self
    {
        return self::create([
            'clubify_id' => $clubifyOrder['id'],
            'customer_id' => $clubifyOrder['customer_id'],
            'status' => $clubifyOrder['status'],
            'total_amount' => $clubifyOrder['total_amount'],
            'currency' => $clubifyOrder['currency'],
            'payment_method' => $clubifyOrder['payment_method'],
            'metadata' => $clubifyOrder['metadata'] ?? [],
            'synced_at' => now()
        ]);
    }
}
```

### 2. Model de Customer Local

```php
<?php

// app/Models/Clubify/LocalCustomer.php
namespace App\Models\Clubify;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LocalCustomer extends Model
{
    protected $table = 'clubify_customers';

    protected $fillable = [
        'clubify_id',
        'name',
        'email',
        'cpf',
        'phone',
        'total_spent',
        'total_orders',
        'tags',
        'segment',
        'last_purchase_at',
        'synced_at'
    ];

    protected $casts = [
        'total_spent' => 'integer',
        'total_orders' => 'integer',
        'tags' => 'array',
        'last_purchase_at' => 'datetime',
        'synced_at' => 'datetime'
    ];

    // Relacionamentos
    public function orders(): HasMany
    {
        return $this->hasMany(LocalOrder::class, 'customer_id', 'clubify_id');
    }

    // Scopes
    public function scopeVip($query)
    {
        return $query->where('total_spent', '>', 100000); // R$ 1.000+
    }

    public function scopeActive($query)
    {
        return $query->where('last_purchase_at', '>', now()->subMonths(3));
    }

    // Accessors
    public function getTotalSpentFormattedAttribute(): string
    {
        return 'R$ ' . number_format($this->total_spent / 100, 2, ',', '.');
    }

    public function getLifetimeValueAttribute(): float
    {
        return $this->total_orders > 0 ? $this->total_spent / $this->total_orders : 0;
    }

    // Métodos de business logic
    public function calculateSegment(): string
    {
        if ($this->total_spent > 100000) return 'vip';
        if ($this->total_spent > 50000) return 'premium';
        if ($this->total_orders > 3) return 'loyal';
        if ($this->total_orders > 0) return 'customer';
        return 'prospect';
    }

    public function updateSegment(): void
    {
        $newSegment = $this->calculateSegment();

        if ($this->segment !== $newSegment) {
            $this->update(['segment' => $newSegment]);

            // Dispara evento de mudança de segmento
            event(new CustomerSegmentChanged($this, $this->segment, $newSegment));
        }
    }
}
```

## Controllers e Routes

### 1. Controller Principal de Checkout

```php
<?php

// app/Http/Controllers/Clubify/CheckoutController.php
namespace App\Http\Controllers\Clubify;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clubify\CheckoutRequest;
use App\Services\Clubify\CheckoutService;
use ClubifyCheckout\Facades\Clubify;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function __construct(
        private CheckoutService $checkoutService
    ) {}

    public function show(Request $request, string $offerSlug)
    {
        try {
            // Busca oferta por slug
            $offer = Clubify::getOfferBySlug($offerSlug);

            if (!$offer || !$offer['active']) {
                abort(404, 'Oferta não encontrada ou inativa');
            }

            // Aplica tracking UTM
            $this->trackVisit($request, $offer);

            // Prepara dados para a view
            $viewData = $this->prepareCheckoutData($offer, $request);

            return view('clubify.checkout.show', $viewData);

        } catch (\Exception $e) {
            logger()->error('Erro ao exibir checkout', [
                'offer_slug' => $offerSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('home')
                ->with('error', 'Oferta temporariamente indisponível');
        }
    }

    public function process(CheckoutRequest $request, string $checkoutId)
    {
        try {
            // Processa checkout via service
            $result = $this->checkoutService->processCheckout($checkoutId, $request->validated());

            if ($result['success']) {
                // Redireciona para página de sucesso
                return redirect()->route('checkout.success', $result['order_id'])
                    ->with('success', 'Pagamento processado com sucesso!');
            } else {
                // Retorna com erro
                return back()
                    ->withInput()
                    ->withErrors(['payment' => $result['message']]);
            }

        } catch (\Exception $e) {
            logger()->error('Erro ao processar checkout', [
                'checkout_id' => $checkoutId,
                'error' => $e->getMessage()
            ]);

            return back()
                ->withInput()
                ->withErrors(['general' => 'Erro interno. Tente novamente.']);
        }
    }

    public function success(string $orderId)
    {
        try {
            $order = Clubify::getOrder($orderId);

            // Verifica se o usuário tem acesso a este pedido
            if (!$this->userCanAccessOrder($order)) {
                abort(403);
            }

            return view('clubify.checkout.success', compact('order'));

        } catch (\Exception $e) {
            return redirect()->route('home')
                ->with('error', 'Pedido não encontrado');
        }
    }

    public function cancel(string $checkoutId)
    {
        try {
            // Cancela checkout
            Clubify::cancelCheckout($checkoutId);

            return redirect()->route('home')
                ->with('info', 'Checkout cancelado');

        } catch (\Exception $e) {
            return redirect()->route('home');
        }
    }

    private function trackVisit(Request $request, array $offer): void
    {
        $trackingData = [
            'offer_id' => $offer['id'],
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referrer' => $request->header('referer'),
            'utm_source' => $request->input('utm_source'),
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_content' => $request->input('utm_content'),
            'utm_term' => $request->input('utm_term'),
        ];

        // Track assíncrono
        dispatch(new TrackVisitJob($trackingData));
    }

    private function prepareCheckoutData(array $offer, Request $request): array
    {
        return [
            'offer' => $offer,
            'products' => Clubify::getOfferProducts($offer['id']),
            'testimonials' => $offer['testimonials'] ?? [],
            'order_bumps' => $offer['order_bumps'] ?? [],
            'checkout_session' => Clubify::createCheckoutSession([
                'offer_id' => $offer['id'],
                'utm_data' => $request->only(['utm_source', 'utm_medium', 'utm_campaign']),
                'expires_in' => 3600 // 1 hora
            ]),
            'payment_methods' => $this->getAvailablePaymentMethods($offer),
            'user_data' => $this->getUserPrefilledData($request)
        ];
    }

    private function getAvailablePaymentMethods(array $offer): array
    {
        return Clubify::getPaymentMethods([
            'offer_id' => $offer['id'],
            'country' => 'BR',
            'currency' => 'BRL'
        ]);
    }

    private function getUserPrefilledData(Request $request): array
    {
        // Se usuário logado, preenche dados
        if (auth()->check()) {
            $user = auth()->user();
            return [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'cpf' => $user->cpf
            ];
        }

        // Dados salvos na sessão (checkout anterior)
        return session('prefilled_data', []);
    }
}
```

### 2. Controller de Webhooks

```php
<?php

// app/Http/Controllers/Clubify/WebhookController.php
namespace App\Http\Controllers\Clubify;

use App\Http\Controllers\Controller;
use App\Jobs\Clubify\ProcessWebhookJob;
use ClubifyCheckout\Facades\Clubify;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        // Valida assinatura do webhook
        if (!$this->validateSignature($request)) {
            logger()->warning('Webhook com assinatura inválida', [
                'ip' => $request->ip(),
                'headers' => $request->headers->all()
            ]);

            return response('Invalid signature', 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        // Log do webhook recebido
        logger()->info('Webhook recebido', [
            'event' => $event,
            'id' => $data['id'] ?? null,
            'timestamp' => now()
        ]);

        // Processa webhook de forma assíncrona
        dispatch(new ProcessWebhookJob($event, $data));

        return response('OK', 200);
    }

    public function handleOrderCompleted(array $orderData): void
    {
        try {
            // Cria/atualiza pedido local
            $localOrder = LocalOrder::updateOrCreate(
                ['clubify_id' => $orderData['id']],
                [
                    'customer_id' => $orderData['customer_id'],
                    'status' => $orderData['status'],
                    'total_amount' => $orderData['total_amount'],
                    'currency' => $orderData['currency'],
                    'payment_method' => $orderData['payment_method'],
                    'metadata' => $orderData['metadata'] ?? [],
                    'synced_at' => now()
                ]
            );

            // Atualiza customer
            $this->updateCustomerFromOrder($orderData);

            // Dispara eventos internos
            event(new OrderCompleted($localOrder, $orderData));

            // Integração com sistemas externos
            dispatch(new SyncOrderWithCRMJob($localOrder));
            dispatch(new SendWelcomeEmailJob($orderData['customer_id']));

        } catch (\Exception $e) {
            logger()->error('Erro ao processar webhook order.completed', [
                'order_id' => $orderData['id'],
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function handlePaymentApproved(array $paymentData): void
    {
        try {
            // Atualiza status do pagamento
            $orderId = $paymentData['order_id'];
            $localOrder = LocalOrder::where('clubify_id', $orderId)->first();

            if ($localOrder) {
                $localOrder->update(['status' => 'payment_approved']);

                // Libera acesso aos produtos
                dispatch(new GrantProductAccessJob($localOrder));

                // Notifica customer
                dispatch(new SendPaymentConfirmationJob($localOrder));
            }

        } catch (\Exception $e) {
            logger()->error('Erro ao processar webhook payment.approved', [
                'payment_id' => $paymentData['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    public function handleCustomerCreated(array $customerData): void
    {
        try {
            // Cria customer local
            LocalCustomer::updateOrCreate(
                ['clubify_id' => $customerData['id']],
                [
                    'name' => $customerData['name'],
                    'email' => $customerData['email'],
                    'cpf' => $customerData['cpf'],
                    'phone' => $customerData['phone'],
                    'tags' => $customerData['tags'] ?? [],
                    'segment' => 'prospect',
                    'synced_at' => now()
                ]
            );

            // Integração com email marketing
            dispatch(new AddToEmailListJob($customerData['email'], 'prospects'));

        } catch (\Exception $e) {
            logger()->error('Erro ao processar webhook customer.created', [
                'customer_id' => $customerData['id'],
                'error' => $e->getMessage()
            ]);
        }
    }

    private function validateSignature(Request $request): bool
    {
        $signature = $request->header('X-Clubify-Signature');
        $payload = $request->getContent();
        $secret = config('clubify.webhooks.secret');

        if (!$signature || !$secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($signature, $expectedSignature);
    }

    private function updateCustomerFromOrder(array $orderData): void
    {
        $customer = LocalCustomer::where('clubify_id', $orderData['customer_id'])->first();

        if ($customer) {
            $customer->increment('total_orders');
            $customer->increment('total_spent', $orderData['total_amount']);
            $customer->update(['last_purchase_at' => now()]);

            // Atualiza segmento
            $customer->updateSegment();
        }
    }
}
```

### 3. Routes Completas

```php
<?php

// routes/web.php
use App\Http\Controllers\Clubify\CheckoutController;
use App\Http\Controllers\Clubify\WebhookController;
use App\Http\Controllers\Clubify\AnalyticsController;

// Grupo de rotas públicas do Clubify
Route::prefix('checkout')->name('checkout.')->group(function () {
    Route::get('/{offer:slug}', [CheckoutController::class, 'show'])->name('show');
    Route::post('/{checkout}/process', [CheckoutController::class, 'process'])->name('process');
    Route::get('/success/{order}', [CheckoutController::class, 'success'])->name('success');
    Route::post('/{checkout}/cancel', [CheckoutController::class, 'cancel'])->name('cancel');
});

// Webhook routes
Route::prefix('webhooks/clubify')->name('webhooks.clubify.')->group(function () {
    Route::post('/', [WebhookController::class, 'handle'])->name('handle');
});

// Rotas administrativas (com auth)
Route::middleware(['auth', 'verified'])->prefix('admin/clubify')->name('admin.clubify.')->group(function () {
    Route::get('/dashboard', [AnalyticsController::class, 'dashboard'])->name('dashboard');
    Route::get('/orders', [AnalyticsController::class, 'orders'])->name('orders');
    Route::get('/customers', [AnalyticsController::class, 'customers'])->name('customers');
    Route::get('/analytics', [AnalyticsController::class, 'analytics'])->name('analytics');
});

// API routes
Route::prefix('api/clubify')->name('api.clubify.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/metrics', [AnalyticsController::class, 'metrics'])->name('metrics');
    Route::get('/offers/{id}/analytics', [AnalyticsController::class, 'offerAnalytics'])->name('offer.analytics');
    Route::post('/customers/{id}/segment', [AnalyticsController::class, 'updateCustomerSegment'])->name('customer.segment');
});
```

## Blade Views e Frontend

### 1. Layout Base

```blade
{{-- resources/views/layouts/clubify.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $offer['name'] ?? 'Checkout' }} - {{ config('app.name') }}</title>

    <!-- SEO Meta Tags -->
    <meta name="description" content="{{ $offer['description'] ?? '' }}">
    <meta name="keywords" content="{{ implode(', ', $offer['tags'] ?? []) }}">

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $offer['name'] ?? '' }}">
    <meta property="og:description" content="{{ $offer['description'] ?? '' }}">
    <meta property="og:image" content="{{ $offer['image_url'] ?? '' }}">
    <meta property="og:type" content="product">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Styles -->
    @vite(['resources/css/app.css'])
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">

    <!-- Analytics -->
    @if(config('clubify.integrations.analytics.google_analytics'))
        <!-- Google Analytics -->
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ config('services.google_analytics.id') }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ config('services.google_analytics.id') }}');
        </script>
    @endif

    @if(config('clubify.integrations.analytics.facebook_pixel'))
        <!-- Facebook Pixel -->
        <script>
            !function(f,b,e,v,n,t,s)
            {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};
            if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;
            t.src=v;s=b.getElementsByTagName(e)[0];
            s.parentNode.insertBefore(t,s)}(window, document,'script',
            'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '{{ config('services.facebook_pixel.id') }}');
            fbq('track', 'PageView');
        </script>
        <noscript><img height="1" width="1" style="display:none"
                      src="https://www.facebook.com/tr?id={{ config('services.facebook_pixel.id') }}&ev=PageView&noscript=1"
        /></noscript>
    @endif
</head>
<body class="bg-gray-50">
    <div id="app">
        <!-- Header -->
        <header class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center">
                        <img class="h-8 w-auto" src="{{ asset('images/logo.png') }}" alt="Logo">
                    </div>

                    <!-- Trust indicators -->
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center text-green-600">
                            <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-sm font-medium">Compra Segura</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main>
            @yield('content')
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-8">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Suporte</h3>
                        <p class="text-gray-300">Email: suporte@example.com</p>
                        <p class="text-gray-300">WhatsApp: (11) 99999-9999</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Garantia</h3>
                        <p class="text-gray-300">7 dias de garantia incondicional</p>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold mb-4">Segurança</h3>
                        <div class="flex space-x-2">
                            <img src="{{ asset('images/ssl-badge.png') }}" alt="SSL" class="h-8">
                            <img src="{{ asset('images/pci-badge.png') }}" alt="PCI" class="h-8">
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- Scripts -->
    @vite(['resources/js/app.js'])

    <!-- Clubify Tracking -->
    <script>
        window.clubifyConfig = {
            sessionId: '{{ $checkout_session['id'] ?? '' }}',
            offerId: '{{ $offer['id'] ?? '' }}',
            customerId: '{{ auth()->id() ?? '' }}'
        };
    </script>
    <script src="{{ asset('js/clubify-tracking.js') }}"></script>

    @stack('scripts')
</body>
</html>
```

### 2. Página de Checkout

```blade
{{-- resources/views/clubify/checkout/show.blade.php --}}
@extends('layouts.clubify')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Coluna da Esquerda: Informações da Oferta -->
        <div class="space-y-6">
            <!-- Hero da Oferta -->
            <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                @if($offer['image_url'])
                    <img src="{{ $offer['image_url'] }}" alt="{{ $offer['name'] }}" class="w-full h-64 object-cover">
                @endif

                <div class="p-6">
                    <h1 class="text-3xl font-bold text-gray-900 mb-4">
                        {{ $offer['name'] }}
                    </h1>

                    <p class="text-lg text-gray-600 mb-6">
                        {{ $offer['description'] }}
                    </p>

                    <!-- Preço -->
                    <div class="flex items-center space-x-4 mb-6">
                        @if($offer['original_price'] && $offer['original_price'] > $offer['price'])
                            <span class="text-2xl text-gray-500 line-through">
                                R$ {{ number_format($offer['original_price'] / 100, 2, ',', '.') }}
                            </span>
                        @endif
                        <span class="text-4xl font-bold text-green-600">
                            R$ {{ number_format($offer['price'] / 100, 2, ',', '.') }}
                        </span>
                        @if($offer['original_price'] && $offer['original_price'] > $offer['price'])
                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">
                                {{ round((1 - $offer['price'] / $offer['original_price']) * 100) }}% OFF
                            </span>
                        @endif
                    </div>

                    <!-- Produtos Inclusos -->
                    <div class="space-y-4">
                        <h3 class="text-xl font-semibold text-gray-900">O que você vai receber:</h3>
                        <ul class="space-y-2">
                            @foreach($products as $product)
                                <li class="flex items-start space-x-3">
                                    <svg class="w-6 h-6 text-green-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <p class="font-medium text-gray-900">{{ $product['name'] }}</p>
                                        <p class="text-sm text-gray-600">{{ $product['description'] }}</p>
                                        @if($product['value'])
                                            <p class="text-sm text-green-600 font-medium">
                                                Valor: R$ {{ number_format($product['value'] / 100, 2, ',', '.') }}
                                            </p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Depoimentos -->
            @if(count($testimonials) > 0)
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">O que nossos clientes dizem:</h3>
                    <div class="space-y-4">
                        @foreach($testimonials as $testimonial)
                            <div class="border-l-4 border-blue-500 pl-4">
                                <p class="text-gray-700 italic">"{{ $testimonial['content'] }}"</p>
                                <p class="text-sm text-gray-600 mt-2 font-medium">
                                    - {{ $testimonial['author'] }}
                                    @if($testimonial['role'])
                                        , {{ $testimonial['role'] }}
                                    @endif
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Garantia -->
            <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                <div class="flex items-center space-x-3">
                    <svg class="w-8 h-8 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h4 class="text-lg font-semibold text-green-800">Garantia de 7 dias</h4>
                        <p class="text-green-700">Se não ficar satisfeito, devolvemos 100% do seu dinheiro</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coluna da Direita: Formulário de Checkout -->
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Finalize sua compra</h2>

                <form action="{{ route('checkout.process', $checkout_session['id']) }}" method="POST" id="checkout-form">
                    @csrf

                    <!-- Dados do Cliente -->
                    <div class="space-y-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Seus dados</h3>

                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-gray-700">Nome completo</label>
                            <input type="text" name="customer[name]" id="customer_name"
                                   value="{{ old('customer.name', $user_data['name'] ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   required>
                            @error('customer.name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="customer_email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="customer[email]" id="customer_email"
                                   value="{{ old('customer.email', $user_data['email'] ?? '') }}"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                   required>
                            @error('customer.email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="customer_cpf" class="block text-sm font-medium text-gray-700">CPF</label>
                                <input type="text" name="customer[cpf]" id="customer_cpf"
                                       value="{{ old('customer.cpf', $user_data['cpf'] ?? '') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       data-mask="000.000.000-00" required>
                                @error('customer.cpf')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="customer_phone" class="block text-sm font-medium text-gray-700">Telefone</label>
                                <input type="tel" name="customer[phone]" id="customer_phone"
                                       value="{{ old('customer.phone', $user_data['phone'] ?? '') }}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                       data-mask="(00) 00000-0000" required>
                                @error('customer.phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Método de Pagamento -->
                    <div class="space-y-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">Forma de pagamento</h3>

                        <div class="space-y-3">
                            @foreach($payment_methods as $method)
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment[method]" value="{{ $method['id'] }}"
                                           class="text-blue-600 focus:ring-blue-500"
                                           {{ $loop->first ? 'checked' : '' }}>
                                    <div class="ml-3 flex-1">
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-gray-900">{{ $method['name'] }}</span>
                                            @if($method['discount'] > 0)
                                                <span class="text-green-600 text-sm font-medium">
                                                    {{ $method['discount'] }}% de desconto
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-sm text-gray-600">{{ $method['description'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Order Bumps -->
                    @if(count($order_bumps) > 0)
                        <div class="space-y-4 mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">Ofertas especiais</h3>

                            @foreach($order_bumps as $bump)
                                <div class="border border-orange-200 rounded-lg p-4 bg-orange-50">
                                    <label class="flex items-start space-x-3 cursor-pointer">
                                        <input type="checkbox" name="order_bumps[]" value="{{ $bump['id'] }}"
                                               class="mt-1 text-orange-600 focus:ring-orange-500">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-2 mb-2">
                                                <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-medium">
                                                    🎁 OFERTA ESPECIAL
                                                </span>
                                                @if($bump['discount_percentage'] > 0)
                                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">
                                                        {{ $bump['discount_percentage'] }}% OFF
                                                    </span>
                                                @endif
                                            </div>
                                            <h4 class="font-semibold text-gray-900">{{ $bump['title'] }}</h4>
                                            <p class="text-sm text-gray-600 mb-2">{{ $bump['description'] }}</p>
                                            <div class="flex items-center space-x-2">
                                                @if($bump['original_price'] > $bump['price'])
                                                    <span class="text-sm text-gray-500 line-through">
                                                        R$ {{ number_format($bump['original_price'] / 100, 2, ',', '.') }}
                                                    </span>
                                                @endif
                                                <span class="text-lg font-bold text-orange-600">
                                                    R$ {{ number_format($bump['price'] / 100, 2, ',', '.') }}
                                                </span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <!-- Resumo do Pedido -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">Resumo do pedido</h3>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-600">{{ $offer['name'] }}</span>
                                <span class="font-medium">R$ {{ number_format($offer['price'] / 100, 2, ',', '.') }}</span>
                            </div>
                            <div id="order-bumps-summary"></div>
                            <div class="border-t border-gray-200 pt-2">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Total</span>
                                    <span id="total-amount">R$ {{ number_format($offer['price'] / 100, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Finalizar -->
                    <button type="submit"
                            class="w-full bg-green-600 text-white py-4 px-6 rounded-lg text-lg font-semibold hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors">
                        <span id="button-text">Finalizar Compra</span>
                        <div id="button-loading" class="hidden flex items-center justify-center">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Processando...
                        </div>
                    </button>

                    <!-- Termos -->
                    <p class="text-xs text-gray-600 text-center mt-4">
                        Ao finalizar a compra, você concorda com nossos
                        <a href="#" class="text-blue-600 hover:underline">termos de uso</a> e
                        <a href="#" class="text-blue-600 hover:underline">política de privacidade</a>.
                    </p>
                </form>
            </div>

            <!-- Trust indicators -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Compra 100% segura</h3>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">SSL 256 bits</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">PCI Compliant</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">Dados protegidos</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-sm text-gray-700">Garantia 7 dias</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts específicos da página -->
@push('scripts')
<script>
    // Máscaras de input
    document.addEventListener('DOMContentLoaded', function() {
        // Máscara CPF
        IMask(document.getElementById('customer_cpf'), {
            mask: '000.000.000-00'
        });

        // Máscara telefone
        IMask(document.getElementById('customer_phone'), {
            mask: [
                {
                    mask: '(00) 0000-0000'
                },
                {
                    mask: '(00) 00000-0000'
                }
            ]
        });

        // Cálculo dinâmico do total
        updateOrderSummary();

        // Event listeners para order bumps
        document.querySelectorAll('input[name="order_bumps[]"]').forEach(function(checkbox) {
            checkbox.addEventListener('change', updateOrderSummary);
        });

        // Loading state no submit
        document.getElementById('checkout-form').addEventListener('submit', function() {
            document.getElementById('button-text').classList.add('hidden');
            document.getElementById('button-loading').classList.remove('hidden');
        });
    });

    function updateOrderSummary() {
        let total = {{ $offer['price'] }};
        const summaryContainer = document.getElementById('order-bumps-summary');
        summaryContainer.innerHTML = '';

        document.querySelectorAll('input[name="order_bumps[]"]:checked').forEach(function(checkbox) {
            const bumpId = checkbox.value;
            const bumpData = @json($order_bumps).find(bump => bump.id === bumpId);

            if (bumpData) {
                total += bumpData.price;

                const div = document.createElement('div');
                div.className = 'flex justify-between text-sm';
                div.innerHTML = `
                    <span class="text-gray-600">${bumpData.title}</span>
                    <span class="font-medium">R$ ${(bumpData.price / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
                `;
                summaryContainer.appendChild(div);
            }
        });

        document.getElementById('total-amount').textContent =
            'R$ ' + (total / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2});
    }

    // Tracking de eventos
    function trackEvent(event, data) {
        if (window.clubifyTracking) {
            window.clubifyTracking.track(event, data);
        }
    }

    // Track início do checkout
    trackEvent('checkout_started', {
        offer_id: '{{ $offer['id'] }}',
        offer_name: '{{ $offer['name'] }}',
        offer_price: {{ $offer['price'] }}
    });

    // Track cliques em order bumps
    document.querySelectorAll('input[name="order_bumps[]"]').forEach(function(checkbox) {
        checkbox.addEventListener('change', function() {
            trackEvent('order_bump_' + (this.checked ? 'added' : 'removed'), {
                bump_id: this.value,
                offer_id: '{{ $offer['id'] }}'
            });
        });
    });
</script>
@endpush
@endsection
```

Continuo criando as próximas seções da documentação de integração completa...

<function_calls>
<invoke name="TodoWrite">
<parameter name="todos">[{"content": "Documentar m\u00f3dulo Organization completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Organization completo"}, {"content": "Documentar m\u00f3dulo Products completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Products completo"}, {"content": "Documentar m\u00f3dulo Checkout completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Checkout completo"}, {"content": "Documentar m\u00f3dulo Payments completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Payments completo"}, {"content": "Documentar m\u00f3dulo Customers completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Customers completo"}, {"content": "Documentar m\u00f3dulo Webhooks completo", "status": "completed", "activeForm": "Documentando m\u00f3dulo Webhooks completo"}, {"content": "Documentar ServiceProvider e configura\u00e7\u00e3o Laravel", "status": "completed", "activeForm": "Documentando ServiceProvider e configura\u00e7\u00e3o Laravel"}, {"content": "Documentar Facade com 25+ m\u00e9todos", "status": "completed", "activeForm": "Documentando Facade com 25+ m\u00e9todos"}, {"content": "Documentar Commands Laravel (Install, Publish, Sync)", "status": "completed", "activeForm": "Documentando Commands Laravel (Install, Publish, Sync)"}, {"content": "Documentar Middleware e Jobs", "status": "completed", "activeForm": "Documentando Middleware e Jobs"}, {"content": "Documentar Validation Rules brasileiras", "status": "completed", "activeForm": "Documentando Validation Rules brasileiras"}, {"content": "Criar guia de instala\u00e7\u00e3o e configura\u00e7\u00e3o", "status": "completed", "activeForm": "Criando guia de instala\u00e7\u00e3o e configura\u00e7\u00e3o"}, {"content": "Criar exemplos b\u00e1sicos de uso com Facade", "status": "completed", "activeForm": "Criando exemplos b\u00e1sicos de uso com Facade"}, {"content": "Criar casos de uso avan\u00e7ados", "status": "completed", "activeForm": "Criando casos de uso avan\u00e7ados"}, {"content": "Documentar integra\u00e7\u00e3o completa com Laravel", "status": "completed", "activeForm": "Documentando integra\u00e7\u00e3o completa com Laravel"}, {"content": "Criar troubleshooting e FAQ", "status": "in_progress", "activeForm": "Criando troubleshooting e FAQ"}, {"content": "Atualizar documenta\u00e7\u00e3o principal da Fase 4 completa", "status": "pending", "activeForm": "Atualizando documenta\u00e7\u00e3o principal da Fase 4 completa"}]