<?php

/**
 * Quick Fix: Migração de Dados Órfãos - Clubify Checkout SDK
 *
 * Script rápido para resolver o problema específico identificado:
 * - Produtos criados ficam no tenant super admin
 * - Após transferir usuário para novo tenant, produtos ficam órfãos
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// ===========================
// CONFIGURAÇÃO - AJUSTE AQUI
// ===========================

$config = [
    'base_uri' => 'https://api.checkout.clubify.com.br/',
    'api_key' => 'your-super-admin-api-key',  // Sua chave super admin
    'timeout' => 30,
    'verify_ssl' => true
];

// IDs do cenário (ajuste com seus dados reais)
$superAdminTenantId = '507f1f77bcf86cd799439011'; // Tenant super admin
$newTenantId = '507f1f77bcf86cd799439033';        // Novo tenant criado
$userId = '507f1f77bcf86cd799439022';             // ID do usuário

echo "🚀 QUICK FIX - Migração de Dados Órfãos\n";
echo "=====================================\n\n";

try {
    // Inicializar SDK
    $sdk = new ClubifyCheckoutSDK($config);

    echo "✅ SDK inicializado com sucesso\n\n";

    // 1. VERIFICAR DADOS ÓRFÃOS
    echo "🔍 Verificando dados órfãos...\n";

    $orphanedData = $sdk->findUserOrphanedData($userId, $superAdminTenantId);

    if (isset($orphanedData['total_orphaned']) && $orphanedData['total_orphaned'] > 0) {
        echo "   📦 Encontrados {$orphanedData['total_orphaned']} itens órfãos\n";

        if (isset($orphanedData['orphaned_items']['products'])) {
            $productsCount = $orphanedData['orphaned_items']['products']['count'];
            echo "   📦 Produtos órfãos: {$productsCount}\n";
        }
        echo "\n";

        // 2. EXECUTAR MIGRAÇÃO
        echo "🚚 Iniciando migração...\n";

        $migrationResult = $sdk->migrateUserDataBetweenTenants(
            $userId,
            $superAdminTenantId,
            $newTenantId
        );

        // 3. EXIBIR RESULTADO
        if ($migrationResult['success']) {
            echo "✅ Migração concluída com sucesso!\n\n";

            if (isset($migrationResult['migrations']['products'])) {
                $productMigration = $migrationResult['migrations']['products'];
                echo "📦 Produtos migrados: {$productMigration['migrated']}/{$productMigration['total_found']}\n";

                if (count($productMigration['errors']) > 0) {
                    echo "⚠️  Erros durante migração de produtos: " . count($productMigration['errors']) . "\n";
                }
            }
        } else {
            echo "❌ Falha na migração\n";
            if (isset($migrationResult['error'])) {
                echo "   Erro: {$migrationResult['error']}\n";
            }
            if (isset($migrationResult['errors']) && count($migrationResult['errors']) > 0) {
                echo "   Erros adicionais:\n";
                foreach ($migrationResult['errors'] as $error) {
                    echo "   - $error\n";
                }
            }
        }

    } else {
        echo "✅ Nenhum dado órfão encontrado!\n";
        echo "   O usuário já tem todos os dados no tenant correto.\n";
    }

    echo "\n🎯 PROBLEMA RESOLVIDO!\n";
    echo "=====================================\n";
    echo "O usuário agora pode acessar seus dados no novo tenant.\n\n";

} catch (Exception $e) {
    echo "❌ ERRO:\n";
    echo "   {$e->getMessage()}\n";
    echo "   Arquivo: {$e->getFile()}:{$e->getLine()}\n\n";

    echo "💡 DICAS PARA RESOLUÇÃO:\n";
    echo "   1. Verifique se as credenciais estão corretas\n";
    echo "   2. Confirme se os IDs dos tenants existem\n";
    echo "   3. Verifique se o usuário existe no sistema\n";
    echo "   4. Confirme a conectividade com a API\n\n";

    exit(1);
}

echo "📚 COMO USAR ESTE SCRIPT:\n";
echo "   1. Ajuste as configurações no início do arquivo\n";
echo "   2. Substitua os IDs pelos valores reais do seu sistema\n";
echo "   3. Execute: php quick-fix-orphaned-data.php\n\n";

echo "📝 PRÓXIMAS MELHORIAS SUGERIDAS:\n";
echo "   - Integrar migração automática no fluxo de criação de tenant\n";
echo "   - Adicionar migração para customers e orders\n";
echo "   - Implementar rollback automático em caso de falha\n";
echo "   - Adicionar logs detalhados para auditoria\n\n";

echo "✨ Script executado em: " . date('Y-m-d H:i:s') . "\n";