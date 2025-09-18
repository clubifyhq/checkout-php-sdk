<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;

echo "🔍 Debug URL Construction - UserManagement Module\n";
echo "===============================================\n\n";

// Usar a mesma configuração que estava causando problemas
$config = [
    'credentials' => [
        'tenant_id' => '68c05e15ad23f0f6aaa1ae51',
        'api_key' => 'clb_test_4186d572ddb73ffdf6e1907cacff58b2',
        'api_secret' => 'demo_secret_456'
    ],
    'environment' => 'development',
    'api' => [
        'base_url' => 'https://checkout.svelve.com',
        'timeout' => 45,
        'retries' => 3,
        'verify_ssl' => false
    ]
];

echo "1. Testing Configuration object directly:\n";
$configObj = new Configuration($config);
echo "   getBaseUrl(): " . $configObj->getBaseUrl() . "\n\n";

echo "2. Testing SDK initialization:\n";
$sdk = new ClubifyCheckoutSDK($config);
$sdk->initialize(); // Initialize the SDK
echo "   SDK initialized successfully\n";

echo "3. Testing UserManagement module:\n";
$userModule = $sdk->userManagement();
echo "   UserManagement module loaded\n";

echo "4. Inspecting SDK HTTP Client:\n";
// Get the HTTP client from the SDK directly
$reflection = new ReflectionClass($sdk);
if ($reflection->hasMethod('getHttpClient')) {
    $httpClientMethod = $reflection->getMethod('getHttpClient');
    $httpClientMethod->setAccessible(true);
    $httpClient = $httpClientMethod->invoke($sdk);

    if ($httpClient) {
        echo "   HTTP Client class: " . get_class($httpClient) . "\n";
        $guzzleClient = $httpClient->getGuzzleClient();
        $baseUri = $guzzleClient->getConfig('base_uri');
        echo "   Guzzle base_uri: " . $baseUri . "\n";
    }
}

echo "\n5. Testing actual HTTP call:\n";

// Let's try to call the method and catch the detailed error
try {
    echo "   Calling findUserByEmail('test@example.com')...\n";
    $result = $userModule->findUserByEmail('test@example.com');
    echo "   ✅ Call completed successfully\n";
    echo "   Result type: " . gettype($result) . "\n";
} catch (Exception $e) {
    echo "   ❌ Error occurred: " . $e->getMessage() . "\n";

    // If it's an HTTP exception, it might contain the URL
    if ($e instanceof \Clubify\Checkout\Exceptions\HttpException) {
        $context = $e->getContext();
        if (isset($context['uri'])) {
            echo "   🎯 Actual URI called: " . $context['uri'] . "\n";
        }
    }

    // Check if it's a Guzzle request exception
    if ($e instanceof \GuzzleHttp\Exception\RequestException) {
        $request = $e->getRequest();
        if ($request) {
            echo "   🎯 Request URI: " . $request->getUri() . "\n";
            echo "   🎯 Request method: " . $request->getMethod() . "\n";
        }
    }

    // Check the previous exception
    if ($e->getPrevious()) {
        echo "   Previous error: " . $e->getPrevious()->getMessage() . "\n";
        if ($e->getPrevious() instanceof \GuzzleHttp\Exception\RequestException) {
            $request = $e->getPrevious()->getRequest();
            if ($request) {
                echo "   🎯 Previous Request URI: " . $request->getUri() . "\n";
            }
        }
    }
}

echo "\n🎯 DIAGNOSIS:\n";
echo "The error message should show us the exact URL being called.\n";
echo "If it shows '/users/search' instead of '/api/v1/users/search',\n";
echo "then we know there's still a configuration issue.\n";