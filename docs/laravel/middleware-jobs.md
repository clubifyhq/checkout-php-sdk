# Laravel Middleware e Jobs - Clubify Checkout SDK

Esta documentação detalha todos os middlewares e jobs incluídos no Clubify Checkout SDK para Laravel, fornecendo controle avançado de requisições, processamento assíncrono e integrações enterprise.

## 📋 Índice

- [Middlewares](#middlewares)
  - [TenantResolver](#tenantresolver)
  - [AuthenticationGuard](#authenticationguard)
  - [RateLimiter](#ratelimiter)
  - [RequestLogger](#requestlogger)
  - [SecurityHeaders](#securityheaders)
- [Jobs](#jobs)
  - [ProcessPayment](#processpayment)
  - [SendWebhook](#sendwebhook)
  - [SyncCustomerData](#synccustomerdata)
  - [GenerateReport](#generatereport)
  - [CleanupSessions](#cleanupsessions)
- [Configuração](#configuração)
- [Exemplos Práticos](#exemplos-práticos)
- [Troubleshooting](#troubleshooting)

## Middlewares

### TenantResolver

Middleware para resolução automática de tenant baseado no domínio ou parâmetros da requisição.

```php
<?php

namespace ClubifyCheckout\Middleware;

use Closure;
use Illuminate\Http\Request;
use ClubifyCheckout\Services\OrganizationService;

class TenantResolver
{
    public function __construct(
        private OrganizationService $organizationService
    ) {}

    public function handle(Request $request, Closure $next)
    {
        // Resolução por domínio customizado
        if ($tenant = $this->resolveByDomain($request->getHost())) {
            app()->instance('current_tenant', $tenant);
            return $next($request);
        }

        // Resolução por subdomain
        if ($tenant = $this->resolveBySubdomain($request)) {
            app()->instance('current_tenant', $tenant);
            return $next($request);
        }

        // Resolução por header
        if ($tenantId = $request->header('X-Tenant-ID')) {
            $tenant = $this->organizationService->tenant()->get($tenantId);
            app()->instance('current_tenant', $tenant);
            return $next($request);
        }

        abort(404, 'Tenant não encontrado');
    }

    private function resolveByDomain(string $host): ?array
    {
        return $this->organizationService->domain()->getByDomain($host);
    }

    private function resolveBySubdomain(Request $request): ?array
    {
        $subdomain = explode('.', $request->getHost())[0];

        if ($subdomain && $subdomain !== 'www') {
            return $this->organizationService->tenant()->getBySubdomain($subdomain);
        }

        return null;
    }
}
```

**Uso no Laravel:**

```php
// routes/web.php
Route::middleware(['tenant.resolver'])->group(function () {
    Route::get('/checkout/{offer}', [CheckoutController::class, 'show']);
    Route::post('/checkout/{offer}/process', [CheckoutController::class, 'process']);
});

// app/Http/Kernel.php
protected $routeMiddleware = [
    'tenant.resolver' => \ClubifyCheckout\Middleware\TenantResolver::class,
];
```

### AuthenticationGuard

Middleware para autenticação e autorização de API keys e usuários.

```php
<?php

namespace ClubifyCheckout\Middleware;

use Closure;
use Illuminate\Http\Request;
use ClubifyCheckout\Services\OrganizationService;

class AuthenticationGuard
{
    public function __construct(
        private OrganizationService $organizationService
    ) {}

    public function handle(Request $request, Closure $next, string $level = 'basic')
    {
        // Verificação de API Key
        $apiKey = $request->header('Authorization');

        if (!$apiKey || !str_starts_with($apiKey, 'Bearer ')) {
            return response()->json(['error' => 'API Key requerida'], 401);
        }

        $key = substr($apiKey, 7);

        // Validação da API Key
        $keyData = $this->organizationService->apiKey()->validate($key);

        if (!$keyData || !$keyData['active']) {
            return response()->json(['error' => 'API Key inválida'], 401);
        }

        // Verificação de permissões baseada no nível
        if (!$this->hasPermission($keyData, $level)) {
            return response()->json(['error' => 'Permissão insuficiente'], 403);
        }

        // Rate limiting por API Key
        if ($this->isRateLimited($keyData)) {
            return response()->json(['error' => 'Rate limit excedido'], 429);
        }

        // Adiciona dados da API Key ao request
        $request->merge(['api_key_data' => $keyData]);

        return $next($request);
    }

    private function hasPermission(array $keyData, string $level): bool
    {
        $permissions = $keyData['permissions'] ?? [];

        return match($level) {
            'basic' => in_array('read', $permissions),
            'write' => in_array('write', $permissions),
            'admin' => in_array('admin', $permissions),
            default => false
        };
    }

    private function isRateLimited(array $keyData): bool
    {
        $limits = $keyData['rate_limits'] ?? ['requests_per_minute' => 60];
        $key = "rate_limit:{$keyData['id']}";

        return cache()->increment($key, 1, 60) > $limits['requests_per_minute'];
    }
}
```

### RateLimiter

Middleware para controle de taxa de requisições avançado.

```php
<?php

namespace ClubifyCheckout\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RateLimiter
{
    public function handle(Request $request, Closure $next, string $maxAttempts = '60', string $decayMinutes = '1')
    {
        $key = $this->resolveRequestSignature($request);

        $attempts = Cache::get($key, 0);

        if ($attempts >= (int) $maxAttempts) {
            return response()->json([
                'error' => 'Rate limit exceeded',
                'retry_after' => (int) $decayMinutes * 60
            ], 429);
        }

        Cache::put($key, $attempts + 1, (int) $decayMinutes * 60);

        $response = $next($request);

        // Adiciona headers de rate limiting
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, (int) $maxAttempts - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes((int) $decayMinutes)->timestamp);

        return $response;
    }

    protected function resolveRequestSignature(Request $request): string
    {
        // Combinação de IP, User-Agent e API Key para signature única
        $apiKey = $request->header('Authorization');

        return sha1(
            $request->ip() . '|' .
            $request->userAgent() . '|' .
            ($apiKey ? substr($apiKey, -10) : 'anonymous')
        );
    }
}
```

### RequestLogger

Middleware para logging detalhado de requisições e auditoria.

```php
<?php

namespace ClubifyCheckout\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestLogger
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Log da requisição entrante
        $this->logIncomingRequest($request);

        $response = $next($request);

        // Log da resposta
        $this->logResponse($request, $response, $startTime);

        return $response;
    }

    private function logIncomingRequest(Request $request): void
    {
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'body' => $this->filterSensitiveData($request->all()),
            'timestamp' => now()->toISOString()
        ];

        Log::channel('api')->info('Incoming request', $logData);
    }

    private function logResponse(Request $request, $response, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000; // em millisegundos

        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'response_size' => strlen($response->getContent()),
            'timestamp' => now()->toISOString()
        ];

        $level = $response->getStatusCode() >= 400 ? 'warning' : 'info';
        Log::channel('api')->$level('Request completed', $logData);
    }

    private function filterHeaders(array $headers): array
    {
        $filtered = [];
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitiveHeaders)) {
                $filtered[$key] = '***FILTERED***';
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    private function filterSensitiveData(array $data): array
    {
        $sensitiveFields = ['password', 'card_number', 'cvv', 'api_key', 'token'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '***FILTERED***';
            }
        }

        return $data;
    }
}
```

### SecurityHeaders

Middleware para adicionar headers de segurança necessários.

```php
<?php

namespace ClubifyCheckout\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Content Security Policy
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://js.stripe.com https://checkout.clubify.app; " .
            "style-src 'self' 'unsafe-inline'; " .
            "img-src 'self' data: https:; " .
            "connect-src 'self' https://api.clubify.app; " .
            "frame-src https://js.stripe.com;"
        );

        // HSTS
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'DENY');

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy
        $response->headers->set('Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(self)'
        );

        return $response;
    }
}
```

## Jobs

### ProcessPayment

Job para processamento assíncrono de pagamentos.

```php
<?php

namespace ClubifyCheckout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ClubifyCheckout\Services\PaymentService;

class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 30;

    public function __construct(
        private string $paymentId,
        private array $paymentData,
        private string $tenantId
    ) {}

    public function handle(PaymentService $paymentService): void
    {
        try {
            // Processa o pagamento
            $result = $paymentService->process($this->paymentData);

            // Atualiza status do pagamento
            $paymentService->updateStatus($this->paymentId, $result['status']);

            // Dispara webhook de confirmação
            if ($result['status'] === 'approved') {
                SendWebhook::dispatch($this->tenantId, 'payment.approved', [
                    'payment_id' => $this->paymentId,
                    'amount' => $result['amount'],
                    'gateway_transaction_id' => $result['transaction_id']
                ]);
            }

        } catch (\Exception $e) {
            // Log do erro
            logger()->error('Payment processing failed', [
                'payment_id' => $this->paymentId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Re-throw para tentar novamente
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Marca pagamento como falhou
        app(PaymentService::class)->updateStatus($this->paymentId, 'failed');

        // Notifica sobre a falha
        SendWebhook::dispatch($this->tenantId, 'payment.failed', [
            'payment_id' => $this->paymentId,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### SendWebhook

Job para entrega de webhooks com retry inteligente.

```php
<?php

namespace ClubifyCheckout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ClubifyCheckout\Services\WebhookService;

class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 30;
    public array $backoff = [1, 5, 15, 30, 60]; // Progressive backoff

    public function __construct(
        private string $tenantId,
        private string $event,
        private array $data,
        private ?string $webhookUrl = null
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        try {
            $result = $webhookService->send($this->tenantId, $this->event, $this->data, $this->webhookUrl);

            if (!$result['success']) {
                throw new \Exception("Webhook failed: {$result['error']}");
            }

            // Log sucesso
            logger()->info('Webhook sent successfully', [
                'tenant_id' => $this->tenantId,
                'event' => $this->event,
                'webhook_id' => $result['webhook_id'],
                'attempt' => $this->attempts()
            ]);

        } catch (\Exception $e) {
            logger()->warning('Webhook delivery failed', [
                'tenant_id' => $this->tenantId,
                'event' => $this->event,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Marca webhook como falhou definitivamente
        app(WebhookService::class)->markAsFailed($this->tenantId, $this->event, [
            'error' => $exception->getMessage(),
            'attempts' => $this->tries,
            'data' => $this->data
        ]);

        logger()->error('Webhook permanently failed', [
            'tenant_id' => $this->tenantId,
            'event' => $this->event,
            'error' => $exception->getMessage()
        ]);
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(2); // Tenta por 2 horas
    }
}
```

### SyncCustomerData

Job para sincronização de dados de clientes com sistemas externos.

```php
<?php

namespace ClubifyCheckout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ClubifyCheckout\Services\CustomerService;

class SyncCustomerData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        private string $customerId,
        private string $tenantId,
        private array $integrations = []
    ) {}

    public function handle(CustomerService $customerService): void
    {
        $customer = $customerService->get($this->customerId);

        if (!$customer) {
            throw new \Exception("Customer not found: {$this->customerId}");
        }

        // Sincroniza com CRM
        if (in_array('crm', $this->integrations) || empty($this->integrations)) {
            $this->syncWithCRM($customerService, $customer);
        }

        // Sincroniza com Email Marketing
        if (in_array('email', $this->integrations) || empty($this->integrations)) {
            $this->syncWithEmailMarketing($customerService, $customer);
        }

        // Sincroniza com Analytics
        if (in_array('analytics', $this->integrations) || empty($this->integrations)) {
            $this->syncWithAnalytics($customerService, $customer);
        }

        logger()->info('Customer data synced', [
            'customer_id' => $this->customerId,
            'tenant_id' => $this->tenantId,
            'integrations' => $this->integrations
        ]);
    }

    private function syncWithCRM(CustomerService $customerService, array $customer): void
    {
        $customerService->syncWithCRM($customer['id'], [
            'email' => $customer['email'],
            'name' => $customer['name'],
            'phone' => $customer['phone'],
            'tags' => $customer['tags'] ?? [],
            'last_purchase' => $customer['last_purchase_at'],
            'total_spent' => $customer['total_spent']
        ]);
    }

    private function syncWithEmailMarketing(CustomerService $customerService, array $customer): void
    {
        $customerService->syncWithEmailMarketing($customer['id'], [
            'email' => $customer['email'],
            'first_name' => $customer['first_name'],
            'last_name' => $customer['last_name'],
            'segments' => $customer['segments'] ?? [],
            'preferences' => $customer['email_preferences'] ?? []
        ]);
    }

    private function syncWithAnalytics(CustomerService $customerService, array $customer): void
    {
        $customerService->syncWithAnalytics($customer['id'], [
            'user_id' => $customer['id'],
            'traits' => [
                'email' => $customer['email'],
                'name' => $customer['name'],
                'created_at' => $customer['created_at'],
                'total_orders' => $customer['total_orders'],
                'total_spent' => $customer['total_spent']
            ]
        ]);
    }
}
```

### GenerateReport

Job para geração assíncrona de relatórios.

```php
<?php

namespace ClubifyCheckout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ClubifyCheckout\Services\ReportService;

class GenerateReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 300; // 5 minutos

    public function __construct(
        private string $reportType,
        private array $parameters,
        private string $tenantId,
        private string $requestedBy
    ) {}

    public function handle(ReportService $reportService): void
    {
        try {
            $report = $reportService->generate($this->reportType, $this->parameters, $this->tenantId);

            // Salva o relatório
            $reportId = $reportService->save($report, [
                'type' => $this->reportType,
                'tenant_id' => $this->tenantId,
                'requested_by' => $this->requestedBy,
                'parameters' => $this->parameters
            ]);

            // Notifica que o relatório está pronto
            SendWebhook::dispatch($this->tenantId, 'report.ready', [
                'report_id' => $reportId,
                'type' => $this->reportType,
                'requested_by' => $this->requestedBy,
                'download_url' => $reportService->getDownloadUrl($reportId)
            ]);

            logger()->info('Report generated successfully', [
                'report_id' => $reportId,
                'type' => $this->reportType,
                'tenant_id' => $this->tenantId
            ]);

        } catch (\Exception $e) {
            logger()->error('Report generation failed', [
                'type' => $this->reportType,
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Notifica sobre a falha
        SendWebhook::dispatch($this->tenantId, 'report.failed', [
            'type' => $this->reportType,
            'requested_by' => $this->requestedBy,
            'error' => $exception->getMessage()
        ]);
    }
}
```

### CleanupSessions

Job para limpeza automática de sessões expiradas.

```php
<?php

namespace ClubifyCheckout\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ClubifyCheckout\Services\CheckoutService;

class CleanupSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(CheckoutService $checkoutService): void
    {
        $cutoffTime = now()->subHours(24); // Remove sessões de 24+ horas

        $result = $checkoutService->cleanupExpiredSessions($cutoffTime);

        logger()->info('Session cleanup completed', [
            'sessions_removed' => $result['removed_count'],
            'cutoff_time' => $cutoffTime->toISOString(),
            'space_freed_mb' => $result['space_freed_mb'] ?? 0
        ]);

        // Se removeu muitas sessões, agenda próxima limpeza mais cedo
        if ($result['removed_count'] > 1000) {
            static::dispatch()->delay(now()->addHours(2));
        }
    }
}
```

## Configuração

### Registro dos Middlewares

```php
// config/clubify.php
return [
    'middleware' => [
        'tenant_resolver' => [
            'enabled' => true,
            'fallback_domain' => env('CLUBIFY_FALLBACK_DOMAIN'),
        ],
        'auth_guard' => [
            'enabled' => true,
            'rate_limit' => [
                'basic' => 100,
                'write' => 60,
                'admin' => 30
            ]
        ],
        'rate_limiter' => [
            'enabled' => true,
            'default_max_attempts' => 60,
            'default_decay_minutes' => 1
        ],
        'request_logger' => [
            'enabled' => env('CLUBIFY_LOG_REQUESTS', true),
            'log_body' => env('CLUBIFY_LOG_BODY', false)
        ],
        'security_headers' => [
            'enabled' => true
        ]
    ],

    'jobs' => [
        'queue' => env('CLUBIFY_QUEUE', 'default'),
        'retry_attempts' => [
            'payment' => 3,
            'webhook' => 5,
            'sync' => 3,
            'report' => 2,
            'cleanup' => 1
        ]
    ]
];
```

### Configuração de Filas

```php
// config/queue.php
'connections' => [
    'clubify' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('CLUBIFY_QUEUE', 'clubify'),
        'retry_after' => 90,
        'block_for' => null,
    ],

    'clubify-high' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => 'clubify-high-priority',
        'retry_after' => 60,
        'block_for' => null,
    ]
],
```

## Exemplos Práticos

### 1. Configuração Completa de Middleware

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'clubify-api' => [
        \ClubifyCheckout\Middleware\SecurityHeaders::class,
        \ClubifyCheckout\Middleware\RequestLogger::class,
        \ClubifyCheckout\Middleware\TenantResolver::class,
        \ClubifyCheckout\Middleware\AuthenticationGuard::class . ':write',
        \ClubifyCheckout\Middleware\RateLimiter::class . ':60,1',
    ],
];

// routes/api.php
Route::middleware(['clubify-api'])->prefix('api/v1')->group(function () {
    Route::post('/checkout/process', [CheckoutController::class, 'process']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
```

### 2. Processamento de Pagamento Assíncrono

```php
// No Controller
public function processPayment(Request $request)
{
    $paymentData = $request->validated();

    // Cria registro de pagamento pendente
    $payment = $this->paymentService->create([
        'status' => 'pending',
        'amount' => $paymentData['amount'],
        'method' => $paymentData['method']
    ]);

    // Agenda processamento assíncrono
    ProcessPayment::dispatch(
        $payment['id'],
        $paymentData,
        $request->get('current_tenant')['id']
    )->onQueue('clubify-high');

    return response()->json([
        'payment_id' => $payment['id'],
        'status' => 'processing'
    ]);
}
```

### 3. Sistema de Webhook com Retry

```php
// Disparar webhook
SendWebhook::dispatch($tenantId, 'order.completed', [
    'order_id' => $order['id'],
    'customer' => $order['customer'],
    'items' => $order['items'],
    'total' => $order['total']
])->onQueue('clubify');

// Webhook personalizado
SendWebhook::dispatch(
    $tenantId,
    'custom.event',
    $eventData,
    'https://custom-endpoint.example.com/webhook'
)->delay(now()->addMinutes(5));
```

### 4. Sincronização de Dados

```php
// Após criação/atualização de cliente
public function updateCustomer(Request $request, string $customerId)
{
    $customer = $this->customerService->update($customerId, $request->validated());

    // Agenda sincronização com integrações específicas
    SyncCustomerData::dispatch(
        $customerId,
        $request->get('current_tenant')['id'],
        ['crm', 'email'] // Apenas CRM e Email Marketing
    )->delay(now()->addSeconds(30));

    return response()->json($customer);
}
```

### 5. Geração de Relatório

```php
// Controller para solicitar relatório
public function generateSalesReport(Request $request)
{
    $parameters = [
        'start_date' => $request->input('start_date'),
        'end_date' => $request->input('end_date'),
        'format' => $request->input('format', 'pdf'),
        'include_details' => $request->boolean('include_details')
    ];

    GenerateReport::dispatch(
        'sales',
        $parameters,
        $request->get('current_tenant')['id'],
        auth()->id()
    )->onQueue('clubify');

    return response()->json([
        'message' => 'Relatório sendo gerado. Você será notificado quando estiver pronto.'
    ]);
}
```

### 6. Agendamento de Limpeza

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Limpeza diária de sessões
    $schedule->job(new CleanupSessions())
             ->daily()
             ->at('02:00')
             ->timezone('America/Sao_Paulo');

    // Limpeza de logs antigos
    $schedule->job(new CleanupLogs())
             ->weekly()
             ->sundays()
             ->at('03:00');
}
```

## Troubleshooting

### Problemas de Performance

```php
// Monitoramento de Jobs lentos
// config/clubify.php
'monitoring' => [
    'slow_job_threshold' => 30, // segundos
    'memory_limit_mb' => 256,
    'enable_profiling' => env('CLUBIFY_PROFILE_JOBS', false)
]

// Job com profiling
class ProcessPayment implements ShouldQueue
{
    public function handle(PaymentService $paymentService): void
    {
        if (config('clubify.monitoring.enable_profiling')) {
            $startMemory = memory_get_usage(true);
            $startTime = microtime(true);
        }

        // Lógica do job...

        if (config('clubify.monitoring.enable_profiling')) {
            $duration = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;

            logger()->info('Job performance', [
                'job' => static::class,
                'duration_seconds' => $duration,
                'memory_used_mb' => $memoryUsed / 1024 / 1024
            ]);
        }
    }
}
```

### Debug de Middleware

```php
// Middleware de debug
class DebugMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('local')) {
            logger()->debug('Middleware Debug', [
                'middleware' => static::class,
                'request_id' => $request->header('X-Request-ID'),
                'tenant' => app('current_tenant'),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . 'MB'
            ]);
        }

        return $next($request);
    }
}
```

### Monitoramento de Filas

```bash
# Comandos úteis para monitoramento
php artisan queue:work clubify --verbose --tries=3 --timeout=90
php artisan queue:failed
php artisan queue:retry all
php artisan queue:clear clubify

# Horizon (se usando)
php artisan horizon:status
php artisan horizon:pause
php artisan horizon:continue
```

### Logs Estruturados

```php
// config/logging.php
'channels' => [
    'clubify' => [
        'driver' => 'daily',
        'path' => storage_path('logs/clubify.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ],

    'clubify-jobs' => [
        'driver' => 'daily',
        'path' => storage_path('logs/clubify-jobs.log'),
        'level' => 'info',
        'days' => 30,
        'formatter' => \Monolog\Formatter\JsonFormatter::class,
    ]
]
```

---

## Conclusão

O sistema de Middleware e Jobs do Clubify Checkout SDK oferece uma arquitetura robusta e escalável para aplicações enterprise Laravel, com:

- **Middleware Avançado**: Controle completo de autenticação, autorização, rate limiting e segurança
- **Jobs Assíncronos**: Processamento eficiente de pagamentos, webhooks e sincronizações
- **Monitoramento**: Logging detalhado e métricas de performance
- **Escalabilidade**: Arquitetura preparada para alto volume de transações
- **Manutenibilidade**: Código limpo e bem documentado seguindo padrões Laravel

Esta implementação garante que sua aplicação tenha toda a infraestrutura necessária para operar como uma plataforma de checkout enterprise de alta performance.