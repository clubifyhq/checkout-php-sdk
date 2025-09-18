<?php

/**
 * Phase 4 Validation Script - Clubify Checkout SDK
 *
 * Este script valida se todos os componentes da Fase 4 foram criados corretamente
 * e seguem os padrões estabelecidos na arquitetura híbrida.
 *
 * USAGE:
 * php docs/scripts/validate_phase4.php
 */

declare(strict_types=1);

class Phase4Validator
{
    private string $basePath;
    private array $errors = [];
    private array $warnings = [];
    private array $successes = [];
    private int $totalChecks = 0;

    private array $requiredFiles = [
        // Templates
        'docs/templates/ModuleTemplate.php' => 'Module Template',
        'docs/templates/RepositoryInterfaceTemplate.php' => 'Repository Interface Template',
        'docs/templates/RepositoryImplementationTemplate.php' => 'Repository Implementation Template',
        'docs/templates/ServiceTemplate.php' => 'Service Template',
        'docs/templates/FactoryTemplate.php' => 'Factory Template',
        'docs/templates/DTOTemplate.php' => 'DTO Template',
        'docs/templates/NotFoundExceptionTemplate.php' => 'NotFoundException Template',
        'docs/templates/ValidationExceptionTemplate.php' => 'ValidationException Template',

        // Documentation
        'docs/ARCHITECTURE.md' => 'Architecture Documentation',
        'docs/guides/DEVELOPMENT_GUIDELINES.md' => 'Development Guidelines',
        'docs/examples/PRACTICAL_EXAMPLES.md' => 'Practical Examples',
        'docs/API_CONTRACTS.md' => 'API Contracts Documentation',
        'docs/guides/MIGRATION_GUIDE.md' => 'Migration Guide',

        // Scripts
        'docs/scripts/scaffold_module.php' => 'Module Scaffolding Script',
        'docs/scripts/validate_module.php' => 'Module Validation Script'
    ];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
    }

    public function run(): void
    {
        echo "🔍 Phase 4 Validation - Clubify Checkout SDK\n";
        echo "===========================================\n\n";

        $this->validateFileStructure();
        $this->validateTemplateQuality();
        $this->validateDocumentationQuality();
        $this->validateScriptFunctionality();
        $this->validateArchitecturalCompliance();
        $this->showResults();
    }

    private function validateFileStructure(): void
    {
        echo "📁 Validating file structure...\n";

        foreach ($this->requiredFiles as $file => $description) {
            $this->totalChecks++;
            $fullPath = $this->basePath . '/' . $file;

            if (file_exists($fullPath)) {
                $this->successes[] = "✅ {$description} exists";
            } else {
                $this->errors[] = "❌ {$description} missing at {$file}";
            }
        }

        // Check directory structure
        $requiredDirs = [
            'docs/templates',
            'docs/guides',
            'docs/examples',
            'docs/scripts'
        ];

        foreach ($requiredDirs as $dir) {
            $this->totalChecks++;
            $fullPath = $this->basePath . '/' . $dir;

            if (is_dir($fullPath)) {
                $this->successes[] = "✅ Directory {$dir} exists";
            } else {
                $this->errors[] = "❌ Directory {$dir} missing";
            }
        }

        echo "   File structure validation completed.\n\n";
    }

    private function validateTemplateQuality(): void
    {
        echo "🔧 Validating template quality...\n";

        $templateChecks = [
            'ModuleTemplate.php' => [
                'implements ModuleInterface',
                'initialize() method',
                'getService() method',
                'getFactory() method'
            ],
            'RepositoryInterfaceTemplate.php' => [
                'extends RepositoryInterface',
                'find{Entity}ById',
                'get{Entity}List',
                'create{Entity}'
            ],
            'RepositoryImplementationTemplate.php' => [
                'extends BaseRepository',
                'implements {Entity}RepositoryInterface',
                'httpClient usage',
                'cacheGet usage'
            ],
            'ServiceTemplate.php' => [
                'implements ServiceInterface',
                'constructor injection',
                'business logic methods',
                'error handling'
            ],
            'FactoryTemplate.php' => [
                'singleton pattern',
                'createService method',
                'dependency injection',
                'getInstance method'
            ]
        ];

        foreach ($templateChecks as $template => $patterns) {
            $templatePath = $this->basePath . '/docs/templates/' . $template;

            if (!file_exists($templatePath)) {
                continue;
            }

            $content = file_get_contents($templatePath);

            foreach ($patterns as $pattern) {
                $this->totalChecks++;
                if (strpos($content, str_replace(['{Entity}', '{ModuleName}'], ['Entity', 'ModuleName'], $pattern)) !== false) {
                    $this->successes[] = "✅ Template {$template} contains {$pattern}";
                } else {
                    $this->warnings[] = "⚠️ Template {$template} missing pattern: {$pattern}";
                }
            }
        }

        echo "   Template quality validation completed.\n\n";
    }

    private function validateDocumentationQuality(): void
    {
        echo "📖 Validating documentation quality...\n";

        $documentationChecks = [
            'docs/ARCHITECTURE.md' => [
                'Repository Pattern',
                'Factory Pattern',
                'Service Pattern',
                'Dependency Injection',
                'Clean Architecture'
            ],
            'docs/guides/DEVELOPMENT_GUIDELINES.md' => [
                'SOLID Principles',
                'Naming Conventions',
                'Testing Standards',
                'Code Review',
                'Best Practices'
            ],
            'docs/examples/PRACTICAL_EXAMPLES.md' => [
                'OrderManagement',
                'Repository Implementation',
                'Service Usage',
                'Factory Usage',
                'Complete Example'
            ],
            'docs/API_CONTRACTS.md' => [
                'ModuleInterface',
                'RepositoryInterface',
                'ServiceInterface',
                'HTTP Endpoints',
                'Response Formats'
            ],
            'docs/guides/MIGRATION_GUIDE.md' => [
                'Migration Strategy',
                'Backward Compatibility',
                'Feature Flags',
                'Step-by-Step',
                'Testing Strategy'
            ]
        ];

        foreach ($documentationChecks as $doc => $topics) {
            $docPath = $this->basePath . '/' . $doc;

            if (!file_exists($docPath)) {
                continue;
            }

            $content = file_get_contents($docPath);

            foreach ($topics as $topic) {
                $this->totalChecks++;
                if (stripos($content, $topic) !== false) {
                    $this->successes[] = "✅ Documentation {$doc} covers {$topic}";
                } else {
                    $this->warnings[] = "⚠️ Documentation {$doc} should cover {$topic}";
                }
            }

            // Check minimum content length
            $this->totalChecks++;
            if (strlen($content) > 5000) {
                $this->successes[] = "✅ Documentation {$doc} has comprehensive content";
            } else {
                $this->warnings[] = "⚠️ Documentation {$doc} might need more content";
            }
        }

        echo "   Documentation quality validation completed.\n\n";
    }

    private function validateScriptFunctionality(): void
    {
        echo "🛠️ Validating script functionality...\n";

        // Validate scaffold_module.php
        $scaffoldPath = $this->basePath . '/docs/scripts/scaffold_module.php';
        if (file_exists($scaffoldPath)) {
            $this->totalChecks++;
            $content = file_get_contents($scaffoldPath);

            $requiredFeatures = [
                'ModuleScaffolder class',
                'gatherRequirements method',
                'createModuleStructure method',
                'generateClasses method',
                'updateSDK method'
            ];

            $hasAllFeatures = true;
            foreach ($requiredFeatures as $feature) {
                if (strpos($content, $feature) === false) {
                    $hasAllFeatures = false;
                    break;
                }
            }

            if ($hasAllFeatures) {
                $this->successes[] = "✅ Scaffold script has all required features";
            } else {
                $this->errors[] = "❌ Scaffold script missing required features";
            }
        }

        // Validate validate_module.php
        $validatePath = $this->basePath . '/docs/scripts/validate_module.php';
        if (file_exists($validatePath)) {
            $this->totalChecks++;
            $content = file_get_contents($validatePath);

            if (strpos($content, 'ModuleValidator') !== false &&
                strpos($content, 'validateModule') !== false) {
                $this->successes[] = "✅ Module validation script is functional";
            } else {
                $this->errors[] = "❌ Module validation script incomplete";
            }
        }

        echo "   Script functionality validation completed.\n\n";
    }

    private function validateArchitecturalCompliance(): void
    {
        echo "🏗️ Validating architectural compliance...\n";

        // Check if existing modules follow the patterns
        $existingModules = glob($this->basePath . '/src/Modules/*', GLOB_ONLYDIR);

        foreach ($existingModules as $moduleDir) {
            $moduleName = basename($moduleDir);
            $this->totalChecks++;

            // Check if module has the required structure
            $requiredStructure = [
                $moduleDir . '/' . $moduleName . 'Module.php',
                $moduleDir . '/Contracts',
                $moduleDir . '/Services',
                $moduleDir . '/Repositories',
                $moduleDir . '/Factories'
            ];

            $hasStructure = true;
            foreach ($requiredStructure as $item) {
                if (!file_exists($item)) {
                    $hasStructure = false;
                    break;
                }
            }

            if ($hasStructure) {
                $this->successes[] = "✅ Module {$moduleName} follows architectural structure";
            } else {
                $this->warnings[] = "⚠️ Module {$moduleName} needs architectural updates (migration available)";
            }
        }

        // Validate SDK integration
        $sdkPath = $this->basePath . '/src/ClubifyCheckoutSDK.php';
        if (file_exists($sdkPath)) {
            $this->totalChecks++;
            $sdkContent = file_get_contents($sdkPath);

            if (strpos($sdkContent, 'ModuleInterface') !== false) {
                $this->successes[] = "✅ SDK integrates with module system";
            } else {
                $this->warnings[] = "⚠️ SDK needs integration with new module system";
            }
        }

        echo "   Architectural compliance validation completed.\n\n";
    }

    private function showResults(): void
    {
        echo "📊 PHASE 4 VALIDATION RESULTS\n";
        echo "============================\n\n";

        $successCount = count($this->successes);
        $warningCount = count($this->warnings);
        $errorCount = count($this->errors);

        echo "✅ Successes: {$successCount}\n";
        echo "⚠️ Warnings: {$warningCount}\n";
        echo "❌ Errors: {$errorCount}\n";
        echo "Total Checks: {$this->totalChecks}\n\n";

        // Show detailed results
        if (!empty($this->errors)) {
            echo "🚨 ERRORS (Must be fixed):\n";
            foreach ($this->errors as $error) {
                echo "   {$error}\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠️ WARNINGS (Recommended to fix):\n";
            foreach ($this->warnings as $warning) {
                echo "   {$warning}\n";
            }
            echo "\n";
        }

        // Calculate compliance score
        $totalIssues = $errorCount + $warningCount;
        $complianceScore = $this->totalChecks > 0 ?
            round(($successCount / $this->totalChecks) * 100, 1) : 0;

        echo "📈 PHASE 4 COMPLIANCE SCORE: {$complianceScore}%\n\n";

        if ($complianceScore >= 95) {
            echo "🎉 EXCELLENT! Phase 4 is fully compliant.\n";
        } elseif ($complianceScore >= 85) {
            echo "✅ GOOD! Phase 4 is mostly compliant with minor issues.\n";
        } elseif ($complianceScore >= 70) {
            echo "⚠️ ACCEPTABLE! Phase 4 needs some improvements.\n";
        } else {
            echo "❌ NEEDS WORK! Phase 4 has significant issues.\n";
        }

        echo "\n🔧 NEXT STEPS:\n";
        echo "   1. Address any errors listed above\n";
        echo "   2. Consider fixing warnings for better quality\n";
        echo "   3. Run module validation: php docs/scripts/validate_module.php <ModuleName>\n";
        echo "   4. Use scaffolding tool: php docs/scripts/scaffold_module.php\n";
        echo "   5. Follow migration guide for existing modules\n\n";

        echo "📖 DOCUMENTATION INDEX:\n";
        echo "   • Architecture: docs/ARCHITECTURE.md\n";
        echo "   • Guidelines: docs/guides/DEVELOPMENT_GUIDELINES.md\n";
        echo "   • Examples: docs/examples/PRACTICAL_EXAMPLES.md\n";
        echo "   • API Contracts: docs/API_CONTRACTS.md\n";
        echo "   • Migration: docs/guides/MIGRATION_GUIDE.md\n\n";

        // Exit with appropriate code
        if ($errorCount > 0) {
            echo "❌ Validation completed with errors. Exit code: 1\n";
            exit(1);
        } elseif ($warningCount > 0) {
            echo "⚠️ Validation completed with warnings. Exit code: 0\n";
            exit(0);
        } else {
            echo "✅ Validation completed successfully. Exit code: 0\n";
            exit(0);
        }
    }
}

// Run the validator
if (php_sapi_name() === 'cli') {
    $validator = new Phase4Validator();
    $validator->run();
} else {
    echo "This script must be run from command line\n";
    exit(1);
}