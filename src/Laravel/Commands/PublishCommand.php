<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * Comando para publicação seletiva de assets
 */
final class PublishCommand extends Command
{
    /**
     * Assinatura do comando
     */
    protected $signature = 'clubify:publish
                            {asset? : Tipo de asset para publicar (config, lang, stubs, all)}
                            {--force : Força sobrescrita de arquivos existentes}
                            {--dry-run : Mostra o que seria publicado sem executar}';

    /**
     * Descrição do comando
     */
    protected $description = 'Publica assets específicos do Clubify Checkout SDK';

    /**
     * Assets disponíveis para publicação
     */
    private const AVAILABLE_ASSETS = [
        'config' => [
            'tag' => 'clubify-checkout-config',
            'description' => 'Arquivos de configuração',
            'files' => ['config/clubify-checkout.php'],
        ],
        'lang' => [
            'tag' => 'clubify-checkout-lang',
            'description' => 'Arquivos de tradução',
            'files' => ['resources/lang/*/messages.php'],
        ],
        'stubs' => [
            'tag' => 'clubify-checkout-stubs',
            'description' => 'Templates de código',
            'files' => ['resources/stubs/*.stub'],
        ],
        'all' => [
            'tag' => 'clubify-checkout',
            'description' => 'Todos os assets',
            'files' => ['config/', 'resources/'],
        ],
    ];

    /**
     * Executa o comando
     */
    public function handle(): int
    {
        $asset = $this->argument('asset') ?? $this->askForAsset();

        if (!$asset) {
            $this->error('❌ Nenhum asset selecionado');
            return Command::FAILURE;
        }

        if (!isset(self::AVAILABLE_ASSETS[$asset])) {
            $this->error("❌ Asset inválido: {$asset}");
            $this->showAvailableAssets();
            return Command::FAILURE;
        }

        try {
            if ($this->option('dry-run')) {
                $this->showDryRun($asset);
            } else {
                $this->publishAsset($asset);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Erro durante a publicação: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Pergunta qual asset publicar
     */
    private function askForAsset(): ?string
    {
        $choices = [];
        foreach (self::AVAILABLE_ASSETS as $key => $asset) {
            $choices[$key] = "{$key} - {$asset['description']}";
        }

        return $this->choice(
            '📦 Qual asset você deseja publicar?',
            $choices,
            'all'
        );
    }

    /**
     * Mostra assets disponíveis
     */
    private function showAvailableAssets(): void
    {
        $this->info('📦 Assets disponíveis:');
        $this->info('');

        foreach (self::AVAILABLE_ASSETS as $key => $asset) {
            $this->info("  🔹 {$key}");
            $this->info("     {$asset['description']}");
            $this->info("     Arquivos: " . implode(', ', $asset['files']));
            $this->info('');
        }
    }

    /**
     * Mostra o que seria publicado (dry run)
     */
    private function showDryRun(string $asset): void
    {
        $assetConfig = self::AVAILABLE_ASSETS[$asset];

        $this->info("🔍 Simulação de publicação do asset: {$asset}");
        $this->info('');
        $this->info("📄 Descrição: {$assetConfig['description']}");
        $this->info("🏷️  Tag: {$assetConfig['tag']}");
        $this->info('📁 Arquivos que seriam publicados:');

        foreach ($assetConfig['files'] as $file) {
            $this->info("   - {$file}");
        }

        $this->info('');

        if ($this->option('force')) {
            $this->warn('⚠️  Flag --force ativada: arquivos existentes seriam sobrescritos');
        }

        $this->info('💡 Para executar a publicação, remova a flag --dry-run');
    }

    /**
     * Publica o asset específico
     */
    private function publishAsset(string $asset): void
    {
        $assetConfig = self::AVAILABLE_ASSETS[$asset];

        $this->info("📦 Publicando {$asset}...");

        $this->call('vendor:publish', [
            '--provider' => 'Clubify\\Checkout\\Laravel\\ClubifyCheckoutServiceProvider',
            '--tag' => $assetConfig['tag'],
            '--force' => $this->option('force'),
        ]);

        $this->info("✅ Asset '{$asset}' publicado com sucesso!");

        // Mostra próximos passos baseado no asset
        $this->showNextSteps($asset);
    }

    /**
     * Mostra próximos passos baseado no asset publicado
     */
    private function showNextSteps(string $asset): void
    {
        $this->info('');
        $this->info('📋 Próximos passos:');

        match ($asset) {
            'config' => $this->showConfigNextSteps(),
            'lang' => $this->showLangNextSteps(),
            'stubs' => $this->showStubsNextSteps(),
            'all' => $this->showAllNextSteps(),
            default => null,
        };
    }

    /**
     * Próximos passos para configuração
     */
    private function showConfigNextSteps(): void
    {
        $this->info('');
        $this->info('1. Edite o arquivo config/clubify-checkout.php');
        $this->info('2. Configure as variáveis de ambiente no .env');
        $this->info('3. Execute: php artisan config:cache');
    }

    /**
     * Próximos passos para traduções
     */
    private function showLangNextSteps(): void
    {
        $this->info('');
        $this->info('1. Edite os arquivos em resources/lang/vendor/clubify-checkout/');
        $this->info('2. Adicione novas traduções conforme necessário');
        $this->info('3. Execute: php artisan optimize');
    }

    /**
     * Próximos passos para stubs
     */
    private function showStubsNextSteps(): void
    {
        $this->info('');
        $this->info('1. Use os templates em resources/stubs/vendor/clubify-checkout/');
        $this->info('2. Customize os stubs conforme sua aplicação');
        $this->info('3. Execute comandos que usam os stubs');
    }

    /**
     * Próximos passos para todos os assets
     */
    private function showAllNextSteps(): void
    {
        $this->info('');
        $this->info('1. Configure o arquivo config/clubify-checkout.php');
        $this->info('2. Customize traduções em resources/lang/vendor/clubify-checkout/');
        $this->info('3. Use templates em resources/stubs/vendor/clubify-checkout/');
        $this->info('4. Execute: php artisan optimize');
    }
}