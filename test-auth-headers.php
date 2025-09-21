<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste de Headers de Autenticação ===\n\n";

// Usar as mesmas credenciais do diagnostic-script.php
$config = [
    'credentials' => [
        'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
        'api_key' => 'ck_test_4b8b7f4b5d6f4b4b7f4b5d6f',
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
    $sdk->initialize();

    echo "3. Verificando configuração...\n";
    $configObj = $sdk->getConfig();
    echo "   Tenant ID: " . $configObj->getTenantId() . "\n";
    echo "   API Key: " . substr($configObj->getApiKey(), 0, 15) . "...\n";
    echo "   Base URL: " . $configObj->getBaseUrl() . "\n";
    echo "   Headers padrão:\n";
    foreach ($configObj->getDefaultHeaders() as $key => $value) {
        if ($key === 'Authorization') {
            echo "     {$key}: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "     {$key}: {$value}\n";
        }
    }
    echo "\n";

    echo "4. Testando headers via HttpClient...\n";

    // Usar reflection para acessar HttpClient
    $reflection = new ReflectionClass($sdk);
    $httpClientMethod = $reflection->getMethod('getHttpClient');
    $httpClientMethod->setAccessible(true);
    $httpClient = $httpClientMethod->invoke($sdk);

    echo "   Headers dinâmicos do HttpClient:\n";
    $headers = $httpClient->getRequestHeaders();
    foreach ($headers as $key => $value) {
        if ($key === 'Authorization') {
            echo "     {$key}: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "     {$key}: {$value}\n";
        }
    }
    echo "\n";

    echo "5. Verificando autenticação...\n";
    echo "   SDK autenticado: " . ($sdk->isAuthenticated() ? 'SIM' : 'NÃO') . "\n\n";

    echo "6. Testando endpoint /health (não precisa auth)...\n";
    try {
        $response = $httpClient->get('health');
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Sucesso: " . ($response->getStatusCode() === 200 ? 'SIM' : 'NÃO') . "\n";
    } catch (Exception $e) {
        echo "   Erro: " . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "7. Testando endpoint /products (precisa auth)...\n";
    try {
        $response = $httpClient->get('products');
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Sucesso: " . ($response->getStatusCode() === 200 ? 'SIM' : 'NÃO') . "\n";
        if ($response->getStatusCode() !== 200) {
            echo "   Erro: " . $response->getError() . "\n";
        }
    } catch (Exception $e) {
        echo "   Exceção: " . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "8. Testando endpoint /users (UserManagement)...\n";
    try {
        $response = $httpClient->get('users');
        echo "   Status: " . $response->getStatusCode() . "\n";
        echo "   Sucesso: " . ($response->getStatusCode() === 200 ? 'SIM' : 'NÃO') . "\n";
        if ($response->getStatusCode() !== 200) {
            echo "   Erro: " . $response->getError() . "\n";
        }
    } catch (Exception $e) {
        echo "   Exceção: " . $e->getMessage() . "\n";
    }
    echo "\n";

    echo "9. Testando manualmente com curl...\n";
    $curlCmd = "curl -s -w '%{http_code}' " .
               "-H 'Authorization: Bearer " . $configObj->getApiKey() . "' " .
               "-H 'X-Tenant-ID: " . $configObj->getTenantId() . "' " .
               "-H 'Content-Type: application/json' " .
               "https://checkout.svelve.com/api/v1/users";

    echo "   Comando: " . $curlCmd . "\n";
    $curlResult = shell_exec($curlCmd);
    echo "   Resultado curl: " . $curlResult . "\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";