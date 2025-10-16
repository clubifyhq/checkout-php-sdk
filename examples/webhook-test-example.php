<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Webhook Testing Example
 *
 * This example demonstrates how to test webhook delivery using the SDK.
 * The SDK provides methods to trigger test webhooks from the notification-service.
 *
 * Prerequisites:
 * 1. Have a webhook configuration set up in the notification-service
 * 2. Have the webhook endpoint running and accessible
 * 3. Have valid credentials (JWT or API Key)
 */

// Load environment variables - Try multiple locations
echo "Loading environment variables...\n";

$envPaths = [
    __DIR__ . '/..',                           // SDK root: sdk/checkout/php/.env
    __DIR__ . '/../../..',                     // Project root: clubify-checkout/.env
    __DIR__ . '/../../../..',                  // Parent project: python/clubify-checkout/.env
];

$envLoaded = false;
$envPath = null;

foreach ($envPaths as $path) {
    $envFile = $path . '/.env';
    if (file_exists($envFile)) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable($path);
            $dotenv->load();
            $envPath = $envFile;
            $envLoaded = true;
            echo "✅ Loaded .env from: $envFile\n\n";
            break;
        } catch (\Exception $e) {
            // Try next path
            continue;
        }
    }
}

if (!$envLoaded) {
    die("❌ ERROR: Could not find .env file in any of these locations:\n" .
        implode("\n", array_map(fn($p) => "  - $p/.env", $envPaths)) .
        "\n\nPlease create a .env file with your credentials.\n");
}

// Verify required environment variables
$requiredVars = [
    'CLUBIFY_ORGANIZATION_ID',
    'CLUBIFY_API_KEY',
    'CLUBIFY_TENANT_ID',
    'CLUBIFY_ENVIRONMENT'
];

$missingVars = [];
foreach ($requiredVars as $var) {
    if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    die("❌ ERROR: Missing required environment variables:\n" .
        implode("\n", array_map(fn($v) => "  - $v", $missingVars)) .
        "\n\nPlease add these variables to your .env file.\n");
}

echo "📋 Configuration loaded:\n";
echo "  - Organization ID: " . substr($_ENV['CLUBIFY_ORGANIZATION_ID'], 0, 8) . "...\n";
echo "  - Tenant ID: " . substr($_ENV['CLUBIFY_TENANT_ID'], 0, 8) . "...\n";
echo "  - API Key: " . substr($_ENV['CLUBIFY_API_KEY'], 0, 12) . "...\n";
echo "  - Environment: " . ($_ENV['CLUBIFY_ENVIRONMENT'] ?? 'staging') . "\n\n";

// =============================================================================
// EXAMPLE 1: Test webhook with JWT authentication
// =============================================================================
echo "\n=== Example 1: Test Webhook with JWT Authentication ===\n";

try {
    // Initialize SDK
    $sdk = new ClubifyCheckoutSDK([
        'credentials' => [
            'organization_id' => $_ENV['CLUBIFY_ORGANIZATION_ID'],
            'tenant_id' => $_ENV['CLUBIFY_TENANT_ID'],
            'api_key' => $_ENV['CLUBIFY_API_KEY']
        ],
        'environment' => $_ENV['CLUBIFY_ENVIRONMENT'] ?? 'staging'
    ]);

    // Initialize SDK
    $initResult = $sdk->initialize();
    echo "SDK initialized: " . ($initResult['authenticated'] ? 'YES' : 'NO') . "\n";

    // Test webhook delivery
    echo "\nTesting webhook delivery...\n";

    $result = $sdk->notifications()->testWebhookDelivery(
        eventType: 'order.paid',
        customData: [
            'orderId' => 'order_test_' . uniqid(),
            'amount' => 99.99,
            'currency' => 'BRL',
            'customer' => [
                'name' => 'João Silva',
                'email' => 'joao.silva@example.com'
            ]
        ],
        webhookUrl: null // null = use configured webhook URL
    );

    // Display results
    echo "\nTest Results:\n";
    echo "  Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "  Status Code: " . ($result['statusCode'] ?? 'N/A') . "\n";
    echo "  Response Time: " . ($result['responseTime'] ?? 'N/A') . " ms\n";
    echo "  Webhook URL: " . ($result['webhookUrl'] ?? 'N/A') . "\n";
    echo "  Event Type: " . ($result['eventType'] ?? 'N/A') . "\n";

    if (isset($result['error'])) {
        echo "  Error: " . $result['error'] . "\n";
    }

    if (isset($result['responseBody'])) {
        echo "  Response Body: " . json_encode($result['responseBody'], JSON_PRETTY_PRINT) . "\n";
    }

    echo "\nFull Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    if ($e->getPrevious()) {
        echo "Previous error: " . $e->getPrevious()->getMessage() . "\n";
    }
}

// =============================================================================
// EXAMPLE 2: Test webhook with custom URL override
// =============================================================================
echo "\n\n=== Example 2: Test Webhook with Custom URL Override ===\n";

try {
    // Test webhook with a custom URL (useful for testing different endpoints)
    $customWebhookUrl = 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/api/webhooks/clubify-checkout'; // Replace with your test URL

    echo "Testing webhook to custom URL: {$customWebhookUrl}\n";

    $result = $sdk->notifications()->testWebhookDelivery(
        eventType: 'payment.approved',
        customData: [
            'paymentId' => 'pay_test_' . uniqid(),
            'amount' => 149.90,
            'method' => 'credit_card',
            'installments' => 3
        ],
        webhookUrl: $customWebhookUrl
    );

    echo "\nTest Results:\n";
    echo "  Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "  Status Code: " . ($result['statusCode'] ?? 'N/A') . "\n";
    echo "  Response Time: " . ($result['responseTime'] ?? 'N/A') . " ms\n";

    if (isset($result['error'])) {
        echo "  Error: " . $result['error'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// =============================================================================
// EXAMPLE 3: Test webhook with API Key authentication
// =============================================================================
echo "\n\n=== Example 3: Test Webhook with API Key Authentication ===\n";

try {
    // Initialize SDK (can use same instance)
    $apiKey = $_ENV['CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY'];

    echo "Testing webhook with API key authentication...\n";

    $result = $sdk->notifications()->testWebhookDeliveryWithApiKey(
        apiKey: $apiKey,
        eventType: 'subscription.created',
        customData: [
            'subscriptionId' => 'sub_test_' . uniqid(),
            'planId' => 'plan_premium',
            'customerId' => 'cust_123',
            'status' => 'active',
            'billingCycle' => 'monthly'
        ],
        webhookUrl: null // null = use configured webhook URL
    );

    echo "\nTest Results:\n";
    echo "  Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "  Status Code: " . ($result['statusCode'] ?? 'N/A') . "\n";
    echo "  Response Time: " . ($result['responseTime'] ?? 'N/A') . " ms\n";

    if (isset($result['error'])) {
        echo "  Error: " . $result['error'] . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// =============================================================================
// EXAMPLE 4: Test multiple event types
// =============================================================================
echo "\n\n=== Example 4: Test Multiple Event Types ===\n";

$eventTypes = [
    'order.created' => ['orderId' => 'order_123', 'status' => 'pending'],
    'order.paid' => ['orderId' => 'order_123', 'status' => 'paid', 'amount' => 299.90],
    'order.shipped' => ['orderId' => 'order_123', 'trackingCode' => 'BR123456789'],
    'order.delivered' => ['orderId' => 'order_123', 'deliveredAt' => date('c')],
];

foreach ($eventTypes as $eventType => $eventData) {
    try {
        echo "\nTesting event: {$eventType}\n";

        $result = $sdk->notifications()->testWebhookDelivery(
            eventType: $eventType,
            customData: $eventData
        );

        echo "  Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        echo "  Response Time: " . ($result['responseTime'] ?? 'N/A') . " ms\n";

        if (isset($result['error'])) {
            echo "  Error: " . $result['error'] . "\n";
        }

        // Small delay between tests to avoid rate limiting
        usleep(200000); // 200ms

    } catch (Exception $e) {
        echo "  Error testing {$eventType}: " . $e->getMessage() . "\n";
    }
}

// =============================================================================
// EXAMPLE 5: Error handling and validation
// =============================================================================
echo "\n\n=== Example 5: Error Handling and Validation ===\n";

try {
    // Test 1: Missing webhook configuration
    echo "\nTest 5.1: Testing with non-existent event type\n";
    $result = $sdk->notifications()->testWebhookDelivery(
        eventType: 'non.existent.event',
        customData: ['test' => true]
    );

    if (!$result['success']) {
        echo "  Expected error occurred: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

} catch (Exception $e) {
    echo "  Expected error: " . $e->getMessage() . "\n";
}

try {
    // Test 2: Invalid webhook URL
    echo "\nTest 5.2: Testing with invalid webhook URL\n";
    $result = $sdk->notifications()->testWebhookDelivery(
        eventType: 'order.paid',
        customData: ['orderId' => 'test'],
        webhookUrl: 'invalid-url'
    );

    if (!$result['success']) {
        echo "  Expected error occurred: " . ($result['error'] ?? 'Unknown error') . "\n";
    }

} catch (Exception $e) {
    echo "  Expected error: " . $e->getMessage() . "\n";
}

// =============================================================================
// EXAMPLE 6: Performance testing
// =============================================================================
echo "\n\n=== Example 6: Performance Testing ===\n";

try {
    $iterations = 5;
    $responseTimes = [];

    echo "Running {$iterations} webhook tests to measure performance...\n";

    for ($i = 1; $i <= $iterations; $i++) {
        $result = $sdk->notifications()->testWebhookDelivery(
            eventType: 'order.paid',
            customData: [
                'orderId' => "test_perf_{$i}",
                'iteration' => $i
            ]
        );

        if ($result['success'] && isset($result['responseTime'])) {
            $responseTimes[] = $result['responseTime'];
            echo "  Test {$i}: {$result['responseTime']} ms\n";
        }

        usleep(200000); // 200ms delay between tests
    }

    if (!empty($responseTimes)) {
        $avgTime = array_sum($responseTimes) / count($responseTimes);
        $minTime = min($responseTimes);
        $maxTime = max($responseTimes);

        echo "\nPerformance Statistics:\n";
        echo "  Average Response Time: " . round($avgTime, 2) . " ms\n";
        echo "  Minimum Response Time: " . round($minTime, 2) . " ms\n";
        echo "  Maximum Response Time: " . round($maxTime, 2) . " ms\n";
    }

} catch (Exception $e) {
    echo "Error during performance testing: " . $e->getMessage() . "\n";
}

// =============================================================================
// BEST PRACTICES AND TIPS
// =============================================================================
echo "\n\n=== Best Practices and Tips ===\n";
echo "
1. Always test webhooks in a staging environment first
2. Use custom URLs for debugging (e.g., webhook.site)
3. Monitor response times to ensure webhook endpoints are performant
4. Handle webhook timeouts gracefully in your application
5. Implement retry logic for failed webhook deliveries
6. Log all webhook events for debugging and auditing
7. Use the API key method for server-to-server testing
8. Test different event types to ensure complete coverage
9. Validate webhook signatures in your application
10. Set up monitoring and alerting for webhook failures

Available Event Types:
  - order.created
  - order.paid
  - order.shipped
  - order.delivered
  - order.cancelled
  - payment.approved
  - payment.declined
  - payment.refunded
  - subscription.created
  - subscription.updated
  - subscription.cancelled
  - customer.created
  - customer.updated
  - ... and more

For more information, see the SDK documentation.
";

echo "\n=== Webhook Testing Complete ===\n\n";
