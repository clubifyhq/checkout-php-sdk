<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste de Correção dos Headers Obrigatórios ===\n\n";

// Configuração de teste
$config = [
    'credentials' => [
        'tenant_id' => 'test_tenant_123',
        'api_key' => 'test_api_key_456',
        'environment' => 'production'
    ],
    'endpoints' => [
        'base_url' => 'https://checkout.svelve.com/api/v1'
    ],
    'debug' => true
];

try {
    echo "1. Criando instância do SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);

    echo "2. Inicializando SDK...\n";
    $initResult = $sdk->initialize();

    echo "Resultado da inicialização:\n";
    print_r($initResult);

    echo "\n3. Verificando configuração...\n";
    $configObj = $sdk->getConfig();
    echo "Tenant ID: " . $configObj->getTenantId() . "\n";
    echo "API Key (primeiros 10 chars): " . substr($configObj->getApiKey(), 0, 10) . "...\n";
    echo "Base URL: " . $configObj->getBaseUrl() . "\n";

    echo "\n4. Verificando headers padrão...\n";
    $defaultHeaders = $configObj->getDefaultHeaders();
    echo "Headers padrão:\n";
    foreach ($defaultHeaders as $key => $value) {
        if ($key === 'Authorization') {
            echo "  $key: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "  $key: $value\n";
        }
    }

    echo "\n5. Testando autenticação...\n";
    echo "Está autenticado: " . ($sdk->isAuthenticated() ? 'SIM' : 'NÃO') . "\n";

    echo "\n6. Tentando fazer requisição de teste...\n";

    // Usar reflection para acessar o httpClient interno
    $reflection = new ReflectionClass($sdk);
    $httpClientMethod = $reflection->getMethod('getHttpClient');
    $httpClientMethod->setAccessible(true);
    $httpClient = $httpClientMethod->invoke($sdk);

    // Obter headers de requisição
    $requestHeaders = $httpClient->getRequestHeaders();
    echo "Headers que serão enviados na requisição:\n";
    foreach ($requestHeaders as $key => $value) {
        if ($key === 'Authorization') {
            echo "  $key: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "  $key: $value\n";
        }
    }

    echo "\n✅ VERIFICAÇÕES IMPORTANTES:\n";
    echo "- Authorization header presente: " . (isset($requestHeaders['Authorization']) ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "- X-Tenant-ID header presente: " . (isset($requestHeaders['X-Tenant-ID']) ? '✅ SIM' : '❌ NÃO') . "\n";

    if (isset($requestHeaders['Authorization']) && isset($requestHeaders['X-Tenant-ID'])) {
        echo "\n🎉 SUCESSO! Headers obrigatórios estão sendo configurados corretamente!\n";
    } else {
        echo "\n❌ PROBLEMA! Headers obrigatórios ainda não estão sendo configurados.\n";
    }

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";