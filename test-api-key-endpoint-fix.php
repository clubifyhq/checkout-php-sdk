<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste da Correção do Endpoint API Key ===\n\n";

// Usar credenciais corretas do super-admin
$config = [
    'credentials' => [
        'tenant_id' => '507f1f77bcf86cd799439011',
        'api_key' => 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd',
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

    echo "2. Tentando inicializar (vai chamar auth/api-key/token)...\n";

    // Usar reflection para acessar o AuthManager e testar diretamente
    $reflection = new ReflectionClass($sdk);
    $authManagerMethod = $reflection->getMethod('getAuthManager');
    $authManagerMethod->setAccessible(true);
    $authManager = $authManagerMethod->invoke($sdk);

    // Testar autenticação direta
    echo "3. Testando autenticação com API key...\n";

    $startTime = microtime(true);
    $authResult = $authManager->authenticate('507f1f77bcf86cd799439011', 'clb_test_c6eb0dda0da66cb65cf92dad27456bbd');
    $duration = round((microtime(true) - $startTime) * 1000, 2);

    if ($authResult) {
        echo "   ✅ SUCESSO! ({$duration}ms)\n";
        echo "   🎯 O endpoint auth/api-key/token está funcionando!\n";

        // Verificar estado
        echo "\n4. Verificando estado pós-autenticação:\n";
        echo "   - API key validada: " . ($authManager->isApiKeyValidated() ? '✅ SIM' : '❌ NÃO') . "\n";
        echo "   - Autenticado (JWT): " . ($authManager->isAuthenticated() ? '✅ SIM' : '❌ NÃO') . "\n";
        echo "   - Access token: " . substr($authManager->getAccessToken() ?? 'NENHUM', 0, 15) . "...\n";

        // Testar uma requisição
        echo "\n5. Testando requisição com token obtido...\n";
        try {
            $userManagement = $sdk->userManagement();
            $result = $userManagement->getUserRoles('test_user');
            echo "   ✅ Requisição funcionou!\n";
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if (strpos($errorMsg, '404') !== false) {
                echo "   ⚠️  404: Endpoint /users não encontrado (mas token funciona)\n";
            } elseif (strpos($errorMsg, '401') !== false) {
                echo "   🔐 401: Token inválido ou sem permissão\n";
            } else {
                echo "   ❓ Outro erro: {$errorMsg}\n";
            }
        }

    } else {
        echo "   ❌ FALHOU ({$duration}ms)\n";
        echo "   🔍 Autenticação retornou false\n";
    }

} catch (\Exception $e) {
    $duration = round((microtime(true) - $startTime) * 1000, 2);
    $errorMsg = $e->getMessage();

    echo "❌ ERRO ({$duration}ms): {$errorMsg}\n";

    // Analisar tipo de erro
    if (strpos($errorMsg, '404') !== false) {
        echo "\n🎯 PROBLEMA IDENTIFICADO:\n";
        echo "   404 significa que o endpoint não foi encontrado.\n";
        echo "   Isso pode indicar:\n";
        echo "   1. O nginx ainda não foi reiniciado\n";
        echo "   2. A configuração do nginx não foi deployada\n";
        echo "   3. O endpoint está sendo chamado incorretamente\n";

        echo "\n🔧 SOLUÇÕES:\n";
        echo "   1. Reiniciar nginx: docker-compose restart nginx-proxy\n";
        echo "   2. Verificar logs: docker-compose logs nginx-proxy\n";
        echo "   3. Testar manualmente: curl -X POST https://checkout.svelve.com/api/v1/auth/api-key/token\n";

    } elseif (strpos($errorMsg, '401') !== false) {
        echo "\n🔐 API key inválida ou sem permissão\n";
    } elseif (strpos($errorMsg, '500') !== false) {
        echo "\n⚡ Erro interno do servidor - verificar logs do backend\n";
    }

    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Resumo da Correção ===\n";
echo "✅ Correção implementada:\n";
echo "   - getAuthEndpointForContext() agora sempre retorna 'auth/api-key/token'\n";
echo "   - Removida lógica de super-admin que estava causando 404\n";
echo "   - Endpoint correto: POST /api/v1/auth/api-key/token\n";
echo "\n";

echo "🚀 Próximo passo:\n";
echo "   Se ainda retornar 404, reiniciar nginx-proxy:\n";
echo "   docker-compose restart nginx-proxy\n";

echo "\n=== Fim do teste ===\n";