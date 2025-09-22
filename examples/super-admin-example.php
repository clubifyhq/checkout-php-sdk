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

    // Credenciais do super admin (API key como método primário, email/senha como fallback)
    $superAdminCredentials = [
        // 'api_key' => 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd', // Comentado para forçar fallback login
        'api_key_disabled' => 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd',
        'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OGMwMzA1Yzg1ZDczZjg3NmY5YTBkNjUiLCJlbWFpbCI6ImFkbWluQGV4YW1wbGUuY29tIiwicm9sZXMiOlsic3lzdGVtX2FkbWluIiwic3VwZXJfYWRtaW4iXSwidGVuYW50SWQiOiI1MDdmMWY3N2JjZjg2Y2Q3OTk0MzkwMTEiLCJmYW1pbHlJZCI6ImQyMTZkZmUzLTFmMzMtNDllNi05ZWIwLTJmZWUwNjk4M2U1NSIsImdlbmVyYXRpb24iOjAsImRldmljZUZpbmdlcnByaW50IjoiZGZwLTE3NTg1NTgxODUiLCJhdWQiOlsiY2x1YmlmeS11c2VycyJdLCJpc3MiOiJjbHViaWZ5LWNoZWNrb3V0IiwidG9rZW5UeXBlIjoiYWNjZXNzIiwianRpIjoiMzUwMzgzN2UtNjk3YS00MjIyLTkxNTYtZjNhYmI5NGE1MzU1IiwiaWF0IjoxNzU4NTU4MTg1LCJleHAiOjE3NTg2NDQ1ODV9.9eZuRGnngSTIQa2Px9Yyfoaddo1m-PM20l4XxdaVMlg',
        'refresh_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OGMwMzA1Yzg1ZDczZjg3NmY5YTBkNjUiLCJlbWFpbCI6ImFkbWluQGV4YW1wbGUuY29tIiwicm9sZXMiOlsic3lzdGVtX2FkbWluIiwic3VwZXJfYWRtaW4iXSwidGVuYW50SWQiOiI1MDdmMWY3N2JjZjg2Y2Q3OTk0MzkwMTEiLCJmYW1pbHlJZCI6ImQyMTZkZmUzLTFmMzMtNDllNi05ZWIwLTJmZWUwNjk4M2U1NSIsImdlbmVyYXRpb24iOjAsImRldmljZUZpbmdlcnByaW50IjoiZGZwLTE3NTg1NTgxODUiLCJhdWQiOlsiY2x1YmlmeS11c2VycyJdLCJpc3MiOiJjbHViaWZ5LWNoZWNrb3V0IiwidG9rZW5UeXBlIjoicmVmcmVzaCIsImp0aSI6ImJiNGU4NzQ3LTk2OGMtNDI0Yi05NDM0LTg1NTQxYjMzMjUyNyIsImlhdCI6MTc1ODU1ODE4NiwiZXhwIjoxNzU5MTYyOTg2fQ.tq3A_UQCWhpJlf8HKzKfsDJ8inKSVjc-QIfOCMK5Ei',
        // Fallback para autenticação por usuário/senha
        'email' => 'admin@example.com',
        'password' => 'P@ssw0rd!',
        'tenant_id' => '507f1f77bcf86cd799439011'
    ];

    // Configuração completa do SDK (baseada no test-sdk-simple.php)
    $config = [
        'credentials' => [
            'tenant_id' => $superAdminCredentials['tenant_id'],
            'api_key' => $superAdminCredentials['api_key_disabled'],
            'api_secret' => '87aa1373d3a948f996cf1b066651941b2f9928507c1e963c867b4aa90fec9e15',  // Placeholder para secret
            'email' => $superAdminCredentials['email'],
            'password' => $superAdminCredentials['password']
        ],
        'environment' => 'test',
        'api' => [
            'base_url' => 'https://checkout.svelve.com/api/v1',
            'timeout' => 45,
            'retries' => 3,
            'verify_ssl' => false
        ],
        'cache' => [
            'enabled' => true,
            'ttl' => 3600
        ],
        'logging' => [
            'enabled' => true,
            'level' => 'info'
        ]
    ];

    echo "📋 Configuração do SDK:\n";
    echo "   Tenant ID: {$config['credentials']['tenant_id']}\n";
    echo "   API Key: " . substr($config['credentials']['api_key'], 0, 20) . "...\n";
    echo "   Environment: {$config['environment']}\n";
    echo "   Base URL: {$config['api']['base_url']}\n\n";

    // Inicializar SDK com configuração completa
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK initialized successfully!\n";

    echo "   Version: " . $sdk->getVersion() . "\n";
    echo "   Authenticated: " . ($sdk->isAuthenticated() ? 'Yes' : 'No') . "\n";
    echo "   Initialized: " . ($sdk->isInitialized() ? 'Yes' : 'No') . "\n\n";

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
        'admin_email' => 'admin@nova-empresa.com',
        'admin_name' => 'João Admin',
        'subdomain' => 'nova-empresa',
        'custom_domain' => 'checkout.nova-empresa.com',
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
    echo "   Current Role: " . (isset($context['current_role']) ? $context['current_role'] : 'N/A') . "\n";
    $currentRole = isset($context['current_role']) ? $context['current_role'] : '';
    echo "   Active Context: " . ($currentRole === 'tenant_admin' ? $tenantId : 'super_admin') . "\n\n";

    // ===============================================
    // 5. OPERAÇÕES COMO TENANT ADMIN
    // ===============================================

    echo "=== Operações como Tenant Admin ===\n";

    // Agora podemos fazer operações normais como tenant admin
    try {
        // Listar produtos (como tenant admin) - usando método direto
        $products = $sdk->products()->list();
        echo "📦 Produtos do tenant: " . count($products) . "\n";
    } catch (Exception $e) {
        echo "ℹ️  Ainda não há produtos para este tenant: " . $e->getMessage() . "\n";
    }

    // Criar um produto de exemplo usando método de conveniência do SDK
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
        // Usar método de conveniência do SDK para criar produto completo
        $product = $sdk->createCompleteProduct($productData);
        echo "✅ Produto criado: " . $product['name'] . "\n";
    } catch (Exception $e) {
        echo "ℹ️  Testando criação de produto: " . $e->getMessage() . "\n";

        // Tentar método alternativo através do módulo products
        try {
            $product = $sdk->products()->create($productData);
            echo "✅ Produto criado (método alternativo): " . $product['name'] . "\n";
        } catch (Exception $e2) {
            echo "⚠️  Erro ao criar produto: " . $e2->getMessage() . "\n";
        }
    }

    // ===============================================
    // 6. VOLTA PARA SUPER ADMIN
    // ===============================================

    echo "\n=== Voltando para Super Admin ===\n";

    // Alternar de volta para super admin
    $sdk->switchToSuperAdmin();

    $context = $sdk->getCurrentContext();
    echo "🔄 Contexto alterado para: " . (isset($context['current_role']) ? $context['current_role'] : 'N/A') . "\n";

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
    echo "📍 Modo de operação: " . (isset($finalContext['mode']) ? $finalContext['mode'] : 'N/A') . "\n";
    echo "👤 Role atual: " . (isset($finalContext['current_role']) ? $finalContext['current_role'] : 'N/A') . "\n";

    if (isset($finalContext['available_contexts']['contexts'])) {
        echo "🏢 Contextos disponíveis: " . count($finalContext['available_contexts']['contexts']) . "\n";
    } else {
        echo "🏢 Contextos disponíveis: N/A\n";
    }

    echo "\n✅ Exemplo de Super Admin concluído com sucesso!\n";

} catch (Exception $e) {
    echo "\n❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}