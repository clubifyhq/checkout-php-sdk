<?php

declare(strict_types=1);

echo "=== Teste de Endpoints de Autenticação ===\n\n";

// Testar endpoints de autenticação possíveis
$baseUrl = 'https://checkout.svelve.com/api/v1';
$apiKey = 'clb_test_4186d572ddb73ffdf6e1907cacff58b2';
$tenantId = '68c05e15ad23f0f6aaa1ae51';

$authEndpoints = [
    'POST /auth/login' => '/auth/login',
    'POST /auth/token' => '/auth/token',
    'POST /oauth/token' => '/oauth/token',
    'POST /api/auth' => '/auth',
    'POST /auth/authenticate' => '/auth/authenticate'
];

foreach ($authEndpoints as $name => $endpoint) {
    echo "🔐 Testando {$name}: {$baseUrl}{$endpoint}\n";

    $payload = json_encode([
        'api_key' => $apiKey,
        'tenant_id' => $tenantId,
        'grant_type' => 'api_key'
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ],
            'content' => $payload,
            'timeout' => 5
        ]
    ]);

    $startTime = microtime(true);
    $result = @file_get_contents($baseUrl . $endpoint, false, $context);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if ($result === false) {
        $statusLine = $http_response_header[0] ?? 'Connection failed';
        echo "   ❌ FALHOU ({$duration}ms): {$statusLine}\n";
    } else {
        $statusLine = $http_response_header[0] ?? 'Unknown';
        echo "   ✅ RESPOSTA ({$duration}ms): {$statusLine}\n";
        echo "   📄 Dados: " . substr($result, 0, 200) . (strlen($result) > 200 ? '...' : '') . "\n";
    }
    echo "\n";
}

echo "🔍 Testando endpoints existentes para ver se tem algo relacionado a auth...\n\n";

// Testar alguns endpoints conhecidos para ver se retornam informações sobre auth
$infoEndpoints = [
    'GET /health' => '/health',
    'GET /' => '/',
    'GET /docs' => '/docs',
    'GET /api-docs' => '/api-docs',
    'OPTIONS /auth' => '/auth'
];

foreach ($infoEndpoints as $name => $endpoint) {
    echo "📋 Testando {$name}: {$baseUrl}{$endpoint}\n";

    $method = explode(' ', $name)[0];

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 3
        ]
    ]);

    $result = @file_get_contents($baseUrl . $endpoint, false, $context);

    if ($result !== false) {
        $statusLine = $http_response_header[0] ?? 'Unknown';
        echo "   ✅ {$statusLine}\n";

        // Procurar por pistas sobre autenticação na resposta
        if (stripos($result, 'auth') !== false || stripos($result, 'token') !== false) {
            echo "   🔍 CONTÉM PISTAS DE AUTH!\n";
            echo "   📄 Trecho: " . substr($result, 0, 300) . "...\n";
        }
    } else {
        echo "   ❌ Sem resposta\n";
    }
    echo "\n";
}

echo "=== Fim do teste ===\n";