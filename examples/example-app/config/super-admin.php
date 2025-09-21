<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Super Admin Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all settings for the Super Admin
    | functionality including authentication, permissions, security,
    | and multi-tenant management.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Enable Super Admin
    |--------------------------------------------------------------------------
    |
    | Determines if the Super Admin functionality is enabled. When disabled,
    | all super admin routes and middleware will be inactive.
    |
    */

    'enabled' => env('SUPER_ADMIN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Tenant
    |--------------------------------------------------------------------------
    |
    | The default tenant ID to use when no specific tenant context is set.
    | This is useful for initial setup and fallback scenarios.
    |
    */

    'default_tenant' => env('SUPER_ADMIN_DEFAULT_TENANT', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for super admin sessions including timeout and
    | concurrent session limits.
    |
    */

    'session' => [
        'timeout' => env('SUPER_ADMIN_SESSION_TIMEOUT', 3600), // 1 hour
        'max_concurrent_sessions' => env('SUPER_ADMIN_MAX_CONCURRENT_SESSIONS', 5),
        'grace_period' => env('SUPER_ADMIN_SESSION_GRACE_PERIOD', 300), // 5 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    |
    | JWT settings for super admin authentication including secret,
    | token TTL, and blacklist configuration.
    |
    */

    'jwt' => [
        'secret' => env('SUPER_ADMIN_JWT_SECRET'),
        'ttl' => env('SUPER_ADMIN_JWT_TTL', 3600), // 1 hour
        'refresh_ttl' => env('SUPER_ADMIN_JWT_REFRESH_TTL', 604800), // 7 days
        'blacklist_enabled' => env('SUPER_ADMIN_JWT_BLACKLIST_ENABLED', true),
        'blacklist_grace_period' => env('SUPER_ADMIN_JWT_BLACKLIST_GRACE_PERIOD', 30),
        'algorithm' => 'HS256',
        'claims' => [
            'iss' => 'clubify-super-admin',
            'aud' => 'clubify-api',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for super admin API endpoints including prefixes,
    | middleware, and rate limiting.
    |
    */

    'api' => [
        'prefix' => env('SUPER_ADMIN_API_PREFIX', 'api/super-admin'),
        'middleware' => explode(',', env('SUPER_ADMIN_API_MIDDLEWARE', 'api,auth.super_admin')),
        'rate_limit' => [
            'limit' => env('SUPER_ADMIN_API_RATE_LIMIT', 100),
            'period' => env('SUPER_ADMIN_API_RATE_LIMIT_PERIOD', 60), // in minutes
        ],
        'versioning' => [
            'enabled' => true,
            'default_version' => 'v1',
            'header' => 'X-API-Version',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Configuration
    |--------------------------------------------------------------------------
    |
    | Security settings including MFA, login attempts, and audit logging.
    |
    */

    'security' => [
        'require_mfa' => env('SUPER_ADMIN_REQUIRE_MFA', false),
        'max_login_attempts' => env('SUPER_ADMIN_MAX_LOGIN_ATTEMPTS', 5),
        'lockout_duration' => env('SUPER_ADMIN_LOCKOUT_DURATION', 900), // 15 minutes
        'password_requirements' => [
            'min_length' => 12,
            'require_uppercase' => true,
            'require_lowercase' => true,
            'require_numbers' => true,
            'require_symbols' => true,
        ],
        'ip_whitelist' => [
            'enabled' => env('SUPER_ADMIN_IP_WHITELIST_ENABLED', false),
            'addresses' => explode(',', env('SUPER_ADMIN_ALLOWED_IPS', '')),
        ],
        'audit_log' => [
            'enabled' => env('SUPER_ADMIN_AUDIT_LOG_ENABLED', true),
            'include_request_data' => true,
            'include_response_data' => false,
            'retention_days' => 90,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions Configuration
    |--------------------------------------------------------------------------
    |
    | Define the available permissions for super admin users and their
    | default assignments.
    |
    */

    'permissions' => [
        'tenant.create' => 'Create new tenants',
        'tenant.read' => 'View tenant information',
        'tenant.update' => 'Update tenant settings',
        'tenant.delete' => 'Delete tenants',
        'tenant.switch' => 'Switch between tenant contexts',
        'system.monitor' => 'View system health and monitoring',
        'system.configure' => 'Modify system configuration',
        'configuration.manage' => 'Manage application configuration',
        'user.impersonate' => 'Impersonate other users',
        'data.export' => 'Export tenant data',
        'data.import' => 'Import tenant data',
        'logs.view' => 'View application logs',
        'metrics.view' => 'View system metrics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Super Admin User
    |--------------------------------------------------------------------------
    |
    | Configuration for the default super admin user that gets created
    | during system setup.
    |
    */

    'default_user' => [
        'email' => env('SUPER_ADMIN_DEFAULT_EMAIL', 'admin@clubify.com'),
        'name' => env('SUPER_ADMIN_DEFAULT_NAME', 'Super Administrator'),
        'permissions' => [
            'tenant.create',
            'tenant.read',
            'tenant.update',
            'tenant.delete',
            'tenant.switch',
            'system.monitor',
            'system.configure',
            'configuration.manage',
            'logs.view',
            'metrics.view',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for caching super admin data including permissions,
    | tenant information, and session data.
    |
    */

    'cache' => [
        'ttl' => env('SUPER_ADMIN_CACHE_TTL', 1800), // 30 minutes
        'prefix' => 'super_admin',
        'tags' => [
            'permissions' => 'super_admin_permissions',
            'tenants' => 'super_admin_tenants',
            'sessions' => 'super_admin_sessions',
        ],
        'store' => env('SUPER_ADMIN_CACHE_STORE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for super admin specific logging including channels
    | and log levels.
    |
    */

    'logging' => [
        'level' => env('SUPER_ADMIN_LOG_LEVEL', 'info'),
        'channel' => env('SUPER_ADMIN_LOG_CHANNEL', 'single'),
        'separate_file' => true,
        'daily' => true,
        'max_files' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenant Configuration
    |--------------------------------------------------------------------------
    |
    | Settings specific to multi-tenant functionality including tenant
    | discovery and context switching.
    |
    */

    'multi_tenant' => [
        'enabled' => true,
        'tenant_model' => 'App\\Models\\Tenant',
        'discovery' => [
            'methods' => ['header', 'subdomain', 'parameter'],
            'header_name' => 'X-Tenant-ID',
            'parameter_name' => 'tenant_id',
        ],
        'context' => [
            'cache_key' => 'super_admin_tenant_context',
            'session_key' => 'super_admin_tenant_id',
        ],
        'isolation' => [
            'database' => true,
            'storage' => true,
            'cache' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for testing super admin functionality including
    | test endpoints and mock data.
    |
    */

    'testing' => [
        'enabled' => env('SUPER_ADMIN_TESTING_ENABLED', env('APP_ENV') === 'testing'),
        'mock_data' => [
            'tenants' => true,
            'users' => true,
            'permissions' => true,
        ],
        'test_endpoints' => [
            'enabled' => env('SUPER_ADMIN_TEST_ENDPOINTS_ENABLED', false),
            'prefix' => 'test',
        ],
        'factories' => [
            'tenant_count' => 5,
            'user_count' => 10,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    |
    | Debug settings for super admin functionality.
    |
    */

    'debug' => env('SUPER_ADMIN_DEBUG', env('APP_DEBUG', false)),

    /*
    |--------------------------------------------------------------------------
    | Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for integrating with external services and APIs.
    |
    */

    'integration' => [
        'clubify_sdk' => [
            'enabled' => true,
            'config_key' => 'clubify-checkout',
        ],
        'webhook_notifications' => [
            'enabled' => true,
            'events' => [
                'tenant.created',
                'tenant.updated',
                'tenant.deleted',
                'user.created',
                'user.updated',
                'permission.granted',
                'permission.revoked',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for error handling and exception management.
    |
    */

    'error_handling' => [
        'log_exceptions' => true,
        'include_stack_trace' => env('APP_DEBUG', false),
        'custom_error_pages' => true,
        'api_error_format' => 'json',
        'error_codes' => [
            'authentication_failed' => 1001,
            'authorization_failed' => 1002,
            'tenant_not_found' => 1003,
            'permission_denied' => 1004,
            'session_expired' => 1005,
            'rate_limit_exceeded' => 1006,
        ],
    ],

];