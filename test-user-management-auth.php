<?php

declare(strict_types=1);

echo "=== Teste dos Endpoints Corretos do User Management Service ===\n\n";

$baseUrl = 'https://checkout.svelve.com/api/v1';
$apiKey = 'clb_test_4186d572ddb73ffdf6e1907cacff58b2';
$tenantId = '68c05e15ad23f0f6aaa1ae51';

echo "🔍 Testando endpoint de validação de API Key...\n";

$validatePayload = json_encode([
    'apiKey' => $apiKey,
    'endpoint' => '/users',
    'clientIp' => '127.0.0.1'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($validatePayload)
        ],
        'content' => $validatePayload,
        'timeout' => 10
    ]
]);

$result = @file_get_contents($baseUrl . '/api-keys/public/validate', false, $context);

if ($result !== false) {
    $statusLine = $http_response_header[0] ?? 'Unknown';
    echo "✅ API Key Validate: {$statusLine}\n";
    echo "📄 Resposta: {$result}\n\n";

    $responseData = json_decode($result, true);
    if (isset($responseData['isValid']) && $responseData['isValid']) {
        echo "🎉 API Key é válida! Tentando usar para acessar recursos...\n\n";

        // Testar acesso direto com API key válida
        testDirectAccess($apiKey, $tenantId);
    }
} else {
    $statusLine = $http_response_header[0] ?? 'Failed';
    echo "❌ API Key Validate: {$statusLine}\n\n";
}

echo "🔐 Testando endpoint de login real...\n";

// Tentar um login de teste (provavelmente falhará, mas vamos ver a resposta)
$loginPayload = json_encode([
    'email' => 'test@example.com',
    'password' => 'test123'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'X-Tenant-Id: ' . $tenantId,
            'Content-Length: ' . strlen($loginPayload)
        ],
        'content' => $loginPayload,
        'timeout' => 10
    ]
]);

$result = @file_get_contents($baseUrl . '/auth/login', false, $context);

if ($result !== false) {
    $statusLine = $http_response_header[0] ?? 'Unknown';
    echo "✅ Auth Login: {$statusLine}\n";
    echo "📄 Resposta: {$result}\n\n";
} else {
    $statusLine = $http_response_header[0] ?? 'Failed';
    echo "❌ Auth Login: {$statusLine}\n";
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (stripos($header, 'content-type') !== false) {
                echo "📋 {$header}\n";
            }
        }
    }
    echo "\n";
}

function testDirectAccess($apiKey, $tenantId) {
    $baseUrl = 'https://checkout.svelve.com/api/v1';

    echo "🧪 Testando acesso direto com API Key válida...\n";

    $endpoints = [
        '/users' => 'GET /users',
        '/health' => 'GET /health',
        '/auth/profile' => 'GET /auth/profile'
    ];

    foreach ($endpoints as $endpoint => $name) {
        echo "   Testando {$name}...\n";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'Authorization: Bearer ' . $apiKey,
                    'X-Tenant-Id: ' . $tenantId,
                    'Content-Type: application/json'
                ],
                'timeout' => 5
            ]
        ]);

        $result = @file_get_contents($baseUrl . $endpoint, false, $context);

        if ($result !== false) {
            $statusLine = $http_response_header[0] ?? 'Unknown';
            echo "      ✅ {$statusLine}\n";
            if (strlen($result) < 200) {
                echo "      📄 {$result}\n";
            } else {
                echo "      📄 " . substr($result, 0, 100) . "...\n";
            }
        } else {
            $statusLine = $http_response_header[0] ?? 'Failed';
            echo "      ❌ {$statusLine}\n";
        }
    }
}

echo "📋 ANÁLISE:\n";
echo "1. Se /api-keys/validate retornar isValid=true, a API key está válida\n";
echo "2. Se mesmo assim /users retornar 401, significa que precisamos de um access token real\n";
echo "3. Se /auth/login funcionar, precisamos implementar o fluxo correto\n";
echo "\n=== Fim do teste ===\n";