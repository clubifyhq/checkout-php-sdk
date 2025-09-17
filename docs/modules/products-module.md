# 🛍️ Products Module - Documentação Completa

## Visão Geral

O **Products Module** é responsável pela gestão completa de produtos e ofertas na plataforma Clubify Checkout, incluindo criação de produtos, configuração de ofertas, order bumps, upsells, estratégias de preços e flows de vendas.

### 🎯 Funcionalidades Principais

- **CRUD Completo de Produtos**: Criação, leitura, atualização e exclusão de produtos
- **Gestão de Ofertas**: Configuração de ofertas com layouts e temas personalizados
- **Order Bumps**: Sistema de produtos complementares para aumentar AOV
- **Upsells**: Sistema de ofertas pós-compra para maximizar receita
- **Estratégias de Preços**: Múltiplas estratégias de precificação (fixo, dinâmico, escalonado)
- **Flows de Vendas**: Fluxos personalizados de checkout e conversão

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** e utiliza **lazy loading** para otimização de performance:

```
ProductsModule
├── Services/
│   ├── ProductService      # CRUD de produtos
│   ├── OfferService        # Gestão de ofertas
│   ├── OrderBumpService    # Order bumps
│   ├── UpsellService       # Sistema de upsells
│   ├── PricingService      # Estratégias de preços
│   └── FlowService         # Flows de vendas
├── Repositories/
│   └── ProductRepository   # Persistência de dados
└── DTOs/
    ├── ProductData        # DTO de produto
    ├── OfferData          # DTO de oferta
    └── PricingData        # DTO de preços
```

## 📚 API Reference

### ProductsModule

#### Métodos Principais

##### `setupProduct(array $productData, array $offerData = []): array`

Configura um produto completo com ofertas e estratégias de preço.

**Parâmetros:**
```php
$productData = [
    'name' => 'Nome do Produto',                    // Required
    'description' => 'Descrição detalhada',        // Required
    'price' => 9900,                               // Required (em centavos)
    'currency' => 'BRL',                           // Optional (default: BRL)
    'type' => 'digital',                           // Required (digital/physical)
    'category' => 'courses',                       // Optional
    'tags' => ['tag1', 'tag2'],                   // Optional
    'metadata' => ['key' => 'value'],             // Optional
    'pricing_strategy' => [                        // Optional
        'type' => 'fixed',
        'rules' => []
    ],
    'inventory' => [                               // Optional para produtos físicos
        'track_quantity' => true,
        'quantity' => 100,
        'allow_backorder' => false
    ]
];

$offerData = [
    'name' => 'Nome da Oferta',                    // Required
    'layout' => 'checkout_v2',                     // Required
    'theme' => 'modern',                           // Required
    'settings' => [                                // Optional
        'show_testimonials' => true,
        'enable_countdown' => false
    ]
];
```

**Retorno:**
```php
[
    'id' => 'prod_123',
    'name' => 'Nome do Produto',
    'price' => 9900,
    'currency' => 'BRL',
    'type' => 'digital',
    'status' => 'active',
    'offer' => [
        'id' => 'offer_456',
        'name' => 'Nome da Oferta',
        'layout' => 'checkout_v2',
        'theme' => 'modern',
        'url' => 'https://checkout.exemplo.com/offer_456'
    ],
    'pricing' => [
        'strategy' => 'fixed',
        'rules' => []
    ],
    'created_at' => '2025-01-16T10:00:00Z'
]
```

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

$result = $sdk->products()->setupProduct([
    'name' => 'Curso de PHP Avançado',
    'description' => 'Aprenda PHP 8.2+ com projetos práticos',
    'price' => 29900, // R$ 299,00
    'type' => 'digital',
    'category' => 'courses'
], [
    'name' => 'Página de Vendas - Curso PHP',
    'layout' => 'checkout_v2',
    'theme' => 'modern'
]);

echo "Produto criado: " . $result['id'];
echo "Oferta disponível em: " . $result['offer']['url'];
```

##### `setupSalesFlow(array $flowData): array`

Configura um flow de vendas completo com order bumps e upsells.

**Parâmetros:**
```php
$flowData = [
    'name' => 'Flow Principal',                    // Required
    'product_id' => 'prod_123',                   // Required
    'steps' => [                                  // Required
        ['type' => 'checkout', 'config' => []],
        ['type' => 'upsell', 'config' => []],
        ['type' => 'confirmation', 'config' => []]
    ],
    'order_bumps' => [                            // Optional
        [
            'product_id' => 'prod_bump_1',
            'position' => 'after_products',
            'discount_percentage' => 20
        ]
    ],
    'upsells' => [                                // Optional
        [
            'product_id' => 'prod_upsell_1',
            'trigger' => 'after_purchase',
            'discount_percentage' => 30
        ]
    ]
];
```

**Exemplo de Uso:**
```php
$flow = $sdk->products()->setupSalesFlow([
    'name' => 'Flow Curso PHP + Bônus',
    'product_id' => 'prod_123',
    'steps' => [
        ['type' => 'checkout', 'config' => ['theme' => 'modern']],
        ['type' => 'upsell', 'config' => ['product_id' => 'prod_bonus']],
        ['type' => 'confirmation', 'config' => ['redirect_url' => '/obrigado']]
    ],
    'order_bumps' => [
        [
            'product_id' => 'prod_ebook',
            'position' => 'after_products',
            'discount_percentage' => 50
        ]
    ]
]);
```

##### `duplicateProduct(string $productId, array $overrideData = []): array`

Duplica um produto existente com todas suas configurações.

**Exemplo de Uso:**
```php
$duplicatedProduct = $sdk->products()->duplicateProduct('prod_123', [
    'name' => 'Curso de PHP Avançado - Edição 2025'
]);
```

#### Services Disponíveis

##### `getProductService(): ProductService`

Retorna o serviço de gestão de produtos.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar produto
- `get(string $id): array` - Obter produto
- `update(string $id, array $data): array` - Atualizar produto
- `delete(string $id): bool` - Excluir produto
- `list(array $filters = []): array` - Listar produtos
- `search(string $query): array` - Buscar produtos
- `count(): int` - Contar produtos

##### `getOfferService(): OfferService`

Retorna o serviço de gestão de ofertas.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar oferta
- `get(string $id): array` - Obter oferta
- `update(string $id, array $data): array` - Atualizar oferta
- `delete(string $id): bool` - Excluir oferta
- `getByProduct(string $productId): array` - Ofertas por produto
- `duplicate(string $id): array` - Duplicar oferta

##### `getOrderBumpService(): OrderBumpService`

Retorna o serviço de order bumps.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar order bump
- `get(string $id): array` - Obter order bump
- `update(string $id, array $data): array` - Atualizar order bump
- `delete(string $id): bool` - Excluir order bump
- `getByOffer(string $offerId): array` - Order bumps por oferta
- `countActive(): int` - Contar order bumps ativos

##### `getUpsellService(): UpsellService`

Retorna o serviço de upsells.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar upsell
- `get(string $id): array` - Obter upsell
- `update(string $id, array $data): array` - Atualizar upsell
- `delete(string $id): bool` - Excluir upsell
- `getByFlow(string $flowId): array` - Upsells por flow
- `countActive(): int` - Contar upsells ativos

##### `getPricingService(): PricingService`

Retorna o serviço de estratégias de preços.

**Métodos Disponíveis:**
- `createStrategy(string $productId, array $strategy): array` - Criar estratégia
- `getStrategy(string $productId): array` - Obter estratégia
- `updateStrategy(string $productId, array $strategy): array` - Atualizar estratégia
- `calculatePrice(string $productId, array $context): int` - Calcular preço
- `countStrategies(): int` - Contar estratégias

##### `getFlowService(): FlowService`

Retorna o serviço de flows de vendas.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar flow
- `get(string $id): array` - Obter flow
- `update(string $id, array $data): array` - Atualizar flow
- `delete(string $id): bool` - Excluir flow
- `execute(string $flowId, array $context): array` - Executar flow
- `count(): int` - Contar flows

## 💡 Exemplos Práticos

### Criação de Produto Digital

```php
// Produto digital básico
$digitalProduct = $sdk->products()->getProductService()->create([
    'name' => 'E-book: Guia Completo de PHP',
    'description' => 'Aprenda PHP do básico ao avançado com este guia completo.',
    'price' => 4900, // R$ 49,00
    'currency' => 'BRL',
    'type' => 'digital',
    'category' => 'ebooks',
    'tags' => ['php', 'programacao', 'ebook'],
    'metadata' => [
        'pages' => 250,
        'format' => 'PDF',
        'language' => 'pt-BR'
    ]
]);

// Criar oferta para o produto
$offer = $sdk->products()->getOfferService()->create([
    'product_id' => $digitalProduct['id'],
    'name' => 'Landing Page - E-book PHP',
    'layout' => 'single_column',
    'theme' => 'light',
    'settings' => [
        'show_testimonials' => true,
        'enable_countdown' => true,
        'countdown_minutes' => 30,
        'show_guarantee' => true
    ]
]);
```

### Produto Físico com Estoque

```php
// Produto físico com controle de estoque
$physicalProduct = $sdk->products()->getProductService()->create([
    'name' => 'Caneca "PHP Developer"',
    'description' => 'Caneca personalizada para desenvolvedores PHP.',
    'price' => 2900, // R$ 29,00
    'type' => 'physical',
    'category' => 'merchandise',
    'inventory' => [
        'track_quantity' => true,
        'quantity' => 50,
        'allow_backorder' => false,
        'low_stock_threshold' => 10
    ],
    'shipping' => [
        'weight' => 300, // 300g
        'dimensions' => [
            'width' => 10,
            'height' => 12,
            'length' => 8
        ]
    ]
]);
```

### Flow de Vendas com Order Bump e Upsell

```php
// Setup completo de flow de vendas
$salesFlow = $sdk->products()->setupSalesFlow([
    'name' => 'Flow Curso Completo + Bônus',
    'product_id' => 'prod_curso_php',
    'steps' => [
        [
            'type' => 'checkout',
            'config' => [
                'theme' => 'modern',
                'layout' => 'two_column',
                'show_testimonials' => true
            ]
        ],
        [
            'type' => 'upsell',
            'config' => [
                'product_id' => 'prod_mentoria',
                'headline' => 'Upgrade para Mentoria 1:1',
                'discount_percentage' => 30
            ]
        ],
        [
            'type' => 'confirmation',
            'config' => [
                'redirect_url' => '/area-membros',
                'show_social_proof' => true
            ]
        ]
    ],
    'order_bumps' => [
        [
            'product_id' => 'prod_ebook_bonus',
            'position' => 'after_products',
            'headline' => 'Adicione o E-book Bônus',
            'description' => 'Guia extra com 50 dicas práticas',
            'discount_percentage' => 70,
            'original_price' => 2900,
            'discounted_price' => 900
        ]
    ],
    'upsells' => [
        [
            'product_id' => 'prod_curso_avancado',
            'trigger' => 'after_purchase',
            'headline' => 'Upgrade para o Curso Avançado',
            'discount_percentage' => 50,
            'timer_minutes' => 15
        ]
    ]
]);
```

### Estratégias de Preços

```php
$pricingService = $sdk->products()->getPricingService();

// Preço escalonado por quantidade
$tieredPricing = $pricingService->createStrategy('prod_123', [
    'type' => 'tiered',
    'rules' => [
        ['min_quantity' => 1, 'max_quantity' => 5, 'price' => 9900],
        ['min_quantity' => 6, 'max_quantity' => 20, 'price' => 8900],
        ['min_quantity' => 21, 'max_quantity' => null, 'price' => 7900]
    ]
]);

// Preço dinâmico baseado em contexto
$dynamicPricing = $pricingService->createStrategy('prod_456', [
    'type' => 'dynamic',
    'rules' => [
        [
            'condition' => 'user_type',
            'value' => 'premium',
            'discount_percentage' => 20
        ],
        [
            'condition' => 'geo_location',
            'value' => 'BR',
            'currency' => 'BRL'
        ]
    ]
]);

// Calcular preço baseado no contexto
$calculatedPrice = $pricingService->calculatePrice('prod_456', [
    'user_type' => 'premium',
    'geo_location' => 'BR',
    'quantity' => 2
]);
```

## 🔧 DTOs e Validação

### ProductData DTO

```php
use ClubifyCheckout\Modules\Products\DTOs\ProductData;

$productData = new ProductData([
    'name' => 'Curso de React',
    'description' => 'Aprenda React do zero',
    'price' => 19900,
    'currency' => 'BRL',
    'type' => 'digital',
    'category' => 'courses',
    'tags' => ['react', 'javascript', 'frontend'],
    'metadata' => [
        'duration_hours' => 40,
        'level' => 'intermediate',
        'certificate' => true
    ]
]);

// Validação automática
if ($productData->isValid()) {
    $product = $sdk->products()->getProductService()->create($productData->toArray());
}

// Obter erros de validação
$errors = $productData->getValidationErrors();
foreach ($errors as $field => $messages) {
    echo "Campo {$field}: " . implode(', ', $messages) . "\n";
}
```

### OfferData DTO

```php
use ClubifyCheckout\Modules\Products\DTOs\OfferData;

$offerData = new OfferData([
    'name' => 'Página de Vendas React',
    'product_id' => 'prod_123',
    'layout' => 'checkout_v2',
    'theme' => 'modern',
    'settings' => [
        'show_testimonials' => true,
        'testimonials' => [
            [
                'name' => 'João Silva',
                'text' => 'Excelente curso!',
                'rating' => 5
            ]
        ],
        'enable_countdown' => true,
        'countdown_minutes' => 30,
        'show_guarantee' => true,
        'guarantee_days' => 30
    ],
    'seo' => [
        'title' => 'Curso de React - Aprenda do Zero',
        'description' => 'O melhor curso de React do Brasil',
        'keywords' => ['react', 'curso', 'javascript']
    ]
]);
```

### PricingData DTO

```php
use ClubifyCheckout\Modules\Products\DTOs\PricingData;

$pricingData = new PricingData([
    'strategy_type' => 'tiered',
    'base_price' => 9900,
    'currency' => 'BRL',
    'rules' => [
        [
            'type' => 'quantity_discount',
            'min_quantity' => 5,
            'discount_percentage' => 10
        ],
        [
            'type' => 'user_type_discount',
            'user_type' => 'premium',
            'discount_percentage' => 15
        ]
    ],
    'validity' => [
        'start_date' => '2025-01-01T00:00:00Z',
        'end_date' => '2025-12-31T23:59:59Z'
    ]
]);
```

## 🎯 Casos de Uso Avançados

### E-commerce com Múltiplos Produtos

```php
// Criação de múltiplos produtos relacionados
$products = [
    [
        'name' => 'Curso Frontend Completo',
        'price' => 49900,
        'type' => 'digital',
        'category' => 'courses'
    ],
    [
        'name' => 'E-book: Guia CSS Grid',
        'price' => 1900,
        'type' => 'digital',
        'category' => 'ebooks'
    ],
    [
        'name' => 'Template React Admin',
        'price' => 9900,
        'type' => 'digital',
        'category' => 'templates'
    ]
];

$createdProducts = [];
foreach ($products as $productData) {
    $product = $sdk->products()->getProductService()->create($productData);
    $createdProducts[] = $product;
}

// Criar bundle com todos os produtos
$bundle = $sdk->products()->getProductService()->create([
    'name' => 'Bundle Frontend Completo',
    'description' => 'Todos os recursos para dominar frontend',
    'price' => 79900, // Preço com desconto
    'type' => 'bundle',
    'category' => 'bundles',
    'bundle_products' => array_column($createdProducts, 'id'),
    'savings_amount' => 19900 // Economia em centavos
]);
```

### Sistema de Afiliados com Produtos

```php
// Produto com programa de afiliados
$productWithAffiliates = $sdk->products()->getProductService()->create([
    'name' => 'Curso de Marketing Digital',
    'price' => 39900,
    'type' => 'digital',
    'category' => 'courses',
    'affiliate_program' => [
        'enabled' => true,
        'commission_percentage' => 30,
        'cookie_duration_days' => 30,
        'minimum_payout' => 5000, // R$ 50,00
        'approval_required' => true
    ]
]);

// Criar oferta específica para afiliados
$affiliateOffer = $sdk->products()->getOfferService()->create([
    'product_id' => $productWithAffiliates['id'],
    'name' => 'Página para Afiliados - Marketing Digital',
    'layout' => 'affiliate_optimized',
    'theme' => 'professional',
    'settings' => [
        'show_commission_info' => true,
        'enable_affiliate_tracking' => true,
        'show_conversion_stats' => true
    ]
]);
```

### Produtos com Assinatura/Recorrência

```php
// Produto de assinatura mensal
$subscriptionProduct = $sdk->products()->getProductService()->create([
    'name' => 'Plataforma de Cursos - Mensal',
    'price' => 4900, // R$ 49,00/mês
    'type' => 'subscription',
    'category' => 'memberships',
    'subscription' => [
        'billing_cycle' => 'monthly',
        'trial_period_days' => 7,
        'setup_fee' => 0,
        'cancellation_policy' => 'anytime',
        'upgrade_options' => ['plan_premium', 'plan_enterprise']
    ]
]);

// Estratégia de preços para diferentes planos
$subscriptionPricing = $sdk->products()->getPricingService()->createStrategy(
    $subscriptionProduct['id'],
    [
        'type' => 'subscription_tiers',
        'rules' => [
            [
                'plan' => 'basic',
                'monthly_price' => 4900,
                'annual_price' => 49900, // 2 meses grátis
                'features' => ['feature1', 'feature2']
            ],
            [
                'plan' => 'premium',
                'monthly_price' => 9900,
                'annual_price' => 99900,
                'features' => ['feature1', 'feature2', 'feature3', 'feature4']
            ]
        ]
    ]
);
```

## 📊 Relatórios e Analytics

### Estatísticas do Módulo

```php
// Obter estatísticas gerais
$stats = $sdk->products()->getStats();

echo "Total de produtos: " . $stats['products_count'] . "\n";
echo "Total de ofertas: " . $stats['offers_count'] . "\n";
echo "Flows ativos: " . $stats['flows_count'] . "\n";
echo "Order bumps ativos: " . $stats['active_order_bumps'] . "\n";
echo "Upsells ativos: " . $stats['active_upsells'] . "\n";
echo "Estratégias de preço: " . $stats['pricing_strategies'] . "\n";
```

### Análise de Performance

```php
// Produtos mais vendidos
$topProducts = $sdk->products()->getProductService()->list([
    'sort_by' => 'sales_count',
    'sort_order' => 'desc',
    'limit' => 10
]);

foreach ($topProducts as $product) {
    echo "Produto: {$product['name']} - Vendas: {$product['sales_count']}\n";
}

// Conversão de order bumps
$orderBumps = $sdk->products()->getOrderBumpService()->list([
    'include_stats' => true
]);

foreach ($orderBumps as $bump) {
    $conversionRate = ($bump['stats']['conversions'] / $bump['stats']['impressions']) * 100;
    echo "Order Bump: {$bump['name']} - Conversão: {$conversionRate}%\n";
}
```

## 🔍 Monitoramento e Logs

### Health Check

```php
// Verificar saúde do módulo
$isHealthy = $sdk->products()->isHealthy();

if ($isHealthy) {
    echo "Módulo Products está saudável\n";
} else {
    echo "Módulo Products com problemas\n";

    // Logs detalhados estão disponíveis automaticamente
    // Verificar logs para diagnóstico
}
```

### Logs Estruturados

```php
// Os logs são gerados automaticamente para todas as operações:

/*
[2025-01-16 10:30:00] INFO: Starting setup_product transaction
{
    "operation": "setup_product",
    "product_name": "Curso de PHP",
    "has_offer": true
}

[2025-01-16 10:30:01] INFO: Product created successfully
{
    "product_id": "prod_123",
    "name": "Curso de PHP",
    "price": 29900,
    "type": "digital"
}

[2025-01-16 10:30:02] INFO: Transaction setup_product completed successfully
{
    "duration_ms": 2450.75,
    "operation": "setup_product"
}
*/
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Products\Exceptions\ProductException;
use ClubifyCheckout\Modules\Products\Exceptions\ProductNotFoundException;
use ClubifyCheckout\Modules\Products\Exceptions\InvalidPriceException;
use ClubifyCheckout\Modules\Products\Exceptions\InsufficientStockException;

try {
    $product = $sdk->products()->setupProduct($productData, $offerData);
} catch (ProductNotFoundException $e) {
    echo "Produto não encontrado: " . $e->getMessage();
} catch (InvalidPriceException $e) {
    echo "Preço inválido: " . $e->getMessage();
} catch (InsufficientStockException $e) {
    echo "Estoque insuficiente: " . $e->getMessage();
} catch (ProductException $e) {
    echo "Erro no produto: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Products
CLUBIFY_PRODUCTS_CACHE_TTL=1800
CLUBIFY_PRODUCTS_MAX_PRODUCTS_PER_PAGE=50
CLUBIFY_PRODUCTS_DEFAULT_CURRENCY=BRL
CLUBIFY_PRODUCTS_ENABLE_INVENTORY_TRACKING=true
CLUBIFY_PRODUCTS_AUTO_ARCHIVE_INACTIVE_DAYS=90
```

### Configuração Avançada

```php
$config = [
    'products' => [
        'cache_ttl' => 1800,
        'max_products_per_page' => 50,
        'default_currency' => 'BRL',
        'enable_inventory_tracking' => true,
        'auto_archive_inactive_days' => 90,
        'allowed_product_types' => ['digital', 'physical', 'subscription', 'bundle'],
        'pricing_strategies' => ['fixed', 'tiered', 'dynamic'],
        'order_bump_positions' => ['after_products', 'before_payment', 'in_payment_form']
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de qualidade enterprise.**