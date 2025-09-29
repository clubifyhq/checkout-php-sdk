<?php

/**
 * Organization API Keys Authentication - Exemplo Completo
 *
 * Demonstra autenticação e geração de access tokens usando
 * Organization-Level API Keys com todos os escopos:
 * - ORGANIZATION: Access tokens com acesso total à organização
 * - CROSS_TENANT: Access tokens com acesso multi-tenant
 * - TENANT: Access tokens com acesso restrito ao tenant
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "🔐 Organization API Keys Authentication - Exemplo Completo\n";
echo "========================================================\n";

// Dados simulados (normalmente vêm do seu sistema)
$organizationId = '68d94e3a878451ed8bb9d873';
$organizationApiKey = 'clb_org_test_813109fb9f2b4b74239df20fa1a5948a';
$crossTenantApiKey = 'clb_multi_test_266a8ae2f416222b2d79d9db3507fd89';
$tenantApiKey = 'clb_tenant_live_123456789abcdef0123456789abcdef0123456789abcdef0123456789abc';

$tenantId = 'tenant_123456789abcdef';
$anotherTenantId = 'tenant_987654321fedcba';

// ===== EXEMPLO 1: Autenticação com Organization API Key (Acesso Total) =====
echo "🏢 EXEMPLO 1: Autenticação com Organization API Key (Acesso Total)\n";
echo "----------------------------------------------------------------\n";

try {
    $sdk = new ClubifyCheckoutSDK([
        'environment' => 'staging'
    ]);

    // Autenticar usando Organization API Key
    $authResult = $sdk->authenticateAsOrganization($organizationId, $organizationApiKey);

    if ($authResult['success']) {
        echo "✅ Autenticação organizacional bem-sucedida!\n";
        echo "   Access Token: " . substr($authResult['access_token'], 0, 20) . "...\n";
        echo "   Token Type: " . $authResult['token_type'] . "\n";
        echo "   Expires In: " . $authResult['expires_in'] . " segundos\n";
        echo "   Scope: " . $authResult['scope'] . "\n";
        echo "   Organization ID: " . $authResult['organization_id'] . "\n";
        echo "   Permissions: " . implode(', ', $authResult['permissions']) . "\n";
        echo "   Accessible Tenants: " . (empty($authResult['accessible_tenants']) ? 'ALL' : implode(', ', $authResult['accessible_tenants'])) . "\n";

        // Verificar contexto organizacional
        $context = $sdk->getOrganizationContext();
        echo "   Current Context: Org={$context['organization_id']}, Tenant={$context['tenant_id']}, Scope={$context['scope']}\n";
    }

} catch (Exception $e) {
    echo "❌ Erro na autenticação organizacional: " . $e->getMessage() . "\n";
}

echo "\n";

/*
// ===== EXEMPLO 2: Autenticação Cross-Tenant =====
echo "🔀 EXEMPLO 2: Autenticação Cross-Tenant (Multi-Tenant)\n";
echo "-----------------------------------------------------\n";

try {
    $sdk2 = new ClubifyCheckoutSDK([
        'environment' => 'staging'
    ]);

    // Autenticar com Cross-Tenant key especificando tenant alvo
    $crossTenantAuth = $sdk2->authenticateAsCrossTenant($organizationId, $crossTenantApiKey, $tenantId);

    if ($crossTenantAuth['success']) {
        echo "✅ Autenticação cross-tenant bem-sucedida!\n";
        echo "   Scope: " . $crossTenantAuth['scope'] . "\n";
        echo "   Target Tenant: " . $crossTenantAuth['tenant_id'] . "\n";
        echo "   Accessible Tenants: " . implode(', ', $crossTenantAuth['accessible_tenants']) . "\n";
        echo "   Permissions: " . implode(', ', $crossTenantAuth['permissions']) . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro na autenticação cross-tenant: " . $e->getMessage() . "\n";
}

echo "\n";
*/

/*
// ===== EXEMPLO 3: Uso do Access Token em Requisições =====
echo "📡 EXEMPLO 3: Usando Access Token em Requisições\n";
echo "-----------------------------------------------\n";

if (isset($authResult) && $authResult['success']) {
    try {
        // O SDK automaticamente usa o access token para requisições
        echo "✅ SDK configurado com access token organizacional\n";
        echo "   Authorization: Bearer " . substr($authResult['access_token'], 0, 20) . "...\n";
        echo "   X-Organization-Id: " . $authResult['organization_id'] . "\n";

        if (isset($authResult['tenant_id'])) {
            echo "   X-Tenant-Id: " . $authResult['tenant_id'] . "\n";
        }

        // Exemplo: Fazer uma requisição que usa o access token
        echo "\n📋 Simulando requisição autenticada:\n";
        echo "   GET /api/v1/checkout/sessions\n";
        echo "   Headers:\n";
        echo "     Authorization: Bearer {access_token}\n";
        echo "     X-Organization-Id: {$authResult['organization_id']}\n";
        echo "     X-Tenant-Id: {$authResult['tenant_id']}\n";
        echo "   ✅ Requisição autorizada com escopo: {$authResult['scope']}\n";

    } catch (Exception $e) {
        echo "❌ Erro ao usar access token: " . $e->getMessage() . "\n";
    }
}

echo "\n";
*/

// ===== EXEMPLO 4: Verificação de Permissões =====
echo "🔐 EXEMPLO 4: Verificação de Permissões\n";
echo "--------------------------------------\n";

if (isset($authResult) && $authResult['success']) {
    // Simular verificação de permissões (seria feita pelo backend)
    $requiredPermissions = ['organization:read', 'tenant:write', 'checkout:full'];
    $userPermissions = $authResult['permissions'];

    echo "✅ Permissões do usuário: " . implode(', ', $userPermissions) . "\n";
    echo "✅ Permissões necessárias: " . implode(', ', $requiredPermissions) . "\n";

    $hasPermissions = !empty(array_intersect($requiredPermissions, $userPermissions));
    echo $hasPermissions ? "✅ Acesso autorizado\n" : "❌ Acesso negado\n";
}

echo "\n";

// ===== EXEMPLO 5: Diferentes Cenários de Uso =====
echo "🎯 EXEMPLO 5: Diferentes Cenários de Uso\n";
echo "---------------------------------------\n";

echo "✅ Cenário 1 - Dashboard Administrativo Geral:\n";
echo "   • Use: Organization API Key (scope=organization)\n";
echo "   • Access Token terá acesso total à organização\n";
echo "   • Pode acessar dados de todos os tenants\n";
echo "   • Ideal para: relatórios, configurações globais, backup\n\n";

echo "✅ Cenário 2 - Sistema Multi-Loja:\n";
echo "   • Use: Cross-Tenant API Key (scope=cross_tenant)\n";
echo "   • Access Token limitado a tenants específicos\n";
echo "   • Pode alternar entre tenants autorizados\n";
echo "   • Ideal para: franquias, multi-marca, parceiros\n\n";

echo "✅ Cenário 3 - Aplicação Específica da Loja:\n";
echo "   • Use: Tenant API Key (scope=tenant)\n";
echo "   • Access Token restrito ao tenant específico\n";
echo "   • Comportamento igual ao sistema atual\n";
echo "   • Ideal para: e-commerce individual, apps específicos\n\n";

// ===== EXEMPLO 6: Comparação com Sistema Atual =====
echo "⚖️  EXEMPLO 6: Comparação com Sistema Atual\n";
echo "-------------------------------------------\n";

echo "🔄 ANTES (Sistema Atual):\n";
echo "   POST /auth/api-key/token\n";
echo "   {\n";
echo "     \"api_key\": \"clb_live_xxxxx\",\n";
echo "     \"tenant_id\": \"tenant_123\"\n";
echo "   }\n";
echo "   → Access token apenas para o tenant específico\n\n";

echo "🆕 AGORA (Organization API Keys):\n";
echo "   POST /auth/api-key/organization/token\n";
echo "   {\n";
echo "     \"api_key\": \"clb_org_live_xxxxx\",\n";
echo "     \"organization_id\": \"org_123\",\n";
echo "     \"tenant_id\": \"tenant_456\" // opcional\n";
echo "   }\n";
echo "   → Access token com contexto organizacional completo\n";
echo "   → Suporte a múltiplos escopos de acesso\n";
echo "   → Hierarquia Organization → Tenant respeitada\n\n";

// ===== EXEMPLO 7: Fluxo Completo de Integração =====
echo "🔄 EXEMPLO 7: Fluxo Completo de Integração\n";
echo "------------------------------------------\n";

echo "1️⃣ Gerar Organization API Key:\n";
echo "   \$orgKey = \$sdk->organization()->generateFullOrganizationKey(\$orgId);\n\n";

echo "2️⃣ Autenticar com Organization Key:\n";
echo "   \$auth = \$sdk->authenticateAsOrganization(\$orgId, \$orgKey['api_key']);\n\n";

echo "3️⃣ Access Token é automaticamente usado em todas as requisições:\n";
echo "   \$sessions = \$sdk->checkout()->listSessions(); // Usa access token\n\n";

echo "4️⃣ Token é válido para toda a organização ou tenants específicos:\n";
echo "   - Organization scope: Acesso total\n";
echo "   - Cross-tenant scope: Multi-tenant controlado\n";
echo "   - Tenant scope: Tenant específico (compatibilidade)\n\n";

// ===== EXEMPLO 8: Migração do Sistema Atual =====
echo "🔄 EXEMPLO 8: Migração Gradual\n";
echo "------------------------------\n";

echo "✅ Estratégia de migração:\n";
echo "1. Sistema atual continua funcionando inalterado\n";
echo "2. Implementar novos endpoints com organization keys\n";
echo "3. Migrar funcionalidades uma por uma\n";
echo "4. Deprecar sistema antigo após migração completa\n\n";

echo "📚 Backward Compatibility:\n";
echo "• Tenant API keys continuam gerando access tokens válidos\n";
echo "• Organization keys estendem funcionalidade sem quebrar código existente\n";
echo "• Mesma estrutura de JWT, com campos adicionais para contexto organizacional\n\n";

echo "🎉 Autenticação com Organization API Keys implementada!\n";
echo "\n📋 Próximos passos:\n";
echo "  1. Configurar middlewares nos serviços para validar organization tokens\n";
echo "  2. Implementar RBAC baseado em organization context\n";
echo "  3. Migrar endpoints críticos para suportar organization scope\n";
echo "  4. Treinar equipe nos novos fluxos de autenticação\n";

?>