<?php

/**
 * Configurações para Gestão de Credenciais
 *
 * Este arquivo define as configurações padrão para a gestão automatizada
 * de credenciais de tenant no exemplo Laravel.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Gestão Automatizada de API Keys
    |--------------------------------------------------------------------------
    |
    | Controla se o sistema deve automaticamente criar, rotacionar e gerenciar
    | as chaves de API dos tenants.
    |
    */

    'example_enable_key_rotation' => env('CLUBIFY_ENABLE_KEY_ROTATION', true),
    'auto_create_api_keys' => env('CLUBIFY_AUTO_CREATE_API_KEYS', true),
    'force_key_rotation' => env('CLUBIFY_FORCE_KEY_ROTATION', false),

    /*
    |--------------------------------------------------------------------------
    | Políticas de Idade da Chave
    |--------------------------------------------------------------------------
    |
    | Define quando uma chave de API é considerada "antiga" e precisa ser
    | rotacionada automaticamente.
    |
    */

    'max_api_key_age_days' => env('CLUBIFY_MAX_API_KEY_AGE_DAYS', 90),
    'key_rotation_grace_period' => env('CLUBIFY_KEY_ROTATION_GRACE_PERIOD', 24),

    /*
    |--------------------------------------------------------------------------
    | Configurações de Segurança
    |--------------------------------------------------------------------------
    |
    | Configurações relacionadas à segurança das chaves de API.
    |
    */

    'ip_whitelist' => env('CLUBIFY_IP_WHITELIST', null), // Comma-separated IPs
    'allowed_origins' => env('CLUBIFY_ALLOWED_ORIGINS', '*'), // Comma-separated origins

    /*
    |--------------------------------------------------------------------------
    | Rate Limits Padrão
    |--------------------------------------------------------------------------
    |
    | Limites de taxa padrão para novas chaves de API criadas automaticamente.
    |
    */

    'default_rate_limits' => [
        'requests_per_minute' => env('CLUBIFY_RATE_LIMIT_PER_MINUTE', 1000),
        'requests_per_hour' => env('CLUBIFY_RATE_LIMIT_PER_HOUR', 50000),
        'requests_per_day' => env('CLUBIFY_RATE_LIMIT_PER_DAY', 1000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissões Padrão
    |--------------------------------------------------------------------------
    |
    | Permissões padrão atribuídas a novas chaves de tenant_admin.
    |
    */

    'default_permissions' => [
        'tenants' => ['read', 'write', 'delete'],
        'users' => ['read', 'write', 'delete'],
        'orders' => ['read', 'write', 'cancel', 'refund'],
        'products' => ['read', 'write', 'delete', 'publish'],
        'payments' => ['process', 'refund', 'view', 'export'],
        'analytics' => ['view', 'export', 'configure'],
        'webhooks' => ['read', 'write', 'delete', 'test'],
        'api_keys' => ['read', 'write', 'rotate'],
        'settings' => ['read', 'write', 'configure']
    ],

    /*
    |--------------------------------------------------------------------------
    | Scopes Padrão
    |--------------------------------------------------------------------------
    |
    | Escopos padrão para novas chaves de API de tenant.
    |
    */

    'default_scopes' => [
        'tenant:admin',
        'api:full',
        'webhooks:manage',
        'analytics:read',
        'payments:process'
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Log
    |--------------------------------------------------------------------------
    |
    | Controla o nível de detalhamento dos logs de gestão de credenciais.
    |
    */

    'log_credential_operations' => env('CLUBIFY_LOG_CREDENTIAL_OPS', true),
    'log_api_key_usage' => env('CLUBIFY_LOG_API_KEY_USAGE', false),
    'mask_sensitive_data' => env('CLUBIFY_MASK_SENSITIVE_DATA', true),

    /*
    |--------------------------------------------------------------------------
    | Configurações de Cache
    |--------------------------------------------------------------------------
    |
    | Cache para operações de credenciais para melhorar performance.
    |
    */

    'cache_credentials' => env('CLUBIFY_CACHE_CREDENTIALS', true),
    'cache_ttl_minutes' => env('CLUBIFY_CACHE_TTL_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Configurações de Backup
    |--------------------------------------------------------------------------
    |
    | Configurações para backup de chaves antes da rotação.
    |
    */

    'backup_old_keys' => env('CLUBIFY_BACKUP_OLD_KEYS', true),
    'backup_retention_days' => env('CLUBIFY_BACKUP_RETENTION_DAYS', 7),

];