<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

echo "=== Teste Manual de Endpoints de Autenticação via API Key ===\n\n";

// Configurações
$tenantId = $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? '68c05e15ad23f0f6aaa1ae51';
$apiKey = $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'clb_test_4f8b2c1d6e9a7f3b5c8e2a1d4f7b9e3c';
$baseUrl = $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1';

echo "Configurações:\n";
echo "- Tenant ID: {$tenantId}\n";
echo "- API Key: " . substr($apiKey, 0, 15) . "...\n";
echo "- Base URL: {$baseUrl}\n\n";

// Lista de endpoints para testar
$endpoints = [
    'auth/api-key/token',
    'auth/token',
    'api-keys/authenticate',
    'auth/api-key',
    'oauth/token',
    'auth/authenticate',
    'token',
    'api/auth/token',
    'v1/auth/token',
    'authenticate'
];

// Diferentes payloads para testar
$payloads = [
    [
        'api_key' => $apiKey,
        'tenant_id' => $tenantId,
        'grant_type' => 'api_key'
    ],
    [
        'apiKey' => $apiKey,
        'tenantId' => $tenantId,
        'grantType' => 'api_key'
    ],
    [
        'api_key' => $apiKey,
        'tenant_id' => $tenantId
    ],
    [
        'key' => $apiKey,
        'tenant' => $tenantId,
        'type' => 'api_key'
    ],
    [
        'client_id' => $apiKey,
        'tenant_id' => $tenantId,
        'grant_type' => 'client_credentials'
    ]
];

function testEndpoint($baseUrl, $endpoint, $payload) {
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-Tenant-ID: ' . $payload['tenant_id'] ?? $payload['tenantId'] ?? $payload['tenant'] ?? ''
            ],
            'content' => json_encode($payload),
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $result = @file_get_contents($url, false, $context);
    $httpCode = 0;

    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = intval($matches[1]);
                break;
            }
        }
    }

    return [
        'url' => $url,
        'http_code' => $httpCode,
        'response' => $result,
        'success' => $httpCode >= 200 && $httpCode < 300
    ];
}

$successfulEndpoints = [];

foreach ($endpoints as $endpoint) {
    echo "Testando endpoint: {$endpoint}\n";

    foreach ($payloads as $index => $payload) {
        echo "  Payload " . ($index + 1) . ": ";

        $result = testEndpoint($baseUrl, $endpoint, $payload);

        echo "HTTP {$result['http_code']} ";

        if ($result['success']) {
            echo "✅ SUCESSO\n";

            $data = json_decode($result['response'], true);

            if (isset($data['access_token']) || isset($data['accessToken']) || isset($data['token'])) {
                echo "    🎯 ENCONTROU TOKEN!\n";
                echo "    - Endpoint: {$endpoint}\n";
                echo "    - Payload: " . json_encode($payload) . "\n";
                echo "    - Response: " . substr($result['response'], 0, 200) . "...\n";

                $successfulEndpoints[] = [
                    'endpoint' => $endpoint,
                    'payload' => $payload,
                    'response' => $data
                ];
            } else {
                echo "    ⚠️  Sucesso mas sem token\n";
                echo "    - Response: " . substr($result['response'], 0, 100) . "...\n";
            }

        } elseif ($result['http_code'] == 404) {
            echo "❌ 404 Not Found\n";
        } elseif ($result['http_code'] == 401) {
            echo "🔒 401 Unauthorized\n";
        } elseif ($result['http_code'] == 422) {
            echo "📋 422 Validation Error\n";
            echo "    - Response: " . substr($result['response'], 0, 100) . "...\n";
        } else {
            echo "❌ Erro {$result['http_code']}\n";
            if (!empty($result['response'])) {
                echo "    - Response: " . substr($result['response'], 0, 100) . "...\n";
            }
        }
    }
    echo "\n";
}

echo "=== RESUMO ===\n";

if (!empty($successfulEndpoints)) {
    echo "🎉 Encontrados " . count($successfulEndpoints) . " endpoint(s) que retornam tokens:\n\n";

    foreach ($successfulEndpoints as $success) {
        echo "✅ Endpoint: {$success['endpoint']}\n";
        echo "   Payload: " . json_encode($success['payload']) . "\n";

        $tokenKey = null;
        $token = null;

        if (isset($success['response']['access_token'])) {
            $tokenKey = 'access_token';
            $token = $success['response']['access_token'];
        } elseif (isset($success['response']['accessToken'])) {
            $tokenKey = 'accessToken';
            $token = $success['response']['accessToken'];
        } elseif (isset($success['response']['token'])) {
            $tokenKey = 'token';
            $token = $success['response']['token'];
        }

        if ($token) {
            echo "   Token ({$tokenKey}): " . substr($token, 0, 20) . "...\n";
        }
        echo "\n";
    }

    echo "RECOMENDAÇÃO: Atualize o AuthManager para usar o endpoint que funciona!\n";

} else {
    echo "❌ Nenhum endpoint retornou um access token válido.\n";
    echo "\nISSO SIGNIFICA QUE:\n";
    echo "1. A API não suporta autenticação via API key para obter access tokens\n";
    echo "2. É necessário fazer login com usuário/senha para obter access tokens\n";
    echo "3. O SDK deve usar a API key apenas para validação, não para autenticação completa\n";
    echo "\nSOLUÇÃO ALTERNATIVA:\n";
    echo "- Use a API key apenas para validar permissões\n";
    echo "- Para endpoints protegidos, implemente login com usuário/senha\n";
    echo "- Ou configure um usuário 'service' especial para o SDK\n";
}

echo "\n=== TESTE FINALIZADO ===\n";