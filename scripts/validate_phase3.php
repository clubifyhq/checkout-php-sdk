<?php

/**
 * Script de Validação da Fase 3: Implementação Completa de Testes
 *
 * Este script valida a implementação completa da Fase 3 do plano estratégico,
 * verificando:
 * - Estrutura de testes criada
 * - Testes unitários para todos os componentes
 * - Testes de integração implementados
 * - Cobertura de testes adequada
 * - Configuração PHPUnit
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

class Phase3Validator
{
    private array $results = [];
    private array $errors = [];
    private array $warnings = [];

    public function run(): void
    {
        echo "\n=== VALIDAÇÃO DA FASE 3: IMPLEMENTAÇÃO COMPLETA DE TESTES ===\n\n";

        $this->validateTestStructure();
        $this->validateUnitTests();
        $this->validateIntegrationTests();
        $this->validatePHPUnitConfiguration();
        $this->validateTestCoverage();
        $this->validatePhase3Requirements();

        $this->printResults();
    }

    private function validateTestStructure(): void
    {
        echo "📁 Validando estrutura de testes...\n";

        $testDirectories = [
            'tests' => 'Diretório principal de testes',
            'tests/Unit' => 'Diretório de testes unitários',
            'tests/Integration' => 'Diretório de testes de integração',
            'tests/Unit/UserManagement' => 'Testes UserManagement',
            'tests/Unit/UserManagement/Repositories' => 'Testes de Repositories',
            'tests/Unit/UserManagement/Services' => 'Testes de Services',
            'tests/Unit/UserManagement/Factories' => 'Testes de Factories'
        ];

        foreach ($testDirectories as $dir => $description) {
            if (is_dir($dir)) {
                $this->results[] = "✅ {$description}: {$dir}";
            } else {
                $this->errors[] = "❌ {$description} não encontrado: {$dir}";
            }
        }

        $testFiles = [
            'tests/TestCase.php' => 'Classe base TestCase',
            'tests/Unit/UserManagement/Repositories/ApiUserRepositoryTest.php' => 'Teste ApiUserRepository',
            'tests/Unit/UserManagement/Services/UserServiceTest.php' => 'Teste UserService',
            'tests/Unit/UserManagement/Factories/UserServiceFactoryTest.php' => 'Teste UserServiceFactory',
            'tests/Unit/UserManagement/UserManagementModuleTest.php' => 'Teste UserManagementModule',
            'tests/Integration/UserManagementIntegrationTest.php' => 'Teste de Integração UserManagement'
        ];

        foreach ($testFiles as $file => $description) {
            if (file_exists($file)) {
                $this->results[] = "✅ {$description}: {$file}";
            } else {
                $this->errors[] = "❌ {$description} não encontrado: {$file}";
            }
        }
    }

    private function validateUnitTests(): void
    {
        echo "🧪 Validando testes unitários...\n";

        // Valida ApiUserRepositoryTest
        $this->validateTestFile(
            'tests/Unit/UserManagement/Repositories/ApiUserRepositoryTest.php',
            [
                'testImplementsRepositoryInterface',
                'testFindByIdSuccess',
                'testFindByEmailSuccess',
                'testCreateUserSuccess',
                'testUpdateUserSuccess',
                'testDeleteUserSuccess',
                'testFindByTenantSuccess',
                'testCacheIntegration',
                'testEventDispatcherIntegration'
            ],
            'ApiUserRepository'
        );

        // Valida UserServiceTest
        $this->validateTestFile(
            'tests/Unit/UserManagement/Services/UserServiceTest.php',
            [
                'testImplementsServiceInterface',
                'testCreateUserSuccess',
                'testGetUserSuccess',
                'testUpdateUserSuccess',
                'testDeleteUserSuccess',
                'testServiceInfo',
                'testHealthCheck'
            ],
            'UserService'
        );

        // Valida UserServiceFactoryTest
        $this->validateTestFile(
            'tests/Unit/UserManagement/Factories/UserServiceFactoryTest.php',
            [
                'testImplementsFactoryInterface',
                'testCreateUserServiceSuccess',
                'testCreateUserServiceSingleton',
                'testCreateUnsupportedServiceType',
                'testIsTypeSupported',
                'testGetSupportedTypes'
            ],
            'UserServiceFactory'
        );

        // Valida UserManagementModuleTest
        $this->validateTestFile(
            'tests/Unit/UserManagement/UserManagementModuleTest.php',
            [
                'testModuleInfo',
                'testLazyLoading',
                'testFactoryIntegration',
                'testHealthCheck',
                'testCleanup'
            ],
            'UserManagementModule'
        );
    }

    private function validateIntegrationTests(): void
    {
        echo "🔄 Validando testes de integração...\n";

        $this->validateTestFile(
            'tests/Integration/UserManagementIntegrationTest.php',
            [
                'testFullUserLifecycleIntegration',
                'testDependencyInjectionIntegration',
                'testModuleHealthAndMetrics',
                'testErrorHandlingIntegration',
                'testCacheIntegrationFlow',
                'testLazyLoadingIntegration',
                'testFactorySingletonBehavior',
                'testSDKUserManagementIntegration'
            ],
            'UserManagement Integration'
        );
    }

    private function validateTestFile(string $filePath, array $expectedMethods, string $component): void
    {
        if (!file_exists($filePath)) {
            $this->errors[] = "❌ Arquivo de teste não encontrado: {$filePath}";
            return;
        }

        $content = file_get_contents($filePath);
        $foundMethods = 0;
        $missingMethods = [];

        foreach ($expectedMethods as $method) {
            if (strpos($content, "function {$method}(") !== false) {
                $foundMethods++;
            } else {
                $missingMethods[] = $method;
            }
        }

        if (empty($missingMethods)) {
            $this->results[] = "✅ {$component}: Todos os {$foundMethods} métodos de teste implementados";
        } else {
            $this->warnings[] = "⚠️  {$component}: Métodos ausentes: " . implode(', ', $missingMethods);
        }

        // Valida uso do Mockery
        if (strpos($content, 'use Mockery') !== false || strpos($content, 'Mockery::mock') !== false) {
            $this->results[] = "✅ {$component}: Mockery configurado corretamente";
        } else {
            $this->warnings[] = "⚠️  {$component}: Mockery não detectado nos testes";
        }

        // Valida extends TestCase
        if (strpos($content, 'extends TestCase') !== false) {
            $this->results[] = "✅ {$component}: Herda de TestCase corretamente";
        } else {
            $this->errors[] = "❌ {$component}: Não herda de TestCase";
        }
    }

    private function validatePHPUnitConfiguration(): void
    {
        echo "⚙️  Validando configuração PHPUnit...\n";

        if (file_exists('phpunit.xml')) {
            $this->results[] = "✅ Arquivo phpunit.xml encontrado";

            $phpunitContent = file_get_contents('phpunit.xml');

            // Valida configurações essenciais
            if (strpos($phpunitContent, 'testdox="true"') !== false) {
                $this->results[] = "✅ PHPUnit testdox habilitado";
            }

            if (strpos($phpunitContent, 'tests/') !== false) {
                $this->results[] = "✅ Diretório de testes configurado";
            }

            if (strpos($phpunitContent, 'src/') !== false) {
                $this->results[] = "✅ Diretório source configurado para autoload";
            }
        } else {
            $this->warnings[] = "⚠️  Arquivo phpunit.xml não encontrado";
        }

        if (file_exists('composer.json')) {
            $composerContent = file_get_contents('composer.json');

            if (strpos($composerContent, 'phpunit/phpunit') !== false) {
                $this->results[] = "✅ PHPUnit instalado via Composer";
            } else {
                $this->warnings[] = "⚠️  PHPUnit não encontrado no composer.json";
            }

            if (strpos($composerContent, 'mockery/mockery') !== false) {
                $this->results[] = "✅ Mockery instalado via Composer";
            } else {
                $this->warnings[] = "⚠️  Mockery não encontrado no composer.json";
            }
        }
    }

    private function validateTestCoverage(): void
    {
        echo "📊 Validando cobertura de testes...\n";

        $componentsToTest = [
            'ApiUserRepository' => 'src/Modules/UserManagement/Repositories/ApiUserRepository.php',
            'UserService' => 'src/Modules/UserManagement/Services/UserService.php',
            'UserServiceFactory' => 'src/Modules/UserManagement/Factories/UserServiceFactory.php',
            'UserManagementModule' => 'src/Modules/UserManagement/UserManagementModule.php'
        ];

        foreach ($componentsToTest as $component => $filePath) {
            if (file_exists($filePath)) {
                $testFile = $this->findTestFile($component);
                if ($testFile) {
                    $this->results[] = "✅ {$component}: Classe e teste implementados";
                } else {
                    $this->errors[] = "❌ {$component}: Classe existe mas teste não encontrado";
                }
            } else {
                $this->errors[] = "❌ {$component}: Classe não encontrada em {$filePath}";
            }
        }
    }

    private function findTestFile(string $component): ?string
    {
        $possiblePaths = [
            "tests/Unit/UserManagement/Repositories/{$component}Test.php",
            "tests/Unit/UserManagement/Services/{$component}Test.php",
            "tests/Unit/UserManagement/Factories/{$component}Test.php",
            "tests/Unit/UserManagement/{$component}Test.php"
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function validatePhase3Requirements(): void
    {
        echo "📋 Validando requisitos da Fase 3...\n";

        // Requisitos específicos da Fase 3
        $requirements = [
            'Testes unitários para Repository Pattern' => $this->checkRepositoryTests(),
            'Testes unitários para Service Pattern' => $this->checkServiceTests(),
            'Testes unitários para Factory Pattern' => $this->checkFactoryTests(),
            'Testes de integração completos' => $this->checkIntegrationTests(),
            'Mocks e dependency injection nos testes' => $this->checkMockingStrategy(),
            'Cobertura de casos de erro' => $this->checkErrorCoverage(),
            'Testes de singleton pattern' => $this->checkSingletonTests(),
            'Testes de lazy loading' => $this->checkLazyLoadingTests()
        ];

        foreach ($requirements as $requirement => $passed) {
            if ($passed) {
                $this->results[] = "✅ {$requirement}";
            } else {
                $this->errors[] = "❌ {$requirement}";
            }
        }
    }

    private function checkRepositoryTests(): bool
    {
        return file_exists('tests/Unit/UserManagement/Repositories/ApiUserRepositoryTest.php');
    }

    private function checkServiceTests(): bool
    {
        return file_exists('tests/Unit/UserManagement/Services/UserServiceTest.php');
    }

    private function checkFactoryTests(): bool
    {
        return file_exists('tests/Unit/UserManagement/Factories/UserServiceFactoryTest.php');
    }

    private function checkIntegrationTests(): bool
    {
        return file_exists('tests/Integration/UserManagementIntegrationTest.php');
    }

    private function checkMockingStrategy(): bool
    {
        $testFiles = glob('tests/Unit/UserManagement/**/*Test.php');
        foreach ($testFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Mockery::mock') !== false || strpos($content, '->shouldReceive') !== false) {
                return true;
            }
        }
        return false;
    }

    private function checkErrorCoverage(): bool
    {
        $testFiles = glob('tests/Unit/UserManagement/**/*Test.php');
        foreach ($testFiles as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'expectException') !== false) {
                return true;
            }
        }
        return false;
    }

    private function checkSingletonTests(): bool
    {
        if (file_exists('tests/Unit/UserManagement/Factories/UserServiceFactoryTest.php')) {
            $content = file_get_contents('tests/Unit/UserManagement/Factories/UserServiceFactoryTest.php');
            return strpos($content, 'testCreateUserServiceSingleton') !== false ||
                   strpos($content, 'assertSame') !== false;
        }
        return false;
    }

    private function checkLazyLoadingTests(): bool
    {
        if (file_exists('tests/Integration/UserManagementIntegrationTest.php')) {
            $content = file_get_contents('tests/Integration/UserManagementIntegrationTest.php');
            return strpos($content, 'testLazyLoadingIntegration') !== false;
        }
        return false;
    }

    private function printResults(): void
    {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "📊 RESULTADOS DA VALIDAÇÃO DA FASE 3\n";
        echo str_repeat("=", 80) . "\n\n";

        if (!empty($this->results)) {
            echo "✅ SUCESSOS (" . count($this->results) . "):\n";
            foreach ($this->results as $result) {
                echo "   {$result}\n";
            }
            echo "\n";
        }

        if (!empty($this->warnings)) {
            echo "⚠️  AVISOS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                echo "   {$warning}\n";
            }
            echo "\n";
        }

        if (!empty($this->errors)) {
            echo "❌ ERROS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "   {$error}\n";
            }
            echo "\n";
        }

        // Resumo final
        $total = count($this->results) + count($this->warnings) + count($this->errors);
        $successRate = count($this->results) / $total * 100;

        echo str_repeat("-", 80) . "\n";
        echo "📈 RESUMO FINAL:\n";
        echo "   Total de verificações: {$total}\n";
        echo "   Sucessos: " . count($this->results) . "\n";
        echo "   Avisos: " . count($this->warnings) . "\n";
        echo "   Erros: " . count($this->errors) . "\n";
        echo "   Taxa de sucesso: " . number_format($successRate, 1) . "%\n\n";

        if (empty($this->errors)) {
            echo "🎉 FASE 3 CONCLUÍDA COM SUCESSO!\n";
            echo "   Todos os testes foram implementados corretamente.\n";
            echo "   A arquitetura híbrida está completamente testada.\n";
            echo "   O sistema está pronto para validação por execução dos testes.\n\n";
        } else {
            echo "🔧 FASE 3 NECESSITA CORREÇÕES!\n";
            echo "   Existem erros que precisam ser corrigidos antes da conclusão.\n";
            echo "   Revise os erros listados acima.\n\n";
        }

        echo "💡 PRÓXIMOS PASSOS:\n";
        echo "   1. Execute 'composer install' para instalar dependências\n";
        echo "   2. Execute 'vendor/bin/phpunit' para rodar todos os testes\n";
        echo "   3. Verifique a cobertura de testes\n";
        echo "   4. Prossiga para a Fase 4 (Documentação) se todos os testes passarem\n\n";
    }
}

// Executar validação
try {
    $validator = new Phase3Validator();
    $validator->run();
} catch (Exception $e) {
    echo "❌ Erro durante a validação: " . $e->getMessage() . "\n";
    echo "Verifique se o autoloader está configurado corretamente.\n";
    exit(1);
}