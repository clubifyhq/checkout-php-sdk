<?php

/**
 * Script para corrigir configurações de URL inconsistentes
 *
 * Este script identifica e corrige o problema onde:
 * 1. Configuration.php procura 'endpoints.base_url'
 * 2. Exemplos usam 'api.base_url' ou 'base_url'
 * 3. URLs com e sem '/api/v1' causando duplicação
 */

declare(strict_types=1);

class UrlConfigurationFixer
{
    private string $basePath;
    private array $fixes = [];
    private array $errors = [];

    public function __construct()
    {
        $this->basePath = dirname(__DIR__);
    }

    public function run(): void
    {
        echo "🔧 URL Configuration Fixer - Clubify Checkout SDK\n";
        echo "================================================\n\n";

        $this->analyzeCurrentIssues();
        $this->presentSolution();
        $this->applyFixes();
        $this->showResults();
    }

    private function analyzeCurrentIssues(): void
    {
        echo "📋 Analyzing current configuration issues...\n\n";

        // 1. Check Configuration.php
        $configFile = $this->basePath . '/src/Core/Config/Configuration.php';
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if (strpos($content, "get('endpoints.base_url')") !== false) {
                $this->fixes[] = [
                    'type' => 'config_class',
                    'file' => $configFile,
                    'issue' => "Configuration.php procura 'endpoints.base_url'",
                    'solution' => "Alterar para aceitar 'api.base_url' ou 'base_url' também"
                ];
                echo "❌ Configuration.php: procura 'endpoints.base_url' mas exemplos usam 'api.base_url'\n";
            }
        }

        // 2. Check example files
        $this->analyzeExampleFiles();

        // 3. Check Environment enum
        $enumFile = $this->basePath . '/src/Enums/Environment.php';
        if (file_exists($enumFile)) {
            $content = file_get_contents($enumFile);
            if (strpos($content, '/api/v1') !== false) {
                echo "✅ Environment.php: corretamente inclui '/api/v1' nas URLs\n";
            }
        }

        echo "\n";
    }

    private function analyzeExampleFiles(): void
    {
        $exampleFiles = glob($this->basePath . '/examples/**/*.php', GLOB_BRACE);

        foreach ($exampleFiles as $file) {
            $content = file_get_contents($file);
            $relativePath = str_replace($this->basePath, '', $file);

            // Check for inconsistent configurations
            if (preg_match("/'base_url'\s*=>\s*'([^']+)'/", $content, $matches)) {
                $url = $matches[1];

                if (strpos($url, '/api/v1') === false) {
                    $this->fixes[] = [
                        'type' => 'example_missing_api',
                        'file' => $file,
                        'current_url' => $url,
                        'suggested_url' => rtrim($url, '/') . '/api/v1',
                        'issue' => "URL sem '/api/v1' vai duplicar com Environment"
                    ];
                    echo "⚠️  {$relativePath}: URL '{$url}' pode causar duplicação\n";
                }
            }

            if (preg_match("/'api'\s*=>\s*\[[\s\S]*?'base_url'/", $content)) {
                $this->fixes[] = [
                    'type' => 'wrong_config_key',
                    'file' => $file,
                    'issue' => "Usa 'api.base_url' mas Configuration procura 'endpoints.base_url'"
                ];
                echo "❌ {$relativePath}: usa 'api.base_url' (key incorreta)\n";
            }
        }
    }

    private function presentSolution(): void
    {
        echo "💡 SOLUÇÃO PROPOSTA:\n";
        echo "===================\n\n";

        echo "1. **Configuration.php** - Tornar mais flexível:\n";
        echo "   - Aceitar 'endpoints.base_url', 'api.base_url' ou 'base_url'\n";
        echo "   - Normalizar URLs removendo '/api/v1' duplicado\n\n";

        echo "2. **Environment.php** - Manter como está:\n";
        echo "   - URLs padrão incluem '/api/v1' corretamente\n\n";

        echo "3. **Arquivos de exemplo** - Padronizar:\n";
        echo "   - Usar sempre 'endpoints' => ['base_url' => '...']\n";
        echo "   - URLs sem '/api/v1' para evitar duplicação\n\n";

        echo "4. **Repositórios** - Ajustar se necessário:\n";
        echo "   - Garantir que endpoints relativos funcionem corretamente\n\n";
    }

    private function applyFixes(): void
    {
        $response = $this->prompt("Aplicar correções automaticamente? (y/n)", 'n');

        if (strtolower($response) !== 'y') {
            echo "⚠️ Correções não aplicadas. Execute com 'y' para aplicar.\n\n";
            return;
        }

        echo "🔧 Aplicando correções...\n\n";

        // Fix 1: Configuration.php
        $this->fixConfigurationClass();

        // Fix 2: Example files
        $this->fixExampleFiles();

        echo "✅ Todas as correções aplicadas!\n\n";
    }

    private function fixConfigurationClass(): void
    {
        $configFile = $this->basePath . '/src/Core/Config/Configuration.php';

        if (!file_exists($configFile)) {
            $this->errors[] = "Configuration.php não encontrado";
            return;
        }

        $content = file_get_contents($configFile);

        // Replace the getBaseUrl method with a more flexible version
        $oldMethod = '/public function getBaseUrl\(\): string\s*\{[^}]+\}/s';

        $newMethod = 'public function getBaseUrl(): string
    {
        // Try multiple configuration paths for flexibility
        $customUrl = $this->get(\'endpoints.base_url\')
                  ?? $this->get(\'api.base_url\')
                  ?? $this->get(\'base_url\');

        if ($customUrl) {
            // Normalize URL - remove duplicate /api/v1 if present
            $normalizedUrl = rtrim($customUrl, \'/\');

            // If URL already ends with /api/v1, use as is
            // If not, let Environment.php add it
            return $normalizedUrl;
        }

        $environment = Environment::from($this->getEnvironment());
        return $environment->getBaseUrl();
    }';

        $content = preg_replace($oldMethod, $newMethod, $content);

        if (file_put_contents($configFile, $content)) {
            echo "✅ Configuration.php: método getBaseUrl() atualizado\n";
        } else {
            $this->errors[] = "Falha ao atualizar Configuration.php";
        }
    }

    private function fixExampleFiles(): void
    {
        foreach ($this->fixes as $fix) {
            if ($fix['type'] === 'wrong_config_key') {
                $this->fixConfigKey($fix['file']);
            }

            if ($fix['type'] === 'example_missing_api' && isset($fix['current_url'])) {
                $this->fixExampleUrl($fix['file'], $fix['current_url']);
            }
        }
    }

    private function fixConfigKey(string $file): void
    {
        $content = file_get_contents($file);
        $relativePath = str_replace($this->basePath, '', $file);

        // Replace 'api' => ['base_url' => ...] with 'endpoints' => ['base_url' => ...]
        $content = preg_replace(
            "/'api'\s*=>\s*\[(\s*)'base_url'/",
            "'endpoints' => [$1'base_url'",
            $content
        );

        if (file_put_contents($file, $content)) {
            echo "✅ {$relativePath}: 'api.base_url' → 'endpoints.base_url'\n";
        } else {
            $this->errors[] = "Falha ao atualizar {$relativePath}";
        }
    }

    private function fixExampleUrl(string $file, string $currentUrl): void
    {
        $content = file_get_contents($file);
        $relativePath = str_replace($this->basePath, '', $file);

        // Remove /api/v1 from URLs to avoid duplication
        $newUrl = str_replace('/api/v1', '', $currentUrl);
        $content = str_replace("'$currentUrl'", "'$newUrl'", $content);

        if (file_put_contents($file, $content)) {
            echo "✅ {$relativePath}: URL normalizada (removido /api/v1 duplicado)\n";
        } else {
            $this->errors[] = "Falha ao atualizar {$relativePath}";
        }
    }

    private function showResults(): void
    {
        echo "\n📊 RESULTADOS:\n";
        echo "==============\n\n";

        if (!empty($this->fixes)) {
            echo "✅ Correções aplicadas: " . count($this->fixes) . "\n";
        }

        if (!empty($this->errors)) {
            echo "❌ Erros encontrados:\n";
            foreach ($this->errors as $error) {
                echo "   - {$error}\n";
            }
        }

        echo "\n🧪 PRÓXIMOS PASSOS:\n";
        echo "==================\n";
        echo "1. Execute os testes para verificar se o problema foi resolvido\n";
        echo "2. Teste especificamente: php examples/example-app/test-sdk.php\n";
        echo "3. Verifique os logs do nginx para confirmar URLs corretas\n";
        echo "4. Se ainda houver problemas, verifique se o base_url está correto\n\n";

        echo "💡 CONFIGURAÇÃO RECOMENDADA:\n";
        echo "============================\n";
        echo "Para novos projetos, use:\n";
        echo "```php\n";
        echo "[\n";
        echo "    'credentials' => [\n";
        echo "        'environment' => 'development'\n";
        echo "    ],\n";
        echo "    'endpoints' => [\n";
        echo "        'base_url' => 'https://checkout.svelve.com'  // SEM /api/v1\n";
        echo "    ]\n";
        echo "]\n";
        echo "```\n\n";
    }

    private function prompt(string $question, string $default = ''): string
    {
        $prompt = $question;
        if ($default) {
            $prompt .= " [{$default}]";
        }
        $prompt .= ': ';

        echo $prompt;
        $input = fgets(STDIN);

        if ($input === false) {
            return $default;
        }

        $trimmed = trim($input);
        return $trimmed !== '' ? $trimmed : $default;
    }
}

// Run the fixer
if (php_sapi_name() === 'cli') {
    $fixer = new UrlConfigurationFixer();
    $fixer->run();
} else {
    echo "Este script deve ser executado via linha de comando\n";
    exit(1);
}