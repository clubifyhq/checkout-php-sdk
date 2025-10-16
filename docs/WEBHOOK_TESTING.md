# Webhook Testing Guide

## Overview

The Clubify Checkout SDK PHP provides built-in functionality to test webhook delivery from the notification-service to your application. This allows you to verify that your webhook endpoints are correctly configured and can receive events.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Methods Available](#methods-available)
3. [Authentication Methods](#authentication-methods)
4. [Usage Examples](#usage-examples)
5. [Response Format](#response-format)
6. [Event Types](#event-types)
7. [Error Handling](#error-handling)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)

## Quick Start

### Simple Test

```php
use Clubify\Checkout\ClubifyCheckoutSDK;

$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'organization_id' => 'your_org_id',
        'tenant_id' => 'your_tenant_id',
        'api_key' => 'your_api_key'
    ],
    'environment' => 'staging'
]);

$sdk->initialize();

// Test webhook delivery
$result = $sdk->notifications()->testWebhookDelivery(
    eventType: 'order.paid',
    customData: ['orderId' => '123', 'amount' => 99.99]
);

if ($result['success']) {
    echo "Webhook test successful! Response time: {$result['responseTime']} ms\n";
} else {
    echo "Webhook test failed: {$result['error']}\n";
}
```

## Methods Available

The SDK provides two main methods for testing webhook delivery:

### 1. testWebhookDelivery()

Uses JWT authentication (requires authenticated user).

```php
public function testWebhookDelivery(
    string $eventType,
    array $customData = [],
    ?string $webhookUrl = null
): array
```

**Parameters:**
- `$eventType` (string): The event type to test (e.g., 'order.paid', 'payment.approved')
- `$customData` (array): Custom data to include in the webhook payload
- `$webhookUrl` (string|null): Optional webhook URL override (defaults to configured URL)

**Returns:** Array with test results

### 2. testWebhookDeliveryWithApiKey()

Uses API key authentication (no user authentication required).

```php
public function testWebhookDeliveryWithApiKey(
    string $apiKey,
    string $eventType,
    array $customData = [],
    ?string $webhookUrl = null
): array
```

**Parameters:**
- `$apiKey` (string): Public API key for authentication
- `$eventType` (string): The event type to test
- `$customData` (array): Custom data to include in the webhook payload
- `$webhookUrl` (string|null): Optional webhook URL override

**Returns:** Array with test results

## Authentication Methods

### JWT Authentication (Recommended for User Actions)

Uses the authenticated user's JWT token. This is the standard method when testing within an authenticated session.

**Endpoint:** `POST /api/v1/notifications/test-webhook`

**Headers:**
- `Authorization: Bearer {JWT_TOKEN}`
- `x-organization-id: {ORG_ID}`
- `x-tenant-id: {TENANT_ID}`

```php
$result = $sdk->notifications()->testWebhookDelivery(
    'order.paid',
    ['orderId' => '123']
);
```

### API Key Authentication (For Server-to-Server)

Uses organization API key for authentication. Useful for server-to-server testing without user context.

**Endpoint:** `POST /api/v1/public/notifications/webhook/test`

**Headers:**
- `x-api-key: {API_KEY}`
- `x-organization-id: {ORG_ID}`

```php
$result = $sdk->notifications()->testWebhookDeliveryWithApiKey(
    'your_api_key',
    'order.paid',
    ['orderId' => '123']
);
```

## Usage Examples

### Example 1: Basic Test with Default Configuration

```php
// Test using configured webhook URL
$result = $sdk->notifications()->testWebhookDelivery(
    eventType: 'order.paid',
    customData: [
        'orderId' => 'order_12345',
        'amount' => 149.90,
        'currency' => 'BRL',
        'customer' => [
            'name' => 'João Silva',
            'email' => 'joao@example.com'
        ]
    ]
);

print_r($result);
```

### Example 2: Test with Custom Webhook URL

```php
// Override webhook URL for testing
$result = $sdk->notifications()->testWebhookDelivery(
    eventType: 'payment.approved',
    customData: [
        'paymentId' => 'pay_67890',
        'method' => 'credit_card'
    ],
    webhookUrl: 'https://webhook.site/your-unique-id'
);
```

### Example 3: Test Multiple Event Types

```php
$events = [
    'order.created' => ['orderId' => '123', 'status' => 'pending'],
    'order.paid' => ['orderId' => '123', 'amount' => 299.90],
    'order.shipped' => ['orderId' => '123', 'trackingCode' => 'BR123'],
];

foreach ($events as $eventType => $data) {
    $result = $sdk->notifications()->testWebhookDelivery($eventType, $data);

    echo "Testing {$eventType}: ";
    echo $result['success'] ? "✓ SUCCESS" : "✗ FAILED";
    echo " ({$result['responseTime']} ms)\n";

    usleep(200000); // 200ms delay between tests
}
```

### Example 4: Using API Key Authentication

```php
$apiKey = getenv('CLUBIFY_CHECKOUT_ORGANIZATION_API_KEY');

$result = $sdk->notifications()->testWebhookDeliveryWithApiKey(
    apiKey: $apiKey,
    eventType: 'subscription.created',
    customData: [
        'subscriptionId' => 'sub_abc123',
        'planId' => 'premium_monthly',
        'status' => 'active'
    ]
);
```

### Example 5: Performance Testing

```php
$iterations = 10;
$responseTimes = [];

for ($i = 0; $i < $iterations; $i++) {
    $result = $sdk->notifications()->testWebhookDelivery(
        'order.paid',
        ['iteration' => $i]
    );

    if ($result['success']) {
        $responseTimes[] = $result['responseTime'];
    }

    usleep(200000); // Delay between tests
}

$avgTime = array_sum($responseTimes) / count($responseTimes);
echo "Average response time: {$avgTime} ms\n";
```

## Response Format

### Success Response

```php
[
    'success' => true,
    'statusCode' => 200,
    'responseTime' => 145.32,  // milliseconds
    'responseBody' => '{"status":"received"}',
    'webhookUrl' => 'https://app.example.com/webhooks/clubify',
    'eventType' => 'order.paid',
    'testData' => [
        'event' => 'order.paid',
        'id' => 'evt_test_abc123',
        'timestamp' => 1697472000,
        'data' => [
            'test' => true,
            'tenant_id' => 'tenant_123',
            'organization_id' => 'org_456',
            'orderId' => '123',
            'amount' => 99.99
        ]
    ],
    'error' => null,
    'timestamp' => '2025-10-16T12:00:00+00:00'
]
```

### Error Response

```php
[
    'success' => false,
    'statusCode' => 0,
    'responseTime' => 5234.12,
    'responseBody' => null,
    'webhookUrl' => 'https://app.example.com/webhooks/clubify',
    'eventType' => 'order.paid',
    'error' => 'Connection timeout: Could not connect to webhook URL',
    'timestamp' => '2025-10-16T12:00:00+00:00'
]
```

## Event Types

The SDK supports testing all standard Clubify event types:

### Order Events
- `order.created` - New order created
- `order.paid` - Order payment completed
- `order.shipped` - Order shipped to customer
- `order.delivered` - Order delivered
- `order.cancelled` - Order cancelled
- `order.refunded` - Order refunded

### Payment Events
- `payment.approved` - Payment approved
- `payment.declined` - Payment declined
- `payment.refunded` - Payment refunded
- `payment.chargeback` - Payment chargeback

### Subscription Events
- `subscription.created` - New subscription created
- `subscription.updated` - Subscription updated
- `subscription.cancelled` - Subscription cancelled
- `subscription.renewed` - Subscription renewed
- `subscription.expired` - Subscription expired

### Customer Events
- `customer.created` - New customer registered
- `customer.updated` - Customer information updated
- `customer.deleted` - Customer account deleted

### Product Events
- `product.created` - New product created
- `product.updated` - Product updated
- `product.deleted` - Product deleted

## Error Handling

### Common Errors

1. **No webhook configuration found**
   ```php
   // Error: No webhook configuration found for tenant
   ```
   **Solution:** Configure a webhook endpoint in the notification-service first.

2. **Webhook URL not configured**
   ```php
   // Error: No webhook URL configured for event type: order.paid
   ```
   **Solution:** Add the event type to your webhook configuration or provide a custom URL.

3. **Connection timeout**
   ```php
   // Error: Connection timeout: Could not connect to webhook URL
   ```
   **Solution:** Verify the webhook URL is accessible and responding within the timeout period.

4. **Authentication failed**
   ```php
   // Error: Tenant ID is required for webhook testing
   ```
   **Solution:** Ensure your SDK is properly configured with tenant_id and organization_id.

### Error Handling Example

```php
try {
    $result = $sdk->notifications()->testWebhookDelivery(
        'order.paid',
        ['orderId' => '123']
    );

    if (!$result['success']) {
        // Handle test failure
        error_log("Webhook test failed: " . $result['error']);

        // Retry logic
        if (strpos($result['error'], 'timeout') !== false) {
            // Retry with increased timeout
            sleep(1);
            $result = $sdk->notifications()->testWebhookDelivery(
                'order.paid',
                ['orderId' => '123']
            );
        }
    }

} catch (\RuntimeException $e) {
    error_log("Configuration error: " . $e->getMessage());
} catch (\Exception $e) {
    error_log("Unexpected error: " . $e->getMessage());
}
```

## Best Practices

### 1. Test in Staging First

Always test webhooks in a staging environment before production:

```php
$sdk = new ClubifyCheckoutSDK([
    'credentials' => [/* ... */],
    'environment' => 'staging'  // Test in staging first
]);
```

### 2. Use Webhook.site for Debugging

For initial testing and debugging, use webhook.site:

```php
$result = $sdk->notifications()->testWebhookDelivery(
    'order.paid',
    ['orderId' => '123'],
    webhookUrl: 'https://webhook.site/your-unique-id'
);
```

### 3. Monitor Response Times

Track webhook response times to ensure performance:

```php
$result = $sdk->notifications()->testWebhookDelivery(/* ... */);

if ($result['responseTime'] > 5000) {
    // Response time > 5 seconds, investigate webhook endpoint
    error_log("Slow webhook response: {$result['responseTime']} ms");
}
```

### 4. Implement Retry Logic

Handle temporary failures gracefully:

```php
function testWebhookWithRetry($sdk, $eventType, $customData, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $result = $sdk->notifications()->testWebhookDelivery($eventType, $customData);

        if ($result['success']) {
            return $result;
        }

        if ($attempt < $maxRetries) {
            sleep(pow(2, $attempt)); // Exponential backoff
        }
    }

    return $result; // Return last failed attempt
}
```

### 5. Test All Event Types

Ensure your webhook endpoint handles all event types:

```php
$eventTypes = [
    'order.created', 'order.paid', 'order.shipped',
    'payment.approved', 'subscription.created'
];

foreach ($eventTypes as $eventType) {
    $result = $sdk->notifications()->testWebhookDelivery($eventType, []);

    if (!$result['success']) {
        echo "Failed to test {$eventType}: {$result['error']}\n";
    }
}
```

### 6. Validate Webhook Signatures

Always validate webhook signatures in your endpoint:

```php
// In your webhook endpoint
use Clubify\Checkout\Laravel\Middleware\ValidateWebhook;

// Laravel route
Route::post('/webhooks/clubify', [WebhookController::class, 'handle'])
    ->middleware(ValidateWebhook::class);
```

### 7. Log All Webhook Events

Implement comprehensive logging:

```php
$result = $sdk->notifications()->testWebhookDelivery(/* ... */);

error_log(json_encode([
    'event' => 'webhook_test',
    'event_type' => $result['eventType'],
    'success' => $result['success'],
    'response_time' => $result['responseTime'],
    'status_code' => $result['statusCode'],
    'error' => $result['error'],
    'timestamp' => $result['timestamp']
]));
```

## Troubleshooting

### Issue: Webhook configuration not found

**Error Message:**
```
No webhook configuration found for tenant. Please configure a webhook endpoint first.
```

**Solution:**
1. Configure a webhook in the notification-service
2. Verify tenant_id matches your configuration
3. Check that webhook endpoints are configured for the event type

### Issue: Connection timeout

**Error Message:**
```
Connection timeout: Could not connect to webhook URL
```

**Possible Causes:**
- Webhook URL is not accessible from the internet
- Firewall blocking incoming requests
- Webhook endpoint not responding fast enough
- SSL/TLS certificate issues

**Solutions:**
1. Verify URL is accessible: `curl https://your-webhook-url.com/webhooks`
2. Check firewall rules
3. Optimize webhook endpoint performance
4. Use a temporary webhook.site URL for testing

### Issue: Authentication errors

**Error Message:**
```
Tenant ID is required for webhook testing
```

**Solution:**
```php
// Ensure SDK is properly configured
$sdk = new ClubifyCheckoutSDK([
    'credentials' => [
        'organization_id' => 'org_123',  // Required
        'tenant_id' => 'tenant_456',     // Required
        'api_key' => 'your_api_key'      // Required
    ],
    'environment' => 'staging'
]);

$sdk->initialize(); // Don't forget to initialize!
```

### Issue: Invalid webhook URL

**Solution:**
```php
// Ensure webhook URL is properly formatted
$webhookUrl = 'https://app.example.com/api/webhooks/clubify'; // ✓ Correct
// Not: 'app.example.com/webhooks' // ✗ Missing protocol
// Not: 'http://localhost:8000/webhooks' // ✗ May not be accessible
```

### Debugging Tips

1. **Enable debug logging:**
   ```php
   $sdk->setDebugMode(true);
   ```

2. **Use webhook.site for inspection:**
   ```php
   $result = $sdk->notifications()->testWebhookDelivery(
       'order.paid',
       ['test' => true],
       webhookUrl: 'https://webhook.site/unique-id'
   );
   ```

3. **Check SDK stats:**
   ```php
   $stats = $sdk->getStats();
   print_r($stats);
   ```

4. **Verify configuration:**
   ```php
   $config = $sdk->getConfig();
   echo "Tenant ID: " . $config->getTenantId() . "\n";
   echo "Org ID: " . $config->getOrganizationId() . "\n";
   echo "Base URL: " . $config->getBaseUrl() . "\n";
   ```

## Running the Examples

The SDK includes ready-to-use example scripts:

### Simple Test
```bash
php examples/simple-webhook-test.php
```

### Comprehensive Examples
```bash
php examples/webhook-test-example.php
```

## Additional Resources

- [Notification Service API Documentation](../README.md)
- [Webhook Signature Validation](./WEBHOOK_VALIDATION.md)
- [Laravel Integration Guide](./LARAVEL_INTEGRATION.md)
- [Error Handling Guide](./ERROR_HANDLING.md)

## Support

For issues or questions:
- Check the [Troubleshooting](#troubleshooting) section
- Review the [examples](../examples/) directory
- Contact Clubify support team

---

**Last Updated:** October 16, 2025
**SDK Version:** 1.0.0
