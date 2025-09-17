<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\Core\Config\Configuration;
use GuzzleHttp\Client as GuzzleClient;

echo "=== Debug SDK Client vs Guzzle Direto ===\n\n";

try {
    $config = new Configuration([
        'credentials' => [
            'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
            'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
            'environment' => 'sandbox'
        ]
    ]);

    echo "Base URL do config: " . $config->getBaseUrl() . "\n";
    echo "Headers padrão:\n";
    print_r($config->getDefaultHeaders());
    echo "\n";

    // Criar Guzzle Client com as mesmas configurações que o SDK usaria
    $guzzleClient = new GuzzleClient([
        'base_uri' => rtrim($config->getBaseUrl(), '/') . '/',
        'timeout' => $config->getTimeout() / 1000,
        'headers' => $config->getDefaultHeaders(),
    ]);

    echo "Fazendo requisição com Guzzle usando configurações do SDK...\n";

    $response = $guzzleClient->post('/api-keys/public/validate', [
        'json' => [
            'apiKey' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
        ]
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    $body = $response->getBody()->getContents();
    echo "Response: " . $body . "\n";

    $data = json_decode($body, true);
    if ($data && isset($data['success']) && $data['success']) {
        echo "✓ Sucesso! A requisição funcionou corretamente.\n";
    } else {
        echo "✗ Falha na validação dos dados de resposta.\n";
    }

} catch (\Throwable $e) {
    echo "✗ Erro: " . $e->getMessage() . "\n";
    echo "Tipo: " . get_class($e) . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e instanceof \GuzzleHttp\Exception\RequestException) {
        echo "Request URL: " . $e->getRequest()->getUri() . "\n";
        if ($e->hasResponse()) {
            echo "Response Status: " . $e->getResponse()->getStatusCode() . "\n";
            echo "Response Body: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
    }
}