<?php

require_once 'vendor/autoload.php';

use GuzzleHttp\Client;

echo "=== Test URL Building ===\n\n";

// Test 1: Simple URL building
$client1 = new Client(['base_uri' => 'https://checkout.svelve.com/api/v1/']);
echo "Client 1 base_uri: https://checkout.svelve.com/api/v1/\n";
echo "Request URI: /api-keys/public/validate\n";
echo "Full URL: " . $client1->getConfig('base_uri') . ltrim('/api-keys/public/validate', '/') . "\n\n";

// Test 2: Different base_uri format
$client2 = new Client(['base_uri' => 'https://checkout.svelve.com/api/v1']);
echo "Client 2 base_uri: https://checkout.svelve.com/api/v1\n";
echo "Request URI: /api-keys/public/validate\n";
echo "Full URL: " . $client2->getConfig('base_uri') . '/api-keys/public/validate' . "\n\n";

// Test 3: Making a test request to see what actually happens
try {
    $client3 = new Client([
        'base_uri' => 'https://checkout.svelve.com/api/v1/',
        'headers' => [
            'X-Tenant-ID' => '68c05e15ad23f0f6aaa1ae51'
        ]
    ]);

    echo "Test 3: Making actual request\n";
    echo "Base URI: " . $client3->getConfig('base_uri') . "\n";
    echo "Request path: api-keys/public/validate\n";

    $response = $client3->post('api-keys/public/validate', [
        'json' => ['apiKey' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2']
    ]);

    echo "SUCCESS! Status: " . $response->getStatusCode() . "\n";
    echo "Response body: " . $response->getBody()->getContents() . "\n";

} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    if ($e instanceof \GuzzleHttp\Exception\RequestException) {
        echo "Request URL: " . $e->getRequest()->getUri() . "\n";
    }
}