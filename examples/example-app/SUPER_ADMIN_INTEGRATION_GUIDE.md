# Super Admin Integration Guide

This guide provides complete configuration and integration details for the Super Admin functionality in the Clubify Checkout Laravel application.

## Overview

The Super Admin system provides multi-tenant management capabilities with secure authentication, fine-grained permissions, and comprehensive API endpoints for managing tenants and system configuration.

## Integration Components

### 1. Route Configuration

#### Primary Routes File
- **File**: `/routes/super-admin.php`
- **Purpose**: Contains all Super Admin API and web routes
- **Integration**: Automatically included in `/routes/web.php`

#### Route Groups

1. **API Routes** (`/api/super-admin`)
   - Authentication endpoints (login)
   - Protected tenant management routes
   - Permission-specific routes with middleware
   - Dashboard and monitoring endpoints

2. **Web Routes** (`/super-admin`)
   - Web-based super admin interface
   - Dashboard, tenant management, and monitoring views

3. **Testing Routes** (`/api/super-admin/test`)
   - Configuration validation endpoints
   - Health checks and integration tests
   - Only enabled when `SUPER_ADMIN_TESTING_ENABLED=true`

### 2. Environment Configuration

#### Required Environment Variables

```env
# Core Configuration
SUPER_ADMIN_ENABLED=true
SUPER_ADMIN_DEFAULT_TENANT=default
SUPER_ADMIN_SESSION_TIMEOUT=3600
SUPER_ADMIN_MAX_CONCURRENT_SESSIONS=5
SUPER_ADMIN_LOG_LEVEL=info
SUPER_ADMIN_CACHE_TTL=1800
SUPER_ADMIN_DEBUG=false

# JWT Configuration
SUPER_ADMIN_JWT_SECRET=your-jwt-secret-here
SUPER_ADMIN_JWT_TTL=3600
SUPER_ADMIN_JWT_REFRESH_TTL=604800
SUPER_ADMIN_JWT_BLACKLIST_ENABLED=true
SUPER_ADMIN_JWT_BLACKLIST_GRACE_PERIOD=30

# API Configuration
SUPER_ADMIN_API_PREFIX=api/super-admin
SUPER_ADMIN_API_MIDDLEWARE=api,auth.super_admin
SUPER_ADMIN_API_RATE_LIMIT=100
SUPER_ADMIN_API_RATE_LIMIT_PERIOD=60

# Security Configuration
SUPER_ADMIN_REQUIRE_MFA=false
SUPER_ADMIN_MAX_LOGIN_ATTEMPTS=5
SUPER_ADMIN_LOCKOUT_DURATION=900
SUPER_ADMIN_AUDIT_LOG_ENABLED=true
```

#### Template Location
- **File**: `/.env.example`
- **Usage**: Copy relevant variables to your `.env` file and update values

### 3. Service Provider Registration

#### Provider Registration
- **File**: `/bootstrap/providers.php`
- **Registered Provider**: `App\Providers\SuperAdminServiceProvider::class`

#### Service Provider Responsibilities
- Configuration loading and validation
- Middleware registration
- Service binding and dependency injection
- Event listener registration

### 4. Configuration Management

#### Main Configuration File
- **File**: `/config/super-admin.php`
- **Purpose**: Centralized configuration for all Super Admin functionality

#### Key Configuration Sections

1. **Core Settings**
   - Enable/disable functionality
   - Default tenant configuration
   - Session management

2. **JWT Authentication**
   - Token configuration
   - Security settings
   - Blacklist management

3. **API Configuration**
   - Route prefixes and middleware
   - Rate limiting
   - API versioning

4. **Security Settings**
   - MFA requirements
   - Password policies
   - IP whitelisting
   - Audit logging

5. **Multi-Tenant Settings**
   - Tenant discovery methods
   - Context switching
   - Data isolation

6. **Testing Configuration**
   - Test endpoint enablement
   - Mock data settings
   - Factory configurations

### 5. Middleware Integration

#### Registered Middleware Aliases
- `auth.super_admin`: Super Admin authentication middleware
- `security.headers`: Security headers middleware
- `ip.whitelist`: IP whitelisting middleware
- `input.sanitization`: Input sanitization middleware
- `api.csrf`: API CSRF protection middleware

#### Middleware Registration Location
- **File**: `/bootstrap/app.php`
- **Configuration**: Applied globally to API routes for security

#### CSRF Token Exclusions
- All super admin API routes (`api/super-admin/*`) are excluded from CSRF validation
- Existing exclusions for Clubify test endpoints maintained

## API Endpoints

### Authentication Endpoints

```http
POST /api/super-admin/login
POST /api/super-admin/logout (protected)
GET  /api/super-admin/context (protected)
```

### Tenant Management Endpoints

```http
GET    /api/super-admin/tenants (protected)
POST   /api/super-admin/tenants (protected)
GET    /api/super-admin/tenants/{tenantId} (protected)
PUT    /api/super-admin/tenants/{tenantId} (protected)
DELETE /api/super-admin/tenants/{tenantId} (protected, requires tenant.delete permission)
```

### Tenant Context Management

```http
POST /api/super-admin/switch-tenant (protected)
POST /api/super-admin/clear-tenant-context (protected)
```

### System Monitoring

```http
GET /api/super-admin/dashboard-stats (protected)
GET /api/super-admin/system/health (protected, requires system.monitor permission)
PUT /api/super-admin/system/configuration (protected, requires configuration.manage permission)
```

### Testing Endpoints (Development Only)

```http
GET  /api/super-admin/test/ (public)
GET  /api/super-admin/test/config (public)
GET  /api/super-admin/test/middleware (public)
GET  /api/super-admin/test/permissions (public)
GET  /api/super-admin/test/health (public)
POST /api/super-admin/test/integration (public)
GET  /api/super-admin/test/authenticated (protected)
GET  /api/super-admin/test/tenant-context (protected)
```

## Security Features

### Authentication
- JWT-based authentication with configurable TTL
- Token blacklisting support
- Session timeout management
- Concurrent session limits

### Authorization
- Fine-grained permission system
- Permission-based route protection
- Tenant-specific permissions
- Role-based access control

### Security Measures
- IP whitelisting support
- Rate limiting on API endpoints
- Input sanitization middleware
- Security headers middleware
- Audit logging for all actions
- CSRF protection for web routes

### Multi-Factor Authentication
- Optional MFA requirement
- Configurable through environment variables
- Integration ready for various MFA providers

## Testing and Validation

### Health Check Endpoint
```http
GET /api/super-admin/test/health
```

Validates:
- Configuration loading
- JWT secret configuration
- Cache store accessibility
- Database connectivity

### Integration Test Endpoint
```http
POST /api/super-admin/test/integration
```

Comprehensive testing of:
- Configuration loading
- Middleware registration
- Route registration
- Environment variable validation

### Configuration Validation
```http
GET /api/super-admin/test/config
```

Returns current configuration settings for validation.

### Middleware Validation
```http
GET /api/super-admin/test/middleware
```

Validates middleware registration and aliases.

## Error Handling

### Custom Error Codes
- `1001`: Authentication failed
- `1002`: Authorization failed
- `1003`: Tenant not found
- `1004`: Permission denied
- `1005`: Session expired
- `1006`: Rate limit exceeded

### Error Response Format
```json
{
    "status": "error",
    "code": 1001,
    "message": "Authentication failed",
    "timestamp": "2025-09-19T18:30:00Z"
}
```

## Performance Considerations

### Caching
- Configurable cache TTL for permissions and tenant data
- Separate cache tags for different data types
- Cache invalidation on permission/tenant changes

### Rate Limiting
- Configurable rate limits per endpoint
- Per-user rate limiting for authenticated endpoints
- Global rate limiting for public endpoints

### Database Optimization
- Indexed queries for tenant and permission lookups
- Optimized multi-tenant data isolation
- Connection pooling for high-concurrency scenarios

## Deployment Considerations

### Production Settings
```env
SUPER_ADMIN_DEBUG=false
SUPER_ADMIN_TESTING_ENABLED=false
SUPER_ADMIN_AUDIT_LOG_ENABLED=true
SUPER_ADMIN_REQUIRE_MFA=true
```

### Security Recommendations
1. Generate a strong JWT secret (256-bit minimum)
2. Enable IP whitelisting for super admin access
3. Configure short JWT TTL for enhanced security
4. Enable audit logging for compliance
5. Require MFA for production environments
6. Disable testing endpoints in production

### Monitoring Setup
1. Configure log aggregation for audit logs
2. Set up alerts for failed authentication attempts
3. Monitor API rate limiting metrics
4. Track tenant context switching patterns

## Troubleshooting

### Common Issues

1. **Middleware Not Registered**
   - Check `/bootstrap/app.php` for middleware alias registration
   - Verify middleware class exists and is properly namespaced

2. **Routes Not Accessible**
   - Ensure `/routes/super-admin.php` is included in `/routes/web.php`
   - Check route caching: `php artisan route:clear`

3. **Configuration Not Loading**
   - Verify `/config/super-admin.php` exists and is readable
   - Clear configuration cache: `php artisan config:clear`

4. **JWT Authentication Issues**
   - Ensure `SUPER_ADMIN_JWT_SECRET` is set and not empty
   - Check JWT TTL settings
   - Verify token format and claims

### Debug Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Check configuration
php artisan tinker
>>> config('super-admin.enabled')

# List routes
php artisan route:list --name=super-admin

# Test middleware registration
php artisan tinker
>>> app('router')->getMiddleware()
```

## Integration Checklist

- [ ] Routes configured in `/routes/web.php`
- [ ] Environment variables added to `.env.example`
- [ ] Service provider registered in `/bootstrap/providers.php`
- [ ] Configuration file created at `/config/super-admin.php`
- [ ] Middleware registered in `/bootstrap/app.php`
- [ ] Testing endpoints accessible (development only)
- [ ] Health check endpoint responding
- [ ] Integration tests passing
- [ ] JWT secret configured
- [ ] Security settings reviewed
- [ ] Production settings configured

## Next Steps

1. **Create Super Admin Users**: Implement user creation and permission assignment
2. **Implement Controllers**: Create the `SuperAdminController` with all endpoint logic
3. **Add Views**: Create web interface views for super admin functionality
4. **Set Up Testing**: Create comprehensive test suites for all functionality
5. **Configure Monitoring**: Set up logging and monitoring for production use

This integration provides a complete foundation for Super Admin functionality with comprehensive security, testing, and configuration management capabilities.