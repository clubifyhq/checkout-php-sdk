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

echo "🐛 DEBUG ESPECÍFICO DO MÓDULO PRODUCTS\n";
echo "=====================================\n\n";

$config = [
    'credentials' => [
        'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? 'demo_tenant',
        'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'demo_key',
        'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
    ],
    'http' => ['timeout' => 8000],
    'endpoints' => ['base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'],
    'logging' => ['enabled' => true, 'level' => 'debug']
];

try {
    echo "1. Criando SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK criado\n\n";

    echo "2. Inicializando SDK...\n";
    $initResult = $sdk->initialize();
    echo "✅ SDK inicializado: " . json_encode($initResult) . "\n\n";

    echo "3. Acessando módulo Products...\n";
    $products = $sdk->products();
    echo "✅ Módulo Products obtido: " . get_class($products) . "\n\n";

    echo "4. Verificando status de inicialização...\n";
    $isInitialized = $products->isInitialized();
    echo "✅ isInitialized(): " . ($isInitialized ? 'TRUE' : 'FALSE') . "\n\n";

    echo "5. Obtendo status do módulo...\n";
    $status = $products->getStatus();
    echo "✅ Status: " . json_encode($status) . "\n\n";

    echo "6. Testando método listThemes (que estava falhando)...\n";
    try {
        $themes = $products->listThemes();
        echo "✅ listThemes executado com sucesso: " . json_encode($themes) . "\n\n";
    } catch (\Exception $e) {
        echo "❌ ERRO em listThemes: " . $e->getMessage() . "\n";
        echo "   Classe: " . get_class($e) . "\n";
        echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    }

    echo "7. Testando método listLayouts (que estava falhando)...\n";
    try {
        $layouts = $products->listLayouts();
        echo "✅ listLayouts executado com sucesso: " . json_encode($layouts) . "\n\n";
    } catch (\Exception $e) {
        echo "❌ ERRO em listLayouts: " . $e->getMessage() . "\n";
        echo "   Classe: " . get_class($e) . "\n";
        echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    }

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}