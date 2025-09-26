<?php

declare(strict_types=1);

/**
 * Script de Pós-Instalação do Clubify Checkout SDK
 *
 * Executado automaticamente após a instalação/atualização via Composer.
 * Configura automaticamente arquivos de configuração e templates de ambiente.
 */

// Verificar se estamos executando via Composer
$isComposerInstall = isset($_SERVER['COMPOSER_BINARY']) ||
                     (isset($argv[0]) && str_contains($argv[0], 'composer'));

// Configurações
$rootDir = dirname(__DIR__);
$vendorDir = $rootDir . '/vendor';
$configDir = $rootDir . '/config';
$scriptsDir = $rootDir . '/scripts';

// Função para log com cores
function logMessage(string $message, string $type = 'info'): void
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

// Detectar ambiente de instalação
function detectInstallationEnvironment(): string
{
    global $rootDir, $vendorDir;

    // Se tem vendor/autoload.php no mesmo diretório, é instalação standalone
    if (file_exists($rootDir . '/vendor/autoload.php')) {
        return 'standalone';
    }

    // Se estamos em vendor/clubify/checkout-sdk-php, é dependência
    if (str_contains($rootDir, 'vendor/clubify/checkout-sdk-php')) {
        return 'dependency';
    }

    // Se tem Laravel, é projeto Laravel
    if (file_exists(dirname($rootDir) . '/artisan') ||
        file_exists(dirname(dirname($rootDir)) . '/artisan')) {
        return 'laravel';
    }

    return 'unknown';
}

// Configurar para diferentes tipos de projetos
function setupForEnvironment(string $environment): void
{
    switch ($environment) {
        case 'laravel':
            setupLaravelProject();
            break;
        case 'standalone':
            setupStandaloneProject();
            break;
        case 'dependency':
            setupDependencyProject();
            break;
        default:
            setupGenericProject();
            break;
    }
}

// Setup para projeto Laravel
function setupLaravelProject(): void
{
    global $rootDir;

    logMessage("Detectado projeto Laravel", 'info');

    // Encontrar diretório raiz do Laravel
    $laravelRoot = dirname($rootDir);
    if (!file_exists($laravelRoot . '/artisan')) {
        $laravelRoot = dirname(dirname($rootDir));
    }

    if (!file_exists($laravelRoot . '/artisan')) {
        logMessage("Não foi possível encontrar diretório raiz do Laravel", 'warning');
        return;
    }

    // Publicar configurações do Laravel
    publishLaravelConfig($laravelRoot);
    publishLaravelEnvTemplate($laravelRoot);
    publishLaravelServiceProvider($laravelRoot);

    logMessage("Configuração Laravel concluída!", 'success');
    logMessage("Execute: php artisan vendor:publish --tag=clubify-config", 'info');
}

// Setup para projeto standalone
function setupStandaloneProject(): void
{
    global $rootDir, $configDir;

    logMessage("Detectado projeto standalone", 'info');

    // Criar diretórios necessários
    createDirectory($configDir);
    createDirectory($rootDir . '/storage/logs');
    createDirectory($rootDir . '/storage/cache');

    // Copiar arquivos de configuração
    copyConfigurationFiles($rootDir);
    copyEnvironmentTemplate($rootDir);

    logMessage("Configuração standalone concluída!", 'success');
}

// Setup para dependência em outro projeto
function setupDependencyProject(): void
{
    logMessage("Instalado como dependência", 'info');
    logMessage("Use 'composer run clubify:config' para configurar", 'info');
}

// Setup genérico
function setupGenericProject(): void
{
    global $rootDir;

    logMessage("Ambiente não detectado, usando configuração genérica", 'warning');
    copyConfigurationFiles($rootDir);
    copyEnvironmentTemplate($rootDir);
}

// Publicar configuração para Laravel
function publishLaravelConfig(string $laravelRoot): void
{
    $configPath = $laravelRoot . '/config/clubify.php';
    $sourceConfig = dirname(__DIR__) . '/src/Laravel/config/clubify.php';

    if (!file_exists($sourceConfig)) {
        createLaravelConfigFile($configPath);
    } else {
        copy($sourceConfig, $configPath);
    }

    logMessage("Configuração Laravel publicada: config/clubify.php", 'success');
}

// Criar arquivo de configuração Laravel
function createLaravelConfigFile(string $configPath): void
{
    $config = <<<'PHP'
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clubify Checkout Configuration
    |--------------------------------------------------------------------------
    |
    | Configuração principal do SDK Clubify Checkout para Laravel.
    |
    */

    'api_key' => env('CLUBIFY_API_KEY'),
    'base_url' => env('CLUBIFY_BASE_URL', 'https://checkout.svelve.com'),
    'environment' => env('CLUBIFY_ENVIRONMENT', 'production'),
    'verify_ssl' => env('CLUBIFY_VERIFY_SSL', true),
    'timeout' => env('CLUBIFY_TIMEOUT', 30),
    'debug' => env('CLUBIFY_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Gestão de Credenciais
    |--------------------------------------------------------------------------
    */

    'credentials' => [
        'auto_create_api_keys' => env('CLUBIFY_AUTO_CREATE_API_KEYS', true),
        'enable_key_rotation' => env('CLUBIFY_ENABLE_KEY_ROTATION', true),
        'max_api_key_age_days' => env('CLUBIFY_MAX_API_KEY_AGE_DAYS', 90),
        'key_rotation_grace_period' => env('CLUBIFY_KEY_ROTATION_GRACE_PERIOD', 24),
        'force_key_rotation' => env('CLUBIFY_FORCE_KEY_ROTATION', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('CLUBIFY_CACHE_ENABLED', true),
        'ttl' => env('CLUBIFY_CACHE_TTL', 3600),
        'prefix' => env('CLUBIFY_CACHE_PREFIX', 'clubify:'),
        'store' => env('CLUBIFY_CACHE_STORE', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('CLUBIFY_LOG_ENABLED', true),
        'level' => env('CLUBIFY_LOG_LEVEL', 'info'),
        'channel' => env('CLUBIFY_LOG_CHANNEL', 'default'),
        'log_requests' => env('CLUBIFY_LOG_REQUESTS', false),
        'mask_sensitive_data' => env('CLUBIFY_MASK_SENSITIVE_DATA', true),
    ],

];
PHP;

    file_put_contents($configPath, $config);
}

// Publicar template de ambiente para Laravel
function publishLaravelEnvTemplate(string $laravelRoot): void
{
    $envExample = $laravelRoot . '/.env.example';
    $envClubify = $laravelRoot . '/.env.clubify.example';

    // Criar template específico do Clubify
    $clubifyEnvContent = <<<'ENV'
# =========================================
# CLUBIFY CHECKOUT SDK CONFIGURATION
# =========================================

# API Configuration
CLUBIFY_API_KEY=your-api-key-here
CLUBIFY_BASE_URL=https://checkout.svelve.com
CLUBIFY_ENVIRONMENT=production
CLUBIFY_VERIFY_SSL=true
CLUBIFY_TIMEOUT=30
CLUBIFY_DEBUG=false

# Credentials Management
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
CLUBIFY_MAX_API_KEY_AGE_DAYS=90
CLUBIFY_KEY_ROTATION_GRACE_PERIOD=24
CLUBIFY_FORCE_KEY_ROTATION=false

# Cache Configuration
CLUBIFY_CACHE_ENABLED=true
CLUBIFY_CACHE_TTL=3600
CLUBIFY_CACHE_PREFIX=clubify:
CLUBIFY_CACHE_STORE=redis

# Logging Configuration
CLUBIFY_LOG_ENABLED=true
CLUBIFY_LOG_LEVEL=info
CLUBIFY_LOG_CHANNEL=daily
CLUBIFY_LOG_REQUESTS=false
CLUBIFY_MASK_SENSITIVE_DATA=true

# Rate Limiting
CLUBIFY_RATE_LIMIT_PER_MINUTE=1000
CLUBIFY_RATE_LIMIT_PER_HOUR=50000
CLUBIFY_RATE_LIMIT_PER_DAY=1000000

# Security
CLUBIFY_IP_WHITELIST=
CLUBIFY_ALLOWED_ORIGINS=*
ENV;

    file_put_contents($envClubify, $clubifyEnvContent);

    // Adicionar ao .env.example principal se existir
    if (file_exists($envExample)) {
        $existingContent = file_get_contents($envExample);
        if (!str_contains($existingContent, 'CLUBIFY_')) {
            file_put_contents($envExample, $existingContent . "\n\n" . $clubifyEnvContent);
        }
    }

    logMessage("Templates de ambiente criados", 'success');
}

// Publicar Service Provider para Laravel
function publishLaravelServiceProvider(string $laravelRoot): void
{
    // O Service Provider já é registrado automaticamente via composer.json
    logMessage("Service Provider registrado automaticamente", 'info');
}

// Copiar arquivos de configuração
function copyConfigurationFiles(string $targetDir): void
{
    $configDir = $targetDir . '/config';
    createDirectory($configDir);

    // Copiar configuração de credenciais
    $credentialsConfig = $configDir . '/clubify-credentials.php';
    $sourceCredentials = dirname(__DIR__) . '/examples/example-app/config/credentials.php';

    if (file_exists($sourceCredentials)) {
        copy($sourceCredentials, $credentialsConfig);
        logMessage("Configuração de credenciais copiada", 'success');
    } else {
        createCredentialsConfigFile($credentialsConfig);
        logMessage("Configuração de credenciais criada", 'success');
    }
}

// Criar arquivo de configuração de credenciais
function createCredentialsConfigFile(string $configPath): void
{
    $config = <<<'PHP'
<?php

/**
 * Configurações de Gestão de Credenciais
 * Clubify Checkout SDK
 */

return [
    'auto_create_api_keys' => true,
    'enable_key_rotation' => true,
    'max_api_key_age_days' => 90,
    'key_rotation_grace_period' => 24,
    'force_key_rotation' => false,

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

    'default_rate_limits' => [
        'requests_per_minute' => 1000,
        'requests_per_hour' => 50000,
        'requests_per_day' => 1000000,
    ],
];
PHP;

    file_put_contents($configPath, $config);
}

// Copiar template de ambiente
function copyEnvironmentTemplate(string $targetDir): void
{
    $envTemplate = $targetDir . '/.env.clubify.example';
    $sourceEnv = dirname(__DIR__) . '/examples/example-app/.env.credentials.example';

    if (file_exists($sourceEnv)) {
        copy($sourceEnv, $envTemplate);
    } else {
        createEnvironmentTemplate($envTemplate);
    }

    logMessage("Template de ambiente criado: .env.clubify.example", 'success');
}

// Criar template de ambiente
function createEnvironmentTemplate(string $envPath): void
{
    $content = <<<'ENV'
# Clubify Checkout SDK Configuration
CLUBIFY_API_KEY=your-api-key-here
CLUBIFY_BASE_URL=https://checkout.svelve.com
CLUBIFY_ENVIRONMENT=production

# Credentials Management
CLUBIFY_AUTO_CREATE_API_KEYS=true
CLUBIFY_ENABLE_KEY_ROTATION=true
CLUBIFY_MAX_API_KEY_AGE_DAYS=90

# Cache and Logging
CLUBIFY_CACHE_ENABLED=true
CLUBIFY_LOG_ENABLED=true
CLUBIFY_DEBUG=false
ENV;

    file_put_contents($envPath, $content);
}

// Criar diretório se não existir
function createDirectory(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Função principal
function main(): void
{
    global $isComposerInstall;

    logMessage("🚀 Iniciando configuração pós-instalação do Clubify Checkout SDK", 'info');

    // Detectar ambiente
    $environment = detectInstallationEnvironment();
    logMessage("Ambiente detectado: {$environment}", 'info');

    // Configurar baseado no ambiente
    setupForEnvironment($environment);

    // Mensagens finais
    logMessage("✅ Configuração concluída com sucesso!", 'success');

    if ($environment === 'laravel') {
        logMessage("", 'info');
        logMessage("📋 Próximos passos para Laravel:", 'info');
        logMessage("1. Configure suas variáveis no .env", 'info');
        logMessage("2. Execute: php artisan config:cache", 'info');
        logMessage("3. Execute: php artisan cache:clear", 'info');
    } else {
        logMessage("", 'info');
        logMessage("📋 Próximos passos:", 'info');
        logMessage("1. Copie .env.clubify.example para .env", 'info');
        logMessage("2. Configure suas credenciais", 'info');
        logMessage("3. Teste a integração", 'info');
    }

    logMessage("", 'info');
    logMessage("📚 Documentação: https://docs.clubify.com/sdk/php", 'info');
}

// Executar apenas se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        main();
    } catch (Throwable $e) {
        logMessage("Erro durante configuração: " . $e->getMessage(), 'error');
        exit(1);
    }
}