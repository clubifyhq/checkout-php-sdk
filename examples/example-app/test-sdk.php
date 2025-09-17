<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "Testing Clubify Checkout SDK - Updated Version...\n";
echo "============================================\n";

try {
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
        ],
        'cache' => [
            'enabled' => true,
            'ttl' => 3600
        ],
        'logging' => [
            'enabled' => true,
            'level' => 'info'
        ]
    ];

    echo "📋 Configuração do SDK:\n";
    echo "   Tenant ID: {$config['credentials']['tenant_id']}\n";
    echo "   API Key: " . substr($config['credentials']['api_key'], 0, 20) . "...\n";
    echo "   Environment: {$config['environment']}\n";
    echo "   Base URL: {$config['api']['base_url']}\n";
    echo "\n";

    // Inicializar SDK
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK initialized successfully!\n";
    echo "   Version: " . $sdk->getVersion() . "\n";
    echo "   Authenticated: " . ($sdk->isAuthenticated() ? 'Yes' : 'No') . "\n";
    echo "   Initialized: " . ($sdk->isInitialized() ? 'Yes' : 'No') . "\n";
    echo "\n";

    // Testar todos os módulos
    echo "🔧 Testing Modules:\n";

    try {
        $organization = $sdk->organization();
        echo "   ✅ Organization module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Organization module error: " . $e->getMessage() . "\n";
    }

    try {
        $products = $sdk->products();
        echo "   ✅ Products module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Products module error: " . $e->getMessage() . "\n";
    }

    try {
        $checkout = $sdk->checkout();
        echo "   ✅ Checkout module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Checkout module error: " . $e->getMessage() . "\n";
    }

    try {
        $payments = $sdk->payments();
        echo "   ✅ Payments module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Payments module error: " . $e->getMessage() . "\n";
    }

    try {
        $customers = $sdk->customers();
        echo "   ✅ Customers module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Customers module error: " . $e->getMessage() . "\n";
    }

    try {
        $webhooks = $sdk->webhooks();
        echo "   ✅ Webhooks module loaded successfully\n";
    } catch (Exception $e) {
        echo "   ❌ Webhooks module error: " . $e->getMessage() . "\n";
    }

    echo "\n";

    // Obter estatísticas do SDK
    $stats = $sdk->getStats();
    echo "📊 SDK Statistics:\n";
    foreach ($stats as $key => $value) {
        if (is_bool($value)) {
            $displayValue = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $displayValue = json_encode($value);
        } else {
            $displayValue = (string)$value;
        }
        echo "   {$key}: {$displayValue}\n";
    }

    echo "\n✅ All tests completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if ($e->getPrevious()) {
        echo "Previous: " . $e->getPrevious()->getMessage() . "\n";
    }
}