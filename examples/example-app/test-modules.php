<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"\'');
        }
    }
}

echo "🧩 TESTE DE TODOS OS MÓDULOS CLUBIFY SDK\n";
echo "=========================================\n\n";

$config = [
    'credentials' => [
        'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? 'demo_tenant',
        'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'demo_key',
        'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
    ],
    'http' => [
        'timeout' => 8000,
        'connect_timeout' => 5,
        'retries' => 2
    ],
    'endpoints' => [
        'base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug'
    ]
];

try {
    echo "1. Inicializando SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    $initResult = $sdk->initialize();
    echo "✅ SDK inicializado com sucesso\n\n";

    $modules = [
        'organization' => 'Organization',
        'products' => 'Products',
        'checkout' => 'Checkout',
        'payments' => 'Payments',
        'customers' => 'Customers',
        'webhooks' => 'Webhooks',
        'tracking' => 'Tracking',
        'userManagement' => 'User Management',
        'subscriptions' => 'Subscriptions'
    ];

    $successCount = 0;
    $totalCount = count($modules);

    foreach ($modules as $moduleMethod => $moduleName) {
        try {
            echo "2. Testando módulo {$moduleName}...\n";

            // Criar instância do módulo
            $module = $sdk->$moduleMethod();
            echo "   ✅ Módulo criado: " . get_class($module) . "\n";

            // Verificar se está inicializado
            $isInitialized = $module->isInitialized();
            echo "   ✅ Inicializado: " . ($isInitialized ? 'SIM' : 'NÃO') . "\n";

            // Testar métodos básicos
            $name = $module->getName();
            $version = $module->getVersion();
            $isAvailable = $module->isAvailable();

            echo "   ✅ Nome: {$name}\n";
            echo "   ✅ Versão: {$version}\n";
            echo "   ✅ Disponível: " . ($isAvailable ? 'SIM' : 'NÃO') . "\n";

            // Testar método getStatus se existir
            if (method_exists($module, 'getStatus')) {
                $status = $module->getStatus();
                echo "   ✅ Status: " . json_encode($status) . "\n";
            }

            echo "   🎉 MÓDULO {$moduleName} - SUCESSO!\n\n";
            $successCount++;

        } catch (\Exception $e) {
            echo "   ❌ ERRO no módulo {$moduleName}: " . $e->getMessage() . "\n";
            echo "   📁 Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
        }
    }

    echo "📊 RESUMO FINAL:\n";
    echo "================\n";
    echo "✅ Módulos funcionais: {$successCount}/{$totalCount}\n";
    echo "📈 Taxa de sucesso: " . round(($successCount / $totalCount) * 100, 2) . "%\n";

    if ($successCount === $totalCount) {
        echo "🎉 TODOS OS MÓDULOS FUNCIONANDO PERFEITAMENTE!\n";
    } else {
        echo "⚠️  Alguns módulos precisam de correção.\n";
    }

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}