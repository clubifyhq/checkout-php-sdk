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

### 👥 Eventos de Cliente (Customer Events)

#### `customer.created` - Cliente Criado
Disparado quando um novo cliente é registrado.

#### `customer.updated` - Cliente Atualizado
Disparado quando dados de um cliente são atualizados.

#### `customer.deleted` - Cliente Removido
Disparado quando um cliente é removido (GDPR compliance).

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

### 📱 Eventos de Assinatura (Subscription Events)

#### `subscription.created` - Assinatura Criada
Disparado quando uma nova assinatura é criada.

#### `subscription.activated` - Assinatura Ativada
Disparado quando uma assinatura é ativada.

#### `subscription.cancelled` - Assinatura Cancelada
Disparado quando uma assinatura é cancelada.

#### `subscription.payment_failed` - Falha no Pagamento da Assinatura
**CRÍTICO** - Disparado quando o pagamento recorrente falha.

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
        'customer.created'
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

## Conclusão

Os webhooks são essenciais para uma integração robusta com o Clubify Checkout. Implemente especialmente os eventos `order.paid` e `payment.failed` para garantir uma experiência completa aos seus clientes.

Para mais informações, consulte a [documentação completa da API](./api-reference.md) ou entre em contato com o suporte.