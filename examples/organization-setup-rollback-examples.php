<?php

/**
 * Organization Setup with Rollback and Recovery Examples
 *
 * This file demonstrates various scenarios for organization setup
 * with comprehensive rollback and recovery handling.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Organization\Exceptions\OrganizationSetupException;
use Clubify\Checkout\Exceptions\ConflictException;




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

// Configurações (em produção, use variáveis de ambiente)
$organizationId = getenv('CLUBIFY_CHECKOUT_ORGANIZATION_ID');
$organizationApiKey = getenv('CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY');
$tenantId = getenv('CLUBIFY_TENANT_ID'); // Tenant específico para as operações
$environment = getenv('CLUBIFY_CHECKOUT_ENVIRONMENT');


// Initialize SDK
$sdk = new ClubifyCheckoutSDK([
    'api_key' => $organizationApiKey,
    'environment' => $environment
]);

// Get organization module
$organizationModule = $sdk->organization();

/**
 * Example 1: Basic Setup with Automatic Rollback
 */
function example1_basicSetupWithRollback($organizationModule)
{
    echo "=== Example 1: Basic Setup with Automatic Rollback ===\n";

    $organizationData = [
        'name' => 'Test Company',
        'subdomain' => 'test-company',
        'admin_name' => 'John Doe',
        'admin_email' => 'john@testcompany.com',
        'admin_password' => 'secure_password_123',
        'domain' => 'testcompany.com'
    ];

    try {
        $result = $organizationModule->setupOrganization(
            $organizationData,
            null,  // Auto-generate idempotency key
            true,  // Enable rollback
            true   // Enable retry
        );

        echo "✅ Setup completed successfully!\n";
        echo "Organization ID: " . $result['organization']['id'] . "\n";
        echo "Tenant ID: " . $result['tenant']['id'] . "\n";
        echo "Admin ID: " . $result['admin']['id'] . "\n";
        echo "Idempotency Key: " . $result['setup_metadata']['idempotency_key'] . "\n";

    } catch (OrganizationSetupException $e) {
        echo "❌ Setup failed at step: " . $e->getSetupStep() . "\n";
        echo "Error: " . $e->getMessage() . "\n";

        if ($e->isRollbackRequired()) {
            echo "🔄 Rollback was required and " .
                 ($e->getRollbackProcedures() ? "executed" : "failed") . "\n";
        }

        // Show recovery options
        $recoveryOptions = $e->getRecoveryOptions();
        if (!empty($recoveryOptions)) {
            echo "\n📋 Recovery Options:\n";
            foreach ($recoveryOptions as $option) {
                echo "- " . $option['description'] . "\n";
            }
        }
    }
}

/**
 * Example 2: Setup with Conflict Resolution
 */
function example2_setupWithConflictResolution($organizationModule)
{
    echo "\n=== Example 2: Setup with Conflict Resolution ===\n";

    $organizationData = [
        'name' => 'Existing Company',
        'subdomain' => 'existing-subdomain', // This might already exist
        'admin_name' => 'Jane Smith',
        'admin_email' => 'existing@email.com', // This might already exist
        'admin_password' => 'secure_password_456'
    ];

    $idempotencyKey = 'setup_existing_company_' . time();

    try {
        $result = $organizationModule->setupOrganization(
            $organizationData,
            $idempotencyKey,
            true,  // Enable rollback
            true   // Enable retry (includes conflict resolution)
        );

        echo "✅ Setup completed (possibly using existing resources)!\n";

        if (isset($result['recovery_type'])) {
            echo "🔄 Recovery type: " . $result['recovery_type'] . "\n";
            echo "ℹ️ " . $result['message'] . "\n";
        }

    } catch (OrganizationSetupException $e) {
        $previous = $e->getPrevious();

        if ($previous instanceof ConflictException) {
            echo "⚠️ Conflict detected: " . $previous->getConflictType() . "\n";
            echo "Existing resource ID: " . ($previous->getExistingResourceId() ?? 'unknown') . "\n";

            // Show resolution suggestions
            $suggestions = $previous->getResolutionSuggestions();
            echo "\n💡 Suggestions:\n";
            foreach ($suggestions as $suggestion) {
                echo "- " . $suggestion . "\n";
            }
        }
    }
}

/**
 * Example 3: Manual Retry with Different Parameters
 */
function example3_manualRetryWithDifferentParams($organizationModule)
{
    echo "\n=== Example 3: Manual Retry with Different Parameters ===\n";

    $baseOrganizationData = [
        'name' => 'Retry Company',
        'admin_name' => 'Bob Wilson',
        'admin_password' => 'secure_password_789'
    ];

    $attempts = [
        ['subdomain' => 'retry-company', 'admin_email' => 'bob@retrycompany.com'],
        ['subdomain' => 'retry-company-alt', 'admin_email' => 'bob@retrycompany.com'],
        ['subdomain' => 'retry-company-alt', 'admin_email' => 'bob.wilson@retrycompany.com']
    ];

    foreach ($attempts as $index => $attempt) {
        echo "\n--- Attempt " . ($index + 1) . " ---\n";

        $organizationData = array_merge($baseOrganizationData, $attempt);
        $idempotencyKey = 'retry_company_attempt_' . ($index + 1);

        try {
            $result = $organizationModule->setupOrganization(
                $organizationData,
                $idempotencyKey,
                true,  // Enable rollback
                false  // Disable automatic retry for manual control
            );

            echo "✅ Setup succeeded on attempt " . ($index + 1) . "!\n";
            break; // Success, exit loop

        } catch (OrganizationSetupException $e) {
            echo "❌ Attempt " . ($index + 1) . " failed: " . $e->getMessage() . "\n";

            if ($index === count($attempts) - 1) {
                echo "💥 All attempts exhausted\n";

                // Generate manual cleanup report if needed
                if ($e->isRollbackRequired()) {
                    $cleanupReport = $organizationModule->rollback()
                        ->generateManualCleanupReport($e);

                    echo "\n🧹 Manual cleanup required:\n";
                    foreach ($cleanupReport['cleanup_required'] as $cleanup) {
                        echo "- {$cleanup['resource_type']}: {$cleanup['resource_id']}\n";
                        echo "  Endpoint: {$cleanup['cleanup_endpoint']}\n";
                    }
                }
            }
        }
    }
}

/**
 * Example 4: Partial Setup Completion
 */
function example4_partialSetupCompletion($organizationModule)
{
    echo "\n=== Example 4: Partial Setup Completion ===\n";

    $organizationData = [
        'name' => 'Partial Company',
        'subdomain' => 'partial-company',
        'admin_name' => 'Alice Johnson',
        'admin_email' => 'alice@partialcompany.com',
        'domain' => 'partialcompany.com' // This might fail
    ];

    try {
        $result = $organizationModule->setupOrganization($organizationData);
        echo "✅ Complete setup successful!\n";

    } catch (OrganizationSetupException $e) {
        if ($e->getSetupStep() === 'domain_configuration') {
            echo "⚠️ Domain configuration failed, but core setup completed\n";
            echo "Organization can be used without custom domain\n";

            // Try to get partial result from completed steps
            $completedSteps = $e->getCompletedSteps();
            $createdResources = $e->getCreatedResources();

            if (in_array('api_keys_generated', $completedSteps)) {
                echo "✅ Core components available:\n";
                echo "- Organization: " . ($createdResources['organization'] ?? 'N/A') . "\n";
                echo "- Tenant: " . ($createdResources['tenant'] ?? 'N/A') . "\n";
                echo "- Admin: " . ($createdResources['admin'] ?? 'N/A') . "\n";
                echo "- API Keys: ✅\n";
                echo "- Domain: ❌ (can be configured later)\n";
            }
        } else {
            echo "❌ Setup failed at critical step: " . $e->getSetupStep() . "\n";
        }
    }
}

/**
 * Example 5: Monitoring and Statistics
 */
function example5_monitoringAndStatistics($organizationModule)
{
    echo "\n=== Example 5: Monitoring and Statistics ===\n";

    // Get rollback service statistics
    $rollbackStats = $organizationModule->rollback()->getRollbackStats();
    echo "📊 Rollback Statistics:\n";
    echo "- Total rollbacks: " . $rollbackStats['total_rollbacks'] . "\n";
    echo "- Successful procedures: " . $rollbackStats['successful_procedures'] . "\n";
    echo "- Failed procedures: " . $rollbackStats['failed_procedures'] . "\n";

    // Get retry service statistics
    $retryStats = $organizationModule->retry()->getRetryStats();
    echo "\n📊 Retry Statistics:\n";
    echo "- Total attempts: " . $retryStats['total_attempts'] . "\n";
    echo "- Success rate: " . number_format($retryStats['success_rate'], 2) . "%\n";
    echo "- Max retry attempts: " . $retryStats['max_retry_attempts'] . "\n";

    // Configure retry parameters for high-load scenarios
    $organizationModule->retry()->configureRetryParams([
        'max_retry_attempts' => 3,
        'base_delay_seconds' => 2,
        'max_delay_seconds' => 120,
        'backoff_multiplier' => 2.0,
        'jitter_factor' => 0.1
    ]);

    echo "\n⚙️ Retry parameters configured for production environment\n";
}

/**
 * Example 6: Idempotency Key Management
 */
function example6_idempotencyKeyManagement($organizationModule)
{
    echo "\n=== Example 6: Idempotency Key Management ===\n";

    $organizationData = [
        'name' => 'Idempotent Company',
        'subdomain' => 'idempotent-company',
        'admin_name' => 'Charlie Brown',
        'admin_email' => 'charlie@idempotentcompany.com'
    ];

    // Generate consistent idempotency key
    $idempotencyKey = 'idempotent_setup_' . hash('sha256', json_encode($organizationData));

    echo "🔑 Using idempotency key: " . substr($idempotencyKey, 0, 20) . "...\n";

    // First attempt
    echo "\n--- First Attempt ---\n";
    try {
        $result1 = $organizationModule->setupOrganization($organizationData, $idempotencyKey);
        echo "✅ First attempt successful\n";
        echo "Organization ID: " . $result1['organization']['id'] . "\n";

        // Second attempt with same key (should return existing result)
        echo "\n--- Second Attempt (Same Key) ---\n";
        $result2 = $organizationModule->setupOrganization($organizationData, $idempotencyKey);

        if ($result1['organization']['id'] === $result2['organization']['id']) {
            echo "✅ Idempotency working - returned existing result\n";
        } else {
            echo "❌ Idempotency failed - created new organization\n";
        }

    } catch (OrganizationSetupException $e) {
        echo "❌ Setup failed: " . $e->getMessage() . "\n";

        // Retry with same key after fixing issues
        echo "\n--- Retry After Fix ---\n";
        echo "🔄 Safe to retry with same idempotency key\n";
    }
}

/**
 * Example 7: Network Failure Simulation and Recovery
 */
function example7_networkFailureRecovery($organizationModule)
{
    echo "\n=== Example 7: Network Failure Recovery ===\n";

    $organizationData = [
        'name' => 'Network Test Company',
        'subdomain' => 'network-test',
        'admin_name' => 'Diana Prince',
        'admin_email' => 'diana@networktest.com'
    ];

    // Simulate setup during network issues
    try {
        $result = $organizationModule->setupOrganization(
            $organizationData,
            'network_test_' . time(),
            true,  // Enable rollback
            true   // Enable retry with exponential backoff
        );

        echo "✅ Setup completed despite network challenges\n";

    } catch (OrganizationSetupException $e) {
        if ($e->isNetworkFailure()) {
            echo "🌐 Network failure detected\n";
            echo "Suggested retry delay: " . $e->getRetryDelay() . " seconds\n";
            echo "Max retries: " . $e->getMaxRetries() . "\n";

            // Wait and retry manually
            echo "⏳ Waiting for network recovery...\n";
            sleep(5); // Simulate waiting

            try {
                $retryResult = $organizationModule->setupOrganization(
                    $organizationData,
                    $e->getRollbackData()['idempotency_key'] ?? null
                );
                echo "✅ Retry successful after network recovery\n";
            } catch (\Exception $retryException) {
                echo "❌ Retry also failed: " . $retryException->getMessage() . "\n";
            }
        }
    }
}

// Run examples
if (php_sapi_name() === 'cli') {
    echo "🚀 Organization Setup Rollback and Recovery Examples\n";
    echo "================================================\n";

    try {
        example1_basicSetupWithRollback($organizationModule);
        example2_setupWithConflictResolution($organizationModule);
        example3_manualRetryWithDifferentParams($organizationModule);
        example4_partialSetupCompletion($organizationModule);
        example5_monitoringAndStatistics($organizationModule);
        example6_idempotencyKeyManagement($organizationModule);
        example7_networkFailureRecovery($organizationModule);

        echo "\n✅ All examples completed!\n";
        echo "\n📚 For more information, see:\n";
        echo "- docs/organization/setup-rollback-recovery-guide.md\n";
        echo "- API documentation at https://docs.clubify.com\n";

    } catch (\Exception $e) {
        echo "\n💥 Fatal error running examples: " . $e->getMessage() . "\n";
        echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    }
}