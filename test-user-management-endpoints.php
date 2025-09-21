<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Diagnóstico de Endpoints UserManagement ===\n\n";

// Configuração de teste
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
    $initResult = $sdk->initialize();

    echo "3. Testando endpoints específicos...\n\n";

    // URLs que vamos testar
    $baseUrl = 'https://checkout.svelve.com/api/v1';
    $testUrls = [
        'Health Check' => $baseUrl . '/health',
        'Users (via user-management)' => $baseUrl . '/user-management/users',
        'Users (direto)' => $baseUrl . '/users',
        'Products' => $baseUrl . '/products',
        'Orders' => $baseUrl . '/orders',
        'Customers' => $baseUrl . '/customers'
    ];

    foreach ($testUrls as $name => $url) {
        echo "Testando {$name}: {$url}\n";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => [
                    'Authorization: Bearer ck_test_4b8b7f4b5d6f4b4b7f4b5d6f',
                    'X-Tenant-ID: 68c05e15ad23f0f6aaa1ae51',
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]
        ]);

        $startTime = microtime(true);
        $result = @file_get_contents($url, false, $context);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        if ($result === false) {
            $error = error_get_last();
            echo "   ❌ FALHOU ({$duration}ms): " . ($error['message'] ?? 'Erro desconhecido') . "\n";
        } else {
            $statusLine = $http_response_header[0] ?? 'Unknown';
            echo "   ✅ SUCESSO ({$duration}ms): {$statusLine}\n";

            // Mostrar primeiros caracteres da resposta
            $preview = substr($result, 0, 100);
            echo "   📄 Resposta: " . $preview . (strlen($result) > 100 ? '...' : '') . "\n";
        }
        echo "\n";
    }

    echo "4. Testando com HttpClient do SDK...\n\n";

    // Obter HttpClient através de reflection
    $reflection = new ReflectionClass($sdk);
    $httpClientMethod = $reflection->getMethod('getHttpClient');
    $httpClientMethod->setAccessible(true);
    $httpClient = $httpClientMethod->invoke($sdk);

    echo "Headers que serão enviados:\n";
    $headers = $httpClient->getRequestHeaders();
    foreach ($headers as $key => $value) {
        if ($key === 'Authorization') {
            echo "   {$key}: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "   {$key}: {$value}\n";
        }
    }
    echo "\n";

    // Testar endpoints específicos via HttpClient
    $testEndpoints = [
        'users' => 'users',
        'users/search' => 'users/search/advanced',
        'health' => 'health'
    ];

    foreach ($testEndpoints as $name => $endpoint) {
        echo "Testando via HttpClient - {$name}: {$endpoint}\n";

        try {
            $startTime = microtime(true);
            $response = $httpClient->get($endpoint);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "   Status: " . $response->getStatusCode() . "\n";
            echo "   Sucesso: " . ($response->isSuccessful() ? 'SIM' : 'NÃO') . "\n";
            echo "   Duração: {$duration}ms\n";

            if (!$response->isSuccessful()) {
                echo "   Erro: " . $response->getError() . "\n";
            } else {
                $data = $response->getData();
                echo "   Dados: " . (is_array($data) ? 'Array[' . count($data) . ']' : gettype($data)) . "\n";
            }
        } catch (Exception $e) {
            echo "   ❌ EXCEÇÃO: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    echo "5. Análise de configuração...\n\n";

    echo "Base URL configurada: " . $sdk->getConfig()->getBaseUrl() . "\n";
    echo "Tenant ID: " . $sdk->getConfig()->getTenantId() . "\n";
    echo "API Key (10 chars): " . substr($sdk->getConfig()->getApiKey(), 0, 10) . "...\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do diagnóstico ===\n";