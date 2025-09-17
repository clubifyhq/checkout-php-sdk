<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Comando para instalação e configuração inicial do SDK
 */
final class InstallCommand extends Command
{
    /**
     * Assinatura do comando
     */
    protected $signature = 'clubify:install
                            {--force : Força sobrescrita de arquivos existentes}
                            {--config-only : Publica apenas arquivos de configuração}
                            {--no-publish : Não publica assets}';

    /**
     * Descrição do comando
     */
    protected $description = 'Instala e configura o Clubify Checkout SDK para Laravel';

    /**
     * Executa o comando
     */
    public function handle(): int
    {
        $this->info('🚀 Instalando Clubify Checkout SDK...');

        try {
            $this->publishAssets();
            $this->createEnvironmentVariables();
            $this->showCompletionMessage();

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro durante a instalação: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Publica assets do pacote
     */
    private function publishAssets(): void
    {
        if ($this->option('no-publish')) {
            $this->info('📄 Pulando publicação de assets...');
            return;
        }

        $this->info('📦 Publicando assets...');

        $force = $this->option('force');
        $configOnly = $this->option('config-only');

        // Publica configuração
        $this->call('vendor:publish', [
            '--provider' => 'Clubify\\Checkout\\Laravel\\ClubifyCheckoutServiceProvider',
            '--tag' => 'clubify-checkout-config',
            '--force' => $force,
        ]);

        if (!$configOnly) {
            // Publica traduções
            $this->call('vendor:publish', [
                '--provider' => 'Clubify\\Checkout\\Laravel\\ClubifyCheckoutServiceProvider',
                '--tag' => 'clubify-checkout-lang',
                '--force' => $force,
            ]);

            // Publica stubs
            $this->call('vendor:publish', [
                '--provider' => 'Clubify\\Checkout\\Laravel\\ClubifyCheckoutServiceProvider',
                '--tag' => 'clubify-checkout-stubs',
                '--force' => $force,
            ]);
        }

        $this->info('✅ Assets publicados com sucesso');
    }

    /**
     * Cria variáveis de ambiente necessárias
     */
    private function createEnvironmentVariables(): void
    {
        $this->info('🔧 Configurando variáveis de ambiente...');

        $envPath = base_path('.env');
        $envExamplePath = base_path('.env.example');

        $variables = [
            'CLUBIFY_CHECKOUT_API_KEY' => 'your-api-key-here',
            'CLUBIFY_CHECKOUT_API_SECRET' => 'your-api-secret-here',
            'CLUBIFY_CHECKOUT_TENANT_ID' => 'your-tenant-id-here',
            'CLUBIFY_CHECKOUT_ENVIRONMENT' => 'sandbox',
            'CLUBIFY_CHECKOUT_BASE_URL' => 'https://api.clubify.com',
            'CLUBIFY_CHECKOUT_TIMEOUT' => '30',
            'CLUBIFY_CHECKOUT_RETRY_ATTEMPTS' => '3',
            'CLUBIFY_CHECKOUT_CACHE_TTL' => '3600',
            'CLUBIFY_CHECKOUT_DEBUG' => 'false',
        ];

        foreach ($variables as $key => $defaultValue) {
            $this->addEnvironmentVariable($envPath, $key, $defaultValue);
            if (File::exists($envExamplePath)) {
                $this->addEnvironmentVariable($envExamplePath, $key, $defaultValue);
            }
        }

        $this->info('✅ Variáveis de ambiente adicionadas');
    }

    /**
     * Adiciona variável ao arquivo .env se não existir
     */
    private function addEnvironmentVariable(string $filePath, string $key, string $value): void
    {
        if (!File::exists($filePath)) {
            File::put($filePath, '');
        }

        $content = File::get($filePath);

        if (!str_contains($content, $key)) {
            $newLine = PHP_EOL . "{$key}={$value}";
            File::append($filePath, $newLine);

            $this->line("  📝 Adicionado: {$key}");
        } else {
            $this->line("  ⏭️  Já existe: {$key}");
        }
    }

    /**
     * Exibe mensagem de conclusão
     */
    private function showCompletionMessage(): void
    {
        $this->info('');
        $this->info('🎉 Instalação concluída com sucesso!');
        $this->info('');
        $this->info('📋 Próximos passos:');
        $this->info('');
        $this->info('1. Configure suas credenciais no arquivo .env:');
        $this->info('   - CLUBIFY_CHECKOUT_API_KEY');
        $this->info('   - CLUBIFY_CHECKOUT_API_SECRET');
        $this->info('   - CLUBIFY_CHECKOUT_TENANT_ID');
        $this->info('');
        $this->info('2. Ajuste as configurações em config/clubify-checkout.php');
        $this->info('');
        $this->info('3. Execute o comando de sincronização:');
        $this->info('   php artisan clubify:sync');
        $this->info('');
        $this->info('4. Teste a integração:');
        $this->info('   php artisan clubify:test');
        $this->info('');
        $this->info('📚 Documentação: https://docs.clubify.com/sdk/php');
        $this->info('🆘 Suporte: https://github.com/clubify/checkout-sdk-php/issues');
    }
}
