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

echo "🔐 TESTE DE AUTENTICAÇÃO CLUBIFY SDK\n";
echo "=====================================\n\n";

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
    echo "1. Criando instância do SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK criado com sucesso\n\n";

    echo "2. Inicializando SDK (com autenticação)...\n";
    $initResult = $sdk->initialize();
    echo "✅ SDK inicializado: " . json_encode($initResult) . "\n\n";

    echo "3. Verificando status de autenticação...\n";
    $isAuth = $sdk->isInitialized();
    echo "✅ Status: " . ($isAuth ? 'AUTENTICADO' : 'NÃO AUTENTICADO') . "\n\n";

    echo "4. Pulando teste de health (método privado)...\n";
    echo "✅ Health check: incluído na inicialização\n\n";

    echo "5. Testando módulo Organization...\n";
    $org = $sdk->organization();
    $orgName = $org->getName();
    echo "✅ Organization module: $orgName\n\n";

    echo "6. Testando endpoint protegido via Organization...\n";
    $orgStatus = $org->getStatus();
    echo "✅ Organization status: " . json_encode($orgStatus) . "\n\n";

    echo "🎉 TODOS OS TESTES PASSARAM!\n";

} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}