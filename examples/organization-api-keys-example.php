<?php

/**
 * Organization API Keys - Exemplo de Uso Completo
 *
 * Demonstra como usar API keys com diferentes escopos:
 * - ORGANIZATION: Acesso total à organização
 * - CROSS_TENANT: Acesso multi-tenant
 * - TENANT: Acesso restrito ao tenant (compatibilidade)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Organization\Services\OrganizationApiKeyService;

echo "🔑 Organization API Keys - Exemplo Completo\n";
echo "==========================================\n";

// Configurar SDK
$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'clb_org_test_813109fb9f2b4b74239df20fa1a5948a',
    'environment' => 'test'
]);

// Autenticar como super admin
$sdk->initializeAsSuperAdmin([
    'email' => 'admin@example.com',
    'password' => 'P@ssw0rd!',
    'tenant_id' => '507f1f77bcf86cd799439011'
]);

echo "✅ Autenticado como Super Admin\n\n";

// Simular organização existente
$organizationId = '68d94e3a878451ed8bb9d873';

// Obter serviço de API keys da organização através do módulo Organization
$orgApiKeyService = $sdk->organization()->organizationApiKey();

// ===== EXEMPLO 1: API Key de ORGANIZAÇÃO (Acesso Total) =====
echo "🏢 EXEMPLO 1: API Key de Organização (Acesso Total)\n";
echo "--------------------------------------------------\n";

try {
    $orgKey = $orgApiKeyService->generateFullOrganizationKey($organizationId, [
        'name' => 'Master Organization Key',
        'environment' => OrganizationApiKeyService::ENV_LIVE,
        'description' => 'Chave com acesso total à organização e todos os seus tenants'
    ]);

    echo "✅ API Key de Organização gerada:\n";
    echo "   Key ID: " . $orgKey['key_id'] . "\n";
    echo "   API Key: " . substr($orgKey['api_key'], 0, 20) . "...\n";
    echo "   Scope: " . $orgKey['scope'] . "\n";
    echo "   Acesso: Toda a organização\n";

    $organizationApiKey = $orgKey['api_key'];

} catch (Exception $e) {
    echo "❌ Erro ao gerar API key de organização: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n";

// ===== EXEMPLO 2: API Key CROSS-TENANT =====
echo "🔀 EXEMPLO 2: API Key Cross-Tenant (Multi-Tenant)\n";
echo "------------------------------------------------\n";

$allowedTenants = [
    '68dab606378f93bd3931cdc0',
    '68da8f00378f93bd3931ad66'
];

try {
    $crossTenantKey = $orgApiKeyService->generateCrossTenantKey($organizationId, $allowedTenants, [
        'name' => 'Multi-Tenant Integration Key',
        'environment' => OrganizationApiKeyService::ENV_LIVE,
        'description' => 'Acesso a múltiplos tenants específicos'
    ]);

    echo "✅ API Key Cross-Tenant gerada:\n";
    echo "   Key ID: " . $crossTenantKey['key_id'] . "\n";
    echo "   API Key: " . substr($crossTenantKey['api_key'], 0, 20) . "...\n";
    echo "   Scope: " . $crossTenantKey['scope'] . "\n";
    echo "   Tenants permitidos: " . count($allowedTenants) . "\n";

} catch (Exception $e) {
    echo "❌ Erro ao gerar API key cross-tenant: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== EXEMPLO 3: API Key de TENANT (Compatibilidade) =====
echo "🏬 EXEMPLO 3: API Key de Tenant (Compatibilidade)\n";
echo "-----------------------------------------------\n";

$specificTenantId = '68dab606378f93bd3931cdc0';

try {
    $tenantKey = $orgApiKeyService->generateTenantKey($organizationId, $specificTenantId, [
        'name' => 'Tenant Specific Key',
        'environment' => OrganizationApiKeyService::ENV_LIVE,
        'description' => 'Acesso restrito ao tenant específico'
    ]);

    echo "✅ API Key de Tenant gerada:\n";
    echo "   Key ID: " . $tenantKey['key_id'] . "\n";
    echo "   API Key: " . substr($tenantKey['api_key'], 0, 20) . "...\n";
    echo "   Scope: " . $tenantKey['scope'] . "\n";
    echo "   Tenant: $specificTenantId\n";

} catch (Exception $e) {
    echo "❌ Erro ao gerar API key de tenant: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== EXEMPLO 4: Validação de API Keys =====
echo "✅ EXEMPLO 4: Validação de API Keys\n";
echo "----------------------------------\n";

if (isset($organizationApiKey)) {
    try {
        // Validar key de organização
        $validation = $orgApiKeyService->validateOrganizationApiKey(
            $organizationId,
            $organizationApiKey,
            [
                'endpoint' => '/api/v1/checkout',
                'tenant_id' => '68dab606378f93bd3931cdc0' // Solicitando acesso a tenant específico
            ]
        );

        if ($validation['success'] && $validation['validation_result']['valid']) {
            echo "✅ API Key de Organização válida:\n";
            echo "   Organization ID: " . $validation['validation_result']['organizationId'] . "\n";
            echo "   Scope: " . $validation['validation_result']['scope'] . "\n";
            echo "   Acesso ao Tenant: " . ($validation['validation_result']['tenantId'] ?? 'Todos') . "\n";
        } else {
            echo "❌ API Key inválida: " . ($validation['validation_result']['error'] ?? 'Unknown error') . "\n";
        }

    } catch (Exception $e) {
        echo "❌ Erro na validação: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ===== EXEMPLO 5: Listar API Keys da Organização =====
echo "📋 EXEMPLO 5: Listar API Keys da Organização\n";
echo "-------------------------------------------\n";

try {
    $keysList = $orgApiKeyService->listOrganizationApiKeys($organizationId, [
        'include_inactive' => false,
        'limit' => 10
    ]);

    if ($keysList['success']) {
        echo "✅ API Keys encontradas: " . $keysList['total'] . "\n";

        foreach ($keysList['keys'] as $key) {
            echo "   • " . $key['maskedApiKey'] . " - " . $key['scope'] . " (" . $key['environment'] . ")\n";
            echo "     Descrição: " . ($key['description'] ?? 'N/A') . "\n";
            echo "     Status: " . $key['status'] . "\n";
            echo "     Uso: " . ($key['usageCount'] ?? 0) . " requests\n";
            echo "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erro ao listar API keys: " . $e->getMessage() . "\n";
}

// ===== EXEMPLO 6: Estatísticas de Uso =====
echo "📊 EXEMPLO 6: Estatísticas de Uso da Organização\n";
echo "-----------------------------------------------\n";

try {
    $stats = $orgApiKeyService->getOrganizationUsageStats($organizationId, [
        'start_date' => date('Y-m-d', strtotime('-30 days')),
        'end_date' => date('Y-m-d')
    ]);

    if ($stats['success']) {
        $statistics = $stats['statistics'];
        echo "✅ Estatísticas dos últimos 30 dias:\n";
        echo "   Total de Keys: " . ($statistics['totalKeys'] ?? 0) . "\n";
        echo "   Keys Ativas: " . ($statistics['activeKeys'] ?? 0) . "\n";
        echo "   Total de Requests: " . number_format($statistics['totalRequests'] ?? 0) . "\n";
        echo "   Requests por Scope:\n";

        foreach ($statistics['requestsByScope'] ?? [] as $scope => $count) {
            echo "     - $scope: " . number_format($count) . "\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Erro ao obter estatísticas: " . $e->getMessage() . "\n";
}

echo "\n";

// ===== EXEMPLO 7: Casos de Uso Práticos =====
echo "🎯 EXEMPLO 7: Casos de Uso Práticos\n";
echo "----------------------------------\n";

echo "✅ Casos de uso recomendados:\n\n";

echo "1. 🏢 ORGANIZATION SCOPE:\n";
echo "   - Dashboard administrativo geral\n";
echo "   - Relatórios cross-tenant\n";
echo "   - Operações de backup/migração\n";
echo "   - Integrações de sistema (ERP, CRM)\n\n";

echo "2. 🔀 CROSS_TENANT SCOPE:\n";
echo "   - Multi-loja (franquias)\n";
echo "   - Integrações com múltiplas filiais\n";
echo "   - Consolidação de dados específicos\n";
echo "   - APIs de parceiros com acesso limitado\n\n";

echo "3. 🏬 TENANT SCOPE:\n";
echo "   - Aplicações específicas da loja\n";
echo "   - Integrações de terceiros por loja\n";
echo "   - APIs públicas da loja\n";
echo "   - Webhook endpoints específicos\n\n";

// ===== EXEMPLO 8: Migração do Sistema Atual =====
echo "🔄 EXEMPLO 8: Migração do Sistema Atual\n";
echo "--------------------------------------\n";

echo "✅ Plano de migração:\n";
echo "1. Sistema atual (tenant-only) continua funcionando\n";
echo "2. Novas funcionalidades usam organization keys\n";
echo "3. Migração gradual conforme necessidade\n";
echo "4. Backward compatibility mantida\n\n";

echo "🎉 Exemplos concluídos!\n";
echo "\n📚 Próximos passos:\n";
echo "  1. Implementar autenticação com organization keys\n";
echo "  2. Configurar middlewares de validação\n";
echo "  3. Migrar APIs existentes gradualmente\n";
echo "  4. Documentar casos de uso específicos\n";

?>