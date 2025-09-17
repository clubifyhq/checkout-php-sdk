# Guia de Uso - Módulo Orders

## Visão Geral
O módulo Orders gerencia todo o ciclo de vida dos pedidos no sistema Clubify Checkout, desde a criação até a entrega final.

## Inicialização

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'seu-tenant-id',
    'api_key' => 'sua-api-key',
    'secret_key' => 'sua-secret-key'
]);

$ordersModule = $sdk->orders();
```

## Funcionalidades Principais

### 1. Criar Pedido

```php
$orderData = [
    'customer_id' => 'cust_123',
    'items' => [
        [
            'id' => 'produto_1',
            'name' => 'Produto Premium',
            'price' => 9999, // em centavos (R$ 99,99)
            'quantity' => 2
        ]
    ],
    'total' => 19998,
    'currency' => 'BRL',
    'payment_method' => 'credit_card'
];

$order = $ordersModule->createOrder($orderData);
```

### 2. Buscar Pedido

```php
// Por ID
$order = $ordersModule->getOrder('order_123');

// Por customer_id
$customerOrders = $ordersModule->getOrdersByCustomer('cust_123');

// Com filtros
$orders = $ordersModule->listOrders([
    'status' => 'pending',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'limit' => 50
]);
```

### 3. Atualizar Status do Pedido

```php
// Atualizar status
$result = $ordersModule->updateOrderStatus('order_123', 'confirmed');

// Adicionar tracking
$ordersModule->addTrackingInfo('order_123', [
    'tracking_code' => 'TR123456789',
    'carrier' => 'Correios',
    'estimated_delivery' => '2024-01-15'
]);
```

### 4. Gerenciar Histórico

```php
// Obter histórico completo
$history = $ordersModule->getOrderStatusHistory('order_123');

// Adicionar nota ao pedido
$ordersModule->addOrderNote('order_123', 'Cliente solicitou entrega expressa');
```

## Status do Pedido

| Status | Descrição |
|--------|-----------|
| `pending` | Aguardando processamento |
| `confirmed` | Confirmado e aguardando pagamento |
| `processing` | Em processamento |
| `shipped` | Enviado |
| `delivered` | Entregue |
| `cancelled` | Cancelado |
| `refunded` | Reembolsado |

## Tratamento de Erros

```php
try {
    $order = $ordersModule->createOrder($orderData);
} catch (\Clubify\Checkout\Exceptions\OrderCreationException $e) {
    // Erro específico de criação de pedido
    echo "Erro ao criar pedido: " . $e->getMessage();
} catch (\Clubify\Checkout\Exceptions\ValidationException $e) {
    // Erro de validação de dados
    echo "Dados inválidos: " . $e->getValidationErrors();
} catch (\Exception $e) {
    // Erro geral
    echo "Erro inesperado: " . $e->getMessage();
}
```

## Exemplos Avançados

### Pedido com Desconto

```php
$orderData = [
    'customer_id' => 'cust_123',
    'items' => [
        [
            'id' => 'produto_1',
            'name' => 'Produto Premium',
            'price' => 10000,
            'quantity' => 1
        ]
    ],
    'subtotal' => 10000,
    'discount_amount' => 1000, // R$ 10,00 de desconto
    'coupon_code' => 'SAVE10',
    'total' => 9000,
    'currency' => 'BRL'
];

$order = $ordersModule->createOrder($orderData);
```

### Pedido com Endereço de Entrega

```php
$orderData = [
    'customer_id' => 'cust_123',
    'items' => [...],
    'total' => 9999,
    'shipping_address' => [
        'street' => 'Rua das Flores, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01234-567',
        'country' => 'BR'
    ],
    'shipping_amount' => 500 // R$ 5,00 de frete
];

$order = $ordersModule->createOrder($orderData);
```

## Webhooks

Configure webhooks para receber notificações automáticas sobre mudanças de status:

```php
// Configurar webhook para status changes
$ordersModule->configureWebhook([
    'url' => 'https://seusite.com/webhook/orders',
    'events' => ['order.created', 'order.status_changed', 'order.delivered']
]);
```

## Métricas e Analytics

```php
// Relatório de vendas
$metrics = $ordersModule->getOrderMetrics([
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'group_by' => 'day'
]);

// Top produtos
$topProducts = $ordersModule->getTopSellingProducts([
    'period' => '30_days',
    'limit' => 10
]);
```