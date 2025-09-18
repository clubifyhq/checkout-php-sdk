<?php

use App\Helpers\ClubifySDKHelper;

// Bootstrap Laravel application
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing Specific Subscription Methods ===\n";

try {
    $sdk = ClubifySDKHelper::getInstance();
    if (!$sdk->isInitialized()) {
        ClubifySDKHelper::initializeForTesting();
    }

    echo "Getting subscriptions module...\n";
    $subscriptions = $sdk->subscriptions();

    echo "Module initialized: " . ($subscriptions->isInitialized() ? 'Yes' : 'No') . "\n\n";

    // Test each failing method individually
    $testMethods = [
        'createSubscription' => [[
            'plan_id' => 'plan_123',
            'customer_id' => 'cust_456',
            'billing_cycle' => 'monthly'
        ]],
        'cancelSubscription' => [
            'sub_123',
            ['reason' => 'customer_request']
        ],
        'getSubscriptionMetrics' => [
            ['start_date' => '2024-01-01', 'end_date' => '2024-12-31']
        ],
        'updateBilling' => [
            'sub_123',
            ['payment_method' => 'credit_card']
        ]
    ];

    foreach ($testMethods as $methodName => $args) {
        echo "Testing {$methodName}...\n";
        try {
            $result = call_user_func_array([$subscriptions, $methodName], $args);
            echo "✅ {$methodName}: SUCCESS\n";
        } catch (Exception $e) {
            echo "❌ {$methodName}: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
}

echo "=== Debug Complete ===\n";