<?php

/**
 * SIMPLE LARAVEL INTEGRATION EXAMPLE
 *
 * Uma versão simplificada que usa configurações do Laravel (.env) mas
 * não depende do bootstrap completo do framework.
 *
 * FUNCIONALIDADES:
 * ================
 * ✅ Usa configurações do .env do Laravel
 * ✅ Carrega autoload do Composer (inclui SDK)
 * ✅ Inicialização do SDK com configurações Laravel
 * ✅ Demonstra operações básicas do SDK
 * ✅ Sem dependências problemáticas do Laravel
 * ✅ Execução rápida e limpa
 *
 * USO:
 * ====
 * php simple-laravel-example.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Carrega variáveis do .env do Laravel
 */
function loadEnvFile($path) {
    if (!file_exists($path)) {
        return false;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, '"\'');

            // Não sobrescrever se já existir
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }
    return true;
}

/**
 * Helper para obter valor do env
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

/**
 * Logging simples
 */
function logStep(string $message, string $level = 'info'): void {
    $timestamp = date('Y-m-d H:i:s');
    $icon = match($level) {
        'info' => '🔄',
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'debug' => '🔍'
    };

    echo "[{$timestamp}] {$icon} {$message}\n";
}

// =======================================================================
// INÍCIO DO SCRIPT
// =======================================================================

try {
    echo "=================================================================\n";
    echo "🚀 CLUBIFY CHECKOUT - SIMPLE LARAVEL INTEGRATION EXAMPLE\n";
    echo "=================================================================\n\n";

    // Carregar .env do Laravel
    $envPath = __DIR__ . '/.env';
    if (loadEnvFile($envPath)) {
        logStep("Arquivo .env carregado com sucesso", 'success');
    } else {
        logStep("Arquivo .env não encontrado - usando valores padrão", 'warning');
    }

    // Mostrar configurações carregadas
    logStep("Configurações carregadas:", 'info');
    logStep("   APP_ENV: " . env('APP_ENV', 'not-set'), 'debug');
    logStep("   Tenant ID: " . env('SUPER_ADMIN_DEFAULT_TENANT', 'not-set'), 'debug');
    logStep("   Environment: " . env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'not-set'), 'debug');
    logStep("   Base URL: " . env('CLUBIFY_CHECKOUT_BASE_URL', 'not-set'), 'debug');

    // Verificar se temos configurações mínimas
    $tenantId = env('SUPER_ADMIN_DEFAULT_TENANT');
    $apiKey = env('CLUBIFY_SUPER_ADMIN_API_KEY');
    $apiSecret = env('CLUBIFY_SUPER_ADMIN_API_SECRET');

    if (!$tenantId || !$apiKey) {
        logStep("Configurações mínimas não encontradas no .env", 'error');
        logStep("Configure SUPER_ADMIN_DEFAULT_TENANT e CLUBIFY_SUPER_ADMIN_API_KEY", 'info');
        exit(1);
    }

    // Configurar SDK usando dados do .env
    $config = [
        'credentials' => [
            'tenant_id' => $tenantId,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ],
        'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'sandbox'),
        'api' => [
            'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', 'https://checkout.svelve.com/api/v1'),
        ],
        'http' => [
            'timeout' => (int) env('CLUBIFY_CHECKOUT_TIMEOUT', 30),
            'connect_timeout' => (int) env('CLUBIFY_CHECKOUT_CONNECT_TIMEOUT', 10),
            'retry' => [
                'enabled' => true,
                'attempts' => (int) env('CLUBIFY_CHECKOUT_RETRY_ATTEMPTS', 3),
                'delay' => (int) env('CLUBIFY_CHECKOUT_RETRY_DELAY', 1000),
            ],
        ],
        'cache' => [
            'enabled' => (bool) env('CLUBIFY_CHECKOUT_CACHE_ENABLED', true),
            'default_ttl' => (int) env('CLUBIFY_CHECKOUT_CACHE_TTL', 3600),
        ],
        'features' => [
            'auto_initialize' => (bool) env('CLUBIFY_CHECKOUT_AUTO_INITIALIZE', true),
        ],
    ];

    logStep("Criando instância do SDK...", 'info');
    $sdk = new ClubifyCheckoutSDK($config);

    logStep("SDK criado com sucesso!", 'success');
    logStep("   Version: " . $sdk->getVersion(), 'info');
    logStep("   Initialized: " . ($sdk->isInitialized() ? 'YES' : 'NO'), 'info');
    logStep("   Authenticated: " . ($sdk->isAuthenticated() ? 'YES' : 'NO'), 'info');

    // Tentar inicialização se não estiver inicializado
    if (!$sdk->isInitialized()) {
        logStep("Tentando inicializar SDK...", 'info');
        try {
            $result = $sdk->initialize(true); // skip health check
            if ($result['success']) {
                logStep("SDK inicializado com sucesso!", 'success');
                logStep("   Tenant ID: " . ($result['tenant_id'] ?? 'N/A'), 'info');
                logStep("   Environment: " . ($result['environment'] ?? 'N/A'), 'info');
                logStep("   Authenticated: " . (($result['authenticated'] ?? false) ? 'YES' : 'NO'), 'info');
            } else {
                logStep("Falha na inicialização: " . json_encode($result), 'warning');
            }
        } catch (Exception $e) {
            logStep("Erro na inicialização: " . $e->getMessage(), 'warning');
            logStep("Continuando com SDK não inicializado (modo demonstração)", 'info');
        }
    }

    // ===============================================
    // DEMONSTRAÇÃO DE FUNCIONALIDADES
    // ===============================================

    echo "\n=== TESTE DE MÓDULOS DISPONÍVEIS ===\n";

    $modules = ['products', 'customers', 'payments', 'checkout'];
    foreach ($modules as $moduleName) {
        try {
            logStep("Testando módulo: $moduleName", 'info');

            switch ($moduleName) {
                case 'products':
                    $module = $sdk->products();
                    logStep("   ✅ Módulo products disponível", 'success');

                    // Tentar listar produtos
                    try {
                        $products = $module->list(['limit' => 3]);
                        $count = count($products['data'] ?? []);
                        logStep("   📦 Produtos encontrados: $count", 'info');
                    } catch (Exception $e) {
                        logStep("   ⚠️ Erro ao listar produtos: " . $e->getMessage(), 'warning');
                    }
                    break;

                case 'customers':
                    $module = $sdk->customers();
                    logStep("   ✅ Módulo customers disponível", 'success');
                    break;

                case 'payments':
                    $module = $sdk->payments();
                    logStep("   ✅ Módulo payments disponível", 'success');
                    break;

                case 'checkout':
                    $module = $sdk->checkout();
                    logStep("   ✅ Módulo checkout disponível", 'success');
                    break;
            }

        } catch (Exception $e) {
            logStep("   ❌ Erro no módulo $moduleName: " . $e->getMessage(), 'warning');
        }
    }

    // ===============================================
    // TESTE DE SUPER ADMIN (SE DISPONÍVEL)
    // ===============================================

    echo "\n=== TESTE DE SUPER ADMIN ===\n";

    $superAdminEnabled = (bool) env('SUPER_ADMIN_ENABLED', false);
    $superAdminApiKey = env('CLUBIFY_SUPER_ADMIN_API_KEY');

    if ($superAdminEnabled && $superAdminApiKey) {
        logStep("Super Admin habilitado - tentando acesso...", 'info');

        try {
            // Preparar credenciais de super admin
            $superAdminCredentials = [
                'api_key' => $superAdminApiKey,
                'api_secret' => env('CLUBIFY_SUPER_ADMIN_API_SECRET'),
                'username' => env('CLUBIFY_SUPER_ADMIN_USERNAME'),
                'password' => env('CLUBIFY_SUPER_ADMIN_PASSWORD'),
                'tenant_id' => env('SUPER_ADMIN_DEFAULT_TENANT', $tenantId)
            ];

            $initResult = $sdk->initializeAsSuperAdmin($superAdminCredentials);
            logStep("   ✅ Super Admin inicializado com sucesso!", 'success');

            // Tentar listar tenants
            try {
                $tenants = $sdk->superAdmin()->listTenants(['limit' => 3]);
                $tenantsData = $tenants['data']['tenants'] ?? $tenants['data'] ?? [];
                $count = count($tenantsData);
                logStep("   🏢 Tenants encontrados: $count", 'info');

                if ($count > 0) {
                    foreach (array_slice($tenantsData, 0, 2) as $tenant) {
                        $name = $tenant['name'] ?? 'Sem nome';
                        $domain = $tenant['domain'] ?? $tenant['subdomain'] ?? 'sem domínio';
                        logStep("   - $name ($domain)", 'debug');
                    }
                }

            } catch (Exception $e) {
                logStep("   ⚠️ Erro ao listar tenants: " . $e->getMessage(), 'warning');
            }

        } catch (Exception $e) {
            logStep("   ❌ Erro no Super Admin: " . $e->getMessage(), 'warning');
        }

    } else {
        logStep("Super Admin não habilitado ou não configurado", 'info');
        logStep("   Configure SUPER_ADMIN_ENABLED=true no .env para habilitar", 'debug');
    }

    // ===============================================
    // DEMONSTRAÇÃO COM DADOS DO .ENV (SE DISPONÍVEL)
    // ===============================================

    echo "\n=== EXEMPLO COM DADOS PERSONALIZADOS ===\n";

    $exampleOrgName = env('EXAMPLE_ORG_NAME');
    $exampleProductName = env('EXAMPLE_PRODUCT_NAME');

    if ($exampleOrgName || $exampleProductName) {
        logStep("Dados de exemplo encontrados no .env:", 'info');

        if ($exampleOrgName) {
            logStep("   Organização: $exampleOrgName", 'info');
        }

        if ($exampleProductName) {
            logStep("   Produto: $exampleProductName", 'info');

            // Exemplo de criação/busca de produto
            try {
                $productData = [
                    'name' => $exampleProductName,
                    'description' => env('EXAMPLE_PRODUCT_DESC', 'Produto de exemplo'),
                    'price' => [
                        'amount' => (int) env('EXAMPLE_PRODUCT_PRICE', 9999),
                        'currency' => env('EXAMPLE_PRODUCT_CURRENCY', 'BRL')
                    ],
                    'type' => 'digital'
                ];

                logStep("   Simulando criação do produto...", 'debug');
                logStep("   (Operação não executada - apenas demonstração)", 'debug');

            } catch (Exception $e) {
                logStep("   ⚠️ Erro na simulação: " . $e->getMessage(), 'warning');
            }
        }

    } else {
        logStep("Nenhum dado de exemplo configurado no .env", 'info');
        logStep("   Configure EXAMPLE_ORG_NAME, EXAMPLE_PRODUCT_NAME, etc. para personalizar", 'debug');
    }

    // ===============================================
    // RELATÓRIO FINAL
    // ===============================================

    echo "\n" . str_repeat("=", 65) . "\n";
    echo "📊 RELATÓRIO FINAL\n";
    echo str_repeat("=", 65) . "\n";

    logStep("Execução concluída com sucesso!", 'success');
    logStep("Resumo:", 'info');
    logStep("   ✅ Configurações do .env: Carregadas", 'success');
    logStep("   ✅ SDK: Instanciado", 'success');
    logStep("   ✅ Módulos: Testados", 'success');
    logStep("   ✅ Status: " . ($sdk->isInitialized() ? 'Inicializado' : 'Não inicializado'), 'info');

    echo "\n💡 PRÓXIMOS PASSOS:\n";
    logStep("1. Configure mais variáveis no .env conforme necessário", 'info');
    logStep("2. Use este script como base para operações mais complexas", 'info');
    logStep("3. Integre com seu sistema Laravel existente", 'info');

    echo "\n🎉 Simple Laravel Integration executado com sucesso!\n";

} catch (Exception $e) {
    logStep("ERRO FATAL: " . $e->getMessage(), 'error');
    logStep("Arquivo: " . $e->getFile() . ":" . $e->getLine(), 'error');

    if (method_exists($e, 'getTraceAsString')) {
        echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    }

    exit(1);
}