<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Auth\AuthManager;

echo "=== Debug Detalhado de Autenticação ===\n\n";

try {
    // Configuração
    $config = new Configuration([
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'environment' => 'sandbox'
        ],
        'endpoints' => [
            'base_url' => 'https://checkout.svelve.com/api/v1'
        ]
    ]);

    echo "1. Criando cliente HTTP...\n";
    $httpClient = new Client($config);
    echo "✓ Cliente HTTP criado\n\n";

    echo "2. Criando AuthManager...\n";
    $authManager = new AuthManager($httpClient, $config);
    echo "✓ AuthManager criado\n\n";

    echo "3. Tentando autenticar...\n";
    $result = $authManager->authenticate();
    echo "✓ Autenticação bem sucedida: " . ($result ? 'true' : 'false') . "\n\n";

    echo "4. Verificando se está autenticado...\n";
    $isAuth = $authManager->isAuthenticated();
    echo "Autenticado: " . ($isAuth ? 'true' : 'false') . "\n\n";

    if ($isAuth) {
        echo "5. Obtendo informações do usuário...\n";
        $userInfo = $authManager->getUserInfo();
        print_r($userInfo);
    }

} catch (\Throwable $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e instanceof \Clubify\Checkout\Exceptions\HttpException) {
        echo "HTTP Status: " . $e->getCode() . "\n";
        if ($e->getResponse()) {
            echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
    }
}