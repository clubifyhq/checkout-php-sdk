<?php

/**
 * Module Scaffolding Script - Clubify Checkout SDK
 *
 * Este script automatiza a criação de novos módulos seguindo a arquitetura híbrida.
 * Cria todas as classes necessárias baseadas nos templates.
 *
 * USAGE:
 * php docs/scripts/scaffold_module.php
 *
 * O script irá solicitar:
 * - Nome do módulo (ex: OrderManagement)
 * - Nome da entidade (ex: Order)
 * - Campos específicos da entidade
 * - Métodos específicos do domínio
 */

declare(strict_types=1);

class ModuleScaffolder
{
    private string $basePath;
    private string $templatesPath;
    private array $replacements = [];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__, 2);
        $this->templatesPath = dirname(__DIR__) . '/templates';
    }

    public function run(): void
    {
        echo "🏗️  Module Scaffolding Tool - Clubify Checkout SDK\n";
        echo "==================================================\n\n";

        try {
            $this->gatherRequirements();
            $this->validateRequirements();
            $this->confirmCreation();
            $this->createModuleStructure();
            $this->generateClasses();
            $this->generateTests();
            $this->updateSDK();
            $this->showCompletionMessage();

        } catch (Exception $e) {
            echo "❌ Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function gatherRequirements(): void
    {
        echo "📝 Please provide the following information:\n\n";

        // Module Name
        $moduleName = $this->prompt('Module Name (ex: OrderManagement)');
        if (!$this->isValidClassName($moduleName)) {
            throw new Exception("Invalid module name. Use PascalCase (ex: OrderManagement)");
        }

        // Entity Name
        $entityName = $this->prompt('Entity Name (ex: Order)');
        if (!$this->isValidClassName($entityName)) {
            throw new Exception("Invalid entity name. Use PascalCase (ex: Order)");
        }

        // Additional fields
        $fields = $this->promptFields();

        // Additional methods
        $methods = $this->promptMethods();

        $this->replacements = [
            '{ModuleName}' => $moduleName,
            '{Entity}' => $entityName,
            '{entity}' => strtolower($entityName),
            '{Field}' => $this->suggestField($fields),
            '{field}' => strtolower($this->suggestField($fields)),
            '{Fields}' => $fields,
            '{Methods}' => $methods
        ];

        echo "\n✅ Requirements gathered successfully!\n\n";
    }

    private function validateRequirements(): void
    {
        $modulePath = $this->basePath . '/src/Modules/' . $this->replacements['{ModuleName}'];

        if (is_dir($modulePath)) {
            throw new Exception("Module '{$this->replacements['{ModuleName}']}' already exists!");
        }

        if (!is_dir($this->templatesPath)) {
            throw new Exception("Templates directory not found: {$this->templatesPath}");
        }
    }

    private function confirmCreation(): void
    {
        echo "📋 Module Configuration:\n";
        echo "   Module Name: {$this->replacements['{ModuleName}']}\n";
        echo "   Entity Name: {$this->replacements['{Entity}']}\n";
        echo "   Entity Variable: {$this->replacements['{entity}']}\n\n";

        $confirm = $this->prompt('Create this module? (y/n)', 'y');
        if (strtolower($confirm) !== 'y') {
            echo "❌ Module creation cancelled.\n";
            exit(0);
        }
    }

    private function createModuleStructure(): void
    {
        echo "📁 Creating module directory structure...\n";

        $moduleName = $this->replacements['{ModuleName}'];
        $basePath = $this->basePath . '/src/Modules/' . $moduleName;

        $directories = [
            $basePath,
            $basePath . '/Contracts',
            $basePath . '/Services',
            $basePath . '/Repositories',
            $basePath . '/Factories',
            $basePath . '/DTOs',
            $basePath . '/Exceptions'
        ];

        foreach ($directories as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new Exception("Failed to create directory: {$dir}");
            }
            echo "   ✅ {$dir}\n";
        }

        // Test directories
        $testBasePath = $this->basePath . '/tests/Unit/' . $moduleName;
        $testDirectories = [
            $testBasePath,
            $testBasePath . '/Services',
            $testBasePath . '/Repositories',
            $testBasePath . '/Factories',
            $testBasePath . '/DTOs',
            $this->basePath . '/tests/Integration'
        ];

        foreach ($testDirectories as $dir) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new Exception("Failed to create test directory: {$dir}");
            }
            echo "   ✅ {$dir}\n";
        }
    }

    private function generateClasses(): void
    {
        echo "\n🔧 Generating classes from templates...\n";

        $templates = [
            'ModuleTemplate.php' => 'src/Modules/{ModuleName}/{ModuleName}Module.php',
            'RepositoryInterfaceTemplate.php' => 'src/Modules/{ModuleName}/Contracts/{Entity}RepositoryInterface.php',
            'RepositoryImplementationTemplate.php' => 'src/Modules/{ModuleName}/Repositories/Api{Entity}Repository.php',
            'ServiceTemplate.php' => 'src/Modules/{ModuleName}/Services/{Entity}Service.php',
            'FactoryTemplate.php' => 'src/Modules/{ModuleName}/Factories/{ModuleName}ServiceFactory.php',
            'DTOTemplate.php' => 'src/Modules/{ModuleName}/DTOs/{Entity}Data.php',
            'NotFoundExceptionTemplate.php' => 'src/Modules/{ModuleName}/Exceptions/{Entity}NotFoundException.php',
            'ValidationExceptionTemplate.php' => 'src/Modules/{ModuleName}/Exceptions/{Entity}ValidationException.php'
        ];

        foreach ($templates as $template => $targetPath) {
            $this->generateFromTemplate($template, $targetPath);
        }
    }

    private function generateTests(): void
    {
        echo "\n🧪 Generating test classes...\n";

        $testTemplates = [
            'ServiceTestTemplate.php' => 'tests/Unit/{ModuleName}/Services/{Entity}ServiceTest.php',
            'RepositoryTestTemplate.php' => 'tests/Unit/{ModuleName}/Repositories/Api{Entity}RepositoryTest.php',
            'FactoryTestTemplate.php' => 'tests/Unit/{ModuleName}/Factories/{ModuleName}ServiceFactoryTest.php',
            'DTOTestTemplate.php' => 'tests/Unit/{ModuleName}/DTOs/{Entity}DataTest.php',
            'IntegrationTestTemplate.php' => 'tests/Integration/{ModuleName}IntegrationTest.php'
        ];

        foreach ($testTemplates as $template => $targetPath) {
            $this->generateTestFromTemplate($template, $targetPath);
        }
    }

    private function updateSDK(): void
    {
        echo "\n🔗 Updating SDK main class...\n";

        $sdkPath = $this->basePath . '/src/ClubifyCheckoutSDK.php';
        $sdkContent = file_get_contents($sdkPath);

        $moduleName = $this->replacements['{ModuleName}'];
        $entity = $this->replacements['{entity}'];

        // Add import
        $importLine = "use Clubify\\Checkout\\Modules\\{$moduleName}\\{$moduleName}Module;";
        if (strpos($sdkContent, $importLine) === false) {
            $lastImport = strrpos($sdkContent, 'use Clubify\\Checkout\\Modules\\');
            if ($lastImport !== false) {
                $endOfLine = strpos($sdkContent, "\n", $lastImport);
                $sdkContent = substr_replace($sdkContent, "\n{$importLine}", $endOfLine, 0);
            }
        }

        // Add property
        $propertyLine = "    private ?{$moduleName}Module \${$entity}Management = null;";
        if (strpos($sdkContent, $propertyLine) === false) {
            $lastProperty = strrpos($sdkContent, 'private ?');
            if ($lastProperty !== false) {
                $endOfLine = strpos($sdkContent, "\n", $lastProperty);
                $sdkContent = substr_replace($sdkContent, "\n{$propertyLine}", $endOfLine, 0);
            }
        }

        // Add method
        $methodCode = "\n    /**\n     * Get {$moduleName} module\n     */\n    public function {$entity}Management(): {$moduleName}Module\n    {\n        if (\$this->{$entity}Management === null) {\n            \$this->{$entity}Management = new {$moduleName}Module(\$this);\n            \$this->{$entity}Management->initialize(\$this->config, \$this->getLogger());\n        }\n        return \$this->{$entity}Management;\n    }\n\n    /**\n     * Create {$moduleName} Service Factory\n     */\n    public function create{$moduleName}ServiceFactory(): {$moduleName}ServiceFactory\n    {\n        return new {$moduleName}ServiceFactory(\n            \$this->config,\n            \$this->getLogger(),\n            \$this->getHttpClient(),\n            \$this->getCache(),\n            \$this->getEventDispatcher()\n        );\n    }";

        // Find a good place to insert the method (before the last closing brace)
        $lastBrace = strrpos($sdkContent, '}');
        $sdkContent = substr_replace($sdkContent, $methodCode . "\n", $lastBrace, 0);

        file_put_contents($sdkPath, $sdkContent);
        echo "   ✅ SDK main class updated\n";
    }

    private function generateFromTemplate(string $templateName, string $targetPath): void
    {
        $templatePath = $this->templatesPath . '/' . $templateName;
        $targetPath = $this->basePath . '/' . $this->replacePlaceholders($targetPath);

        if (!file_exists($templatePath)) {
            echo "   ⚠️  Template not found: {$templateName}\n";
            return;
        }

        $content = file_get_contents($templatePath);
        $content = $this->replacePlaceholders($content);

        if (!file_put_contents($targetPath, $content)) {
            throw new Exception("Failed to write file: {$targetPath}");
        }

        echo "   ✅ " . basename($targetPath) . "\n";
    }

    private function generateTestFromTemplate(string $templateName, string $targetPath): void
    {
        // For now, create basic test structure
        $targetPath = $this->basePath . '/' . $this->replacePlaceholders($targetPath);
        $className = basename($targetPath, '.php');

        $content = "<?php\n\n";
        $content .= "declare(strict_types=1);\n\n";
        $content .= "namespace Clubify\\Checkout\\Tests\\Unit\\{$this->replacements['{ModuleName}']}\\Services;\n\n";
        $content .= "use Clubify\\Checkout\\Tests\\TestCase;\n";
        $content .= "use Mockery;\n\n";
        $content .= "/**\n";
        $content .= " * {$className}\n";
        $content .= " *\n";
        $content .= " * TODO: Implement comprehensive tests\n";
        $content .= " */\n";
        $content .= "class {$className} extends TestCase\n";
        $content .= "{\n";
        $content .= "    protected function setUp(): void\n";
        $content .= "    {\n";
        $content .= "        parent::setUp();\n";
        $content .= "        // TODO: Setup test dependencies\n";
        $content .= "    }\n\n";
        $content .= "    protected function tearDown(): void\n";
        $content .= "    {\n";
        $content .= "        Mockery::close();\n";
        $content .= "    }\n\n";
        $content .= "    public function testImplementation(): void\n";
        $content .= "    {\n";
        $content .= "        // TODO: Implement test\n";
        $content .= "        \$this->markTestIncomplete('Test needs to be implemented');\n";
        $content .= "    }\n";
        $content .= "}\n";

        if (!file_put_contents($targetPath, $content)) {
            throw new Exception("Failed to write test file: {$targetPath}");
        }

        echo "   ✅ " . basename($targetPath) . "\n";
    }

    private function replacePlaceholders(string $content): string
    {
        return str_replace(array_keys($this->replacements), array_values($this->replacements), $content);
    }

    private function showCompletionMessage(): void
    {
        $moduleName = $this->replacements['{ModuleName}'];
        $entity = $this->replacements['{entity}'];

        echo "\n🎉 Module '{$moduleName}' created successfully!\n\n";
        echo "📋 Next steps:\n";
        echo "   1. Review generated classes in src/Modules/{$moduleName}/\n";
        echo "   2. Customize business logic in {$moduleName}Service\n";
        echo "   3. Implement domain-specific methods in Api{$this->replacements['{Entity}']}Repository\n";
        echo "   4. Update {$this->replacements['{Entity}']}Data with actual fields\n";
        echo "   5. Implement tests in tests/Unit/{$moduleName}/ and tests/Integration/\n";
        echo "   6. Run validation: php docs/scripts/validate_module.php {$moduleName}\n\n";
        echo "💡 Usage example:\n";
        echo "   \$sdk = new ClubifyCheckoutSDK(\$config);\n";
        echo "   \$result = \$sdk->{$entity}Management()->create{$this->replacements['{Entity}']}(\$data);\n\n";
        echo "📖 Documentation:\n";
        echo "   - Architecture: docs/ARCHITECTURE.md\n";
        echo "   - Guidelines: docs/guides/DEVELOPMENT_GUIDELINES.md\n";
        echo "   - Examples: docs/examples/PRACTICAL_EXAMPLES.md\n\n";
    }

    // Helper methods
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

    private function promptFields(): array
    {
        echo "\n📝 Define entity fields (press Enter when done):\n";
        $fields = [];

        while (true) {
            $field = $this->prompt('Field name (or Enter to finish)');
            if (empty($field)) {
                break;
            }

            $type = $this->prompt('Field type (string/int/float/bool/array)', 'string');
            $required = $this->prompt('Required? (y/n)', 'n') === 'y';

            $fields[] = [
                'name' => $field,
                'type' => $type,
                'required' => $required
            ];
        }

        return $fields;
    }

    private function promptMethods(): array
    {
        echo "\n🔧 Define domain-specific methods (press Enter when done):\n";
        $methods = [];

        while (true) {
            $method = $this->prompt('Method name (or Enter to finish)');
            if (empty($method)) {
                break;
            }

            $description = $this->prompt('Method description');

            $methods[] = [
                'name' => $method,
                'description' => $description
            ];
        }

        return $methods;
    }

    private function isValidClassName(string $name): bool
    {
        return preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    private function suggestField(array $fields): string
    {
        if (empty($fields)) {
            return 'Name';
        }

        // Return first field as suggestion
        return ucfirst($fields[0]['name']);
    }
}

// Run the scaffolder
if (php_sapi_name() === 'cli') {
    $scaffolder = new ModuleScaffolder();
    $scaffolder->run();
} else {
    echo "This script must be run from command line\n";
    exit(1);
}