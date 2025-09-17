<?php

require_once 'vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "Testing Clubify Checkout SDK - Business Methods...\n";
echo "================================================\n";

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
        ]
    ];

    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK initialized successfully!\n\n";

    // Testar métodos de conveniência do SDK principal
    echo "🚀 Testing SDK Convenience Methods:\n";

    try {
        $orgResult = $sdk->setupOrganization([
            'name' => 'Teste Org',
            'admin_name' => 'Admin Teste',
            'admin_email' => 'admin@teste.com'
        ]);
        echo "   ✅ setupOrganization: " . $orgResult['organization']['id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ setupOrganization error: " . $e->getMessage() . "\n";
    }

    try {
        $productResult = $sdk->createCompleteProduct([
            'name' => 'Produto Teste',
            'price' => 99.90
        ]);
        echo "   ✅ createCompleteProduct: " . $productResult['product_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ createCompleteProduct error: " . $e->getMessage() . "\n";
    }

    try {
        $sessionResult = $sdk->createCheckoutSession([
            'product_id' => 'prod_123',
            'customer_email' => 'customer@teste.com'
        ]);
        echo "   ✅ createCheckoutSession: " . $sessionResult['session_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ createCheckoutSession error: " . $e->getMessage() . "\n";
    }

    try {
        $oneClickResult = $sdk->processOneClick([
            'payment_method' => 'credit_card',
            'amount' => 199.90
        ]);
        echo "   ✅ processOneClick: " . $oneClickResult['transaction_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ processOneClick error: " . $e->getMessage() . "\n";
    }

    echo "\n🔧 Testing Individual Module Methods:\n";

    // Testar métodos específicos dos módulos
    $payments = $sdk->payments();
    try {
        $paymentResult = $payments->processPayment([
            'method' => 'pix',
            'amount' => 50.00,
            'currency' => 'BRL'
        ]);
        echo "   ✅ PaymentsModule->processPayment: " . $paymentResult['transaction_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ PaymentsModule->processPayment error: " . $e->getMessage() . "\n";
    }

    try {
        $tokenResult = $payments->tokenizeCard([
            'number' => '4111111111111111',
            'brand' => 'visa'
        ]);
        echo "   ✅ PaymentsModule->tokenizeCard: " . $tokenResult['token'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ PaymentsModule->tokenizeCard error: " . $e->getMessage() . "\n";
    }

    $customers = $sdk->customers();
    try {
        $customerResult = $customers->findByEmail('teste@email.com');
        echo "   ✅ CustomersModule->findByEmail: " . $customerResult['customer_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ CustomersModule->findByEmail error: " . $e->getMessage() . "\n";
    }

    $webhooks = $sdk->webhooks();
    try {
        $webhookResult = $webhooks->configureWebhook([
            'url' => 'https://example.com/webhook',
            'events' => ['order.created', 'payment.completed']
        ]);
        echo "   ✅ WebhooksModule->configureWebhook: " . $webhookResult['webhook_id'] . "\n";
    } catch (Exception $e) {
        echo "   ❌ WebhooksModule->configureWebhook error: " . $e->getMessage() . "\n";
    }

    echo "\n📊 Module Implementation Status:\n";

    $modules = ['organization', 'products', 'checkout', 'payments', 'customers', 'webhooks'];

    foreach ($modules as $moduleName) {
        $module = $sdk->$moduleName();
        $status = $module->getStatus();
        $methods = get_class_methods($module);
        $businessMethods = array_filter($methods, function($method) {
            return !in_array($method, [
                '__construct', 'initialize', 'isInitialized', 'getName',
                'getVersion', 'getDependencies', 'isAvailable', 'getStatus',
                'cleanup', 'isHealthy', 'getStats'
            ]);
        });

        echo "   📦 " . ucfirst($moduleName) . "Module:\n";
        echo "      - Interface methods: " . count($methods) . "\n";
        echo "      - Business methods: " . count($businessMethods) . "\n";
        echo "      - Available: " . ($status['available'] ? 'Yes' : 'No') . "\n";
        echo "      - Methods: " . implode(', ', $businessMethods) . "\n\n";
    }

    echo "✅ All business method tests completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}