<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;

echo "=== Debug Response da API ===\n\n";

try {
    $config = new Configuration([
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'environment' => 'sandbox'
        ]
    ]);

    echo "Base URL: " . $config->getBaseUrl() . "\n\n";

    $httpClient = new Client($config);

    echo "Fazendo requisição para: " . $config->getBaseUrl() . "/api-keys/public/validate\n\n";

    $response = $httpClient->post('/api-keys/public/validate', [
        'json' => [
            'apiKey' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
        ],
        'headers' => [
            'X-Tenant-ID' => '68c05e15ad23f0f6aaa1ae51'
        ]
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    $body = (string) $response->getBody();
    echo "Response Body: " . $body . "\n\n";

    $data = json_decode($body, true);
    echo "Parsed JSON:\n";
    print_r($data);

    // Verificar as condições que o AuthManager está validando
    echo "\n=== Validações do AuthManager ===\n";
    echo "isset(\$data['success']): " . (isset($data['success']) ? 'true' : 'false') . "\n";
    echo "\$data['success']: " . ($data['success'] ? 'true' : 'false') . "\n";
    echo "isset(\$data['data']['valid']): " . (isset($data['data']['valid']) ? 'true' : 'false') . "\n";
    echo "\$data['data']['valid']: " . ($data['data']['valid'] ? 'true' : 'false') . "\n";

    $condition = isset($data['success']) && $data['success'] && isset($data['data']['valid']) && $data['data']['valid'];
    echo "Condição total (deve ser true): " . ($condition ? 'true' : 'false') . "\n";

} catch (\Throwable $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}