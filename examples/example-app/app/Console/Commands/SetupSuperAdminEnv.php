<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SetupSuperAdminEnv extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'super-admin:env-setup
                           {--force : Force update existing values}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup Super Admin environment variables in .env file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔧 Configurando variáveis de ambiente do Super Admin...');
        $this->newLine();

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $this->error('.env file não encontrado!');

            if ($this->confirm('Deseja copiar do .env.example?')) {
                if (file_exists(base_path('.env.example'))) {
                    copy(base_path('.env.example'), $envPath);
                    $this->info('✅ .env criado a partir do .env.example');
                } else {
                    $this->error('.env.example também não encontrado!');
                    return 1;
                }
            } else {
                return 1;
            }
        }

        $envContent = file_get_contents($envPath);

        // Super Admin environment variables
        $envVars = [
            'SUPER_ADMIN_ENABLED' => 'true',
            'SUPER_ADMIN_DEFAULT_TENANT' => 'default',
            'SUPER_ADMIN_SESSION_TIMEOUT' => '3600',
            'SUPER_ADMIN_MAX_CONCURRENT_SESSIONS' => '5',
            'SUPER_ADMIN_LOG_LEVEL' => 'info',
            'SUPER_ADMIN_CACHE_TTL' => '1800',
            'SUPER_ADMIN_DEBUG' => 'false',
            'SUPER_ADMIN_JWT_SECRET' => Str::random(64),
            'SUPER_ADMIN_JWT_TTL' => '3600',
            'SUPER_ADMIN_JWT_REFRESH_TTL' => '604800',
            'SUPER_ADMIN_JWT_BLACKLIST_ENABLED' => 'true',
            'SUPER_ADMIN_JWT_BLACKLIST_GRACE_PERIOD' => '30',
            'SUPER_ADMIN_API_PREFIX' => '',
            'SUPER_ADMIN_API_MIDDLEWARE' => 'api,auth.super_admin',
            'SUPER_ADMIN_API_RATE_LIMIT' => '100',
            'SUPER_ADMIN_API_RATE_LIMIT_PERIOD' => '60',
            'SUPER_ADMIN_REQUIRE_MFA' => 'false',
            'SUPER_ADMIN_MAX_LOGIN_ATTEMPTS' => '5',
            'SUPER_ADMIN_LOCKOUT_DURATION' => '900',
            'SUPER_ADMIN_AUDIT_LOG_ENABLED' => 'true',
            'SUPER_ADMIN_IP_WHITELIST_ENABLED' => 'false',
            'SUPER_ADMIN_IP_WHITELIST' => '',
            'SUPER_ADMIN_SECURITY_HEADERS' => 'true',
            'SUPER_ADMIN_CSRF_PROTECTION' => 'true',
            'SUPER_ADMIN_TENANT_DISCOVERY_ENABLED' => 'true',
            'SUPER_ADMIN_TENANT_DISCOVERY_CACHE_TTL' => '3600',
            'SUPER_ADMIN_CONTEXT_SWITCHING_ENABLED' => 'true',
            'SUPER_ADMIN_CONTEXT_SWITCHING_TTL' => '1800',
            'SUPER_ADMIN_MULTI_TENANT_ISOLATION' => 'true',
            'SUPER_ADMIN_DATA_ISOLATION_LEVEL' => 'strict',
        ];

        $updatedVars = [];
        $newVars = [];

        foreach ($envVars as $key => $defaultValue) {
            $currentValue = env($key);

            if ($currentValue !== null && !$this->option('force')) {
                $this->line("✓ {$key} já configurado: {$currentValue}");
                continue;
            }

            if (str_contains($envContent, $key . '=')) {
                // Update existing variable
                $pattern = '/^' . preg_quote($key) . '=.*$/m';
                $envContent = preg_replace($pattern, $key . '=' . $defaultValue, $envContent);
                $updatedVars[] = $key;
            } else {
                // Add new variable
                $envContent .= "\n{$key}={$defaultValue}";
                $newVars[] = $key;
            }
        }

        // Write updated .env file
        file_put_contents($envPath, $envContent);

        $this->newLine();
        $this->info('✅ Variáveis de ambiente configuradas!');
        $this->newLine();

        if (!empty($newVars)) {
            $this->line('📝 Variáveis adicionadas:');
            foreach ($newVars as $var) {
                $this->line("  + {$var}");
            }
            $this->newLine();
        }

        if (!empty($updatedVars)) {
            $this->line('🔄 Variáveis atualizadas:');
            foreach ($updatedVars as $var) {
                $this->line("  ~ {$var}");
            }
            $this->newLine();
        }

        $this->warn('⚠️  Importante:');
        $this->line('1. Reinicie o servidor Laravel após alterar o .env');
        $this->line('2. Execute: php artisan config:clear');
        $this->line('3. Execute: php artisan cache:clear');
        $this->newLine();

        $this->info('🚀 Próximo passo: php artisan super-admin:setup');

        return 0;
    }
}