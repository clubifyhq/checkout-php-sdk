<?php

declare(strict_types=1);

// Carregar autoload do Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste de Autenticação via API Key para Access Token ===\n\n";

// Configurações do SDK
$config = [
    'credentials' => [
        'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? '68c05e15ad23f0f6aaa1ae51',
        'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'clb_test_4f8b2c1d6e9a7f3b5c8e2a1d4f7b9e3c',
        'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
    ],
    'http' => [
        'timeout' => 15000,
        'connect_timeout' => 5,
        'retries' => 2
    ],
    'endpoints' => [
        'base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'
    ],
    'cache' => [
        'enabled' => true,
        'ttl' => 3600
    ],
    'logging' => [
        'enabled' => true,
        'level' => 'debug'
    ]
];

try {
    echo "1. Instanciando o SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "   ✅ SDK instanciado\n\n";

    echo "2. Tentando autenticar via API key (sem health check)...\n";
    $authResult = $sdk->initialize(true); // Skip health check

    if ($authResult['success']) {
        echo "   ✅ Autenticação iniciada\n";
        echo "   - Autenticado: " . ($authResult['authenticated'] ? 'SIM' : 'NÃO') . "\n";
        echo "   - Tenant ID: " . $authResult['tenant_id'] . "\n";
        echo "   - Ambiente: " . $authResult['environment'] . "\n";
        echo "   - Health check pulado: " . ($authResult['health_check_skipped'] ? 'SIM' : 'NÃO') . "\n\n";

        // Verificar se obtivemos access token
        $authManager = $sdk->getAuthManager();
        $isAuthenticated = $authManager->isAuthenticated();
        $accessToken = $authManager->getAccessToken();

        echo "3. Verificando estado da autenticação...\n";
        echo "   - SDK autenticado: " . ($isAuthenticated ? 'SIM' : 'NÃO') . "\n";
        echo "   - Access token presente: " . (!empty($accessToken) ? 'SIM' : 'NÃO') . "\n";

        if (!empty($accessToken)) {
            echo "   - Access token (10 primeiros): " . substr($accessToken, 0, 10) . "...\n";
        }
        echo "\n";

        // Testar chamada que requer autenticação
        echo "4. Testando chamada que requer autenticação...\n";
        try {
            $userManagement = $sdk->userManagement();
            $userData = [
                'name' => 'Teste API Key Auth',
                'email' => 'teste.apikey.' . time() . '@exemplo.com',
                'password' => 'SenhaSegura123!'
            ];

            $result = $userManagement->createUser($userData);
            echo "   ✅ Usuário criado com sucesso usando access token obtido via API key!\n";
            echo "   - User ID: " . ($result['id'] ?? 'N/A') . "\n";
            echo "   - Email: " . ($result['email'] ?? 'N/A') . "\n";

        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Unauthorized') !== false || strpos($e->getMessage(), '401') !== false) {
                echo "   ❌ 401 Unauthorized - Access token não funcionou\n";
                echo "   - Erro: " . $e->getMessage() . "\n";
            } else {
                echo "   ⚠️  Outro erro: " . $e->getMessage() . "\n";
            }
        }

    } else {
        echo "   ❌ Falha na autenticação\n";
    }

} catch (Exception $e) {
    echo "\n❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e->getPrevious()) {
        echo "   Erro anterior: " . $e->getPrevious()->getMessage() . "\n";
    }
}

echo "\n=== TESTE FINALIZADO ===\n";