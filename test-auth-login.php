<?php

declare(strict_types=1);

echo "=== Teste Detalhado do Endpoint /auth/login ===\n\n";

$baseUrl = 'https://checkout.svelve.com/api/v1';
$apiKey = 'clb_test_4186d572ddb73ffdf6e1907cacff58b2';
$tenantId = '68c05e15ad23f0f6aaa1ae51';

// Diferentes payloads para testar
$payloads = [
    'API Key + Tenant ID' => [
        'api_key' => $apiKey,
        'tenant_id' => $tenantId
    ],
    'API Key como credential' => [
        'credential' => $apiKey,
        'tenant_id' => $tenantId
    ],
    'API Key com grant_type' => [
        'client_id' => $tenantId,
        'client_secret' => $apiKey,
        'grant_type' => 'client_credentials'
    ],
    'Auth básico' => [
        'username' => $apiKey,
        'password' => $tenantId,
        'grant_type' => 'password'
    ]
];

foreach ($payloads as $name => $payload) {
    echo "🧪 Testando payload: {$name}\n";
    echo "   📦 Dados: " . json_encode($payload) . "\n";

    $jsonPayload = json_encode($payload);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($jsonPayload)
            ],
            'content' => $jsonPayload,
            'timeout' => 10
        ]
    ]);

    $startTime = microtime(true);
    $result = @file_get_contents($baseUrl . '/auth/login', false, $context);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if ($result === false) {
        $statusLine = $http_response_header[0] ?? 'Connection failed';
        echo "   ❌ FALHOU ({$duration}ms): {$statusLine}\n";

        // Verificar se temos headers de resposta para mais detalhes
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'content-type') !== false ||
                    stripos($header, 'www-authenticate') !== false) {
                    echo "   📋 Header: {$header}\n";
                }
            }
        }
    } else {
        $statusLine = $http_response_header[0] ?? 'Unknown';
        echo "   ✅ SUCESSO ({$duration}ms): {$statusLine}\n";
        echo "   📄 Resposta: " . $result . "\n";

        // Se obtivemos um token, testar usá-lo
        $responseData = json_decode($result, true);
        if (isset($responseData['access_token'])) {
            echo "   🎉 ACCESS TOKEN OBTIDO! Testando...\n";
            testWithAccessToken($responseData['access_token']);
        }
    }
    echo "\n";
}

function testWithAccessToken($accessToken) {
    $baseUrl = 'https://checkout.svelve.com/api/v1';
    $tenantId = '68c05e15ad23f0f6aaa1ae51';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Authorization: Bearer ' . $accessToken,
                'X-Tenant-ID: ' . $tenantId,
                'Content-Type: application/json'
            ],
            'timeout' => 5
        ]
    ]);

    echo "      🧪 Testando GET /users com access token...\n";
    $result = @file_get_contents($baseUrl . '/users', false, $context);

    if ($result !== false) {
        $statusLine = $http_response_header[0] ?? 'Unknown';
        echo "      ✅ SUCESSO: {$statusLine}\n";
        echo "      📄 Dados: " . substr($result, 0, 200) . "...\n";
    } else {
        $statusLine = $http_response_header[0] ?? 'Failed';
        echo "      ❌ FALHOU: {$statusLine}\n";
    }
}

echo "🔍 Testando também com Authorization header na autenticação...\n\n";

// Testar com Authorization header usando API key
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Authorization: Bearer ' . $apiKey,
            'X-Tenant-ID: ' . $tenantId,
            'Content-Type: application/json'
        ],
        'content' => '{}',
        'timeout' => 5
    ]
]);

$result = @file_get_contents($baseUrl . '/auth/login', false, $context);

if ($result !== false) {
    $statusLine = $http_response_header[0] ?? 'Unknown';
    echo "✅ Com Authorization header: {$statusLine}\n";
    echo "📄 Resposta: " . $result . "\n";
} else {
    $statusLine = $http_response_header[0] ?? 'Failed';
    echo "❌ Com Authorization header: {$statusLine}\n";
}

echo "\n=== Fim do teste ===\n";