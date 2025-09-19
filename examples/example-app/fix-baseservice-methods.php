<?php

/**
 * Script para corrigir automaticamente todos os serviços que estendem BaseService
 * mas não implementam o método getServiceVersion()
 */

// Encontrar todos os arquivos que estendem BaseService
$command = "find /Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/src/ -name '*.php' -exec grep -l 'extends BaseService' {} \\;";
$files = explode("\n", trim(shell_exec($command)));

echo "🔧 Encontrados " . count($files) . " arquivos que estendem BaseService\n\n";

$fixedCount = 0;
$skippedCount = 0;

foreach ($files as $file) {
    if (empty($file)) continue;

    echo "📁 Analisando: " . basename($file) . "\n";

    // Ler conteúdo do arquivo
    $content = file_get_contents($file);

    // Verificar se já tem o método getServiceVersion
    if (strpos($content, 'getServiceVersion') !== false) {
        echo "   ✅ Já possui getServiceVersion - pulando\n";
        $skippedCount++;
        continue;
    }

    // Encontrar o método getServiceName
    if (preg_match('/protected function getServiceName\(\): string\s*\{[^}]+\}/s', $content, $matches)) {
        $getServiceNameMethod = $matches[0];

        // Criar o método getServiceVersion
        $getServiceVersionMethod = "\n\n    /**\n     * Obtém a versão do serviço\n     */\n    protected function getServiceVersion(): string\n    {\n        return '1.0.0';\n    }";

        // Substituir o método getServiceName pelo mesmo método + getServiceVersion
        $newContent = str_replace($getServiceNameMethod, $getServiceNameMethod . $getServiceVersionMethod, $content);

        // Salvar o arquivo
        file_put_contents($file, $newContent);
        echo "   🔧 Adicionado método getServiceVersion\n";
        $fixedCount++;
    } else {
        echo "   ⚠️  Não encontrou getServiceName - pulando\n";
        $skippedCount++;
    }

    echo "\n";
}

echo "═══════════════════════════════════════════\n";
echo "📊 RESUMO:\n";
echo "✅ Arquivos corrigidos: {$fixedCount}\n";
echo "⏭️  Arquivos pulados: {$skippedCount}\n";
echo "📁 Total analisado: " . count($files) . "\n";
echo "═══════════════════════════════════════════\n";