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
            'url' => 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify',
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
        'url' => 'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify',
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
// APPROACH 1: Add endpoint with all properties (RECOMMENDED)
// ────────────────────────────────────────────────────────────────────────────
// This avoids the need for immediate updates after creation.

echo "Approach 1: Creating endpoint with all desired properties...\n";

try {
    $webhooks->addEndpoint(
        $config['credentials']['organization_id'],
        'Default Configuration', // Config name
        'product.updated',       // Event type
        'https://webhook.site/253381d2-654a-4f9b-b44e-4ce347f1d087/webhooks/clubify', // URL
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

echo "Production Example: Robust endpoint management with custom retry...\n";

/**
 * Helper function: Add endpoint with verification
 */
function addEndpointWithVerification($webhooks, $orgId, $configName, $eventType, $url, $options = []) {
    $maxAttempts = 3;
    $delaySeconds = 5;

    // Attempt to add endpoint
    try {
        $webhooks->addEndpoint($orgId, $configName, $eventType, $url, $options);
        echo "✓ Added endpoint for {$eventType}\n";
    } catch (\Exception $e) {
        if (strpos($e->getMessage(), 'duplicate') !== false ||
            strpos($e->getMessage(), 'exists') !== false) {
            echo "⚠ Endpoint already exists for {$eventType}\n";
            return true; // Already exists, consider it success
        }
        throw $e; // Re-throw other errors
    }

    // Verify endpoint was created (with retries)
    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        echo "  Verifying creation (attempt {$attempt}/{$maxAttempts})...\n";
        sleep($delaySeconds);

        try {
            $configs = $webhooks->listWebhooks(['organization_id' => $orgId]);
            foreach ($configs as $config) {
                if ($config['name'] === $configName) {
                    foreach ($config['endpoints'] as $endpoint) {
                        if ($endpoint['eventType'] === $eventType) {
                            echo "✓ Endpoint verified successfully!\n";
                            return true;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            echo "  Verification attempt {$attempt} failed: {$e->getMessage()}\n";
        }

        if ($attempt < $maxAttempts) {
            $delaySeconds *= 2; // Exponential backoff
        }
    }

    echo "⚠ Could not verify endpoint creation after {$maxAttempts} attempts.\n";
    echo "  The endpoint may still be created, check manually.\n";
    return false;
}

// Example usage of the helper function
try {
    $verified = addEndpointWithVerification(
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

    if ($verified) {
        echo "✓ Production-ready endpoint management completed successfully!\n\n";
    } else {
        echo "⚠ Endpoint created but verification uncertain. Check configuration.\n\n";
    }
} catch (\Exception $e) {
    echo "✗ Error in production example: {$e->getMessage()}\n\n";
}

// ────────────────────────────────────────────────────────────────────────────
// KEY TAKEAWAYS
// ────────────────────────────────────────────────────────────────────────────
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "KEY TAKEAWAYS for Production Use:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. CREATE endpoints with all properties from the start (avoid updates)\n";
echo "2. WAIT 10+ seconds between operations on the same endpoint\n";
echo "3. IMPLEMENT retry logic with exponential backoff\n";
echo "4. VERIFY operations succeeded before proceeding\n";
echo "5. HANDLE 404 errors gracefully (may indicate replication lag)\n";
echo "6. USE createOrUpdateWebhook() for simpler, safer bulk operations\n";
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
