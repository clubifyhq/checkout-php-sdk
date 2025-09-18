<?php

/**
 * Module Validation Script - Clubify Checkout SDK
 *
 * Este script valida se um módulo foi implementado corretamente
 * seguindo a arquitetura híbrida e os padrões estabelecidos.
 *
 * USAGE:
 * php docs/scripts/validate_module.php [ModuleName]
 * php docs/scripts/validate_module.php UserManagement
 */

declare(strict_types=1);

class ModuleValidator
{
    private string $basePath;
    private string $moduleName;
    private array $results = [];
    private array $warnings = [];
    private array $errors = [];

    public function __construct(string $moduleName)
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->moduleName = $moduleName;
    }

    public function run(): void
    {
        echo "🔍 Module Validation Tool - {$this->moduleName}\n";
        echo str_repeat("=", 50) . "\n\n";

        try {
            $this->validateModuleExists();
            $this->validateDirectoryStructure();
            $this->validateClassImplementations();
            $this->validateInterfaceCompliance();
            $this->validateTestCoverage();
            $this->validateNamingConventions();
            $this->validateDocumentation();
            $this->validateSDKIntegration();

            $this->printResults();

        } catch (Exception $e) {
            echo "❌ Validation failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function validateModuleExists(): void
    {
        $modulePath = $this->basePath . '/src/Modules/' . $this->moduleName;

        if (!is_dir($modulePath)) {
            throw new Exception("Module '{$this->moduleName}' does not exist at: {$modulePath}");
        }

        $this->results[] = "✅ Module directory exists";
    }

    private function validateDirectoryStructure(): void
    {
        echo "📁 Validating directory structure...\n";

        $requiredDirectories = [
            "src/Modules/{$this->moduleName}",
            "src/Modules/{$this->moduleName}/Contracts",
            "src/Modules/{$this->moduleName}/Services",
            "src/Modules/{$this->moduleName}/Repositories",
            "src/Modules/{$this->moduleName}/Factories",
            "src/Modules/{$this->moduleName}/DTOs",
            "src/Modules/{$this->moduleName}/Exceptions"
        ];

        foreach ($requiredDirectories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->results[] = "✅ Directory exists: {$dir}";
            } else {
                $this->warnings[] = "⚠️  Directory missing: {$dir}";
            }
        }

        // Check test directories
        $testDirectories = [
            "tests/Unit/{$this->moduleName}",
            "tests/Unit/{$this->moduleName}/Services",
            "tests/Unit/{$this->moduleName}/Repositories",
            "tests/Unit/{$this->moduleName}/Factories"
        ];

        foreach ($testDirectories as $dir) {
            $fullPath = $this->basePath . '/' . $dir;
            if (is_dir($fullPath)) {
                $this->results[] = "✅ Test directory exists: {$dir}";
            } else {
                $this->warnings[] = "⚠️  Test directory missing: {$dir}";
            }
        }
    }

    private function validateClassImplementations(): void
    {
        echo "🔧 Validating class implementations...\n";

        $this->validateModuleClass();
        $this->validateServiceClass();
        $this->validateRepositoryClass();
        $this->validateFactoryClass();
        $this->validateDTOClass();
        $this->validateExceptionClasses();
    }

    private function validateModuleClass(): void
    {
        $moduleFile = $this->basePath . "/src/Modules/{$this->moduleName}/{$this->moduleName}Module.php";

        if (!file_exists($moduleFile)) {
            $this->errors[] = "❌ Module class not found: {$this->moduleName}Module.php";
            return;
        }

        $content = file_get_contents($moduleFile);

        // Check class declaration
        if (strpos($content, "class {$this->moduleName}Module") === false) {
            $this->errors[] = "❌ Module class not properly declared";
        } else {
            $this->results[] = "✅ Module class exists and is properly declared";
        }

        // Check interface implementation
        if (strpos($content, 'implements ModuleInterface') === false) {
            $this->errors[] = "❌ Module class does not implement ModuleInterface";
        } else {
            $this->results[] = "✅ Module class implements ModuleInterface";
        }

        // Check required methods
        $requiredMethods = [
            'initialize',
            'isInitialized',
            'getName',
            'getVersion',
            'getDependencies',
            'isAvailable',
            'getStatus',
            'cleanup',
            'isHealthy'
        ];

        foreach ($requiredMethods as $method) {
            if (strpos($content, "public function {$method}(") === false) {
                $this->warnings[] = "⚠️  Module class missing method: {$method}";
            } else {
                $this->results[] = "✅ Module class has method: {$method}";
            }
        }
    }

    private function validateServiceClass(): void
    {
        $serviceFiles = glob($this->basePath . "/src/Modules/{$this->moduleName}/Services/*Service.php");

        if (empty($serviceFiles)) {
            $this->warnings[] = "⚠️  No service classes found";
            return;
        }

        foreach ($serviceFiles as $serviceFile) {
            $content = file_get_contents($serviceFile);
            $className = basename($serviceFile, '.php');

            // Check ServiceInterface implementation
            if (strpos($content, 'implements ServiceInterface') === false) {
                $this->errors[] = "❌ {$className} does not implement ServiceInterface";
            } else {
                $this->results[] = "✅ {$className} implements ServiceInterface";
            }

            // Check required methods
            $requiredMethods = ['getName', 'getVersion', 'isHealthy', 'getMetrics'];
            foreach ($requiredMethods as $method) {
                if (strpos($content, "public function {$method}(") === false) {
                    $this->warnings[] = "⚠️  {$className} missing ServiceInterface method: {$method}";
                } else {
                    $this->results[] = "✅ {$className} has method: {$method}";
                }
            }

            // Check dependency injection
            if (strpos($content, '__construct(') !== false && strpos($content, 'private') !== false) {
                $this->results[] = "✅ {$className} uses dependency injection";
            } else {
                $this->warnings[] = "⚠️  {$className} might not use proper dependency injection";
            }
        }
    }

    private function validateRepositoryClass(): void
    {
        $repositoryFiles = glob($this->basePath . "/src/Modules/{$this->moduleName}/Repositories/*Repository.php");

        if (empty($repositoryFiles)) {
            $this->warnings[] = "⚠️  No repository classes found";
            return;
        }

        foreach ($repositoryFiles as $repositoryFile) {
            $content = file_get_contents($repositoryFile);
            $className = basename($repositoryFile, '.php');

            // Check if extends BaseRepository
            if (strpos($content, 'extends BaseRepository') === false) {
                $this->warnings[] = "⚠️  {$className} does not extend BaseRepository";
            } else {
                $this->results[] = "✅ {$className} extends BaseRepository";
            }

            // Check interface implementation
            $interfaceName = str_replace('Api', '', str_replace('Cache', '', $className));
            $interfaceName = str_replace('Repository', 'RepositoryInterface', $interfaceName);

            if (strpos($content, "implements {$interfaceName}") === false) {
                $this->warnings[] = "⚠️  {$className} might not implement proper interface";
            } else {
                $this->results[] = "✅ {$className} implements repository interface";
            }

            // Check required abstract method implementations
            $requiredMethods = ['getEndpoint', 'getResourceName', 'getServiceName'];
            foreach ($requiredMethods as $method) {
                if (strpos($content, "function {$method}(") === false) {
                    $this->warnings[] = "⚠️  {$className} missing method: {$method}";
                } else {
                    $this->results[] = "✅ {$className} implements: {$method}";
                }
            }
        }
    }

    private function validateFactoryClass(): void
    {
        $factoryFile = $this->basePath . "/src/Modules/{$this->moduleName}/Factories/{$this->moduleName}ServiceFactory.php";

        if (!file_exists($factoryFile)) {
            $this->warnings[] = "⚠️  Factory class not found: {$this->moduleName}ServiceFactory.php";
            return;
        }

        $content = file_get_contents($factoryFile);

        // Check interface implementation
        if (strpos($content, 'implements FactoryInterface') === false) {
            $this->errors[] = "❌ Factory class does not implement FactoryInterface";
        } else {
            $this->results[] = "✅ Factory class implements FactoryInterface";
        }

        // Check required methods
        $requiredMethods = ['create', 'getSupportedTypes'];
        foreach ($requiredMethods as $method) {
            if (strpos($content, "public function {$method}(") === false) {
                $this->warnings[] = "⚠️  Factory class missing method: {$method}";
            } else {
                $this->results[] = "✅ Factory class has method: {$method}";
            }
        }

        // Check dependency injection
        if (strpos($content, '__construct(') !== false) {
            $this->results[] = "✅ Factory class uses dependency injection";
        } else {
            $this->warnings[] = "⚠️  Factory class might not use dependency injection";
        }
    }

    private function validateDTOClass(): void
    {
        $dtoFiles = glob($this->basePath . "/src/Modules/{$this->moduleName}/DTOs/*Data.php");

        if (empty($dtoFiles)) {
            $this->warnings[] = "⚠️  No DTO classes found";
            return;
        }

        foreach ($dtoFiles as $dtoFile) {
            $content = file_get_contents($dtoFile);
            $className = basename($dtoFile, '.php');

            // Check required methods
            $requiredMethods = ['validate', 'toArray', 'fromArray'];
            foreach ($requiredMethods as $method) {
                if (strpos($content, "function {$method}(") === false) {
                    $this->warnings[] = "⚠️  {$className} missing method: {$method}";
                } else {
                    $this->results[] = "✅ {$className} has method: {$method}";
                }
            }

            // Check for validation logic
            if (strpos($content, 'ValidationException') !== false) {
                $this->results[] = "✅ {$className} has validation logic";
            } else {
                $this->warnings[] = "⚠️  {$className} might not have proper validation";
            }
        }
    }

    private function validateExceptionClasses(): void
    {
        $expectedExceptions = [
            "{$this->getEntityName()}NotFoundException.php",
            "{$this->getEntityName()}ValidationException.php"
        ];

        foreach ($expectedExceptions as $exceptionFile) {
            $fullPath = $this->basePath . "/src/Modules/{$this->moduleName}/Exceptions/{$exceptionFile}";

            if (file_exists($fullPath)) {
                $this->results[] = "✅ Exception class exists: {$exceptionFile}";

                $content = file_get_contents($fullPath);
                if (strpos($content, 'extends Exception') !== false ||
                    strpos($content, 'extends \Exception') !== false) {
                    $this->results[] = "✅ Exception class extends Exception properly";
                } else {
                    $this->warnings[] = "⚠️  Exception class might not extend Exception";
                }
            } else {
                $this->warnings[] = "⚠️  Exception class missing: {$exceptionFile}";
            }
        }
    }

    private function validateInterfaceCompliance(): void
    {
        echo "🔗 Validating interface compliance...\n";

        $this->validateRepositoryInterface();
        $this->validateServiceInterface();
    }

    private function validateRepositoryInterface(): void
    {
        $interfaceFile = $this->basePath . "/src/Modules/{$this->moduleName}/Contracts/{$this->getEntityName()}RepositoryInterface.php";

        if (!file_exists($interfaceFile)) {
            $this->warnings[] = "⚠️  Repository interface not found";
            return;
        }

        $content = file_get_contents($interfaceFile);

        // Check extends RepositoryInterface
        if (strpos($content, 'extends RepositoryInterface') === false) {
            $this->errors[] = "❌ Repository interface does not extend RepositoryInterface";
        } else {
            $this->results[] = "✅ Repository interface extends RepositoryInterface";
        }

        // Check for domain-specific methods
        $commonMethods = ['findBy', 'updateStatus', 'getStats'];
        $foundMethods = 0;

        foreach ($commonMethods as $methodPattern) {
            if (strpos($content, $methodPattern) !== false) {
                $foundMethods++;
            }
        }

        if ($foundMethods > 0) {
            $this->results[] = "✅ Repository interface has domain-specific methods ({$foundMethods} found)";
        } else {
            $this->warnings[] = "⚠️  Repository interface might lack domain-specific methods";
        }
    }

    private function validateServiceInterface(): void
    {
        $serviceFiles = glob($this->basePath . "/src/Modules/{$this->moduleName}/Services/*Service.php");

        foreach ($serviceFiles as $serviceFile) {
            $content = file_get_contents($serviceFile);
            $className = basename($serviceFile, '.php');

            // Check for business logic methods
            $businessMethods = ['create', 'get', 'update', 'delete', 'list'];
            $foundMethods = 0;

            foreach ($businessMethods as $methodPattern) {
                if (preg_match("/public function {$methodPattern}\w*\(/", $content)) {
                    $foundMethods++;
                }
            }

            if ($foundMethods >= 3) {
                $this->results[] = "✅ {$className} has comprehensive business methods ({$foundMethods} CRUD methods)";
            } else {
                $this->warnings[] = "⚠️  {$className} might lack comprehensive business methods";
            }
        }
    }

    private function validateTestCoverage(): void
    {
        echo "🧪 Validating test coverage...\n";

        $testFiles = [
            "tests/Unit/{$this->moduleName}/Services/{$this->getEntityName()}ServiceTest.php",
            "tests/Unit/{$this->moduleName}/Repositories/Api{$this->getEntityName()}RepositoryTest.php",
            "tests/Unit/{$this->moduleName}/Factories/{$this->moduleName}ServiceFactoryTest.php",
            "tests/Integration/{$this->moduleName}IntegrationTest.php"
        ];

        $testCoverage = 0;
        foreach ($testFiles as $testFile) {
            $fullPath = $this->basePath . '/' . $testFile;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);

                // Check if test has actual test methods (not just placeholder)
                if (strpos($content, 'markTestIncomplete') === false &&
                    preg_match('/public function test\w+\(\)/', $content)) {
                    $this->results[] = "✅ Test implemented: " . basename($testFile);
                    $testCoverage++;
                } else {
                    $this->warnings[] = "⚠️  Test exists but not implemented: " . basename($testFile);
                }
            } else {
                $this->warnings[] = "⚠️  Test file missing: " . basename($testFile);
            }
        }

        $coveragePercentage = ($testCoverage / count($testFiles)) * 100;
        if ($coveragePercentage >= 75) {
            $this->results[] = "✅ Good test coverage: {$coveragePercentage}%";
        } else {
            $this->warnings[] = "⚠️  Low test coverage: {$coveragePercentage}%";
        }
    }

    private function validateNamingConventions(): void
    {
        echo "📝 Validating naming conventions...\n";

        // Check module name follows PascalCase
        if (preg_match('/^[A-Z][a-zA-Z0-9]*$/', $this->moduleName)) {
            $this->results[] = "✅ Module name follows PascalCase convention";
        } else {
            $this->errors[] = "❌ Module name does not follow PascalCase convention";
        }

        // Check file naming consistency
        $moduleFile = "/src/Modules/{$this->moduleName}/{$this->moduleName}Module.php";
        if (file_exists($this->basePath . $moduleFile)) {
            $this->results[] = "✅ Module file follows naming convention";
        }

        $factoryFile = "/src/Modules/{$this->moduleName}/Factories/{$this->moduleName}ServiceFactory.php";
        if (file_exists($this->basePath . $factoryFile)) {
            $this->results[] = "✅ Factory file follows naming convention";
        }
    }

    private function validateDocumentation(): void
    {
        echo "📖 Validating documentation...\n";

        // Check for PHPDoc comments in main classes
        $mainFiles = [
            "/src/Modules/{$this->moduleName}/{$this->moduleName}Module.php",
            "/src/Modules/{$this->moduleName}/Services/{$this->getEntityName()}Service.php"
        ];

        foreach ($mainFiles as $file) {
            $fullPath = $this->basePath . $file;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);

                // Check for class-level documentation
                if (strpos($content, '/**') !== false && strpos($content, '* @') !== false) {
                    $this->results[] = "✅ Documentation found in: " . basename($file);
                } else {
                    $this->warnings[] = "⚠️  Missing documentation in: " . basename($file);
                }
            }
        }
    }

    private function validateSDKIntegration(): void
    {
        echo "🔗 Validating SDK integration...\n";

        $sdkFile = $this->basePath . '/src/ClubifyCheckoutSDK.php';
        if (!file_exists($sdkFile)) {
            $this->warnings[] = "⚠️  SDK main class not found";
            return;
        }

        $content = file_get_contents($sdkFile);

        // Check for module import
        if (strpos($content, "use Clubify\\Checkout\\Modules\\{$this->moduleName}\\{$this->moduleName}Module;") !== false) {
            $this->results[] = "✅ Module properly imported in SDK";
        } else {
            $this->warnings[] = "⚠️  Module not imported in SDK";
        }

        // Check for module method
        $entityName = lcfirst($this->getEntityName());
        if (strpos($content, "function {$entityName}Management()") !== false) {
            $this->results[] = "✅ Module accessor method exists in SDK";
        } else {
            $this->warnings[] = "⚠️  Module accessor method missing in SDK";
        }

        // Check for factory method
        if (strpos($content, "create{$this->moduleName}ServiceFactory()") !== false) {
            $this->results[] = "✅ Factory method exists in SDK";
        } else {
            $this->warnings[] = "⚠️  Factory method missing in SDK";
        }
    }

    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 VALIDATION RESULTS FOR {$this->moduleName}\n";
        echo str_repeat("=", 60) . "\n\n";

        if (!empty($this->results)) {
            echo "✅ SUCCESSES (" . count($this->results) . "):\n";
            foreach ($this->results as $result) {
                echo "   {$result}\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "   {$warning}\n";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "   {$error}\n";
            }
            echo "\n";
        }

        // Calculate scores
        $total = count($this->results) + count($this->warnings) + count($this->errors);
        $successRate = count($this->results) / $total * 100;

        echo str_repeat("-", 60) . "\n";
        echo "📈 SUMMARY:\n";
        echo "   Total checks: {$total}\n";
        echo "   Successes: " . count($this->results) . "\n";
        echo "   Warnings: " . count($this->warnings) . "\n";
        echo "   Errors: " . count($this->errors) . "\n";
        echo "   Success rate: " . number_format($successRate, 1) . "%\n\n";

        if (empty($this->errors) && count($this->warnings) <= 3) {
            echo "🎉 MODULE VALIDATION PASSED!\n";
            echo "   Your module follows the architectural standards.\n";
            echo "   Address any warnings to improve quality.\n\n";
            exit(0);
        } elseif (empty($this->errors)) {
            echo "⚠️  MODULE VALIDATION PASSED WITH WARNINGS!\n";
            echo "   Your module works but has some issues to address.\n";
            echo "   Review the warnings above for improvements.\n\n";
            exit(0);
        } else {
            echo "❌ MODULE VALIDATION FAILED!\n";
            echo "   Your module has critical errors that need fixing.\n";
            echo "   Address all errors before using this module.\n\n";
            exit(1);
        }
    }

    private function getEntityName(): string
    {
        // Extract entity name from module name
        // Examples: UserManagement -> User, OrderManagement -> Order
        $entityName = str_replace('Management', '', $this->moduleName);
        return $entityName;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php validate_module.php [ModuleName]\n";
        echo "Example: php validate_module.php UserManagement\n";
        exit(1);
    }

    $moduleName = $argv[1];
    $validator = new ModuleValidator($moduleName);
    $validator->run();
} else {
    echo "This script must be run from command line\n";
    exit(1);
}