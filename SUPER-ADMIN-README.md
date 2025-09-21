# Super Admin - Clubify Checkout SDK PHP

Este documento descreve como usar o SDK em modo Super Admin para gerenciar múltiplos tenants e organizações.

## 📋 Visão Geral

O modo Super Admin permite:
- ✅ Criar e gerenciar organizações
- ✅ Gerenciar tenants e suas credenciais
- ✅ Alternar entre contextos (super admin ↔ tenant admin)
- ✅ Supervisionar o sistema completo
- ✅ Manter compatibilidade com modo single-tenant

## 🚀 Inicialização Super Admin

### Configuração Básica

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK();

// Credenciais do super admin
$superAdminCredentials = [
    'api_key' => 'clb_live_super_admin_key',
    'access_token' => 'super_admin_access_token',
    'refresh_token' => 'super_admin_refresh_token',
    'username' => 'super_admin@clubify.com',
    'password' => 'super_admin_password'
];

// Inicializar como super admin
$result = $sdk->initializeAsSuperAdmin($superAdminCredentials);
```

### Resposta da Inicialização

```php
[
    'success' => true,
    'mode' => 'super_admin',
    'authenticated' => true,
    'role' => 'super_admin',
    'timestamp' => '2024-01-15T10:30:00+00:00'
]
```

## 🏢 Criação de Organizações

### Criar Nova Organização

```php
$organizationData = [
    'name' => 'Nova Empresa Ltda',
    'admin_email' => 'admin@novaempresa.com',
    'admin_name' => 'João Admin',
    'subdomain' => 'novaempresa',
    'custom_domain' => 'checkout.novaempresa.com',
    'settings' => [
        'timezone' => 'America/Sao_Paulo',
        'currency' => 'BRL',
        'language' => 'pt-BR'
    ],
    'features' => [
        'payments' => true,
        'subscriptions' => true,
        'webhooks' => true
    ]
];

$organization = $sdk->createOrganization($organizationData);
```

### Resposta da Criação

```php
[
    'organization' => [
        'id' => 'org_abc123',
        'name' => 'Nova Empresa Ltda',
        'slug' => 'nova-empresa-ltda',
        'status' => 'active'
    ],
    'tenant' => [
        'id' => 'tenant_xyz789',
        'organization_id' => 'org_abc123',
        'api_key' => 'clb_live_tenant_key',
        'subdomain' => 'novaempresa'
    ],
    'admin' => [
        'id' => 'user_admin123',
        'name' => 'João Admin',
        'email' => 'admin@novaempresa.com',
        'role' => 'tenant_admin'
    ],
    'credentials' => [
        'access_token' => 'tenant_access_token',
        'refresh_token' => 'tenant_refresh_token'
    ]
]
```

## 🔄 Alternância de Contextos

### Alternar para Tenant Específico

```php
$tenantId = 'tenant_xyz789';

// Alternar para tenant
$sdk->switchToTenant($tenantId);

// Verificar contexto atual
$context = $sdk->getCurrentContext();
```

### Alternar de Volta para Super Admin

```php
// Voltar para super admin
$sdk->switchToSuperAdmin();

// Verificar contexto
$context = $sdk->getCurrentContext();
```

### Verificar Contexto Atual

```php
$context = $sdk->getCurrentContext();

// Modo super admin
[
    'mode' => 'super_admin',
    'current_role' => 'super_admin',
    'available_contexts' => [
        'total_contexts' => 3,
        'active_context' => 'super_admin',
        'contexts' => [
            'super_admin' => [...],
            'tenant_xyz789' => [...],
            'tenant_abc123' => [...]
        ]
    ]
]

// Modo single tenant (compatibilidade)
[
    'mode' => 'single_tenant',
    'tenant_id' => 'tenant_xyz789',
    'role' => 'tenant_admin'
]
```

## 👥 Gerenciamento de Tenants

### Listar Todos os Tenants

```php
// Listar todos
$tenants = $sdk->superAdmin()->listTenants();

// Listar com filtros
$filteredTenants = $sdk->superAdmin()->listTenants([
    'status' => 'active',
    'limit' => 10,
    'offset' => 0
]);
```

### Obter Credenciais de Tenant

```php
$tenantId = 'tenant_xyz789';
$credentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
```

### Regenerar API Key

```php
$tenantId = 'tenant_xyz789';
$newCredentials = $sdk->superAdmin()->regenerateApiKey($tenantId);
```

### Suspender/Reativar Tenant

```php
// Suspender
$sdk->superAdmin()->suspendTenant($tenantId, 'Violação de termos');

// Reativar
$sdk->superAdmin()->reactivateTenant($tenantId);
```

## 📊 Monitoramento e Estatísticas

### Estatísticas do Sistema

```php
$stats = $sdk->superAdmin()->getSystemStats();

[
    'organizations' => [
        'total' => 150,
        'active' => 142,
        'suspended' => 8
    ],
    'tenants' => [
        'total' => 150,
        'active' => 142
    ],
    'users' => [
        'total' => 1250,
        'active_last_30_days' => 890
    ],
    'transactions' => [
        'total_volume' => 2500000.00,
        'last_30_days' => 450000.00
    ]
]
```

## 🔧 Operações como Tenant Admin

Após alternar para um tenant, todas as operações funcionam normalmente:

```php
// Alternar para tenant
$sdk->switchToTenant($tenantId);

// Operações normais do SDK
$products = $sdk->products()->list();
$customers = $sdk->customers()->list();
$checkout = $sdk->checkout()->createSession($sessionData);

// Voltar para super admin quando necessário
$sdk->switchToSuperAdmin();
```

## 🔐 Segurança e Validações

### Validações Automáticas

- ✅ Formato de API keys
- ✅ Permissões de role
- ✅ Contexto adequado para operações
- ✅ Validação de dados de entrada

### Exemplo de Validação de Role

```php
// Operações que requerem super admin
try {
    $sdk->createOrganization($data); // ✅ Só funciona em modo super admin
} catch (SDKException $e) {
    echo "Erro: " . $e->getMessage(); // "SDK must be in super_admin mode"
}
```

## 📝 DTOs Disponíveis

### SuperAdminCredentials

```php
use Clubify\Checkout\Modules\SuperAdmin\DTOs\SuperAdminCredentials;

$credentials = SuperAdminCredentials::fromArray([
    'api_key' => 'clb_live_super_admin_key',
    'access_token' => 'access_token',
    'refresh_token' => 'refresh_token',
    'username' => 'admin@clubify.com',
    'password' => 'password'
]);
```

### TenantCreationData

```php
use Clubify\Checkout\Modules\SuperAdmin\DTOs\TenantCreationData;

$tenantData = TenantCreationData::fromArray([
    'organization_name' => 'Nova Empresa',
    'admin_email' => 'admin@empresa.com',
    'admin_name' => 'Admin User',
    'subdomain' => 'empresa',
    'settings' => [...],
    'features' => [...]
]);
```

### OrganizationData

```php
use Clubify\Checkout\Modules\SuperAdmin\DTOs\OrganizationData;

$orgData = OrganizationData::fromArray([
    'name' => 'Empresa Ltda',
    'slug' => 'empresa-ltda',
    'description' => 'Descrição da empresa',
    'industry' => 'technology',
    'country' => 'BR',
    'website' => 'https://empresa.com'
]);
```

## 🔄 Retrocompatibilidade

O SDK mantém total compatibilidade com o modo single-tenant:

```php
// Modo atual (single-tenant) - continua funcionando
$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'tenant_id' => 'tenant_123',
        'api_key' => 'clb_live_tenant_key'
    ]
]);

$sdk->initialize(); // Funciona como sempre
```

## ⚠️ Considerações Importantes

### Segurança

1. **Credenciais Super Admin**: Devem ser armazenadas de forma segura
2. **Logs de Auditoria**: Todas as operações de super admin são logadas
3. **Rate Limiting**: Operações de criação têm rate limiting
4. **Validação de Permissões**: Cada operação valida o role adequado

### Performance

1. **Lazy Loading**: Módulos são carregados sob demanda
2. **Cache**: Credenciais são cacheadas para evitar re-autenticação
3. **Context Switching**: Troca de contexto é otimizada

### Boas Práticas

1. **Sempre verificar contexto** antes de operações sensíveis
2. **Usar DTOs** para validação automática de dados
3. **Implementar logs** para auditoria de operações
4. **Testar alternância** de contextos em ambiente de desenvolvimento

## 📚 Exemplos Completos

Veja os exemplos completos em:
- `examples/super-admin-example.php` - Exemplo completo de uso
- `examples/organization-creation.php` - Foco na criação de organizações
- `examples/tenant-management.php` - Gerenciamento de tenants

## 🆘 Troubleshooting

### Erro: "SDK must be in super_admin mode"

```php
// Verificar se está em modo super admin
if (!$sdk->isSuperAdminMode()) {
    echo "SDK não está em modo super admin";
}
```

### Erro: "Context not found"

```php
// Verificar contextos disponíveis
$context = $sdk->getCurrentContext();
var_dump($context['available_contexts']);
```

### Erro: "Authentication failed"

```php
// Verificar credenciais
$credentials = $sdk->getConfig()->getSuperAdminCredentials();
if (!$credentials) {
    echo "Credenciais de super admin não configuradas";
}
```