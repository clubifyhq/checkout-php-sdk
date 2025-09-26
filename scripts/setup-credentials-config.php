<?php

declare(strict_types=1);

/**
 * Script de Configuração de Credenciais
 *
 * Configura automaticamente arquivos de configuração para gestão
 * de credenciais do Clubify Checkout SDK.
 */

// Configurações
$rootDir = dirname(__DIR__);
$configDir = $rootDir . '/config';

// Função para log com cores
function logStep(string $message, string $type = 'info'): void
{
    $colors = [
        'info' => "\033[36m",     // Cyan
        'success' => "\033[32m",  // Green
        'warning' => "\033[33m",  // Yellow
        'error' => "\033[31m",    // Red
        'reset' => "\033[0m"      // Reset
    ];

    $icon = match($type) {
        'success' => '✅',
        'warning' => '⚠️',
        'error' => '❌',
        'info' => 'ℹ️',
        default => '📝'
    };

    $color = $colors[$type] ?? $colors['info'];
    $reset = $colors['reset'];

    echo "{$color}{$icon} {$message}{$reset}\n";
}

// Detectar tipo de projeto
function detectProjectType(): string
{
    global $rootDir;

    // Laravel
    if (file_exists(dirname($rootDir) . '/artisan') ||
        file_exists(dirname(dirname($rootDir)) . '/artisan')) {
        return 'laravel';
    }

    // Standalone
    if (file_exists($rootDir . '/vendor/autoload.php')) {
        return 'standalone';
    }

    return 'generic';
}

// Configurar para Laravel
function setupLaravelCredentials(): void
{
    logStep("Configurando credenciais para projeto Laravel", 'info');

    // Encontrar diretório do Laravel
    $laravelRoot = findLaravelRoot();
    if (!$laravelRoot) {
        logStep("Não foi possível encontrar diretório raiz do Laravel", 'error');
        return;
    }

    // Criar configuração Laravel
    createLaravelCredentialsConfig($laravelRoot);
    updateLaravelEnv($laravelRoot);

    logStep("Configuração Laravel concluída!", 'success');
}

// Encontrar diretório raiz do Laravel
function findLaravelRoot(): ?string
{
    global $rootDir;

    $candidates = [
        dirname($rootDir),
        dirname(dirname($rootDir)),
        dirname(dirname(dirname($rootDir)))
    ];

    foreach ($candidates as $candidate) {
        if (file_exists($candidate . '/artisan')) {
            return $candidate;
        }
    }

    return null;
}

// Criar configuração de credenciais para Laravel
function createLaravelCredentialsConfig(string $laravelRoot): void
{
    $configPath = $laravelRoot . '/config/clubify-credentials.php';

    $config = <<<'PHP'
<?php

/**
 * Configuração de Gestão de Credenciais
 * Clubify Checkout SDK para Laravel
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Gestão Automatizada de Credenciais
    |--------------------------------------------------------------------------
    |
    | Controla se o SDK deve automaticamente criar e gerenciar credenciais
    | de API para tenants.
    |
    */

    'auto_create_api_keys' => env('CLUBIFY_AUTO_CREATE_API_KEYS', true),
    'enable_key_rotation' => env('CLUBIFY_ENABLE_KEY_ROTATION', true),
    'max_api_key_age_days' => env('CLUBIFY_MAX_API_KEY_AGE_DAYS', 90),
    'key_rotation_grace_period' => env('CLUBIFY_KEY_ROTATION_GRACE_PERIOD', 24),
    'force_key_rotation' => env('CLUBIFY_FORCE_KEY_ROTATION', false),

    /*
    |--------------------------------------------------------------------------
    | Permissões Padrão para Tenant Admin
    |--------------------------------------------------------------------------
    |
    | Permissões atribuídas automaticamente a novas chaves de tenant_admin.
    |
    */

    'default_tenant_permissions' => [
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
    | Rate Limits Padrão
    |--------------------------------------------------------------------------
    |
    | Limites de taxa para novas chaves de API.
    |
    */

    'default_rate_limits' => [
        'requests_per_minute' => env('CLUBIFY_RATE_LIMIT_PER_MINUTE', 1000),
        'requests_per_hour' => env('CLUBIFY_RATE_LIMIT_PER_HOUR', 50000),
        'requests_per_day' => env('CLUBIFY_RATE_LIMIT_PER_DAY', 1000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Segurança
    |--------------------------------------------------------------------------
    |
    | Configurações relacionadas à segurança das chaves de API.
    |
    */

    'security' => [
        'ip_whitelist' => env('CLUBIFY_IP_WHITELIST'),
        'allowed_origins' => explode(',', env('CLUBIFY_ALLOWED_ORIGINS', '*')),
        'require_https' => env('CLUBIFY_REQUIRE_HTTPS', true),
        'mask_sensitive_data' => env('CLUBIFY_MASK_SENSITIVE_DATA', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Cache
    |--------------------------------------------------------------------------
    |
    | Cache para operações de credenciais.
    |
    */

    'cache' => [
        'enabled' => env('CLUBIFY_CACHE_CREDENTIALS', true),
        'ttl_minutes' => env('CLUBIFY_CACHE_TTL_MINUTES', 30),
        'prefix' => env('CLUBIFY_CACHE_PREFIX', 'clubify:credentials:'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Backup
    |--------------------------------------------------------------------------
    |
    | Backup de chaves antes da rotação.
    |
    */

    'backup' => [
        'enabled' => env('CLUBIFY_BACKUP_OLD_KEYS', true),
        'retention_days' => env('CLUBIFY_BACKUP_RETENTION_DAYS', 7),
        'storage_path' => storage_path('app/clubify/backups'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações de Log
    |--------------------------------------------------------------------------
    |
    | Logs específicos para operações de credenciais.
    |
    */

    'logging' => [
        'log_credential_operations' => env('CLUBIFY_LOG_CREDENTIAL_OPS', true),
        'log_api_key_usage' => env('CLUBIFY_LOG_API_KEY_USAGE', false),
        'log_rotation_events' => env('CLUBIFY_LOG_ROTATION_EVENTS', true),
        'channel' => env('CLUBIFY_CREDENTIALS_LOG_CHANNEL', 'daily'),
    ],

];
PHP;

    file_put_contents($configPath, $config);
    logStep("Configuração criada: config/clubify-credentials.php", 'success');
}

// Atualizar .env do Laravel
function updateLaravelEnv(string $laravelRoot): void
{
    $envPath = $laravelRoot . '/.env';
    $envExamplePath = $laravelRoot . '/.env.example';

    $clubifyEnvVars = <<<'ENV'

# =========================================
# CLUBIFY CHECKOUT SDK - CREDENTIALS
# =========================================
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
CLUBIFY_MAX_API_KEY_AGE_DAYS=90
CLUBIFY_KEY_ROTATION_GRACE_PERIOD=24
CLUBIFY_FORCE_KEY_ROTATION=false

# Rate Limits
CLUBIFY_RATE_LIMIT_PER_MINUTE=1000
CLUBIFY_RATE_LIMIT_PER_HOUR=50000
CLUBIFY_RATE_LIMIT_PER_DAY=1000000

# Security
CLUBIFY_IP_WHITELIST=
CLUBIFY_ALLOWED_ORIGINS=*
CLUBIFY_REQUIRE_HTTPS=true
CLUBIFY_MASK_SENSITIVE_DATA=true

# Cache and Logging
CLUBIFY_CACHE_CREDENTIALS=true
CLUBIFY_CACHE_TTL_MINUTES=30
CLUBIFY_LOG_CREDENTIAL_OPS=true
CLUBIFY_LOG_API_KEY_USAGE=false
CLUBIFY_BACKUP_OLD_KEYS=true
ENV;

    // Adicionar ao .env.example
    if (file_exists($envExamplePath)) {
        $content = file_get_contents($envExamplePath);
        if (!str_contains($content, 'CLUBIFY_AUTO_CREATE_API_KEYS')) {
            file_put_contents($envExamplePath, $content . $clubifyEnvVars);
            logStep("Variáveis adicionadas ao .env.example", 'success');
        }
    }

    // Criar template específico
    $clubifyEnvPath = $laravelRoot . '/.env.clubify-credentials.example';
    file_put_contents($clubifyEnvPath, trim($clubifyEnvVars));
    logStep("Template criado: .env.clubify-credentials.example", 'success');
}

// Configurar para projeto standalone
function setupStandaloneCredentials(): void
{
    global $rootDir, $configDir;

    logStep("Configurando credenciais para projeto standalone", 'info');

    // Criar diretório de configuração
    if (!is_dir($configDir)) {
        mkdir($configDir, 0755, true);
    }

    // Criar configuração standalone
    createStandaloneCredentialsConfig();
    createStandaloneEnvTemplate();

    logStep("Configuração standalone concluída!", 'success');
}

// Criar configuração standalone de credenciais
function createStandaloneCredentialsConfig(): void
{
    global $configDir;

    $configPath = $configDir . '/credentials.php';

    $config = <<<'PHP'
<?php

/**
 * Configuração de Gestão de Credenciais
 * Clubify Checkout SDK - Standalone
 */

return [
    'auto_create_api_keys' => $_ENV['CLUBIFY_AUTO_CREATE_API_KEYS'] ?? true,
    'enable_key_rotation' => $_ENV['CLUBIFY_ENABLE_KEY_ROTATION'] ?? true,
    'max_api_key_age_days' => (int)($_ENV['CLUBIFY_MAX_API_KEY_AGE_DAYS'] ?? 90),
    'key_rotation_grace_period' => (int)($_ENV['CLUBIFY_KEY_ROTATION_GRACE_PERIOD'] ?? 24),
    'force_key_rotation' => (bool)($_ENV['CLUBIFY_FORCE_KEY_ROTATION'] ?? false),

    'default_tenant_permissions' => [
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

    'default_scopes' => [
        'tenant:admin',
        'api:full',
        'webhooks:manage',
        'analytics:read',
        'payments:process'
    ],

    'default_rate_limits' => [
        'requests_per_minute' => (int)($_ENV['CLUBIFY_RATE_LIMIT_PER_MINUTE'] ?? 1000),
        'requests_per_hour' => (int)($_ENV['CLUBIFY_RATE_LIMIT_PER_HOUR'] ?? 50000),
        'requests_per_day' => (int)($_ENV['CLUBIFY_RATE_LIMIT_PER_DAY'] ?? 1000000),
    ],

    'security' => [
        'ip_whitelist' => $_ENV['CLUBIFY_IP_WHITELIST'] ?? null,
        'allowed_origins' => explode(',', $_ENV['CLUBIFY_ALLOWED_ORIGINS'] ?? '*'),
        'require_https' => (bool)($_ENV['CLUBIFY_REQUIRE_HTTPS'] ?? true),
        'mask_sensitive_data' => (bool)($_ENV['CLUBIFY_MASK_SENSITIVE_DATA'] ?? true),
    ],

    'cache' => [
        'enabled' => (bool)($_ENV['CLUBIFY_CACHE_CREDENTIALS'] ?? true),
        'ttl_minutes' => (int)($_ENV['CLUBIFY_CACHE_TTL_MINUTES'] ?? 30),
        'prefix' => $_ENV['CLUBIFY_CACHE_PREFIX'] ?? 'clubify:credentials:',
    ],

    'logging' => [
        'log_credential_operations' => (bool)($_ENV['CLUBIFY_LOG_CREDENTIAL_OPS'] ?? true),
        'log_api_key_usage' => (bool)($_ENV['CLUBIFY_LOG_API_KEY_USAGE'] ?? false),
        'log_rotation_events' => (bool)($_ENV['CLUBIFY_LOG_ROTATION_EVENTS'] ?? true),
    ],
];
PHP;

    file_put_contents($configPath, $config);
    logStep("Configuração criada: config/credentials.php", 'success');
}

// Criar template de ambiente standalone
function createStandaloneEnvTemplate(): void
{
    global $rootDir;

    $envTemplatePath = $rootDir . '/.env.credentials.example';

    $envContent = <<<'ENV'
# =========================================
# CLUBIFY CHECKOUT SDK - CREDENTIALS
# =========================================

# Gestão Automatizada
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
CLUBIFY_MAX_API_KEY_AGE_DAYS=90
CLUBIFY_KEY_ROTATION_GRACE_PERIOD=24
CLUBIFY_FORCE_KEY_ROTATION=false

# Rate Limits
CLUBIFY_RATE_LIMIT_PER_MINUTE=1000
CLUBIFY_RATE_LIMIT_PER_HOUR=50000
CLUBIFY_RATE_LIMIT_PER_DAY=1000000

# Segurança
CLUBIFY_IP_WHITELIST=
CLUBIFY_ALLOWED_ORIGINS=*
CLUBIFY_REQUIRE_HTTPS=true
CLUBIFY_MASK_SENSITIVE_DATA=true

# Cache
CLUBIFY_CACHE_CREDENTIALS=true
CLUBIFY_CACHE_TTL_MINUTES=30
CLUBIFY_CACHE_PREFIX=clubify:credentials:

# Logging
CLUBIFY_LOG_CREDENTIAL_OPS=true
CLUBIFY_LOG_API_KEY_USAGE=false
CLUBIFY_LOG_ROTATION_EVENTS=true

# Backup
CLUBIFY_BACKUP_OLD_KEYS=true
CLUBIFY_BACKUP_RETENTION_DAYS=7
ENV;

    file_put_contents($envTemplatePath, $envContent);
    logStep("Template criado: .env.credentials.example", 'success');
}

// Função principal
function main(): void
{
    logStep("🔑 Configurando gestão de credenciais do Clubify SDK", 'info');

    $projectType = detectProjectType();
    logStep("Tipo de projeto detectado: {$projectType}", 'info');

    switch ($projectType) {
        case 'laravel':
            setupLaravelCredentials();
            break;
        case 'standalone':
            setupStandaloneCredentials();
            break;
        default:
            setupStandaloneCredentials(); // Fallback
            break;
    }

    logStep("✅ Configuração de credenciais concluída!", 'success');
    logStep("", 'info');
    logStep("📋 Próximos passos:", 'info');
    logStep("1. Configure suas variáveis de ambiente", 'info');
    logStep("2. Teste a gestão automatizada de credenciais", 'info');
    logStep("3. Execute seu exemplo de aplicação", 'info');
}

// Executar se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        main();
    } catch (Throwable $e) {
        logStep("Erro: " . $e->getMessage(), 'error');
        exit(1);
    }
}