# 💳 Payments Module - Documentação Completa

## Visão Geral

O **Payments Module** é responsável pelo processamento seguro de pagamentos, gestão de múltiplos gateways, tokenização de cartões e conformidade com PCI-DSS. O módulo oferece uma arquitetura robusta e escalável para operações financeiras críticas.

### 🎯 Funcionalidades Principais

- **Multi-Gateway Processing**: Suporte a múltiplos gateways com failover automático
- **Tokenização Segura**: Tokenização PCI-DSS compliant para cartões de crédito
- **Gestão de Cartões**: Salvamento e gerenciamento seguro de métodos de pagamento
- **Processamento Avançado**: Autorização, captura, estorno e cancelamento
- **Webhook Management**: Processamento de notificações de gateways
- **Relatórios e Analytics**: Estatísticas detalhadas de transações

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** com arquitetura extensível para novos gateways:

```
PaymentsModule
├── Services/
│   ├── PaymentService        # Processamento de pagamentos
│   ├── CardService          # Gestão de cartões
│   ├── TokenizationService  # Tokenização segura
│   └── GatewayService       # Gestão de gateways
├── Gateways/
│   ├── StripeGateway        # Integração Stripe
│   ├── PagarMeGateway       # Integração Pagar.me
│   └── GatewayInterface     # Interface padrão
├── Contracts/
│   ├── PaymentRepositoryInterface
│   └── CardRepositoryInterface
└── DTOs/
    ├── PaymentData          # DTO de pagamento
    ├── CardData             # DTO de cartão
    └── TransactionData      # DTO de transação
```

## 📚 API Reference

### PaymentsModule

#### Métodos de Processamento

##### `processPayment(array $paymentData): array`

Processa um pagamento através do gateway configurado.

**Parâmetros:**
```php
$paymentData = [
    'amount' => 9900,                           // Required (em centavos)
    'currency' => 'BRL',                        // Required
    'method' => 'credit_card',                  // Required (credit_card/pix/boleto)
    'customer_id' => 'cust_123',               // Required
    'order_id' => 'order_456',                 // Required
    'description' => 'Curso de PHP Avançado',  // Optional
    'metadata' => ['course_id' => 'php_001'],  // Optional

    // Para cartão de crédito
    'card' => [
        'token' => 'card_token_789',           // Required se método = credit_card
        'installments' => 6,                   // Optional
        'capture' => true,                     // Optional (default: true)
        'save_card' => false                   // Optional
    ],

    // Para PIX
    'pix' => [
        'expires_in' => 3600                   // Optional (segundos)
    ],

    // Para boleto
    'boleto' => [
        'expires_at' => '2025-01-20',         // Optional
        'instructions' => 'Não aceitar após vencimento'  // Optional
    ]
];
```

**Retorno:**
```php
[
    'id' => 'pay_123456',
    'status' => 'approved',                    // pending/approved/declined/canceled
    'amount' => 9900,
    'currency' => 'BRL',
    'method' => 'credit_card',
    'gateway' => 'stripe',
    'gateway_transaction_id' => 'ch_xyz789',
    'customer_id' => 'cust_123',
    'order_id' => 'order_456',
    'installments' => 6,
    'installment_amount' => 1650,
    'fees' => [
        'gateway_fee' => 290,                  // Taxa do gateway
        'platform_fee' => 99                   // Taxa da plataforma
    ],
    'card' => [
        'last_four' => '4242',
        'brand' => 'visa',
        'country' => 'BR'
    ],
    'pix' => [
        'qr_code' => 'base64_encoded_qr',
        'qr_code_url' => 'https://...',
        'expires_at' => '2025-01-16T12:00:00Z'
    ],
    'created_at' => '2025-01-16T10:00:00Z',
    'updated_at' => '2025-01-16T10:00:05Z'
]
```

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

// Pagamento com cartão de crédito
$creditCardPayment = $sdk->payments()->processPayment([
    'amount' => 29900, // R$ 299,00
    'currency' => 'BRL',
    'method' => 'credit_card',
    'customer_id' => 'cust_123',
    'order_id' => 'order_456',
    'description' => 'Curso de React Avançado',
    'card' => [
        'token' => 'card_token_secure_123',
        'installments' => 3,
        'save_card' => true
    ]
]);

// Pagamento PIX
$pixPayment = $sdk->payments()->processPayment([
    'amount' => 29900,
    'currency' => 'BRL',
    'method' => 'pix',
    'customer_id' => 'cust_123',
    'order_id' => 'order_789',
    'pix' => [
        'expires_in' => 1800 // 30 minutos
    ]
]);

echo "PIX QR Code: " . $pixPayment['pix']['qr_code_url'];
```

##### `refundPayment(string $paymentId, ?float $amount = null, string $reason = ''): array`

Estorna um pagamento total ou parcialmente.

##### `capturePayment(string $paymentId, ?float $amount = null): array`

Captura um pagamento previamente autorizado.

##### `cancelPayment(string $paymentId, string $reason = ''): array`

Cancela um pagamento autorizado.

**Exemplo de Uso:**
```php
// Estorno total
$fullRefund = $sdk->payments()->refundPayment('pay_123456', null, 'Cancelamento solicitado pelo cliente');

// Estorno parcial
$partialRefund = $sdk->payments()->refundPayment('pay_123456', 9900, 'Produto não entregue');

// Captura de pagamento autorizado
$capture = $sdk->payments()->capturePayment('pay_123456');

// Cancelamento
$cancellation = $sdk->payments()->cancelPayment('pay_123456', 'Fraude detectada');
```

#### Métodos de Tokenização e Cartões

##### `tokenizeCard(array $cardData): array`

Tokeniza um cartão de crédito de forma segura.

**Parâmetros:**
```php
$cardData = [
    'number' => '4242424242424242',            // Required
    'exp_month' => '12',                       // Required
    'exp_year' => '2025',                      // Required
    'cvv' => '123',                           // Required
    'holder_name' => 'João Silva',            // Required
    'holder_document' => '123.456.789-00',    // Optional
    'billing_address' => [                     // Optional
        'street' => 'Rua das Flores, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567',
        'country' => 'BR'
    ]
];
```

##### `saveCard(string $customerId, array $cardData): array`

Salva um cartão para uso futuro.

##### `getCustomerCards(string $customerId): array`

Lista cartões salvos de um cliente.

##### `removeCard(string $cardId): bool`

Remove um cartão salvo.

**Exemplo de Uso:**
```php
// Tokenizar cartão
$token = $sdk->payments()->tokenizeCard([
    'number' => '4242424242424242',
    'exp_month' => '12',
    'exp_year' => '2025',
    'cvv' => '123',
    'holder_name' => 'Maria Santos',
    'holder_document' => '987.654.321-00'
]);

// Salvar cartão para cliente
$savedCard = $sdk->payments()->saveCard('cust_123', [
    'token' => $token['token'],
    'is_default' => true,
    'nickname' => 'Cartão Principal'
]);

// Listar cartões do cliente
$customerCards = $sdk->payments()->getCustomerCards('cust_123');

foreach ($customerCards as $card) {
    echo "Cartão: **** **** **** {$card['last_four']} ({$card['brand']})\n";
}

// Remover cartão
$removed = $sdk->payments()->removeCard($savedCard['id']);
```

#### Métodos de Gateway

##### `getAvailableGateways(): array`

Obtém lista de gateways configurados e disponíveis.

##### `getDefaultGateway(): string`

Obtém o gateway padrão configurado.

##### `testGateway(string $gatewayName): array`

Testa conectividade com um gateway específico.

##### `getSupportedPaymentMethods(): array`

Obtém métodos de pagamento suportados por todos os gateways.

**Exemplo de Uso:**
```php
// Listar gateways disponíveis
$gateways = $sdk->payments()->getAvailableGateways();

foreach ($gateways as $gateway) {
    echo "Gateway: {$gateway['name']} - Status: {$gateway['status']}\n";
}

// Gateway padrão
$defaultGateway = $sdk->payments()->getDefaultGateway();
echo "Gateway padrão: {$defaultGateway}\n";

// Testar gateway
$stripeTest = $sdk->payments()->testGateway('stripe');
echo "Stripe Status: " . ($stripeTest['success'] ? 'OK' : 'FALHA') . "\n";

// Métodos suportados
$methods = $sdk->payments()->getSupportedPaymentMethods();
// ['credit_card', 'pix', 'boleto', 'debit_card']
```

#### Métodos de Validação

##### `validateCard(array $cardData): array`

Valida dados de cartão de crédito.

##### `detectCardBrand(string $cardNumber): string`

Detecta a bandeira do cartão pelo número.

**Exemplo de Uso:**
```php
// Validar cartão
$validation = $sdk->payments()->validateCard([
    'number' => '4242424242424242',
    'exp_month' => '12',
    'exp_year' => '2025',
    'cvv' => '123'
]);

if ($validation['is_valid']) {
    echo "Cartão válido!\n";
} else {
    foreach ($validation['errors'] as $error) {
        echo "Erro: {$error}\n";
    }
}

// Detectar bandeira
$brand = $sdk->payments()->detectCardBrand('4242424242424242');
echo "Bandeira: {$brand}\n"; // visa
```

#### Services Disponíveis

##### `payments(): PaymentService`

Retorna o serviço de processamento de pagamentos.

**Métodos Disponíveis:**
- `process(array $data): array` - Processar pagamento
- `find(string $paymentId): ?array` - Buscar pagamento
- `refund(string $paymentId, ?float $amount, string $reason): array` - Estornar
- `capture(string $paymentId, ?float $amount): array` - Capturar
- `cancel(string $paymentId, string $reason): array` - Cancelar
- `list(array $filters): array` - Listar pagamentos
- `getStatistics(array $filters): array` - Estatísticas
- `getTransactionReport(array $filters): array` - Relatório

##### `cards(): CardService`

Retorna o serviço de gestão de cartões.

**Métodos Disponíveis:**
- `save(string $customerId, array $cardData): array` - Salvar cartão
- `findByCustomer(string $customerId): array` - Cartões do cliente
- `delete(string $cardId): bool` - Excluir cartão
- `validate(array $cardData): array` - Validar cartão
- `detectBrand(string $number): string` - Detectar bandeira
- `mask(string $number): string` - Mascarar número

##### `tokenization(): TokenizationService`

Retorna o serviço de tokenização.

**Métodos Disponíveis:**
- `tokenizeCard(array $cardData): array` - Tokenizar cartão
- `detokenize(string $token): array` - Desfazer tokenização
- `validateToken(string $token): bool` - Validar token
- `expireToken(string $token): bool` - Expirar token

##### `gateways(): GatewayService`

Retorna o serviço de gestão de gateways.

**Métodos Disponíveis:**
- `getAvailable(): array` - Gateways disponíveis
- `getDefault(): string` - Gateway padrão
- `test(string $gatewayName): array` - Testar gateway
- `getSupportedMethods(): array` - Métodos suportados
- `handleWebhook(string $gateway, array $payload, array $headers): array` - Webhook
- `getConfig(string $gatewayName): array` - Configuração
- `updateConfig(string $gatewayName, array $config): bool` - Atualizar config

## 💡 Exemplos Práticos

### Checkout Completo com Múltiplos Métodos

```php
// Configuração de pagamento flexível
$paymentMethods = [
    'credit_card' => [
        'installments' => [1, 2, 3, 6, 12],
        'brands' => ['visa', 'mastercard', 'elo', 'hipercard']
    ],
    'pix' => [
        'discount_percentage' => 5,
        'expires_in' => 1800
    ],
    'boleto' => [
        'discount_percentage' => 3,
        'expires_in_days' => 3
    ]
];

$orderId = 'order_' . uniqid();
$customerId = 'cust_123';
$amount = 19900; // R$ 199,00

// Processamento baseado na escolha do usuário
$selectedMethod = 'credit_card'; // Vem do frontend

switch ($selectedMethod) {
    case 'credit_card':
        $payment = $sdk->payments()->processPayment([
            'amount' => $amount,
            'currency' => 'BRL',
            'method' => 'credit_card',
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'card' => [
                'token' => $cardToken,
                'installments' => $installments,
                'save_card' => $saveCard
            ]
        ]);
        break;

    case 'pix':
        $discountedAmount = $amount - ($amount * 0.05); // 5% desconto
        $payment = $sdk->payments()->processPayment([
            'amount' => $discountedAmount,
            'currency' => 'BRL',
            'method' => 'pix',
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'pix' => [
                'expires_in' => 1800
            ]
        ]);
        break;

    case 'boleto':
        $discountedAmount = $amount - ($amount * 0.03); // 3% desconto
        $payment = $sdk->payments()->processPayment([
            'amount' => $discountedAmount,
            'currency' => 'BRL',
            'method' => 'boleto',
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'boleto' => [
                'expires_at' => date('Y-m-d', strtotime('+3 days'))
            ]
        ]);
        break;
}
```

### Sistema de Assinaturas com Cartão Salvo

```php
// Configurar assinatura com cartão salvo
$subscription = [
    'customer_id' => 'cust_premium_456',
    'plan' => 'premium_monthly',
    'amount' => 4900, // R$ 49,00/mês
    'billing_cycle' => 'monthly',
    'trial_days' => 7
];

// Verificar se cliente tem cartão salvo
$customerCards = $sdk->payments()->getCustomerCards($subscription['customer_id']);

if (empty($customerCards)) {
    // Primeira cobrança - salvar cartão
    $payment = $sdk->payments()->processPayment([
        'amount' => $subscription['amount'],
        'currency' => 'BRL',
        'method' => 'credit_card',
        'customer_id' => $subscription['customer_id'],
        'order_id' => 'subscription_' . uniqid(),
        'description' => 'Plano Premium - Primeira cobrança',
        'card' => [
            'token' => $newCardToken,
            'save_card' => true,
            'is_default' => true
        ],
        'metadata' => [
            'subscription_id' => $subscription['plan'],
            'billing_cycle' => $subscription['billing_cycle']
        ]
    ]);
} else {
    // Cobrança recorrente com cartão salvo
    $defaultCard = array_filter($customerCards, fn($card) => $card['is_default'])[0];

    $payment = $sdk->payments()->processPayment([
        'amount' => $subscription['amount'],
        'currency' => 'BRL',
        'method' => 'credit_card',
        'customer_id' => $subscription['customer_id'],
        'order_id' => 'subscription_' . date('Ym') . '_' . uniqid(),
        'description' => 'Plano Premium - Cobrança mensal',
        'card' => [
            'saved_card_id' => $defaultCard['id']
        ],
        'metadata' => [
            'subscription_id' => $subscription['plan'],
            'billing_cycle' => $subscription['billing_cycle'],
            'recurring' => true
        ]
    ]);
}

if ($payment['status'] === 'approved') {
    echo "Assinatura ativa até: " . date('d/m/Y', strtotime('+1 month')) . "\n";
} else {
    echo "Falha na cobrança: " . $payment['status_reason'] . "\n";
}
```

### Gateway Failover Automático

```php
// Sistema de failover entre gateways
$paymentConfig = [
    'gateways' => [
        'primary' => 'stripe',
        'secondary' => 'pagarme',
        'fallback' => 'mercadopago'
    ],
    'retry_attempts' => 3,
    'retry_delay_seconds' => 2
];

$paymentData = [
    'amount' => 15900,
    'currency' => 'BRL',
    'method' => 'credit_card',
    'customer_id' => 'cust_789',
    'order_id' => 'order_failover_' . uniqid(),
    'card' => ['token' => $cardToken]
];

$attempts = 0;
$maxAttempts = $paymentConfig['retry_attempts'];
$gateways = array_values($paymentConfig['gateways']);

do {
    $currentGateway = $gateways[$attempts] ?? $gateways[0];

    try {
        // Forçar uso de gateway específico
        $gatewayConfig = $sdk->payments()->getGatewayConfig($currentGateway);
        $sdk->payments()->updateGatewayConfig($currentGateway, [
            'priority' => 1,
            'enabled' => true
        ]);

        $payment = $sdk->payments()->processPayment($paymentData);

        if ($payment['status'] === 'approved') {
            echo "Pagamento aprovado via {$currentGateway}\n";
            break;
        }

        $attempts++;
        if ($attempts < $maxAttempts) {
            sleep($paymentConfig['retry_delay_seconds']);
        }

    } catch (\Exception $e) {
        echo "Falha no gateway {$currentGateway}: {$e->getMessage()}\n";
        $attempts++;

        if ($attempts < $maxAttempts) {
            sleep($paymentConfig['retry_delay_seconds']);
        }
    }

} while ($attempts < $maxAttempts);

if ($attempts >= $maxAttempts) {
    echo "Falha em todos os gateways após {$maxAttempts} tentativas\n";
}
```

### Gestão de Webhooks

```php
// Processamento de webhooks de diferentes gateways
class PaymentWebhookHandler
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function handleStripeWebhook($payload, $headers)
    {
        $result = $this->sdk->payments()->handleWebhook('stripe', $payload, $headers);

        switch ($result['event_type']) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSuccess($result['payment']);
                break;

            case 'payment_intent.payment_failed':
                $this->handlePaymentFailure($result['payment']);
                break;

            case 'invoice.payment_succeeded':
                $this->handleSubscriptionPayment($result['payment']);
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionCancellation($result['subscription']);
                break;
        }

        return ['status' => 'processed'];
    }

    public function handlePagarMeWebhook($payload, $headers)
    {
        $result = $this->sdk->payments()->handleWebhook('pagarme', $payload, $headers);

        switch ($result['event_type']) {
            case 'transaction_status_changed':
                if ($result['payment']['status'] === 'paid') {
                    $this->handlePaymentSuccess($result['payment']);
                } elseif ($result['payment']['status'] === 'refused') {
                    $this->handlePaymentFailure($result['payment']);
                }
                break;

            case 'subscription_status_changed':
                $this->handleSubscriptionStatusChange($result['subscription']);
                break;
        }

        return ['status' => 'processed'];
    }

    private function handlePaymentSuccess($payment)
    {
        // Atualizar status do pedido
        // Enviar email de confirmação
        // Liberar acesso ao produto
        echo "Pagamento aprovado: {$payment['id']}\n";
    }

    private function handlePaymentFailure($payment)
    {
        // Marcar pedido como cancelado
        // Notificar cliente sobre falha
        // Tentar gateway alternativo
        echo "Pagamento falhou: {$payment['id']}\n";
    }
}
```

## 🔧 DTOs e Validação

### PaymentData DTO

```php
use ClubifyCheckout\Modules\Payments\DTOs\PaymentData;

$paymentData = new PaymentData([
    'amount' => 9900,
    'currency' => 'BRL',
    'method' => 'credit_card',
    'customer_id' => 'cust_123',
    'order_id' => 'order_456',
    'description' => 'Pagamento do pedido #456',
    'metadata' => [
        'product_type' => 'digital',
        'category' => 'course'
    ],
    'card' => [
        'token' => 'card_token_123',
        'installments' => 6,
        'capture' => true
    ]
]);

// Validação automática
if ($paymentData->isValid()) {
    $payment = $sdk->payments()->processPayment($paymentData->toArray());
} else {
    foreach ($paymentData->getValidationErrors() as $field => $errors) {
        echo "Campo {$field}: " . implode(', ', $errors) . "\n";
    }
}
```

### CardData DTO

```php
use ClubifyCheckout\Modules\Payments\DTOs\CardData;

$cardData = new CardData([
    'number' => '4242424242424242',
    'exp_month' => '12',
    'exp_year' => '2025',
    'cvv' => '123',
    'holder_name' => 'João Silva',
    'holder_document' => '123.456.789-00',
    'billing_address' => [
        'street' => 'Rua Principal, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567',
        'country' => 'BR'
    ]
]);

// Validações automáticas incluem:
// - Algoritmo de Luhn para número do cartão
// - Validação de data de expiração
// - Formato do CVV
// - Validação de CPF/CNPJ
// - Formato do CEP
```

### TransactionData DTO

```php
use ClubifyCheckout\Modules\Payments\DTOs\TransactionData;

$transactionData = new TransactionData([
    'payment_id' => 'pay_123',
    'type' => 'payment', // payment/refund/chargeback
    'status' => 'approved',
    'amount' => 9900,
    'gateway' => 'stripe',
    'gateway_transaction_id' => 'ch_xyz789',
    'processed_at' => '2025-01-16T10:00:00Z',
    'fees' => [
        'gateway_fee' => 290,
        'platform_fee' => 99
    ]
]);
```

## 📊 Relatórios e Analytics

### Estatísticas de Pagamentos

```php
// Relatório de vendas por período
$salesReport = $sdk->payments()->getPaymentStats([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'group_by' => 'day'
]);

foreach ($salesReport['daily_stats'] as $stat) {
    echo "Data: {$stat['date']}\n";
    echo "Volume: R$ " . number_format($stat['amount'] / 100, 2, ',', '.') . "\n";
    echo "Transações: {$stat['count']}\n";
    echo "Taxa de aprovação: {$stat['approval_rate']}%\n\n";
}

// Análise por método de pagamento
$methodStats = $sdk->payments()->getPaymentStats([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'group_by' => 'payment_method'
]);

foreach ($methodStats['method_stats'] as $method => $stats) {
    echo "Método: {$method}\n";
    echo "Participação: {$stats['percentage']}%\n";
    echo "Valor médio: R$ " . number_format($stats['average_amount'] / 100, 2, ',', '.') . "\n\n";
}
```

### Relatório de Transações

```php
// Relatório detalhado de transações
$transactionReport = $sdk->payments()->getTransactionReport([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'status' => 'approved',
    'gateway' => 'stripe',
    'export_format' => 'array' // array/csv/xlsx
]);

echo "Total de transações: {$transactionReport['summary']['total_count']}\n";
echo "Volume total: R$ " . number_format($transactionReport['summary']['total_amount'] / 100, 2, ',', '.') . "\n";
echo "Taxas totais: R$ " . number_format($transactionReport['summary']['total_fees'] / 100, 2, ',', '.') . "\n";

// Detalhamento por gateway
foreach ($transactionReport['gateway_breakdown'] as $gateway => $data) {
    echo "\nGateway: {$gateway}\n";
    echo "Transações: {$data['count']}\n";
    echo "Volume: R$ " . number_format($data['amount'] / 100, 2, ',', '.') . "\n";
    echo "Taxa de aprovação: {$data['approval_rate']}%\n";
}
```

## 🔍 Monitoramento e Logs

### Health Check dos Gateways

```php
// Verificar saúde de todos os gateways
$availableGateways = $sdk->payments()->getAvailableGateways();

foreach ($availableGateways as $gateway) {
    $healthCheck = $sdk->payments()->testGateway($gateway['name']);

    echo "Gateway: {$gateway['name']}\n";
    echo "Status: " . ($healthCheck['success'] ? 'OK' : 'FALHA') . "\n";
    echo "Latência: {$healthCheck['response_time_ms']}ms\n";

    if (!$healthCheck['success']) {
        echo "Erro: {$healthCheck['error']}\n";
    }

    echo "\n";
}
```

### Logs de Segurança

```php
// Os logs de segurança são gerados automaticamente:

/*
[2025-01-16 10:30:00] INFO: Processando pagamento
{
    "amount": 9900,
    "currency": "BRL",
    "method": "credit_card",
    "customer_id": "cust_123",
    "card_last_four": "4242",
    "gateway": "stripe"
}

[2025-01-16 10:30:01] SECURITY: Cartão tokenizado
{
    "token": "card_token_abc123",
    "brand": "visa",
    "last_four": "4242",
    "customer_id": "cust_123",
    "pci_compliant": true
}

[2025-01-16 10:30:02] INFO: Pagamento aprovado
{
    "payment_id": "pay_123456",
    "gateway_transaction_id": "ch_xyz789",
    "status": "approved",
    "amount": 9900,
    "fees": 389
}
*/
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Payments\Exceptions\PaymentException;
use ClubifyCheckout\Modules\Payments\Exceptions\InvalidCardException;
use ClubifyCheckout\Modules\Payments\Exceptions\PaymentDeclinedException;
use ClubifyCheckout\Modules\Payments\Exceptions\GatewayException;
use ClubifyCheckout\Modules\Payments\Exceptions\InsufficientFundsException;

try {
    $payment = $sdk->payments()->processPayment($paymentData);
} catch (PaymentDeclinedException $e) {
    echo "Pagamento recusado: " . $e->getMessage();
    // Sugerir método alternativo
} catch (InvalidCardException $e) {
    echo "Cartão inválido: " . $e->getMessage();
    // Solicitar correção dos dados
} catch (InsufficientFundsException $e) {
    echo "Saldo insuficiente: " . $e->getMessage();
    // Sugerir parcelas ou outro método
} catch (GatewayException $e) {
    echo "Erro no gateway: " . $e->getMessage();
    // Tentar gateway alternativo
} catch (PaymentException $e) {
    echo "Erro no pagamento: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Payments
CLUBIFY_PAYMENTS_DEFAULT_GATEWAY=stripe
CLUBIFY_PAYMENTS_CACHE_TTL=300
CLUBIFY_PAYMENTS_MAX_RETRY_ATTEMPTS=3
CLUBIFY_PAYMENTS_TOKENIZATION_ENABLED=true

# Stripe
STRIPE_API_KEY=sk_live_your_stripe_key
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret
STRIPE_ENVIRONMENT=live

# Pagar.me
PAGARME_API_KEY=ak_live_your_pagarme_key
PAGARME_ENCRYPTION_KEY=ek_live_your_encryption_key
PAGARME_ENVIRONMENT=live
```

### Configuração Avançada

```php
$config = [
    'payments' => [
        'enabled' => true,
        'default_gateway' => 'stripe',
        'fallback_gateway' => 'pagarme',
        'max_retry_attempts' => 3,
        'retry_delay_seconds' => 2,
        'cache_ttl' => 300,

        'gateways' => [
            'stripe' => [
                'enabled' => true,
                'api_key' => env('STRIPE_API_KEY'),
                'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
                'environment' => env('STRIPE_ENVIRONMENT', 'sandbox'),
                'currency' => 'BRL',
                'capture_automatically' => true,
                'save_cards' => true
            ],
            'pagarme' => [
                'enabled' => true,
                'api_key' => env('PAGARME_API_KEY'),
                'encryption_key' => env('PAGARME_ENCRYPTION_KEY'),
                'environment' => env('PAGARME_ENVIRONMENT', 'sandbox'),
                'currency' => 'BRL',
                'capture_automatically' => true
            ]
        ],

        'card' => [
            'tokenization_enabled' => true,
            'save_by_default' => false,
            'mask_numbers' => true,
            'validate_luhn' => true,
            'require_cvv' => true
        ],

        'pix' => [
            'default_expires_in' => 3600,
            'max_expires_in' => 86400
        ],

        'boleto' => [
            'default_expires_in_days' => 3,
            'max_expires_in_days' => 30
        ]
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de qualidade enterprise e conformidade PCI-DSS.**