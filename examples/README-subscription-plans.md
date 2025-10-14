# Exemplo: Gerenciamento Idempotente de Planos de Assinatura

## Visão Geral

Este exemplo demonstra como usar o SDK PHP do Clubify Checkout para gerenciar planos de assinatura de forma idempotente. O script pode ser executado múltiplas vezes sem criar duplicatas ou causar erros.

## Características

✅ **Idempotente**: Verifica se o plano existe antes de criar
✅ **Atualização Inteligente**: Atualiza apenas campos que mudaram
✅ **Tratamento de Erros**: Captura e reporta erros de forma clara
✅ **Autenticação via API Key**: Usa credenciais do tenant
✅ **Fácil de Usar**: Basta configurar as credenciais e executar

## Pré-requisitos

- PHP 8.2 ou superior
- Composer instalado
- SDK Clubify Checkout instalado (`composer require clubify/checkout-sdk-php`)
- Credenciais válidas do tenant:
  - API Key
  - Tenant ID
  - Organization ID

## Instalação

1. Clone o repositório ou navegue até o diretório do SDK:
```bash
cd sdk/checkout/php
```

2. Instale as dependências:
```bash
composer install
```

## Configuração

### Opção 1: Variáveis de Ambiente (Recomendado)

Crie um arquivo `.env` ou exporte as variáveis:

```bash
export CLUBIFY_API_KEY='sua-api-key-aqui'
export CLUBIFY_TENANT_ID='seu-tenant-id-aqui'
export CLUBIFY_ORGANIZATION_ID='seu-organization-id-aqui'
export CLUBIFY_ENVIRONMENT='sandbox'
export CLUBIFY_BASE_URL='https://checkout.svelve.com/api/v1'
```

### Opção 2: Editar o Código

Edite o arquivo `examples/subscription-plan-management.php` e atualize o array `$config`:

```php
$config = [
    'api_key' => 'sua-api-key-aqui',
    'tenant_id' => 'seu-tenant-id-aqui',
    'organization_id' => 'seu-organization-id-aqui',
    'environment' => 'sandbox',
    'base_url' => 'https://checkout.svelve.com/api/v1',
    'debug' => true
];
```

## Como Obter as Credenciais

### API Key e Tenant ID

1. Acesse o dashboard do Clubify Checkout
2. Navegue até **Configurações > API Keys**
3. Crie uma nova API Key ou use uma existente
4. Copie o **API Key** e o **Tenant ID**

### Organization ID

1. No dashboard, vá até **Organização > Detalhes**
2. Copie o **Organization ID**

Ou use o endpoint da API:
```bash
curl -X GET https://checkout.svelve.com/api/v1/organization \
  -H "Authorization: Bearer sua-api-key" \
  -H "x-tenant-id: seu-tenant-id"
```

## Executando o Exemplo

```bash
php examples/subscription-plan-management.php
```

### Saída Esperada

```
╔════════════════════════════════════════════════════════════════════╗
║   Clubify Checkout SDK - Gerenciamento de Planos de Assinatura   ║
╚════════════════════════════════════════════════════════════════════╝

🔧 Inicializando SDK...
✅ SDK inicializado com sucesso

┌────────────────────────────────────────────────────────────────────┐
│  EXEMPLO 1: Criar/Atualizar Planos de Assinatura (Idempotente)    │
└────────────────────────────────────────────────────────────────────┘

======================================================================
📋 Processando plano: Plano Básico
======================================================================

🔍 Verificando se o plano já existe...
➕ Plano não existe, criando novo...

📝 Criando novo plano...
✅ Plano criado com sucesso!
   ID: plan_abc123
   Nome: Plano Básico
   Valor: R$ 29,90
   Ciclo: monthly
   MRR: R$ 29,90

...
```

## O que o Script Faz

### 1. Inicialização
- Configura o SDK com as credenciais fornecidas
- Valida a conexão com a API
- Inicializa os módulos necessários

### 2. Criação/Atualização de Planos
O script cria 3 planos de exemplo:

#### Plano Básico (R$ 29,90/mês)
- 10 usuários
- Analytics básico
- Suporte por email
- 5GB de armazenamento
- 7 dias de trial

#### Plano Premium (R$ 99,90/mês)
- Usuários ilimitados
- Analytics avançado
- Suporte prioritário
- 50GB de armazenamento
- Integrações customizadas
- Acesso à API
- 14 dias de trial

#### Plano Enterprise (R$ 299,90/mês)
- Tudo ilimitado
- Analytics corporativo
- Suporte dedicado
- Armazenamento ilimitado
- SSO
- Segurança avançada
- Onboarding customizado
- SLA garantido
- 30 dias de trial

### 3. Listagem de Planos
- Lista todos os planos ativos
- Exibe detalhes de cada plano

### 4. Métricas
- Obtém métricas de um plano específico
- Exibe assinaturas ativas, MRR, ARR, churn, etc.

### 5. Teste de Idempotência
- Executa novamente a criação de um plano
- Verifica que não cria duplicatas
- Atualiza apenas campos alterados

## Estrutura do Código

```php
class SubscriptionPlanManager
{
    // Construtor - inicializa o SDK
    __construct(array $config)

    // Método principal - idempotente
    createOrUpdatePlan(array $planData): array

    // Busca plano pelo nome
    findPlanByName(string $planName): ?array

    // Cria novo plano
    createNewPlan(array $planData): array

    // Atualiza plano existente
    updateExistingPlan(array $existingPlan, array $newData): array

    // Lista todos os planos
    listPlans(array $filters = []): array

    // Ativa/desativa planos
    activatePlan(string $planId): bool
    deactivatePlan(string $planId): bool

    // Obtém métricas
    getPlanMetrics(string $planId): array
}
```

## Estrutura de Dados do Plano

```php
[
    'name' => 'Nome do Plano',              // Obrigatório
    'description' => 'Descrição detalhada', // Opcional
    'amount' => 99.90,                      // Obrigatório (float)
    'currency' => 'BRL',                    // Obrigatório
    'billing_cycle' => 'monthly',           // Obrigatório: daily, weekly, monthly, quarterly, yearly
    'trial_days' => 14,                     // Opcional (0 = sem trial)
    'is_active' => true,                    // Opcional (default: true)
    'features' => [                         // Opcional
        'feature_1',
        'feature_2'
    ],
    'metadata' => [                         // Opcional
        'tier' => 'premium',
        'custom_field' => 'value'
    ]
]
```

## Análise da API de Subscription Plans

### Endpoints Disponíveis

Com base na investigação do código da API (`apps/subscription-service/`):

#### POST /api/v1/subscription-plans
Cria um novo plano de assinatura.

**Headers Obrigatórios:**
```
Authorization: Bearer {api-key}
x-organization-id: {organization-id}
x-tenant-id: {tenant-id}
Content-Type: application/json
```

**Body:**
```json
{
  "name": "Plano Premium",
  "description": "Plano completo para empresas",
  "tier": "premium",
  "prices": [
    {
      "name": "Mensal",
      "amount": 9990,
      "currency": "BRL",
      "interval": "monthly",
      "intervalCount": 1,
      "isActive": true
    }
  ],
  "features": [
    {
      "name": "API Access",
      "description": "Acesso completo à API",
      "enabled": true,
      "limit": 10000
    }
  ],
  "gatewayProductId": "prod_xxx",
  "gatewayName": "pagarme",
  "defaultTrialDays": 14,
  "isActive": true,
  "metadata": {}
}
```

#### GET /api/v1/subscription-plans
Lista planos com filtros e paginação.

**Query Parameters:**
- `active` (boolean): Filtrar por status ativo
- `tier` (string): Filtrar por tier (basic, standard, premium, enterprise)
- `search` (string): Buscar por nome ou descrição
- `page` (number): Página (default: 1)
- `limit` (number): Itens por página (default: 20)
- `sortBy` (string): Campo de ordenação (default: createdAt)
- `sortOrder` (string): asc ou desc (default: desc)

#### GET /api/v1/subscription-plans/active
Retorna apenas planos ativos (endpoint simplificado).

#### GET /api/v1/subscription-plans/:id
Busca plano por ID.

#### PUT /api/v1/subscription-plans/:id
Atualiza um plano existente.

**Body (todos opcionais):**
```json
{
  "name": "Novo Nome",
  "description": "Nova descrição",
  "tier": "premium",
  "prices": [...],
  "features": [...],
  "defaultTrialDays": 30,
  "isActive": true
}
```

#### DELETE /api/v1/subscription-plans/:id
Desativa o plano (soft delete).

### Estrutura de Dados Completa da API

```typescript
// DTO do Plano
interface SubscriptionPlan {
  _id: string;
  organizationId: string;
  tenantId: string;
  name: string;
  description?: string;
  tier: 'basic' | 'standard' | 'premium' | 'enterprise';

  prices: PlanPrice[];
  features: PlanFeature[];

  gatewayProductId: string;
  gatewayName: string;

  defaultTrialDays: number;
  isActive: boolean;

  metadata: Record<string, any>;

  createdAt: Date;
  updatedAt: Date;
  createdBy: string;
  lastModifiedBy: string;
}

// DTO de Preço
interface PlanPrice {
  name: string;
  amount: number;          // Em centavos
  currency: string;        // ISO 4217 (BRL, USD, etc)
  interval: 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'semi_annually' | 'annually';
  intervalCount: number;   // Ex: 3 meses = interval: monthly, intervalCount: 3
  gatewayPriceId?: string;
  isActive: boolean;
}

// DTO de Feature
interface PlanFeature {
  name: string;
  description?: string;
  enabled: boolean;
  limit?: number;
  type?: string;
}
```

## Como o SDK Funciona

### 1. Autenticação

O SDK usa os headers HTTP para autenticação:

```php
Headers:
  Authorization: Bearer {api_key}
  X-Tenant-Id: {tenant_id}
  X-Organization-Id: {organization_id}
  Content-Type: application/json
```

Estes headers são configurados automaticamente pelo `Core\Http\Client` através do `Configuration::getDefaultHeaders()`.

### 2. Módulo de Subscriptions

O SDK possui um módulo dedicado para subscriptions:

```php
// Estrutura do módulo
src/Modules/Subscriptions/
├── Services/
│   ├── SubscriptionPlanService.php
│   ├── SubscriptionService.php
│   ├── BillingService.php
│   ├── SubscriptionMetricsService.php
│   └── SubscriptionLifecycleService.php
├── DTOs/
│   ├── SubscriptionPlanData.php
│   └── SubscriptionData.php
├── Repositories/
│   └── ApiSubscriptionRepository.php
└── SubscriptionsModule.php
```

### 3. Fluxo de Criação de Plano

```
1. App chama: $sdk->subscriptions()->createPlan($data)
   ↓
2. SubscriptionsModule.createPlan()
   ↓
3. SubscriptionPlanService.createPlan()
   ↓
4. Cria SubscriptionPlanData (DTO validado)
   ↓
5. Logger registra a operação
   ↓
6. Retorna resposta com plan_id e MRR calculado
```

**Nota:** O código atual do SDK não faz chamadas HTTP reais para a API, mas retorna dados simulados. Para usar a API real, seria necessário implementar as chamadas HTTP no `SubscriptionPlanService` usando o `Core\Http\Client`.

## Limitações Conhecidas

### SDK Atual

⚠️ **Importante**: A implementação atual do `SubscriptionPlanService` no SDK retorna dados **mockados/simulados**. As seguintes limitações existem:

1. **Sem persistência real**: Os planos não são salvos no banco de dados
2. **Dados simulados**: `listPlans()` retorna sempre os mesmos 3 planos de exemplo
3. **IDs gerados localmente**: Usa `uniqid('plan_')` ao invés de IDs do banco

### Para Usar a API Real

Para conectar à API real de subscription plans, seria necessário:

1. Modificar o `SubscriptionPlanService` para usar o `ApiSubscriptionRepository`
2. Implementar os métodos HTTP no repository
3. Mapear os DTOs corretamente

Exemplo de como deveria ser:

```php
// No SubscriptionPlanService
public function createPlan(array $planData): array
{
    try {
        // Usar repository para fazer chamada HTTP real
        $response = $this->repository->create($planData);

        return [
            'success' => true,
            'plan' => $response,
            'mrr' => $this->calculateMRR($response)
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
```

## Troubleshooting

### Erro: "Authentication failed"

**Problema**: API Key inválida ou headers ausentes

**Solução**:
1. Verifique se a API Key está correta
2. Confirme que o Tenant ID e Organization ID estão corretos
3. Verifique se os headers `x-tenant-id` e `x-organization-id` estão sendo enviados

### Erro: "Plan with name already exists"

**Problema**: Tentando criar plano com nome duplicado

**Solução**: O script é idempotente e já trata isso automaticamente. Se o plano existe, ele é atualizado ao invés de criar novo.

### Erro: "Failed to connect to API"

**Problema**: Não consegue conectar ao servidor

**Solução**:
1. Verifique a URL da API (`base_url`)
2. Confirme que o ambiente está correto (sandbox/production)
3. Verifique conectividade de rede

### Debug Mode

Para ativar logs detalhados:

```bash
export CLUBIFY_DEBUG=true
php examples/subscription-plan-management.php
```

## Próximos Passos

Após criar os planos, você pode:

1. **Criar Assinaturas**: Use os IDs dos planos para criar assinaturas para clientes
2. **Configurar Webhooks**: Monitore eventos de assinatura (criação, cancelamento, etc)
3. **Implementar Checkout**: Crie sessões de checkout vinculadas aos planos
4. **Monitorar Métricas**: Acompanhe MRR, ARR, churn e outras métricas

## Documentação Adicional

- [SDK PHP - Documentação Completa](../README.md)
- [API Reference](../docs/api-reference.md)
- [Guia de Integração](../docs/INTEGRATION_EXAMPLES.md)
- [Exemplos Práticos](../docs/examples/PRACTICAL_EXAMPLES.md)

## Suporte

Para dúvidas ou problemas:

- 📧 Email: suporte@clubify.com
- 📚 Documentação: https://docs.clubify.com
- 🐛 Issues: https://github.com/clubifyhq/checkout-sdk-php/issues

## Licença

MIT License - Copyright (c) 2025 Clubify
