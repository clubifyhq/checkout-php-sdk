# Guia de Uso - Módulo Payments

## Visão Geral
O módulo Payments gerencia todo o processamento de pagamentos, suportando múltiplos gateways e métodos de pagamento.

## Inicialização

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'seu-tenant-id',
    'api_key' => 'sua-api-key',
    'secret_key' => 'sua-secret-key'
]);

$paymentsModule = $sdk->payments();
```

## Métodos de Pagamento Suportados

- **Cartão de Crédito** (Visa, Mastercard, Amex, Elo)
- **Cartão de Débito**
- **PIX** (Pagamento instantâneo brasileiro)
- **Boleto Bancário**
- **Carteiras Digitais** (Apple Pay, Google Pay)
- **Transferência Bancária**

## Funcionalidades Principais

### 1. Processar Pagamento com Cartão

```php
$paymentData = [
    'order_id' => 'order_123',
    'amount' => 9999, // R$ 99,99 em centavos
    'currency' => 'BRL',
    'payment_method' => 'credit_card',
    'card_data' => [
        'number' => '4111111111111111',
        'expiry_month' => '12',
        'expiry_year' => '2025',
        'cvv' => '123',
        'holder_name' => 'João Silva'
    ],
    'customer' => [
        'id' => 'cust_123',
        'email' => 'joao@email.com',
        'document' => '12345678901'
    ]
];

$payment = $paymentsModule->processPayment($paymentData);
```

### 2. Processar PIX

```php
$pixData = [
    'order_id' => 'order_123',
    'amount' => 9999,
    'currency' => 'BRL',
    'payment_method' => 'pix',
    'customer' => [
        'id' => 'cust_123',
        'email' => 'joao@email.com',
        'document' => '12345678901'
    ]
];

$pixPayment = $paymentsModule->processPayment($pixData);

// Retorna QR Code e chave PIX
echo "QR Code: " . $pixPayment['qr_code'];
echo "Chave PIX: " . $pixPayment['pix_key'];
echo "Expira em: " . $pixPayment['expires_at'];
```

### 3. Gerar Boleto

```php
$boletoData = [
    'order_id' => 'order_123',
    'amount' => 9999,
    'currency' => 'BRL',
    'payment_method' => 'boleto',
    'due_date' => '2024-01-15', // Data de vencimento
    'customer' => [
        'id' => 'cust_123',
        'name' => 'João Silva',
        'email' => 'joao@email.com',
        'document' => '12345678901',
        'address' => [
            'street' => 'Rua das Flores, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'postal_code' => '01234-567'
        ]
    ]
];

$boleto = $paymentsModule->processPayment($boletoData);

// Retorna linha digitável e URL do PDF
echo "Linha digitável: " . $boleto['barcode'];
echo "URL do PDF: " . $boleto['pdf_url'];
```

### 4. Consultar Status do Pagamento

```php
// Por payment_id
$payment = $paymentsModule->getPayment('pay_123');

// Por order_id
$orderPayments = $paymentsModule->getPaymentsByOrder('order_123');

// Verificar status
if ($payment['status'] === 'approved') {
    echo "Pagamento aprovado!";
}
```

### 5. Estornos e Reembolsos

```php
// Estorno total
$refund = $paymentsModule->refundPayment('pay_123');

// Estorno parcial
$partialRefund = $paymentsModule->refundPayment('pay_123', [
    'amount' => 5000, // R$ 50,00
    'reason' => 'Produto defeituoso'
]);

// Cancelar pagamento (antes da captura)
$cancellation = $paymentsModule->cancelPayment('pay_123');
```

## Status do Pagamento

| Status | Descrição |
|--------|-----------|
| `pending` | Aguardando processamento |
| `processing` | Em processamento |
| `approved` | Aprovado |
| `declined` | Recusado |
| `cancelled` | Cancelado |
| `refunded` | Reembolsado |
| `partial_refunded` | Parcialmente reembolsado |
| `expired` | Expirado (PIX/Boleto) |

## Configuração de Gateways

### Configurar Gateway Principal

```php
$paymentsModule->configureGateway([
    'provider' => 'stripe',
    'credentials' => [
        'public_key' => 'pk_test_...',
        'secret_key' => 'sk_test_...'
    ],
    'webhook_secret' => 'whsec_...'
]);
```

### Gateway de Fallback

```php
$paymentsModule->configureFallbackGateway([
    'provider' => 'pagarme',
    'credentials' => [
        'api_key' => 'ak_test_...',
        'encryption_key' => 'ek_test_...'
    ]
]);
```

## Webhook de Pagamentos

```php
// Configurar webhook
$paymentsModule->configureWebhook([
    'url' => 'https://seusite.com/webhook/payments',
    'events' => [
        'payment.approved',
        'payment.declined',
        'payment.refunded',
        'pix.paid',
        'boleto.paid'
    ]
]);

// Processar webhook recebido
$webhookData = $paymentsModule->processWebhook($_POST, $_SERVER['HTTP_SIGNATURE']);
```

## Tratamento de Erros

```php
try {
    $payment = $paymentsModule->processPayment($paymentData);
} catch (\Clubify\Checkout\Exceptions\PaymentDeclinedException $e) {
    // Pagamento recusado
    echo "Pagamento recusado: " . $e->getDeclineReason();
} catch (\Clubify\Checkout\Exceptions\GatewayException $e) {
    // Erro no gateway
    echo "Erro no gateway: " . $e->getMessage();
} catch (\Clubify\Checkout\Exceptions\ValidationException $e) {
    // Dados inválidos
    echo "Dados inválidos: " . $e->getValidationErrors();
}
```

## Segurança e Compliance

### Tokenização de Cartão

```php
// Tokenizar cartão para uso futuro
$token = $paymentsModule->tokenizeCard([
    'number' => '4111111111111111',
    'expiry_month' => '12',
    'expiry_year' => '2025',
    'cvv' => '123',
    'holder_name' => 'João Silva'
]);

// Usar token em pagamento
$paymentData = [
    'order_id' => 'order_123',
    'amount' => 9999,
    'payment_method' => 'credit_card',
    'card_token' => $token['token']
];
```

### 3D Secure

```php
$paymentData = [
    'order_id' => 'order_123',
    'amount' => 9999,
    'payment_method' => 'credit_card',
    'card_data' => [...],
    'security' => [
        'require_3ds' => true,
        'return_url' => 'https://seusite.com/payment/callback'
    ]
];

$payment = $paymentsModule->processPayment($paymentData);

// Se 3DS for necessário
if ($payment['status'] === 'requires_action') {
    // Redirecionar para URL de autenticação
    header('Location: ' . $payment['authentication_url']);
}
```

## Relatórios e Analytics

```php
// Relatório de transações
$report = $paymentsModule->getTransactionReport([
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'status' => 'approved'
]);

// Taxa de aprovação
$metrics = $paymentsModule->getApprovalMetrics([
    'period' => '30_days',
    'group_by' => 'gateway'
]);

// Chargeback e disputas
$chargebacks = $paymentsModule->getChargebacks([
    'status' => 'open'
]);
```