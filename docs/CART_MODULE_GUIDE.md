# Cart Module Guide

## Table of Contents

1. [Overview](#overview)
2. [Installation and Setup](#installation-and-setup)
3. [Basic Usage](#basic-usage)
4. [API Reference](#api-reference)
5. [Advanced Features](#advanced-features)
6. [Performance Optimization](#performance-optimization)
7. [Security Best Practices](#security-best-practices)
8. [Error Handling](#error-handling)
9. [Integration Examples](#integration-examples)
10. [Migration Guide](#migration-guide)
11. [Troubleshooting](#troubleshooting)

## Overview

The Clubify Checkout SDK Cart Module provides comprehensive cart management functionality for e-commerce and subscription-based applications. It follows SOLID principles and provides a clean, intuitive API for cart operations.

### Key Features

- **Complete CRUD Operations**: Create, read, update, and delete carts
- **Advanced Item Management**: Add, remove, update items with metadata
- **Flow Navigation**: Multi-step checkout flows with conditional logic
- **Promotion System**: Coupons, discounts, and promotional campaigns
- **One-Click Checkout**: Streamlined payment processing
- **Performance Optimization**: Intelligent caching and batch operations
- **Security**: CSRF protection, input validation, and rate limiting
- **Analytics**: Comprehensive tracking and reporting

### Architecture

The Cart Module follows a modular architecture with clear separation of concerns:

```
CartModule
├── Services/
│   ├── CartService          # Core cart operations
│   ├── ItemService          # Item management
│   ├── NavigationService    # Flow navigation
│   ├── PromotionService     # Promotions and discounts
│   └── OneClickService      # One-click checkout
├── Repositories/
│   └── ApiCartRepository    # API communication
├── DTOs/
│   ├── CartData            # Cart data transfer object
│   ├── ItemData            # Item data transfer object
│   └── NavigationData      # Navigation data transfer object
└── Factories/
    └── CartServiceFactory   # Service creation and dependency injection
```

## Installation and Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Clubify API credentials

### Installation

```bash
composer require clubify/checkout-sdk
```

### Configuration

```php
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;

$config = new Configuration([
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'environment' => 'sandbox', // or 'production'
    'cache' => [
        'driver' => 'file',
        'ttl' => 1800,
        'path' => '/tmp/clubify_cache'
    ],
    'logging' => [
        'level' => 'info',
        'file' => '/var/log/clubify.log'
    ]
]);

$sdk = new ClubifyCheckoutSDK($config);
$cartModule = $sdk->cart();
```

## Basic Usage

### Creating a Cart

```php
// Basic cart creation
$sessionId = 'session_' . uniqid();
$cart = $cartModule->create($sessionId, [
    'customer_id' => 'customer_123',
    'currency' => 'BRL',
    'type' => 'standard'
]);

echo "Cart created: {$cart['id']}";
```

### Finding Carts

```php
// Find by cart ID
$cart = $cartModule->find('cart_id_123');

// Find by session ID
$cart = $cartModule->findBySession('session_abc');

// Check if cart exists
if ($cart) {
    echo "Cart found: {$cart['id']}";
} else {
    echo "Cart not found";
}
```

### Managing Items

```php
// Add item to cart
$itemData = [
    'product_id' => 'prod_123',
    'name' => 'Premium Course',
    'price' => 299.99,
    'quantity' => 1,
    'metadata' => [
        'duration' => '40 hours',
        'level' => 'intermediate'
    ]
];

$item = $cartModule->addItem($cart['id'], $itemData);

// Update item
$cartModule->updateItem($cart['id'], $item['id'], [
    'quantity' => 2,
    'notes' => 'Quantity updated'
]);

// Remove item
$cartModule->removeItem($cart['id'], $item['id']);
```

### Applying Promotions

```php
// Apply promotion code
$result = $cartModule->applyPromotion($cart['id'], 'SUMMER20');
echo "Discount applied: {$result['promotion']['discount_amount']}";

// Remove promotion
$cartModule->removePromotion($cart['id']);
```

## API Reference

### CartModule Methods

#### Core Operations

```php
// Create cart
public function create(string $sessionId, array $data = []): array

// Find cart by ID
public function find(string $id): ?array

// Find cart by session
public function findBySession(string $sessionId): ?array

// Update cart
public function update(string $id, array $data): array

// Delete cart
public function delete(string $id): bool
```

#### Item Management

```php
// Add item
public function addItem(string $cartId, array $itemData): array

// Update item
public function updateItem(string $cartId, string $itemId, array $updates): array

// Remove item
public function removeItem(string $cartId, string $itemId): array

// Get cart items
public function getItems(string $cartId): array
```

#### Promotion Operations

```php
// Apply promotion
public function applyPromotion(string $cartId, string $promotionCode): array

// Remove promotion
public function removePromotion(string $cartId): array

// Validate promotion
public function validatePromotion(string $cartId, string $promotionCode): array
```

#### Flow Navigation

```php
// Start flow navigation
public function startFlowNavigation(string $offerId, array $context = []): array

// Continue flow
public function continueFlowNavigation(string $navigationId, array $stepData): array

// Complete flow
public function completeFlowNavigation(string $navigationId): array
```

#### One-Click Checkout

```php
// Process one-click checkout
public function processOneClick(string $cartId, array $paymentData): array

// Validate one-click requirements
public function validateOneClickRequirements(string $cartId, array $paymentData): array
```

### Service Classes

#### CartService

The core service for cart operations:

```php
use Clubify\Checkout\Modules\Cart\Services\CartService;

$cartService = $cartModule->cart();

// Advanced cart operations
$cart = $cartService->createAdvanced($sessionId, [
    'customer_id' => 'cust_123',
    'organization_id' => 'org_456',
    'type' => 'subscription',
    'configuration' => [
        'auto_calculate_taxes' => true,
        'allow_backorders' => false,
        'require_shipping' => true
    ]
]);

// Calculate totals
$totals = $cartService->calculateTotals($cartId);

// Batch operations
$results = $cartService->createBatch([
    ['session_id' => 'session_1', 'customer_id' => 'cust_1'],
    ['session_id' => 'session_2', 'customer_id' => 'cust_2']
]);
```

#### ItemService

Specialized service for item management:

```php
$itemService = $cartModule->item();

// Advanced item operations
$item = $itemService->addAdvanced($cartId, [
    'product_id' => 'prod_123',
    'variant_id' => 'variant_456',
    'customizations' => [
        'color' => 'blue',
        'size' => 'large'
    ],
    'subscription_options' => [
        'frequency' => 'monthly',
        'duration' => 12
    ]
]);

// Bulk item operations
$results = $itemService->addBulk($cartId, [
    ['product_id' => 'prod_1', 'quantity' => 1],
    ['product_id' => 'prod_2', 'quantity' => 2]
]);
```

#### NavigationService

Service for handling complex checkout flows:

```php
$navigationService = $cartModule->navigation();

// Start complex flow
$flow = $navigationService->startAdvancedFlow($offerId, [
    'user_segment' => 'premium',
    'referral_source' => 'email',
    'device_type' => 'mobile'
]);

// Configure flow steps
$steps = $navigationService->configureSteps($flowId, [
    ['type' => 'product_selection', 'required' => true],
    ['type' => 'customization', 'required' => false],
    ['type' => 'addon_selection', 'required' => false],
    ['type' => 'confirmation', 'required' => true]
]);
```

#### PromotionService

Service for managing promotions and discounts:

```php
$promotionService = $cartModule->promotion();

// Advanced promotion application
$result = $promotionService->applyAdvanced($cartId, [
    'code' => 'BUNDLE20',
    'validation_rules' => [
        'minimum_amount' => 100.00,
        'customer_segments' => ['premium', 'vip'],
        'product_categories' => ['courses', 'books']
    ]
]);

// Stack multiple promotions
$results = $promotionService->stackPromotions($cartId, [
    'FIRST10',    // 10% off first purchase
    'STUDENT5'    // Additional 5% for students
]);
```

#### OneClickService

Service for streamlined checkout:

```php
$oneClickService = $cartModule->oneClick();

// Configure one-click settings
$oneClickService->configure($cartId, [
    'payment_methods' => ['credit_card', 'pix'],
    'shipping_options' => ['standard', 'express'],
    'auto_apply_best_promotion' => true,
    'skip_confirmation' => false
]);

// Process with advanced options
$result = $oneClickService->processAdvanced($cartId, [
    'payment_data' => $paymentData,
    'shipping_preference' => 'fastest',
    'notification_preferences' => [
        'email' => true,
        'sms' => false,
        'push' => true
    ]
]);
```

## Advanced Features

### Cart Abandonment

```php
// Setup abandonment tracking
$cartService->setupAbandonment($cartId, [
    'reminder_emails' => true,
    'reminder_schedule' => [30, 60, 180], // minutes
    'discount_progression' => [5, 10, 15], // percentage
    'maximum_reminders' => 3
]);

// Get abandonment analytics
$analytics = $cartService->getAbandonmentAnalytics([
    'period' => 'last_30_days',
    'segment_by' => 'customer_type'
]);
```

### Cart Sharing

```php
// Generate shareable cart link
$shareLink = $cartService->generateShareLink($cartId, [
    'expires_in' => 7200, // 2 hours
    'allow_modifications' => false,
    'require_authentication' => false
]);

// Access shared cart
$sharedCart = $cartService->accessSharedCart($shareToken);
```

### Performance Optimization

```php
// Enable lazy loading
$cart = $cartService->findWithLazyLoading($cartId, [
    'load_items' => false,
    'load_promotions' => false,
    'load_analytics' => false
]);

// Load sections on demand
$cartWithItems = $cartService->loadSection($cartId, 'items');

// Optimize cache usage
$cacheStats = $cartService->optimizeCache($cartId);
```

### Batch Operations

```php
// Batch cart creation
$batchResults = $cartService->createBatch([
    ['session_id' => 'session_1', 'customer_id' => 'cust_1'],
    ['session_id' => 'session_2', 'customer_id' => 'cust_2'],
    ['session_id' => 'session_3', 'customer_id' => 'cust_3']
]);

// Batch item operations
$itemOperations = [
    ['cart_id' => 'cart_1', 'operation' => 'add', 'item_data' => $itemData1],
    ['cart_id' => 'cart_2', 'operation' => 'update', 'item_id' => 'item_1', 'updates' => $updates],
    ['cart_id' => 'cart_3', 'operation' => 'remove', 'item_id' => 'item_2']
];

$batchItemResults = $itemService->processBatch($itemOperations);
```

## Performance Optimization

### Caching Strategies

The Cart Module implements multi-layer caching:

```php
// Configure cache strategies
$cartService->configureCaching([
    'strategies' => [
        'cart_data' => 'redis',      // 30 minutes
        'item_data' => 'memory',     // 15 minutes
        'calculations' => 'file',    // 5 minutes
        'promotions' => 'database'   // 1 hour
    ],
    'invalidation_triggers' => [
        'cart_update',
        'item_change',
        'promotion_apply'
    ]
]);
```

### Query Optimization

```php
// Enable query optimization
$cartService->optimizeQueries($cartId, [
    'enable_eager_loading' => true,
    'cache_query_results' => true,
    'optimize_joins' => true
]);
```

### Memory Management

```php
// Optimize memory usage
$cartService->optimizeMemory([
    'enable_garbage_collection' => true,
    'limit_result_sets' => 100,
    'stream_large_datasets' => true
]);
```

## Security Best Practices

### Input Validation

```php
// Validate and sanitize input
$sanitizedData = $cartService->sanitizeInput([
    'customer_notes' => '<script>alert("xss")</script>Valid notes',
    'metadata' => [
        'user_input' => 'DROP TABLE carts; --',
        'safe_field' => 'legitimate data'
    ]
]);
```

### CSRF Protection

```php
use Clubify\Checkout\Core\Security\CsrfProtection;

$csrf = new CsrfProtection();
$token = $csrf->generateToken();

// Validate token before operations
if ($csrf->validateToken($_POST['csrf_token'])) {
    // Process cart operation
    $result = $cartModule->addItem($cartId, $itemData);
}
```

### Rate Limiting

```php
// Check rate limits
$rateLimit = $cartService->checkRateLimit($cartId, [
    'requests_per_minute' => 60,
    'requests_per_hour' => 1000
]);

if (!$rateLimit['within_limits']) {
    throw new Exception("Rate limit exceeded");
}
```

### Permission Validation

```php
// Validate user permissions
$permissions = $cartService->validatePermissions($cartId, [
    'user_id' => $userId,
    'required_permissions' => ['read', 'write'],
    'check_ownership' => true
]);
```

## Error Handling

### Exception Types

The Cart Module throws specific exceptions for different error conditions:

```php
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\AuthenticationException;
use Clubify\Checkout\Exceptions\RateLimitException;

try {
    $cart = $cartModule->create($sessionId, $data);
} catch (ValidationException $e) {
    // Handle validation errors
    echo "Validation error: " . $e->getMessage();
} catch (HttpException $e) {
    // Handle API errors
    echo "API error: " . $e->getMessage();
} catch (AuthenticationException $e) {
    // Handle authentication errors
    echo "Authentication error: " . $e->getMessage();
} catch (RateLimitException $e) {
    // Handle rate limiting
    echo "Rate limit exceeded: " . $e->getMessage();
}
```

### Retry Logic

```php
// Implement retry with exponential backoff
$result = $cartService->findWithRetry($cartId, [
    'max_attempts' => 3,
    'initial_delay' => 1000, // 1 second
    'backoff_multiplier' => 2,
    'jitter' => true
]);
```

### Data Recovery

```php
// Check data consistency
$consistency = $cartService->checkConsistency($cartId);

if (!$consistency['consistent']) {
    // Attempt automatic repair
    $repairResult = $cartService->repairData($cartId);

    if (!$repairResult['success']) {
        // Manual intervention required
        $cartService->flagForManualReview($cartId);
    }
}
```

## Integration Examples

### E-commerce Integration

```php
// Complete e-commerce workflow
class EcommerceCartIntegration
{
    private $cartModule;
    private $inventoryService;
    private $taxService;

    public function addProductToCart($sessionId, $productId, $quantity)
    {
        // Check inventory
        if (!$this->inventoryService->isAvailable($productId, $quantity)) {
            throw new Exception("Product not available");
        }

        // Get product details
        $product = $this->inventoryService->getProduct($productId);

        // Create or get cart
        $cart = $this->cartModule->findBySession($sessionId)
               ?? $this->cartModule->create($sessionId);

        // Add item
        $item = $this->cartModule->addItem($cart['id'], [
            'product_id' => $productId,
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'sku' => $product['sku']
        ]);

        // Calculate taxes
        $taxes = $this->taxService->calculate($cart['id']);

        // Update cart with taxes
        $this->cartModule->cart()->update($cart['id'], [
            'taxes' => $taxes
        ]);

        return $cart;
    }
}
```

### Subscription Service Integration

```php
// Subscription-based service integration
class SubscriptionCartIntegration
{
    public function createSubscriptionCart($customerId, $planId)
    {
        $sessionId = 'subscription_' . uniqid();

        // Create cart with subscription configuration
        $cart = $this->cartModule->create($sessionId, [
            'customer_id' => $customerId,
            'type' => 'subscription',
            'configuration' => [
                'billing_cycle' => 'monthly',
                'auto_renewal' => true,
                'trial_period' => 14
            ]
        ]);

        // Add subscription plan
        $this->cartModule->addItem($cart['id'], [
            'product_id' => $planId,
            'type' => 'subscription_plan',
            'billing_frequency' => 'monthly',
            'subscription_metadata' => [
                'trial_end_date' => date('Y-m-d', strtotime('+14 days')),
                'next_billing_date' => date('Y-m-d', strtotime('+1 month'))
            ]
        ]);

        return $cart;
    }
}
```

### Multi-tenant Integration

```php
// Multi-tenant application integration
class MultiTenantCartIntegration
{
    public function createTenantCart($tenantId, $sessionId, $customerId)
    {
        // Create cart with tenant isolation
        $cart = $this->cartModule->create($sessionId, [
            'customer_id' => $customerId,
            'organization_id' => $tenantId,
            'tenant_settings' => [
                'custom_fields' => $this->getTenantCustomFields($tenantId),
                'pricing_rules' => $this->getTenantPricingRules($tenantId),
                'branding' => $this->getTenantBranding($tenantId)
            ]
        ]);

        return $cart;
    }

    private function getTenantCustomFields($tenantId)
    {
        // Return tenant-specific custom fields
        return [];
    }
}
```

## Migration Guide

### From Legacy Cart Systems

#### Step 1: Data Mapping

```php
// Map legacy cart data to Clubify format
function migrateLegacyCart($legacyCart)
{
    return [
        'session_id' => $legacyCart['session_id'],
        'customer_id' => $legacyCart['user_id'],
        'currency' => $legacyCart['currency'] ?? 'BRL',
        'items' => array_map(function($item) {
            return [
                'product_id' => $item['product_id'],
                'name' => $item['product_name'],
                'price' => $item['unit_price'],
                'quantity' => $item['qty'],
                'metadata' => [
                    'legacy_id' => $item['id'],
                    'migrated_at' => date('Y-m-d H:i:s')
                ]
            ];
        }, $legacyCart['items'])
    ];
}
```

#### Step 2: Bulk Migration

```php
// Migrate carts in batches
function migrateCartsInBatches($batchSize = 100)
{
    $offset = 0;

    do {
        $legacyCarts = $this->getLegacyCarts($offset, $batchSize);

        $migratedCarts = array_map(function($legacyCart) {
            return $this->migrateLegacyCart($legacyCart);
        }, $legacyCarts);

        $results = $this->cartModule->cart()->createBatch($migratedCarts);

        $this->logMigrationResults($results);

        $offset += $batchSize;

    } while (count($legacyCarts) === $batchSize);
}
```

### From Other SDKs

When migrating from other cart SDKs, consider these compatibility layers:

```php
// Compatibility layer for common cart operations
class CartCompatibilityLayer
{
    private $cartModule;

    // Legacy method mapping
    public function addToCart($productId, $quantity, $sessionId = null)
    {
        $sessionId = $sessionId ?? session_id();

        $cart = $this->cartModule->findBySession($sessionId)
               ?? $this->cartModule->create($sessionId);

        return $this->cartModule->addItem($cart['id'], [
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
    }

    public function removeFromCart($itemId, $sessionId = null)
    {
        $sessionId = $sessionId ?? session_id();
        $cart = $this->cartModule->findBySession($sessionId);

        if ($cart) {
            return $this->cartModule->removeItem($cart['id'], $itemId);
        }

        return false;
    }
}
```

## Troubleshooting

### Common Issues

#### Issue: Cart Not Found

```php
// Problem: Cart returns null
$cart = $cartModule->find($cartId);

// Solution: Check cart existence and session
if (!$cart) {
    // Try finding by session
    $cart = $cartModule->findBySession($sessionId);

    if (!$cart) {
        // Create new cart
        $cart = $cartModule->create($sessionId);
    }
}
```

#### Issue: Items Not Adding

```php
// Problem: Items fail to add to cart
try {
    $item = $cartModule->addItem($cartId, $itemData);
} catch (ValidationException $e) {
    // Check required fields
    $requiredFields = ['product_id', 'name', 'price', 'quantity'];
    $missingFields = array_diff($requiredFields, array_keys($itemData));

    if (!empty($missingFields)) {
        throw new Exception("Missing required fields: " . implode(', ', $missingFields));
    }
}
```

#### Issue: Performance Problems

```php
// Problem: Slow cart operations
// Solution: Enable performance monitoring
$cartService->enablePerformanceMonitoring();

// Check performance metrics
$metrics = $cartService->getPerformanceMetrics($cartId);

if ($metrics['load_time'] > 1000) { // > 1 second
    // Optimize cache
    $cartService->optimizeCache($cartId);

    // Enable lazy loading
    $cart = $cartService->findWithLazyLoading($cartId);
}
```

### Debug Mode

```php
// Enable debug mode for detailed logging
$config = new Configuration([
    'debug' => true,
    'logging' => [
        'level' => 'debug',
        'include_request_response' => true
    ]
]);

// Access debug information
$debugInfo = $cartModule->getDebugInfo();
print_r($debugInfo);
```

### Support Resources

- **Documentation**: https://docs.clubify.me/cart-module
- **API Reference**: https://api.clubify.me/docs
- **Support Forum**: https://community.clubify.me
- **GitHub Issues**: https://github.com/clubify/checkout-sdk-php/issues

---

For more detailed examples and advanced use cases, refer to the example files:
- [`/examples/cart-module-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/cart-module-examples.php)
- [`/examples/integration-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/integration-examples.php)