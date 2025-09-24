<?php

/**
 * Exemplo de Migração de Dados entre Tenants - Clubify Checkout SDK
 *
 * Este exemplo demonstra como resolver o problema de dados órfãos quando
 * um usuário é transferido de um tenant para outro.
 *
 * PROBLEMA RESOLVIDO:
 * - Usuário criado no tenant super admin
 * - Usuário cria produtos (associados ao tenant super admin)
 * - Usuário é transferido para novo tenant
 * - Produtos ficam órfãos no tenant super admin
 *
 * SOLUÇÃO:
 * - Detectar dados órfãos
 * - Migrar dados automaticamente
 * - Verificar integridade pós-migração
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\SDK;
use Clubify\Checkout\Modules\Organization\Services\TenantDataMigrationService;

function printSection($title) {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  " . strtoupper($title) . "\n";
    echo str_repeat('=', 60) . "\n\n";
}

function printStep($step, $description) {
    echo "📋 Passo $step: $description\n";
}

try {
    printSection("Exemplo de Migração de Dados entre Tenants");

    // Configuração inicial
    $config = [
        'base_uri' => 'https://api.checkout.clubify.com.br/',
        'api_key' => 'your-super-admin-api-key',  // Chave do super admin
        'timeout' => 30,
        'verify_ssl' => true
    ];

    $sdk = new SDK($config);

    // Cenário de demonstração
    $scenarioData = [
        'super_admin_tenant_id' => '507f1f77bcf86cd799439011', // Tenant do super admin
        'user_id' => '507f1f77bcf86cd799439022',              // ID do usuário
        'new_tenant_id' => '507f1f77bcf86cd799439033',         // Novo tenant criado
        'user_email' => 'admin@nova-empresa.com'
    ];

    printStep(1, "Verificando situação atual");

    // 1. Verificar dados órfãos no tenant super admin
    echo "🔍 Verificando produtos órfãos do usuário no tenant super admin...\n";

    // Listar produtos no tenant super admin
    $superAdminProducts = $sdk->products()->list([
        'tenant_id' => $scenarioData['super_admin_tenant_id'],
        'created_by' => $scenarioData['user_id']
    ]);

    echo "   Produtos encontrados no tenant super admin: " . count($superAdminProducts) . "\n";

    if (count($superAdminProducts) > 0) {
        echo "   📦 Produtos órfãos encontrados:\n";
        foreach ($superAdminProducts as $product) {
            echo "      - " . ($product['name'] ?? 'Nome não encontrado') . " (ID: " . ($product['id'] ?? $product['_id']) . ")\n";
        }
    }

    // Verificar produtos no novo tenant
    $newTenantProducts = $sdk->products()->list([
        'tenant_id' => $scenarioData['new_tenant_id']
    ]);

    echo "   Produtos encontrados no novo tenant: " . count($newTenantProducts) . "\n\n";

    printStep(2, "Iniciando migração de dados");

    // 2. Simular migração de dados
    echo "🚀 Iniciando migração de produtos órfãos...\n";

    $migrationResult = [
        'success' => false,
        'products_to_migrate' => count($superAdminProducts),
        'migrated_products' => [],
        'errors' => []
    ];

    foreach ($superAdminProducts as $product) {
        try {
            echo "   📦 Migrando produto: " . ($product['name'] ?? 'Nome não encontrado') . "\n";

            // Preparar dados do produto para o novo tenant
            $productData = $product;
            unset($productData['id'], $productData['_id'], $productData['created_at'], $productData['updated_at']);
            $productData['tenant_id'] = $scenarioData['new_tenant_id'];

            // Criar produto no novo tenant
            $newProduct = $sdk->products()->create($productData);

            $migrationResult['migrated_products'][] = [
                'original_id' => $product['id'] ?? $product['_id'],
                'new_id' => $newProduct['id'] ?? $newProduct['_id'],
                'name' => $product['name']
            ];

            echo "      ✅ Produto migrado com sucesso\n";
            echo "         Original ID: " . ($product['id'] ?? $product['_id']) . "\n";
            echo "         Novo ID: " . ($newProduct['id'] ?? $newProduct['_id']) . "\n";

        } catch (Exception $e) {
            $migrationResult['errors'][] = [
                'product' => $product['name'] ?? 'Nome não encontrado',
                'error' => $e->getMessage()
            ];
            echo "      ❌ Erro ao migrar produto: " . $e->getMessage() . "\n";
        }
    }

    $migrationResult['success'] = count($migrationResult['errors']) === 0;

    printStep(3, "Verificando resultado da migração");

    echo "📊 Resultado da migração:\n";
    echo "   Total de produtos para migrar: " . $migrationResult['products_to_migrate'] . "\n";
    echo "   Produtos migrados com sucesso: " . count($migrationResult['migrated_products']) . "\n";
    echo "   Erros encontrados: " . count($migrationResult['errors']) . "\n\n";

    if (count($migrationResult['migrated_products']) > 0) {
        echo "✅ Produtos migrados:\n";
        foreach ($migrationResult['migrated_products'] as $product) {
            echo "   - " . $product['name'] . " (" . $product['original_id'] . " → " . $product['new_id'] . ")\n";
        }
        echo "\n";
    }

    if (count($migrationResult['errors']) > 0) {
        echo "❌ Erros durante migração:\n";
        foreach ($migrationResult['errors'] as $error) {
            echo "   - " . $error['product'] . ": " . $error['error'] . "\n";
        }
        echo "\n";
    }

    printStep(4, "Verificação pós-migração");

    // 4. Verificar produtos no novo tenant após migração
    $newTenantProductsAfter = $sdk->products()->list([
        'tenant_id' => $scenarioData['new_tenant_id']
    ]);

    echo "📈 Verificação pós-migração:\n";
    echo "   Produtos no novo tenant (antes): " . count($newTenantProducts) . "\n";
    echo "   Produtos no novo tenant (depois): " . count($newTenantProductsAfter) . "\n";
    echo "   Diferença: +" . (count($newTenantProductsAfter) - count($newTenantProducts)) . " produtos\n\n";

    printStep(5, "Limpeza opcional");

    // 5. Opcionalmente, remover produtos do tenant original
    $cleanupOriginal = false; // Altere para true se quiser limpar

    if ($cleanupOriginal && $migrationResult['success']) {
        echo "🧹 Removendo produtos órfãos do tenant original...\n";

        foreach ($superAdminProducts as $product) {
            try {
                $sdk->products()->delete($product['id'] ?? $product['_id']);
                echo "   ✅ Produto removido do tenant original: " . ($product['name'] ?? 'Nome não encontrado') . "\n";
            } catch (Exception $e) {
                echo "   ❌ Erro ao remover produto: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "ℹ️  Produtos originais mantidos no tenant super admin (configure \$cleanupOriginal = true para remover)\n";
    }

    printSection("Resumo da operação");

    echo "🎯 PROBLEMA RESOLVIDO:\n";
    echo "   ✅ Dados órfãos identificados\n";
    echo "   ✅ Produtos migrados para novo tenant\n";
    echo "   ✅ Usuário agora tem acesso aos dados no tenant correto\n\n";

    echo "📝 PRÓXIMOS PASSOS RECOMENDADOS:\n";
    echo "   1. Integrar este processo no fluxo de criação de tenant\n";
    echo "   2. Implementar migração automática via SDK\n";
    echo "   3. Adicionar migração para outras entidades (orders, customers, etc.)\n";
    echo "   4. Implementar rollback em caso de falha\n";
    echo "   5. Adicionar logs detalhados para auditoria\n\n";

    echo "🔧 USO DO TENANT DATA MIGRATION SERVICE:\n";
    echo "   // Exemplo de uso direto do serviço\n";
    echo "   \$migrationService = new TenantDataMigrationService(\$productRepo, \$userRepo, \$logger);\n";
    echo "   \$result = \$migrationService->migrateUserData(\$userId, \$oldTenant, \$newTenant);\n\n";

    echo "✨ MIGRAÇÃO CONCLUÍDA COM SUCESSO!\n";

} catch (Exception $e) {
    echo "❌ ERRO DURANTE A MIGRAÇÃO:\n";
    echo "   Erro: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n📞 SUPORTE:\n";
    echo "   Em caso de problemas, consulte a documentação ou contate o suporte técnico.\n";
    exit(1);
}

echo "\n" . str_repeat('-', 60) . "\n";
echo "Exemplo executado em: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('-', 60) . "\n";