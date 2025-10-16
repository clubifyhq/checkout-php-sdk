<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Simple Webhook Test Example
 *
 * Quick example to test webhook delivery using the SDK.
 * Run this script to verify your webhook configuration is working.
 *
 * Usage:
 *   php examples/simple-webhook-test.php
 */

// Load environment variables - Try multiple locations
$envPaths = [
    __DIR__ . '/..',                           // SDK root: sdk/checkout/php/.env
    __DIR__ . '/../../..',                     // Project root: clubify-checkout/.env
    __DIR__ . '/../../../..',                  // Parent project: python/clubify-checkout/.env
];

$envLoaded = false;
foreach ($envPaths as $path) {
    if (file_exists($path . '/.env')) {
        try {
            $dotenv = Dotenv\Dotenv::createImmutable($path);
            $dotenv->load();
            $envLoaded = true;
            break;
        } catch (\Exception $e) {
            continue;
        }
    }
}

if (!$envLoaded) {
    die("❌ ERROR: Could not find .env file. Please create one with your credentials.\n");
}

// Verify required environment variables
$requiredVars = [
    'CLUBIFY_CHECKOUT_ORGANIZATION_ID',
    'CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY',
    'CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ID',
];

$missingVars = array_filter($requiredVars, fn($v) => !isset($_ENV[$v]) || empty($_ENV[$v]));
if (!empty($missingVars)) {
    die("❌ ERROR: Missing required variables: " . implode(', ', $missingVars) . "\n");
}

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║           Clubify Checkout - Webhook Test Tool              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "📋 Configuration:\n";
echo "  - Organization: " . substr($_ENV['CLUBIFY_CHECKOUT_ORGANIZATION_ID'], 0, 8) . "...\n";
echo "  - Tenant: " . substr($_ENV['CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ID'], 0, 8) . "...\n";
echo "  - Environment: " . ($_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'staging') . "\n\n";

try {
    // Initialize SDK
    echo "Initializing SDK...\n";
    $sdk = new ClubifyCheckoutSDK([
        'credentials' => [
            'organization_id' => $_ENV['CLUBIFY_CHECKOUT_ORGANIZATION_ID'],
            'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ID'],
            'api_key' => $_ENV['CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY']
        ],
        'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'staging'
    ]);

    $initResult = $sdk->initialize();

    if (!$initResult['authenticated']) {
        throw new Exception('Failed to authenticate SDK');
    }

    echo "✓ SDK initialized successfully\n";
    echo "  Environment: " . $initResult['environment'] . "\n";
    echo "  Tenant ID: " . $initResult['tenant_id'] . "\n\n";

    // Test webhook delivery
    echo "Testing webhook delivery...\n";
    echo "  Event Type: order.paid\n";
    echo "  Webhook URL: (using configured URL)\n\n";

    $startTime = microtime(true);

    $result = $sdk->notifications()->testWebhookDelivery(
        eventType: 'order.paid',
        customData: [
            'orderId' => 'order_test_' . uniqid(),
            'amount' => 99.99,
            'currency' => 'BRL',
            'test' => true
        ]
    );

    $totalTime = round((microtime(true) - $startTime) * 1000, 2);

    // Display results
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "                      Test Results                            \n";
    echo "═══════════════════════════════════════════════════════════════\n\n";

    if ($result['success']) {
        echo "✓ Webhook test SUCCESSFUL\n\n";
        echo "Response Details:\n";
        echo "  Status Code:    " . ($result['statusCode'] ?? 'N/A') . "\n";
        echo "  Response Time:  " . ($result['responseTime'] ?? 'N/A') . " ms\n";
        echo "  Total Time:     {$totalTime} ms\n";
        echo "  Webhook URL:    " . ($result['webhookUrl'] ?? 'N/A') . "\n";
        echo "  Event Type:     " . ($result['eventType'] ?? 'N/A') . "\n";
        echo "  Timestamp:      " . ($result['timestamp'] ?? 'N/A') . "\n";

        if (isset($result['responseBody'])) {
            echo "\nWebhook Response:\n";
            if (is_array($result['responseBody'])) {
                echo json_encode($result['responseBody'], JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "  " . $result['responseBody'] . "\n";
            }
        }

        echo "\n✓ Your webhook endpoint received the test event successfully!\n";

    } else {
        echo "✗ Webhook test FAILED\n\n";
        echo "Error Details:\n";
        echo "  Error:          " . ($result['error'] ?? 'Unknown error') . "\n";
        echo "  Status Code:    " . ($result['statusCode'] ?? 'N/A') . "\n";
        echo "  Response Time:  " . ($result['responseTime'] ?? 'N/A') . " ms\n";
        echo "  Total Time:     {$totalTime} ms\n";
        echo "  Webhook URL:    " . ($result['webhookUrl'] ?? 'N/A') . "\n";
        echo "  Timestamp:      " . ($result['timestamp'] ?? 'N/A') . "\n";

        echo "\n✗ Please check your webhook configuration and endpoint.\n";
    }

    echo "\n═══════════════════════════════════════════════════════════════\n\n";

    // Show next steps
    if ($result['success']) {
        echo "Next Steps:\n";
        echo "  • Test other event types (payment.approved, order.created, etc.)\n";
        echo "  • Verify webhook signature validation in your endpoint\n";
        echo "  • Monitor webhook delivery logs in the notification-service\n";
        echo "  • Set up retry logic for failed webhook deliveries\n";
    } else {
        echo "Troubleshooting Tips:\n";
        echo "  • Verify your webhook URL is accessible from the internet\n";
        echo "  • Check if your endpoint returns a 2xx status code\n";
        echo "  • Ensure your webhook endpoint can handle POST requests\n";
        echo "  • Check firewall and security settings\n";
        echo "  • Review logs at your webhook endpoint for errors\n";
        echo "  • Try testing with webhook.site for debugging\n";
    }

    echo "\nFor more examples, see: examples/webhook-test-example.php\n\n";

} catch (Exception $e) {
    echo "\n✗ Error occurred:\n";
    echo "  Message: " . $e->getMessage() . "\n";

    if ($e->getPrevious()) {
        echo "  Previous: " . $e->getPrevious()->getMessage() . "\n";
    }

    echo "\nPlease check:\n";
    echo "  • Environment variables are set correctly (.env file)\n";
    echo "  • Organization ID and API Key are valid\n";
    echo "  • Tenant ID is configured\n";
    echo "  • Webhook configuration exists in notification-service\n";
    echo "\n";

    exit(1);
}
