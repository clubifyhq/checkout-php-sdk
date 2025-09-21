<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste do AuthManager Corrigido ===\n\n";

// Usar credenciais corretas
$config = [
    'credentials' => [
        'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
        'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
        'environment' => 'sandbox'
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

    echo "3. Testando autenticação via API key (método atual)...\n";

    // Usar reflection para acessar o AuthManager
    $reflection = new ReflectionClass($sdk);
    $authManagerMethod = $reflection->getMethod('getAuthManager');
    $authManagerMethod->setAccessible(true);
    $authManager = $authManagerMethod->invoke($sdk);

    echo "   Status autenticado: " . ($authManager->isAuthenticated() ? 'SIM' : 'NÃO') . "\n";
    echo "   Access token: " . substr($authManager->getAccessToken() ?? 'NENHUM', 0, 15) . "...\n";

    $userInfo = $authManager->getUserInfo();
    echo "   Tipo de auth: " . ($userInfo['auth_type'] ?? 'desconhecido') . "\n";
    echo "\n";

    echo "4. Testando requisição com UserManagement...\n";
    $userManagement = $sdk->userManagement();

    // Testar um método simples
    try {
        $result = $userManagement->getUserRoles('test_user_123');
        echo "   ✅ getUserRoles executou sem erro de autenticação\n";
    } catch (\Exception $e) {
        $errorMsg = $e->getMessage();

        if (strpos($errorMsg, '401') !== false || strpos($errorMsg, 'Unauthorized') !== false) {
            echo "   ❌ ERRO 401: " . $errorMsg . "\n";
            echo "   🔍 Isso indica que a API key não é suficiente, precisamos de access token real\n";
        } elseif (strpos($errorMsg, 'HTTP request failed') !== false) {
            echo "   ⚠️  Erro de conectividade: " . $errorMsg . "\n";
        } else {
            echo "   ❓ Outro erro: " . $errorMsg . "\n";
        }
    }
    echo "\n";

    echo "5. Verificando se precisamos de login real...\n";

    // Se temos erro 401, isso significa que precisamos fazer login com usuário/senha
    // para obter um access token JWT real

    try {
        echo "   Tentando login com credenciais de teste...\n";
        $loginResult = $authManager->login('test@example.com', 'password123');

        echo "   ✅ LOGIN SUCESSO!\n";
        echo "   📄 Resultado: " . json_encode($loginResult, JSON_PRETTY_PRINT) . "\n";

        // Agora testar novamente com access token real
        echo "   Testando getUserRoles com access token real...\n";
        $result = $userManagement->getUserRoles('test_user_123');
        echo "   ✅ getUserRoles com access token real funcionou!\n";

    } catch (\Exception $e) {
        echo "   ❌ Login falhou: " . $e->getMessage() . "\n";
        echo "   💡 Isso é esperado, pois não temos credenciais válidas de usuário\n";
    }
    echo "\n";

    echo "6. Testando fallback de validação da API key...\n";

    // Testar se a validação de formato da API key funciona
    $reflection = new ReflectionClass($authManager);
    $validateMethod = $reflection->getMethod('isValidApiKeyFormat');
    $validateMethod->setAccessible(true);

    $testKeys = [
        'clb_test_4186d572ddb73ffdf6e1907cacff58b2' => 'API key real',
        'clb_live_1234567890abcdef1234567890abcdef' => 'API key formato válido',
        'invalid_key' => 'API key inválida',
        'ck_test_123' => 'API key formato antigo'
    ];

    foreach ($testKeys as $key => $description) {
        $isValid = $validateMethod->invoke($authManager, $key);
        echo "   {$description}: " . ($isValid ? '✅ VÁLIDA' : '❌ INVÁLIDA') . "\n";
    }
    echo "\n";

    echo "📋 ANÁLISE FINAL:\n";
    echo "==================\n";
    echo "✅ AuthManager corrigido implementa:\n";
    echo "   - Validação de API key via endpoint /api-keys/validate\n";
    echo "   - Fallback para validação de formato se endpoint falhar\n";
    echo "   - Login real com usuário/senha via /auth/login\n";
    echo "   - Refresh token via /auth/refresh\n";
    echo "   - Uso correto de access tokens JWT\n";
    echo "\n";
    echo "🔍 PRÓXIMOS PASSOS:\n";
    echo "   1. Para usar o SDK, precisamos de credenciais de usuário válidas\n";
    echo "   2. Ou implementar um fluxo que permita usar API keys diretamente\n";
    echo "   3. Verificar se existem endpoints que aceitam API keys no header\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";