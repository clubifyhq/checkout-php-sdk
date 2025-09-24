# Organization Setup Rollback and Recovery Guide

This guide provides comprehensive procedures for handling rollback and recovery scenarios in the organization setup process.

## Overview

The organization setup process consists of 5 sequential steps:
1. **Organization Creation** - Creates the base organization entity
2. **Tenant Creation** - Sets up multi-tenancy isolation
3. **Admin User Creation** - Creates the initial administrator
4. **API Key Generation** - Generates authentication keys
5. **Domain Configuration** - Configures custom domain (optional)

Each step has specific rollback procedures and recovery options depending on the failure point.

## Rollback Scenarios

### Scenario 1: Tenant Creation Fails

**What happens:**
- Organization is created successfully
- Tenant creation fails (subdomain conflict, network error, etc.)

**Automatic Rollback:**
```php
// The system automatically:
// 1. Deletes the created organization
// 2. Cleans up any cached data
// 3. Logs the rollback operation
```

**Manual Recovery Options:**

1. **Complete Rollback and Retry:**
   ```bash
   # Delete organization manually if auto-rollback failed
   curl -X DELETE /api/organizations/{org_id}

   # Retry with different subdomain
   curl -X POST /api/organizations/setup \
     -H "Content-Type: application/json" \
     -d '{"name": "My Org", "subdomain": "alternative-subdomain"}'
   ```

2. **Use Existing Tenant:**
   ```php
   // If subdomain conflict, check if existing tenant is suitable
   $existingTenant = $organizationModule->tenant()->getTenantBySubdomain($subdomain);
   if ($existingTenant && $existingTenant['organization_id'] === $organizationId) {
       // Continue setup with existing tenant
   }
   ```

### Scenario 2: Admin User Creation Fails

**What happens:**
- Organization and tenant created successfully
- Admin user creation fails (email conflict, validation error, etc.)

**Automatic Rollback:**
```php
// The system automatically:
// 1. Deletes the created admin user (if partially created)
// 2. Deletes the tenant
// 3. Deletes the organization
// 4. Cleans up all cached data
```

**Manual Recovery Options:**

1. **Complete Rollback:**
   ```bash
   # Manual cleanup if auto-rollback fails
   curl -X DELETE /api/tenants/{tenant_id}
   curl -X DELETE /api/organizations/{org_id}
   ```

2. **Continue with Existing User:**
   ```php
   // If email exists, verify user belongs to organization
   $existingUser = $organizationModule->admin()->getAdminByEmail($email);
   if ($existingUser && $existingUser['organization_id'] === $organizationId) {
       // Continue setup with existing admin
   }
   ```

3. **Use Different Admin Email:**
   ```php
   // Retry with different admin email
   $organizationData['admin_email'] = 'alternative@email.com';
   $result = $organizationModule->setupOrganization($organizationData, $idempotencyKey);
   ```

### Scenario 3: API Key Generation Fails

**What happens:**
- Organization, tenant, and admin created successfully
- API key generation fails (rate limiting, service unavailable, etc.)

**Recovery Options:**

1. **Retry API Key Generation Only:**
   ```php
   // This is recoverable - retry just the API key step
   try {
       $apiKeys = $organizationModule->apiKey()->generateKeys($organizationId);
       // Continue with domain configuration if needed
   } catch (\Exception $e) {
       // If still failing, can complete setup without API keys initially
   }
   ```

2. **Complete Setup Without API Keys (Temporary):**
   ```php
   // Mark setup as "partial" - admin can generate keys later
   $result = [
       'success' => true,
       'status' => 'partial',
       'missing_components' => ['api_keys'],
       'organization' => $organization,
       'tenant' => $tenant,
       'admin' => $admin,
       'next_steps' => [
           'Generate API keys manually from admin dashboard',
           'Configure domain if needed'
       ]
   ];
   ```

3. **Manual API Key Generation:**
   ```bash
   # Generate keys manually via API
   curl -X POST /api/organizations/{org_id}/api-keys \
     -H "Authorization: Bearer {admin_token}" \
     -H "Content-Type: application/json"
   ```

### Scenario 4: Domain Configuration Fails

**What happens:**
- All core components created successfully
- Domain configuration fails (DNS issues, domain unavailable, etc.)

**Recovery Options:**

1. **Complete Without Domain:**
   ```php
   // Domain is optional - setup can be considered successful
   $result = [
       'success' => true,
       'organization' => $organization,
       'tenant' => $tenant,
       'admin' => $admin,
       'api_keys' => $apiKeys,
       'domain' => null,
       'warnings' => ['Domain configuration failed - can be configured later']
   ];
   ```

2. **Retry Domain Configuration Later:**
   ```php
   // Configure domain separately after setup
   try {
       $domain = $organizationModule->domain()->configureDomain($organizationId, $domainName);
   } catch (\Exception $e) {
       // Log for manual configuration
   }
   ```

### Scenario 5: Network/API Failures

**What happens:**
- Network connectivity issues or API service unavailable
- Can occur at any step

**Recovery Strategies:**

1. **Automatic Retry with Exponential Backoff:**
   ```php
   // System automatically retries with increasing delays
   // Retry attempts: 1s, 2s, 4s, 8s, 16s (max 5 attempts)
   $result = $organizationModule->setupOrganization(
       $organizationData,
       $idempotencyKey,
       true,  // enableRollback
       true   // enableRetry
   );
   ```

2. **Manual Retry After Connectivity Restored:**
   ```php
   // Use same idempotency key to safely retry
   $result = $organizationModule->setupOrganization($organizationData, $originalIdempotencyKey);
   ```

## Idempotency Handling

All setup operations use idempotency keys to ensure safe retries:

```php
// Generate consistent idempotency key
$idempotencyKey = 'org_setup_' . hash('sha256', json_encode($organizationData));

// Safe to retry multiple times with same key
$result = $organizationModule->setupOrganization($organizationData, $idempotencyKey);
```

**Idempotency Behavior:**
- If operation completed successfully, returns existing result
- If operation failed, attempts retry or rollback as configured
- Keys expire after 24 hours

## Manual Cleanup Procedures

### Complete Manual Rollback

When automatic rollback fails, follow these steps in order:

1. **Revoke API Keys:**
   ```bash
   curl -X DELETE /api/organizations/{org_id}/api-keys \
     -H "Authorization: Bearer {admin_token}"
   ```

2. **Delete Admin User:**
   ```bash
   curl -X DELETE /api/admins/{admin_id} \
     -H "Authorization: Bearer {system_token}"
   ```

3. **Delete Tenant:**
   ```bash
   curl -X DELETE /api/tenants/{tenant_id} \
     -H "Authorization: Bearer {system_token}"
   ```

4. **Delete Organization:**
   ```bash
   curl -X DELETE /api/organizations/{org_id} \
     -H "Authorization: Bearer {system_token}"
   ```

5. **Clear Cache:**
   ```bash
   # Clear all cached data
   curl -X DELETE /api/cache/organization/{org_id} \
     -H "Authorization: Bearer {system_token}"
   ```

### Verification Steps

After manual cleanup, verify all resources are removed:

1. **Check Organization:**
   ```bash
   curl -X GET /api/organizations/{org_id}
   # Should return 404 Not Found
   ```

2. **Check Tenant:**
   ```bash
   curl -X GET /api/tenants/{tenant_id}
   # Should return 404 Not Found
   ```

3. **Check Admin User:**
   ```bash
   curl -X GET /api/admins/{admin_id}
   # Should return 404 Not Found
   ```

4. **Check API Keys:**
   ```bash
   curl -X GET /api/api-keys/{key_id}/status
   # Should return "revoked" or 404
   ```

## Error Reporting and Monitoring

### Setup Failure Alerts

Monitor these metrics for setup failures:

```php
// Get rollback statistics
$rollbackStats = $organizationModule->rollback()->getRollbackStats();

// Get retry statistics
$retryStats = $organizationModule->retry()->getRetryStats();

// Monitor key metrics
$metrics = [
    'total_setups' => $retryStats['total_attempts'],
    'success_rate' => $retryStats['success_rate'],
    'rollback_rate' => $rollbackStats['failed_procedures'] / $rollbackStats['total_rollbacks'],
    'avg_retry_attempts' => $retryStats['total_attempts'] / count(unique_idempotency_keys)
];
```

### Logging

All setup operations are logged with detailed context:

```php
// Setup attempt logging
$this->logger->info('Organization setup started', [
    'idempotency_key' => $idempotencyKey,
    'organization_data' => $sanitizedData
]);

// Step completion logging
$this->logger->info('Setup step completed', [
    'step' => 'tenant_creation',
    'organization_id' => $organizationId,
    'tenant_id' => $tenantId
]);

// Failure logging with rollback context
$this->logger->error('Setup failed', [
    'step' => $failedStep,
    'error' => $exception->getMessage(),
    'rollback_required' => $rollbackRequired,
    'created_resources' => $createdResources
]);
```

## Best Practices

### For Development

1. **Always Test Rollback Scenarios:**
   ```php
   // Test each failure point
   $testScenarios = [
       'tenant_creation_fails',
       'admin_creation_fails',
       'api_key_generation_fails',
       'domain_configuration_fails',
       'network_failure'
   ];
   ```

2. **Use Consistent Idempotency Keys:**
   ```php
   // Generate deterministic keys for testing
   $idempotencyKey = 'test_' . hash('sha256', $testData);
   ```

### For Production

1. **Enable Comprehensive Logging:**
   ```php
   $organizationModule->setupOrganization(
       $organizationData,
       $idempotencyKey,
       true,  // enableRollback
       true   // enableRetry
   );
   ```

2. **Monitor Setup Success Rates:**
   - Setup completion rate should be > 95%
   - Rollback rate should be < 5%
   - Average retry attempts should be < 2

3. **Set Up Alerts for:**
   - Setup failures requiring manual intervention
   - Rollback failures
   - High retry rates
   - Network connectivity issues

### For Customer Support

1. **Provide Clear Error Messages:**
   ```php
   try {
       $result = $organizationModule->setupOrganization($data, $idempotencyKey);
   } catch (OrganizationSetupException $e) {
       $userMessage = $this->formatUserFriendlyError($e);
       $supportData = $e->toArray(); // For support team
   }
   ```

2. **Recovery Instructions:**
   - Provide idempotency key for safe retries
   - Offer alternative options (different subdomain, email, etc.)
   - Escalate to technical support with full context

## Troubleshooting Common Issues

### Issue: Subdomain Already Exists
**Solution:**
```php
// Check subdomain availability first
$available = $organizationModule->tenant()->isSubdomainAvailable($subdomain);
if (!$available) {
    $suggestions = generateSubdomainSuggestions($subdomain);
    // Offer alternatives to user
}
```

### Issue: Email Already Exists
**Solution:**
```php
// Check if user belongs to same organization context
$existingUser = $organizationModule->admin()->getAdminByEmail($email);
if ($existingUser) {
    // Offer to use existing user or provide different email
}
```

### Issue: API Rate Limiting
**Solution:**
```php
// Implement exponential backoff
$retryService->configureRetryParams([
    'max_retry_attempts' => 5,
    'base_delay_seconds' => 2,
    'max_delay_seconds' => 300,
    'backoff_multiplier' => 2.0
]);
```

### Issue: Partial Setup State
**Solution:**
```php
// Generate manual cleanup report
$cleanupReport = $rollbackService->generateManualCleanupReport($setupException);
// Provide step-by-step cleanup instructions
```

This guide ensures robust handling of all failure scenarios in the organization setup process, providing both automatic recovery mechanisms and manual procedures for complex situations.