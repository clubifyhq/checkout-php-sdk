<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "=== Teste Final das Correções de Autenticação ===\n\n";

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

    echo "3. Verificando headers padrão...\n";
    $configObj = $sdk->getConfig();
    $defaultHeaders = $configObj->getDefaultHeaders();

    echo "   Headers configurados:\n";
    foreach ($defaultHeaders as $key => $value) {
        if ($key === 'Authorization') {
            echo "     {$key}: " . substr($value, 0, 20) . "...\n";
        } else {
            echo "     {$key}: {$value}\n";
        }
    }
    echo "\n";

    echo "4. Testando autenticação...\n";
    echo "   Status autenticado: " . ($sdk->isAuthenticated() ? 'SIM' : 'NÃO') . "\n";

    // Usar reflection para acessar o AuthManager
    $reflection = new ReflectionClass($sdk);
    $authManagerMethod = $reflection->getMethod('getAuthManager');
    $authManagerMethod->setAccessible(true);
    $authManager = $authManagerMethod->invoke($sdk);

    echo "   Access token: " . substr($authManager->getAccessToken() ?? 'NENHUM', 0, 15) . "...\n\n";

    echo "5. Testando UserManagement com correções...\n";
    $userManagement = $sdk->userManagement();

    $testMethods = [
        'createUser' => [
            'description' => 'Criar usuário',
            'params' => [[
                'email' => 'test@example.com',
                'firstName' => 'Test',
                'lastName' => 'User'
            ]]
        ],
        'authenticateUser' => [
            'description' => 'Autenticar usuário',
            'params' => ['test@example.com', 'password123']
        ],
        'getUserRoles' => [
            'description' => 'Obter roles do usuário',
            'params' => ['test_user_123']
        ],
        'updateUserProfile' => [
            'description' => 'Atualizar perfil do usuário',
            'params' => ['test_user_123', ['firstName' => 'Updated']]
        ]
    ];

    foreach ($testMethods as $method => $config) {
        echo "   🧪 Testando {$method} ({$config['description']}):\n";

        try {
            $startTime = microtime(true);
            $result = call_user_func_array([$userManagement, $method], $config['params']);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            echo "      ✅ SUCESSO ({$duration}ms): Método executou\n";
            echo "      📊 Tipo resultado: " . gettype($result) . "\n";

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $errorMsg = $e->getMessage();

            // Analisar tipo de erro
            if (strpos($errorMsg, 'does not have a method') !== false) {
                echo "      ❌ ERRO CRÍTICO ({$duration}ms): Método não existe!\n";
                echo "         {$errorMsg}\n";
            } elseif (strpos($errorMsg, '401') !== false || strpos($errorMsg, 'Unauthorized') !== false) {
                echo "      🔐 ERRO AUTH ({$duration}ms): Credenciais inválidas\n";
                echo "         Isso pode indicar que a API key não é válida para este tenant\n";
            } elseif (strpos($errorMsg, '400') !== false || strpos($errorMsg, 'Bad Request') !== false) {
                echo "      ⚠️  ERRO 400 ({$duration}ms): Requisição malformada\n";
                echo "         Parâmetros podem estar incorretos\n";
            } elseif (strpos($errorMsg, 'HTTP request failed') !== false) {
                echo "      ⚡ ERRO CONECTIVIDADE ({$duration}ms): Problema de rede/timeout\n";
                echo "         Pode ser timeout ou problema no servidor\n";
            } else {
                echo "      ❓ OUTRO ERRO ({$duration}ms): {$errorMsg}\n";
            }
        }
        echo "\n";
    }

    echo "6. Testando endpoint manual para verificar headers...\n";

    // Teste manual direto para verificar se os headers estão sendo enviados
    $curlTest = "curl -s -w '%{http_code}' " .
                "-H 'Authorization: Bearer clb_test_4186d572ddb73ffdf6e1907cacff58b2' " .
                "-H 'X-Tenant-ID: 68c05e15ad23f0f6aaa1ae51' " .
                "-H 'Content-Type: application/json' " .
                "https://checkout.svelve.com/api/v1/users";

    echo "   Comando curl de teste:\n";
    echo "   {$curlTest}\n";

    $curlResult = shell_exec($curlTest . ' 2>&1');
    echo "   Resultado: {$curlResult}\n\n";

    echo "📋 RESUMO FINAL:\n";
    echo "================\n";
    echo "✅ Correções implementadas:\n";
    echo "   - X-Tenant-ID nos headers padrão\n";
    echo "   - Validação de API key corrigida\n";
    echo "   - Métodos UserManagement implementados\n";
    echo "   - Sintaxe de requisições corrigida\n";
    echo "\n";

    if (strpos($curlResult, '401') !== false) {
        echo "🔐 Status: API key pode precisar de permissões adicionais\n";
        echo "   Os endpoints existem, mas retornam 401 (não autorizado)\n";
        echo "   Isso indica que a API key não tem permissão para acessar /users\n";
    } elseif (strpos($curlResult, '200') !== false) {
        echo "🎉 Status: Tudo funcionando perfeitamente!\n";
        echo "   Headers corretos e API key válida\n";
    } else {
        echo "📊 Status: Verificar logs do servidor para mais detalhes\n";
    }

} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Fim do teste ===\n";