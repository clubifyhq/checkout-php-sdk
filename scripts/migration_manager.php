#!/usr/bin/env php
<?php

/**
 * Migration Manager - Coordenador Geral das Migrações FASE 5
 *
 * Script principal que coordena a migração de todos os módulos
 * seguindo a ordem de prioridade e executando validações.
 *
 * Uso: php migration_manager.php [command] [options]
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class MigrationManager
{
    private string $sdkRoot;
    private string $scriptsPath;
    private array $migrationOrder;
    private array $migrationStatus = [];

    public function __construct()
    {
        $this->sdkRoot = dirname(__DIR__);
        $this->scriptsPath = $this->sdkRoot . '/scripts';

        // Ordem de migração conforme estratégia (Orders e Payments por último)
        $this->migrationOrder = [
            // Sprint 1: Baixo Risco (15 dias)
            'customers'     => ['priority' => 'high', 'risk' => 'low',    'estimated_hours' => 10, 'reason' => 'Já tem repository interface'],
            'products'      => ['priority' => 'high', 'risk' => 'medium', 'estimated_hours' => 12, 'reason' => 'Complexidade média (themes/layouts)'],
            'webhooks'      => ['priority' => 'low',  'risk' => 'low',    'estimated_hours' => 8,  'reason' => 'Infraestrutura simples'],
            'notifications' => ['priority' => 'low',  'risk' => 'low',    'estimated_hours' => 8,  'reason' => 'Infraestrutura simples'],
            'tracking'      => ['priority' => 'low',  'risk' => 'low',    'estimated_hours' => 6,  'reason' => 'Analytics simples'],

            // Sprint 2: Médio Risco (5 dias)
            'subscriptions' => ['priority' => 'low',  'risk' => 'medium', 'estimated_hours' => 15, 'reason' => 'Futuro, menos crítico'],

            // Sprint 3: Alto Risco (10 dias) - POR ÚLTIMO
            'orders'        => ['priority' => 'critical', 'risk' => 'high', 'estimated_hours' => 20, 'reason' => 'Crítico - alta complexidade'],
            'payments'      => ['priority' => 'critical', 'risk' => 'high', 'estimated_hours' => 20, 'reason' => 'Crítico - máxima segurança'],
        ];
    }

    public function run(): void
    {
        $command = $GLOBALS['argv'][1] ?? 'help';

        try {
            switch ($command) {
                case 'status':
                    $this->showStatus();
                    break;

                case 'plan':
                    $this->showMigrationPlan();
                    break;

                case 'migrate':
                    $module = $GLOBALS['argv'][2] ?? '';
                    if (empty($module)) {
                        echo "❌ Uso: php migration_manager.php migrate <module_name>\n";
                        exit(1);
                    }
                    $this->migrateModule($module);
                    break;

                case 'migrate-all':
                    $this->migrateAllModules();
                    break;

                case 'rollback':
                    $module = $GLOBALS['argv'][2] ?? '';
                    if (empty($module)) {
                        echo "❌ Uso: php migration_manager.php rollback <module_name>\n";
                        exit(1);
                    }
                    $this->rollbackModule($module);
                    break;

                case 'validate-all':
                    $this->validateAllModules();
                    break;

                case 'report':
                    $this->generateReport();
                    break;

                default:
                    $this->showHelp();
            }

        } catch (Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function showStatus(): void
    {
        echo "📊 Status das Migrações - SDK Clubify Checkout\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->loadMigrationStatus();

        $totalModules = count($this->migrationOrder);
        $migratedCount = 0;
        $pendingCount = 0;

        foreach ($this->migrationOrder as $module => $config) {
            $status = $this->getMigrationStatus($module);
            $icon = $this->getStatusIcon($status);
            $risk = $config['risk'];
            $priority = $config['priority'];
            $hours = $config['estimated_hours'];

            echo sprintf(
                "  %s %-15s | %-8s | %-8s | %2dh | %s\n",
                $icon,
                ucfirst($module),
                ucfirst($priority),
                ucfirst($risk),
                $hours,
                $status
            );

            if ($status === 'migrated') $migratedCount++;
            else $pendingCount++;
        }

        echo "\n" . str_repeat("-", 60) . "\n";
        echo "📈 Resumo: {$migratedCount}/{$totalModules} migrados, {$pendingCount} pendentes\n";
        echo "⏱️  Total estimado: " . array_sum(array_column($this->migrationOrder, 'estimated_hours')) . " horas\n\n";
    }

    private function showMigrationPlan(): void
    {
        echo "📋 Plano de Migração Incremental - FASE 5\n";
        echo str_repeat("=", 60) . "\n\n";

        $currentSprint = 1;
        $sprintModules = [
            1 => ['customers', 'products', 'webhooks', 'notifications', 'tracking'],
            2 => ['subscriptions'],
            3 => ['orders', 'payments']
        ];

        foreach ($sprintModules as $sprint => $modules) {
            echo "🚀 Sprint {$sprint}:\n";

            $totalHours = 0;
            foreach ($modules as $module) {
                $config = $this->migrationOrder[$module];
                $totalHours += $config['estimated_hours'];

                echo sprintf(
                    "   • %-15s (%2dh) - %s\n",
                    ucfirst($module),
                    $config['estimated_hours'],
                    $config['reason']
                );
            }

            $days = ceil($totalHours / 8);
            echo "   📅 Duração estimada: {$days} dias ({$totalHours}h)\n\n";
        }

        echo "⚠️  Importante:\n";
        echo "   • Orders e Payments ficam por último (como solicitado)\n";
        echo "   • Cada módulo tem backup automático antes da migração\n";
        echo "   • Rollback disponível em caso de problemas\n";
        echo "   • Validação contínua durante todo o processo\n\n";
    }

    private function migrateModule(string $moduleName): void
    {
        if (!isset($this->migrationOrder[$moduleName])) {
            throw new InvalidArgumentException("Módulo não suportado: {$moduleName}");
        }

        echo "🚀 Iniciando migração do módulo: {$moduleName}\n";
        echo str_repeat("=", 50) . "\n\n";

        $config = $this->migrationOrder[$moduleName];

        // Verificar pré-requisitos
        $this->checkPrerequisites($moduleName);

        // Executar script de migração
        $migrateScript = $this->scriptsPath . '/migrate_module.php';

        if (!file_exists($migrateScript)) {
            throw new RuntimeException("Script de migração não encontrado");
        }

        echo "⏱️  Tempo estimado: {$config['estimated_hours']} horas\n";
        echo "⚠️  Nível de risco: {$config['risk']}\n\n";

        $startTime = time();

        $output = [];
        $returnCode = 0;
        exec("php {$migrateScript} {$moduleName} 2>&1", $output, $returnCode);

        $duration = time() - $startTime;

        if ($returnCode === 0) {
            $this->updateMigrationStatus($moduleName, 'migrated', $duration);
            echo "\n✅ Migração de {$moduleName} concluída em " . gmdate('H:i:s', $duration) . "\n";
        } else {
            $this->updateMigrationStatus($moduleName, 'failed', $duration);
            echo "\n❌ Migração de {$moduleName} falhou:\n";
            echo implode("\n", $output) . "\n";
            throw new RuntimeException("Migração falhou");
        }
    }

    private function migrateAllModules(): void
    {
        echo "🚀 Iniciando migração de TODOS os módulos\n";
        echo str_repeat("=", 60) . "\n\n";

        $confirm = $this->prompt("⚠️  Isso irá migrar TODOS os módulos. Continuar? (yes/no)", "no");
        if (strtolower($confirm) !== 'yes') {
            echo "❌ Migração cancelada.\n";
            return;
        }

        $totalStart = time();
        $successful = 0;
        $failed = 0;

        foreach ($this->migrationOrder as $module => $config) {
            try {
                echo "\n" . str_repeat("-", 40) . "\n";
                $this->migrateModule($module);
                $successful++;

                // Pausa entre migrações para análise
                if ($module !== array_key_last($this->migrationOrder)) {
                    echo "\n⏸️  Pausando 30 segundos para análise...\n";
                    sleep(30);
                }

            } catch (Exception $e) {
                echo "❌ Falha na migração de {$module}: " . $e->getMessage() . "\n";
                $failed++;

                $continue = $this->prompt("Continuar com próximo módulo? (y/n)", "n");
                if (strtolower($continue) !== 'y') {
                    break;
                }
            }
        }

        $totalDuration = time() - $totalStart;

        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 RESUMO FINAL DA MIGRAÇÃO\n";
        echo str_repeat("=", 60) . "\n";
        echo "✅ Sucessos: {$successful}\n";
        echo "❌ Falhas: {$failed}\n";
        echo "⏱️  Tempo total: " . gmdate('H:i:s', $totalDuration) . "\n\n";

        if ($failed === 0) {
            echo "🎉 TODAS as migrações foram concluídas com sucesso!\n";
        } else {
            echo "⚠️  Algumas migrações falharam. Verifique os logs acima.\n";
        }
    }

    private function rollbackModule(string $moduleName): void
    {
        echo "🔄 Iniciando rollback do módulo: {$moduleName}\n";

        $backupScript = $this->scriptsPath . '/backup_module.php';

        if (!file_exists($backupScript)) {
            throw new RuntimeException("Script de backup não encontrado");
        }

        $output = [];
        $returnCode = 0;

        // Listar backups disponíveis
        exec("php {$backupScript} list {$moduleName} 2>&1", $output, $returnCode);

        if (empty($output) || $returnCode !== 0) {
            throw new RuntimeException("Nenhum backup encontrado para {$moduleName}");
        }

        echo "📁 Backups disponíveis:\n";
        foreach ($output as $line) {
            if (strpos($line, "- {$moduleName}_") === 0) {
                echo "  {$line}\n";
            }
        }

        $backupId = $this->prompt("\n🔧 Digite o ID do backup para restaurar");

        if (empty($backupId)) {
            echo "❌ Rollback cancelado.\n";
            return;
        }

        // Executar rollback
        $output = [];
        exec("php {$backupScript} restore {$backupId} 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            $this->updateMigrationStatus($moduleName, 'rolled_back');
            echo "✅ Rollback de {$moduleName} concluído com sucesso!\n";
        } else {
            echo "❌ Falha no rollback:\n";
            echo implode("\n", $output) . "\n";
        }
    }

    private function validateAllModules(): void
    {
        echo "🔍 Validando todos os módulos migrados\n";
        echo str_repeat("=", 50) . "\n\n";

        $validationScript = $this->sdkRoot . '/docs/scripts/validate_module.php';

        if (!file_exists($validationScript)) {
            throw new RuntimeException("Script de validação não encontrado");
        }

        $results = [];

        foreach ($this->migrationOrder as $module => $config) {
            if ($this->getMigrationStatus($module) !== 'migrated') {
                echo "⏭️  Pulando {$module} (não migrado)\n";
                continue;
            }

            echo "🔍 Validando {$module}...\n";

            $moduleClassName = $this->getModuleClassName($module);
            $output = [];
            $returnCode = 0;

            exec("php {$validationScript} {$moduleClassName} 2>&1", $output, $returnCode);

            $results[$module] = $returnCode === 0 ? 'passed' : 'failed';

            if ($returnCode === 0) {
                echo "  ✅ Validação passou\n";
            } else {
                echo "  ❌ Validação falhou\n";
                echo "  " . implode("\n  ", array_slice($output, -3)) . "\n";
            }
        }

        echo "\n📊 Resumo das validações:\n";
        foreach ($results as $module => $result) {
            $icon = $result === 'passed' ? '✅' : '❌';
            echo "  {$icon} {$module}\n";
        }
    }

    private function generateReport(): void
    {
        echo "📊 Relatório de Migração - SDK Clubify Checkout\n";
        echo str_repeat("=", 60) . "\n\n";

        $this->loadMigrationStatus();

        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'total_modules' => count($this->migrationOrder),
            'modules' => []
        ];

        foreach ($this->migrationOrder as $module => $config) {
            $status = $this->getMigrationStatus($module);

            $report['modules'][$module] = [
                'status' => $status,
                'priority' => $config['priority'],
                'risk' => $config['risk'],
                'estimated_hours' => $config['estimated_hours'],
                'reason' => $config['reason']
            ];

            echo sprintf(
                "%-15s | %-10s | %-8s | %-6s | %2dh\n",
                ucfirst($module),
                $status,
                $config['priority'],
                $config['risk'],
                $config['estimated_hours']
            );
        }

        // Salvar relatório em arquivo
        $reportFile = $this->sdkRoot . '/migration_report_' . date('Y-m-d_H-i-s') . '.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));

        echo "\n📄 Relatório salvo em: {$reportFile}\n";
    }

    private function showHelp(): void
    {
        echo "🔧 Migration Manager - SDK Clubify Checkout\n";
        echo str_repeat("=", 50) . "\n\n";
        echo "Comandos disponíveis:\n";
        echo "  status         - Mostra status atual das migrações\n";
        echo "  plan          - Mostra plano de migração detalhado\n";
        echo "  migrate <mod> - Migra um módulo específico\n";
        echo "  migrate-all   - Migra todos os módulos em ordem\n";
        echo "  rollback <mod>- Faz rollback de um módulo\n";
        echo "  validate-all  - Valida todos módulos migrados\n";
        echo "  report        - Gera relatório completo\n\n";
        echo "Exemplos:\n";
        echo "  php migration_manager.php status\n";
        echo "  php migration_manager.php migrate customers\n";
        echo "  php migration_manager.php migrate-all\n\n";
        echo "Ordem recomendada (Orders e Payments por último):\n";
        echo "  1. customers, products, webhooks, notifications, tracking\n";
        echo "  2. subscriptions\n";
        echo "  3. orders, payments\n\n";
    }

    // Métodos auxiliares

    private function checkPrerequisites(string $moduleName): void
    {
        // Verificar se módulo existe
        $moduleDir = $this->sdkRoot . '/src/Modules/' . $this->getModuleClassName($moduleName);
        if (!is_dir($moduleDir)) {
            throw new RuntimeException("Módulo {$moduleName} não encontrado em: {$moduleDir}");
        }

        // Verificar se templates existem
        $templatesDir = $this->sdkRoot . '/docs/templates';
        if (!is_dir($templatesDir)) {
            throw new RuntimeException("Diretório de templates não encontrado: {$templatesDir}");
        }
    }

    private function getMigrationStatus(string $module): string
    {
        return $this->migrationStatus[$module] ?? 'pending';
    }

    private function updateMigrationStatus(string $module, string $status, int $duration = 0): void
    {
        $this->migrationStatus[$module] = $status;

        $statusFile = $this->sdkRoot . '/migration_status.json';
        $data = [
            'last_updated' => date('Y-m-d H:i:s'),
            'modules' => $this->migrationStatus
        ];

        if ($duration > 0) {
            $data['durations'][$module] = $duration;
        }

        file_put_contents($statusFile, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function loadMigrationStatus(): void
    {
        $statusFile = $this->sdkRoot . '/migration_status.json';

        if (file_exists($statusFile)) {
            $data = json_decode(file_get_contents($statusFile), true);
            $this->migrationStatus = $data['modules'] ?? [];
        }
    }

    private function getStatusIcon(string $status): string
    {
        return match ($status) {
            'migrated' => '✅',
            'failed' => '❌',
            'rolled_back' => '🔄',
            'in_progress' => '🔄',
            default => '⏳'
        };
    }

    private function getModuleClassName(string $moduleName): string
    {
        $moduleMap = [
            'customers' => 'Customers',
            'products' => 'Products',
            'orders' => 'Orders',
            'payments' => 'Payments',
            'webhooks' => 'Webhooks',
            'notifications' => 'Notifications',
            'tracking' => 'Tracking',
            'subscriptions' => 'Subscriptions'
        ];

        return $moduleMap[$moduleName] ?? ucfirst($moduleName);
    }

    private function prompt(string $question, string $default = ''): string
    {
        $prompt = $question;
        if ($default) {
            $prompt .= " [{$default}]";
        }
        $prompt .= ': ';

        echo $prompt;
        $input = trim(fgets(STDIN));

        return $input ?: $default;
    }
}

// Execução CLI
if (php_sapi_name() === 'cli') {
    $manager = new MigrationManager();
    $manager->run();
} else {
    echo "Este script deve ser executado via linha de comando\n";
    exit(1);
}