<?php

declare(strict_types=1);

echo "=== Investigação de Endpoints Disponíveis ===\n\n";

$baseUrl = 'https://checkout.svelve.com/api/v1';
$apiKey = 'clb_test_4186d572ddb73ffdf6e1907cacff58b2';
$tenantId = '68c05e15ad23f0f6aaa1ae51';

echo "🔍 Testando endpoints existentes na API...\n\n";

// Lista de endpoints para testar
$endpoints = [
    // Endpoints de sistema
    'GET /health' => ['method' => 'GET', 'path' => '/health', 'auth' => false],

    // Endpoints que sabemos que existem
    'GET /products' => ['method' => 'GET', 'path' => '/products', 'auth' => true],
    'GET /orders' => ['method' => 'GET', 'path' => '/orders', 'auth' => true],
    'GET /customers' => ['method' => 'GET', 'path' => '/customers', 'auth' => true],

    // Endpoints do user-management-service
    'GET /users' => ['method' => 'GET', 'path' => '/users', 'auth' => true],
    'GET /users/stats' => ['method' => 'GET', 'path' => '/users/stats', 'auth' => true],
    'POST /users' => ['method' => 'POST', 'path' => '/users', 'auth' => true],

    // Endpoints de autenticação
    'POST /auth/login' => ['method' => 'POST', 'path' => '/auth/login', 'auth' => false],
    'POST /api-keys/validate' => ['method' => 'POST', 'path' => '/api-keys/validate', 'auth' => false],
    'GET /auth/profile' => ['method' => 'GET', 'path' => '/auth/profile', 'auth' => true],

    // Endpoints possíveis de user-management
    'GET /user-management/users' => ['method' => 'GET', 'path' => '/user-management/users', 'auth' => true],
    'GET /user-management/health' => ['method' => 'GET', 'path' => '/user-management/health', 'auth' => false],
];

foreach ($endpoints as $name => $config) {
    echo "📡 Testando {$name}...\n";

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];

    if ($config['auth']) {
        $headers[] = 'Authorization: Bearer ' . $apiKey;
        $headers[] = 'X-Tenant-ID: ' . $tenantId;
    }

    $postData = '';
    if ($config['method'] === 'POST') {
        if (strpos($config['path'], '/auth/login') !== false) {
            $postData = json_encode(['email' => 'test@test.com', 'password' => 'test']);
            $headers[] = 'X-Tenant-ID: ' . $tenantId;
        } elseif (strpos($config['path'], '/api-keys/validate') !== false) {
            $postData = json_encode(['apiKey' => $apiKey, 'endpoint' => '/users']);
        } elseif (strpos($config['path'], '/users') !== false) {
            $postData = json_encode(['email' => 'test@test.com', 'firstName' => 'Test']);
        } else {
            $postData = '{}';
        }
        $headers[] = 'Content-Length: ' . strlen($postData);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $config['method'],
            'header' => implode("\r\n", $headers),
            'content' => $postData,
            'timeout' => 5,
            'ignore_errors' => true
        ]
    ]);

    $startTime = microtime(true);
    $result = @file_get_contents($baseUrl . $config['path'], false, $context);
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if (isset($http_response_header) && count($http_response_header) > 0) {
        $statusLine = $http_response_header[0];
        $statusCode = intval(substr($statusLine, strpos($statusLine, ' ') + 1, 3));

        if ($statusCode >= 200 && $statusCode < 300) {
            echo "   ✅ SUCESSO ({$duration}ms): {$statusLine}\n";
            if ($result && strlen($result) < 200) {
                echo "   📄 Resposta: {$result}\n";
            }
        } elseif ($statusCode === 401) {
            echo "   🔐 AUTH REQUIRED ({$duration}ms): {$statusLine}\n";
        } elseif ($statusCode === 404) {
            echo "   ❌ NOT FOUND ({$duration}ms): {$statusLine}\n";
        } elseif ($statusCode === 400) {
            echo "   ⚠️  BAD REQUEST ({$duration}ms): {$statusLine}\n";
            if ($result) {
                echo "   📄 Erro: " . substr($result, 0, 100) . "\n";
            }
        } else {
            echo "   ❓ OTHER ({$duration}ms): {$statusLine}\n";
        }
    } else {
        echo "   💥 CONNECTION FAILED ({$duration}ms)\n";
    }
    echo "\n";
}

echo "🔍 Testando roteamento específico...\n\n";

// Verificar se existe um proxy/gateway que roteia para user-management
$routingTests = [
    '/api/v1/users' => 'Endpoint direto users',
    '/api/v1/user-management/users' => 'Endpoint via user-management prefix',
    '/users' => 'Endpoint sem /api/v1',
    '/user-management/api/v1/users' => 'Endpoint invertido'
];

foreach ($routingTests as $path => $description) {
    echo "🛣️  Testando roteamento - {$description}: {$path}\n";

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'Authorization: Bearer ' . $apiKey,
                'X-Tenant-ID: ' . $tenantId,
                'Content-Type: application/json'
            ],
            'timeout' => 3,
            'ignore_errors' => true
        ]
    ]);

    $result = @file_get_contents('https://checkout.svelve.com' . $path, false, $context);

    if (isset($http_response_header) && count($http_response_header) > 0) {
        $statusLine = $http_response_header[0];
        echo "   📡 {$statusLine}\n";
    } else {
        echo "   💥 Sem resposta\n";
    }
    echo "\n";
}

echo "=== Fim da investigação ===\n";