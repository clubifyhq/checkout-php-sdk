<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Debug de Autenticação SDK Clubify ===\n\n";

try {
    // Configuração do SDK igual ao Helper
    $config = [
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'environment' => 'sandbox'
        ],
        'http' => [
            'timeout' => 5000,
            'connect_timeout' => 3,
            'retries' => 1
        ],
        'endpoints' => [
            'base_url' => 'https://checkout.svelve.com/api/v1'
        ]
    ];

    echo "1. Criando instância do SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✓ SDK criado com sucesso\n\n";

    echo "2. Verificando configuração...\n";
    $configObj = $sdk->getConfig();
    echo "Base URL: " . $configObj->getBaseUrl() . "\n";
    echo "Tenant ID: " . $configObj->getTenantId() . "\n";
    echo "API Key (primeiro 10): " . substr($configObj->getApiKey(), 0, 10) . "...\n";
    echo "Environment: " . $configObj->getEnvironment() . "\n";
    echo "Default Headers: " . json_encode($configObj->getDefaultHeaders(), JSON_PRETTY_PRINT) . "\n\n";

    echo "3. Tentando inicializar o SDK...\n";
    $result = $sdk->initialize();
    echo "✓ Inicialização bem sucedida!\n";
    echo "Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

} catch (\Throwable $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}