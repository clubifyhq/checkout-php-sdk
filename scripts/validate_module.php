#!/usr/bin/env php
<?php

/**
 * Script de Validação Automatizada de Módulos
 *
 * Executa validação completa de um módulo após migração
 * para garantir que tudo funciona corretamente.
 *
 * Uso: php validate_module.php <module_name>
 * Exemplo: php validate_module.php customers
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

class ModuleValidator
{
    private string $sdkRoot;
    private array $supportedModules;
    private array $validationResults = [];

    public function __construct()
    {
        $this->sdkRoot = dirname(__DIR__);
        $this->supportedModules = [
            'customers', 'products', 'orders', 'payments', 'webhooks',
            'notifications', 'tracking', 'subscriptions', 'usermanagement'
        ];
    }

    public function validate(string $moduleName, bool $strict = false): array
    {
        $moduleName = strtolower($moduleName);

        if (!$this->isValidModule($moduleName)) {
            throw new InvalidArgumentException("Módulo não suportado: {$moduleName}");
        }

        echo "🔍 Iniciando validação do módulo '{$moduleName}'...\n";

        $this->validationResults = [
            'module' => $moduleName,
            'start_time' => microtime(true),
            'checks' => [],
            'errors' => [],
            'warnings' => [],
            'success' => false
        ];

        // Executa validações em ordem
        $this->validateSyntax($moduleName);
        $this->validateNamespaces($moduleName);
        $this->validateDependencies($moduleName);
        $this->validateArchitecture($moduleName);
        $this->validateInterfaces($moduleName);
        $this->validateRepository($moduleName);
        $this->validateFactory($moduleName);
        $this->validateModule($moduleName);
        $this->validateTests($moduleName);
        $this->validatePerformance($moduleName);

        $this->validationResults['end_time'] = microtime(true);
        $this->validationResults['duration'] = $this->validationResults['end_time'] - $this->validationResults['start_time'];
        $this->validationResults['success'] = empty($this->validationResults['errors']);

        $this->printResults($strict);

        return $this->validationResults;
    }

    private function validateSyntax(string $moduleName): void
    {
        $this->runCheck('syntax', 'Validação de Sintaxe PHP', function () use ($moduleName) {
            $moduleDir = $this->getModuleDirectory($moduleName);
            $phpFiles = $this->findPhpFiles($moduleDir);

            foreach ($phpFiles as $file) {
                $output = [];
                $returnCode = 0;
                exec("php -l \"{$file}\" 2>&1", $output, $returnCode);

                if ($returnCode !== 0) {
                    throw new RuntimeException("Erro de sintaxe em {$file}: " . implode("\n", $output));
                }
            }

            return ['files_checked' => count($phpFiles)];
        });
    }

    private function validateNamespaces(string $moduleName): void
    {
        $this->runCheck('namespaces', 'Validação de Namespaces', function () use ($moduleName) {
            $moduleDir = $this->getModuleDirectory($moduleName);
            $phpFiles = $this->findPhpFiles($moduleDir);
            $issues = [];

            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);

                // Verifica namespace correto
                if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
                    $namespace = $matches[1];
                    $expectedStart = 'Clubify\\Checkout\\Modules\\' . $this->getModuleClassName($moduleName);

                    if (!str_starts_with($namespace, $expectedStart)) {
                        $issues[] = "Namespace incorreto em {$file}: {$namespace}";
                    }
                } else {
                    $issues[] = "Namespace ausente em {$file}";
                }

                // Verifica imports inconsistentes
                $badImports = [
                    'ClubifyCheckout\\Services\\BaseService',
                    'ClubifyCheckout\\Exceptions\\',
                ];

                foreach ($badImports as $badImport) {
                    if (strpos($content, $badImport) !== false) {
                        $issues[] = "Import inconsistente em {$file}: {$badImport}";
                    }
                }
            }

            if (!empty($issues)) {
                throw new RuntimeException("Problemas de namespace encontrados:\n" . implode("\n", $issues));
            }

            return ['files_checked' => count($phpFiles), 'issues' => 0];
        });
    }

    private function validateDependencies(string $moduleName): void
    {
        $this->runCheck('dependencies', 'Validação de Dependências', function () use ($moduleName) {
            $moduleDir = $this->getModuleDirectory($moduleName);
            $phpFiles = $this->findPhpFiles($moduleDir);
            $dependencies = [];

            foreach ($phpFiles as $file) {
                $content = file_get_contents($file);

                // Extrai uses
                preg_match_all('/use\s+([^;]+);/', $content, $matches);
                foreach ($matches[1] as $use) {
                    $dependencies[] = trim($use);
                }
            }

            // Verifica se dependências básicas estão presentes
            $requiredDeps = [
                'Clubify\\Checkout\\Contracts\\ModuleInterface',
                'Clubify\\Checkout\\Core\\Config\\Configuration',
                'Clubify\\Checkout\\Core\\Logger\\Logger'
            ];

            $missing = [];
            foreach ($requiredDeps as $dep) {
                if (!in_array($dep, $dependencies)) {
                    $missing[] = $dep;
                }
            }

            if (!empty($missing)) {
                $this->addWarning("Dependências básicas ausentes: " . implode(', ', $missing));
            }

            return ['total_dependencies' => count(array_unique($dependencies))];
        });
    }

    private function validateArchitecture(string $moduleName): void
    {
        $this->runCheck('architecture', 'Validação de Arquitetura', function () use ($moduleName) {
            $moduleDir = $this->getModuleDirectory($moduleName);
            $expectedStructure = [
                'Contracts',
                'Services',
                'Repositories',
                'Factories',
                'DTOs',
                'Exceptions'
            ];

            $missingDirs = [];
            foreach ($expectedStructure as $dir) {
                if (!is_dir($moduleDir . '/' . $dir)) {
                    $missingDirs[] = $dir;
                }
            }

            if (!empty($missingDirs)) {
                $this->addWarning("Diretórios arquiteturais ausentes: " . implode(', ', $missingDirs));
            }

            return ['expected_dirs' => count($expectedStructure), 'missing_dirs' => count($missingDirs)];
        });
    }

    private function validateInterfaces(string $moduleName): void
    {
        $this->runCheck('interfaces', 'Validação de Interfaces', function () use ($moduleName) {
            $contractsDir = $this->getModuleDirectory($moduleName) . '/Contracts';

            if (!is_dir($contractsDir)) {
                throw new RuntimeException("Diretório Contracts não encontrado");
            }

            $interfaces = $this->findPhpFiles($contractsDir);
            $validInterfaces = 0;

            foreach ($interfaces as $interface) {
                $content = file_get_contents($interface);

                if (strpos($content, 'interface ') === false) {
                    throw new RuntimeException("Arquivo em Contracts não é interface: " . basename($interface));
                }

                // Verifica se estende interface base
                if (strpos($content, 'RepositoryInterface') !== false) {
                    if (strpos($content, 'extends RepositoryInterface') === false) {
                        $this->addWarning("Repository interface deve estender RepositoryInterface base");
                    }
                }

                $validInterfaces++;
            }

            return ['interfaces_found' => $validInterfaces];
        });
    }

    private function validateRepository(string $moduleName): void
    {
        $this->runCheck('repository', 'Validação de Repository', function () use ($moduleName) {
            $repositoriesDir = $this->getModuleDirectory($moduleName) . '/Repositories';

            if (!is_dir($repositoriesDir)) {
                $this->addWarning("Diretório Repositories não encontrado - migração pendente");
                return ['status' => 'pending'];
            }

            $repositories = $this->findPhpFiles($repositoriesDir);
            $validRepositories = 0;

            foreach ($repositories as $repository) {
                $content = file_get_contents($repository);

                // Verifica se estende BaseRepository
                if (strpos($content, 'extends BaseRepository') === false) {
                    throw new RuntimeException("Repository deve estender BaseRepository: " . basename($repository));
                }

                // Verifica se implementa interface
                if (strpos($content, 'implements ') === false) {
                    throw new RuntimeException("Repository deve implementar interface: " . basename($repository));
                }

                $validRepositories++;
            }

            return ['repositories_found' => $validRepositories];
        });
    }

    private function validateFactory(string $moduleName): void
    {
        $this->runCheck('factory', 'Validação de Factory', function () use ($moduleName) {
            $factoriesDir = $this->getModuleDirectory($moduleName) . '/Factories';

            if (!is_dir($factoriesDir)) {
                $this->addWarning("Diretório Factories não encontrado - migração pendente");
                return ['status' => 'pending'];
            }

            $factories = $this->findPhpFiles($factoriesDir);
            $validFactories = 0;

            foreach ($factories as $factory) {
                $content = file_get_contents($factory);

                // Verifica se implementa FactoryInterface
                if (strpos($content, 'implements FactoryInterface') === false) {
                    throw new RuntimeException("Factory deve implementar FactoryInterface: " . basename($factory));
                }

                // Verifica métodos obrigatórios
                $requiredMethods = ['create', 'getSupportedTypes'];
                foreach ($requiredMethods as $method) {
                    if (strpos($content, "function {$method}(") === false) {
                        throw new RuntimeException("Factory deve implementar método {$method}: " . basename($factory));
                    }
                }

                $validFactories++;
            }

            return ['factories_found' => $validFactories];
        });
    }

    private function validateModule(string $moduleName): void
    {
        $this->runCheck('module', 'Validação de Module', function () use ($moduleName) {
            $moduleFile = $this->getModuleDirectory($moduleName) . '/' . $this->getModuleClassName($moduleName) . 'Module.php';

            if (!file_exists($moduleFile)) {
                throw new RuntimeException("Arquivo do módulo não encontrado: {$moduleFile}");
            }

            $content = file_get_contents($moduleFile);

            // Verifica se implementa ModuleInterface
            if (strpos($content, 'implements ModuleInterface') === false) {
                throw new RuntimeException("Module deve implementar ModuleInterface");
            }

            // Verifica métodos obrigatórios
            $requiredMethods = [
                'initialize', 'getName', 'getVersion', 'getDependencies',
                'isAvailable', 'getStatus', 'cleanup', 'isHealthy'
            ];

            foreach ($requiredMethods as $method) {
                if (strpos($content, "function {$method}(") === false) {
                    throw new RuntimeException("Module deve implementar método {$method}");
                }
            }

            // Verifica uso de Factory (se existe)
            $factoriesDir = $this->getModuleDirectory($moduleName) . '/Factories';
            if (is_dir($factoriesDir)) {
                if (strpos($content, 'Factory') === false) {
                    $this->addWarning("Module deve usar Factory Pattern quando disponível");
                }
            }

            return ['status' => 'valid'];
        });
    }

    private function validateTests(string $moduleName): void
    {
        $this->runCheck('tests', 'Validação de Testes', function () use ($moduleName) {
            $testsDir = $this->sdkRoot . '/tests';
            $moduleTestsFound = 0;

            if (is_dir($testsDir)) {
                $testFiles = $this->findPhpFiles($testsDir);

                foreach ($testFiles as $testFile) {
                    if (stripos($testFile, $moduleName) !== false) {
                        $moduleTestsFound++;
                    }
                }
            }

            if ($moduleTestsFound === 0) {
                $this->addWarning("Nenhum teste encontrado para o módulo {$moduleName}");
            }

            return ['tests_found' => $moduleTestsFound];
        });
    }

    private function validatePerformance(string $moduleName): void
    {
        $this->runCheck('performance', 'Validação de Performance', function () use ($moduleName) {
            $moduleDir = $this->getModuleDirectory($moduleName);
            $phpFiles = $this->findPhpFiles($moduleDir);

            $totalSize = 0;
            $largeFiles = [];

            foreach ($phpFiles as $file) {
                $size = filesize($file);
                $totalSize += $size;

                if ($size > 50 * 1024) { // 50KB
                    $largeFiles[] = basename($file) . ' (' . $this->formatBytes($size) . ')';
                }
            }

            if (!empty($largeFiles)) {
                $this->addWarning("Arquivos grandes encontrados: " . implode(', ', $largeFiles));
            }

            return [
                'total_files' => count($phpFiles),
                'total_size' => $this->formatBytes($totalSize),
                'large_files' => count($largeFiles)
            ];
        });
    }

    private function runCheck(string $checkName, string $description, callable $validator): void
    {
        echo "  🔍 {$description}...\n";

        try {
            $startTime = microtime(true);
            $result = $validator();
            $duration = microtime(true) - $startTime;

            $this->validationResults['checks'][$checkName] = [
                'description' => $description,
                'status' => 'passed',
                'duration' => $duration,
                'result' => $result
            ];

            echo "    ✅ Passou (" . round($duration * 1000, 2) . "ms)\n";

        } catch (Exception $e) {
            $this->validationResults['checks'][$checkName] = [
                'description' => $description,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];

            $this->validationResults['errors'][] = "{$description}: " . $e->getMessage();
            echo "    ❌ Falhou: " . $e->getMessage() . "\n";
        }
    }

    private function addWarning(string $message): void
    {
        $this->validationResults['warnings'][] = $message;
        echo "    ⚠️  {$message}\n";
    }

    private function printResults(bool $strict): void
    {
        echo "\n📊 Resultados da Validação:\n";
        echo "==========================================\n";

        $totalChecks = count($this->validationResults['checks']);
        $passedChecks = count(array_filter($this->validationResults['checks'], fn($check) => $check['status'] === 'passed'));
        $failedChecks = $totalChecks - $passedChecks;

        echo "📈 Checks: {$passedChecks}/{$totalChecks} passaram\n";
        echo "⏱️  Duração: " . round($this->validationResults['duration'], 2) . "s\n";
        echo "⚠️  Warnings: " . count($this->validationResults['warnings']) . "\n";
        echo "❌ Erros: " . count($this->validationResults['errors']) . "\n";

        if (!empty($this->validationResults['warnings'])) {
            echo "\n⚠️  Warnings:\n";
            foreach ($this->validationResults['warnings'] as $warning) {
                echo "  - {$warning}\n";
            }
        }

        if (!empty($this->validationResults['errors'])) {
            echo "\n❌ Erros:\n";
            foreach ($this->validationResults['errors'] as $error) {
                echo "  - {$error}\n";
            }
        }

        echo "\n";

        if ($this->validationResults['success']) {
            echo "✅ Módulo validado com sucesso!\n";
        } else {
            echo "❌ Módulo falhou na validação!\n";

            if ($strict) {
                echo "🚨 Modo strict ativado - migração deve ser interrompida!\n";
                exit(1);
            }
        }
    }

    private function isValidModule(string $moduleName): bool
    {
        return in_array($moduleName, $this->supportedModules);
    }

    private function getModuleDirectory(string $moduleName): string
    {
        return $this->sdkRoot . '/src/Modules/' . $this->getModuleClassName($moduleName);
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
            'subscriptions' => 'Subscriptions',
            'usermanagement' => 'UserManagement'
        ];

        return $moduleMap[$moduleName] ?? ucfirst($moduleName);
    }

    private function findPhpFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $phpFiles = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $phpFiles[] = $file->getRealPath();
            }
        }

        return $phpFiles;
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
    $moduleName = $argv[1] ?? '';
    $strict = in_array('--strict', $argv);

    if (empty($moduleName)) {
        echo "❌ Uso: php validate_module.php <module_name> [--strict]\n";
        echo "Módulos disponíveis: customers, products, orders, payments, webhooks, notifications, tracking, subscriptions\n";
        exit(1);
    }

    $validator = new ModuleValidator();

    try {
        $validator->validate($moduleName, $strict);
    } catch (Exception $e) {
        echo "❌ Erro: " . $e->getMessage() . "\n";
        exit(1);
    }
}