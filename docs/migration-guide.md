# Guia de Migração - Clubify Checkout SDK PHP

## Visão Geral

Este guia orienta desenvolvedores na migração e atualização do Clubify Checkout SDK PHP, incluindo novos módulos enterprise e mudanças de breaking changes.

## Versões e Compatibilidade

### Versão Atual: v2.0.0 (Sprint 4 Complete)

- **PHP**: 8.1+
- **Laravel**: 10.0+ (opcional)
- **Dependências**: Guzzle 7.0+, Monolog 3.0+

### Matriz de Compatibilidade

| SDK Version | PHP Version | Laravel Version | Status |
|-------------|-------------|-----------------|---------|
| 2.0.x | 8.1+ | 10.0+ | ✅ Atual |
| 1.x.x | 8.0+ | 9.0+ | 🔶 Legacy |
| 0.x.x | 7.4+ | 8.0+ | ❌ Descontinuado |

---

## Migração v1.x → v2.0

### 1. Novos Módulos Enterprise

A versão 2.0 introduz 5 novos módulos enterprise:

#### Módulos Adicionados:
- **Subscriptions** - Gestão de assinaturas recorrentes
- **Analytics** - Analytics avançados e relatórios
- **Notifications** - Sistema unificado de notificações
- **Shipping** - Cálculos e gestão de frete
- **Webhooks** - Gestão robusta de webhooks

### 2. Mudanças na Inicialização

#### Antes (v1.x):
```php
use Clubify\Checkout\ClubifySDK;

$sdk = new ClubifySDK([
    'api_key' => 'sua-api-key',
    'environment' => 'sandbox'
]);
```

#### Depois (v2.0):
```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'seu-tenant-id', // NOVO - obrigatório
    'api_key' => 'sua-api-key',
    'secret_key' => 'sua-secret-key', // NOVO - obrigatório
    'debug' => false // NOVO - opcional
]);
```

#### Ações Necessárias:
1. ✅ Atualizar classe principal: `ClubifySDK` → `ClubifyCheckoutSDK`
2. ✅ Adicionar `tenant_id` e `secret_key` na configuração
3. ✅ Remover `environment`, usar `api_url` específica

### 3. Mudanças nos Módulos Existentes

#### Orders Module

##### Antes (v1.x):
```php
$order = $sdk->createOrder([
    'amount' => 99.99,
    'currency' => 'BRL'
]);
```

##### Depois (v2.0):
```php
$order = $sdk->orders()->createOrder([
    'customer_id' => 'cust_123', // NOVO - obrigatório
    'items' => [                  // NOVO - estrutura de items
        [
            'id' => 'produto_1',
            'name' => 'Produto',
            'price' => 9999,      // Agora em centavos
            'quantity' => 1
        ]
    ],
    'total' => 9999,              // Agora em centavos
    'currency' => 'BRL'
]);
```

#### Payments Module

##### Antes (v1.x):
```php
$payment = $sdk->processPayment([
    'amount' => 99.99,
    'card' => [...]
]);
```

##### Depois (v2.0):
```php
$payment = $sdk->payments()->processPayment([
    'order_id' => 'order_123',    // NOVO - obrigatório
    'amount' => 9999,             // Agora em centavos
    'currency' => 'BRL',          // NOVO - obrigatório
    'payment_method' => 'credit_card', // NOVO - obrigatório
    'card_data' => [...]          // Renomeado de 'card'
]);
```

#### Customers Module

##### Antes (v1.x):
```php
$customer = $sdk->createCustomer([
    'name' => 'João Silva',
    'email' => 'joao@email.com'
]);
```

##### Depois (v2.0):
```php
$customer = $sdk->customers()->createCustomer([
    'name' => 'João Silva',
    'email' => 'joao@email.com',
    'document' => '12345678901',  // NOVO - obrigatório para BR
    'phone' => '+5511999999999',  // NOVO - formato internacional
    'address' => [...]            // NOVO - estrutura expandida
]);
```

### 4. Tratamento de Erros

#### Antes (v1.x):
```php
try {
    $payment = $sdk->processPayment($data);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
```

#### Depois (v2.0):
```php
use Clubify\Checkout\Exceptions\PaymentDeclinedException;
use Clubify\Checkout\Exceptions\ValidationException;

try {
    $payment = $sdk->payments()->processPayment($data);
} catch (PaymentDeclinedException $e) {
    echo "Pagamento recusado: " . $e->getDeclineReason();
} catch (ValidationException $e) {
    echo "Dados inválidos: " . implode(', ', $e->getValidationErrors());
} catch (Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

---

## Implementação dos Novos Módulos

### 1. Subscriptions Module

```php
// Criar plano de assinatura
$plan = $sdk->subscriptions()->createPlan([
    'name' => 'Plano Premium',
    'amount' => 2999, // R$ 29,99
    'currency' => 'BRL',
    'interval' => 'monthly',
    'trial_days' => 14
]);

// Criar assinatura
$subscription = $sdk->subscriptions()->createSubscription([
    'customer_id' => 'cust_123',
    'plan_id' => $plan['id'],
    'payment_method' => 'credit_card'
]);
```

### 2. Analytics Module

```php
// Métricas de vendas
$metrics = $sdk->analytics()->getSalesMetrics([
    'period' => '30_days',
    'group_by' => 'day'
]);

// Análise de funil
$funnel = $sdk->analytics()->getFunnelAnalysis([
    'period' => '30_days'
]);
```

### 3. Notifications Module

```php
// Configurar notificações
$sdk->notifications()->configureDefaults([
    'email_from' => 'noreply@sualoja.com',
    'sms_sender' => 'SuaLoja'
]);

// Enviar email
$sdk->notifications()->sendEmail([
    'to' => 'cliente@email.com',
    'template' => 'order_confirmation',
    'variables' => ['order_id' => '123']
]);
```

### 4. Shipping Module

```php
// Calcular frete
$shipping = $sdk->shipping()->calculateShipping('order_123', [
    'postal_code' => '01234-567'
]);

// Agendar envio
$sdk->shipping()->scheduleShipping('order_123', [
    'method' => 'correios_pac',
    'estimated_delivery' => '2024-01-15'
]);
```

### 5. Webhooks Module

```php
// Configurar webhook
$sdk->webhooks()->create([
    'url' => 'https://seusite.com/webhook',
    'events' => ['order.created', 'payment.approved']
]);

// Processar webhook recebido
$event = $sdk->webhooks()->processIncoming($_POST, $_SERVER['HTTP_SIGNATURE']);
```

---

## Checklist de Migração

### Preparação

- [ ] **Backup**: Criar backup do código atual
- [ ] **Ambiente de teste**: Configurar ambiente de testes
- [ ] **Dependências**: Verificar compatibilidade PHP 8.1+
- [ ] **Credenciais**: Obter `tenant_id` e `secret_key` no painel Clubify

### Atualização do Código

- [ ] **Composer**: Atualizar para `clubify/checkout-sdk:^2.0`
- [ ] **Namespace**: Atualizar imports e classes
- [ ] **Configuração**: Migrar configuração para novo formato
- [ ] **Módulos**: Adaptar chamadas para novos módulos

### Valores Monetários

- [ ] **Conversão**: Converter valores de reais para centavos
- [ ] **Formatação**: Atualizar exibição de valores
- [ ] **Cálculos**: Revisar cálculos monetários

### Novos Campos Obrigatórios

- [ ] **Customer**: Adicionar `document` e `phone`
- [ ] **Orders**: Implementar estrutura de `items`
- [ ] **Payments**: Adicionar `order_id` e `currency`

### Tratamento de Erros

- [ ] **Exceptions**: Implementar exceptions específicas
- [ ] **Logging**: Configurar logs detalhados
- [ ] **Fallbacks**: Implementar fallbacks para cenários de erro

### Testes

- [ ] **Unit Tests**: Executar testes unitários
- [ ] **Integration Tests**: Testar integração com API
- [ ] **End-to-End**: Testar fluxos completos
- [ ] **Performance**: Verificar performance

---

## Scripts de Migração

### 1. Script de Conversão de Valores

```php
<?php
/**
 * Script para converter valores monetários de reais para centavos
 */

function convertMonetaryValues(array $data): array
{
    $monetaryFields = ['amount', 'total', 'price', 'subtotal', 'discount_amount', 'shipping_amount'];

    foreach ($data as $key => $value) {
        if (in_array($key, $monetaryFields) && is_numeric($value)) {
            $data[$key] = (int) ($value * 100);
        } elseif (is_array($value)) {
            $data[$key] = convertMonetaryValues($value);
        }
    }

    return $data;
}

// Exemplo de uso
$oldOrderData = [
    'amount' => 99.99,
    'items' => [
        ['price' => 49.99],
        ['price' => 50.00]
    ]
];

$newOrderData = convertMonetaryValues($oldOrderData);
// Resultado: amount = 9999, items[0]['price'] = 4999, items[1]['price'] = 5000
```

### 2. Script de Validação de Configuração

```php
<?php
/**
 * Script para validar configuração da migração
 */

function validateMigrationConfig(array $config): array
{
    $errors = [];

    // Verificar campos obrigatórios
    $required = ['api_url', 'tenant_id', 'api_key', 'secret_key'];
    foreach ($required as $field) {
        if (empty($config[$field])) {
            $errors[] = "Campo obrigatório ausente: {$field}";
        }
    }

    // Verificar formato do tenant_id
    if (!empty($config['tenant_id']) && !preg_match('/^[a-z0-9-]+$/', $config['tenant_id'])) {
        $errors[] = "tenant_id deve conter apenas letras minúsculas, números e hífens";
    }

    // Verificar URL da API
    if (!empty($config['api_url']) && !filter_var($config['api_url'], FILTER_VALIDATE_URL)) {
        $errors[] = "api_url deve ser uma URL válida";
    }

    return $errors;
}

// Exemplo de uso
$config = [
    'api_url' => 'https://api.clubify.com',
    'tenant_id' => 'meu-tenant',
    'api_key' => 'key_123',
    'secret_key' => 'secret_456'
];

$errors = validateMigrationConfig($config);
if (empty($errors)) {
    echo "✅ Configuração válida!\n";
} else {
    echo "❌ Erros encontrados:\n";
    foreach ($errors as $error) {
        echo "   - {$error}\n";
    }
}
```

---

## Problemas Comuns e Soluções

### 1. Erro: "tenant_id is required"

**Problema**: Configuração não inclui `tenant_id`
**Solução**:
```php
$sdk = new ClubifyCheckoutSDK([
    'tenant_id' => 'seu-tenant-id', // Adicionar este campo
    // ... outras configurações
]);
```

### 2. Erro: "Amount must be in cents"

**Problema**: Valores ainda em formato decimal
**Solução**:
```php
// Antes
'amount' => 99.99

// Depois
'amount' => 9999 // R$ 99,99 em centavos
```

### 3. Erro: "Customer document is required"

**Problema**: Campo `document` não fornecido para clientes brasileiros
**Solução**:
```php
$customer = $sdk->customers()->createCustomer([
    'name' => 'João Silva',
    'email' => 'joao@email.com',
    'document' => '12345678901', // CPF obrigatório
    // ...
]);
```

### 4. Erro: "Invalid phone format"

**Problema**: Telefone não está em formato internacional
**Solução**:
```php
// Antes
'phone' => '(11) 99999-9999'

// Depois
'phone' => '+5511999999999' // Formato internacional
```

---

## Recursos Adicionais

### Documentação
- [Guia de Uso - Orders](./usage-guides/orders-module-guide.md)
- [Guia de Uso - Payments](./usage-guides/payments-module-guide.md)
- [Guia de Uso - Customers](./usage-guides/customers-module-guide.md)
- [Guia de Uso - Subscriptions](./usage-guides/subscriptions-module-guide.md)

### Exemplos
- [Integração E-commerce Completa](../examples/complete-ecommerce-integration.php)
- [Integração SaaS com Assinaturas](../examples/subscription-saas-integration.php)

### Suporte
- **Email**: dev-support@clubify.com
- **Discord**: [Clubify Developers](https://discord.gg/clubify-dev)
- **GitHub**: [Issues e Discussões](https://github.com/clubify/checkout-sdk-php)

---

## Cronograma Sugerido

### Semana 1: Preparação
- Análise do código atual
- Setup do ambiente de testes
- Backup e versionamento

### Semana 2: Migração Base
- Atualização de dependências
- Migração da configuração
- Adaptação dos módulos core

### Semana 3: Novos Módulos
- Implementação dos módulos enterprise
- Adaptação dos fluxos existentes
- Testes de integração

### Semana 4: Finalização
- Testes completos
- Otimizações de performance
- Deploy em produção

---

*Este guia será atualizado conforme novas versões e feedback da comunidade.*