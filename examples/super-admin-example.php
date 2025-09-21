<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\SuperAdmin\DTOs\SuperAdminCredentials;
use Clubify\Checkout\Modules\SuperAdmin\DTOs\TenantCreationData;

/**
 * Exemplo de uso do SDK com funcionalidades de Super Admin
 */

try {
    // ===============================================
    // 1. INICIALIZAÇÃO COMO SUPER ADMIN
    // ===============================================

    echo "=== Inicializando SDK como Super Admin ===\n";

    $sdk = new ClubifyCheckoutSDK();

    // Credenciais do super admin
    $superAdminCredentials = [
        'api_key' => 'clb_live_super_admin_api_key_here',
        'access_token' => 'super_admin_access_token_here',
        'refresh_token' => 'super_admin_refresh_token_here',
        'username' => 'super_admin@clubify.com',
        'password' => 'super_admin_password'
    ];

    // Inicializar como super admin
    $initResult = $sdk->initializeAsSuperAdmin($superAdminCredentials);

    echo "✅ SDK inicializado como super admin:\n";
    echo "   Mode: " . $initResult['mode'] . "\n";
    echo "   Role: " . $initResult['role'] . "\n";
    echo "   Authenticated: " . ($initResult['authenticated'] ? 'Yes' : 'No') . "\n\n";

    // ===============================================
    // 2. CRIAÇÃO DE ORGANIZAÇÃO
    // ===============================================

    echo "=== Criando Nova Organização ===\n";

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

    echo "✅ Organização criada com sucesso:\n";
    echo "   Organization ID: " . $organization['organization']['id'] . "\n";
    echo "   Tenant ID: " . $organization['tenant']['id'] . "\n";
    echo "   API Key: " . substr($organization['tenant']['api_key'], 0, 20) . "...\n\n";

    $tenantId = $organization['tenant']['id'];

    // ===============================================
    // 3. GERENCIAMENTO DE TENANTS (SUPER ADMIN)
    // ===============================================

    echo "=== Operações de Super Admin ===\n";

    // Listar todos os tenants
    $tenants = $sdk->superAdmin()->listTenants();
    echo "📋 Total de tenants: " . count($tenants['data']) . "\n";

    // Obter estatísticas do sistema
    $stats = $sdk->superAdmin()->getSystemStats();
    echo "📊 Organizações ativas: " . $stats['organizations']['active'] . "\n";
    echo "📊 Total de usuários: " . $stats['users']['total'] . "\n\n";

    // ===============================================
    // 4. ALTERNÂNCIA DE CONTEXTO
    // ===============================================

    echo "=== Alternando para Contexto de Tenant ===\n";

    // Alternar para o tenant criado
    $sdk->switchToTenant($tenantId);

    $context = $sdk->getCurrentContext();
    echo "🔄 Contexto alterado:\n";
    echo "   Current Role: " . $context['current_role'] . "\n";
    echo "   Active Context: " . ($context['current_role'] === 'tenant_admin' ? $tenantId : 'super_admin') . "\n\n";

    // ===============================================
    // 5. OPERAÇÕES COMO TENANT ADMIN
    // ===============================================

    echo "=== Operações como Tenant Admin ===\n";

    // Agora podemos fazer operações normais como tenant admin
    try {
        // Listar produtos (como tenant admin)
        $products = $sdk->products()->getProductService()->list();
        echo "📦 Produtos do tenant: " . count($products) . "\n";
    } catch (Exception $e) {
        echo "ℹ️  Ainda não há produtos para este tenant\n";
    }

    // Criar um produto de exemplo
    $productData = [
        'name' => 'Produto Demo',
        'description' => 'Produto criado via SDK com super admin',
        'price' => [
            'amount' => 9999, // R$ 99,99
            'currency' => 'BRL'
        ],
        'type' => 'digital'
    ];

    try {
        $product = $sdk->products()->getProductService()->create($productData);
        echo "✅ Produto criado: " . $product['name'] . "\n";
    } catch (Exception $e) {
        echo "⚠️  Erro ao criar produto: " . $e->getMessage() . "\n";
    }

    // ===============================================
    // 6. VOLTA PARA SUPER ADMIN
    // ===============================================

    echo "\n=== Voltando para Super Admin ===\n";

    // Alternar de volta para super admin
    $sdk->switchToSuperAdmin();

    $context = $sdk->getCurrentContext();
    echo "🔄 Contexto alterado para: " . $context['current_role'] . "\n";

    // Agora podemos fazer operações de super admin novamente
    $tenantCredentials = $sdk->superAdmin()->getTenantCredentials($tenantId);
    echo "🔑 Credenciais do tenant obtidas com sucesso\n";

    // ===============================================
    // 7. GESTÃO AVANÇADA DE TENANTS
    // ===============================================

    echo "\n=== Gestão Avançada de Tenants ===\n";

    // Regenerar API key do tenant
    try {
        $newCredentials = $sdk->superAdmin()->regenerateApiKey($tenantId);
        echo "🔄 API key regenerada: " . substr($newCredentials['api_key'], 0, 20) . "...\n";
    } catch (Exception $e) {
        echo "⚠️  Erro ao regenerar API key: " . $e->getMessage() . "\n";
    }

    // Listar tenants com filtros
    $filteredTenants = $sdk->superAdmin()->listTenants([
        'status' => 'active',
        'limit' => 10
    ]);
    echo "📋 Tenants ativos: " . count($filteredTenants['data']) . "\n";

    // ===============================================
    // 8. INFORMAÇÕES DE CONTEXTO
    // ===============================================

    echo "\n=== Informações do Contexto Atual ===\n";

    $finalContext = $sdk->getCurrentContext();
    echo "📍 Modo de operação: " . $finalContext['mode'] . "\n";
    echo "👤 Role atual: " . $finalContext['current_role'] . "\n";
    echo "🏢 Contextos disponíveis: " . count($finalContext['available_contexts']['contexts']) . "\n";

    echo "\n✅ Exemplo de Super Admin concluído com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}