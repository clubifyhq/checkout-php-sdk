# Troubleshooting e FAQ - Clubify Checkout SDK

Este guia apresenta soluções para os problemas mais comuns encontrados ao usar o Clubify Checkout SDK, junto com perguntas frequentes e dicas de debug.

## 📋 Índice

- [Problemas de Instalação](#problemas-de-instalação)
- [Problemas de Configuração](#problemas-de-configuração)
- [Problemas de API](#problemas-de-api)
- [Problemas de Integração Laravel](#problemas-de-integração-laravel)
- [Problemas de Performance](#problemas-de-performance)
- [Problemas de Webhook](#problemas-de-webhook)
- [Problemas de Pagamento](#problemas-de-pagamento)
- [FAQ - Perguntas Frequentes](#faq---perguntas-frequentes)
- [Debug e Logging](#debug-e-logging)
- [Suporte Técnico](#suporte-técnico)

## Problemas de Instalação

### 1. Erro: "Package not found"

**Sintomas:**
```bash
composer require clubify/checkout-sdk-php
# Could not find package clubify/checkout-sdk-php
```

**Soluções:**

```bash
# 1. Verifique se tem acesso ao repositório
composer config repositories.clubify composer https://packages.clubify.app

# 2. Autentique-se com suas credenciais
composer config http-basic.packages.clubify.app username password

# 3. Limpe cache do composer
composer clear-cache

# 4. Tente novamente
composer require clubify/checkout-sdk-php
```

### 2. Erro: "Your requirements could not be resolved"

**Sintomas:**
```
Problem 1
- clubify/checkout-sdk-php requires php ^8.2
```

**Soluções:**

```bash
# 1. Verifique versão do PHP
php -v

# 2. Atualize o PHP (Ubuntu/Debian)
sudo apt update
sudo apt install php8.2 php8.2-cli php8.2-curl php8.2-json

# 3. Para Laravel, verifique compatibilidade
composer why-not clubify/checkout-sdk-php
```

### 3. Erro: "Class not found"

**Sintomas:**
```php
Class 'ClubifyCheckout\ClubifyCheckout' not found
```

**Soluções:**

```bash
# 1. Regenere autoloader
composer dump-autoload

# 2. Verifique se o pacote foi instalado
composer show clubify/checkout-sdk-php

# 3. Verifique imports
```

```php
// Correto
use ClubifyCheckout\ClubifyCheckout;

// Para Laravel
use ClubifyCheckout\Facades\Clubify;
```

## Problemas de Configuração

### 1. Erro: "Invalid API key"

**Sintomas:**
```json
{
  "error": "Unauthorized",
  "message": "Invalid API key"
}
```

**Diagnóstico:**

```php
// Verifique se a API key está configurada
var_dump([
    'api_key' => config('clubify.api.key'),
    'organization_id' => config('clubify.organization.id'),
    'environment' => config('clubify.organization.environment')
]);
```

**Soluções:**

```bash
# 1. Verifique arquivo .env
cat .env | grep CLUBIFY

# 2. Limpe cache de configuração
php artisan config:clear

# 3. Teste conexão
php artisan clubify:test
```

### 2. Erro: "Organization not found"

**Sintomas:**
```json
{
  "error": "Not Found",
  "message": "Organization not found"
}
```

**Soluções:**

```php
// 1. Verifique ID da organização
$config = [
    'api_key' => 'sua_api_key',
    'organization_id' => 'ID_CORRETO_AQUI', // Verifique no painel
    'environment' => 'sandbox' // ou 'production'
];
```

### 3. Problemas de Environment

**Sintomas:**
```
Sandbox data appearing in production
```

**Soluções:**

```env
# .env.production
CLUBIFY_ENVIRONMENT=production
CLUBIFY_API_URL=https://api.clubify.app

# .env.local / .env.testing
CLUBIFY_ENVIRONMENT=sandbox
CLUBIFY_API_URL=https://sandbox-api.clubify.app
```

## Problemas de API

### 1. Timeout de Conexão

**Sintomas:**
```
cURL error 28: Operation timed out after 30000 milliseconds
```

**Soluções:**

```php
// Aumentar timeout
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'timeout' => 60,        // 60 segundos
    'connect_timeout' => 10 // 10 segundos para conectar
]);

// Para Laravel
// config/clubify.php
'api' => [
    'timeout' => 60,
    'retries' => 5,
],
```

### 2. SSL/TLS Certificate Problems

**Sintomas:**
```
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

**Soluções:**

```php
// Opção 1: Desabilitar verificação SSL (NÃO recomendado para produção)
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'verify_ssl' => false
]);

// Opção 2: Configurar CA bundle
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'ca_bundle' => '/path/to/cacert.pem'
]);

// Opção 3: Atualizar CA certificates
# Ubuntu/Debian
sudo apt-get update && sudo apt-get install ca-certificates

# CentOS/RHEL
sudo yum update ca-certificates
```

### 3. Rate Limiting

**Sintomas:**
```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded",
  "retry_after": 60
}
```

**Soluções:**

```php
// Implementar retry com backoff
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'retries' => 3,
    'retry_delay' => 1000, // 1 segundo
    'retry_multiplier' => 2 // Exponential backoff
]);

// Ou usar cache para reduzir chamadas
$offer = cache()->remember("offer.{$id}", 3600, function () use ($id) {
    return Clubify::getOffer($id);
});
```

## Problemas de Integração Laravel

### 1. ServiceProvider não registrado

**Sintomas:**
```
Class 'ClubifyCheckout\Laravel\ClubifyServiceProvider' not found
```

**Soluções:**

```bash
# 1. Publique configuração
php artisan vendor:publish --tag=clubify-config

# 2. Adicione manualmente ao config/app.php
'providers' => [
    // ...
    ClubifyCheckout\Laravel\ClubifyServiceProvider::class,
],

# 3. Registre a Facade
'aliases' => [
    // ...
    'Clubify' => ClubifyCheckout\Laravel\Facades\ClubifyFacade::class,
],
```

### 2. Middleware não funcionando

**Sintomas:**
```
Middleware 'clubify.auth' not found
```

**Soluções:**

```php
// app/Http/Kernel.php
protected $routeMiddleware = [
    // ...
    'clubify.auth' => \ClubifyCheckout\Middleware\AuthenticationGuard::class,
    'clubify.tenant' => \ClubifyCheckout\Middleware\TenantResolver::class,
];
```

### 3. Jobs não executando

**Sintomas:**
```
Jobs ficam na fila mas não são processados
```

**Diagnóstico:**

```bash
# Verifique se o worker está rodando
php artisan queue:work --verbose

# Verifique jobs falhados
php artisan queue:failed

# Monitore em tempo real
php artisan queue:monitor
```

**Soluções:**

```bash
# 1. Configure supervisor ou similar
# /etc/supervisor/conf.d/laravel-worker.conf
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3
directory=/path/to/project
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/project/storage/logs/worker.log

# 2. Configure fila específica
CLUBIFY_QUEUE=clubify
php artisan queue:work redis --queue=clubify
```

## Problemas de Performance

### 1. Consultas lentas à API

**Sintomas:**
```
Checkout taking 5+ seconds to load
```

**Soluções:**

```php
// 1. Implemente cache multi-camada
class FastClubifyService
{
    public function getOffer($id)
    {
        // L1: Memory cache
        if (isset($this->memoryCache[$id])) {
            return $this->memoryCache[$id];
        }

        // L2: Redis cache
        $cached = Redis::get("offer.{$id}");
        if ($cached) {
            $offer = json_decode($cached, true);
            $this->memoryCache[$id] = $offer;
            return $offer;
        }

        // L3: API call
        $offer = Clubify::getOffer($id);

        // Popula caches
        Redis::setex("offer.{$id}", 3600, json_encode($offer));
        $this->memoryCache[$id] = $offer;

        return $offer;
    }
}

// 2. Use eager loading
$offers = Clubify::listOffers(['include' => 'products,analytics']);

// 3. Processe em background
dispatch(new PreloadOfferDataJob($offerId));
```

### 2. Memory leaks

**Sintomas:**
```
PHP Fatal error: Allowed memory size of X bytes exhausted
```

**Soluções:**

```php
// 1. Processe em lotes
$customers = Clubify::listCustomers(['limit' => 100]);
foreach ($customers['data'] as $customer) {
    // Processa customer
    unset($customer); // Libera memória
}

// 2. Use generators para grandes datasets
function getCustomersBatch() {
    $page = 1;
    do {
        $batch = Clubify::listCustomers(['page' => $page, 'limit' => 100]);
        foreach ($batch['data'] as $customer) {
            yield $customer;
        }
        $page++;
    } while ($batch['has_more']);
}

// 3. Configure memory limit
ini_set('memory_limit', '512M');
```

## Problemas de Webhook

### 1. Webhooks não chegando

**Diagnóstico:**

```bash
# Teste conectividade
curl -X POST https://seu-site.com/webhooks/clubify \
  -H "Content-Type: application/json" \
  -H "X-Clubify-Signature: test" \
  -d '{"event": "test", "data": {}}'
```

**Soluções:**

```php
// 1. Verifique URL no painel Clubify
// 2. Confirme que a rota está acessível publicamente
Route::post('/webhooks/clubify', [WebhookController::class, 'handle'])
    ->withoutMiddleware(['auth', 'verified']); // Remove auth

// 3. Log webhooks recebidos
public function handle(Request $request)
{
    logger()->info('Webhook received', [
        'headers' => $request->headers->all(),
        'body' => $request->all()
    ]);

    // resto da lógica...
}
```

### 2. Falha na validação de assinatura

**Sintomas:**
```
Invalid webhook signature
```

**Soluções:**

```php
private function validateSignature(Request $request): bool
{
    $signature = $request->header('X-Clubify-Signature');
    $payload = $request->getContent(); // Use raw content
    $secret = config('clubify.webhooks.secret');

    // Debug
    logger()->debug('Webhook signature validation', [
        'received_signature' => $signature,
        'payload_length' => strlen($payload),
        'secret_configured' => !empty($secret)
    ]);

    if (!$signature || !$secret) {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', $payload, $secret);

    return hash_equals($signature, $expectedSignature);
}
```

### 3. Duplicação de webhooks

**Sintomas:**
```
Same webhook processed multiple times
```

**Soluções:**

```php
// Implementar idempotência
public function handleOrderCompleted(array $data)
{
    $webhookId = $data['webhook_id'] ?? null;

    if ($webhookId && Cache::has("webhook.{$webhookId}")) {
        logger()->info('Duplicate webhook ignored', ['webhook_id' => $webhookId]);
        return;
    }

    // Processa webhook
    $this->processOrder($data);

    // Marca como processado
    if ($webhookId) {
        Cache::put("webhook.{$webhookId}", true, 3600);
    }
}
```

## Problemas de Pagamento

### 1. Pagamento aprovado mas pedido não finalizado

**Diagnóstico:**

```php
// Verifique status no Clubify
$payment = Clubify::getPayment($paymentId);
$order = Clubify::getOrder($orderId);

logger()->info('Payment status check', [
    'payment_status' => $payment['status'],
    'order_status' => $order['status'],
    'last_updated' => $payment['updated_at']
]);
```

**Soluções:**

```php
// 1. Sincronização manual
public function syncPaymentStatus($paymentId)
{
    $payment = Clubify::getPayment($paymentId);

    if ($payment['status'] === 'approved') {
        $order = Clubify::getOrder($payment['order_id']);

        if ($order['status'] !== 'completed') {
            // Force completion
            Clubify::completeOrder($order['id']);
        }
    }
}

// 2. Job de reconciliação
dispatch(new ReconcilePaymentsJob())->delay(now()->addMinutes(5));
```

### 2. Cartão recusado indevidamente

**Sintomas:**
```json
{
  "error": "Payment Failed",
  "code": "card_declined",
  "message": "Your card was declined"
}
```

**Soluções:**

```php
// 1. Implementar retry com método alternativo
public function processPayment($paymentData)
{
    try {
        return Clubify::processPayment($paymentData);
    } catch (PaymentDeclinedException $e) {
        // Sugere método alternativo
        if ($paymentData['method'] === 'credit_card') {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'alternative_methods' => ['pix', 'boleto']
            ];
        }
        throw $e;
    }
}

// 2. Fallback para gateway alternativo
$result = Clubify::processPayment([
    'amount' => $amount,
    'method' => 'credit_card',
    'fallback_gateway' => 'backup_gateway'
]);
```

## FAQ - Perguntas Frequentes

### Geral

**Q: O SDK funciona com outras frameworks PHP além do Laravel?**

A: Sim! O SDK core funciona com qualquer aplicação PHP 8.2+. As funcionalidades específicas do Laravel (ServiceProvider, Facade, Commands) são opcionais.

```php
// PHP Vanilla
$clubify = new ClubifyCheckout\ClubifyCheckout($config);

// Symfony
$container->set('clubify', new ClubifyCheckout($config));

// CodeIgniter 4
$clubify = new ClubifyCheckout($config);
```

**Q: Posso usar o SDK em ambiente compartilhado?**

A: Sim, mas certifique-se de que:
- PHP 8.2+ está disponível
- Extensões cURL e JSON estão habilitadas
- Permissões de escrita para cache (se usado)

**Q: O SDK suporta multi-tenancy?**

A: Sim! Você pode criar múltiplas instâncias para diferentes organizações:

```php
$tenant1 = new ClubifyCheckout(['api_key' => 'key1', 'organization_id' => 'org1']);
$tenant2 = new ClubifyCheckout(['api_key' => 'key2', 'organization_id' => 'org2']);
```

### Desenvolvimento

**Q: Como testar sem fazer transações reais?**

A: Use o ambiente sandbox:

```php
$clubify = new ClubifyCheckout([
    'api_key' => 'sandbox_key',
    'environment' => 'sandbox'
]);
```

**Q: Posso mockar o SDK nos testes?**

A: Sim! Use mocks ou fakes:

```php
// PHPUnit
$mock = $this->createMock(ClubifyCheckout::class);
$mock->method('getOffer')->willReturn(['id' => 'test-offer']);

// Laravel Fake
Clubify::fake();
Clubify::shouldReceive('getOffer')->andReturn(['id' => 'test-offer']);
```

**Q: Como debug chamadas da API?**

A: Habilite logging detalhado:

```php
$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'debug' => true,
    'logger' => new \Monolog\Logger('clubify')
]);
```

### Performance

**Q: Qual é o limite de chamadas da API?**

A: Por padrão:
- 100 req/min para operações de leitura
- 60 req/min para operações de escrita
- 30 req/min para operações administrativas

**Q: Como otimizar performance?**

A: Use cache inteligentemente:

```php
// Cache por tipo de operação
$offers = cache()->remember('offers.active', 1800, fn() => Clubify::listOffers(['active' => true]));
$metrics = cache()->remember('metrics.daily', 3600, fn() => Clubify::getMetrics(['period' => 'today']));
```

### Integração

**Q: Como integrar com meu CRM existente?**

A: Use webhooks para sincronização bidirecional:

```php
// Webhook handler
public function handleCustomerCreated($data)
{
    // Cria no CRM
    MeuCRM::createContact([
        'name' => $data['name'],
        'email' => $data['email'],
        'source' => 'clubify'
    ]);
}
```

**Q: Posso customizar a aparência do checkout?**

A: Sim! Configure temas e layouts:

```php
$offer = Clubify::createOffer([
    'name' => 'Minha Oferta',
    'layout' => [
        'theme' => 'custom',
        'primary_color' => '#1a202c',
        'font_family' => 'Inter',
        'custom_css' => 'body { background: #f7fafc; }'
    ]
]);
```

## Debug e Logging

### 1. Habilitando Debug Completo

```php
// Laravel
// config/clubify.php
'logging' => [
    'enabled' => true,
    'level' => 'debug',
    'include_body' => true,
    'include_headers' => true,
],

// PHP Vanilla
$logger = new Monolog\Logger('clubify');
$logger->pushHandler(new Monolog\Handler\StreamHandler('logs/clubify.log', Monolog\Logger::DEBUG));

$clubify = new ClubifyCheckout([
    'api_key' => 'sua_key',
    'debug' => true,
    'logger' => $logger
]);
```

### 2. Debug de Requisições HTTP

```php
// Interceptar requisições
$clubify->onRequest(function ($method, $url, $headers, $body) {
    logger()->debug('Clubify API Request', [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body
    ]);
});

// Interceptar respostas
$clubify->onResponse(function ($response) {
    logger()->debug('Clubify API Response', [
        'status' => $response['status'],
        'headers' => $response['headers'],
        'body' => $response['body']
    ]);
});
```

### 3. Health Check Endpoint

```php
// routes/web.php
Route::get('/health/clubify', function () {
    try {
        $start = microtime(true);
        $org = Clubify::getOrganization();
        $duration = microtime(true) - $start;

        return response()->json([
            'status' => 'healthy',
            'organization' => $org['name'],
            'response_time' => round($duration * 1000, 2) . 'ms',
            'timestamp' => now()->toISOString()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'unhealthy',
            'error' => $e->getMessage(),
            'timestamp' => now()->toISOString()
        ], 503);
    }
});
```

### 4. Performance Monitoring

```php
// Middleware de performance
class ClubifyPerformanceMiddleware
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        $duration = microtime(true) - $start;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        if ($duration > 2.0) { // Se demorou mais que 2 segundos
            logger()->warning('Slow Clubify operation', [
                'url' => $request->fullUrl(),
                'duration' => $duration,
                'memory_used' => $memoryUsed / 1024 / 1024 . 'MB'
            ]);
        }

        return $response;
    }
}
```

## Suporte Técnico

### Informações para Coleta

Antes de entrar em contato com o suporte, colete as seguintes informações:

```php
// Script de diagnóstico
function collectDiagnosticInfo()
{
    return [
        'php_version' => PHP_VERSION,
        'sdk_version' => ClubifyCheckout::VERSION,
        'laravel_version' => app()->version() ?? 'N/A',
        'environment' => config('clubify.organization.environment'),
        'api_url' => config('clubify.api.url'),
        'last_error' => cache()->get('clubify.last_error'),
        'system_info' => [
            'os' => php_uname(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'curl_version' => curl_version()['version'] ?? 'N/A'
        ]
    ];
}
```

### Canais de Suporte

1. **Documentação**: https://docs.clubify.app
2. **GitHub Issues**: https://github.com/clubify/checkout-sdk-php/issues
3. **Email**: support@clubify.app
4. **Discord**: https://discord.gg/clubify
5. **Status Page**: https://status.clubify.app

### Níveis de Suporte

- **Community**: GitHub Issues, Discord
- **Professional**: Email support, SLA 24h
- **Enterprise**: Dedicated support, SLA 4h, phone support

---

## Conclusão

Este guia de troubleshooting cobre os problemas mais comuns encontrados ao usar o Clubify Checkout SDK. Lembre-se sempre de:

1. **Verificar logs** antes de reportar problemas
2. **Testar em ambiente sandbox** antes de produção
3. **Manter o SDK atualizado** para correções e melhorias
4. **Implementar monitoramento** proativo
5. **Usar cache** para otimizar performance

Para problemas não cobertos neste guia, consulte a documentação completa ou entre em contato com o suporte técnico.