<?php

declare(strict_types=1);

/**
 * Gerador de Configuração de Ambiente
 *
 * Gera templates de configuração de ambiente personalizados
 * baseados no tipo de projeto e necessidades específicas.
 */

// Função para log
function logMessage(string $message, string $type = 'info'): void
{
    $colors = [
        'info' => "\033[36m",
        'success' => "\033[32m",
        'warning' => "\033[33m",
        'error' => "\033[31m",
        'reset' => "\033[0m"
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

// Obter input do usuário
function getUserInput(string $prompt, string $default = ''): string
{
    $defaultText = $default ? " [{$default}]" : '';
    echo "❓ {$prompt}{$defaultText}: ";

    $input = trim(fgets(STDIN));
    return $input ?: $default;
}

// Confirmar com usuário
function confirmAction(string $message): bool
{
    echo "❓ {$message} (y/N): ";
    $input = trim(fgets(STDIN));
    return in_array(strtolower($input), ['y', 'yes', 'sim', 's']);
}

// Detectar tipo de projeto
function detectProjectType(): string
{
    $rootDir = dirname(__DIR__);

    if (file_exists(dirname($rootDir) . '/artisan') ||
        file_exists(dirname(dirname($rootDir)) . '/artisan')) {
        return 'laravel';
    }

    if (file_exists($rootDir . '/vendor/autoload.php')) {
        return 'standalone';
    }

    return 'generic';
}

// Coletar configurações do usuário
function collectUserConfiguration(): array
{
    logMessage("Coletando informações de configuração...", 'info');

    $config = [];

    // Configurações básicas
    $config['api_key'] = getUserInput("API Key do Clubify", "your-api-key-here");
    $config['base_url'] = getUserInput("URL Base da API", "https://checkout.svelve.com");
    $config['environment'] = getUserInput("Ambiente (development/staging/production)", "production");

    // Configurações de credenciais
    logMessage("\nConfigurações de Gestão de Credenciais:", 'info');
    $config['auto_create_keys'] = confirmAction("Habilitar criação automática de chaves de API?");
    $config['enable_rotation'] = confirmAction("Habilitar rotação automática de chaves?");

    if ($config['enable_rotation']) {
        $config['max_key_age'] = getUserInput("Idade máxima da chave (em dias)", "90");
        $config['grace_period'] = getUserInput("Período de graça para rotação (em horas)", "24");
    }

    // Configurações de cache
    logMessage("\nConfigurações de Cache:", 'info');
    $config['cache_enabled'] = confirmAction("Habilitar cache?");
    if ($config['cache_enabled']) {
        $config['cache_ttl'] = getUserInput("TTL do cache (em minutos)", "30");
        $config['cache_prefix'] = getUserInput("Prefixo do cache", "clubify:");
    }

    // Configurações de logging
    logMessage("\nConfigurações de Logging:", 'info');
    $config['log_enabled'] = confirmAction("Habilitar logging?");
    if ($config['log_enabled']) {
        $config['log_level'] = getUserInput("Nível de log (debug/info/warning/error)", "info");
        $config['log_requests'] = confirmAction("Logar requisições HTTP?");
    }

    // Configurações de segurança
    logMessage("\nConfigurações de Segurança:", 'info');
    $config['verify_ssl'] = confirmAction("Verificar certificados SSL?");
    $config['mask_sensitive'] = confirmAction("Mascarar dados sensíveis nos logs?");

    $config['ip_whitelist'] = getUserInput("IP Whitelist (separado por vírgulas, vazio para permitir todos)", "");
    $config['allowed_origins'] = getUserInput("Origins permitidas (separadas por vírgulas)", "*");

    return $config;
}

// Gerar configuração para Laravel
function generateLaravelEnv(array $config): string
{
    $env = "# =========================================\n";
    $env .= "# CLUBIFY CHECKOUT SDK - CONFIGURAÇÃO\n";
    $env .= "# =========================================\n\n";
    $env .= "# Configurações Principais\n";
    $env .= "CLUBIFY_API_KEY={$config['api_key']}\n";
    $env .= "CLUBIFY_BASE_URL={$config['base_url']}\n";
    $env .= "CLUBIFY_ENVIRONMENT={$config['environment']}\n";
    $env .= "CLUBIFY_VERIFY_SSL=" . ($config['verify_ssl'] ? 'true' : 'false') . "\n";
    $env .= "CLUBIFY_DEBUG=false\n\n";
    $env .= "# Gestão de Credenciais\n";
    $env .= "CLUBIFY_AUTO_CREATE_API_KEYS=" . ($config['auto_create_keys'] ? 'true' : 'false') . "\n";
    $env .= "CLUBIFY_ENABLE_KEY_ROTATION=" . ($config['enable_rotation'] ? 'true' : 'false') . "\n";

    if ($config['enable_rotation']) {
        $env .= "\nCLUBIFY_MAX_API_KEY_AGE_DAYS={$config['max_key_age']}";
        $env .= "\nCLUBIFY_KEY_ROTATION_GRACE_PERIOD={$config['grace_period']}";
    }

    if ($config['cache_enabled']) {
        $env .= "\n\n# Cache";
        $env .= "\nCLUBIFY_CACHE_ENABLED=true";
        $env .= "\nCLUBIFY_CACHE_TTL={$config['cache_ttl']}";
        $env .= "\nCLUBIFY_CACHE_PREFIX={$config['cache_prefix']}";
        $env .= "\nCLUBIFY_CACHE_STORE=redis";
    }

    if ($config['log_enabled']) {
        $env .= "\n\n# Logging";
        $env .= "\nCLUBIFY_LOG_ENABLED=true";
        $env .= "\nCLUBIFY_LOG_LEVEL={$config['log_level']}";
        $env .= "\nCLUBIFY_LOG_CHANNEL=daily";
        $env .= "\nCLUBIFY_LOG_REQUESTS=" . ($config['log_requests'] ? 'true' : 'false');
    }

    $env .= "\n\n# Segurança";
    $env .= "\nCLUBIFY_MASK_SENSITIVE_DATA=" . ($config['mask_sensitive'] ? 'true' : 'false');

    if ($config['ip_whitelist']) {
        $env .= "\nCLUBIFY_IP_WHITELIST={$config['ip_whitelist']}";
    }

    $env .= "\nCLUBIFY_ALLOWED_ORIGINS={$config['allowed_origins']}";

    $env .= "\n\n# Rate Limits";
    $env .= "\nCLUBIFY_RATE_LIMIT_PER_MINUTE=1000";
    $env .= "\nCLUBIFY_RATE_LIMIT_PER_HOUR=50000";
    $env .= "\nCLUBIFY_RATE_LIMIT_PER_DAY=1000000";

    return $env;
}

// Gerar configuração standalone
function generateStandaloneEnv(array $config): string
{
    return generateLaravelEnv($config); // Mesmo formato
}

// Gerar arquivo de configuração PHP
function generatePhpConfig(array $config): string
{
    $php = <<<'PHP'
<?php

/**
 * Configuração Personalizada do Clubify Checkout SDK
 * Gerada automaticamente
 */

return [
    'api_key' => $_ENV['CLUBIFY_API_KEY'] ?? 'API_KEY_PLACEHOLDER',
    'base_url' => $_ENV['CLUBIFY_BASE_URL'] ?? 'BASE_URL_PLACEHOLDER',
    'environment' => $_ENV['CLUBIFY_ENVIRONMENT'] ?? 'ENVIRONMENT_PLACEHOLDER',
    'verify_ssl' => (bool)($_ENV['CLUBIFY_VERIFY_SSL'] ?? true),
    'debug' => (bool)($_ENV['CLUBIFY_DEBUG'] ?? false),

    'credentials' => [
        'auto_create_api_keys' => (bool)($_ENV['CLUBIFY_AUTO_CREATE_API_KEYS'] ?? AUTO_CREATE_PLACEHOLDER),
        'enable_key_rotation' => (bool)($_ENV['CLUBIFY_ENABLE_KEY_ROTATION'] ?? ENABLE_ROTATION_PLACEHOLDER),
        'max_api_key_age_days' => (int)($_ENV['CLUBIFY_MAX_API_KEY_AGE_DAYS'] ?? MAX_AGE_PLACEHOLDER),
        'key_rotation_grace_period' => (int)($_ENV['CLUBIFY_KEY_ROTATION_GRACE_PERIOD'] ?? GRACE_PERIOD_PLACEHOLDER),
    ],

    'cache' => [
        'enabled' => (bool)($_ENV['CLUBIFY_CACHE_ENABLED'] ?? CACHE_ENABLED_PLACEHOLDER),
        'ttl' => (int)($_ENV['CLUBIFY_CACHE_TTL'] ?? CACHE_TTL_PLACEHOLDER),
        'prefix' => $_ENV['CLUBIFY_CACHE_PREFIX'] ?? 'CACHE_PREFIX_PLACEHOLDER',
    ],

    'logging' => [
        'enabled' => (bool)($_ENV['CLUBIFY_LOG_ENABLED'] ?? LOG_ENABLED_PLACEHOLDER),
        'level' => $_ENV['CLUBIFY_LOG_LEVEL'] ?? 'LOG_LEVEL_PLACEHOLDER',
        'log_requests' => (bool)($_ENV['CLUBIFY_LOG_REQUESTS'] ?? LOG_REQUESTS_PLACEHOLDER),
    ],

    'security' => [
        'mask_sensitive_data' => (bool)($_ENV['CLUBIFY_MASK_SENSITIVE_DATA'] ?? MASK_SENSITIVE_PLACEHOLDER),
        'ip_whitelist' => $_ENV['CLUBIFY_IP_WHITELIST'] ?? null,
        'allowed_origins' => explode(',', $_ENV['CLUBIFY_ALLOWED_ORIGINS'] ?? '*'),
    ],
];
PHP;

    // Substituir placeholders
    $replacements = [
        'API_KEY_PLACEHOLDER' => "'{$config['api_key']}'",
        'BASE_URL_PLACEHOLDER' => "'{$config['base_url']}'",
        'ENVIRONMENT_PLACEHOLDER' => "'{$config['environment']}'",
        'AUTO_CREATE_PLACEHOLDER' => $config['auto_create_keys'] ? 'true' : 'false',
        'ENABLE_ROTATION_PLACEHOLDER' => $config['enable_rotation'] ? 'true' : 'false',
        'MAX_AGE_PLACEHOLDER' => $config['max_key_age'] ?? '90',
        'GRACE_PERIOD_PLACEHOLDER' => $config['grace_period'] ?? '24',
        'CACHE_ENABLED_PLACEHOLDER' => $config['cache_enabled'] ? 'true' : 'false',
        'CACHE_TTL_PLACEHOLDER' => $config['cache_ttl'] ?? '30',
        'CACHE_PREFIX_PLACEHOLDER' => $config['cache_prefix'] ?? 'clubify:',
        'LOG_ENABLED_PLACEHOLDER' => $config['log_enabled'] ? 'true' : 'false',
        'LOG_LEVEL_PLACEHOLDER' => $config['log_level'] ?? 'info',
        'LOG_REQUESTS_PLACEHOLDER' => $config['log_requests'] ? 'true' : 'false',
        'MASK_SENSITIVE_PLACEHOLDER' => $config['mask_sensitive'] ? 'true' : 'false',
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $php);
}

// Salvar arquivos de configuração
function saveConfiguration(string $projectType, array $config): void
{
    $rootDir = dirname(__DIR__);

    // Gerar .env
    $envContent = match($projectType) {
        'laravel' => generateLaravelEnv($config),
        default => generateStandaloneEnv($config)
    };

    // Salvar .env personalizado
    $envPath = $rootDir . '/.env.clubify.custom';
    file_put_contents($envPath, $envContent);
    logMessage("Configuração de ambiente salva: .env.clubify.custom", 'success');

    // Gerar arquivo PHP de configuração
    $phpConfig = generatePhpConfig($config);
    $configPath = $rootDir . '/config/clubify.custom.php';

    // Criar diretório se não existir
    if (!is_dir(dirname($configPath))) {
        mkdir(dirname($configPath), 0755, true);
    }

    file_put_contents($configPath, $phpConfig);
    logMessage("Configuração PHP salva: config/clubify.custom.php", 'success');

    // Para Laravel, criar também na pasta do projeto
    if ($projectType === 'laravel') {
        $laravelRoot = findLaravelRoot();
        if ($laravelRoot) {
            $laravelEnvPath = $laravelRoot . '/.env.clubify.generated';
            file_put_contents($laravelEnvPath, $envContent);
            logMessage("Configuração Laravel salva: .env.clubify.generated", 'success');
        }
    }
}

// Encontrar diretório raiz do Laravel
function findLaravelRoot(): ?string
{
    $rootDir = dirname(__DIR__);

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

// Função principal
function main(): void
{
    logMessage("🔧 Gerador de Configuração de Ambiente - Clubify SDK", 'info');

    $projectType = detectProjectType();
    logMessage("Tipo de projeto: {$projectType}", 'info');

    // Modo interativo vs argumentos
    $interactive = !isset($GLOBALS['argv'][1]) || $GLOBALS['argv'][1] !== '--non-interactive';

    if ($interactive) {
        logMessage("\nModo interativo ativado. Responda as perguntas para personalizar a configuração.\n", 'info');
        $config = collectUserConfiguration();
    } else {
        logMessage("Usando configurações padrão...", 'info');
        $config = [
            'api_key' => 'your-api-key-here',
            'base_url' => 'https://checkout.svelve.com',
            'environment' => 'production',
            'auto_create_keys' => true,
            'enable_rotation' => true,
            'max_key_age' => '90',
            'grace_period' => '24',
            'cache_enabled' => true,
            'cache_ttl' => '30',
            'cache_prefix' => 'clubify:',
            'log_enabled' => true,
            'log_level' => 'info',
            'log_requests' => false,
            'verify_ssl' => true,
            'mask_sensitive' => true,
            'ip_whitelist' => '',
            'allowed_origins' => '*'
        ];
    }

    // Salvar configuração
    saveConfiguration($projectType, $config);

    logMessage("\n✅ Configuração gerada com sucesso!", 'success');
    logMessage("\n📋 Próximos passos:", 'info');
    logMessage("1. Revise os arquivos de configuração gerados", 'info');
    logMessage("2. Copie as variáveis para seu .env principal", 'info');
    logMessage("3. Ajuste as configurações conforme necessário", 'info');
    logMessage("4. Teste a integração", 'info');
}

// Executar se chamado diretamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    try {
        main();
    } catch (Throwable $e) {
        logMessage("Erro: " . $e->getMessage(), 'error');
        exit(1);
    }
}