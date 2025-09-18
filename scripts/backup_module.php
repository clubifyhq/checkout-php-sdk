#!/usr/bin/env php
<?php

/**
 * Script de Backup Automático de Módulos
 *
 * Cria backup completo de um módulo antes da migração
 * para permitir rollback instantâneo em caso de problemas.
 *
 * Uso: php backup_module.php <module_name>
 * Exemplo: php backup_module.php customers
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class ModuleBackup
{
    private string $sdkRoot;
    private string $backupRoot;
    private array $supportedModules;

    public function __construct()
    {
        $this->sdkRoot = dirname(__DIR__);
        $this->backupRoot = $this->sdkRoot . '/backups';
        $this->supportedModules = [
            'customers', 'products', 'orders', 'payments', 'webhooks',
            'notifications', 'tracking', 'subscriptions', 'usermanagement'
        ];

        $this->ensureBackupDirectory();
    }

    public function backup(string $moduleName): array
    {
        $moduleName = strtolower($moduleName);

        if (!$this->isValidModule($moduleName)) {
            throw new InvalidArgumentException("Módulo não suportado: {$moduleName}");
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupId = "{$moduleName}_{$timestamp}_" . substr(md5(uniqid()), 0, 8);

        echo "🔄 Iniciando backup do módulo '{$moduleName}'...\n";

        try {
            $backupPath = $this->createBackupStructure($backupId);
            $stats = $this->backupModuleFiles($moduleName, $backupPath);
            $this->createBackupManifest($backupId, $moduleName, $stats);

            echo "✅ Backup criado com sucesso!\n";
            echo "📁 ID do Backup: {$backupId}\n";
            echo "📂 Localização: {$backupPath}\n";
            echo "📊 Arquivos: {$stats['files_count']}\n";
            echo "💾 Tamanho: " . $this->formatBytes($stats['total_size']) . "\n";

            return [
                'success' => true,
                'backup_id' => $backupId,
                'path' => $backupPath,
                'stats' => $stats
            ];

        } catch (Exception $e) {
            echo "❌ Erro no backup: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function listBackups(string $moduleName = null): array
    {
        $pattern = $moduleName ? "{$moduleName}_*" : "*";
        $backups = [];

        foreach (glob($this->backupRoot . "/{$pattern}") as $backupDir) {
            if (is_dir($backupDir)) {
                $manifestFile = $backupDir . '/manifest.json';
                if (file_exists($manifestFile)) {
                    $manifest = json_decode(file_get_contents($manifestFile), true);
                    $backups[] = [
                        'id' => basename($backupDir),
                        'module' => $manifest['module'],
                        'created_at' => $manifest['created_at'],
                        'size' => $this->formatBytes($manifest['stats']['total_size']),
                        'files' => $manifest['stats']['files_count']
                    ];
                }
            }
        }

        // Ordenar por data (mais recente primeiro)
        usort($backups, fn($a, $b) => $b['created_at'] <=> $a['created_at']);

        return $backups;
    }

    public function restore(string $backupId): array
    {
        $backupPath = $this->backupRoot . '/' . $backupId;

        if (!is_dir($backupPath)) {
            throw new InvalidArgumentException("Backup não encontrado: {$backupId}");
        }

        $manifestFile = $backupPath . '/manifest.json';
        if (!file_exists($manifestFile)) {
            throw new RuntimeException("Manifest do backup não encontrado");
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);
        $moduleName = $manifest['module'];

        echo "🔄 Restaurando backup '{$backupId}' do módulo '{$moduleName}'...\n";

        try {
            $this->restoreModuleFiles($backupPath, $moduleName);

            echo "✅ Restauração concluída com sucesso!\n";
            echo "📦 Módulo: {$moduleName}\n";
            echo "🕒 Backup de: {$manifest['created_at']}\n";

            return [
                'success' => true,
                'module' => $moduleName,
                'backup_date' => $manifest['created_at']
            ];

        } catch (Exception $e) {
            echo "❌ Erro na restauração: " . $e->getMessage() . "\n";
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function isValidModule(string $moduleName): bool
    {
        return in_array($moduleName, $this->supportedModules);
    }

    private function ensureBackupDirectory(): void
    {
        if (!is_dir($this->backupRoot)) {
            mkdir($this->backupRoot, 0755, true);
        }
    }

    private function createBackupStructure(string $backupId): string
    {
        $backupPath = $this->backupRoot . '/' . $backupId;

        if (!mkdir($backupPath, 0755, true)) {
            throw new RuntimeException("Não foi possível criar diretório de backup");
        }

        return $backupPath;
    }

    private function backupModuleFiles(string $moduleName, string $backupPath): array
    {
        $moduleDir = $this->getModuleDirectory($moduleName);
        $stats = ['files_count' => 0, 'total_size' => 0];

        if (!is_dir($moduleDir)) {
            throw new RuntimeException("Diretório do módulo não encontrado: {$moduleDir}");
        }

        $this->copyDirectory($moduleDir, $backupPath . '/module', $stats);

        return $stats;
    }

    private function copyDirectory(string $source, string $destination, array &$stats): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getRealPath(), $destPath);
                $stats['files_count']++;
                $stats['total_size'] += $item->getSize();
            }
        }
    }

    private function restoreModuleFiles(string $backupPath, string $moduleName): void
    {
        $moduleDir = $this->getModuleDirectory($moduleName);
        $backupModuleDir = $backupPath . '/module';

        if (!is_dir($backupModuleDir)) {
            throw new RuntimeException("Diretório do módulo no backup não encontrado");
        }

        // Remove diretório atual
        if (is_dir($moduleDir)) {
            $this->removeDirectory($moduleDir);
        }

        // Restaura do backup
        $this->copyDirectory($backupModuleDir, $moduleDir, $stats);
    }

    private function removeDirectory(string $dir): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }

    private function createBackupManifest(string $backupId, string $moduleName, array $stats): void
    {
        $manifest = [
            'backup_id' => $backupId,
            'module' => $moduleName,
            'created_at' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'sdk_version' => $this->getSDKVersion(),
            'stats' => $stats
        ];

        $manifestFile = $this->backupRoot . '/' . $backupId . '/manifest.json';
        file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function getModuleDirectory(string $moduleName): string
    {
        $moduleMap = [
            'customers' => 'Customers',
            'products' => 'Products',
            'orders' => 'Orders',
            'payments' => 'Payments',
            'webhooks' => 'Webhooks',
            'notifications' => 'Notifications',
            'tracking' => 'Tracking',
            'subscriptions' => 'Subscriptions',
            'usermanagement' => 'UserManagement'
        ];

        $dirName = $moduleMap[$moduleName] ?? ucfirst($moduleName);
        return $this->sdkRoot . '/src/Modules/' . $dirName;
    }

    private function getSDKVersion(): string
    {
        $composerFile = $this->sdkRoot . '/composer.json';
        if (file_exists($composerFile)) {
            $composer = json_decode(file_get_contents($composerFile), true);
            return $composer['version'] ?? 'unknown';
        }
        return 'unknown';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}

// CLI Usage
if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? '';
    $moduleName = $argv[2] ?? '';

    $backup = new ModuleBackup();

    try {
        switch ($command) {
            case 'create':
                if (empty($moduleName)) {
                    echo "❌ Uso: php backup_module.php create <module_name>\n";
                    exit(1);
                }
                $backup->backup($moduleName);
                break;

            case 'list':
                $backups = $backup->listBackups($moduleName);
                echo "📁 Backups disponíveis:\n";
                foreach ($backups as $bkp) {
                    echo "  - {$bkp['id']} | {$bkp['module']} | {$bkp['created_at']} | {$bkp['size']}\n";
                }
                break;

            case 'restore':
                if (empty($moduleName)) {
                    echo "❌ Uso: php backup_module.php restore <backup_id>\n";
                    exit(1);
                }
                $backup->restore($moduleName);
                break;

            default:
                echo "🔧 Backup de Módulos SDK\n";
                echo "Comandos disponíveis:\n";
                echo "  create <module>  - Criar backup\n";
                echo "  list [module]    - Listar backups\n";
                echo "  restore <id>     - Restaurar backup\n";
        }
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}