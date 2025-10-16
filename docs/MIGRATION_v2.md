# Migration Guide - SDK PHP v2.0.0

## Breaking Changes

### partnerId → tenantId

All methods now use `tenantId` as the primary identifier instead of `partnerId`.

#### Before (v1.x)
```php
$config = $webhooks->findByPartnerId('partner-123');
```

#### After (v2.0.0)
```php
$config = $webhooks->findByTenantId('partner-123'); // Same ID, new name
```

**Note:** The old methods still work but are deprecated and will be removed in v3.0.0.

### Multiple Configurations per Tenant

The notification-service now supports multiple webhook configurations per tenant,
identified by a unique `name` field.

#### Before (v1.x)
```php
// Only one configuration allowed per organization
$config = $webhooks->create([
    'url' => '...',
    'events' => [...],
    'organization_id' => 'org-123'
]);
```

#### After (v2.0.0)
```php
// Multiple configurations allowed with unique names
$prodConfig = $webhooks->create([
    'name' => 'Production Webhooks',
    'url' => 'https://prod.myapp.com/webhooks',
    'events' => [...],
    'organization_id' => 'org-123'
]);

$testConfig = $webhooks->create([
    'name' => 'Test Webhooks',
    'url' => 'https://test.myapp.com/webhooks',
    'events' => [...],
    'organization_id' => 'org-123'
]);
```

## New Features

### createOrUpdateWebhook()

Automatically creates a new configuration or adds events to an existing one.

```php
// Safely add events without worrying about 409 Conflict
$webhooks->createOrUpdateWebhook([
    'url' => 'https://myapp.com/webhooks',
    'events' => ['order.created', 'payment.paid'],
    'organization_id' => 'org-123'
]);
```

### Endpoint Management Methods

Fine-grained control over individual webhook endpoints:

```php
// Add single endpoint
$webhooks->addEndpoint($orgId, $configName, 'order.created', 'https://...');

// Remove endpoint
$webhooks->removeEndpoint($orgId, $configName, 'order.created');

// List endpoints
$endpoints = $webhooks->listEndpoints($orgId, $configName);

// Update endpoint
$webhooks->updateEndpoint($orgId, $configName, 'order.created', ['isActive' => false]);
```

## Deprecation Timeline

- **v2.0.0 (Now)**: `partnerId` methods deprecated, deprecation warnings logged
- **v2.5.0 (Q2 2025)**: Deprecation warnings become errors in strict mode
- **v3.0.0 (Q3 2025)**: `partnerId` methods removed completely

## Migration Checklist

- [ ] Replace all `findByPartnerId()` calls with `findByTenantId()`
- [ ] Update `organization_id` to `tenant_id` in configuration arrays (optional, backward compatible)
- [ ] Add `name` field to webhook configurations if using multiple configs per tenant
- [ ] Use `createOrUpdateWebhook()` instead of `create()` to avoid 409 conflicts
- [ ] Test webhook delivery with new endpoints
- [ ] Monitor logs for deprecation warnings

## Need Help?

See examples in `examples/webhook-management.php` or check the full documentation at
https://docs.clubify.com/sdk/php
