# Guia de Migração de Dados entre Tenants

## Problema Identificado

Quando um usuário é criado inicialmente no **tenant super admin** e posteriormente é transferido para um **novo tenant**, os dados que ele criou (produtos, customers, orders, etc.) permanecem associados ao tenant original, causando inconsistência e inacessibilidade dos dados.

### Cenário Problemático

1. **Usuário criado** no tenant super admin (`507f1f77bcf86cd799439011`)
2. **Usuário cria produtos** que ficam associados ao tenant super admin
3. **Usuário é transferido** para novo tenant (`507f1f77bcf86cd799439033`)
4. **Produtos ficam órfãos** no tenant super admin
5. **Usuário não encontra** seus produtos no novo tenant

## Solução Implementada

### 1. Serviço de Migração de Dados

**Arquivo:** `src/Modules/Organization/Services/TenantDataMigrationService.php`

```php
use Clubify\Checkout\Modules\Organization\Services\TenantDataMigrationService;

$migrationService = new TenantDataMigrationService($productRepo, $userRepo, $logger);
$result = $migrationService->migrateUserData($userId, $sourceTenant, $targetTenant);
```

### 2. Métodos Helper no SDK Principal

**Arquivo:** `src/ClubifyCheckoutSDK.php`

```php
// Verificar dados órfãos
$orphanedData = $sdk->findUserOrphanedData($userId, $tenantId);

// Migrar dados entre tenants
$migrationResult = $sdk->migrateUserDataBetweenTenants(
    $userId,
    $sourceTenantId,
    $targetTenantId
);
```

### 3. Extensão do TenantService

**Arquivo:** `src/Modules/Organization/Services/TenantService.php`

```php
// Transferir usuário com migração de dados
$result = $tenantService->transferUserToTenant(
    $userId,
    $currentTenantId,
    $newTenantId,
    ['skip_data_migration' => false]
);
```

## Como Usar

### Opção 1: Script Rápido (Recomendado)

```bash
cd sdk/php/examples
php quick-fix-orphaned-data.php
```

**Configuração necessária:**
```php
$config = [
    'base_uri' => 'https://api.checkout.clubify.com.br/',
    'api_key' => 'your-super-admin-api-key',
];

$superAdminTenantId = 'seu-tenant-super-admin-id';
$newTenantId = 'seu-novo-tenant-id';
$userId = 'id-do-usuario';
```

### Opção 2: Integração Programática

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK($config);

// 1. Verificar dados órfãos
$orphanedData = $sdk->findUserOrphanedData($userId, $superAdminTenantId);

if ($orphanedData['total_orphaned'] > 0) {
    // 2. Executar migração
    $result = $sdk->migrateUserDataBetweenTenants(
        $userId,
        $superAdminTenantId,
        $newTenantId
    );

    if ($result['success']) {
        echo "Migração concluída com sucesso!";
    }
}
```

### Opção 3: Exemplo Completo

```bash
cd sdk/php/examples
php tenant-data-migration-example.php
```

## Entidades Suportadas

### ✅ Implementado
- **Produtos** - Migração completa com metadados

### 🔄 Próximas Implementações
- **Customers** - Clientes criados pelo usuário
- **Orders** - Pedidos associados ao usuário
- **Webhooks** - Configurações de webhook
- **Custom Fields** - Campos personalizados

## Estrutura da Resposta

### Verificação de Dados Órfãos

```php
[
    'user_id' => '507f1f77bcf86cd799439022',
    'tenant_id' => '507f1f77bcf86cd799439011',
    'orphaned_items' => [
        'products' => [
            'count' => 5,
            'items' => [
                ['id' => '...', 'name' => 'Produto 1', 'type' => 'digital'],
                ['id' => '...', 'name' => 'Produto 2', 'type' => 'physical']
            ]
        ]
    ],
    'total_orphaned' => 5,
    'checked_at' => '2025-09-24T18:00:00+00:00'
]
```

### Resultado da Migração

```php
[
    'success' => true,
    'user_id' => '507f1f77bcf86cd799439022',
    'source_tenant' => '507f1f77bcf86cd799439011',
    'target_tenant' => '507f1f77bcf86cd799439033',
    'migrations' => [
        'products' => [
            'total_found' => 5,
            'migrated' => 5,
            'errors' => []
        ]
    ],
    'started_at' => '2025-09-24T18:00:00+00:00',
    'completed_at' => '2025-09-24T18:00:30+00:00'
]
```

## Segurança e Logs

### Logs Automáticos

Todas as operações são automaticamente logadas:

```php
[INFO] Iniciando migração de dados do usuário
[INFO] Produtos encontrados para migração: 5
[INFO] Produto migrado com sucesso: Produto 1
[INFO] Migração de dados concluída com sucesso
```

### Rollback Automático

Em caso de falha, o sistema pode automaticamente reverter:

```php
$options = [
    'rollback_on_error' => true,
    'skip_products' => false
];
```

## Fluxo Recomendado

### Para Novos Tenants

1. **Criar tenant** via SuperAdmin
2. **Criar usuário admin** para o tenant
3. **Detectar dados órfãos** do usuário anterior
4. **Executar migração** automaticamente
5. **Verificar integridade** pós-migração

### Para Tenants Existentes

1. **Identificar usuários** com dados órfãos
2. **Executar script** de verificação
3. **Migrar dados** conforme necessário
4. **Monitorar logs** para auditoria

## Tratamento de Erros

### Erros Comuns

**Produto já existe no tenant destino:**
```php
[
    'product' => 'Nome do Produto',
    'error' => 'Product with SKU already exists'
]
```

**Falha de conectividade:**
```php
[
    'success' => false,
    'error' => 'HTTP request failed: Connection timeout'
]
```

### Estratégias de Recuperação

1. **Retry automático** para falhas temporárias
2. **Skip duplicados** e continuar migração
3. **Log detalhado** para análise posterior
4. **Rollback parcial** em caso de falha crítica

## Configurações Avançadas

### Opções de Migração

```php
$options = [
    'skip_products' => false,           // Pular migração de produtos
    'skip_customers' => false,          // Pular migração de customers
    'rollback_on_error' => true,        // Rollback automático
    'batch_size' => 10,                 // Processar em lotes
    'dry_run' => false,                 // Simular sem executar
    'preserve_timestamps' => false      // Manter timestamps originais
];
```

### Monitoramento

```php
// Estatísticas por tenant
$stats = $migrationService->getTenantDataStats($tenantId, $userId);

// Produtos órfãos específicos
$orphaned = $migrationService->findOrphanedProducts($userId, $tenantId);

// Migração seletiva
$result = $migrationService->migrateSpecificProducts(
    ['product_id_1', 'product_id_2'],
    $targetTenantId
);
```

## Performance

### Otimizações Implementadas

- **Cache inteligente** para evitar re-consultas
- **Processamento em lotes** para grandes volumes
- **Logs assíncronos** para não impactar performance
- **Validação prévia** para evitar operações desnecessárias

### Métricas Esperadas

- **Verificação:** ~100ms por usuário
- **Migração:** ~200ms por produto
- **Rollback:** ~50ms por item
- **Logs:** Assíncronos, sem impacto

## Roadmap

### Versão 1.1
- [ ] Migração de Customers
- [ ] Migração de Orders
- [ ] Interface web para administração

### Versão 1.2
- [ ] Migração automática via webhook
- [ ] Dashboard de monitoramento
- [ ] Relatórios de auditoria

### Versão 1.3
- [ ] Migração de configurações avançadas
- [ ] API REST dedicada
- [ ] Integração com ferramentas de BI

## Suporte

### Documentação
- [API Reference](./api-reference.md)
- [Examples](../examples/)
- [Troubleshooting](./troubleshooting.md)

### Contato
- **Issues:** [GitHub Issues](https://github.com/clubifyhq/checkout-sdk-php/issues)
- **Suporte:** suporte@clubify.com.br
- **Documentação:** docs.clubify.com.br

---

**Última atualização:** 24 de setembro de 2025
**Versão do SDK:** 1.0.0
**Status:** ✅ Produção