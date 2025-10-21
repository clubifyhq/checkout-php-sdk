# Guia de Eventos de Webhook - Clubify Checkout SDK

Este guia fornece informações completas sobre todos os eventos de webhook suportados pelo Clubify Checkout SDK, incluindo exemplos de payload, casos de uso e melhores práticas.

## ⚠️ Important Update (v2.0.0)

**Webhook Architecture Change:**

As of v2.0.0, the way webhook configurations work has changed:

1. **One Configuration per Organization** (v1.x) is now **Multiple Configurations per Tenant** (v2.0.0+)
2. Each configuration must have a unique `name`
3. Use `createOrUpdateWebhook()` to safely add events without conflicts
4. `partnerId` is deprecated, use `tenantId` instead

See [MIGRATION_v2.md](./MIGRATION_v2.md) for full migration guide.

---

## Visão Geral

O sistema de webhooks do Clubify Checkout permite que você receba notificações em tempo real sobre eventos importantes que ocorrem em sua aplicação. Cada evento é enviado como uma requisição HTTP POST para as URLs configuradas.

## Eventos Suportados

> ⚠️ **IMPORTANTE**: Esta é a lista oficial e completa de eventos suportados pela API. Qualquer evento fora desta lista será rejeitado com erro HTTP 400.

### 📦 Eventos de Pedido (Order Events)

#### `order.created` - Pedido Criado
Disparado quando um novo pedido é criado no sistema.

**Casos de uso:**
- Iniciar processo de fulfillment
- Enviar confirmação por email
- Atualizar inventário

**Exemplo de payload:**
```json
{
  "event": "order.created",
  "data": {
    "orderId": "order_123456",
    "status": "pending",
    "total": 19900,
    "currency": "BRL",
    "customer": {
      "customerId": "cust_789",
      "name": "João Silva",
      "email": "joao@exemplo.com"
    }
  }
}
```

#### `order.paid` - Pedido Pago
**⭐ EVENTO MAIS IMPORTANTE** - Disparado quando o pagamento de um pedido é confirmado.

**Casos de uso:**
- Liberar produtos digitais
- Iniciar envio de produtos físicos
- Enviar nota fiscal
- Ativar assinaturas

**Exemplo de payload:**
```json
{
  "event": "order.paid",
  "data": {
    "eventType": "order.paid",
    "orderId": "order_123456789",
    "partnerId": "partner_abc",
    "organizationId": "org_xyz",
    "customer": {
      "customerId": "cust_789",
      "name": "João Silva",
      "email": "joao@exemplo.com",
      "phone": "+55 (11) 99999-9999",
      "document": "12345678901"
    },
    "items": [
      {
        "productId": "prod_digital_001",
        "name": "Curso de Marketing Digital",
        "quantity": 1,
        "unitPrice": 19900,
        "totalPrice": 19900,
        "type": "digital"
      }
    ],
    "subtotal": 19900,
    "shippingCost": 0,
    "discount": 1990,
    "total": 17910,
    "currency": "BRL",
    "payment": {
      "method": "credit_card",
      "brand": "visa",
      "lastFourDigits": "1234",
      "installments": 3,
      "status": "paid",
      "paidAt": "2025-09-23T10:30:00Z"
    },
    "orderDate": "2025-09-23T10:30:00Z",
    "priority": "high"
  }
}
```

#### `order.shipped` - Pedido Enviado
Disparado quando um pedido físico é despachado.

#### `order.delivered` - Pedido Entregue
Disparado quando um pedido físico é entregue.

#### `order.completed` - Pedido Concluído
Disparado quando todo o processo do pedido é finalizado.

#### `order.cancelled` - Pedido Cancelado
Disparado quando um pedido é cancelado.

#### `order.refunded` - Pedido Reembolsado
Disparado quando um pedido é reembolsado.

### 💳 Eventos de Pagamento (Payment Events)

#### `payment.authorized` - Pagamento Autorizado
Disparado quando um pagamento é pré-autorizado (mas ainda não capturado).

#### `payment.paid` - Pagamento Pago
Disparado quando um pagamento é confirmado/capturado.

#### `payment.failed` - Pagamento Falhou
**CRÍTICO** - Disparado quando um pagamento falha.

**Casos de uso:**
- Notificar cliente sobre falha
- Tentar método de pagamento alternativo
- Análise de fraude

#### `payment.refunded` - Pagamento Reembolsado
Disparado quando um pagamento é estornado.

#### `payment.cancelled` - Pagamento Cancelado
Disparado quando um pagamento é cancelado.

#### `payment.method.saved` - Método de Pagamento Salvo
Disparado quando um método de pagamento é salvo para uso futuro.

#### `payment.action_required` - Ação Requerida no Pagamento
Disparado quando o pagamento requer uma ação adicional (ex: 3D Secure).

#### `payment_method.changed` - Método de Pagamento Alterado
⚠️ **Nota**: Use underscore (`payment_method.changed`), não ponto.

#### `payment_method.setup_completed` - Configuração de Método de Pagamento Concluída
Disparado quando a configuração de um método de pagamento é finalizada.

### 👥 Eventos de Cliente (Customer Events)

#### `customer.created` - Cliente Criado
Disparado quando um novo cliente é registrado.

#### `customer.updated` - Cliente Atualizado
Disparado quando dados de um cliente são atualizados.

#### `customer.deleted` - Cliente Removido
Disparado quando um cliente é removido (GDPR compliance).

#### `customer.merged` - Clientes Mesclados
Disparado quando dois perfis de cliente são mesclados.

#### `customer.segment.changed` - Segmento do Cliente Alterado
Disparado quando o segmento de um cliente é alterado.

#### `customer.consent.updated` - Consentimento do Cliente Atualizado
Disparado quando as preferências de consentimento são atualizadas.

#### `customer.metrics.updated` - Métricas do Cliente Atualizadas
Disparado quando as métricas de um cliente são recalculadas.

#### `customer.address.added` - Endereço Adicionado
⚠️ **Nota**: Use `address.added`, não `address.created`.

#### `customer.address.updated` - Endereço Atualizado
Disparado quando um endereço é modificado.

#### `customer.address.deleted` - Endereço Removido
Disparado quando um endereço é deletado.

### 🛒 Eventos de Carrinho (Cart Events)

#### `cart.created` - Carrinho Criado
Disparado quando um novo carrinho é criado.

#### `cart.updated` - Carrinho Atualizado
Disparado quando itens são adicionados/removidos do carrinho.

#### `cart.abandoned` - Carrinho Abandonado
**IMPORTANTE** - Disparado quando um carrinho é abandonado por um período de tempo.

**Casos de uso:**
- Campanhas de recuperação de carrinho
- Análise de abandono
- Ofertas especiais

#### `cart.recovered` - Carrinho Recuperado
Disparado quando um carrinho abandonado é recuperado.

#### `cart.cleanup` - Limpeza de Carrinho
Disparado quando carrinhos antigos são removidos do sistema.

#### `cart.converted` - Carrinho Convertido
Disparado quando um carrinho é convertido em pedido.

### 📱 Eventos de Assinatura (Subscription Events)

#### `subscription.created` - Assinatura Criada
Disparado quando uma nova assinatura é criada.

#### `subscription.activated` - Assinatura Ativada
Disparado quando uma assinatura é ativada.

#### `subscription.cancelled` - Assinatura Cancelada
Disparado quando uma assinatura é cancelada.

#### `subscription.canceled` - Assinatura Cancelada (alternativa)
Alias para `subscription.cancelled`.

#### `subscription.updated` - Assinatura Atualizada
Disparado quando dados da assinatura são modificados.

#### `subscription.access_suspended` - Acesso Suspenso
Disparado quando o acesso à assinatura é suspenso.

#### `subscription.canceled_for_nonpayment` - Cancelada por Não Pagamento
Disparado quando uma assinatura é cancelada devido a não pagamento.

#### `subscription.access_revoked` - Acesso Revogado
Disparado quando o acesso é completamente revogado.

#### `subscription.trial_ending` - Trial Terminando
⚠️ **Nota**: Use underscore (`trial_ending`), não ponto.
Disparado quando o período de trial está próximo do fim.

#### `subscription.trial_converted` - Trial Convertido
⚠️ **Nota**: Use `trial_converted`, não `trial.ended`.
Disparado quando um trial é convertido em assinatura paga.

#### `subscription.payment_failed` - Falha no Pagamento da Assinatura
⚠️ **Nota**: Use underscore (`payment_failed`), não ponto.
**CRÍTICO** - Disparado quando o pagamento recorrente falha.

#### `subscription.payment_recovered` - Pagamento Recuperado
⚠️ **Nota**: Use underscore (`payment_recovered`), não ponto.
Disparado quando um pagamento falhado é recuperado.

### 🛍️ Eventos de Checkout (Checkout Events)

#### `checkout.created` - Checkout Criado
Disparado quando uma sessão de checkout é iniciada.

#### `checkout.confirmed` - Checkout Confirmado
Disparado quando o checkout é confirmado.

#### `checkout.failed` - Checkout Falhou
Disparado quando o processo de checkout falha.

#### `checkout.expired` - Checkout Expirado
Disparado quando uma sessão de checkout expira.

#### `checkout.payment_method_updated` - Método de Pagamento Atualizado no Checkout
⚠️ **Nota**: Use underscore (`payment_method_updated`).

### 👤 Eventos de Usuário (User Events)

#### `user.updated` - Usuário Atualizado
Disparado quando dados do usuário são atualizados.

#### `user.preferences.updated` - Preferências do Usuário Atualizadas
Disparado quando preferências são modificadas.

#### `user.data.deleted` - Dados do Usuário Deletados
Disparado quando dados do usuário são removidos (GDPR).

### 📦 Eventos de Produto (Product Events)

#### `product.created` - Produto Criado
Disparado quando um novo produto é adicionado.

#### `product.updated` - Produto Atualizado
Disparado quando informações do produto são modificadas.

#### `product.deleted` - Produto Deletado
Disparado quando um produto é removido.

### 🎟️ Eventos de Cupom/Promoção (Coupon/Promotion Events)

#### `coupon.validated` - Cupom Validado
Disparado quando um cupom é verificado.

#### `coupon.applied` - Cupom Aplicado
Disparado quando um cupom é aplicado a um pedido.

#### `promotions.detected` - Promoções Detectadas
Disparado quando promoções aplicáveis são detectadas.

#### `promotion.applied` - Promoção Aplicada
Disparado quando uma promoção é aplicada.

### 💰 Eventos de Dunning (Dunning Events)

#### `dunning.email_required` - Email de Dunning Necessário
Disparado quando um email de cobrança precisa ser enviado.

#### `dunning.sms_required` - SMS de Dunning Necessário
Disparado quando um SMS de cobrança precisa ser enviado.

#### `dunning.payment_recovered` - Pagamento Recuperado via Dunning
Disparado quando um pagamento é recuperado através do processo de dunning.

### 🔒 Eventos de GDPR (GDPR Events)

#### `gdpr.audit` - Auditoria GDPR
Disparado para fins de auditoria GDPR.

### 🚀 Eventos de One-Click Checkout (OneClickCheckout Events)

#### `oneclickcheckout.initiated` - One-Click Checkout Iniciado
Disparado quando um checkout com um clique é iniciado.

#### `oneclickcheckout.processing` - One-Click Checkout em Processamento
Disparado quando o checkout está sendo processado.

#### `oneclickcheckout.completed` - One-Click Checkout Completo
Disparado quando o checkout é finalizado com sucesso.

#### `oneclickcheckout.failed` - One-Click Checkout Falhou
Disparado quando o checkout falha.

### 🤝 Eventos de Afiliados (Affiliate Events)

#### `affiliate.registered` - Afiliado Registrado
Disparado quando um novo afiliado se registra.

#### `affiliate.approved` - Afiliado Aprovado
Disparado quando um afiliado é aprovado.

#### `affiliate.click` - Click de Afiliado
Disparado quando um link de afiliado é clicado.

#### `affiliate.conversion` - Conversão de Afiliado
Disparado quando uma venda de afiliado é confirmada.

### 💳 Eventos de Carteira Digital (Digital Wallet Events)

#### `digital-wallet.payment.processed` - Pagamento de Carteira Digital Processado
Disparado quando um pagamento via carteira digital é processado.

#### `digital-wallet.payment.failed` - Pagamento de Carteira Digital Falhou
Disparado quando um pagamento via carteira digital falha.

### 🔑 Eventos de API Key (API Key Events)

#### `api-key.generated` - API Key Gerada
Disparado quando uma nova API key é gerada.

#### `api-key.updated` - API Key Atualizada
Disparado quando uma API key é atualizada.

#### `api-key.revoked` - API Key Revogada
Disparado quando uma API key é revogada.

#### `api-key.rotated` - API Key Rotacionada
Disparado quando uma API key é rotacionada.

### 🧪 Eventos de Teste (Test Events)

#### `test` - Evento de Teste
**IMPORTANTE** - Evento especial usado para testar a configuração de webhooks.

**Casos de uso:**
- Validar configuração de webhook antes de produção
- Testar conectividade e processamento de eventos
- Verificar assinatura HMAC e validação
- Troubleshooting de problemas de integração

**Exemplo de payload:**
```json
{
  "event": "test",
  "data": {
    "message": "This is a test webhook event",
    "timestamp": "2025-10-16T10:30:00Z",
    "webhookId": "webhook_test_123"
  }
}
```

**Exemplo de teste:**
```php
<?php

use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'sua_api_key',
    'environment' => 'sandbox'
]);

// Criar webhook com evento de teste
$webhook = $sdk->webhooks()->create([
    'url' => 'https://seu-site.com/webhooks/test',
    'events' => ['test'],
    'secret' => 'webhook_secret_super_seguro_32_chars',
    'active' => true,
    'description' => 'Webhook de teste'
]);

// Simular evento de teste
$testResult = $sdk->webhooks()->testing()->simulateEvent(
    $webhook['id'],
    'test',
    ['message' => 'Testing webhook configuration']
);

if ($testResult['success']) {
    echo "Webhook configurado corretamente!";
} else {
    echo "Erro: " . $testResult['error'];
}
```

## Configuração de Webhooks

### Exemplo de Criação

```php
<?php

use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'sua_api_key',
    'environment' => 'production'
]);

// Criar webhook para eventos principais
$webhook = $sdk->webhooks()->create([
    'url' => 'https://seu-site.com/webhooks/clubify',
    'events' => [
        'order.paid',        // Evento mais importante
        'order.created',
        'order.cancelled',
        'payment.failed',    // Para tratamento de erros
        'cart.abandoned',    // Para recuperação
        'customer.created',
        'test'               // Para testes e validação
    ],
    'secret' => 'webhook_secret_super_seguro_32_chars',
    'active' => true,
    'description' => 'Webhook principal para eventos críticos'
]);
```

### Validação de Assinatura

Todos os webhooks são assinados com HMAC-SHA256:

```php
<?php

function validateWebhookSignature($payload, $signature, $secret) {
    $expectedSignature = hash_hmac('sha256', $payload, $secret);
    return hash_equals($signature, $expectedSignature);
}

// No seu endpoint de webhook
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (!validateWebhookSignature($payload, $signature, 'seu_secret')) {
    http_response_code(401);
    exit('Assinatura inválida');
}

$event = json_decode($payload, true);
// Processar evento...
```

## Suporte Multi-Tenant (Multi-Tenancy)

O SDK agora suporta webhooks em ambientes multi-tenant, permitindo que cada organização tenha seu próprio webhook secret. Há três formas de configurar:

### 1. Callback Customizado (Recomendado)

Configure um callback para resolver o secret dinamicamente:

```php
<?php

// config/clubify-checkout.php
return [
    'webhook' => [
        'secret_resolver' => function(\Illuminate\Http\Request $request) {
            // Obter organization_id do header
            $orgId = $request->header('X-Organization-ID');

            // Buscar secret da organização
            $org = \App\Models\Organization::find($orgId);
            return $org?->clubify_checkout_webhook_secret;
        },
    ],
];
```

### 2. Model Automático (Laravel)

Configure o model Organization para resolução automática:

```php
<?php

// config/clubify-checkout.php
return [
    'webhook' => [
        // Model da organização
        'organization_model' => '\\App\\Models\\Organization',

        // Campo onde está o secret (busca em 3 locais)
        'organization_secret_key' => 'clubify_checkout_webhook_secret',
    ],
];
```

O SDK buscará o secret em:
1. `$organization->settings['clubify_checkout_webhook_secret']` (array settings)
2. `$organization->webhook_secret` (campo direto)
3. `$organization->clubify_checkout_webhook_secret` (campo customizado)

### 3. Secret Global (Fallback)

Para aplicações single-tenant:

```php
<?php

// config/clubify-checkout.php
return [
    'webhook' => [
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];
```

### Headers Multi-Tenant

O webhook deve enviar o `X-Organization-ID` header:

```
X-Organization-ID: org_123456789
X-Clubify-Signature: abc123...
X-Clubify-Timestamp: 1234567890
```

Se não houver header, o SDK tentará extrair do payload:
- `data.organization_id`
- `organization_id`
- `data.organizationId`

### Exemplo Completo Multi-Tenant

```php
<?php

// Model Organization
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    protected $casts = [
        'settings' => 'array',
    ];

    // Opção 1: Settings array
    public function getWebhookSecret(): ?string
    {
        return $this->settings['clubify_checkout_webhook_secret'] ?? null;
    }

    // Opção 2: Campo direto no banco
    // protected $fillable = ['webhook_secret'];
}

// Configuração
// config/clubify-checkout.php
return [
    'webhook' => [
        // Callback customizado (prioridade 1)
        'secret_resolver' => function($request) {
            $orgId = $request->header('X-Organization-ID');
            $org = \App\Models\Organization::find($orgId);
            return $org?->getWebhookSecret();
        },

        // Model automático (fallback)
        'organization_model' => '\\App\\Models\\Organization',
        'organization_secret_key' => 'clubify_checkout_webhook_secret',

        // Secret global (fallback final)
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
    ],
];

// Uso do Middleware Laravel
Route::post('/webhooks/clubify', function (Request $request) {
    // O middleware ValidateWebhook já validou a assinatura
    // usando o secret da organização correta

    $event = $request->attributes->get('webhook_event');
    $data = $request->attributes->get('webhook_data');
    $orgId = $request->header('X-Organization-ID');

    // Processar evento para a organização específica
    event(new WebhookReceived($event, $data, $orgId));

    return response('OK', 200);
})->middleware('clubify.webhook');
```

### Variáveis de Ambiente Multi-Tenant

```env
# Single-tenant (fallback)
CLUBIFY_CHECKOUT_WEBHOOK_SECRET=webhook_secret_32_chars

# Multi-tenant
CLUBIFY_ORGANIZATION_MODEL=\\App\\Models\\Organization
CLUBIFY_WEBHOOK_SECRET_KEY=clubify_checkout_webhook_secret
```

## Melhores Práticas

### ✅ Implementação Segura

1. **Sempre valide a assinatura HMAC**
2. **Use HTTPS em produção**
3. **Implemente idempotência** (mesmo evento pode ser enviado múltiplas vezes)
4. **Responda rapidamente** (timeout padrão: 30 segundos)
5. **Use status HTTP 200** para sucesso

### ✅ Tratamento de Eventos

```php
<?php

function processWebhookEvent($event) {
    // Verificar se evento já foi processado (idempotência)
    if (isEventProcessed($event['id'])) {
        return;
    }

    switch ($event['event']) {
        case 'order.paid':
            handleOrderPaid($event['data']);
            break;

        case 'payment.failed':
            handlePaymentFailed($event['data']);
            break;

        case 'cart.abandoned':
            scheduleCartRecoveryEmail($event['data']);
            break;

        case 'test':
            handleTestEvent($event['data']);
            break;

        default:
            error_log("Evento não tratado: " . $event['event']);
    }

    // Marcar evento como processado
    markEventAsProcessed($event['id']);
}
```

### ✅ Monitoramento e Debug

```php
<?php

// Teste de conectividade
$validation = $sdk->webhooks()->validateUrl('https://seu-site.com/webhook');

if (!$validation['accessible']) {
    echo "Erro: " . $validation['error'];
}

// Teste completo de webhook
$testResult = $sdk->webhooks()->testing()->testWebhook($webhookId);

// Simulação de evento
$simulation = $sdk->webhooks()->testing()->simulateEvent(
    $webhookId,
    'order.paid',
    ['orderId' => 'test_123']
);
```

## Códigos de Resposta

| Código | Significado | Ação do Sistema |
|--------|-------------|-----------------|
| 200-299 | Sucesso | Marcar como entregue |
| 400-499 | Erro do cliente | Não retentar |
| 500-599 | Erro do servidor | Retentar com backoff |
| Timeout | Sem resposta | Retentar com backoff |

## Retry e Fallback

- **Tentativas automáticas**: Até 5 tentativas
- **Backoff exponencial**: 1s, 2s, 4s, 8s, 16s
- **Timeout por tentativa**: 30 segundos
- **Após 5 falhas**: Webhook desativado automaticamente

## Exemplos de Endpoint

### PHP Básico

```php
<?php
// webhook-endpoint.php

$payload = file_get_contents('php://input');
$event = json_decode($payload, true);

// Validar assinatura (implementar função)
if (!validateSignature($payload, $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])) {
    http_response_code(401);
    exit();
}

switch ($event['event']) {
    case 'order.paid':
        // Liberar produto, enviar email, etc.
        liberarProduto($event['data']['orderId']);
        break;

    case 'cart.abandoned':
        // Agendar email de recuperação
        agendarEmailRecuperacao($event['data']['customerId']);
        break;
}

http_response_code(200);
echo 'OK';
?>
```

### Laravel

```php
<?php
// routes/web.php
Route::post('/webhooks/clubify', [WebhookController::class, 'handle']);

// app/Http/Controllers/WebhookController.php
class WebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->json()->all();

        event(new WebhookReceived($event));

        return response('OK', 200);
    }
}
```

## Suporte e Debugging

### Logs Úteis

- **URL de teste**: https://webhook.site (para testes)
- **Ferramenta de debug**: Use `generateDebugReport()` do SDK
- **Logs do sistema**: Verifique logs de entrega no painel

### Problemas Comuns

1. **URL não acessível**: Verificar firewall, DNS
2. **Timeout**: Otimizar processamento do endpoint
3. **Assinatura inválida**: Verificar secret configurado
4. **Eventos duplicados**: Implementar verificação de idempotência

---

## 📚 Referência Rápida de Eventos

### Lista Completa de Eventos Válidos

```php
// 💳 Payment Events (9 eventos)
'payment.authorized'
'payment.paid'
'payment.failed'
'payment.refunded'
'payment.cancelled'
'payment.method.saved'
'payment.action_required'
'payment_method.changed'              // ⚠️ underscore
'payment_method.setup_completed'      // ⚠️ underscore

// 📦 Order Events (7 eventos)
'order.created'
'order.paid'
'order.shipped'
'order.delivered'
'order.cancelled'
'order.refunded'
'order.completed'

// 📱 Subscription Events (12 eventos)
'subscription.created'
'subscription.activated'
'subscription.cancelled'
'subscription.canceled'               // alternativa
'subscription.updated'
'subscription.access_suspended'
'subscription.canceled_for_nonpayment'
'subscription.access_revoked'
'subscription.trial_ending'           // ⚠️ underscore
'subscription.trial_converted'        // ⚠️ underscore
'subscription.payment_failed'         // ⚠️ underscore
'subscription.payment_recovered'      // ⚠️ underscore

// 👥 Customer Events (10 eventos)
'customer.created'
'customer.updated'
'customer.deleted'
'customer.merged'
'customer.segment.changed'
'customer.consent.updated'
'customer.metrics.updated'
'customer.address.added'              // ⚠️ "added", não "created"
'customer.address.updated'
'customer.address.deleted'

// 👤 User Events (3 eventos)
'user.updated'
'user.preferences.updated'
'user.data.deleted'

// 🛒 Cart Events (5 eventos)
'cart.created'
'cart.updated'
'cart.abandoned'
'cart.recovered'
'cart.cleanup'
'cart.converted'

// 🛍️ Checkout Events (5 eventos)
'checkout.created'
'checkout.confirmed'
'checkout.failed'
'checkout.expired'
'checkout.payment_method_updated'     // ⚠️ underscore

// 📦 Product Events (3 eventos)
'product.created'
'product.updated'
'product.deleted'

// 🎟️ Coupon/Promotion Events (4 eventos)
'coupon.validated'
'coupon.applied'
'promotions.detected'
'promotion.applied'

// 💰 Dunning Events (3 eventos)
'dunning.email_required'
'dunning.sms_required'
'dunning.payment_recovered'

// 🔒 GDPR Events (1 evento)
'gdpr.audit'

// 🚀 OneClickCheckout Events (4 eventos)
'oneclickcheckout.initiated'
'oneclickcheckout.processing'
'oneclickcheckout.completed'
'oneclickcheckout.failed'

// 🤝 Affiliate Events (4 eventos)
'affiliate.registered'
'affiliate.approved'
'affiliate.click'
'affiliate.conversion'

// 💳 Digital Wallet Events (2 eventos)
'digital-wallet.payment.processed'
'digital-wallet.payment.failed'

// 🔑 API Key Events (4 eventos)
'api-key.generated'
'api-key.updated'
'api-key.revoked'
'api-key.rotated'

// 🧪 Test Events (1 evento)
'test'
```

**Total: 91 eventos suportados**

### ❌ Eventos que NÃO EXISTEM (Não Use)

Estes eventos são comumente procurados mas **não existem na API** e causarão erro HTTP 400:

```php
// ❌ EVENTOS INEXISTENTES
'subscription.renewed'          // Não existe
'subscription.expired'          // Não existe
'subscription.trial.ended'      // Use: subscription.trial_converted
'customer.address.created'      // Use: customer.address.added
'coupon.removed'                // Não existe
'coupon.expired'                // Não existe
'invoice.created'               // Não existe (nenhum evento de invoice)
'invoice.paid'                  // Não existe
'invoice.voided'                // Não existe
'invoice.payment.failed'        // Não existe
```

### ⚠️ Notas Importantes sobre Sintaxe

#### Underscore vs Ponto

Alguns eventos usam **underscore (_)** ao invés de **ponto (.)** - preste atenção:

```php
// ✅ CORRETO (underscore)
'payment_method.changed'
'payment_method.setup_completed'
'subscription.trial_ending'
'subscription.trial_converted'
'subscription.payment_failed'
'subscription.payment_recovered'
'checkout.payment_method_updated'

// ❌ ERRADO (ponto)
'payment.method.changed'           // Erro!
'subscription.trial.ending'        // Erro!
'subscription.payment.failed'      // Erro!
```

#### Variações de Nomenclatura

```php
// customer.address
'customer.address.added'    // ✅ Use este
'customer.address.created'  // ❌ Não existe

// subscription.trial
'subscription.trial_converted'  // ✅ Use este
'subscription.trial.ended'      // ❌ Não existe

// subscription status
'subscription.cancelled'  // ✅ UK spelling
'subscription.canceled'   // ✅ US spelling (ambos funcionam)
```

### 💡 Eventos Recomendados para Produção

Se você está começando, implemente estes eventos essenciais:

```php
$criticalEvents = [
    // ⭐ CRÍTICOS (implementar primeiro)
    'order.paid',
    'payment.failed',
    'subscription.payment_failed',

    // 🔴 IMPORTANTES
    'order.created',
    'order.cancelled',
    'order.refunded',
    'payment.paid',
    'subscription.created',
    'subscription.cancelled',

    // 🟡 ÚTEIS
    'cart.abandoned',
    'cart.recovered',
    'customer.created',
    'subscription.trial_ending',

    // 🧪 TESTES
    'test',
];
```

---

## Conclusão

Os webhooks são essenciais para uma integração robusta com o Clubify Checkout. Implemente especialmente os eventos `order.paid` e `payment.failed` para garantir uma experiência completa aos seus clientes.

**Lembre-se:**
- ✅ Use apenas eventos da lista oficial (91 eventos suportados)
- ⚠️ Atenção à sintaxe: alguns usam underscore (_), não ponto (.)
- ❌ Eventos fora da lista causarão erro HTTP 400
- 📝 Consulte sempre esta documentação antes de adicionar novos eventos

Para mais informações, consulte a [documentação completa da API](./api-reference.md) ou entre em contato com o suporte.