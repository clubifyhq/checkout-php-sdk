<?php

/**
 * CLUBIFY CHECKOUT SDK - CART MODULE EXAMPLES
 *
 * Comprehensive examples demonstrating all Cart Module features:
 * - Basic cart operations (CRUD)
 * - Item management
 * - Navigation flows
 * - Promotions and discounts
 * - One-click checkout
 * - Advanced features
 * - Security and validation
 * - Performance optimization
 * - Error handling
 * - Best practices
 *
 * @version 1.0.0
 * @author Clubify Development Team
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Security\CsrfProtection;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

// ===========================================
// SETUP AND CONFIGURATION
// ===========================================

echo "=== CLUBIFY CHECKOUT SDK - CART MODULE EXAMPLES ===\n\n";

try {
    // Initialize SDK with configuration
    $config = new Configuration([
        'api_key' => $_ENV['CLUBIFY_API_KEY'] ?? 'your-api-key-here',
        'api_secret' => $_ENV['CLUBIFY_API_SECRET'] ?? 'your-api-secret-here',
        'environment' => $_ENV['CLUBIFY_ENV'] ?? 'sandbox', // sandbox or production
        'api_base_url' => $_ENV['CLUBIFY_API_URL'] ?? 'https://api.clubify.me',
        'timeout' => 30,
        'retry_attempts' => 3,
        'cache' => [
            'driver' => 'file',
            'ttl' => 1800, // 30 minutes
            'path' => sys_get_temp_dir() . '/clubify_cache'
        ],
        'security' => [
            'csrf_protection' => true,
            'rate_limiting' => true,
            'input_validation' => true
        ],
        'logging' => [
            'level' => 'info',
            'file' => sys_get_temp_dir() . '/clubify_cart_examples.log'
        ]
    ]);

    $logger = new Logger($config->get('logging', []));
    $sdk = new ClubifyCheckoutSDK($config, $logger);

    // Get Cart Module
    $cartModule = $sdk->cart();

    echo "✓ SDK initialized successfully\n";
    echo "✓ Cart module loaded\n\n";

} catch (Exception $e) {
    echo "✗ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ===========================================
// EXAMPLE 1: BASIC CART OPERATIONS
// ===========================================

echo "=== EXAMPLE 1: BASIC CART OPERATIONS ===\n";

try {
    // Create a new cart
    echo "Creating new cart...\n";
    $sessionId = 'session_' . uniqid();

    $cartData = [
        'customer_id' => 'customer_123',
        'organization_id' => 'org_456',
        'type' => 'standard',
        'currency' => 'BRL',
        'metadata' => [
            'source' => 'web',
            'campaign' => 'summer_sale_2024'
        ]
    ];

    $cart = $cartModule->create($sessionId, $cartData);
    echo "✓ Cart created with ID: {$cart['id']}\n";

    // Find cart by ID
    echo "Finding cart by ID...\n";
    $foundCart = $cartModule->find($cart['id']);
    echo "✓ Cart found: {$foundCart['id']}\n";

    // Find cart by session
    echo "Finding cart by session...\n";
    $sessionCart = $cartModule->findBySession($sessionId);
    echo "✓ Cart found by session: {$sessionCart['id']}\n";

    // Update cart
    echo "Updating cart...\n";
    $updateData = [
        'customer_notes' => 'Please handle with care',
        'metadata' => array_merge($cart['metadata'] ?? [], [
            'updated_at' => date('Y-m-d H:i:s'),
            'notes_added' => true
        ])
    ];

    $updatedCart = $cartModule->cart()->update($cart['id'], $updateData);
    echo "✓ Cart updated successfully\n";

    echo "Example 1 completed successfully!\n\n";

} catch (ValidationException $e) {
    echo "✗ Validation error: " . $e->getMessage() . "\n\n";
} catch (HttpException $e) {
    echo "✗ API error: " . $e->getMessage() . "\n\n";
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 2: COMPREHENSIVE ITEM MANAGEMENT
// ===========================================

echo "=== EXAMPLE 2: COMPREHENSIVE ITEM MANAGEMENT ===\n";

try {
    // Add single item
    echo "Adding single item to cart...\n";
    $itemData = [
        'product_id' => 'prod_123',
        'name' => 'Premium Course - Web Development',
        'price' => 299.99,
        'quantity' => 1,
        'type' => 'course',
        'metadata' => [
            'duration' => '40 hours',
            'level' => 'intermediate',
            'category' => 'technology'
        ],
        'custom_fields' => [
            'student_level' => 'intermediate',
            'preferred_schedule' => 'evening'
        ]
    ];

    $addedItem = $cartModule->addItem($cart['id'], $itemData);
    echo "✓ Item added: {$addedItem['name']}\n";

    // Add multiple items in batch
    echo "Adding multiple items...\n";
    $multipleItems = [
        [
            'product_id' => 'prod_124',
            'name' => 'E-book - Advanced PHP',
            'price' => 49.99,
            'quantity' => 1,
            'type' => 'digital_product'
        ],
        [
            'product_id' => 'prod_125',
            'name' => 'Mentorship Session',
            'price' => 150.00,
            'quantity' => 2,
            'type' => 'service',
            'metadata' => [
                'duration' => '1 hour per session',
                'delivery' => 'online'
            ]
        ]
    ];

    foreach ($multipleItems as $item) {
        $cartModule->addItem($cart['id'], $item);
        echo "✓ Added: {$item['name']}\n";
    }

    // Update item quantity
    echo "Updating item quantity...\n";
    $itemToUpdate = $addedItem['id'];
    $updateItemData = [
        'quantity' => 2,
        'notes' => 'Quantity increased due to group discount'
    ];

    $updatedItem = $cartModule->updateItem($cart['id'], $itemToUpdate, $updateItemData);
    echo "✓ Item quantity updated to: {$updatedItem['quantity']}\n";

    // Get cart with items
    $cartWithItems = $cartModule->find($cart['id']);
    echo "✓ Cart now has " . count($cartWithItems['items']) . " items\n";

    // Remove specific item
    echo "Removing one item...\n";
    $itemToRemove = $cartWithItems['items'][1]['id'] ?? null;
    if ($itemToRemove) {
        $cartModule->removeItem($cart['id'], $itemToRemove);
        echo "✓ Item removed successfully\n";
    }

    echo "Example 2 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Item management error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 3: ADVANCED NAVIGATION FLOWS
// ===========================================

echo "=== EXAMPLE 3: ADVANCED NAVIGATION FLOWS ===\n";

try {
    // Start flow navigation
    echo "Starting flow navigation...\n";
    $offerId = 'offer_premium_course';
    $flowContext = [
        'user_type' => 'returning_customer',
        'referral_source' => 'email_campaign',
        'utm_campaign' => 'summer_2024',
        'device_type' => 'desktop',
        'geo_location' => 'BR'
    ];

    $flowNavigation = $cartModule->startFlowNavigation($offerId, $flowContext);
    echo "✓ Flow navigation started: {$flowNavigation['navigation_id']}\n";
    echo "✓ Current step: {$flowNavigation['current_step']}\n";

    // Continue flow with step data
    echo "Continuing flow navigation...\n";
    $stepData = [
        'step' => 'product_selection',
        'selections' => [
            'product_id' => 'prod_123',
            'variant' => 'premium',
            'add_ons' => ['support', 'certificate']
        ],
        'user_input' => [
            'experience_level' => 'intermediate',
            'learning_goals' => ['career_change', 'skill_upgrade']
        ]
    ];

    $nextStep = $cartModule->continueFlowNavigation(
        $flowNavigation['navigation_id'],
        $stepData
    );
    echo "✓ Flow continued to step: {$nextStep['current_step']}\n";

    // Complete navigation with final selections
    echo "Completing flow navigation...\n";
    $finalStepData = [
        'step' => 'confirmation',
        'final_selections' => true,
        'payment_method_preference' => 'credit_card',
        'marketing_consent' => true
    ];

    $completedFlow = $cartModule->continueFlowNavigation(
        $flowNavigation['navigation_id'],
        $finalStepData
    );
    echo "✓ Flow completed successfully\n";

    // Navigation analytics
    $navigationService = $cartModule->navigation();
    $analytics = $navigationService->getAnalytics($flowNavigation['navigation_id']);
    echo "✓ Navigation took {$analytics['total_time']}s with {$analytics['step_count']} steps\n";

    echo "Example 3 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Navigation flow error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 4: PROMOTIONS AND DISCOUNTS
// ===========================================

echo "=== EXAMPLE 4: PROMOTIONS AND DISCOUNTS ===\n";

try {
    // Apply percentage discount
    echo "Applying percentage discount...\n";
    $percentagePromo = 'SUMMER20'; // 20% off

    $promoResult = $cartModule->applyPromotion($cart['id'], $percentagePromo);
    echo "✓ Promotion applied: {$promoResult['promotion']['code']}\n";
    echo "✓ Discount amount: {$promoResult['promotion']['discount_amount']}\n";

    // Try to apply fixed amount discount
    echo "Trying to apply fixed amount discount...\n";
    try {
        $fixedPromo = 'SAVE50'; // $50 off
        $fixedResult = $cartModule->applyPromotion($cart['id'], $fixedPromo);
        echo "✓ Fixed discount applied: {$fixedResult['promotion']['discount_amount']}\n";
    } catch (ValidationException $e) {
        echo "! Only one promotion allowed: {$e->getMessage()}\n";
    }

    // Get promotion details
    $promotionService = $cartModule->promotion();
    $activePromotions = $promotionService->getActive($cart['id']);
    echo "✓ Active promotions: " . count($activePromotions) . "\n";

    // Validate promotion eligibility
    echo "Validating promotion eligibility...\n";
    $eligibilityCheck = $promotionService->checkEligibility($cart['id'], 'VIP25');
    if ($eligibilityCheck['eligible']) {
        echo "✓ Cart is eligible for VIP25 promotion\n";
    } else {
        echo "! Cart not eligible: {$eligibilityCheck['reason']}\n";
    }

    // Remove promotion
    echo "Removing current promotion...\n";
    $removedPromo = $cartModule->removePromotion($cart['id']);
    echo "✓ Promotion removed successfully\n";

    // Apply bundle promotion
    echo "Applying bundle promotion...\n";
    $bundlePromo = 'BUNDLE2024';
    try {
        $bundleResult = $cartModule->applyPromotion($cart['id'], $bundlePromo);
        echo "✓ Bundle promotion applied with conditions\n";
    } catch (ValidationException $e) {
        echo "! Bundle requirements not met: {$e->getMessage()}\n";
    }

    echo "Example 4 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Promotion error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 5: ONE-CLICK CHECKOUT
// ===========================================

echo "=== EXAMPLE 5: ONE-CLICK CHECKOUT ===\n";

try {
    // Prepare payment data for one-click
    echo "Preparing one-click checkout...\n";
    $paymentData = [
        'payment_method' => 'credit_card',
        'card_token' => 'card_token_12345', // From tokenization
        'billing_address' => [
            'street' => 'Rua das Flores, 123',
            'city' => 'São Paulo',
            'state' => 'SP',
            'zip_code' => '01234-567',
            'country' => 'BR'
        ],
        'customer_data' => [
            'name' => 'João Silva',
            'email' => 'joao.silva@email.com',
            'document' => '123.456.789-00',
            'phone' => '+55 11 99999-9999'
        ],
        'processing_options' => [
            'capture_immediately' => true,
            'send_confirmation_email' => true,
            'create_customer_account' => false
        ]
    ];

    // Validate one-click requirements
    $oneClickService = $cartModule->oneClick();
    $validation = $oneClickService->validateRequirements($cart['id'], $paymentData);

    if ($validation['valid']) {
        echo "✓ One-click requirements validated\n";

        // Process one-click checkout
        echo "Processing one-click checkout...\n";
        $checkoutResult = $cartModule->processOneClick($cart['id'], $paymentData);

        echo "✓ One-click checkout successful!\n";
        echo "✓ Order ID: {$checkoutResult['order_id']}\n";
        echo "✓ Payment status: {$checkoutResult['payment_status']}\n";
        echo "✓ Total amount: {$checkoutResult['total_amount']}\n";

    } else {
        echo "! One-click validation failed:\n";
        foreach ($validation['errors'] as $error) {
            echo "  - {$error}\n";
        }

        // Simulate checkout process for demo
        echo "Simulating checkout process...\n";
        $simulationResult = $oneClickService->simulate($cart['id'], $paymentData);
        echo "✓ Checkout simulation completed\n";
        echo "✓ Estimated total: {$simulationResult['estimated_total']}\n";
    }

    echo "Example 5 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ One-click checkout error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 6: ADVANCED FEATURES
// ===========================================

echo "=== EXAMPLE 6: ADVANCED FEATURES ===\n";

try {
    // Cart abandonment handling
    echo "Setting up cart abandonment tracking...\n";
    $abandonmentData = [
        'reminder_emails' => true,
        'reminder_schedule' => [30, 60, 180], // minutes
        'discount_progression' => [5, 10, 15], // percentage
        'maximum_reminders' => 3
    ];

    $cartService = $cartModule->cart();
    $abandonmentSetup = $cartService->setupAbandonment($cart['id'], $abandonmentData);
    echo "✓ Abandonment tracking configured\n";

    // Cart sharing functionality
    echo "Generating cart share link...\n";
    $shareData = [
        'expires_in' => 7200, // 2 hours
        'allow_modifications' => false,
        'require_authentication' => false,
        'share_type' => 'public_link'
    ];

    $shareLink = $cartService->generateShareLink($cart['id'], $shareData);
    echo "✓ Share link generated: {$shareLink['url']}\n";

    // Cart export functionality
    echo "Exporting cart data...\n";
    $exportOptions = [
        'format' => 'json',
        'include_metadata' => true,
        'include_analytics' => true,
        'anonymize_customer_data' => false
    ];

    $exportData = $cartService->export($cart['id'], $exportOptions);
    echo "✓ Cart exported successfully ({$exportData['size']} bytes)\n";

    // Cart comparison
    echo "Comparing with previous cart...\n";
    $comparisonCart = $cart['id']; // In real scenario, this would be different
    $comparison = $cartService->compare($cart['id'], $comparisonCart);
    echo "✓ Cart comparison completed\n";

    // Performance metrics
    echo "Getting performance metrics...\n";
    $metrics = $cartService->getPerformanceMetrics($cart['id']);
    echo "✓ Load time: {$metrics['load_time']}ms\n";
    echo "✓ Memory usage: {$metrics['memory_usage']}MB\n";

    echo "Example 6 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Advanced features error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 7: BATCH OPERATIONS
// ===========================================

echo "=== EXAMPLE 7: BATCH OPERATIONS ===\n";

try {
    // Create multiple carts in batch
    echo "Creating multiple carts in batch...\n";
    $cartService = $cartModule->cart();

    $batchCartData = [
        [
            'session_id' => 'batch_session_1',
            'customer_id' => 'customer_001',
            'type' => 'standard'
        ],
        [
            'session_id' => 'batch_session_2',
            'customer_id' => 'customer_002',
            'type' => 'premium'
        ],
        [
            'session_id' => 'batch_session_3',
            'customer_id' => 'customer_003',
            'type' => 'enterprise'
        ]
    ];

    $batchResults = $cartService->createBatch($batchCartData);
    echo "✓ Created {$batchResults['created_count']} carts in batch\n";

    // Batch item operations
    echo "Adding items to multiple carts...\n";
    $batchItemOperations = [];
    foreach ($batchResults['carts'] as $batchCart) {
        $batchItemOperations[] = [
            'cart_id' => $batchCart['id'],
            'operation' => 'add',
            'item_data' => [
                'product_id' => 'prod_batch_' . rand(100, 999),
                'name' => 'Batch Product',
                'price' => 99.99,
                'quantity' => 1
            ]
        ];
    }

    $itemService = $cartModule->item();
    $batchItemResults = $itemService->processBatch($batchItemOperations);
    echo "✓ Processed {$batchItemResults['processed_count']} item operations\n";

    // Batch promotion application
    echo "Applying promotions to multiple carts...\n";
    $promotionService = $cartModule->promotion();
    $cartIds = array_column($batchResults['carts'], 'id');

    $batchPromoResult = $promotionService->applyToBatch($cartIds, 'BATCH20');
    echo "✓ Applied promotions to {$batchPromoResult['success_count']} carts\n";

    // Cleanup batch carts
    echo "Cleaning up batch carts...\n";
    $cleanupResults = $cartService->deleteBatch($cartIds);
    echo "✓ Cleaned up {$cleanupResults['deleted_count']} carts\n";

    echo "Example 7 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Batch operations error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 8: SECURITY AND VALIDATION
// ===========================================

echo "=== EXAMPLE 8: SECURITY AND VALIDATION ===\n";

try {
    // CSRF Protection
    echo "Setting up CSRF protection...\n";
    $csrfProtection = new CsrfProtection();
    $csrfToken = $csrfProtection->generateToken();
    echo "✓ CSRF token generated: " . substr($csrfToken, 0, 10) . "...\n";

    // Input validation
    echo "Validating cart data input...\n";
    $unsafeData = [
        'customer_notes' => '<script>alert("xss")</script>Safe notes',
        'metadata' => [
            'user_input' => 'DROP TABLE carts; --',
            'safe_field' => 'legitimate data'
        ]
    ];

    $cartService = $cartModule->cart();
    $sanitizedData = $cartService->sanitizeInput($unsafeData);
    echo "✓ Input sanitized and validated\n";

    // Rate limiting check
    echo "Checking rate limits...\n";
    $rateLimitStatus = $cartService->checkRateLimit($cart['id']);
    if ($rateLimitStatus['within_limits']) {
        echo "✓ Within rate limits ({$rateLimitStatus['requests_remaining']} remaining)\n";
    } else {
        echo "! Rate limit exceeded, wait {$rateLimitStatus['reset_in']}s\n";
    }

    // Permission validation
    echo "Validating permissions...\n";
    $permissions = $cartService->validatePermissions($cart['id'], [
        'read' => true,
        'write' => true,
        'delete' => false,
        'admin' => false
    ]);
    echo "✓ Permissions validated\n";

    // Security audit
    echo "Running security audit...\n";
    $auditResults = $cartService->securityAudit($cart['id']);
    echo "✓ Security audit completed with {$auditResults['issues_found']} issues\n";

    echo "Example 8 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Security validation error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 9: PERFORMANCE OPTIMIZATION
// ===========================================

echo "=== EXAMPLE 9: PERFORMANCE OPTIMIZATION ===\n";

try {
    // Enable performance monitoring
    echo "Enabling performance monitoring...\n";
    $cartService = $cartModule->cart();
    $cartService->enablePerformanceMonitoring();

    // Cache optimization
    echo "Optimizing cache usage...\n";
    $cacheStats = $cartService->optimizeCache($cart['id']);
    echo "✓ Cache hit ratio: {$cacheStats['hit_ratio']}%\n";
    echo "✓ Cache size reduced by: {$cacheStats['size_reduction']}%\n";

    // Lazy loading demonstration
    echo "Demonstrating lazy loading...\n";
    $lazyCart = $cartService->findWithLazyLoading($cart['id'], [
        'load_items' => false,     // Don't load items initially
        'load_promotions' => false, // Don't load promotions initially
        'load_analytics' => false   // Don't load analytics initially
    ]);
    echo "✓ Cart loaded with lazy loading\n";

    // Load specific sections on demand
    echo "Loading items on demand...\n";
    $cartWithItems = $cartService->loadSection($cart['id'], 'items');
    echo "✓ Items section loaded ({$cartWithItems['items_count']} items)\n";

    // Database query optimization
    echo "Optimizing database queries...\n";
    $queryStats = $cartService->optimizeQueries($cart['id']);
    echo "✓ Query count reduced from {$queryStats['before']} to {$queryStats['after']}\n";

    // Memory usage optimization
    echo "Optimizing memory usage...\n";
    $memoryBefore = memory_get_usage();
    $cartService->optimizeMemory();
    $memoryAfter = memory_get_usage();
    $memorySaved = $memoryBefore - $memoryAfter;
    echo "✓ Memory saved: " . number_format($memorySaved / 1024, 2) . " KB\n";

    echo "Example 9 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Performance optimization error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 10: ERROR HANDLING AND RECOVERY
// ===========================================

echo "=== EXAMPLE 10: ERROR HANDLING AND RECOVERY ===\n";

try {
    // Graceful error handling
    echo "Demonstrating graceful error handling...\n";

    // Simulate network timeout
    try {
        $cartService = $cartModule->cart();
        $result = $cartService->findWithTimeout($cart['id'], 1); // 1 second timeout
    } catch (HttpException $e) {
        echo "! Network timeout handled gracefully: {$e->getMessage()}\n";

        // Retry with exponential backoff
        echo "Retrying with exponential backoff...\n";
        $retryResult = $cartService->findWithRetry($cart['id'], [
            'max_attempts' => 3,
            'initial_delay' => 1000, // 1 second
            'backoff_multiplier' => 2
        ]);
        echo "✓ Operation succeeded after retry\n";
    }

    // Data consistency check
    echo "Checking data consistency...\n";
    $consistencyCheck = $cartService->checkConsistency($cart['id']);
    if ($consistencyCheck['consistent']) {
        echo "✓ Data is consistent\n";
    } else {
        echo "! Data inconsistency detected, attempting repair...\n";
        $repairResult = $cartService->repairData($cart['id']);
        echo "✓ Data repaired successfully\n";
    }

    // Transaction rollback demonstration
    echo "Demonstrating transaction rollback...\n";
    try {
        $cartService->beginTransaction();

        // Simulate operations that might fail
        $cartService->update($cart['id'], ['invalid_field' => 'value']);
        $cartModule->addItem($cart['id'], ['invalid' => 'item']);

        $cartService->commitTransaction();

    } catch (Exception $e) {
        echo "! Transaction failed, rolling back: {$e->getMessage()}\n";
        $cartService->rollbackTransaction();
        echo "✓ Transaction rolled back successfully\n";
    }

    // Error reporting and logging
    echo "Setting up error reporting...\n";
    $errorHandler = $cartService->setupErrorHandler([
        'log_errors' => true,
        'notify_admin' => false, // Don't send emails in example
        'capture_context' => true
    ]);
    echo "✓ Error handler configured\n";

    echo "Example 10 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Error handling demonstration error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// CLEANUP AND BEST PRACTICES
// ===========================================

echo "=== CLEANUP AND BEST PRACTICES ===\n";

try {
    // Clean up test data
    echo "Cleaning up test data...\n";
    $cartService = $cartModule->cart();

    // Archive cart instead of deleting (best practice)
    $archiveResult = $cartService->archive($cart['id'], [
        'reason' => 'example_completion',
        'retain_analytics' => true,
        'notify_customer' => false
    ]);
    echo "✓ Cart archived instead of deleted\n";

    // Performance summary
    echo "Generating performance summary...\n";
    $performanceSummary = $cartService->getPerformanceSummary();
    echo "✓ Total operations: {$performanceSummary['total_operations']}\n";
    echo "✓ Average response time: {$performanceSummary['avg_response_time']}ms\n";
    echo "✓ Success rate: {$performanceSummary['success_rate']}%\n";

    // Module status check
    $moduleStatus = $cartModule->getStatus();
    echo "✓ Module status: " . ($moduleStatus['available'] ? 'Available' : 'Unavailable') . "\n";

    echo "\n=== BEST PRACTICES SUMMARY ===\n";
    echo "1. Always validate input data before API calls\n";
    echo "2. Use appropriate cache strategies for different operations\n";
    echo "3. Implement proper error handling and retry logic\n";
    echo "4. Monitor performance metrics and optimize accordingly\n";
    echo "5. Use batch operations for multiple similar operations\n";
    echo "6. Implement CSRF protection for web applications\n";
    echo "7. Archive data instead of deleting when possible\n";
    echo "8. Use lazy loading for large datasets\n";
    echo "9. Implement proper logging for debugging and auditing\n";
    echo "10. Always clean up resources and handle memory efficiently\n";

    echo "\nAll examples completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Cleanup error: " . $e->getMessage() . "\n";
} finally {
    // Final cleanup
    if (isset($cartModule)) {
        $cartModule->cleanup();
    }
    echo "✓ Final cleanup completed\n";
}

echo "\n=== END OF CART MODULE EXAMPLES ===\n";

/**
 * ADDITIONAL INTEGRATION EXAMPLES
 *
 * For real-world applications, consider these integration patterns:
 *
 * 1. E-commerce Integration:
 *    - Integrate with product catalogs
 *    - Implement inventory checks
 *    - Handle tax calculations
 *    - Process payment methods
 *
 * 2. Subscription Management:
 *    - Recurring billing setup
 *    - Plan upgrades/downgrades
 *    - Proration calculations
 *    - Cancellation handling
 *
 * 3. Multi-tenant Applications:
 *    - Organization-level isolation
 *    - Shared vs dedicated resources
 *    - Custom pricing rules
 *    - Brand-specific configurations
 *
 * 4. Analytics Integration:
 *    - Cart abandonment tracking
 *    - Conversion rate optimization
 *    - A/B testing framework
 *    - Customer behavior analysis
 *
 * 5. External Service Integration:
 *    - CRM systems
 *    - Email marketing platforms
 *    - Inventory management
 *    - Shipping providers
 */