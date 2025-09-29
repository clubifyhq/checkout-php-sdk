<?php

/**
 * Passo 1: Gerar Organization API Key
 *
 * Este script cria uma Organization API Key real que pode ser usada
 * nos exemplos de autenticação.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "🔑 Gerando Organization API Key\n";
echo "================================\n\n";

// Configuração
$organizationId = '68d94e3a878451ed8bb9d873';
$userId = '68c0305c85d73f876f9a0d65';
$tenantId = '507f1f77bcf86cd799439011';

// Token de autenticação de um usuário admin (você precisa ter um token válido)
$adminAccessToken = getenv('ADMIN_ACCESS_TOKEN');

if (!$adminAccessToken) {
    echo "❌ Erro: Defina a variável de ambiente ADMIN_ACCESS_TOKEN com um token de admin válido\n";
    echo "\n";
    echo "Como obter um token:\n";
    echo "1. Faça login como admin no sistema\n";
    echo "2. Copie o access_token da resposta\n";
    echo "3. Execute: export ADMIN_ACCESS_TOKEN='seu_token_aqui'\n";
    echo "4. Execute este script novamente\n";
    exit(1);
}

try {
    echo "📡 Fazendo requisição para criar Organization API Key...\n\n";

    // Fazer requisição direta com cURL
    $ch = curl_init();

    $url = "https://checkout.svelve.com/api/v1/organizations/{$organizationId}/api-keys";

    $data = [
        'name' => 'SDK Test Organization Key',
        'scope' => 'ORGANIZATION',
        'environment' => 'test',
        'permissions' => [
            'organization:read',
            'organization:write',
            'tenant:read',
            'tenant:write',
            'checkout:read',
            'checkout:write',
            'products:read',
            'products:write',
            'orders:read',
            'customers:read'
        ],
        'description' => 'API Key de teste para desenvolvimento do SDK PHP',
        'rateLimit' => [
            'requests' => 1000,
            'window' => 3600,
            'burst' => 100
        ],
        'allowedDomains' => ['*.example.com', 'localhost'],
        'autoRotate' => false
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $adminAccessToken,
            'X-User-Id: ' . $userId
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        throw new Exception("Erro na requisição: " . $error);
    }

    if ($httpCode !== 200 && $httpCode !== 201) {
        echo "❌ Erro ao criar API Key (HTTP {$httpCode}):\n";
        echo $response . "\n";
        exit(1);
    }

    $result = json_decode($response, true);

    if (!$result || !isset($result['data']['api_key'])) {
        throw new Exception("Resposta inválida do servidor");
    }

    echo "✅ Organization API Key criada com sucesso!\n\n";
    echo "📋 Detalhes:\n";
    echo "   Key ID: " . $result['data']['key_id'] . "\n";
    echo "   API Key: " . $result['data']['api_key'] . "\n";
    echo "   Scope: " . $result['data']['scope'] . "\n";
    echo "   Environment: " . $result['data']['environment'] . "\n";
    echo "   Expires At: " . ($result['data']['expires_at'] ?? 'Never') . "\n";
    echo "\n";

    echo "🎯 Próximo passo:\n";
    echo "   Use esta API Key no arquivo organization-authentication-example.php:\n";
    echo "   \$organizationApiKey = '" . $result['data']['api_key'] . "';\n";
    echo "\n";

    // Salvar em arquivo para referência
    $keyFile = __DIR__ . '/.api-key-test.json';
    file_put_contents($keyFile, json_encode($result['data'], JSON_PRETTY_PRINT));
    echo "💾 API Key salva em: {$keyFile}\n";

} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

?>