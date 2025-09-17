<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

echo "=== Debug Guzzle Direto ===\n\n";

try {
    $client = new Client([
        'base_uri' => 'https://checkout.svelve.com/api/v1',
        'timeout' => 30,
        'headers' => [
            'User-Agent' => 'ClubifyCheckoutSDK-PHP/1.0.0',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SDK-Version' => '1.0.0',
            'X-SDK-Language' => 'php',
            'X-Tenant-ID' => '68c05e15ad23f0f6aaa1ae51'
        ]
    ]);

    echo "1. Fazendo requisição com Guzzle...\n";
    echo "URL: https://checkout.svelve.com/api/v1/api-keys/public/validate\n\n";

    $response = $client->post('/api-keys/public/validate', [
        'json' => [
            'apiKey' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2'
        ]
    ]);

    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Headers:\n";
    foreach ($response->getHeaders() as $name => $values) {
        echo "  {$name}: " . implode(', ', $values) . "\n";
    }
    echo "\nBody:\n";
    echo $response->getBody()->getContents() . "\n";

} catch (RequestException $e) {
    echo "✗ Erro HTTP: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";

    if ($e->hasResponse()) {
        $response = $e->getResponse();
        echo "Status: " . $response->getStatusCode() . "\n";
        echo "Body: " . $response->getBody()->getContents() . "\n";
    }

    echo "Request URL: " . $e->getRequest()->getUri() . "\n";
    echo "Request Headers:\n";
    foreach ($e->getRequest()->getHeaders() as $name => $values) {
        echo "  {$name}: " . implode(', ', $values) . "\n";
    }
}