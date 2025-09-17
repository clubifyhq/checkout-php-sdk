# 👥 Customers Module - Documentação Completa

## Visão Geral

O **Customers Module** é responsável pela gestão completa de clientes, incluindo CRUD, matching inteligente, histórico de compras, perfis comportamentais e conformidade com LGPD/GDPR. O módulo oferece funcionalidades avançadas de análise e segmentação de clientes.

### 🎯 Funcionalidades Principais

- **Gestão Completa de Clientes**: CRUD completo com validação e sanitização
- **Matching Inteligente**: Identificação e deduplicação automática de clientes
- **Histórico de Transações**: Rastreamento completo de compras e interações
- **Perfis Comportamentais**: Análise de comportamento e segmentação avançada
- **Customer Lifetime Value (CLV)**: Cálculo e análise de valor do cliente
- **Conformidade LGPD/GDPR**: Exportação e anonimização de dados
- **Analytics Avançado**: Predições e recomendações baseadas em ML

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** com foco em privacy e performance:

```
CustomersModule
├── Services/
│   ├── CustomerService      # CRUD de clientes
│   ├── MatchingService      # Deduplicação e matching
│   ├── HistoryService       # Histórico de transações
│   └── ProfileService       # Perfis e analytics
├── Contracts/
│   └── CustomerRepositoryInterface
├── DTOs/
│   ├── CustomerData         # DTO de cliente
│   ├── HistoryData          # DTO de histórico
│   └── ProfileData          # DTO de perfil
└── Exceptions/
    ├── CustomerException
    ├── CustomerNotFoundException
    └── DuplicateCustomerException
```

## 📚 API Reference

### CustomersModule

#### Métodos de Gestão de Clientes

##### `createCustomer(array $customerData): array`

Cria um novo cliente com validação e sanitização.

**Parâmetros:**
```php
$customerData = [
    'name' => 'João Silva',                     // Required
    'email' => 'joao@exemplo.com',             // Required
    'phone' => '+5511999999999',               // Optional
    'document' => '123.456.789-00',            // Optional (CPF/CNPJ)
    'birth_date' => '1990-05-15',              // Optional
    'gender' => 'M',                           // Optional (M/F/O)
    'address' => [                             // Optional
        'street' => 'Rua das Flores, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567',
        'country' => 'BR'
    ],
    'metadata' => [                            // Optional
        'source' => 'website',
        'utm_campaign' => 'black_friday',
        'company' => 'Tech Startup Ltda'
    ],
    'preferences' => [                         // Optional
        'newsletter' => true,
        'sms_marketing' => false,
        'language' => 'pt-BR',
        'currency' => 'BRL'
    ],
    'tags' => ['premium', 'early_adopter']     // Optional
];
```

**Retorno:**
```php
[
    'id' => 'cust_123456',
    'name' => 'João Silva',
    'email' => 'joao@exemplo.com',
    'phone' => '+5511999999999',
    'document' => '123.456.789-00',
    'birth_date' => '1990-05-15',
    'gender' => 'M',
    'status' => 'active',                      // active/inactive/blocked
    'address' => [...],
    'metadata' => [...],
    'preferences' => [...],
    'tags' => ['premium', 'early_adopter'],
    'created_at' => '2025-01-16T10:00:00Z',
    'updated_at' => '2025-01-16T10:00:00Z',
    'profile' => [
        'segment' => 'high_value',
        'lifetime_value' => 0,
        'purchase_count' => 0,
        'last_purchase_at' => null
    ]
]
```

##### `getCustomer(string $customerId): ?array`

Obtém um cliente por ID.

##### `updateCustomer(string $customerId, array $updateData): array`

Atualiza dados de um cliente.

##### `deleteCustomer(string $customerId): bool`

Remove um cliente (soft delete).

##### `listCustomers(array $filters = []): array`

Lista clientes com filtros avançados.

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

// Criar cliente
$customer = $sdk->customers()->createCustomer([
    'name' => 'Maria Santos',
    'email' => 'maria@exemplo.com',
    'phone' => '+5511888888888',
    'document' => '987.654.321-00',
    'address' => [
        'street' => 'Av. Paulista, 1000',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01310-100'
    ],
    'preferences' => [
        'newsletter' => true,
        'language' => 'pt-BR'
    ],
    'tags' => ['vip', 'newsletter_subscriber']
]);

// Atualizar cliente
$updatedCustomer = $sdk->customers()->updateCustomer($customer['id'], [
    'phone' => '+5511777777777',
    'tags' => ['vip', 'newsletter_subscriber', 'premium']
]);

// Listar clientes com filtros
$customers = $sdk->customers()->listCustomers([
    'tags' => ['premium'],
    'created_after' => '2025-01-01',
    'limit' => 50,
    'offset' => 0
]);
```

#### Métodos de Matching e Deduplicação

##### `findOrCreateCustomer(array $customerData): array`

Busca cliente existente ou cria novo baseado em matching inteligente.

##### `findDuplicateCustomers(array $criteria = []): array`

Identifica clientes duplicados baseado em critérios.

##### `mergeCustomers(string $primaryCustomerId, array $duplicateCustomerIds): array`

Mescla clientes duplicados mantendo o histórico.

**Exemplo de Uso:**
```php
// Find or Create - evita duplicação automática
$customer = $sdk->customers()->findOrCreateCustomer([
    'name' => 'João Silva',
    'email' => 'joao.silva@email.com',
    'phone' => '+5511999999999'
]);

// Buscar duplicatas
$duplicates = $sdk->customers()->findDuplicateCustomers([
    'match_by' => ['email', 'document', 'phone'],
    'similarity_threshold' => 0.8
]);

foreach ($duplicates as $duplicateGroup) {
    $primaryId = $duplicateGroup['primary']['id'];
    $duplicateIds = array_column($duplicateGroup['duplicates'], 'id');

    // Mesclar duplicatas
    $mergedCustomer = $sdk->customers()->mergeCustomers($primaryId, $duplicateIds);
    echo "Clientes mesclados: {$mergedCustomer['id']}\n";
}
```

#### Métodos de Histórico

##### `getCustomerHistory(string $customerId, array $filters = []): array`

Obtém histórico completo de transações do cliente.

##### `addTransactionToHistory(string $customerId, array $transactionData): array`

Adiciona transação ao histórico do cliente.

**Exemplo de Uso:**
```php
// Adicionar transação ao histórico
$transaction = $sdk->customers()->addTransactionToHistory($customer['id'], [
    'type' => 'purchase',
    'amount' => 29900,
    'currency' => 'BRL',
    'order_id' => 'order_123',
    'product_ids' => ['prod_curso_php'],
    'payment_method' => 'credit_card',
    'status' => 'completed',
    'metadata' => [
        'channel' => 'website',
        'campaign' => 'summer_sale'
    ]
]);

// Obter histórico com filtros
$history = $sdk->customers()->getCustomerHistory($customer['id'], [
    'type' => 'purchase',
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31',
    'status' => 'completed',
    'limit' => 20
]);

foreach ($history['transactions'] as $transaction) {
    echo "Compra: R$ " . number_format($transaction['amount'] / 100, 2, ',', '.');
    echo " em " . date('d/m/Y', strtotime($transaction['created_at'])) . "\n";
}
```

#### Métodos de Perfil e Analytics

##### `getCustomerProfile(string $customerId): ?array`

Obtém perfil comportamental completo do cliente.

##### `updateCustomerProfile(string $customerId, array $profileData): array`

Atualiza perfil do cliente.

##### `calculateCustomerLifetimeValue(string $customerId): array`

Calcula o valor do tempo de vida do cliente (CLV).

##### `segmentCustomers(array $criteria): array`

Segmenta clientes baseado em critérios avançados.

##### `getCustomerBehaviorAnalysis(string $customerId): array`

Obtém análise comportamental detalhada.

##### `predictNextPurchase(string $customerId): array`

Prediz próxima compra usando algoritmos de ML.

##### `getCustomerRecommendations(string $customerId, array $options = []): array`

Obtém recomendações personalizadas para o cliente.

**Exemplo de Uso:**
```php
// Calcular CLV
$clv = $sdk->customers()->calculateCustomerLifetimeValue($customer['id']);
echo "CLV: R$ " . number_format($clv['lifetime_value'] / 100, 2, ',', '.');
echo "Predição 12 meses: R$ " . number_format($clv['predicted_12_months'] / 100, 2, ',', '.');

// Segmentação de clientes
$segments = $sdk->customers()->segmentCustomers([
    'criteria' => [
        'lifetime_value' => ['min' => 50000], // R$ 500+
        'purchase_frequency' => ['min' => 5],
        'last_purchase_days' => ['max' => 90]
    ],
    'segment_name' => 'high_value_active'
]);

// Análise comportamental
$behavior = $sdk->customers()->getCustomerBehaviorAnalysis($customer['id']);
echo "Frequência de compra: {$behavior['purchase_frequency']}\n";
echo "Produto preferido: {$behavior['preferred_category']}\n";
echo "Valor médio pedido: R$ " . number_format($behavior['average_order_value'] / 100, 2, ',', '.');

// Predição de próxima compra
$prediction = $sdk->customers()->predictNextPurchase($customer['id']);
echo "Probabilidade próxima compra (30 dias): {$prediction['probability_30_days']}%\n";
echo "Valor estimado: R$ " . number_format($prediction['estimated_value'] / 100, 2, ',', '.');

// Recomendações personalizadas
$recommendations = $sdk->customers()->getCustomerRecommendations($customer['id'], [
    'categories' => ['courses', 'ebooks'],
    'max_recommendations' => 5,
    'include_reasoning' => true
]);

foreach ($recommendations as $rec) {
    echo "Produto: {$rec['name']} - Score: {$rec['score']}\n";
    echo "Motivo: {$rec['reasoning']}\n\n";
}
```

#### Métodos de Tags

##### `addCustomerTag(string $customerId, string $tag): array`

Adiciona tag ao cliente.

##### `removeCustomerTag(string $customerId, string $tag): array`

Remove tag do cliente.

##### `findCustomersByTag(string $tag): array`

Busca clientes por tag.

**Exemplo de Uso:**
```php
// Adicionar tags
$sdk->customers()->addCustomerTag($customer['id'], 'premium');
$sdk->customers()->addCustomerTag($customer['id'], 'newsletter_subscriber');

// Buscar clientes por tag
$premiumCustomers = $sdk->customers()->findCustomersByTag('premium');

// Remover tag
$sdk->customers()->removeCustomerTag($customer['id'], 'newsletter_subscriber');
```

#### Métodos de Conformidade LGPD/GDPR

##### `exportCustomerData(string $customerId): array`

Exporta todos os dados do cliente para conformidade.

##### `anonymizeCustomerData(string $customerId): bool`

Anonimiza dados do cliente conforme LGPD.

**Exemplo de Uso:**
```php
// Exportar dados do cliente (direito de portabilidade)
$customerData = $sdk->customers()->exportCustomerData($customer['id']);

// O retorno inclui todos os dados:
// - Informações pessoais
// - Histórico de compras
// - Preferências
// - Logs de acesso
// - Dados de terceiros

// Anonimizar dados (direito ao esquecimento)
$anonymized = $sdk->customers()->anonymizeCustomerData($customer['id']);

if ($anonymized) {
    echo "Dados do cliente anonimizados com sucesso\n";
}
```

#### Services Disponíveis

##### `customers(): CustomerService`

Retorna o serviço de gestão de clientes.

**Métodos Disponíveis:**
- `create(array $data): array` - Criar cliente
- `findById(string $id): ?array` - Buscar por ID
- `update(string $id, array $data): array` - Atualizar cliente
- `delete(string $id): bool` - Excluir cliente
- `findByFilters(array $filters): array` - Buscar com filtros
- `addTag(string $id, string $tag): array` - Adicionar tag
- `removeTag(string $id, string $tag): array` - Remover tag
- `findByTag(string $tag): array` - Buscar por tag
- `getStatistics(array $filters): array` - Estatísticas
- `exportData(string $id): array` - Exportar dados
- `anonymizeData(string $id): bool` - Anonimizar dados

##### `matching(): MatchingService`

Retorna o serviço de matching e deduplicação.

**Métodos Disponíveis:**
- `findBestMatch(array $customerData): ?array` - Encontrar melhor match
- `findDuplicates(array $criteria): array` - Encontrar duplicatas
- `mergeCustomers(string $primaryId, array $duplicateIds): array` - Mesclar clientes
- `calculateSimilarity(array $customer1, array $customer2): float` - Calcular similaridade
- `setMatchingRules(array $rules): void` - Configurar regras

##### `history(): HistoryService`

Retorna o serviço de histórico.

**Métodos Disponíveis:**
- `getCustomerHistory(string $customerId, array $filters): array` - Obter histórico
- `addTransaction(string $customerId, array $data): array` - Adicionar transação
- `updateTransaction(string $transactionId, array $data): array` - Atualizar transação
- `deleteTransaction(string $transactionId): bool` - Excluir transação
- `getTransactionStats(string $customerId): array` - Estatísticas de transações

##### `profiles(): ProfileService`

Retorna o serviço de perfis e analytics.

**Métodos Disponíveis:**
- `getProfile(string $customerId): ?array` - Obter perfil
- `updateProfile(string $customerId, array $data): array` - Atualizar perfil
- `calculateLifetimeValue(string $customerId): array` - Calcular CLV
- `segmentCustomers(array $criteria): array` - Segmentar clientes
- `getBehaviorAnalysis(string $customerId): array` - Análise comportamental
- `predictNextPurchase(string $customerId): array` - Predizer compra
- `getRecommendations(string $customerId, array $options): array` - Recomendações

## 💡 Exemplos Práticos

### Sistema de CRM Completo

```php
// Implementação de CRM com o módulo Customers
class CustomerCRM
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function onboardNewCustomer($customerData)
    {
        // 1. Criar ou encontrar cliente
        $customer = $this->sdk->customers()->findOrCreateCustomer($customerData);

        // 2. Definir segmento inicial
        $this->assignInitialSegment($customer);

        // 3. Configurar preferências padrão
        $this->setupDefaultPreferences($customer['id']);

        // 4. Adicionar à sequência de boas-vindas
        $this->addToWelcomeSequence($customer['id']);

        return $customer;
    }

    public function processCustomerPurchase($customerId, $orderData)
    {
        // 1. Adicionar transação ao histórico
        $transaction = $this->sdk->customers()->addTransactionToHistory($customerId, [
            'type' => 'purchase',
            'amount' => $orderData['total'],
            'currency' => $orderData['currency'],
            'order_id' => $orderData['id'],
            'product_ids' => $orderData['product_ids'],
            'payment_method' => $orderData['payment_method'],
            'status' => 'completed'
        ]);

        // 2. Recalcular CLV
        $clv = $this->sdk->customers()->calculateCustomerLifetimeValue($customerId);

        // 3. Reavaliar segmentação
        $this->reevaluateCustomerSegment($customerId, $clv);

        // 4. Atualizar perfil comportamental
        $this->updateBehaviorProfile($customerId, $orderData);

        // 5. Gerar recomendações para próxima compra
        $recommendations = $this->sdk->customers()->getCustomerRecommendations($customerId);

        return [
            'transaction' => $transaction,
            'clv' => $clv,
            'recommendations' => $recommendations
        ];
    }

    private function assignInitialSegment($customer)
    {
        $segments = [];

        // Segmentação por fonte
        if (isset($customer['metadata']['source'])) {
            $segments[] = "source_{$customer['metadata']['source']}";
        }

        // Segmentação por região
        if (isset($customer['address']['state'])) {
            $segments[] = "region_{$customer['address']['state']}";
        }

        // Segmentação por tipo de documento
        if (isset($customer['document'])) {
            $segments[] = strlen($customer['document']) > 14 ? 'corporate' : 'individual';
        }

        foreach ($segments as $segment) {
            $this->sdk->customers()->addCustomerTag($customer['id'], $segment);
        }
    }

    private function reevaluateCustomerSegment($customerId, $clv)
    {
        // Segmentação por valor
        if ($clv['lifetime_value'] >= 100000) { // R$ 1000+
            $this->sdk->customers()->addCustomerTag($customerId, 'high_value');
        } elseif ($clv['lifetime_value'] >= 50000) { // R$ 500+
            $this->sdk->customers()->addCustomerTag($customerId, 'medium_value');
        }

        // Segmentação por frequência
        $profile = $this->sdk->customers()->getCustomerProfile($customerId);
        if ($profile['purchase_count'] >= 10) {
            $this->sdk->customers()->addCustomerTag($customerId, 'frequent_buyer');
        }
    }
}
```

### Sistema de Recomendações Avançado

```php
// Sistema de recomendações baseado em comportamento
class RecommendationEngine
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function getPersonalizedRecommendations($customerId, $context = [])
    {
        // 1. Obter perfil comportamental
        $behavior = $this->sdk->customers()->getCustomerBehaviorAnalysis($customerId);

        // 2. Obter histórico de compras
        $history = $this->sdk->customers()->getCustomerHistory($customerId, [
            'type' => 'purchase',
            'status' => 'completed',
            'limit' => 50
        ]);

        // 3. Calcular preferências
        $preferences = $this->calculatePreferences($history['transactions']);

        // 4. Obter recomendações base
        $baseRecommendations = $this->sdk->customers()->getCustomerRecommendations($customerId, [
            'max_recommendations' => 10,
            'include_reasoning' => true,
            'context' => $context
        ]);

        // 5. Aplicar filtros avançados
        $filteredRecommendations = $this->applyAdvancedFilters(
            $baseRecommendations,
            $preferences,
            $behavior
        );

        // 6. Personalizar mensagens
        return $this->personalizeRecommendations($filteredRecommendations, $behavior);
    }

    private function calculatePreferences($transactions)
    {
        $categories = [];
        $brands = [];
        $priceRanges = [];

        foreach ($transactions as $transaction) {
            // Analisar categorias
            foreach ($transaction['product_ids'] as $productId) {
                // Aqui você buscaria detalhes do produto
                // $product = $this->getProductDetails($productId);
                // $categories[$product['category']] = ($categories[$product['category']] ?? 0) + 1;
            }

            // Analisar faixas de preço
            $priceRange = $this->getPriceRange($transaction['amount']);
            $priceRanges[$priceRange] = ($priceRanges[$priceRange] ?? 0) + 1;
        }

        return [
            'preferred_categories' => array_keys(array_slice(arsort($categories) ?: [], 0, 3, true)),
            'preferred_price_range' => array_keys(array_slice(arsort($priceRanges) ?: [], 0, 1, true))[0] ?? 'medium',
            'purchase_frequency' => count($transactions) / 12 // compras por mês
        ];
    }
}
```

### Sistema de Segmentação Dinâmica

```php
// Segmentação automática de clientes
class CustomerSegmentation
{
    private $sdk;

    public function __construct($sdk)
    {
        $this->sdk = $sdk;
    }

    public function performDailySegmentation()
    {
        $segments = [
            'champions' => [
                'criteria' => [
                    'lifetime_value' => ['min' => 100000], // R$ 1000+
                    'purchase_frequency' => ['min' => 5],
                    'last_purchase_days' => ['max' => 30]
                ]
            ],
            'loyal_customers' => [
                'criteria' => [
                    'lifetime_value' => ['min' => 50000], // R$ 500+
                    'purchase_frequency' => ['min' => 3],
                    'last_purchase_days' => ['max' => 60]
                ]
            ],
            'at_risk' => [
                'criteria' => [
                    'lifetime_value' => ['min' => 25000], // R$ 250+
                    'last_purchase_days' => ['min' => 90, 'max' => 180]
                ]
            ],
            'hibernating' => [
                'criteria' => [
                    'last_purchase_days' => ['min' => 180]
                ]
            ],
            'new_customers' => [
                'criteria' => [
                    'created_days' => ['max' => 30],
                    'purchase_count' => ['max' => 1]
                ]
            ]
        ];

        $results = [];

        foreach ($segments as $segmentName => $config) {
            // Segmentar clientes
            $segmentedCustomers = $this->sdk->customers()->segmentCustomers($config);

            // Aplicar tags
            foreach ($segmentedCustomers as $customer) {
                // Remover tags de segmentos anteriores
                $this->removeOldSegmentTags($customer['id']);

                // Adicionar nova tag de segmento
                $this->sdk->customers()->addCustomerTag($customer['id'], $segmentName);
            }

            $results[$segmentName] = [
                'count' => count($segmentedCustomers),
                'total_value' => array_sum(array_column($segmentedCustomers, 'lifetime_value'))
            ];

            echo "Segmento '{$segmentName}': {$results[$segmentName]['count']} clientes\n";
        }

        return $results;
    }

    public function createCustomCampaignSegment($campaignName, $criteria)
    {
        $customers = $this->sdk->customers()->segmentCustomers([
            'criteria' => $criteria,
            'segment_name' => $campaignName
        ]);

        // Aplicar tag da campanha
        foreach ($customers as $customer) {
            $this->sdk->customers()->addCustomerTag($customer['id'], "campaign_{$campaignName}");
        }

        return [
            'segment_name' => $campaignName,
            'customer_count' => count($customers),
            'estimated_reach' => count($customers),
            'customers' => $customers
        ];
    }

    private function removeOldSegmentTags($customerId)
    {
        $segmentTags = ['champions', 'loyal_customers', 'at_risk', 'hibernating', 'new_customers'];

        foreach ($segmentTags as $tag) {
            $this->sdk->customers()->removeCustomerTag($customerId, $tag);
        }
    }
}
```

## 🔧 DTOs e Validação

### CustomerData DTO

```php
use ClubifyCheckout\Modules\Customers\DTOs\CustomerData;

$customerData = new CustomerData([
    'name' => 'Ana Costa',
    'email' => 'ana@empresa.com',
    'phone' => '+5511777777777',
    'document' => '987.654.321-00',
    'birth_date' => '1985-03-20',
    'gender' => 'F',
    'address' => [
        'street' => 'Rua Augusta, 456',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01304-001',
        'country' => 'BR'
    ],
    'preferences' => [
        'newsletter' => true,
        'sms_marketing' => false,
        'language' => 'pt-BR',
        'currency' => 'BRL',
        'communication_frequency' => 'weekly'
    ],
    'metadata' => [
        'source' => 'organic_search',
        'utm_campaign' => 'content_marketing',
        'referrer' => 'blog_post_123'
    ]
]);

// Validação automática inclui:
// - Formato de email
// - Validação de CPF/CNPJ
// - Formato de telefone
// - Validação de CEP
// - Data de nascimento
if ($customerData->isValid()) {
    $customer = $sdk->customers()->createCustomer($customerData->toArray());
}
```

### ProfileData DTO

```php
use ClubifyCheckout\Modules\Customers\DTOs\ProfileData;

$profileData = new ProfileData([
    'segment' => 'high_value',
    'lifetime_value' => 150000, // R$ 1500,00
    'purchase_count' => 8,
    'average_order_value' => 18750, // R$ 187,50
    'preferred_categories' => ['courses', 'ebooks'],
    'preferred_payment_method' => 'credit_card',
    'churn_probability' => 0.15,
    'next_purchase_probability' => 0.75,
    'recommended_products' => ['prod_123', 'prod_456'],
    'behavior_tags' => ['frequent_buyer', 'price_conscious'],
    'last_activity' => [
        'type' => 'purchase',
        'date' => '2025-01-15T14:30:00Z',
        'value' => 29900
    ]
]);
```

### HistoryData DTO

```php
use ClubifyCheckout\Modules\Customers\DTOs\HistoryData;

$historyData = new HistoryData([
    'type' => 'purchase',                      // purchase/refund/subscription/cancellation
    'amount' => 29900,
    'currency' => 'BRL',
    'order_id' => 'order_123',
    'product_ids' => ['prod_curso_react'],
    'payment_method' => 'credit_card',
    'status' => 'completed',
    'channel' => 'website',
    'metadata' => [
        'campaign' => 'spring_promo',
        'coupon_used' => 'SPRING20',
        'affiliate_id' => 'aff_456'
    ]
]);
```

## 📊 Relatórios e Analytics

### Estatísticas de Clientes

```php
// Relatório geral de clientes
$stats = $sdk->customers()->getCustomerStatistics([
    'start_date' => '2025-01-01',
    'end_date' => '2025-01-31'
]);

echo "Total de clientes: {$stats['total_customers']}\n";
echo "Novos clientes: {$stats['new_customers']}\n";
echo "CLV médio: R$ " . number_format($stats['average_clv'] / 100, 2, ',', '.') . "\n";
echo "Taxa de retenção: {$stats['retention_rate']}%\n";

// Análise por segmento
foreach ($stats['segment_breakdown'] as $segment => $data) {
    echo "\nSegmento: {$segment}\n";
    echo "Clientes: {$data['count']}\n";
    echo "Valor total: R$ " . number_format($data['total_value'] / 100, 2, ',', '.') . "\n";
    echo "CLV médio: R$ " . number_format($data['average_clv'] / 100, 2, ',', '.') . "\n";
}
```

### Análise de Coorte

```php
// Análise de coorte por período de cadastro
class CohortAnalysis
{
    private $sdk;

    public function generateCohortReport($startDate, $endDate)
    {
        $customers = $sdk->customers()->listCustomers([
            'created_after' => $startDate,
            'created_before' => $endDate,
            'include_history' => true
        ]);

        $cohorts = [];

        foreach ($customers as $customer) {
            $cohortMonth = date('Y-m', strtotime($customer['created_at']));

            if (!isset($cohorts[$cohortMonth])) {
                $cohorts[$cohortMonth] = [
                    'customers' => [],
                    'month_0' => 0, // Mês de cadastro
                    'month_1' => 0, // 1 mês depois
                    'month_3' => 0, // 3 meses depois
                    'month_6' => 0, // 6 meses depois
                    'month_12' => 0 // 12 meses depois
                ];
            }

            $cohorts[$cohortMonth]['customers'][] = $customer;
            $cohorts[$cohortMonth]['month_0']++;

            // Analisar atividade em períodos posteriores
            $this->analyzeCohortActivity($customer, $cohorts[$cohortMonth]);
        }

        return $cohorts;
    }
}
```

## 🔍 Monitoramento e Logs

### Health Check

```php
// Verificar saúde do módulo
$healthCheck = $sdk->customers()->healthCheck();

echo "Status do módulo: {$healthCheck['status']}\n";
echo "Repositório: {$healthCheck['repository']}\n";
echo "Cache: {$healthCheck['cache']}\n";

foreach ($healthCheck['services'] as $service => $status) {
    echo "Serviço {$service}: {$status}\n";
}
```

### Logs de Conformidade

```php
// Os logs de conformidade são gerados automaticamente:

/*
[2025-01-16 10:30:00] INFO: Cliente criado
{
    "customer_id": "cust_123456",
    "email_hash": "sha256_hash",
    "source": "website",
    "gdpr_consent": true
}

[2025-01-16 14:20:00] PRIVACY: Dados exportados
{
    "customer_id": "cust_123456",
    "request_type": "data_export",
    "requester_ip": "192.168.1.100",
    "data_types": ["personal", "transactions", "preferences"]
}

[2025-01-16 16:45:00] PRIVACY: Dados anonimizados
{
    "customer_id": "cust_123456",
    "request_type": "data_anonymization",
    "reason": "customer_request",
    "retention_period_expired": false
}
*/
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Customers\Exceptions\CustomerException;
use ClubifyCheckout\Modules\Customers\Exceptions\CustomerNotFoundException;
use ClubifyCheckout\Modules\Customers\Exceptions\DuplicateCustomerException;

try {
    $customer = $sdk->customers()->createCustomer($customerData);
} catch (DuplicateCustomerException $e) {
    echo "Cliente já existe: " . $e->getMessage();
    // Sugerir merge ou atualização
} catch (CustomerNotFoundException $e) {
    echo "Cliente não encontrado: " . $e->getMessage();
    // Verificar ID ou criar novo
} catch (CustomerException $e) {
    echo "Erro no cliente: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Customers
CLUBIFY_CUSTOMERS_CACHE_TTL=1800
CLUBIFY_CUSTOMERS_ENABLE_MATCHING=true
CLUBIFY_CUSTOMERS_MATCHING_THRESHOLD=0.8
CLUBIFY_CUSTOMERS_ENABLE_ANALYTICS=true
CLUBIFY_CUSTOMERS_GDPR_COMPLIANCE=true
CLUBIFY_CUSTOMERS_AUTO_SEGMENTATION=true
```

### Configuração Avançada

```php
$config = [
    'customers' => [
        'cache_ttl' => 1800,
        'enable_matching' => true,
        'enable_analytics' => true,
        'gdpr_compliance' => true,

        'matching' => [
            'threshold' => 0.8,
            'match_fields' => ['email', 'document', 'phone'],
            'fuzzy_matching' => true,
            'auto_merge' => false
        ],

        'analytics' => [
            'enable_clv_calculation' => true,
            'enable_predictions' => true,
            'enable_recommendations' => true,
            'update_frequency' => 'daily'
        ],

        'privacy' => [
            'gdpr_compliance' => true,
            'data_retention_days' => 2555, // 7 anos
            'auto_anonymization' => false,
            'consent_tracking' => true
        ],

        'segmentation' => [
            'auto_segmentation' => true,
            'custom_segments' => [
                'vip' => [
                    'lifetime_value' => ['min' => 200000], // R$ 2000+
                    'purchase_frequency' => ['min' => 10]
                ]
            ]
        ]
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de qualidade enterprise e conformidade LGPD/GDPR.**