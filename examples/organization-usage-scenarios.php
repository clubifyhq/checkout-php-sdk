<?php

/**
 * Organization API Keys - 3 Cenários de Uso Principais
 *
 * Este arquivo demonstra como usar Organization API Keys em 3 cenários práticos:
 * 1. Autenticação Básica de Organização
 * 2. Uso Multi-Tenant (Franquias/Filiais)
 * 3. Operações Administrativas
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "🏢 Organization API Keys - Cenários de Uso Práticos\n";
echo "==================================================\n\n";

// Configurações base
$baseConfig = [
    'environment' => 'sandbox',
    'base_url' => 'https://checkout.svelve.com/api/v1',
    'timeout' => 30000
];

// ====================================================================
// CENÁRIO 1: AUTENTICAÇÃO BÁSICA DE ORGANIZAÇÃO
// ====================================================================
echo "📋 CENÁRIO 1: Autenticação Básica de Organização\n";
echo "===============================================\n";
echo "Uso: Dashboard administrativo, relatórios gerais, backup de dados\n\n";

try {
    // Configurar SDK com Organization API Key
    $organizationConfig = array_merge($baseConfig, [
        'organization_id' => '68d94e3a878451ed8bb9d873',
        'api_key' => 'clb_org_test_813109fb9f2b4b74239df20fa1a5948a',
        'scope' => 'organization'
    ]);

    $orgSdk = new ClubifyCheckoutSDK($organizationConfig);

    // Passo 1: Autenticar como organização
    echo "🔐 Passo 1: Autenticação como Organização\n";
    $authResult = $orgSdk->authenticateAsOrganization($organizationConfig['organization_id'], $organizationConfig['api_key']);

    if ($authResult['success']) {
        echo "✅ Autenticação bem-sucedida!\n";
        echo "   Organization: {$authResult['organization_name']}\n";
        echo "   Tenants disponíveis: " . count($authResult['available_tenants']) . "\n";

        // Passo 2: Obter informações gerais da organização
        echo "\n📊 Passo 2: Dashboard da Organização\n";

        if (method_exists($orgSdk, 'organization')) {
            $dashboardData = $orgSdk->organization()->getDashboard([
                'period' => '30d',
                'include_tenants' => true,
                'metrics' => ['revenue', 'orders', 'customers', 'products']
            ]);

            if ($dashboardData['success']) {
                $data = $dashboardData['data'];
                echo "✅ Dashboard carregado:\n";
                echo "   Revenue Total: R$ " . number_format($data['total_revenue'] / 100, 2) . "\n";
                echo "   Pedidos (30d): " . number_format($data['total_orders']) . "\n";
                echo "   Clientes Ativos: " . number_format($data['active_customers']) . "\n";
                echo "   Produtos Total: " . number_format($data['total_products']) . "\n";
            }
        }

        // Passo 3: Backup de configurações
        echo "\n💾 Passo 3: Backup de Configurações\n";

        try {
            $backupData = $orgSdk->organization()->exportConfiguration([
                'include_tenants' => true,
                'include_products' => true,
                'include_settings' => true,
                'format' => 'json'
            ]);

            if ($backupData['success']) {
                echo "✅ Backup de configurações criado\n";
                echo "   Tamanho: " . round(strlen(json_encode($backupData['data'])) / 1024, 2) . " KB\n";
                echo "   Tenants incluídos: " . count($backupData['data']['tenants']) . "\n";
            }
        } catch (Exception $e) {
            echo "ℹ️  Backup não disponível: " . $e->getMessage() . "\n";
        }

    } else {
        echo "❌ Falha na autenticação: " . $authResult['error'] . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro no Cenário 1: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 60) . "\n\n";

// ====================================================================
// CENÁRIO 2: USO MULTI-TENANT (FRANQUIAS/FILIAIS)
// ====================================================================
echo "🏪 CENÁRIO 2: Uso Multi-Tenant (Franquias/Filiais)\n";
echo "=================================================\n";
echo "Uso: Gestão de múltiplas lojas, consolidação de dados, relatórios por região\n\n";

try {
    // Configurar SDK com Cross-Tenant API Key
    $crossTenantConfig = array_merge($baseConfig, [
        'organization_id' => 'org_example_123456789',
        'api_key' => 'ct_live_fedcba0987654321fedcba0987654321',
        'scope' => 'CROSS_TENANT',
        'allowed_tenants' => ['tenant_loja_sp', 'tenant_loja_rj', 'tenant_loja_mg']
    ]);

    $multiSdk = new ClubifyCheckoutSDK($crossTenantConfig);

    // Passo 1: Autenticar com acesso multi-tenant
    echo "🔐 Passo 1: Autenticação Multi-Tenant\n";
    $authResult = $multiSdk->authenticateAsOrganization([
        'organization_id' => $crossTenantConfig['organization_id'],
        'api_key' => $crossTenantConfig['api_key'],
        'scope' => 'CROSS_TENANT'
    ]);

    if ($authResult['success']) {
        echo "✅ Autenticação multi-tenant bem-sucedida!\n";
        echo "   Tenants permitidos: " . count($authResult['allowed_tenants']) . "\n";

        foreach ($authResult['allowed_tenants'] as $tenant) {
            echo "   - {$tenant['name']} ({$tenant['location']})\n";
        }

        // Passo 2: Relatório consolidado de vendas
        echo "\n📊 Passo 2: Relatório Consolidado de Vendas\n";

        $salesReport = [];
        foreach ($authResult['allowed_tenants'] as $tenant) {
            try {
                // Alternar contexto para cada loja
                $switchResult = $multiSdk->organization()->switchToTenant($tenant['id']);

                if ($switchResult['success']) {
                    // Obter dados de vendas da loja
                    $tenantSales = $multiSdk->orders()->getStatistics([
                        'period' => '7d',
                        'metrics' => ['revenue', 'orders_count', 'avg_order_value']
                    ]);

                    if ($tenantSales['success']) {
                        $salesReport[$tenant['name']] = $tenantSales['data'];
                        echo "   ✅ {$tenant['name']}: R$ " .
                             number_format($tenantSales['data']['revenue'] / 100, 2) .
                             " ({$tenantSales['data']['orders_count']} pedidos)\n";
                    }
                }
            } catch (Exception $e) {
                echo "   ⚠️  {$tenant['name']}: Erro - " . $e->getMessage() . "\n";
            }
        }

        // Passo 3: Sincronização de produtos entre lojas
        echo "\n🔄 Passo 3: Sincronização de Produtos\n";

        if (method_exists($multiSdk, 'products')) {
            try {
                // Obter produtos populares da loja principal
                $popularProducts = $multiSdk->products()->getPopular([
                    'tenant_id' => 'tenant_loja_sp', // Loja matriz
                    'period' => '30d',
                    'limit' => 5
                ]);

                if ($popularProducts['success'] && !empty($popularProducts['data'])) {
                    echo "✅ Produtos populares identificados:\n";

                    foreach ($popularProducts['data'] as $product) {
                        echo "   - {$product['name']} (vendas: {$product['sales_count']})\n";

                        // Sincronizar para outras lojas
                        foreach (['tenant_loja_rj', 'tenant_loja_mg'] as $targetTenant) {
                            try {
                                $syncResult = $multiSdk->products()->syncToTenant([
                                    'product_id' => $product['id'],
                                    'target_tenant_id' => $targetTenant,
                                    'adapt_pricing' => true,
                                    'copy_inventory' => false
                                ]);

                                if ($syncResult['success']) {
                                    echo "     → Sincronizado para " . substr($targetTenant, -2) . "\n";
                                }
                            } catch (Exception $e) {
                                echo "     → Erro ao sincronizar: " . $e->getMessage() . "\n";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                echo "ℹ️  Sincronização não disponível: " . $e->getMessage() . "\n";
            }
        }

        // Passo 4: Análise comparativa de performance
        echo "\n📈 Passo 4: Análise Comparativa de Performance\n";

        if (!empty($salesReport)) {
            echo "✅ Comparativo de Performance (últimos 7 dias):\n";

            $totalRevenue = 0;
            $totalOrders = 0;

            foreach ($salesReport as $storeName => $data) {
                $revenue = $data['revenue'] / 100;
                $orders = $data['orders_count'];
                $avgOrder = $data['avg_order_value'] / 100;

                echo "   📍 $storeName:\n";
                echo "      Revenue: R$ " . number_format($revenue, 2) . "\n";
                echo "      Pedidos: $orders\n";
                echo "      Ticket Médio: R$ " . number_format($avgOrder, 2) . "\n";

                $totalRevenue += $revenue;
                $totalOrders += $orders;
            }

            echo "\n   🎯 TOTAL CONSOLIDADO:\n";
            echo "      Revenue: R$ " . number_format($totalRevenue, 2) . "\n";
            echo "      Pedidos: $totalOrders\n";
            echo "      Ticket Médio Geral: R$ " . number_format($totalRevenue / max($totalOrders, 1), 2) . "\n";
        }

    } else {
        echo "❌ Falha na autenticação multi-tenant: " . $authResult['error'] . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro no Cenário 2: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 60) . "\n\n";

// ====================================================================
// CENÁRIO 3: OPERAÇÕES ADMINISTRATIVAS
// ====================================================================
echo "⚙️  CENÁRIO 3: Operações Administrativas\n";
echo "======================================\n";
echo "Uso: Gestão de API keys, auditoria, configuração de sistema, troubleshooting\n\n";

try {
    // Usar SDK com privilégios administrativos
    $adminConfig = array_merge($baseConfig, [
        'organization_id' => 'org_example_123456789',
        'api_key' => 'org_admin_9876543210fedcba9876543210fedcba',
        'scope' => 'ORGANIZATION',
        'admin_mode' => true
    ]);

    $adminSdk = new ClubifyCheckoutSDK($adminConfig);

    // Passo 1: Auditoria de API Keys
    echo "🔍 Passo 1: Auditoria de API Keys\n";

    if (method_exists($adminSdk, 'organization')) {
        $apiKeysAudit = $adminSdk->organization()->auditApiKeys([
            'include_usage_stats' => true,
            'check_security' => true,
            'period' => '30d'
        ]);

        if ($apiKeysAudit['success']) {
            $audit = $apiKeysAudit['data'];
            echo "✅ Auditoria de API Keys concluída:\n";
            echo "   Total de Keys: {$audit['total_keys']}\n";
            echo "   Keys Ativas: {$audit['active_keys']}\n";
            echo "   Keys Expiradas: {$audit['expired_keys']}\n";
            echo "   Uso Total (30d): " . number_format($audit['total_requests']) . " requests\n";

            // Alertas de segurança
            if (!empty($audit['security_alerts'])) {
                echo "\n⚠️  Alertas de Segurança:\n";
                foreach ($audit['security_alerts'] as $alert) {
                    echo "   - {$alert['type']}: {$alert['message']}\n";
                    echo "     Key ID: {$alert['key_id']} | Severity: {$alert['severity']}\n";
                }
            }

            // Top keys por uso
            if (!empty($audit['top_keys_by_usage'])) {
                echo "\n📊 Top API Keys por Uso:\n";
                foreach (array_slice($audit['top_keys_by_usage'], 0, 3) as $keyData) {
                    echo "   - {$keyData['name']}: " . number_format($keyData['requests']) . " requests\n";
                    echo "     Última atividade: {$keyData['last_used_at']}\n";
                }
            }
        }
    }

    // Passo 2: Gestão automatizada de credenciais
    echo "\n🔑 Passo 2: Gestão de Credenciais\n";

    try {
        // Rotacionar keys antigas
        $rotationResult = $adminSdk->organization()->rotateExpiredKeys([
            'max_age_days' => 90,
            'grace_period_hours' => 24,
            'notify_users' => true
        ]);

        if ($rotationResult['success']) {
            $rotation = $rotationResult['data'];
            echo "✅ Rotação de credenciais executada:\n";
            echo "   Keys rotacionadas: {$rotation['rotated_count']}\n";
            echo "   Usuários notificados: {$rotation['notifications_sent']}\n";

            if (!empty($rotation['rotated_keys'])) {
                foreach ($rotation['rotated_keys'] as $key) {
                    echo "   - {$key['name']}: Nova key gerada\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "ℹ️  Rotação automática não disponível: " . $e->getMessage() . "\n";
    }

    // Passo 3: Troubleshooting e diagnostics
    echo "\n🔧 Passo 3: Diagnostics do Sistema\n";

    $diagnostics = [
        'connectivity' => 'OK',
        'api_health' => 'OK',
        'database_status' => 'OK',
        'cache_status' => 'OK'
    ];

    // Teste de conectividade
    try {
        $healthCheck = $adminSdk->organization()->healthCheck([
            'deep_check' => true,
            'include_tenants' => true
        ]);

        if ($healthCheck['success']) {
            $health = $healthCheck['data'];
            echo "✅ Health Check do Sistema:\n";
            echo "   API Status: {$health['api_status']}\n";
            echo "   Database: {$health['database_status']}\n";
            echo "   Cache: {$health['cache_status']}\n";
            echo "   Response Time: {$health['avg_response_time']}ms\n";

            // Status por tenant
            if (!empty($health['tenants_status'])) {
                echo "\n   Status por Tenant:\n";
                foreach ($health['tenants_status'] as $tenant => $status) {
                    $icon = $status === 'healthy' ? '✅' : '⚠️';
                    echo "   $icon $tenant: $status\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Health check falhou: " . $e->getMessage() . "\n";
    }

    // Passo 4: Configuração de monitoramento
    echo "\n📊 Passo 4: Configuração de Monitoramento\n";

    try {
        $monitoringSetup = $adminSdk->organization()->setupMonitoring([
            'alerts' => [
                'high_error_rate' => ['threshold' => 5, 'window' => '5m'],
                'slow_response' => ['threshold' => 2000, 'window' => '1m'],
                'quota_exceeded' => ['threshold' => 90, 'window' => '1h']
            ],
            'webhooks' => [
                'endpoint' => 'https://your-monitoring.com/webhooks/clubify',
                'events' => ['alert.triggered', 'system.error', 'quota.warning']
            ],
            'reporting' => [
                'daily_summary' => true,
                'weekly_report' => true,
                'email' => 'admin@yourcompany.com'
            ]
        ]);

        if ($monitoringSetup['success']) {
            echo "✅ Monitoramento configurado:\n";
            echo "   Alertas ativos: " . count($monitoringSetup['data']['active_alerts']) . "\n";
            echo "   Webhook endpoint: configurado\n";
            echo "   Relatórios: habilitados\n";
        }
    } catch (Exception $e) {
        echo "ℹ️  Configuração de monitoramento: " . $e->getMessage() . "\n";
    }

    // Passo 5: Backup e disaster recovery
    echo "\n💾 Passo 5: Backup e Disaster Recovery\n";

    try {
        $backupStrategy = $adminSdk->organization()->configureBackup([
            'schedule' => 'daily',
            'retention_days' => 30,
            'include_data' => ['configurations', 'products', 'customers'],
            'storage' => [
                'type' => 's3',
                'bucket' => 'your-backup-bucket',
                'encryption' => 'AES256'
            ],
            'notification' => [
                'success' => true,
                'failure' => true,
                'email' => 'admin@yourcompany.com'
            ]
        ]);

        if ($backupStrategy['success']) {
            echo "✅ Estratégia de backup configurada:\n";
            echo "   Frequência: {$backupStrategy['data']['schedule']}\n";
            echo "   Retenção: {$backupStrategy['data']['retention_days']} dias\n";
            echo "   Próximo backup: {$backupStrategy['data']['next_backup_at']}\n";
        }
    } catch (Exception $e) {
        echo "ℹ️  Configuração de backup: " . $e->getMessage() . "\n";
    }

} catch (Exception $e) {
    echo "❌ Erro no Cenário 3: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "🎉 Demonstração dos 3 Cenários Concluída!\n";
echo str_repeat("=", 60) . "\n\n";

echo "📋 RESUMO DOS CENÁRIOS:\n\n";

echo "1. 📊 AUTENTICAÇÃO BÁSICA DE ORGANIZAÇÃO:\n";
echo "   ✓ Dashboard consolidado\n";
echo "   ✓ Backup de configurações\n";
echo "   ✓ Visão geral da organização\n";
echo "   🎯 Ideal para: Administradores, relatórios executivos\n\n";

echo "2. 🏪 USO MULTI-TENANT:\n";
echo "   ✓ Gestão de múltiplas lojas\n";
echo "   ✓ Relatórios consolidados\n";
echo "   ✓ Sincronização de produtos\n";
echo "   ✓ Análise comparativa\n";
echo "   🎯 Ideal para: Franquias, redes de lojas, multi-marca\n\n";

echo "3. ⚙️  OPERAÇÕES ADMINISTRATIVAS:\n";
echo "   ✓ Auditoria de segurança\n";
echo "   ✓ Gestão de credenciais\n";
echo "   ✓ Diagnostics do sistema\n";
echo "   ✓ Monitoramento e alertas\n";
echo "   ✓ Backup e disaster recovery\n";
echo "   🎯 Ideal para: DevOps, SysAdmins, equipe técnica\n\n";

echo "💡 PRÓXIMOS PASSOS:\n";
echo "   1. Configurar suas credenciais nos exemplos\n";
echo "   2. Testar cada cenário no seu ambiente\n";
echo "   3. Adaptar os códigos para suas necessidades específicas\n";
echo "   4. Implementar monitoramento e alertas\n";
echo "   5. Documentar processos internos da equipe\n\n";

echo "📚 Para mais informações, consulte:\n";
echo "   - Documentação completa da API\n";
echo "   - Guias de melhores práticas\n";
echo "   - Exemplos adicionais na pasta /examples/\n\n";

?>