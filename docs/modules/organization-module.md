# 🏢 Organization Module - Documentação Completa

## Visão Geral

O **Organization Module** é responsável pela gestão completa de organizações na plataforma Clubify Checkout, incluindo setup de multi-tenancy, gestão de administradores, geração de API keys e configuração de domínios customizados.

### 🎯 Funcionalidades Principais

- **Setup Completo de Organização**: Processo automatizado de criação e configuração
- **Multi-Tenant Management**: Gestão de tenants com isolamento completo
- **Admin User Management**: Criação e gestão de usuários administradores
- **API Key Management**: Geração, validação e rotação de chaves API
- **Domain Configuration**: Configuração de domínios customizados

### 🏗️ Arquitetura

O módulo segue os **princípios SOLID** e utiliza **lazy loading** para otimização de performance:

```
OrganizationModule
├── Services/
│   ├── TenantService      # Gestão de tenants
│   ├── AdminService       # Gestão de admins
│   ├── ApiKeyService      # Gestão de API keys
│   └── DomainService      # Gestão de domínios
├── Repositories/
│   └── OrganizationRepository
└── DTOs/
    ├── OrganizationData
    ├── TenantData
    └── AdminData
```

## 📚 API Reference

### OrganizationModule

#### Métodos Principais

##### `setupOrganization(array $organizationData): array`

Executa o setup completo de uma nova organização.

**Parâmetros:**
```php
$organizationData = [
    'name' => 'Nome da Organização',           // Required
    'admin_name' => 'Nome do Admin',           // Required
    'admin_email' => 'admin@example.com',      // Required
    'subdomain' => 'minha-empresa',            // Optional
    'domain' => 'checkout.minha-empresa.com',  // Optional
    'settings' => [                            // Optional
        'currency' => 'BRL',
        'language' => 'pt-BR',
        'timezone' => 'America/Sao_Paulo'
    ]
];
```

**Retorno:**
```php
[
    'organization' => [
        'id' => 'org_123',
        'name' => 'Nome da Organização',
        'status' => 'active',
        'created_at' => '2025-01-16T10:00:00Z'
    ],
    'tenant' => [
        'id' => 'tenant_456',
        'organization_id' => 'org_123',
        'subdomain' => 'minha-empresa'
    ],
    'admin' => [
        'id' => 'admin_789',
        'name' => 'Nome do Admin',
        'email' => 'admin@example.com'
    ],
    'api_keys' => [
        'public_key' => 'pk_live_...',
        'secret_key' => 'sk_live_...'
    ],
    'domain' => [
        'domain' => 'checkout.minha-empresa.com',
        'status' => 'pending_verification'
    ]
]
```

**Exemplo de Uso:**
```php
use ClubifyCheckout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'your-api-key',
    'environment' => 'production'
]);

$result = $sdk->organization()->setupOrganization([
    'name' => 'Minha Empresa',
    'admin_name' => 'João Silva',
    'admin_email' => 'joao@minhaempresa.com',
    'subdomain' => 'minha-empresa',
    'domain' => 'checkout.minhaempresa.com'
]);

echo "Organização criada: " . $result['organization']['id'];
```

#### Services Disponíveis

##### `tenant(): TenantService`

Retorna o serviço de gestão de tenants.

**Métodos Disponíveis:**
- `createTenant(string $organizationId, array $data): array`
- `getTenant(string $tenantId): array`
- `updateTenant(string $tenantId, array $data): array`
- `deleteTenant(string $tenantId): bool`
- `listTenants(string $organizationId): array`

##### `admin(): AdminService`

Retorna o serviço de gestão de administradores.

**Métodos Disponíveis:**
- `createAdmin(string $organizationId, array $data): array`
- `getAdmin(string $adminId): array`
- `updateAdmin(string $adminId, array $data): array`
- `deleteAdmin(string $adminId): bool`
- `listAdmins(string $organizationId): array`

##### `apiKey(): ApiKeyService`

Retorna o serviço de gestão de API keys.

**Métodos Disponíveis:**
- `generateInitialKeys(string $organizationId): array`
- `createApiKey(string $organizationId, array $data): array`
- `getApiKey(string $keyId): array`
- `rotateApiKey(string $keyId): array`
- `revokeApiKey(string $keyId): bool`
- `listApiKeys(string $organizationId): array`

##### `domain(): DomainService`

Retorna o serviço de gestão de domínios.

**Métodos Disponíveis:**
- `configure(string $organizationId, string $domain): array`
- `verify(string $domainId): array`
- `getDomain(string $domainId): array`
- `updateDomain(string $domainId, array $data): array`
- `deleteDomain(string $domainId): bool`

## 💡 Exemplos Práticos

### Setup Básico de Organização

```php
// Configuração mínima
$basicSetup = $sdk->organization()->setupOrganization([
    'name' => 'Startup Tech',
    'admin_name' => 'Maria Santos',
    'admin_email' => 'maria@startuptech.com'
]);

// Setup com domínio customizado
$advancedSetup = $sdk->organization()->setupOrganization([
    'name' => 'E-commerce Pro',
    'admin_name' => 'Carlos Oliveira',
    'admin_email' => 'carlos@ecommercepro.com',
    'subdomain' => 'ecommerce-pro',
    'domain' => 'pay.ecommercepro.com',
    'settings' => [
        'currency' => 'BRL',
        'language' => 'pt-BR',
        'timezone' => 'America/Sao_Paulo'
    ]
]);
```

### Gestão de Tenants

```php
$tenantService = $sdk->organization()->tenant();

// Criar novo tenant
$tenant = $tenantService->createTenant('org_123', [
    'name' => 'Filial São Paulo',
    'subdomain' => 'sp'
]);

// Listar todos os tenants
$tenants = $tenantService->listTenants('org_123');

// Atualizar tenant
$updatedTenant = $tenantService->updateTenant('tenant_456', [
    'name' => 'Filial São Paulo - Centro'
]);
```

### Gestão de API Keys

```php
$apiKeyService = $sdk->organization()->apiKey();

// Gerar chaves iniciais
$initialKeys = $apiKeyService->generateInitialKeys('org_123');

// Criar chave adicional para webhook
$webhookKey = $apiKeyService->createApiKey('org_123', [
    'name' => 'Webhook Key',
    'permissions' => ['webhook:read', 'webhook:write'],
    'expires_at' => '2025-12-31T23:59:59Z'
]);

// Rotacionar chave de API
$rotatedKey = $apiKeyService->rotateApiKey('key_789');

// Listar todas as chaves
$allKeys = $apiKeyService->listApiKeys('org_123');
```

### Configuração de Domínio

```php
$domainService = $sdk->organization()->domain();

// Configurar domínio
$domain = $domainService->configure('org_123', 'checkout.minhaempresa.com');

// Verificar domínio
$verification = $domainService->verify($domain['id']);

// Status do domínio
$status = $domainService->getDomain($domain['id']);
echo "Status: " . $status['verification_status'];
```

## 🔧 DTOs e Validação

### OrganizationData DTO

```php
use ClubifyCheckout\Modules\Organization\DTOs\OrganizationData;

$organizationData = new OrganizationData([
    'name' => 'Minha Empresa',
    'email' => 'contato@minhaempresa.com',
    'document' => '12.345.678/0001-90',
    'address' => [
        'street' => 'Rua das Flores, 123',
        'city' => 'São Paulo',
        'state' => 'SP',
        'zipcode' => '01234-567'
    ],
    'settings' => [
        'currency' => 'BRL',
        'language' => 'pt-BR'
    ]
]);

// Validação automática
if ($organizationData->isValid()) {
    $result = $sdk->organization()->setupOrganization($organizationData->toArray());
}
```

### TenantData DTO

```php
use ClubifyCheckout\Modules\Organization\DTOs\TenantData;

$tenantData = new TenantData([
    'name' => 'Filial Norte',
    'subdomain' => 'norte',
    'settings' => [
        'timezone' => 'America/Manaus'
    ]
]);
```

### AdminData DTO

```php
use ClubifyCheckout\Modules\Organization\DTOs\AdminData;

$adminData = new AdminData([
    'name' => 'Ana Costa',
    'email' => 'ana@empresa.com',
    'role' => 'super_admin',
    'permissions' => [
        'organization:read',
        'organization:write',
        'users:manage'
    ]
]);
```

## 🎯 Casos de Uso Avançados

### Multi-Tenant com Domínios Customizados

```php
// Setup de organização enterprise
$enterpriseOrg = $sdk->organization()->setupOrganization([
    'name' => 'Rede de Lojas Enterprise',
    'admin_name' => 'Diretor TI',
    'admin_email' => 'ti@empresa.com',
    'subdomain' => 'enterprise'
]);

$tenantService = $sdk->organization()->tenant();
$domainService = $sdk->organization()->domain();

// Criar filiais com domínios específicos
$filiais = [
    ['name' => 'Filial SP', 'subdomain' => 'sp', 'domain' => 'sp.empresa.com'],
    ['name' => 'Filial RJ', 'subdomain' => 'rj', 'domain' => 'rj.empresa.com'],
    ['name' => 'Filial MG', 'subdomain' => 'mg', 'domain' => 'mg.empresa.com']
];

foreach ($filiais as $filial) {
    // Criar tenant
    $tenant = $tenantService->createTenant($enterpriseOrg['organization']['id'], [
        'name' => $filial['name'],
        'subdomain' => $filial['subdomain']
    ]);

    // Configurar domínio customizado
    $domain = $domainService->configure(
        $enterpriseOrg['organization']['id'],
        $filial['domain']
    );

    echo "Filial {$filial['name']} criada: {$tenant['id']}\n";
    echo "Domínio configurado: {$domain['domain']}\n";
}
```

### Rotação Automática de API Keys

```php
// Sistema de rotação automática
$apiKeyService = $sdk->organization()->apiKey();

// Listar chaves próximas do vencimento
$keys = $apiKeyService->listApiKeys('org_123');

foreach ($keys as $key) {
    $expiresAt = new DateTime($key['expires_at']);
    $now = new DateTime();
    $daysDiff = $now->diff($expiresAt)->days;

    // Rotacionar chaves que vencem em 30 dias
    if ($daysDiff <= 30) {
        $newKey = $apiKeyService->rotateApiKey($key['id']);

        // Notificar administradores
        echo "Chave {$key['name']} foi rotacionada.\n";
        echo "Nova chave: {$newKey['public_key']}\n";

        // Agendar revogação da chave antiga (grace period)
        // $scheduler->schedule('revoke_key', $key['id'], '+7 days');
    }
}
```

## 🔍 Monitoramento e Logs

### Status do Módulo

```php
// Verificar status do módulo
$status = $sdk->organization()->getStatus();

echo "Módulo: {$status['name']} v{$status['version']}\n";
echo "Inicializado: " . ($status['initialized'] ? 'Sim' : 'Não') . "\n";
echo "Disponível: " . ($status['available'] ? 'Sim' : 'Não') . "\n";

// Verificar serviços individuais
foreach ($status['services'] as $service => $available) {
    echo "Serviço {$service}: " . ($available ? 'OK' : 'Falha') . "\n";
}
```

### Logs Estruturados

```php
// Os logs são automaticamente gerados para todas as operações
// Exemplo de saída de log:

/*
[2025-01-16 10:30:00] INFO: Organization module initialized
{
    "module": "organization",
    "version": "1.0.0",
    "timestamp": "2025-01-16T10:30:00Z"
}

[2025-01-16 10:31:15] INFO: Starting organization setup
{
    "name": "Minha Empresa",
    "admin_email": "admin@empresa.com",
    "has_domain": true
}

[2025-01-16 10:31:45] INFO: Organization setup completed
{
    "organization_id": "org_123",
    "tenant_id": "tenant_456",
    "admin_id": "admin_789",
    "duration_ms": 30000
}
*/
```

## ⚠️ Tratamento de Erros

### Exceptions Específicas

```php
use ClubifyCheckout\Modules\Organization\Exceptions\OrganizationException;
use ClubifyCheckout\Modules\Organization\Exceptions\DuplicateOrganizationException;
use ClubifyCheckout\Modules\Organization\Exceptions\InvalidDomainException;

try {
    $result = $sdk->organization()->setupOrganization($data);
} catch (DuplicateOrganizationException $e) {
    echo "Organização já existe: " . $e->getMessage();
} catch (InvalidDomainException $e) {
    echo "Domínio inválido: " . $e->getMessage();
} catch (OrganizationException $e) {
    echo "Erro na organização: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🔧 Configuração

### Variáveis de Ambiente

```env
# Configurações do módulo Organization
CLUBIFY_ORGANIZATION_CACHE_TTL=3600
CLUBIFY_ORGANIZATION_MAX_TENANTS=100
CLUBIFY_ORGANIZATION_DOMAIN_VERIFICATION_TIMEOUT=300
CLUBIFY_ORGANIZATION_API_KEY_EXPIRES_DAYS=365
```

### Configuração Avançada

```php
$config = [
    'organization' => [
        'cache_ttl' => 3600,
        'max_tenants_per_org' => 100,
        'domain_verification_timeout' => 300,
        'api_key_expires_days' => 365,
        'allowed_subdomains' => ['app', 'checkout', 'pay'],
        'auto_verify_domains' => false
    ]
];

$sdk = new ClubifyCheckoutSDK($config);
```

---

**Desenvolvido com ❤️ seguindo os mais altos padrões de qualidade enterprise.**