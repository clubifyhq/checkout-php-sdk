<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Configurações de Autenticação
    |--------------------------------------------------------------------------
    |
    | Credenciais para autenticação com a API do Clubify Checkout.
    | Essas configurações devem ser definidas no arquivo .env
    |
    */

    'api_key' => env('CLUBIFY_CHECKOUT_API_KEY'),
    'api_secret' => env('CLUBIFY_CHECKOUT_API_SECRET'),
    'tenant_id' => env('CLUBIFY_CHECKOUT_TENANT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Configurações de Ambiente
    |--------------------------------------------------------------------------
    |
    | Define o ambiente de execução do SDK.
    | Valores aceitos: 'development', 'staging', 'production'
    |
    */

    'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'development'),
    'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', 'https://checkout.svelve.com/api/v1'),
    'debug' => env('CLUBIFY_CHECKOUT_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Configurações HTTP
    |--------------------------------------------------------------------------
    |
    | Configurações para requisições HTTP do SDK
    |
    */

    'http' => [
        'timeout' => env('CLUBIFY_CHECKOUT_TIMEOUT', 30),
        'connect_timeout' => env('CLUBIFY_CHECKOUT_CONNECT_TIMEOUT', 10),
        'user_agent' => 'Clubify-Checkout-SDK-PHP/' . (env('CLUBIFY_CHECKOUT_VERSION') ?? '1.0.0'),

        'retry' => [
            'enabled' => env('CLUBIFY_CHECKOUT_RETRY_ENABLED', true),
            'attempts' => env('CLUBIFY_CHECKOUT_RETRY_ATTEMPTS', 3),
            'delay' => env('CLUBIFY_CHECKOUT_RETRY_DELAY', 1000), // milliseconds
            'multiplier' => env('CLUBIFY_CHECKOUT_RETRY_MULTIPLIER', 2.0),
            'max_delay' => env('CLUBIFY_CHECKOUT_RETRY_MAX_DELAY', 10000), // milliseconds
        ],

        'headers' => [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Cache
    |--------------------------------------------------------------------------
    |
    | Configurações do sistema de cache do SDK
    |
    */

    'cache' => [
        'enabled' => env('CLUBIFY_CHECKOUT_CACHE_ENABLED', true),
        'default_ttl' => env('CLUBIFY_CHECKOUT_CACHE_TTL', 3600), // seconds
        'prefix' => env('CLUBIFY_CHECKOUT_CACHE_PREFIX', 'clubify_checkout'),

        // Adapter específico para Laravel
        'adapter' => 'laravel',
        'store' => env('CLUBIFY_CHECKOUT_CACHE_STORE', 'default'),

        // TTLs específicos por tipo de dados
        'ttls' => [
            'auth_token' => 3600,
            'organization' => 7200,
            'products' => 1800,
            'configuration' => 7200,
            'user_info' => 1800,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Logging
    |--------------------------------------------------------------------------
    |
    | Configurações do sistema de logging do SDK
    |
    */

    'logger' => [
        'enabled' => env('CLUBIFY_CHECKOUT_LOGGER_ENABLED', true),
        'level' => env('CLUBIFY_CHECKOUT_LOGGER_LEVEL', 'info'),
        'channel' => env('CLUBIFY_CHECKOUT_LOGGER_CHANNEL', 'single'),
        'filename' => env('CLUBIFY_CHECKOUT_LOGGER_FILENAME', 'clubify-checkout.log'),

        'context' => [
            'sdk' => 'clubify-checkout-php',
            'version' => env('CLUBIFY_CHECKOUT_VERSION', '1.0.0'),
            'environment' => env('APP_ENV', 'production'),
        ],

        'formatters' => [
            'default' => 'json',
            'console' => 'line',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Eventos
    |--------------------------------------------------------------------------
    |
    | Configurações do sistema de eventos do SDK
    |
    */

    'events' => [
        'enabled' => env('CLUBIFY_CHECKOUT_EVENTS_ENABLED', true),
        'async' => env('CLUBIFY_CHECKOUT_EVENTS_ASYNC', true),
        'timeout' => env('CLUBIFY_CHECKOUT_EVENTS_TIMEOUT', 5), // seconds

        'listeners' => [
            // Listeners automáticos podem ser definidos aqui
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Webhooks
    |--------------------------------------------------------------------------
    |
    | Configurações para processamento de webhooks
    |
    */

    'webhooks' => [
        'enabled' => env('CLUBIFY_CHECKOUT_WEBHOOKS_ENABLED', true),
        'secret' => env('CLUBIFY_CHECKOUT_WEBHOOK_SECRET'),
        'tolerance' => env('CLUBIFY_CHECKOUT_WEBHOOK_TOLERANCE', 300), // seconds
        'verify_ssl' => env('CLUBIFY_CHECKOUT_WEBHOOK_VERIFY_SSL', true),

        'routes' => [
            'prefix' => 'clubify/webhooks',
            'middleware' => ['api', 'clubify.webhook'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Filas (Queue)
    |--------------------------------------------------------------------------
    |
    | Configurações para processamento assíncrono
    |
    */

    'queue' => [
        'enabled' => env('CLUBIFY_CHECKOUT_QUEUE_ENABLED', true),
        'connection' => env('CLUBIFY_CHECKOUT_QUEUE_CONNECTION', 'default'),

        'queues' => [
            'payments' => [
                'high' => 'payments-high',
                'normal' => 'payments-normal',
                'low' => 'payments-low',
            ],
            'webhooks' => 'webhooks',
            'customers' => 'customers',
            'notifications' => 'notifications',
        ],

        'retry' => [
            'payments' => 3,
            'webhooks' => 5,
            'customers' => 3,
        ],

        'timeout' => [
            'payments' => 120,
            'webhooks' => 30,
            'customers' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Módulos
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para cada módulo do SDK
    |
    */

    'modules' => [
        'organization' => [
            'enabled' => true,
            'cache_ttl' => 7200,
        ],

        'products' => [
            'enabled' => true,
            'cache_ttl' => 1800,
            'auto_sync' => false,
        ],

        'checkout' => [
            'enabled' => true,
            'session_ttl' => 3600,
            'auto_expire' => true,
        ],

        'payments' => [
            'enabled' => true,
            'default_gateway' => env('CLUBIFY_CHECKOUT_DEFAULT_GATEWAY', 'pagarme'),
            'multi_gateway' => true,
            'retry_failed' => true,
        ],

        'customers' => [
            'enabled' => true,
            'auto_matching' => true,
            'update_profile' => true,
            'lgpd_compliance' => true,
        ],

        'webhooks' => [
            'enabled' => true,
            'auto_retry' => true,
            'max_attempts' => 5,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Features
    |--------------------------------------------------------------------------
    |
    | Feature flags para habilitar/desabilitar funcionalidades
    |
    */

    'features' => [
        'auto_initialize' => env('CLUBIFY_CHECKOUT_AUTO_INITIALIZE', true),
        'metrics' => env('CLUBIFY_CHECKOUT_METRICS_ENABLED', true),
        'health_check' => env('CLUBIFY_CHECKOUT_HEALTH_CHECK_ENABLED', true),
        'auto_refresh_token' => env('CLUBIFY_CHECKOUT_AUTO_REFRESH_TOKEN', true),
        'circuit_breaker' => env('CLUBIFY_CHECKOUT_CIRCUIT_BREAKER_ENABLED', true),
        'request_id_tracking' => env('CLUBIFY_CHECKOUT_REQUEST_ID_TRACKING', true),
        'performance_monitoring' => env('CLUBIFY_CHECKOUT_PERFORMANCE_MONITORING', false),
        'fake_mode' => env('CLUBIFY_CHECKOUT_FAKE_MODE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Validação
    |--------------------------------------------------------------------------
    |
    | Configurações para validação de dados
    |
    */

    'validation' => [
        'strict_mode' => env('CLUBIFY_CHECKOUT_VALIDATION_STRICT', true),
        'auto_format' => env('CLUBIFY_CHECKOUT_VALIDATION_AUTO_FORMAT', true),
        'brazilian_documents' => env('CLUBIFY_CHECKOUT_VALIDATION_BR_DOCS', true),
        'credit_cards' => env('CLUBIFY_CHECKOUT_VALIDATION_CREDIT_CARDS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Segurança
    |--------------------------------------------------------------------------
    |
    | SECURITY: Enhanced security settings for credential management
    | Configurações de segurança e compliance
    |
    */

    'security' => [
        'encrypt_sensitive_data' => env('CLUBIFY_CHECKOUT_ENCRYPT_SENSITIVE', true),
        'mask_logs' => env('CLUBIFY_CHECKOUT_MASK_LOGS', true),
        'pci_compliance' => env('CLUBIFY_CHECKOUT_PCI_COMPLIANCE', true),
        'audit_logs' => env('CLUBIFY_CHECKOUT_AUDIT_LOGS', true),

        // NEW: Secure credential storage settings
        'credential_storage' => [
            'driver' => env('CLUBIFY_CREDENTIAL_STORAGE_DRIVER', 'encrypted_file'),
            'path' => env('CLUBIFY_CREDENTIAL_STORAGE_PATH', storage_path('app/clubify/credentials')),
            'encryption_key' => env('APP_KEY'), // Uses Laravel's app key
            'auto_sync' => env('CLUBIFY_CREDENTIAL_AUTO_SYNC', true),
        ],

        // NEW: Rate limiting for security
        'rate_limiting' => [
            'super_admin_transitions' => [
                'max_attempts' => env('CLUBIFY_SUPER_ADMIN_RATE_LIMIT_ATTEMPTS', 5),
                'window_minutes' => env('CLUBIFY_SUPER_ADMIN_RATE_LIMIT_WINDOW', 60),
            ],
            'authentication' => [
                'max_attempts' => env('CLUBIFY_AUTH_RATE_LIMIT_ATTEMPTS', 10),
                'window_minutes' => env('CLUBIFY_AUTH_RATE_LIMIT_WINDOW', 15),
            ],
        ],

        // NEW: Enhanced audit logging
        'audit_logging' => [
            'enabled' => env('CLUBIFY_AUDIT_ENABLED', true),
            'channel' => env('CLUBIFY_AUDIT_CHANNEL', 'single'),
            'events' => [
                'role_transitions' => true,
                'authentication_failures' => true,
                'credential_operations' => true,
                'super_admin_actions' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Super Admin
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para funcionalidades de Super Admin multi-tenant
    |
    */

    'super_admin' => [
        'enabled' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ENABLED', false),
        'api_key' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_API_KEY'),
        'api_hash' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_HASH_KEY'),
        'api_url' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_API_URL', 'https://checkout.svelve.com/api/v1/super-admin'),
        'session_ttl' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_SESSION_TTL', 3600), // 1 hour
        'tenant_switch_ttl' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_SWITCH_TTL', 1800), // 30 minutes

        'authentication' => [
            'require_2fa' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_REQUIRE_2FA', true),
            'token_refresh_threshold' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_TOKEN_REFRESH_THRESHOLD', 300), // 5 minutes
            'max_concurrent_sessions' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_MAX_SESSIONS', 1),
            'ip_whitelist' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_IP_WHITELIST', ''), // comma-separated IPs
        ],

        'permissions' => [
            'default' => [
                'tenant.list',
                'tenant.read',
                'tenant.switch',
            ],
            'full_access' => [
                'tenant.list',
                'tenant.create',
                'tenant.read',
                'tenant.update',
                'tenant.delete',
                'tenant.switch',
                'user.impersonate',
                'system.monitor',
                'analytics.view',
                'configuration.manage',
                'billing.manage',
                'support.access',
            ],
        ],

        'tenant_management' => [
            'auto_provisioning' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_AUTO_PROVISIONING', false),
            'default_plan' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_DEFAULT_PLAN', 'basic'),
            'max_tenants_per_admin' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_MAX_TENANTS', 100),
            'tenant_isolation' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_TENANT_ISOLATION', true),
        ],

        'monitoring' => [
            'track_activities' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_TRACK_ACTIVITIES', true),
            'audit_log' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_AUDIT_LOG', true),
            'performance_monitoring' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_PERFORMANCE_MONITORING', true),
            'alert_thresholds' => [
                'failed_logins' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ALERT_FAILED_LOGINS', 5),
                'session_duration' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ALERT_SESSION_DURATION', 7200), // 2 hours
                'concurrent_sessions' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ALERT_CONCURRENT_SESSIONS', 3),
            ],
        ],

        'context_management' => [
            'session_storage' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_SESSION_STORAGE', 'both'), // session, cache, both
            'context_persistence' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_CONTEXT_PERSISTENCE', true),
            'auto_extend_session' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_AUTO_EXTEND_SESSION', true),
            'cross_tab_sync' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_CROSS_TAB_SYNC', false),
        ],

        'security' => [
            'encrypt_tokens' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_ENCRYPT_TOKENS', true),
            'token_rotation' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_TOKEN_ROTATION', true),
            'secure_headers' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_SECURE_HEADERS', true),
            'csrf_protection' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_CSRF_PROTECTION', true),
            'rate_limiting' => [
                'enabled' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_RATE_LIMITING', true),
                'max_requests_per_minute' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_MAX_REQUESTS_PER_MINUTE', 60),
                'max_tenant_switches_per_hour' => env('CLUBIFY_CHECKOUT_SUPER_ADMIN_MAX_TENANT_SWITCHES_PER_HOUR', 20),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações Multi-Tenant
    |--------------------------------------------------------------------------
    |
    | Configurações para suporte multi-tenant
    |
    */

    'multi_tenant' => [
        'enabled' => env('CLUBIFY_CHECKOUT_MULTI_TENANT_ENABLED', false),
        'tenant_identification' => env('CLUBIFY_CHECKOUT_TENANT_IDENTIFICATION', 'header'), // header, subdomain, parameter
        'tenant_header' => env('CLUBIFY_CHECKOUT_TENANT_HEADER', 'X-Tenant-ID'),
        'tenant_parameter' => env('CLUBIFY_CHECKOUT_TENANT_PARAMETER', 'tenant_id'),

        'database' => [
            'strategy' => env('CLUBIFY_CHECKOUT_TENANT_DB_STRATEGY', 'single'), // single, separate, schema
            'prefix' => env('CLUBIFY_CHECKOUT_TENANT_DB_PREFIX', 'tenant_'),
            'connection_pooling' => env('CLUBIFY_CHECKOUT_TENANT_CONNECTION_POOLING', true),
        ],

        'cache' => [
            'tenant_separation' => env('CLUBIFY_CHECKOUT_TENANT_CACHE_SEPARATION', true),
            'shared_cache_keys' => env('CLUBIFY_CHECKOUT_TENANT_SHARED_CACHE_KEYS', ''), // comma-separated
        ],

        'features' => [
            'tenant_switching' => env('CLUBIFY_CHECKOUT_TENANT_SWITCHING_ENABLED', true),
            'cross_tenant_operations' => env('CLUBIFY_CHECKOUT_CROSS_TENANT_OPERATIONS', false),
            'tenant_analytics' => env('CLUBIFY_CHECKOUT_TENANT_ANALYTICS', true),
            'resource_quotas' => env('CLUBIFY_CHECKOUT_TENANT_RESOURCE_QUOTAS', true),
        ],

        'default_configuration' => [
            'plan' => env('CLUBIFY_CHECKOUT_DEFAULT_TENANT_PLAN', 'basic'),
            'features' => [
                'checkout',
                'payments',
                'customers',
            ],
            'limits' => [
                'max_products' => env('CLUBIFY_CHECKOUT_DEFAULT_MAX_PRODUCTS', 100),
                'max_orders_per_month' => env('CLUBIFY_CHECKOUT_DEFAULT_MAX_ORDERS', 1000),
                'max_users' => env('CLUBIFY_CHECKOUT_DEFAULT_MAX_USERS', 10),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Desenvolvimento
    |--------------------------------------------------------------------------
    |
    | Configurações específicas para ambiente de desenvolvimento
    |
    */

    'development' => [
        'mock_api' => env('CLUBIFY_CHECKOUT_MOCK_API', false),
        'simulate_errors' => env('CLUBIFY_CHECKOUT_SIMULATE_ERRORS', false),
        'debug_requests' => env('CLUBIFY_CHECKOUT_DEBUG_REQUESTS', false),
        'profiling' => env('CLUBIFY_CHECKOUT_PROFILING', false),
    ],
];