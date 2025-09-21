# Super Admin Backend Architecture Implementation

This document provides a comprehensive overview of the Super Admin functionality implementation for the Clubify Checkout PHP SDK example application.

## Overview

The Super Admin backend architecture enables multi-tenant management capabilities with secure context switching, comprehensive session management, and robust audit logging. The implementation maintains backward compatibility with existing single-tenant mode while providing elevated privileges for tenant administration.

## Architecture Components

### 1. ClubifySDKHelper Extension (`app/Helpers/ClubifySDKHelper.php`)

**New Methods Added:**
- `getSuperAdminInstance(?string $superAdminToken = null): ClubifyCheckoutSDK`
- `getTenantInstance(string $tenantId, ?string $superAdminToken = null): ClubifyCheckoutSDK`
- `validateSuperAdminAccess(string $superAdminToken): array`
- `getAvailableTenants(string $superAdminToken, array $filters = []): array`
- `switchToTenant(string $tenantId, string $superAdminToken): bool`

**Key Features:**
- Singleton pattern maintenance for regular SDK usage
- Separate instances for super admin operations
- Tenant-specific SDK instances with proper headers
- Token validation and permission checking
- Comprehensive error handling and logging

### 2. SuperAdminController (`app/Http/Controllers/SuperAdminController.php`)

**Endpoints Provided:**
- `POST /api/super-admin/login` - Super admin authentication
- `GET /api/super-admin/tenants` - List available tenants
- `POST /api/super-admin/switch-tenant` - Switch to tenant context
- `GET /api/super-admin/tenants/{tenantId}` - Get tenant information
- `POST /api/super-admin/tenants` - Create new tenant
- `PUT /api/super-admin/tenants/{tenantId}` - Update tenant
- `GET /api/super-admin/context` - Get current context
- `POST /api/super-admin/clear-tenant-context` - Return to super admin mode
- `POST /api/super-admin/logout` - End super admin session
- `GET /api/super-admin/dashboard-stats` - Dashboard statistics

**Security Features:**
- Comprehensive input validation
- Permission-based access control
- Activity logging for all operations
- IP address and user agent tracking
- Error handling with sanitized responses

### 3. ContextManager Service (`app/Services/ContextManager.php`)

**Core Functionality:**
- Session management for super admin authentication
- Tenant context switching and persistence
- Permission checking and validation
- Session expiration and renewal
- Cross-request context preservation

**Key Methods:**
- `setSuperAdminContext(string $superAdminToken, array $permissions, ?int $ttl = null): bool`
- `getSuperAdminToken(): ?string`
- `setCurrentTenant(string $tenantId, ?int $ttl = null): bool`
- `getCurrentTenantId(): ?string`
- `getCurrentContext(): array`
- `hasPermission(string $permission): bool`

### 4. Configuration Updates (`config/clubify-checkout.php`)

**New Configuration Sections:**

#### Super Admin Configuration
```php
'super_admin' => [
    'enabled' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ENABLED', false),
    'api_key' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_API_KEY'),
    'session_ttl' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_SESSION_TTL', 3600),
    'authentication' => [...],
    'permissions' => [...],
    'tenant_management' => [...],
    'monitoring' => [...],
    'security' => [...],
]
```

#### Multi-Tenant Configuration
```php
'multi_tenant' => [
    'enabled' => env('CLUBIFY_CHECKOUT_MULTI_TENANT_ENABLED', false),
    'tenant_identification' => env('CLUBIFY_CHECKOUT_TENANT_IDENTIFICATION', 'header'),
    'database' => [...],
    'cache' => [...],
    'features' => [...],
]
```

### 5. Database Models

#### Tenant Model (`app/Models/Tenant.php`)
- Tenant information and configuration
- Subscription and plan management
- Feature and limit tracking
- Activity relationship management

#### SuperAdmin Model (`app/Models/SuperAdmin.php`)
- Super admin user management
- Permission and access control
- Multi-tenant access relationships
- Authentication and session tracking

#### Activity Logging Models
- `TenantActivityLog` - Tenant-specific activities
- `SuperAdminActivityLog` - Super admin actions
- `SuperAdminSession` - Session management

### 6. Database Migrations

Six comprehensive migrations create the required database structure:
1. `create_tenants_table` - Tenant storage
2. `create_super_admins_table` - Super admin users
3. `create_super_admin_tenant_access_table` - Access relationships
4. `create_tenant_activity_logs_table` - Tenant audit logs
5. `create_super_admin_activity_logs_table` - Admin audit logs
6. `create_super_admin_sessions_table` - Session management

### 7. Middleware and Security

#### SuperAdminMiddleware (`app/Http/Middleware/SuperAdminMiddleware.php`)
- Authentication verification
- Permission checking
- Request context injection
- Security logging

#### Security Features
- Token-based authentication
- Session management with TTL
- IP address tracking
- Rate limiting support
- CSRF protection
- Secure header handling

## Implementation Details

### Backward Compatibility

The implementation maintains full backward compatibility:
- Existing single-tenant SDK usage remains unchanged
- Original helper methods continue to function
- Configuration defaults preserve existing behavior
- No breaking changes to public APIs

### Session Management

```php
// Super admin session flow
$contextManager->setSuperAdminContext($token, $permissions);
$contextManager->setCurrentTenant($tenantId);
$currentContext = $contextManager->getCurrentContext();
```

### Multi-Tenant SDK Usage

```php
// Get super admin SDK instance
$sdk = ClubifySDKHelper::getSuperAdminInstance($token);

// Get tenant-specific SDK instance
$tenantSdk = ClubifySDKHelper::getTenantInstance($tenantId, $token);

// Switch tenant context
ClubifySDKHelper::switchToTenant($tenantId, $token);
```

### Permission System

Granular permissions control access to functionality:
- `tenant.list` - List tenants
- `tenant.create` - Create tenants
- `tenant.read` - View tenant details
- `tenant.update` - Modify tenants
- `tenant.delete` - Remove tenants
- `tenant.switch` - Switch context
- `user.impersonate` - Impersonate users
- `system.monitor` - System monitoring
- `analytics.view` - View analytics
- `configuration.manage` - Manage configuration

## Environment Variables

### Required Super Admin Variables
```env
CLUBIFY_CHECKOUT_SUPER_ADMIN_ENABLED=true
CLUBIFY_CHECKOUT_SUPER_ADMIN_API_KEY=your_super_admin_api_key
CLUBIFY_CHECKOUT_SUPER_ADMIN_API_URL=https://checkout.svelve.com/api/v1/super-admin
```

### Optional Configuration
```env
CLUBIFY_CHECKOUT_SUPER_ADMIN_SESSION_TTL=3600
CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_SWITCH_TTL=1800
CLUBIFY_CHECKOUT_SUPER_ADMIN_REQUIRE_2FA=true
CLUBIFY_CHECKOUT_MULTI_TENANT_ENABLED=true
```

## Usage Examples

### Super Admin Authentication
```php
// POST /api/super-admin/login
{
    "email": "admin@example.com",
    "password": "secure_password",
    "super_admin_token": "your_super_admin_token"
}
```

### Tenant Switching
```php
// POST /api/super-admin/switch-tenant
{
    "tenant_id": "tenant_12345"
}
```

### Tenant Creation
```php
// POST /api/super-admin/tenants
{
    "name": "New Tenant",
    "email": "tenant@example.com",
    "plan": "premium",
    "features": ["checkout", "payments", "analytics"]
}
```

## Security Considerations

1. **Token Security**: Super admin tokens are encrypted and rotated
2. **Session Management**: TTL-based sessions with activity tracking
3. **Audit Logging**: Comprehensive logging of all admin activities
4. **Permission Control**: Granular permission system
5. **Rate Limiting**: API rate limiting for security
6. **IP Restrictions**: Optional IP whitelist support
7. **2FA Support**: Two-factor authentication integration

## Monitoring and Logging

### Activity Tracking
- All tenant operations are logged
- Super admin actions are audited
- Session activities are monitored
- Security events are tracked

### Performance Monitoring
- SDK operation timing
- Session performance metrics
- Database query optimization
- Cache utilization tracking

## Error Handling

Comprehensive error handling throughout:
- SDK initialization failures
- Authentication errors
- Permission violations
- Session expiration
- Tenant access issues
- Network connectivity problems

## Testing Considerations

### Unit Tests
- SDK helper method testing
- Context manager functionality
- Model relationship validation
- Permission system verification

### Integration Tests
- End-to-end super admin workflows
- Tenant switching scenarios
- Security boundary testing
- Performance under load

## Deployment Notes

### Database Setup
```bash
php artisan migrate
```

### Service Provider Registration
Add to `config/app.php`:
```php
'providers' => [
    // ...
    App\Providers\SuperAdminServiceProvider::class,
],
```

### Route Registration
Include in `routes/web.php` or `routes/api.php`:
```php
require __DIR__.'/super-admin.php';
```

## Future Enhancements

1. **Web Interface**: Complete admin dashboard UI
2. **Real-time Notifications**: WebSocket-based updates
3. **Advanced Analytics**: Tenant usage analytics
4. **Bulk Operations**: Multi-tenant bulk actions
5. **API Rate Limiting**: Per-tenant rate limiting
6. **Advanced Security**: Enhanced security features
7. **Integration Testing**: Comprehensive test suite

This implementation provides a robust foundation for multi-tenant super admin functionality while maintaining the flexibility for future enhancements and scalability requirements.