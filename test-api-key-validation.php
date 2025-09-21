<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste Específico de Validação de API Key ===\n\n";

$config = [
    'credentials' => [
        'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
        'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
        'environment' => 'sandbox'
    ],
    'endpoints' => [
        'base_url' => 'https://checkout.svelve.com/api/v1'
    ],
    'debug' => true
];

try {
    echo "1. Criando SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);

    echo "2. Inicializando (irá validar API key)...\n";
    $initResult = $sdk->initialize();

    echo "3. Resultado da inicialização:\n";
    print_r($initResult);
    echo "\n";

    echo "4. Status após inicialização:\n";
    echo "   API key validada: " . ($sdk->isApiKeyValidated() ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   Autenticado: " . ($sdk->isAuthenticated() ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   Precisa login: " . ($sdk->requiresUserLogin() ? '⚠️ SIM' : '✅ NÃO') . "\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";