<?php

use App\Helpers\ClubifySDKHelper;

// Bootstrap Laravel application
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Debugging Subscriptions Module ===\n";

try {
    echo "1. Checking if SDK is available...\n";
    $available = ClubifySDKHelper::isAvailable();
    echo "SDK Available: " . ($available ? 'Yes' : 'No') . "\n";

    if (!$available) {
        echo "SDK not available, exiting.\n";
        exit(1);
    }

    echo "2. Getting SDK instance...\n";
    $sdk = ClubifySDKHelper::getInstance();
    echo "SDK instance obtained.\n";

    echo "3. Checking if SDK is initialized...\n";
    $initialized = $sdk->isInitialized();
    echo "SDK Initialized: " . ($initialized ? 'Yes' : 'No') . "\n";

    if (!$initialized) {
        echo "4. Initializing SDK for testing...\n";
        $initResult = ClubifySDKHelper::initializeForTesting();
        echo "Initialization result: " . ($initResult ? 'Success' : 'Failed') . "\n";

        $initialized = $sdk->isInitialized();
        echo "SDK Initialized after init: " . ($initialized ? 'Yes' : 'No') . "\n";
    }

    echo "5. Getting subscriptions module...\n";
    $subscriptions = $sdk->subscriptions();
    echo "Subscriptions module obtained.\n";

    echo "6. Checking if subscriptions module is initialized...\n";
    $subInitialized = $subscriptions->isInitialized();
    echo "Subscriptions Initialized: " . ($subInitialized ? 'Yes' : 'No') . "\n";

    if ($subInitialized) {
        echo "7. Testing a simple method...\n";
        try {
            $result = $subscriptions->getName();
            echo "getName() result: " . $result . "\n";
        } catch (Exception $e) {
            echo "Error calling getName(): " . $e->getMessage() . "\n";
        }

        echo "8. Testing createSubscription...\n";
        try {
            $result = $subscriptions->createSubscription([
                'plan_id' => 'plan_test',
                'customer_id' => 'cust_test',
                'billing_cycle' => 'monthly'
            ]);
            echo "createSubscription() success\n";
        } catch (Exception $e) {
            echo "Error calling createSubscription(): " . $e->getMessage() . "\n";
        }
    }

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";