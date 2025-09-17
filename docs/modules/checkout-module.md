# 🛒 Checkout Module - Documentação Completa

## Visão Geral

O **Checkout Module** é responsável por orquestrar todo o processo de checkout, incluindo gestão de sessões, carrinho de compras, processo one-click e navegação de flows personalizados.

### 🎯 Funcionalidades Principais

- **Gestão de Sessões**: Controle completo de sessões de checkout com persistência
- **Carrinho de Compras**: CRUD completo de itens, cupons e cálculos de totais
- **One-Click Checkout**: Processo simplificado para conversão rápida
- **Flow Navigation**: Navegação dinâmica através de flows personalizados
- **Cache Inteligente**: Sistema de cache multi-nível para performance otimizada

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** e utiliza **lazy loading** para otimização de performance:

```
CheckoutModule
├── Services/
│   ├── SessionService     # Gestão de sessões
│   ├── CartService        # Carrinho de compras
│   ├── OneClickService    # Checkout simplificado
│   └── FlowService        # Navegação de flows
├── Contracts/
│   ├── SessionRepositoryInterface
│   └── CartRepositoryInterface
└── DTOs/
    ├── SessionData       # DTO de sessão
    ├── CartData          # DTO de carrinho
    └── ItemData          # DTO de item
```

## 📚 API Reference

### CheckoutModule

#### Métodos de Sessão

##### `createSession(string $organizationId, array $data = []): array`

Cria uma nova sessão de checkout.

**Parâmetros:**
```php
$organizationId = 'org_123';                    // Required
$data = [
    'customer_id' => 'cust_456',                // Optional
    'source' => 'website',                      // Optional
    'utm_source' => 'google',                   // Optional
    'utm_campaign' => 'summer_sale',            // Optional
    'metadata' => ['key' => 'value'],           // Optional
    'expires_at' => '2025-01-16T23:59:59Z',    // Optional
    'settings' => [                             // Optional
        'allow_guest_checkout' => true,
        'require_phone' => false,
        'auto_apply_coupons' => true
    ]
];
```

**Retorno:**
```php
[
    'id' => 'session_789',
    'organization_id' => 'org_123',
    'customer_id' => 'cust_456',
    'status' => 'active',
    'cart_id' => 'cart_101',
    'source' => 'website',
    'utm_data' => [
        'utm_source' => 'google',
        'utm_campaign' => 'summer_sale'
    ],
    'created_at' => '2025-01-16T10:00:00Z',
    'expires_at' => '2025-01-16T23:59:59Z'
]
```

##### `getSession(string $sessionId): ?array`

Obtém uma sessão de checkout.

##### `updateSession(string $sessionId, array $data): array`

Atualiza uma sessão de checkout.

##### `completeSession(string $sessionId): array`

Finaliza uma sessão de checkout.

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

// Criar sessão
$session = $sdk->checkout()->createSession('org_123', [
    'customer_id' => 'cust_456',
    'source' => 'website',
    'utm_campaign' => 'black_friday'
]);

// Atualizar sessão
$updatedSession = $sdk->checkout()->updateSession($session['id'], [
    'metadata' => ['promo_code' => 'BLACK20']
]);

// Finalizar sessão
$completedSession = $sdk->checkout()->completeSession($session['id']);
```

#### Métodos de Carrinho

##### `createCart(string $sessionId, array $data = []): array`

Cria um novo carrinho para uma sessão.

##### `addToCart(string $cartId, array $item): array`

Adiciona um item ao carrinho.

**Parâmetros do Item:**
```php
$item = [
    'product_id' => 'prod_123',                 // Required
    'quantity' => 2,                            // Required
    'price_override' => null,                   // Optional
    'metadata' => ['variant' => 'premium'],     // Optional
    'custom_fields' => [                        // Optional
        'personalization' => 'Nome gravado'
    ]
];
```

##### `removeFromCart(string $cartId, string $itemId): array`

Remove um item do carrinho.

##### `updateCartItem(string $cartId, string $itemId, int $quantity): array`

Atualiza a quantidade de um item no carrinho.

##### `applyCoupon(string $cartId, string $couponCode): array`

Aplica um cupom de desconto ao carrinho.

##### `removeCoupon(string $cartId): array`

Remove o cupom de desconto do carrinho.

##### `calculateCartTotals(string $cartId): array`

Calcula os totais do carrinho.

**Exemplo de Uso:**
```php
// Criar carrinho
$cart = $sdk->checkout()->createCart($session['id']);

// Adicionar itens
$cart = $sdk->checkout()->addToCart($cart['id'], [
    'product_id' => 'prod_curso_php',
    'quantity' => 1
]);

$cart = $sdk->checkout()->addToCart($cart['id'], [
    'product_id' => 'prod_ebook_bonus',
    'quantity' => 1,
    'metadata' => ['is_order_bump' => true]
]);

// Aplicar cupom
$cart = $sdk->checkout()->applyCoupon($cart['id'], 'DESCONTO10');

// Calcular totais
$totals = $sdk->checkout()->calculateCartTotals($cart['id']);

echo "Subtotal: R$ " . number_format($totals['subtotal'] / 100, 2, ',', '.');
echo "Desconto: R$ " . number_format($totals['discount'] / 100, 2, ',', '.');
echo "Total: R$ " . number_format($totals['total'] / 100, 2, ',', '.');
```

#### Métodos One-Click

##### `initiateOneClick(string $organizationId, array $productData, array $customerData): array`

Inicia um processo de checkout one-click.

**Parâmetros:**
```php
$productData = [
    'id' => 'prod_123',                         // Required
    'quantity' => 1,                            // Optional (default: 1)
    'price_override' => null                    // Optional
];

$customerData = [
    'email' => 'cliente@exemplo.com',           // Required
    'name' => 'João Silva',                     // Optional
    'phone' => '+5511999999999',               // Optional
    'document' => '123.456.789-00'             // Optional
];
```

##### `completeOneClick(string $oneClickId, array $paymentData): array`

Completa um processo de checkout one-click.

**Exemplo de Uso:**
```php
// Iniciar one-click
$oneClick = $sdk->checkout()->initiateOneClick('org_123', [
    'id' => 'prod_curso_react',
    'quantity' => 1
], [
    'email' => 'joao@exemplo.com',
    'name' => 'João Silva'
]);

// Completar one-click
$completed = $sdk->checkout()->completeOneClick($oneClick['id'], [
    'payment_method' => 'credit_card',
    'card_token' => 'card_token_123'
]);
```

#### Métodos de Flow

##### `createFlow(string $organizationId, array $flowConfig): array`

Cria um novo flow de checkout.

**Configuração do Flow:**
```php
$flowConfig = [
    'name' => 'Flow Padrão',                    // Required
    'type' => 'standard',                       // Required (standard/upsell/downsell)
    'steps' => [                                // Required
        [
            'type' => 'customer_info',
            'required' => true,
            'config' => ['require_phone' => true]
        ],
        [
            'type' => 'payment_info',
            'required' => true,
            'config' => ['allowed_methods' => ['credit_card', 'pix']]
        ],
        [
            'type' => 'order_review',
            'required' => false,
            'config' => ['show_order_bumps' => true]
        ]
    ],
    'settings' => [                             // Optional
        'allow_back_navigation' => true,
        'auto_save_progress' => true,
        'timeout_minutes' => 30
    ]
];
```

##### `navigateFlow(string $sessionId, string $currentStep, array $data = []): array`

Navega para o próximo passo do flow.

##### `getFlowConfig(string $sessionId): ?array`

Obtém a configuração do flow atual.

##### `validateFlowData(string $sessionId, string $step, array $data): array`

Valida os dados de um passo do flow.

**Exemplo de Uso:**
```php
// Criar flow
$flow = $sdk->checkout()->createFlow('org_123', [
    'name' => 'Flow Venda Curso',
    'type' => 'standard',
    'steps' => [
        ['type' => 'customer_info', 'required' => true],
        ['type' => 'payment_info', 'required' => true],
        ['type' => 'confirmation', 'required' => false]
    ]
]);

// Navegar no flow
$session = $sdk->checkout()->createSession('org_123', [
    'flow_id' => $flow['id']
]);

// Passo 1: Informações do cliente
$step1 = $sdk->checkout()->navigateFlow($session['id'], 'customer_info', [
    'name' => 'Maria Silva',
    'email' => 'maria@exemplo.com',
    'phone' => '+5511888888888'
]);

// Validar dados antes de prosseguir
$validation = $sdk->checkout()->validateFlowData($session['id'], 'customer_info', $step1['data']);

if ($validation['is_valid']) {
    // Passo 2: Informações de pagamento
    $step2 = $sdk->checkout()->navigateFlow($session['id'], 'payment_info', [
        'payment_method' => 'credit_card',
        'card_token' => 'card_token_456'
    ]);
}
```

#### Services Disponíveis

##### `sessions(): SessionService`

Retorna o serviço de gestão de sessões.

**Métodos Disponíveis:**
- `create(string $organizationId, array $data): array` - Criar sessão
- `find(string $sessionId): ?array` - Buscar sessão
- `update(string $sessionId, array $data): array` - Atualizar sessão
- `complete(string $sessionId): array` - Completar sessão
- `expire(string $sessionId): bool` - Expirar sessão
- `cleanup(): int` - Limpar sessões expiradas

##### `cart(): CartService`

Retorna o serviço de carrinho de compras.

**Métodos Disponíveis:**
- `create(string $sessionId, array $data): array` - Criar carrinho
- `find(string $cartId): ?array` - Buscar carrinho
- `addItem(string $cartId, array $item): array` - Adicionar item
- `updateItem(string $cartId, string $itemId, int $quantity): array` - Atualizar item
- `removeItem(string $cartId, string $itemId): array` - Remover item
- `applyCoupon(string $cartId, string $couponCode): array` - Aplicar cupom
- `removeCoupon(string $cartId): array` - Remover cupom
- `calculateTotals(string $cartId): array` - Calcular totais
- `clear(string $cartId): array` - Limpar carrinho

##### `oneClick(): OneClickService`

Retorna o serviço de checkout one-click.

**Métodos Disponíveis:**
- `initiate(string $organizationId, array $productData, array $customerData): array` - Iniciar
- `complete(string $oneClickId, array $paymentData): array` - Completar
- `cancel(string $oneClickId): bool` - Cancelar
- `getStatus(string $oneClickId): array` - Obter status

##### `flows(): FlowService`

Retorna o serviço de navegação de flows.

**Métodos Disponíveis:**
- `create(string $organizationId, array $flowConfig): array` - Criar flow
- `navigate(string $sessionId, string $currentStep, array $data): array` - Navegar
- `validate(string $sessionId, string $step, array $data): array` - Validar
- `getConfig(string $sessionId): ?array` - Obter configuração
- `getCurrentStep(string $sessionId): ?string` - Passo atual
- `canNavigateBack(string $sessionId): bool` - Pode voltar

## 💡 Exemplos Práticos

### Checkout Completo Passo-a-Passo

```php
// 1. Criar sessão de checkout
$session = $sdk->checkout()->createSession('org_123', [
    'customer_id' => null, // Guest checkout
    'source' => 'landing_page',
    'utm_campaign' => 'spring_promo'
]);

// 2. Criar carrinho
$cart = $sdk->checkout()->createCart($session['id']);

// 3. Adicionar produto principal
$cart = $sdk->checkout()->addToCart($cart['id'], [
    'product_id' => 'prod_curso_fullstack',
    'quantity' => 1
]);

// 4. Adicionar order bump
$cart = $sdk->checkout()->addToCart($cart['id'], [
    'product_id' => 'prod_mentoria_bonus',
    'quantity' => 1,
    'metadata' => ['is_order_bump' => true, 'discount' => 50]
]);

// 5. Aplicar cupom promocional
$cart = $sdk->checkout()->applyCoupon($cart['id'], 'SPRING30');

// 6. Calcular totais finais
$totals = $sdk->checkout()->calculateCartTotals($cart['id']);

// 7. Atualizar sessão com informações do cliente
$session = $sdk->checkout()->updateSession($session['id'], [
    'customer_data' => [
        'name' => 'Ana Costa',
        'email' => 'ana@exemplo.com',
        'phone' => '+5511777777777',
        'document' => '987.654.321-00'
    ],
    'billing_address' => [
        'street' => 'Rua das Palmeiras, 456',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567'
    ]
]);

// 8. Finalizar sessão
$completedSession = $sdk->checkout()->completeSession($session['id']);

echo "Checkout concluído! Order ID: " . $completedSession['order_id'];
```

### Flow Personalizado com Upsells

```php
// Criar flow com upsells
$upsellFlow = $sdk->checkout()->createFlow('org_123', [
    'name' => 'Flow com Upsells Sequenciais',
    'type' => 'upsell',
    'steps' => [
        [
            'type' => 'product_selection',
            'required' => true,
            'config' => [
                'main_product' => 'prod_curso_basico',
                'show_variants' => true
            ]
        ],
        [
            'type' => 'customer_info',
            'required' => true,
            'config' => [
                'require_phone' => true,
                'auto_fill_from_history' => true
            ]
        ],
        [
            'type' => 'upsell_1',
            'required' => false,
            'config' => [
                'product' => 'prod_curso_intermediario',
                'discount_percentage' => 40,
                'timer_seconds' => 300
            ]
        ],
        [
            'type' => 'upsell_2',
            'required' => false,
            'config' => [
                'product' => 'prod_mentoria_grupo',
                'discount_percentage' => 60,
                'timer_seconds' => 180
            ]
        ],
        [
            'type' => 'payment_info',
            'required' => true,
            'config' => [
                'allowed_methods' => ['credit_card', 'pix', 'boleto'],
                'installments_max' => 12
            ]
        ],
        [
            'type' => 'order_confirmation',
            'required' => false,
            'config' => [
                'show_order_summary' => true,
                'enable_social_sharing' => true
            ]
        ]
    ]
]);

// Iniciar sessão no flow
$session = $sdk->checkout()->createSession('org_123', [
    'flow_id' => $upsellFlow['id'],
    'metadata' => ['flow_type' => 'upsell_sequence']
]);

// Navegar através dos passos
$steps = [];

// Passo 1: Seleção do produto
$steps['product'] = $sdk->checkout()->navigateFlow($session['id'], 'product_selection', [
    'selected_product' => 'prod_curso_basico',
    'variant' => 'premium'
]);

// Passo 2: Informações do cliente
$steps['customer'] = $sdk->checkout()->navigateFlow($session['id'], 'customer_info', [
    'name' => 'Carlos Oliveira',
    'email' => 'carlos@exemplo.com',
    'phone' => '+5511666666666'
]);

// Passo 3: Primeiro upsell (aceito)
$steps['upsell1'] = $sdk->checkout()->navigateFlow($session['id'], 'upsell_1', [
    'accepted' => true,
    'decision_time_seconds' => 45
]);

// Passo 4: Segundo upsell (rejeitado)
$steps['upsell2'] = $sdk->checkout()->navigateFlow($session['id'], 'upsell_2', [
    'accepted' => false,
    'decision_time_seconds' => 120
]);

// Passo 5: Pagamento
$steps['payment'] = $sdk->checkout()->navigateFlow($session['id'], 'payment_info', [
    'payment_method' => 'credit_card',
    'installments' => 6,
    'card_token' => 'card_token_789'
]);

// Passo 6: Confirmação
$steps['confirmation'] = $sdk->checkout()->navigateFlow($session['id'], 'order_confirmation', [
    'marketing_consent' => true,
    'newsletter_subscription' => true
]);
```

### One-Click para Produtos Recorrentes

```php
// Setup de one-click para assinaturas
$subscriptionOneClick = $sdk->checkout()->initiateOneClick('org_123', [
    'id' => 'prod_plano_premium_mensal',
    'quantity' => 1,
    'subscription_config' => [
        'trial_days' => 7,
        'billing_cycle' => 'monthly',
        'auto_renew' => true
    ]
], [
    'email' => 'premium@exemplo.com',
    'name' => 'Empresa Premium Ltda',
    'document' => '12.345.678/0001-90',
    'saved_payment_method' => 'pm_saved_card_123'
]);

// Completar com método de pagamento salvo
$completedSubscription = $sdk->checkout()->completeOneClick($subscriptionOneClick['id'], [
    'use_saved_payment' => true,
    'payment_method_id' => 'pm_saved_card_123',
    'terms_accepted' => true,
    'auto_renew_consent' => true
]);

echo "Assinatura ativada! ID: " . $completedSubscription['subscription_id'];
```

## 🔧 DTOs e Validação

### SessionData DTO

```php
use ClubifyCheckout\Modules\Checkout\DTOs\SessionData;

$sessionData = new SessionData([
    'organization_id' => 'org_123',
    'customer_id' => 'cust_456',
    'source' => 'website',
    'utm_data' => [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'utm_campaign' => 'conversion_campaign'
    ],
    'metadata' => [
        'landing_page' => '/curso-php',
        'referrer' => 'https://google.com'
    ],
    'settings' => [
        'allow_guest_checkout' => true,
        'require_phone' => false,
        'session_timeout_minutes' => 30
    ]
]);

// Validação automática
if ($sessionData->isValid()) {
    $session = $sdk->checkout()->sessions()->create(
        $sessionData->organization_id,
        $sessionData->toArray()
    );
}
```

### CartData DTO

```php
use ClubifyCheckout\Modules\Checkout\DTOs\CartData;

$cartData = new CartData([
    'session_id' => 'session_789',
    'currency' => 'BRL',
    'items' => [
        [
            'product_id' => 'prod_123',
            'quantity' => 2,
            'unit_price' => 9900
        ]
    ],
    'coupon_code' => 'DESCONTO20',
    'shipping_address' => [
        'street' => 'Rua Principal, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567'
    ],
    'metadata' => [
        'cart_type' => 'standard',
        'created_from' => 'product_page'
    ]
]);
```

### ItemData DTO

```php
use ClubifyCheckout\Modules\Checkout\DTOs\ItemData;

$itemData = new ItemData([
    'product_id' => 'prod_curso_react',
    'quantity' => 1,
    'unit_price' => 29900,
    'metadata' => [
        'variant' => 'premium',
        'access_type' => 'lifetime',
        'bonus_included' => true
    ],
    'custom_fields' => [
        'student_name' => 'João Silva',
        'company' => 'Tech Startup Ltda'
    ]
]);

// Validação de item
if ($itemData->isValid()) {
    $cart = $sdk->checkout()->addToCart($cartId, $itemData->toArray());
}
```

## 🎯 Casos de Uso Avançados

### Checkout Multi-Tenant

```php
// Configuração para diferentes tenants
$tenants = [
    'tenant_ecommerce' => [
        'settings' => [
            'require_phone' => true,
            'allow_guest_checkout' => false,
            'default_currency' => 'BRL'
        ]
    ],
    'tenant_saas' => [
        'settings' => [
            'require_phone' => false,
            'allow_guest_checkout' => true,
            'default_currency' => 'USD'
        ]
    ]
];

foreach ($tenants as $tenantId => $config) {
    $session = $sdk->checkout()->createSession($tenantId, [
        'settings' => $config['settings']
    ]);

    echo "Sessão criada para tenant {$tenantId}: {$session['id']}\n";
}
```

### Recovery de Carrinho Abandonado

```php
// Identificar carrinhos abandonados
$checkoutService = $sdk->checkout();

// Buscar sessões expiradas com carrinhos não vazios
$abandonedSessions = $checkoutService->sessions()->findAbandoned([
    'abandoned_minutes' => 30,
    'has_items' => true,
    'customer_contacted' => false
]);

foreach ($abandonedSessions as $session) {
    // Reativar sessão
    $reactivatedSession = $checkoutService->sessions()->reactivate($session['id'], [
        'expires_at' => date('c', strtotime('+24 hours')),
        'recovery_token' => bin2hex(random_bytes(16))
    ]);

    // Criar link de recovery
    $recoveryUrl = "https://checkout.exemplo.com/recovery/{$reactivatedSession['recovery_token']}";

    echo "Recovery criado para {$session['customer_email']}: {$recoveryUrl}\n";

    // Marcar como contatado
    $checkoutService->sessions()->markAsContacted($session['id']);
}
```

### Checkout com Pagamento Parcelado

```php
// Configurar checkout com opções de parcelamento
$installmentSession = $sdk->checkout()->createSession('org_123', [
    'payment_options' => [
        'allow_installments' => true,
        'max_installments' => 12,
        'min_installment_value' => 2000, // R$ 20,00
        'interest_rate' => 2.99 // % ao mês
    ]
]);

$cart = $sdk->checkout()->createCart($installmentSession['id']);

// Adicionar produto de alto valor
$cart = $sdk->checkout()->addToCart($cart['id'], [
    'product_id' => 'prod_curso_completo',
    'quantity' => 1,
    'unit_price' => 149900 // R$ 1.499,00
]);

// Calcular opções de parcelamento
$totals = $sdk->checkout()->calculateCartTotals($cart['id']);
$installmentOptions = $totals['installment_options'];

foreach ($installmentOptions as $option) {
    $value = number_format($option['installment_value'] / 100, 2, ',', '.');
    echo "{$option['installments']}x de R$ {$value}";

    if ($option['interest_rate'] > 0) {
        echo " (com juros de {$option['interest_rate']}% a.m.)";
    }

    echo "\n";
}
```

## 📊 Relatórios e Analytics

### Estatísticas do Módulo

```php
// Obter estatísticas gerais
$stats = $sdk->checkout()->getStats();

echo "Módulo: {$stats['module']} v{$stats['version']}\n";
echo "Status: " . ($stats['enabled'] ? 'Ativo' : 'Inativo') . "\n";

foreach ($stats['services'] as $service => $loaded) {
    echo "Serviço {$service}: " . ($loaded ? 'Carregado' : 'Não carregado') . "\n";
}
```

### Análise de Conversão

```php
// Métricas de conversão por período
$conversionMetrics = $sdk->checkout()->sessions()->getConversionMetrics([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'group_by' => 'day'
]);

foreach ($conversionMetrics as $metric) {
    $conversionRate = ($metric['completed_sessions'] / $metric['total_sessions']) * 100;
    echo "Data: {$metric['date']} - Conversão: {$conversionRate}%\n";
}

// Análise de abandono por etapa
$abandonmentAnalysis = $sdk->checkout()->flows()->getAbandonmentAnalysis([
    'flow_id' => 'flow_123',
    'period_days' => 30
]);

foreach ($abandonmentAnalysis['steps'] as $step) {
    echo "Etapa: {$step['name']} - Abandono: {$step['abandonment_rate']}%\n";
}
```

## 🔍 Monitoramento e Logs

### Health Check

```php
// Verificar saúde dos serviços
$healthChecks = [
    'sessions' => $sdk->checkout()->sessions()->healthCheck(),
    'cart' => $sdk->checkout()->cart()->healthCheck(),
    'one_click' => $sdk->checkout()->oneClick()->healthCheck(),
    'flows' => $sdk->checkout()->flows()->healthCheck()
];

foreach ($healthChecks as $service => $isHealthy) {
    echo "Serviço {$service}: " . ($isHealthy ? 'OK' : 'FALHA') . "\n";
}
```

### Cache Management

```php
// Limpar cache do módulo
$cacheCleared = $sdk->checkout()->clearCache();

if ($cacheCleared) {
    echo "Cache do checkout limpo com sucesso\n";
} else {
    echo "Erro ao limpar cache do checkout\n";
}

// Estatísticas de cache
$cacheStats = $sdk->checkout()->getCacheStats();
echo "Cache hits: {$cacheStats['hits']}\n";
echo "Cache misses: {$cacheStats['misses']}\n";
echo "Hit rate: {$cacheStats['hit_rate']}%\n";
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Checkout\Exceptions\CheckoutException;
use ClubifyCheckout\Modules\Checkout\Exceptions\SessionExpiredException;
use ClubifyCheckout\Modules\Checkout\Exceptions\CartNotFoundException;
use ClubifyCheckout\Modules\Checkout\Exceptions\InvalidCouponException;

try {
    $session = $sdk->checkout()->createSession('org_123', $data);
    $cart = $sdk->checkout()->addToCart($cartId, $item);
} catch (SessionExpiredException $e) {
    echo "Sessão expirada: " . $e->getMessage();
    // Redirecionar para nova sessão
} catch (CartNotFoundException $e) {
    echo "Carrinho não encontrado: " . $e->getMessage();
    // Criar novo carrinho
} catch (InvalidCouponException $e) {
    echo "Cupom inválido: " . $e->getMessage();
    // Mostrar erro ao usuário
} catch (CheckoutException $e) {
    echo "Erro no checkout: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Checkout
CLUBIFY_CHECKOUT_SESSION_TTL=1800
CLUBIFY_CHECKOUT_CART_PERSISTENCE=true
CLUBIFY_CHECKOUT_ENABLE_ONE_CLICK=true
CLUBIFY_CHECKOUT_MAX_ITEMS_PER_CART=50
CLUBIFY_CHECKOUT_AUTO_EXPIRE_SESSIONS=true
CLUBIFY_CHECKOUT_CACHE_ENABLED=true
```

### Configuração Avançada

```php
$config = [
    'checkout' => [
        'session' => [
            'ttl_seconds' => 1800,
            'auto_extend' => true,
            'cleanup_interval' => 3600
        ],
        'cart' => [
            'persistence' => true,
            'max_items' => 50,
            'auto_calculate_totals' => true,
            'currency_conversion' => true
        ],
        'one_click' => [
            'enabled' => true,
            'require_confirmation' => false,
            'auto_save_payment_methods' => true
        ],
        'flow' => [
            'validation_strict' => true,
            'allow_back_navigation' => true,
            'auto_save_progress' => true
        ],
        'cache' => [
            'enabled' => true,
            'default_ttl' => 300,
            'max_size_mb' => 100
        ]
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de qualidade enterprise.**