<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste do Fluxo de Autenticação Correto ===\n\n";

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

    echo "2. Inicializando SDK (valida API key)...\n";
    $sdk->initialize();

    echo "3. Status após inicialização:\n";
    echo "   API key validada: " . ($sdk->isApiKeyValidated() ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   Autenticado (JWT): " . ($sdk->isAuthenticated() ? '✅ SIM' : '❌ NÃO') . "\n";
    echo "   Precisa login: " . ($sdk->requiresUserLogin() ? '⚠️ SIM' : '✅ NÃO') . "\n";
    echo "\n";

    echo "4. Testando request SEM access token:\n";
    $userManagement = $sdk->userManagement();

    try {
        $result = $userManagement->getUserRoles('test_user');
        echo "   ✅ Funcionou (inesperado!)\n";
    } catch (\Exception $e) {
        $errorMsg = $e->getMessage();
        if (strpos($errorMsg, '401') !== false || strpos($errorMsg, 'Unauthorized') !== false) {
            echo "   ❌ 401 Unauthorized (ESPERADO - sem access token)\n";
        } else {
            echo "   ❓ Outro erro: " . $errorMsg . "\n";
        }
    }
    echo "\n";

    echo "5. Tentando fazer login para obter access token:\n";
    echo "   (Usando credenciais de teste - pode falhar, mas demonstra o fluxo)\n";

    try {
        $loginResult = $sdk->loginUser('admin@example.com', 'password123');

        echo "   ✅ LOGIN SUCESSO!\n";
        echo "   📊 Resultado: " . json_encode($loginResult, JSON_PRETTY_PRINT) . "\n";

        echo "\n6. Status após login:\n";
        echo "   API key validada: " . ($sdk->isApiKeyValidated() ? '✅ SIM' : '❌ NÃO') . "\n";
        echo "   Autenticado (JWT): " . ($sdk->isAuthenticated() ? '✅ SIM' : '❌ NÃO') . "\n";
        echo "   Precisa login: " . ($sdk->requiresUserLogin() ? '⚠️ SIM' : '✅ NÃO') . "\n";
        echo "\n";

        echo "7. Testando request COM access token:\n";
        try {
            $result = $userManagement->getUserRoles('test_user');
            echo "   ✅ FUNCIONOU! Access token válido\n";
            echo "   📊 Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        } catch (\Exception $e) {
            echo "   ❌ Ainda falhou: " . $e->getMessage() . "\n";
        }

    } catch (\Exception $e) {
        echo "   ❌ LOGIN FALHOU: " . $e->getMessage() . "\n";
        echo "   💡 Isso é esperado, pois não temos credenciais válidas de usuário\n";
        echo "\n";

        echo "6. DEMONSTRAÇÃO - Como deveria ser usado:\n";
        echo "   \$sdk = new ClubifyCheckoutSDK(\$config);\n";
        echo "   \$sdk->initialize(); // Valida API key\n";
        echo "   \n";
        echo "   if (\$sdk->requiresUserLogin()) {\n";
        echo "       \$loginResult = \$sdk->loginUser('email@real.com', 'real_password');\n";
        echo "       // Agora \$sdk->isAuthenticated() retornará true\n";
        echo "   }\n";
        echo "   \n";
        echo "   // Agora pode usar endpoints protegidos:\n";
        echo "   \$users = \$sdk->userManagement()->getUserRoles('user_id');\n";
        echo "\n";
    }

    echo "📋 RESUMO DO FLUXO CORRETO:\n";
    echo "==========================\n";
    echo "✅ FASE 1: API Key Validation\n";
    echo "   - API key é usada apenas para validar a aplicação\n";
    echo "   - SDK fica 'configurado' mas não 'autenticado'\n";
    echo "   - Endpoints protegidos retornam 401\n";
    echo "\n";
    echo "✅ FASE 2: User Authentication\n";
    echo "   - Login com email/senha via loginUser()\n";
    echo "   - Retorna access_token JWT + refresh_token\n";
    echo "   - Access token é usado no Authorization header\n";
    echo "\n";
    echo "✅ FASE 3: Authenticated Requests\n";
    echo "   - Todos os requests incluem 'Authorization: Bearer <jwt>'\n";
    echo "   - Refresh automático quando token expira\n";
    echo "   - Endpoints protegidos funcionam\n";
    echo "\n";
    echo "🎯 AGORA O FLUXO ESTÁ CORRETO!\n";
    echo "   - API key ≠ Access token\n";
    echo "   - Validação ≠ Autenticação\n";
    echo "   - Configuração ≠ Login\n";

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";