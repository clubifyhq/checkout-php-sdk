<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Webhook Management Example - CORRECT USAGE
 *
 * This example demonstrates the proper way to manage webhooks:
 * 1. Create ONE configuration with multiple endpoints (one per event)
 * 2. Or use createOrUpdateWebhook() to automatically merge into existing config
 *
 * IMPORTANT: The notification-service allows multiple webhook configurations per
 * organization (tenant), each with a unique name.
 */

// Load .env file from parent directory if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
    echo "✓ Loaded environment variables from .env\n\n";
}

// Configuration - properly structure credentials for SDK
$config = [
    'credentials' => [
        'api_key' => getenv('CLUBIFY_API_KEY'),
        'tenant_id' => getenv('CLUBIFY_TENANT_ID'),
        'organization_id' => getenv('CLUBIFY_ORGANIZATION_ID'),
        'environment' => getenv('CLUBIFY_ENVIRONMENT') ?: 'live',
    ],
    'base_url' => getenv('CLUBIFY_BASE_URL'),
];

// Debug: Verify configuration
echo "Configuration loaded:\n";
echo "  API Key: " . (getenv('CLUBIFY_API_KEY') ? substr(getenv('CLUBIFY_API_KEY'), 0, 20) . '...' : 'NOT SET') . "\n";
echo "  Tenant ID: " . (getenv('CLUBIFY_TENANT_ID') ?: 'NOT SET') . "\n";
echo "  Organization ID: " . (getenv('CLUBIFY_ORGANIZATION_ID') ?: 'NOT SET') . "\n";
echo "  Environment: " . ($config['credentials']['environment']) . "\n";
echo "  Base URL: " . (getenv('CLUBIFY_BASE_URL') ?: 'NOT SET') . "\n\n";

// Validate required configuration
if (!getenv('CLUBIFY_API_KEY')) {
    die("ERROR: CLUBIFY_API_KEY not set in .env file\n");
}
if (!getenv('CLUBIFY_TENANT_ID')) {
    die("ERROR: CLUBIFY_TENANT_ID not set in .env file\n");
}
if (!getenv('CLUBIFY_ORGANIZATION_ID')) {
    die("ERROR: CLUBIFY_ORGANIZATION_ID not set in .env file\n");
}

$clubify = new ClubifyCheckoutSDK($config);

// Initialize SDK to authenticate and get access token
echo "Initializing SDK for authentication...\n";
try {
    $initResult = $clubify->initialize();
    if ($initResult['success'] && $initResult['authenticated']) {
        echo "✓ SDK initialized and authenticated successfully\n";
        echo "  Access token obtained: " . ($initResult['authenticated'] ? 'Yes' : 'No') . "\n\n";
    } else {
        die("✗ SDK initialization failed\n");
    }
} catch (\Exception $e) {
    die("✗ SDK initialization error: " . $e->getMessage() . "\n");
}

$webhooks = $clubify->webhooks();

// ============================================================================
// EXAMPLE 1: Create a single webhook configuration with multiple events
// ============================================================================

echo "Example 1: Creating webhook configuration with multiple events...\n";

// First, check if webhook configuration already exists
try {
    $existingConfigs = $webhooks->listWebhooks(['organization_id' => $config['credentials']['organization_id']]);

    if (!empty($existingConfigs) && count($existingConfigs) > 0) {
        echo "⚠ Webhook configuration already exists for this organization.\n";
        echo "  Found " . count($existingConfigs) . " existing configuration(s).\n";
        echo "  Skipping creation. Use Example 2 (createOrUpdateWebhook) to add events.\n\n";
    } else {
        // No existing config, create a new one
        $webhookConfig = $webhooks->createWebhook([
            'url' => 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify/subscription',
            'events' => [
                'subscription.created',
                'subscription.updated',
                'subscription.cancelled',
                'subscription.payment_failed',
                'payment.paid',
                'payment.failed',
                'payment.refunded',
                'order.created',
                'order.paid',
                'order.completed',
                'order.cancelled',
            ],
            'description' => 'Main webhook configuration for all events',
            'organization_id' => $config['credentials']['organization_id'],
            'tenant_id' => $config['credentials']['tenant_id'],
            'active' => true,
            'timeout' => 30,
            'max_retries' => 3,
            'headers' => [
                'X-Custom-Header' => 'my-value'
            ]
        ]);

        echo "✓ Webhook configuration created successfully!\n";
        echo "  Config ID: {$webhookConfig['_id']}\n";
        echo "  Endpoints created: " . count($webhookConfig['endpoints']) . "\n\n";
    }
} catch (\Exception $e) {
    echo "✗ Error in Example 1: {$e->getMessage()}\n";
    if (method_exists($e, 'getContext')) {
        echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

// ============================================================================
// EXAMPLE 2: Use createOrUpdateWebhook() to add events to existing config
// ============================================================================

echo "Example 2: Using createOrUpdateWebhook() to add new events...\n";

try {
    // This method will:
    // - Create a new config if none exists
    // - OR add the events to the existing configuration
    $result = $webhooks->createOrUpdateWebhook([
        'url' => 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify/customer',
        'events' => [
            'customer.created',
            'customer.updated',
            'cart.abandoned',
        ],
        'organization_id' => $config['credentials']['organization_id'],
        'tenant_id' => $config['credentials']['tenant_id'],
        'timeout' => 30,
    ]);

    echo "✓ Webhook updated successfully!\n";
    echo "  Total endpoints now: " . count($result['endpoints'] ?? []) . "\n\n";

} catch (\Exception $e) {
    echo "✗ Error in Example 2: {$e->getMessage()}\n";
    if (method_exists($e, 'getContext')) {
        echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

// ============================================================================
// EXAMPLE 3: Manage individual endpoints
// ============================================================================
//
// ⚠️  IMPORTANT: Eventual Consistency Behavior
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// The notification-service API uses eventual consistency with replication lag.
// After creating/updating endpoints, there may be a 5-10 second delay before
// changes are visible in subsequent read operations.
//
// 🔄 SDK RETRY MECHANISM:
// The SDK has built-in retry logic with exponential backoff:
// - 5 retry attempts (configurable)
// - Exponential backoff: 1s, 2s, 4s, 8s, 16s
// - Retries on 404 errors (endpoint not found after creation)
//
// 📋 PRODUCTION BEST PRACTICES:
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// 1. ✅ CREATE WITH CORRECT PROPERTIES from the start (avoid immediate updates)
//    - Set isActive, timeout, max_retries, headers during creation
//    - This avoids the need for immediate update operations
//
// 2. ⏱️  ADD DELAYS between consecutive operations on the same endpoint
//    - Wait 10+ seconds between create → update or update → delete
//    - Use sleep() for sequential operations in scripts
//
// 3. 🔁 IMPLEMENT RETRY LOGIC for production applications
//    - Check if operation succeeded before proceeding
//    - Retry failed operations after a delay
//    - The SDK will retry automatically, but your code should handle final failures
//
// 4. ⚠️  HANDLE EXPECTED FAILURES gracefully
//    - Catch exceptions and check for consistency-related errors
//    - Provide helpful error messages to users
//    - Log issues for debugging
//
// This example demonstrates both the problem and the solutions.
// ============================================================================

echo "Example 3: Managing individual endpoints...\n";
echo "Note: Demonstrates eventual consistency handling.\n\n";

// List all webhooks for the organization
try {
    $configList = $webhooks->listWebhooks(['organization_id' => $config['credentials']['organization_id']]);
    echo "✓ Current webhook configurations: " . count($configList) . "\n";

    // List endpoints from first config
    if (!empty($configList)) {
        $firstConfig = $configList[0];
        $endpoints = $firstConfig['endpoints'] ?? [];
        echo "✓ Endpoints in first config (" . count($endpoints) . "):\n";
        foreach ($endpoints as $endpoint) {
            $status = ($endpoint['isActive'] ?? true) ? 'active' : 'inactive';
            echo "  - {$endpoint['eventType']}: {$endpoint['url']} [{$status}]\n";
        }
    }
    echo "\n";
} catch (\Exception $e) {
    echo "✗ Error listing webhooks: {$e->getMessage()}\n";
    if (method_exists($e, 'getContext')) {
        echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

// ────────────────────────────────────────────────────────────────────────────
// URL VALIDATION: Validate webhook URL before creating endpoint
// ────────────────────────────────────────────────────────────────────────────
// IMPORTANT: Always validate URLs before creating endpoints to ensure they can
// receive webhooks. This prevents configuration errors and failed deliveries.

echo "URL Validation: Testing webhook endpoint accessibility...\n";

$webhookUrl = 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify/subscription';

try {
    $validation = $webhooks->validateUrl($webhookUrl);

    echo "✓ URL Validation Results:\n";
    echo "  - URL: {$validation['url']}\n";
    echo "  - Accessible: " . ($validation['accessible'] ? 'Yes' : 'No') . "\n";
    echo "  - Response Code: " . ($validation['response_code'] ?? 'N/A') . "\n";
    echo "  - Response Time: " . ($validation['response_time'] ?? 'N/A') . "ms\n";
    echo "  - SSL Valid: " . ($validation['ssl_valid'] ? 'Yes' : 'No') . "\n";
    echo "  - Redirects: " . ($validation['redirect_count'] ?? 0) . "\n";

    if (isset($validation['headers']) && !empty($validation['headers'])) {
        echo "  - Server: " . ($validation['headers']['server'] ?? 'Unknown') . "\n";
    }

    if ($validation['accessible']) {
        echo "  ✓ URL is valid and ready to receive webhooks!\n";
    } else {
        echo "  ⚠ URL validation failed: " . ($validation['error'] ?? 'Unknown error') . "\n";
        echo "  ⚠ Creating endpoint anyway for demonstration purposes.\n";
        echo "  ⚠ In production, you should NOT create endpoints with invalid URLs.\n";
    }

    if (!$validation['ssl_valid'] && strpos($webhookUrl, 'https://') === 0) {
        echo "  ⚠ WARNING: SSL certificate is invalid!\n";
        echo "  ⚠ This is not recommended for production webhooks.\n";
    }

    echo "\n";

} catch (\Exception $e) {
    echo "⚠ Error validating URL: {$e->getMessage()}\n";
    echo "  Continuing with endpoint creation anyway...\n\n";
}

echo "📚 Validation Explained:\n";
echo "  • accessible: URL responds to HTTP requests (200-499 status codes)\n";
echo "  • response_code: HTTP status code returned by the endpoint\n";
echo "  • response_time: How fast the endpoint responds (important for webhooks)\n";
echo "  • ssl_valid: SSL certificate is valid (required for HTTPS URLs)\n";
echo "  • redirect_count: Number of redirects followed (0 is best for webhooks)\n";
echo "\n";

echo "💡 When to Validate URLs:\n";
echo "  ✓ Before creating new webhook endpoints\n";
echo "  ✓ When updating webhook URLs\n";
echo "  ✓ During webhook configuration audits\n";
echo "  ✓ When troubleshooting failed deliveries\n";
echo "  ✗ NOT needed for every webhook delivery (too slow)\n";
echo "\n";

// ────────────────────────────────────────────────────────────────────────────
// APPROACH 1: Add endpoint with all properties (RECOMMENDED)
// ────────────────────────────────────────────────────────────────────────────
// This avoids the need for immediate updates after creation.

echo "Approach 1: Creating endpoint with all desired properties...\n";

try {
    $webhooks->addEndpoint(
        $config['credentials']['organization_id'],
        'Default Configuration', // Config name
        'product.updated',       // Event type
        $webhookUrl,             // URL (already validated above)
        [
            'isActive' => false,    // Set to desired state from the start
            'timeout' => 15,        // Custom timeout
            'max_retries' => 5,     // Custom retry count
            // Optional: Add custom headers
            // 'headers' => ['X-Custom-Header' => 'value'],
        ]
    );
    echo "✓ Added endpoint for product.updated (created with isActive=false)\n";
    echo "  This is the RECOMMENDED approach - create with correct properties.\n\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'duplicate') !== false ||
        strpos($e->getMessage(), 'exists') !== false) {
        echo "⚠ Endpoint already exists for product.updated\n";
        echo "  Continuing with update/delete examples...\n\n";
    } else {
        echo "✗ Error adding endpoint: {$e->getMessage()}\n";
        if (method_exists($e, 'getContext')) {
            echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }
}

// ────────────────────────────────────────────────────────────────────────────
// APPROACH 2: Update endpoint with delay (for existing endpoints)
// ────────────────────────────────────────────────────────────────────────────
// If you need to update an existing endpoint or one just created, add a delay.

echo "Approach 2: Updating endpoint with consistency delay...\n";
echo "⏳ Waiting 6 seconds for API consistency...\n";
sleep(6); // Wait for replication

try {
    $webhooks->updateEndpoint(
        $config['credentials']['organization_id'],
        'Default Configuration',
        'product.updated',
        [
            'isActive' => true,     // Re-enable the endpoint
            'timeout' => 20,        // Update timeout
        ]
    );
    echo "✓ Updated endpoint for product.updated (enabled and timeout changed)\n";
    echo "  The 6-second delay ensured the endpoint was replicated.\n\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'not found') !== false) {
        echo "⚠ Endpoint not found - API hasn't replicated yet.\n";
        echo "  In production: Increase delay or implement retry logic.\n";
        echo "  The SDK already retried 5 times with exponential backoff.\n";
        echo "  You may need to wait longer or try again later.\n\n";
    } else {
        echo "✗ Error updating endpoint: {$e->getMessage()}\n";
        if (method_exists($e, 'getContext')) {
            echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }
}

// ────────────────────────────────────────────────────────────────────────────
// APPROACH 3: Delete endpoint with proper error handling
// ────────────────────────────────────────────────────────────────────────────
// Demonstrate graceful handling of consistency issues during deletion.

echo "Approach 3: Removing endpoint with error handling...\n";
echo "⏳ Waiting 6 seconds for API consistency...\n";
sleep(6); // Wait for replication

try {
    $webhooks->removeEndpoint(
        $config['credentials']['organization_id'],
        'Default Configuration',
        'product.updated'
    );
    echo "✓ Removed endpoint for product.updated\n";
    echo "  The delay ensured the endpoint was available for deletion.\n\n";
} catch (\Exception $e) {
    if (strpos($e->getMessage(), 'not found') !== false) {
        echo "⚠ Endpoint not found for deletion.\n";
        echo "  Possible reasons:\n";
        echo "  1. Already deleted by another process\n";
        echo "  2. API replication lag (try again in 10 seconds)\n";
        echo "  3. Endpoint was never created successfully\n\n";
    } else {
        echo "✗ Error removing endpoint: {$e->getMessage()}\n";
        if (method_exists($e, 'getContext')) {
            echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
        }
        echo "\n";
    }
}

// ────────────────────────────────────────────────────────────────────────────
// PRODUCTION EXAMPLE: Comprehensive error handling and retry logic
// ────────────────────────────────────────────────────────────────────────────

echo "Production Example: Robust endpoint management with validation...\n";

/**
 * Helper function: Create validated endpoint with comprehensive checks
 *
 * This function demonstrates production-ready webhook endpoint creation:
 * 1. Validates URL accessibility and SSL
 * 2. Creates endpoint with proper configuration
 * 3. Verifies endpoint was created successfully
 * 4. Handles errors gracefully with retries
 *
 * @param object $webhooks Webhook service instance
 * @param string $orgId Organization ID
 * @param string $configName Configuration name
 * @param string $eventType Event type (e.g., 'product.updated')
 * @param string $url Webhook URL
 * @param array $options Additional options (isActive, timeout, etc.)
 * @return bool True if successful, false otherwise
 */
function createValidatedEndpoint($webhooks, $orgId, $configName, $eventType, $url, $options = []) {
    echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "Creating Validated Endpoint: {$eventType}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    // ─────────────────────────────────────────────────────────────
    // STEP 1: Validate URL accessibility
    // ─────────────────────────────────────────────────────────────
    echo "Step 1: Validating URL...\n";

    try {
        $validation = $webhooks->validateUrl($url);

        echo "  ✓ URL validation completed\n";
        echo "    - Accessible: " . ($validation['accessible'] ? 'Yes' : 'No') . "\n";
        echo "    - Response Code: " . ($validation['response_code'] ?? 'N/A') . "\n";
        echo "    - Response Time: " . ($validation['response_time'] ?? 'N/A') . "ms\n";
        echo "    - SSL Valid: " . ($validation['ssl_valid'] ? 'Yes' : 'No') . "\n";

        // Check if URL is accessible
        if (!$validation['accessible']) {
            echo "  ✗ ERROR: URL is not accessible\n";
            echo "    Error: " . ($validation['error'] ?? 'Unknown error') . "\n";
            echo "    Please check the URL and try again.\n\n";
            return false;
        }

        // Warn about SSL issues (but continue)
        if (!$validation['ssl_valid'] && strpos($url, 'https://') === 0) {
            echo "  ⚠ WARNING: SSL certificate is invalid\n";
            echo "    This is not recommended for production webhooks.\n";
            echo "    Continuing anyway...\n";
        }

        // Warn about slow response times
        if (isset($validation['response_time']) && $validation['response_time'] > 5000) {
            echo "  ⚠ WARNING: Endpoint response time is slow ({$validation['response_time']}ms)\n";
            echo "    This may cause webhook delivery timeouts.\n";
            echo "    Consider optimizing your endpoint.\n";
        }

        echo "\n";

    } catch (\Exception $e) {
        echo "  ✗ ERROR: URL validation failed\n";
        echo "    Exception: {$e->getMessage()}\n";
        echo "    Aborting endpoint creation.\n\n";
        return false;
    }

    // ─────────────────────────────────────────────────────────────
    // STEP 2: Create endpoint
    // ─────────────────────────────────────────────────────────────
    echo "Step 2: Creating endpoint...\n";

    try {
        $webhooks->addEndpoint($orgId, $configName, $eventType, $url, $options);
        echo "  ✓ Endpoint created successfully\n";
        echo "    - Event Type: {$eventType}\n";
        echo "    - URL: {$url}\n";
        echo "    - Active: " . (($options['isActive'] ?? true) ? 'Yes' : 'No') . "\n";
        echo "    - Timeout: " . ($options['timeout'] ?? 30) . "s\n";
        echo "    - Max Retries: " . ($options['max_retries'] ?? 3) . "\n\n";

    } catch (\Exception $e) {
        // Check if endpoint already exists
        if (strpos($e->getMessage(), 'duplicate') !== false ||
            strpos($e->getMessage(), 'exists') !== false) {
            echo "  ⚠ Endpoint already exists for {$eventType}\n";
            echo "    Treating as success and continuing to verification.\n\n";
        } else {
            echo "  ✗ ERROR: Failed to create endpoint\n";
            echo "    Exception: {$e->getMessage()}\n\n";
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // STEP 3: Wait for API consistency
    // ─────────────────────────────────────────────────────────────
    echo "Step 3: Waiting for API consistency...\n";
    echo "  ⏳ Waiting 6 seconds for replication...\n";
    sleep(6);
    echo "  ✓ Wait completed\n\n";

    // ─────────────────────────────────────────────────────────────
    // STEP 4: Verify endpoint exists
    // ─────────────────────────────────────────────────────────────
    echo "Step 4: Verifying endpoint...\n";

    $maxAttempts = 3;
    $delaySeconds = 3;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
            $configs = $webhooks->listWebhooks(['organization_id' => $orgId]);

            foreach ($configs as $config) {
                if ($config['name'] === $configName) {
                    foreach (($config['endpoints'] ?? []) as $endpoint) {
                        if ($endpoint['eventType'] === $eventType) {
                            echo "  ✓ Endpoint verified successfully!\n";
                            echo "    - Config ID: {$config['_id']}\n";
                            echo "    - Config Name: {$config['name']}\n";
                            echo "    - Total Endpoints: " . count($config['endpoints']) . "\n";
                            echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                            echo "✓ Endpoint creation COMPLETED successfully!\n";
                            echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
                            return true;
                        }
                    }
                }
            }

            echo "  ⚠ Verification attempt {$attempt}/{$maxAttempts}: Endpoint not found yet\n";

            if ($attempt < $maxAttempts) {
                echo "    Retrying in {$delaySeconds} seconds...\n";
                sleep($delaySeconds);
                $delaySeconds *= 2; // Exponential backoff
            }

        } catch (\Exception $e) {
            echo "  ⚠ Verification attempt {$attempt} failed: {$e->getMessage()}\n";

            if ($attempt < $maxAttempts) {
                echo "    Retrying...\n";
                sleep($delaySeconds);
            }
        }
    }

    echo "  ⚠ WARNING: Could not verify endpoint after {$maxAttempts} attempts\n";
    echo "    The endpoint may still exist. Check manually with listWebhooks().\n";
    echo "    This can happen due to API replication lag.\n\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "⚠ Endpoint creation UNCERTAIN (verification failed)\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
    return false;
}

// Example usage of the helper function
try {
    $success = createValidatedEndpoint(
        $webhooks,
        $config['credentials']['organization_id'],
        'Default Configuration',
        'product.deleted',
        'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify',
        [
            'isActive' => true,
            'timeout' => 25,
            'max_retries' => 3,
        ]
    );

    if ($success) {
        echo "✓ Production-ready endpoint management completed successfully!\n\n";
    } else {
        echo "⚠ Endpoint creation failed or verification uncertain.\n";
        echo "  Check logs and configuration. The endpoint may still exist.\n\n";
    }
} catch (\Exception $e) {
    echo "✗ Error in production example: {$e->getMessage()}\n";
    if (method_exists($e, 'getContext')) {
        echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
    echo "\n";
}

// ────────────────────────────────────────────────────────────────────────────
// KEY TAKEAWAYS
// ────────────────────────────────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "KEY TAKEAWAYS for Production Use:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "📋 ENDPOINT VALIDATION:\n";
echo "  1. ✅ VALIDATE URLs before creating endpoints using validateUrl()\n";
echo "     - Checks accessibility, SSL, and response times\n";
echo "     - Prevents configuration errors and failed deliveries\n";
echo "     - Validates SSL certificates for HTTPS endpoints\n";
echo "  2. ⚠️  WARN users about slow endpoints (>5000ms response time)\n";
echo "  3. ⚠️  WARN about invalid SSL certificates (but allow for testing)\n";
echo "  4. ❌ BLOCK inaccessible URLs (HTTP errors, DNS failures)\n\n";

echo "🔧 ENDPOINT CREATION:\n";
echo "  1. ✅ CREATE endpoints with all properties from the start (avoid updates)\n";
echo "     - Set isActive, timeout, max_retries during creation\n";
echo "     - This avoids immediate update operations\n";
echo "  2. ⏱️  WAIT 6-10+ seconds between operations on the same endpoint\n";
echo "  3. 🔁 IMPLEMENT retry logic with exponential backoff\n";
echo "  4. ✅ VERIFY operations succeeded before proceeding\n";
echo "  5. ⚠️  HANDLE 404 errors gracefully (may indicate replication lag)\n";
echo "  6. 🎯 USE createOrUpdateWebhook() for simpler, safer bulk operations\n\n";

echo "🏭 PRODUCTION BEST PRACTICES:\n";
echo "  1. 🔒 ALWAYS use HTTPS for production webhooks\n";
echo "  2. 🔐 VALIDATE SSL certificates in production environments\n";
echo "  3. ⏱️  MONITOR endpoint response times (should be <2000ms)\n";
echo "  4. 📊 TRACK webhook delivery success rates\n";
echo "  5. 🔄 IMPLEMENT automatic retry for failed deliveries\n";
echo "  6. 📝 LOG all webhook operations for debugging\n";
echo "  7. 🧪 TEST webhooks in staging before production deployment\n";
echo "  8. 🚨 SET UP alerts for webhook delivery failures\n\n";

echo "🛠️ HELPER FUNCTIONS:\n";
echo "  • createValidatedEndpoint() - Complete validation workflow\n";
echo "    - Validates URL accessibility and SSL\n";
echo "    - Creates endpoint with proper configuration\n";
echo "    - Verifies endpoint was created successfully\n";
echo "    - Handles errors gracefully with retries\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";

// ============================================================================
// EXAMPLE 4: Get webhook configuration
// ============================================================================

echo "Example 4: Retrieving webhook configuration...\n";

try {
    $configList = $webhooks->listWebhooks(['organization_id' => $config['credentials']['organization_id']]);
    $webhookConfig = $configList[0] ?? null;

    if ($webhookConfig) {
        echo "✓ Configuration found:\n";
        echo "  Name: {$webhookConfig['name']}\n";
        echo "  Active: " . ($webhookConfig['isActive'] ? 'Yes' : 'No') . "\n";
        echo "  Endpoints: " . count($webhookConfig['endpoints']) . "\n";
        echo "  Created: {$webhookConfig['createdAt']}\n";
    } else {
        echo "⚠ No configuration found\n";
    }
} catch (\Exception $e) {
    echo "✗ Error retrieving configuration: {$e->getMessage()}\n";
    if (method_exists($e, 'getContext')) {
        echo "  Context: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n=== Webhook Management Examples Complete ===\n";
