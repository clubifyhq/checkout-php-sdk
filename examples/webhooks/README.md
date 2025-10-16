# Exemplos de Webhooks

Este diretório contém exemplos práticos de como configurar e usar webhooks com o Clubify Checkout SDK.

## Estrutura

```
webhooks/
├── README.md (este arquivo)
├── single-tenant/
│   └── simple-example.php           # Configuração single-tenant (simples)
└── multi-tenant/
    ├── callback-example.php         # Multi-tenant com callback customizado
    └── model-automatic-example.php  # Multi-tenant com model automático
```

## Quando Usar Cada Exemplo

### Single-Tenant (Simples)

**Use quando:**
- Sua aplicação tem apenas uma organização/loja
- Você não precisa isolar secrets por tenant
- Quer a configuração mais simples possível

**Vantagens:**
- Configuração mínima (apenas um secret global)
- Fácil de entender e implementar
- Menos código

**Arquivo:** `single-tenant/simple-example.php`

---

### Multi-Tenant com Callback Customizado

**Use quando:**
- Você precisa de controle total sobre como o secret é resolvido
- Sua estrutura de dados é complexa ou não convencional
- Você quer adicionar lógica customizada (logs, cache, fallbacks)
- Você tem múltiplos modelos que podem representar organizations

**Vantagens:**
- Máxima flexibilidade
- Permite lógica condicional
- Fácil debugging (adicione logs no callback)
- Suporta qualquer estrutura de dados

**Arquivo:** `multi-tenant/callback-example.php`

---

### Multi-Tenant com Model Automático

**Use quando:**
- Você segue a convenção Laravel/Eloquent
- Seu model Organization tem um campo `settings` (JSON)
- Você quer resolução automática sem código customizado
- Você prefere configuração por convenção

**Vantagens:**
- Nenhum código customizado necessário
- Configuração por convenção
- Menos código para manter
- Funciona automaticamente se você seguir a convenção

**Arquivo:** `multi-tenant/model-automatic-example.php`

---

## Execução dos Exemplos

### Pré-requisitos

```bash
cd /Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/checkout/php

# Instalar dependências
composer install
```

### Single-Tenant

```bash
php examples/webhooks/single-tenant/simple-example.php
```

**Saída esperada:**
```
=== EXEMPLO: Webhook Single-Tenant (Simples) ===

--- Configuração (config/clubify-checkout.php) ---
...
✓ Configuração definida

--- Simulação de Webhook ---
Payload: {"event":"order.paid","data":{"order_id":"order_123"...}}
Signature: sha256=abc123...
✅ Assinatura válida!
...
```

### Multi-Tenant (Callback)

```bash
php examples/webhooks/multi-tenant/callback-example.php
```

**Saída esperada:**
```
=== EXEMPLO: Webhook Multi-Tenant com Callback ===

--- Teste 1: Webhook da Acme Corp (Organization 1) ---
🔍 Resolvendo webhook secret...
✓ Organization ID: 1
✓ Organization: Acme Corp
✓ Secret encontrado: whsec_acme_co...
✅ Secret resolvido com sucesso!
✅ Assinatura válida!
...
```

### Multi-Tenant (Model Automático)

```bash
php examples/webhooks/multi-tenant/model-automatic-example.php
```

**Saída esperada:**
```
=== EXEMPLO: Webhook Multi-Tenant com Model Automático ===

--- Estrutura do Banco (Simulação) ---
✓ Estrutura criada (simulação)

--- Teste 1: Webhook da Loja Acme ---
🔍 SDK buscando secret para Organization 1...
✓ Organization encontrada: Loja Acme
✓ Secret encontrado: whsec_acme_12...
✅ Assinatura válida!
...
```

## Conceitos Importantes

### 1. Assinatura HMAC

Todos os webhooks enviados pelo Clubify são assinados com HMAC-SHA256:

```php
$signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
```

O SDK valida automaticamente essa assinatura usando o middleware `ValidateWebhook`.

### 2. Organization ID

Em ambientes multi-tenant, o `organization_id` identifica qual organização está recebendo o webhook:

**Via Header (recomendado para testes):**
```
X-Organization-ID: 123
```

**Via Payload (automático em produção):**
```json
{
  "event": "order.paid",
  "organization_id": "123",
  "data": { ... }
}
```

### 3. Ordem de Resolução (Multi-Tenant)

O SDK tenta resolver o secret nesta ordem:

1. **Callback customizado** (`webhook.secret_resolver`)
2. **Model Organization** automático
3. **Config global** (`webhook.secret`) - fallback

## Estrutura de Dados Esperada

### Organization Model (Multi-Tenant)

```php
class Organization extends Model
{
    protected $casts = [
        'settings' => 'array', // JSON
    ];
}
```

**Campo settings:**
```json
{
  "clubify_checkout_webhook_secret": "whsec_abc123...",
  "other_configs": "..."
}
```

**Ou campo direto:**
```php
$table->string('webhook_secret');
```

## Testes

Todos os exemplos incluem seções de testes automatizados com PHPUnit.

### Executar Testes

```bash
# Testes do SDK
./vendor/bin/phpunit tests/Feature/Webhook/

# Teste específico
./vendor/bin/phpunit tests/Feature/Webhook/ValidateWebhookMiddlewareTest.php
```

## Integração com Laravel

### Rota

```php
// routes/api.php
Route::post('/webhooks/clubify-checkout', [WebhookController::class, 'handle'])
    ->middleware('clubify.webhook');
```

### Controller

```php
// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->json('event');
        $data = $request->json('data');

        match($event) {
            'order.paid' => $this->handleOrderPaid($data),
            'payment.approved' => $this->handlePaymentApproved($data),
            default => Log::info("Evento não tratado: {$event}"),
        };

        return response('OK', 200);
    }
}
```

## Debugging

### Webhook.site

Use [webhook.site](https://webhook.site) para inspecionar webhooks durante desenvolvimento:

```php
// Configurar temporariamente
'webhook' => [
    'secret' => 'test_secret',
],

// URL: https://webhook.site/your-unique-id
```

### Logs

Ative logs detalhados:

```env
CLUBIFY_CHECKOUT_DEBUG=true
CLUBIFY_CHECKOUT_LOGGER_LEVEL=debug
```

### Endpoint de Debug

Crie um endpoint temporário:

```php
Route::post('/debug-webhook', function(Request $request) {
    \Log::info('Webhook Debug', [
        'headers' => $request->headers->all(),
        'body' => $request->getContent(),
    ]);
    return response('OK', 200);
});
```

## Troubleshooting

### Erro: "Webhook secret não configurado"

**Causa:** SDK não encontrou o secret.

**Soluções:**
1. Verificar se `CLUBIFY_CHECKOUT_WEBHOOK_SECRET` está no `.env`
2. Para multi-tenant: verificar se `organization_id` está sendo enviado
3. Verificar se Organization existe no banco
4. Verificar se secret está salvo no campo correto

### Erro: "Invalid signature"

**Causa:** Assinatura HMAC inválida.

**Soluções:**
1. Verificar se o secret está correto
2. Verificar se o payload não foi modificado
3. Testar manualmente:
   ```php
   $signature = hash_hmac('sha256', $payload, $secret);
   ```

### Erro: "Organization ID não encontrado"

**Causa:** Webhook não inclui `organization_id`.

**Soluções:**
1. Adicionar header `X-Organization-ID` manualmente (testes)
2. Verificar payload JSON do Clubify
3. Configurar fallback single-tenant

## Documentação Completa

Para informações detalhadas, consulte:

- **[Multi-Tenant Setup Guide](../../docs/webhooks/multi-tenant-setup.md)** - Guia completo de configuração multi-tenant
- **[Webhook Events Guide](../../docs/WEBHOOK_EVENTS_GUIDE.md)** - Lista completa de eventos e payloads
- **[Webhook Testing Guide](../../docs/WEBHOOK_TESTING.md)** - Guia de testes de webhooks

## Suporte

- **Issues:** [GitHub Issues](https://github.com/clubify/checkout-sdk-php/issues)
- **Documentação:** [docs/](../../docs/)
- **Changelog:** [CHANGELOG.md](../../CHANGELOG.md)

---

**Última Atualização:** 16 de Outubro de 2025
