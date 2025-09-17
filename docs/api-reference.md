# API Reference - Clubify Checkout SDK PHP

## Visão Geral

Esta referência documenta todas as APIs disponíveis no Clubify Checkout SDK PHP v2.0, incluindo os novos módulos enterprise implementados no Sprint 4.

---

## Core SDK

### ClubifyCheckoutSDK

Classe principal para inicialização e acesso aos módulos.

```php
class ClubifyCheckoutSDK
{
    public function __construct(array $config)
    public function orders(): OrdersModule
    public function payments(): PaymentsModule
    public function customers(): CustomersModule
    public function subscriptions(): SubscriptionsModule
    public function analytics(): AnalyticsModule
    public function notifications(): NotificationsModule
    public function shipping(): ShippingModule
    public function webhooks(): WebhooksModule
    public function products(): ProductsModule
}
```

#### Configuração

```php
$config = [
    'api_url' => string,      // URL da API (obrigatório)
    'tenant_id' => string,    // ID do tenant (obrigatório)
    'api_key' => string,      // Chave da API (obrigatório)
    'secret_key' => string,   // Chave secreta (obrigatório)
    'debug' => bool,          // Modo debug (opcional, padrão: false)
    'timeout' => int,         // Timeout em segundos (opcional, padrão: 30)
    'retry_attempts' => int   // Tentativas de retry (opcional, padrão: 3)
];
```

---

## Orders Module

### Métodos Principais

#### `createOrder(array $data): array`

Cria um novo pedido.

**Parâmetros:**
```php
[
    'customer_id' => string,        // ID do cliente (obrigatório)
    'items' => array,              // Array de items (obrigatório)
    'total' => int,                // Total em centavos (obrigatório)
    'currency' => string,          // Moeda (obrigatório)
    'payment_method' => string,    // Método de pagamento (obrigatório)
    'shipping_address' => array,   // Endereço de entrega (opcional)
    'billing_address' => array,    // Endereço de cobrança (opcional)
    'coupon_code' => string,       // Código do cupom (opcional)
    'discount_amount' => int,      // Desconto em centavos (opcional)
    'shipping_amount' => int,      // Frete em centavos (opcional)
    'metadata' => array          // Metadados customizados (opcional)
]
```

**Estrutura de Item:**
```php
[
    'id' => string,        // ID do produto
    'name' => string,      // Nome do produto
    'price' => int,        // Preço em centavos
    'quantity' => int,     // Quantidade
    'sku' => string,       // SKU (opcional)
    'description' => string // Descrição (opcional)
]
```

**Resposta:**
```php
[
    'id' => string,
    'customer_id' => string,
    'status' => string,
    'items' => array,
    'total' => int,
    'currency' => string,
    'created_at' => string,
    'updated_at' => string
]
```

#### `getOrder(string $orderId): array`

Busca um pedido pelo ID.

#### `updateOrderStatus(string $orderId, string $status): bool`

Atualiza o status de um pedido.

**Status Válidos:**
- `pending` - Aguardando processamento
- `confirmed` - Confirmado
- `processing` - Em processamento
- `shipped` - Enviado
- `delivered` - Entregue
- `cancelled` - Cancelado
- `refunded` - Reembolsado

#### `listOrders(array $filters = []): array`

Lista pedidos com filtros opcionais.

**Filtros Disponíveis:**
```php
[
    'customer_id' => string,
    'status' => string,
    'date_from' => string,    // YYYY-MM-DD
    'date_to' => string,      // YYYY-MM-DD
    'limit' => int,           // Padrão: 50
    'offset' => int           // Padrão: 0
]
```

#### `getOrderStatusHistory(string $orderId): array`

Obtém o histórico de status de um pedido.

#### `addOrderNote(string $orderId, string $note): bool`

Adiciona uma nota ao pedido.

#### `addTrackingInfo(string $orderId, array $tracking): bool`

Adiciona informações de rastreamento.

```php
[
    'tracking_code' => string,
    'carrier' => string,
    'estimated_delivery' => string,  // YYYY-MM-DD
    'tracking_url' => string         // (opcional)
]
```

---

## Payments Module

### Métodos Principais

#### `processPayment(array $data): array`

Processa um pagamento.

**Parâmetros:**
```php
[
    'order_id' => string,           // ID do pedido (obrigatório)
    'amount' => int,                // Valor em centavos (obrigatório)
    'currency' => string,           // Moeda (obrigatório)
    'payment_method' => string,     // Método de pagamento (obrigatório)
    'card_data' => array,          // Dados do cartão (se aplicável)
    'customer' => array,           // Dados do cliente (obrigatório)
    'billing_address' => array,    // Endereço de cobrança (opcional)
    'installments' => int,         // Parcelas (opcional, padrão: 1)
    'capture' => bool,             // Capturar automaticamente (opcional, padrão: true)
    'soft_descriptor' => string    // Descrição no extrato (opcional)
]
```

**Dados do Cartão:**
```php
[
    'number' => string,        // Número do cartão
    'expiry_month' => string,  // Mês de expiração (MM)
    'expiry_year' => string,   // Ano de expiração (YYYY)
    'cvv' => string,          // Código de segurança
    'holder_name' => string   // Nome do portador
]
```

**Métodos de Pagamento Suportados:**
- `credit_card` - Cartão de crédito
- `debit_card` - Cartão de débito
- `pix` - PIX
- `boleto` - Boleto bancário
- `bank_transfer` - Transferência bancária

#### `getPayment(string $paymentId): array`

Busca um pagamento pelo ID.

#### `refundPayment(string $paymentId, array $options = []): array`

Estorna um pagamento.

```php
[
    'amount' => int,      // Valor a estornar (opcional, padrão: total)
    'reason' => string    // Motivo do estorno (opcional)
]
```

#### `cancelPayment(string $paymentId): array`

Cancela um pagamento antes da captura.

#### `tokenizeCard(array $cardData): array`

Tokeniza um cartão para uso futuro.

#### `configureGateway(array $config): bool`

Configura um gateway de pagamento.

```php
[
    'provider' => string,      // Nome do provedor
    'credentials' => array,    // Credenciais do gateway
    'webhook_secret' => string // Segredo do webhook (opcional)
]
```

---

## Customers Module

### Métodos Principais

#### `createCustomer(array $data): array`

Cria um novo cliente.

**Parâmetros:**
```php
[
    'name' => string,             // Nome completo (obrigatório)
    'email' => string,            // Email (obrigatório)
    'document' => string,         // CPF/CNPJ (obrigatório para BR)
    'phone' => string,            // Telefone formato internacional (obrigatório)
    'birth_date' => string,       // Data de nascimento YYYY-MM-DD (opcional)
    'type' => string,             // 'individual' ou 'business' (opcional, padrão: individual)
    'company_name' => string,     // Nome da empresa (se type = business)
    'company_document' => string, // CNPJ (se type = business)
    'contact_person' => string,   // Pessoa de contato (se type = business)
    'address' => array,           // Endereço (opcional)
    'preferences' => array        // Preferências (opcional)
]
```

**Estrutura de Endereço:**
```php
[
    'street' => string,
    'complement' => string,      // (opcional)
    'neighborhood' => string,
    'city' => string,
    'state' => string,
    'postal_code' => string,
    'country' => string
]
```

**Preferências:**
```php
[
    'language' => string,           // Idioma (padrão: pt-BR)
    'currency' => string,           // Moeda (padrão: BRL)
    'timezone' => string,           // Timezone (padrão: America/Sao_Paulo)
    'marketing_emails' => bool,     // Aceita emails de marketing
    'sms_notifications' => bool,    // Aceita SMS
    'push_notifications' => bool    // Aceita push notifications
]
```

#### `getCustomer(string $customerId): array`

Busca um cliente pelo ID.

#### `getCustomerByEmail(string $email): array`

Busca um cliente pelo email.

#### `getCustomerByDocument(string $document): array`

Busca um cliente pelo documento.

#### `updateCustomer(string $customerId, array $data): array`

Atualiza dados de um cliente.

#### `updateCustomerField(string $customerId, string $field, mixed $value): bool`

Atualiza um campo específico do cliente.

#### `listCustomers(array $filters = []): array`

Lista clientes com filtros.

**Filtros:**
```php
[
    'status' => string,           // Status do cliente
    'created_after' => string,    // Data de criação (YYYY-MM-DD)
    'created_before' => string,   // Data limite (YYYY-MM-DD)
    'segment' => string,          // Segmento
    'limit' => int,
    'offset' => int
]
```

#### `authenticateCustomer(array $credentials): array`

Autentica um cliente.

```php
[
    'email' => string,
    'password' => string
]
```

#### `validateToken(string $token): array`

Valida um token de autenticação.

#### `revokeToken(string $token): bool`

Revoga um token de autenticação.

---

## Subscriptions Module

### Métodos Principais

#### `createPlan(array $data): array`

Cria um plano de assinatura.

**Parâmetros:**
```php
[
    'name' => string,           // Nome do plano (obrigatório)
    'description' => string,    // Descrição (opcional)
    'amount' => int,           // Valor em centavos (obrigatório)
    'currency' => string,      // Moeda (obrigatório)
    'interval' => string,      // Intervalo (obrigatório)
    'interval_count' => int,   // Quantidade de intervalos (padrão: 1)
    'trial_days' => int,       // Dias de trial (opcional)
    'features' => array,       // Lista de features (opcional)
    'limits' => array,         // Limites do plano (opcional)
    'metadata' => array       // Metadados (opcional)
]
```

**Intervalos Válidos:**
- `daily` - Diário
- `weekly` - Semanal
- `monthly` - Mensal
- `quarterly` - Trimestral
- `yearly` - Anual

#### `createSubscription(array $data): array`

Cria uma nova assinatura.

```php
[
    'customer_id' => string,      // ID do cliente (obrigatório)
    'plan_id' => string,          // ID do plano (obrigatório)
    'payment_method' => string,   // Método de pagamento (obrigatório)
    'card_token' => string,       // Token do cartão (se aplicável)
    'trial_end' => string,        // Data fim do trial (opcional)
    'coupon_code' => string,      // Código do cupom (opcional)
    'metadata' => array          // Metadados (opcional)
]
```

#### `getSubscription(string $subscriptionId): array`

Busca uma assinatura pelo ID.

#### `pauseSubscription(string $subscriptionId, array $options = []): bool`

Pausa uma assinatura.

```php
[
    'reason' => string,        // Motivo da pausa
    'resume_date' => string    // Data para reativar (YYYY-MM-DD)
]
```

#### `resumeSubscription(string $subscriptionId): bool`

Reativa uma assinatura pausada.

#### `cancelSubscription(string $subscriptionId, array $options = []): bool`

Cancela uma assinatura.

```php
[
    'reason' => string,              // Motivo do cancelamento
    'cancel_at_period_end' => bool   // Cancelar no fim do período atual
]
```

#### `changeSubscriptionPlan(string $subscriptionId, array $data): array`

Altera o plano de uma assinatura.

```php
[
    'new_plan_id' => string,         // ID do novo plano
    'prorate' => bool,               // Calcular valor proporcional
    'billing_cycle_anchor' => string // 'now' ou 'unchanged'
]
```

#### `getMRRReport(array $filters = []): array`

Obtém relatório de MRR (Monthly Recurring Revenue).

#### `getChurnAnalysis(array $filters = []): array`

Obtém análise de churn.

---

## Analytics Module

### Métodos Principais

#### `getSalesMetrics(array $filters = []): array`

Obtém métricas de vendas.

**Filtros:**
```php
[
    'period' => string,        // Período (30_days, 90_days, etc.)
    'date_from' => string,     // Data início (YYYY-MM-DD)
    'date_to' => string,       // Data fim (YYYY-MM-DD)
    'group_by' => string       // Agrupamento (day, week, month)
]
```

#### `getFunnelAnalysis(array $filters = []): array`

Obtém análise do funil de conversão.

#### `getTopProducts(array $filters = []): array`

Obtém produtos mais vendidos.

#### `getCustomerSegmentAnalysis(array $filters = []): array`

Obtém análise de segmentação de clientes.

#### `recordUsage(string $entityId, array $data): bool`

Registra uso/evento para analytics.

```php
[
    'metric' => string,      // Nome da métrica
    'value' => mixed,        // Valor da métrica
    'timestamp' => int       // Timestamp (opcional)
]
```

#### `recordEvent(string $entityId, array $data): bool`

Registra um evento customizado.

```php
[
    'event_type' => string,   // Tipo do evento
    'event_name' => string,   // Nome do evento
    'properties' => array,    // Propriedades (opcional)
    'timestamp' => int        // Timestamp (opcional)
]
```

---

## Notifications Module

### Métodos Principais

#### `sendEmail(array $data): bool`

Envia um email.

**Parâmetros:**
```php
[
    'to' => string,           // Email destinatário (obrigatório)
    'subject' => string,      // Assunto (obrigatório se não usar template)
    'template' => string,     // ID do template (opcional)
    'variables' => array,     // Variáveis do template (opcional)
    'body' => string,         // Corpo do email (se não usar template)
    'from' => string,         // Email remetente (opcional)
    'reply_to' => string,     // Email para resposta (opcional)
    'attachments' => array    // Anexos (opcional)
]
```

#### `sendSMS(array $data): bool`

Envia um SMS.

```php
[
    'to' => string,          // Número do telefone (obrigatório)
    'message' => string,     // Mensagem (obrigatório)
    'template' => string,    // ID do template (opcional)
    'variables' => array     // Variáveis do template (opcional)
]
```

#### `sendPushNotification(array $data): bool`

Envia uma notificação push.

```php
[
    'to' => string,          // ID do dispositivo ou usuário
    'title' => string,       // Título
    'body' => string,        // Corpo da notificação
    'action_url' => string,  // URL da ação (opcional)
    'icon' => string,        // Ícone (opcional)
    'badge' => int          // Badge count (opcional)
]
```

#### `sendWebhook(array $data): bool`

Envia um webhook.

```php
[
    'url' => string,         // URL do webhook (obrigatório)
    'payload' => array,      // Dados a enviar (obrigatório)
    'headers' => array,      // Headers customizados (opcional)
    'retry_attempts' => int  // Tentativas de retry (opcional)
]
```

#### `configureDefaults(array $config): bool`

Configura padrões para notificações.

```php
[
    'email_from' => string,      // Email padrão de envio
    'email_from_name' => string, // Nome padrão do remetente
    'sms_sender' => string,      // Remetente padrão SMS
    'webhook_timeout' => int     // Timeout para webhooks
]
```

---

## Shipping Module

### Métodos Principais

#### `calculateShipping(string $orderId, array $destination): array`

Calcula opções de frete para um pedido.

**Parâmetros:**
```php
[
    'postal_code' => string,  // CEP de destino (obrigatório)
    'city' => string,         // Cidade (opcional)
    'state' => string         // Estado (opcional)
]
```

**Resposta:**
```php
[
    [
        'name' => string,         // Nome da opção
        'code' => string,         // Código da opção
        'price' => int,           // Preço em centavos
        'delivery_time' => int,   // Prazo em dias
        'carrier' => string       // Transportadora
    ]
]
```

#### `scheduleShipping(string $orderId, array $options): array`

Agenda o envio de um pedido.

```php
[
    'method' => string,            // Código do método de envio
    'estimated_delivery' => string, // Data estimada (YYYY-MM-DD)
    'notes' => string             // Observações (opcional)
]
```

#### `trackShipment(string $trackingCode): array`

Rastreia um envio.

#### `getShippingMethods(): array`

Lista métodos de envio disponíveis.

#### `configureCarriers(array $carriers): bool`

Configura transportadoras.

```php
[
    'correios' => [
        'enabled' => bool,
        'credentials' => array
    ],
    'jadlog' => [
        'enabled' => bool,
        'credentials' => array
    ]
]
```

---

## Webhooks Module

### Métodos Principais

#### `create(array $data): array`

Cria um webhook.

**Parâmetros:**
```php
[
    'url' => string,         // URL do webhook (obrigatório)
    'events' => array,       // Eventos a escutar (obrigatório)
    'secret' => string,      // Segredo para validação (opcional)
    'enabled' => bool,       // Ativo (opcional, padrão: true)
    'description' => string  // Descrição (opcional)
]
```

**Eventos Disponíveis:**
- `order.created` - Pedido criado
- `order.updated` - Pedido atualizado
- `payment.approved` - Pagamento aprovado
- `payment.declined` - Pagamento recusado
- `subscription.created` - Assinatura criada
- `subscription.cancelled` - Assinatura cancelada

#### `list(): array`

Lista todos os webhooks.

#### `get(string $webhookId): array`

Busca um webhook pelo ID.

#### `update(string $webhookId, array $data): array`

Atualiza um webhook.

#### `delete(string $webhookId): bool`

Remove um webhook.

#### `processIncoming(array $payload, string $signature): array`

Processa um webhook recebido.

#### `validateSignature(array $payload, string $signature, string $secret): bool`

Valida a assinatura de um webhook.

---

## Tratamento de Erros

### Exceptions Disponíveis

```php
use Clubify\Checkout\Exceptions\{
    ClubifyException,           // Exception base
    ValidationException,        // Erro de validação
    PaymentDeclinedException,   // Pagamento recusado
    PaymentMethodException,     // Método de pagamento inválido
    CustomerNotFoundException,  // Cliente não encontrado
    OrderNotFoundException,     // Pedido não encontrado
    SubscriptionException,      // Erro de assinatura
    PlanNotFoundException,      // Plano não encontrado
    GatewayException,          // Erro no gateway
    NetworkException,          // Erro de rede
    RateLimitException,        // Limite de taxa excedido
    UnauthorizedException,     // Não autorizado
    ServerException            // Erro interno do servidor
};
```

### Exemplo de Tratamento

```php
try {
    $payment = $sdk->payments()->processPayment($data);
} catch (PaymentDeclinedException $e) {
    // Pagamento recusado
    $reason = $e->getDeclineReason();
    $code = $e->getDeclineCode();
} catch (ValidationException $e) {
    // Dados inválidos
    $errors = $e->getValidationErrors();
} catch (GatewayException $e) {
    // Problema no gateway
    $gatewayCode = $e->getGatewayCode();
    $gatewayMessage = $e->getGatewayMessage();
} catch (ClubifyException $e) {
    // Erro geral da Clubify
    $errorCode = $e->getErrorCode();
    $requestId = $e->getRequestId();
} catch (Exception $e) {
    // Erro não esperado
    $message = $e->getMessage();
}
```

---

## Rate Limiting

O SDK implementa rate limiting automático com:

- **Limite padrão**: 1000 requests/minuto
- **Burst limit**: 100 requests/10 segundos
- **Retry automático**: Até 3 tentativas com backoff exponencial
- **Headers de resposta**:
  - `X-RateLimit-Limit` - Limite total
  - `X-RateLimit-Remaining` - Requests restantes
  - `X-RateLimit-Reset` - Timestamp do reset

---

## Debugging e Logging

### Habilitar Debug Mode

```php
$sdk = new ClubifyCheckoutSDK([
    'debug' => true,
    // ... outras configurações
]);
```

### Configurar Logger Customizado

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('clubify');
$logger->pushHandler(new StreamHandler('logs/clubify.log', Logger::DEBUG));

$sdk->setLogger($logger);
```

### Logs Disponíveis

- **Request/Response**: Todas as chamadas HTTP
- **Errors**: Erros e exceptions
- **Retries**: Tentativas de retry
- **Rate Limiting**: Informações de rate limiting

---

*Esta documentação é atualizada continuamente. Para a versão mais recente, consulte o [repositório oficial](https://github.com/clubifyhq/checkout-sdk-php).*