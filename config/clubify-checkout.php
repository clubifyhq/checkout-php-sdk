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
    | Valores aceitos: 'development', 'sandbox', 'staging', 'production'
    |
    */

    'environment' => env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'sandbox'),
    'base_url' => env('CLUBIFY_CHECKOUT_BASE_URL', env('CLUBIFY_CHECKOUT_ENVIRONMENT', 'sandbox') === 'sandbox' ? 'https://sandbox.svelve.com/api/v1' : 'https://checkout.svelve.com/api/v1'),
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
    | Configurações de segurança e compliance
    |
    */

    'security' => [
        'encrypt_sensitive_data' => env('CLUBIFY_CHECKOUT_ENCRYPT_SENSITIVE', true),
        'mask_logs' => env('CLUBIFY_CHECKOUT_MASK_LOGS', true),
        'pci_compliance' => env('CLUBIFY_CHECKOUT_PCI_COMPLIANCE', true),
        'audit_logs' => env('CLUBIFY_CHECKOUT_AUDIT_LOGS', true),
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