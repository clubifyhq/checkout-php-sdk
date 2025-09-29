# Organization API Keys - Guia Completo de Implementação

Este guia fornece instruções detalhadas para implementar e usar Organization API Keys no Clubify Checkout SDK.

## 📋 Visão Geral

Organization API Keys introduzem uma nova camada de autenticação que permite acesso multi-tenant e operações administrativas avançadas. O sistema suporta três escopos principais:

- **ORGANIZATION**: Acesso completo a todos os tenants da organização
- **CROSS_TENANT**: Acesso específico a tenants selecionados
- **TENANT**: Acesso restrito a um único tenant (compatibilidade legacy)

## 🚀 Configuração Inicial

### 1. Configuração do Laravel (.env)

```bash
# Organization Identity
CLUBIFY_CHECKOUT_ORGANIZATION_ID=org_example_123456789
CLUBIFY_CHECKOUT_ORGANIZATION_NAME="Example Organization Ltd"

# Organization API Keys (different scopes)
CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY=org_live_1234567890abcdef1234567890abcdef
CLUBIFY_CHECKOUT_CROSS_TENANT_API_KEY=ct_live_fedcba0987654321fedcba0987654321
CLUBIFY_CHECKOUT_TENANT_API_KEY=tenant_live_abcd1234efgh5678ijkl9012mnop3456

# Authentication Configuration
CLUBIFY_CHECKOUT_ORG_AUTH_MODE=api_key
CLUBIFY_CHECKOUT_ORG_DEFAULT_SCOPE=ORGANIZATION
CLUBIFY_CHECKOUT_ORG_AUTO_DETECT_TENANT=true
```

### 2. Configuração do SDK

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'organization_id' => env('CLUBIFY_CHECKOUT_ORGANIZATION_ID'),
    'api_key' => env('CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY'),
    'scope' => 'ORGANIZATION',
    'environment' => 'sandbox'
]);
```

## 🎯 Cenários de Uso

### Cenário 1: Autenticação Básica de Organização

**Ideal para**: Dashboard administrativo, relatórios gerais, backup de dados

```php
// Autenticar como organização
$authResult = $sdk->authenticateAsOrganization([
    'organization_id' => 'org_example_123456789',
    'api_key' => 'org_live_...'
]);

if ($authResult['success']) {
    // Acessar dashboard
    $dashboard = $sdk->organization()->getDashboard([
        'period' => '30d',
        'include_tenants' => true
    ]);

    // Fazer backup de configurações
    $backup = $sdk->organization()->exportConfiguration([
        'include_tenants' => true,
        'format' => 'json'
    ]);
}
```

### Cenário 2: Uso Multi-Tenant (Franquias)

**Ideal para**: Gestão de múltiplas lojas, consolidação de dados

```php
$multiSdk = new ClubifyCheckoutSDK([
    'organization_id' => 'org_example_123456789',
    'api_key' => 'ct_live_...',  // Cross-tenant key
    'scope' => 'CROSS_TENANT'
]);

// Obter dados de todas as lojas
foreach ($authResult['allowed_tenants'] as $tenant) {
    $sdk->organization()->switchToTenant($tenant['id']);

    $sales = $sdk->orders()->getStatistics(['period' => '7d']);
    echo "Loja {$tenant['name']}: R$ " . number_format($sales['revenue']/100, 2);
}
```

### Cenário 3: Operações Administrativas

**Ideal para**: DevOps, auditoria, gestão de credenciais

```php
// Auditoria de API Keys
$audit = $sdk->organization()->auditApiKeys([
    'include_usage_stats' => true,
    'check_security' => true
]);

// Health check do sistema
$health = $sdk->organization()->healthCheck([
    'deep_check' => true,
    'include_tenants' => true
]);

// Configurar monitoramento
$monitoring = $sdk->organization()->setupMonitoring([
    'alerts' => [
        'high_error_rate' => ['threshold' => 5],
        'slow_response' => ['threshold' => 2000]
    ]
]);
```

## 📊 Endpoints da API

### Autenticação

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/auth/organization` | Autenticar como organização |

### Gestão de API Keys

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| POST | `/organizations/{id}/api-keys` | Gerar nova API key |
| GET | `/organizations/{id}/api-keys` | Listar API keys |
| POST | `/organizations/{id}/api-keys/validate` | Validar API key |
| PUT | `/organizations/{id}/api-keys/{keyId}` | Atualizar API key |
| DELETE | `/organizations/{id}/api-keys/{keyId}` | Revogar API key |

### Operações da Organização

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/organization/tenants` | Listar tenants |
| POST | `/organization/switch-tenant` | Alternar contexto |
| GET | `/organization/dashboard` | Dashboard da organização |

## 🧪 Testando com Postman

1. Importe a collection: `Clubify-Organization-API-Keys.postman_collection.json`
2. Configure as variáveis de ambiente:
   - `base_url`: https://checkout.svelve.com/api/v1
   - `organization_id`: Seu organization ID
   - `organization_api_key`: Sua API key da organização
3. Execute os requests na ordem dos folders

### Variáveis da Collection

```json
{
  "base_url": "https://checkout.svelve.com/api/v1",
  "organization_id": "org_example_123456789",
  "organization_api_key": "org_live_...",
  "cross_tenant_api_key": "ct_live_...",
  "tenant_api_key": "tenant_live_..."
}
```

## 🔒 Segurança e Boas Práticas

### Configuração de Rate Limiting

```json
{
  "rateLimit": {
    "requests": 1000,
    "window": 3600,
    "burst": 100
  }
}
```

### Controle de Domínios

```json
{
  "allowedDomains": [
    "*.example.com",
    "admin.mystore.com"
  ]
}
```

### Rotação Automática

```json
{
  "autoRotate": true,
  "rotationInterval": 90
}
```

## 📈 Monitoramento e Alertas

### Configuração de Alertas

```php
$alerts = [
    'high_error_rate' => ['threshold' => 5, 'window' => '5m'],
    'slow_response' => ['threshold' => 2000, 'window' => '1m'],
    'quota_exceeded' => ['threshold' => 90, 'window' => '1h']
];
```

### Webhooks de Monitoramento

```php
$webhooks = [
    'endpoint' => 'https://your-monitoring.com/webhooks/clubify',
    'events' => ['alert.triggered', 'system.error', 'quota.warning']
];
```

## 🚨 Troubleshooting

### Problemas Comuns

1. **Erro 401 - Unauthorized**
   - Verifique se a API key está correta
   - Confirme o scope da key (ORGANIZATION, CROSS_TENANT, TENANT)
   - Verifique se a organização está ativa

2. **Erro 403 - Forbidden**
   - Verifique as permissões da API key
   - Confirme se o tenant solicitado está nos allowed_tenants
   - Verifique IP whitelist se configurado

3. **Erro 429 - Rate Limit Exceeded**
   - Reduza a frequência de requests
   - Verifique os limites configurados
   - Implemente backoff exponential

### Health Check

```php
$health = $sdk->organization()->healthCheck([
    'deep_check' => true,
    'include_tenants' => true
]);

// Verificar status
foreach ($health['tenants_status'] as $tenant => $status) {
    if ($status !== 'healthy') {
        echo "⚠️ Tenant $tenant tem problemas: $status";
    }
}
```

## 📚 Recursos Adicionais

### Arquivos de Exemplo

- `laravel-complete-example.php`: Exemplo completo integrado com Laravel
- `organization-usage-scenarios.php`: 3 cenários principais de uso
- `organization-api-keys-example.php`: Exemplos básicos de API keys

### Collection do Postman

- `Clubify-Organization-API-Keys.postman_collection.json`: Collection completa

### Configuração

- `.env`: Configuração de ambiente do Laravel
- `config/clubify-checkout.php`: Configuração do SDK

## 🤝 Suporte

Para suporte adicional:

1. Consulte a documentação completa da API
2. Verifique os logs de debug no Laravel
3. Use os endpoints de health check para diagnostics
4. Entre em contato com o suporte técnico

## 📝 Changelog

### v1.0.0
- Implementação inicial dos Organization API Keys
- Suporte para escopos ORGANIZATION, CROSS_TENANT e TENANT
- Integração com Laravel example app
- Collection do Postman
- Exemplos de uso práticos

---

**Nota**: Este guia está em constante atualização. Consulte a documentação oficial para as informações mais recentes.