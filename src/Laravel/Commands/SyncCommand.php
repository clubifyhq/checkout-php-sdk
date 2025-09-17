<?php

declare(strict_types=1);

namespace Clubify\Checkout\Laravel\Commands;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Exceptions\SDKException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Comando para sincronização e teste de conectividade
 */
final class SyncCommand extends Command
{
    /**
     * Assinatura do comando
     */
    protected $signature = 'clubify:sync
                            {--test : Executa apenas teste de conectividade}
                            {--force : Força sincronização mesmo com cache válido}
                            {--clear-cache : Limpa cache antes da sincronização}
                            {--timeout=30 : Timeout para operações (segundos)}';

    /**
     * Descrição do comando
     */
    protected $description = 'Sincroniza dados e testa conectividade com a API do Clubify Checkout';

    /**
     * Construtor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Obtem SDK instance de forma lazy
     */
    private function getSDK(): ClubifyCheckoutSDK
    {
        return app(ClubifyCheckoutSDK::class);
    }

    /**
     * Executa o comando
     */
    public function handle(): int
    {
        $this->info('🔄 Iniciando sincronização com Clubify Checkout...');

        try {
            if ($this->option('clear-cache')) {
                $this->clearCache();
            }

            if ($this->option('test')) {
                return $this->runConnectivityTest();
            }

            return $this->runFullSync();

        } catch (SDKException $e) {
            $this->error("❌ Erro do SDK: {$e->getMessage()}");
            if ($e->getContext()) {
                $this->info('📋 Contexto: ' . json_encode($e->getContext(), JSON_PRETTY_PRINT));
            }
            return Command::FAILURE;

        } catch (\Exception $e) {
            $this->error("❌ Erro inesperado: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Executa teste de conectividade
     */
    private function runConnectivityTest(): int
    {
        $this->info('🔍 Testando conectividade...');

        // Testa inicialização do SDK
        $this->testSDKInitialization();

        // Testa conectividade básica
        $this->testBasicConnectivity();

        // Testa autenticação
        $this->testAuthentication();

        // Testa módulos principais
        $this->testModules();

        $this->info('');
        $this->info('✅ Teste de conectividade concluído com sucesso!');

        return Command::SUCCESS;
    }

    /**
     * Executa sincronização completa
     */
    private function runFullSync(): int
    {
        $this->info('📊 Executando sincronização completa...');

        // Primeiro executa teste de conectividade
        $this->runConnectivityTest();

        // Sincroniza dados
        $this->syncOrganizationData();
        $this->syncProductsData();
        $this->syncCustomersData();
        $this->syncWebhooksConfig();

        // Atualiza cache de configuração
        $this->updateConfigurationCache();

        $this->info('');
        $this->info('🎉 Sincronização completa finalizada!');

        return Command::SUCCESS;
    }

    /**
     * Testa inicialização do SDK
     */
    private function testSDKInitialization(): void
    {
        $this->info('  🔧 Testando inicialização do SDK...');

        $sdk = $this->getSDK();
        if (!$sdk->isInitialized()) {
            $sdk->initialize();
        }

        $stats = $sdk->getStats();
        $this->info("     ✅ SDK inicializado (versão: {$stats['version']})");
    }

    /**
     * Testa conectividade básica
     */
    private function testBasicConnectivity(): void
    {
        $this->info('  🌐 Testando conectividade básica...');

        $health = $this->getSDK()->healthCheck();

        if ($health['status'] === 'healthy') {
            $this->info('     ✅ API acessível');
            $this->info("     📊 Response time: {$health['response_time']}ms");
        } else {
            throw new \RuntimeException('API não acessível: ' . ($health['error'] ?? 'Unknown error'));
        }
    }

    /**
     * Testa autenticação
     */
    private function testAuthentication(): void
    {
        $this->info('  🔐 Testando autenticação...');

        try {
            // Testa autenticação através de uma operação simples
            $this->getSDK()->organization()->getStatus();
            $this->info('     ✅ Autenticação válida');
        } catch (\Exception $e) {
            throw new \RuntimeException('Falha na autenticação: ' . $e->getMessage());
        }
    }

    /**
     * Testa módulos principais
     */
    private function testModules(): void
    {
        $this->info('  🧩 Testando módulos...');

        $modules = [
            'organization' => 'Organização',
            'products' => 'Produtos',
            'checkout' => 'Checkout',
            'payments' => 'Pagamentos',
            'customers' => 'Clientes',
            'webhooks' => 'Webhooks',
        ];

        foreach ($modules as $module => $name) {
            try {
                $moduleInstance = $this->getSDK()->{$module}();
                $status = $moduleInstance->isHealthy();

                if ($status) {
                    $this->info("     ✅ {$name}");
                } else {
                    $this->warn("     ⚠️  {$name} (com problemas)");
                }
            } catch (\Exception $e) {
                $this->error("     ❌ {$name}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Sincroniza dados da organização
     */
    private function syncOrganizationData(): void
    {
        $this->info('  🏢 Sincronizando dados da organização...');

        try {
            $status = $this->getSDK()->organization()->getStatus();
            Cache::put('clubify.organization.status', $status, 3600);
            $this->info('     ✅ Dados da organização sincronizados');
        } catch (\Exception $e) {
            $this->warn("     ⚠️  Falha na sincronização da organização: {$e->getMessage()}");
        }
    }

    /**
     * Sincroniza dados de produtos
     */
    private function syncProductsData(): void
    {
        $this->info('  📦 Sincronizando dados de produtos...');

        try {
            $stats = $this->getSDK()->products()->getStats();
            Cache::put('clubify.products.stats', $stats, 1800);
            $this->info('     ✅ Dados de produtos sincronizados');
        } catch (\Exception $e) {
            $this->warn("     ⚠️  Falha na sincronização de produtos: {$e->getMessage()}");
        }
    }

    /**
     * Sincroniza dados de clientes
     */
    private function syncCustomersData(): void
    {
        $this->info('  👥 Sincronizando dados de clientes...');

        try {
            $stats = $this->getSDK()->customers()->getStats();
            Cache::put('clubify.customers.stats', $stats, 1800);
            $this->info('     ✅ Dados de clientes sincronizados');
        } catch (\Exception $e) {
            $this->warn("     ⚠️  Falha na sincronização de clientes: {$e->getMessage()}");
        }
    }

    /**
     * Sincroniza configuração de webhooks
     */
    private function syncWebhooksConfig(): void
    {
        $this->info('  🔗 Sincronizando configuração de webhooks...');

        try {
            $config = $this->getSDK()->webhooks()->getConfig();
            Cache::put('clubify.webhooks.config', $config, 3600);
            $this->info('     ✅ Configuração de webhooks sincronizada');
        } catch (\Exception $e) {
            $this->warn("     ⚠️  Falha na sincronização de webhooks: {$e->getMessage()}");
        }
    }

    /**
     * Atualiza cache de configuração
     */
    private function updateConfigurationCache(): void
    {
        $this->info('  🗂️  Atualizando cache de configuração...');

        try {
            $config = $this->getSDK()->getConfiguration();
            Cache::put('clubify.configuration', $config, 7200);
            $this->info('     ✅ Cache de configuração atualizado');
        } catch (\Exception $e) {
            $this->warn("     ⚠️  Falha na atualização do cache: {$e->getMessage()}");
        }
    }

    /**
     * Limpa cache
     */
    private function clearCache(): void
    {
        $this->info('🗑️  Limpando cache...');

        $cacheKeys = [
            'clubify.organization.status',
            'clubify.products.stats',
            'clubify.customers.stats',
            'clubify.webhooks.config',
            'clubify.configuration',
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Limpa cache do SDK
        $this->getSDK()->clearCache();

        $this->info('     ✅ Cache limpo');
    }
}