# Guia de Uso - Módulo Customers

## Visão Geral
O módulo Customers gerencia todas as informações e operações relacionadas aos clientes, incluindo perfis, autenticação e segmentação.

## Inicialização

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'seu-tenant-id',
    'api_key' => 'sua-api-key',
    'secret_key' => 'sua-secret-key'
]);

$customersModule = $sdk->customers();
```

## Funcionalidades Principais

### 1. Criar Cliente

```php
$customerData = [
    'name' => 'João Silva',
    'email' => 'joao@email.com',
    'document' => '12345678901', // CPF
    'phone' => '+5511999999999',
    'birth_date' => '1990-05-15',
    'address' => [
        'street' => 'Rua das Flores, 123',
        'complement' => 'Apto 45',
        'neighborhood' => 'Centro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'postal_code' => '01234-567',
        'country' => 'BR'
    ],
    'preferences' => [
        'language' => 'pt-BR',
        'currency' => 'BRL',
        'marketing_emails' => true,
        'sms_notifications' => false
    ]
];

$customer = $customersModule->createCustomer($customerData);
```

### 2. Buscar Clientes

```php
// Por ID
$customer = $customersModule->getCustomer('cust_123');

// Por email
$customer = $customersModule->getCustomerByEmail('joao@email.com');

// Por documento
$customer = $customersModule->getCustomerByDocument('12345678901');

// Listar com filtros
$customers = $customersModule->listCustomers([
    'status' => 'active',
    'created_after' => '2024-01-01',
    'segment' => 'premium',
    'limit' => 100,
    'offset' => 0
]);
```

### 3. Atualizar Cliente

```php
// Atualização completa
$updatedCustomer = $customersModule->updateCustomer('cust_123', [
    'name' => 'João Silva Santos',
    'phone' => '+5511888888888',
    'address' => [
        'street' => 'Rua Nova, 456',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
        'postal_code' => '20000-000'
    ]
]);

// Atualização parcial
$customersModule->updateCustomerField('cust_123', 'email', 'novo@email.com');
```

### 4. Autenticação e Sessões

```php
// Autenticar cliente
$auth = $customersModule->authenticateCustomer([
    'email' => 'joao@email.com',
    'password' => 'senha123'
]);

if ($auth['success']) {
    $token = $auth['token'];
    $customer = $auth['customer'];
}

// Validar token
$validation = $customersModule->validateToken($token);

// Logout
$customersModule->revokeToken($token);
```

### 5. Redefinir Senha

```php
// Solicitar redefinição
$resetRequest = $customersModule->requestPasswordReset('joao@email.com');

// Confirmar nova senha
$passwordReset = $customersModule->resetPassword([
    'token' => $resetRequest['reset_token'],
    'new_password' => 'novaSenha123'
]);
```

## Segmentação de Clientes

### 1. Criar Segmentos

```php
$segment = $customersModule->createSegment([
    'name' => 'VIP Customers',
    'description' => 'Clientes com alto valor',
    'criteria' => [
        'total_spent' => ['gte' => 100000], // R$ 1.000+
        'order_count' => ['gte' => 5],
        'last_purchase' => ['gte' => '30_days']
    ]
]);
```

### 2. Adicionar Cliente ao Segmento

```php
$customersModule->addCustomerToSegment('cust_123', 'segment_vip');

// Remover do segmento
$customersModule->removeCustomerFromSegment('cust_123', 'segment_vip');
```

### 3. Listar Clientes por Segmento

```php
$vipCustomers = $customersModule->getCustomersBySegment('segment_vip');
```

## Histórico e Atividades

### 1. Histórico de Compras

```php
$purchaseHistory = $customersModule->getCustomerOrders('cust_123', [
    'status' => 'completed',
    'date_from' => '2024-01-01',
    'limit' => 50
]);
```

### 2. Adicionar Atividade

```php
$customersModule->addCustomerActivity('cust_123', [
    'type' => 'support_contact',
    'description' => 'Cliente entrou em contato via chat',
    'metadata' => [
        'channel' => 'chat',
        'agent_id' => 'agent_456',
        'satisfaction' => 5
    ]
]);
```

### 3. Timeline do Cliente

```php
$timeline = $customersModule->getCustomerTimeline('cust_123', [
    'include' => ['orders', 'payments', 'support', 'marketing'],
    'limit' => 100
]);
```

## Métricas e Analytics

### 1. Customer Lifetime Value (CLV)

```php
$clv = $customersModule->calculateCustomerCLV('cust_123');

echo "CLV: R$ " . number_format($clv['lifetime_value'] / 100, 2);
echo "Valor médio do pedido: R$ " . number_format($clv['avg_order_value'] / 100, 2);
echo "Frequência de compra: " . $clv['purchase_frequency'] . " dias";
```

### 2. Relatórios de Segmento

```php
$segmentReport = $customersModule->getSegmentAnalytics('segment_vip', [
    'metrics' => ['total_revenue', 'avg_order_value', 'retention_rate'],
    'period' => '90_days'
]);
```

### 3. Análise de Churn

```php
$churnAnalysis = $customersModule->getChurnAnalysis([
    'period' => '30_days',
    'segment' => 'all'
]);

$riskCustomers = $customersModule->getChurnRiskCustomers([
    'risk_level' => 'high',
    'limit' => 50
]);
```

## Preferências e Consentimentos

### 1. Gerenciar Preferências

```php
$customersModule->updateCustomerPreferences('cust_123', [
    'marketing_emails' => false,
    'sms_notifications' => true,
    'push_notifications' => true,
    'data_sharing' => false
]);
```

### 2. Consentimentos LGPD/GDPR

```php
// Registrar consentimento
$customersModule->recordConsent('cust_123', [
    'type' => 'marketing',
    'granted' => true,
    'source' => 'checkout_form',
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// Revogar consentimento
$customersModule->revokeConsent('cust_123', 'marketing');

// Histórico de consentimentos
$consentHistory = $customersModule->getConsentHistory('cust_123');
```

## Comunicação

### 1. Enviar Email

```php
$customersModule->sendEmail('cust_123', [
    'template' => 'welcome_email',
    'subject' => 'Bem-vindo à nossa loja!',
    'variables' => [
        'customer_name' => 'João',
        'discount_code' => 'WELCOME10'
    ]
]);
```

### 2. Enviar SMS

```php
$customersModule->sendSMS('cust_123', [
    'message' => 'Seu pedido #123 foi enviado!',
    'template' => 'order_shipped'
]);
```

### 3. Notificações Push

```php
$customersModule->sendPushNotification('cust_123', [
    'title' => 'Oferta especial!',
    'body' => 'Produtos com 30% de desconto',
    'action_url' => 'https://loja.com/ofertas'
]);
```

## Tratamento de Erros

```php
try {
    $customer = $customersModule->createCustomer($customerData);
} catch (\Clubify\Checkout\Exceptions\DuplicateCustomerException $e) {
    // Cliente já existe
    echo "Cliente já cadastrado: " . $e->getExistingCustomerId();
} catch (\Clubify\Checkout\Exceptions\ValidationException $e) {
    // Dados inválidos
    echo "Dados inválidos: " . $e->getValidationErrors();
} catch (\Exception $e) {
    // Erro geral
    echo "Erro inesperado: " . $e->getMessage();
}
```

## Webhooks

```php
// Configurar webhooks para eventos de cliente
$customersModule->configureWebhook([
    'url' => 'https://seusite.com/webhook/customers',
    'events' => [
        'customer.created',
        'customer.updated',
        'customer.segment_changed',
        'customer.churn_risk'
    ]
]);
```

## Exemplos Avançados

### Cliente B2B

```php
$b2bCustomer = $customersModule->createCustomer([
    'type' => 'business',
    'company_name' => 'Empresa XYZ Ltda',
    'company_document' => '12345678000190', // CNPJ
    'contact_person' => 'Maria Silva',
    'email' => 'compras@empresa.com',
    'billing_address' => [...],
    'shipping_address' => [...],
    'payment_terms' => 'net_30',
    'credit_limit' => 5000000 // R$ 50.000
]);
```

### Importação em Lote

```php
$customersData = [
    ['name' => 'Cliente 1', 'email' => 'cliente1@email.com'],
    ['name' => 'Cliente 2', 'email' => 'cliente2@email.com'],
    // ... mais clientes
];

$importResult = $customersModule->importCustomers($customersData, [
    'update_existing' => true,
    'send_welcome_email' => false,
    'default_segment' => 'imported'
]);

echo "Importados: " . $importResult['created'];
echo "Atualizados: " . $importResult['updated'];
echo "Erros: " . count($importResult['errors']);
```