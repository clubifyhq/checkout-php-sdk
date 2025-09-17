<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Teste de Debugging do Controller ===\n";

try {
    // Carregar autoloader
    require_once __DIR__ . '/vendor/autoload.php';
    echo "✓ Autoloader carregado\n";

    // Carregar app
    $app = require_once __DIR__ . '/bootstrap/app.php';
    echo "✓ App bootstrap carregado\n";

    // Tentar instanciar o controller diretamente
    $controller = new App\Http\Controllers\ClubifyDemoController();
    echo "✓ Controller instanciado com sucesso\n";

    // Tentar chamar o método testAllMethodsPage
    echo "Tentando chamar testAllMethodsPage()...\n";
    $result = $controller->testAllMethodsPage();
    echo "✓ Método executado com sucesso\n";
    echo "Tipo de retorno: " . gettype($result) . "\n";

    if (is_object($result)) {
        echo "Classe: " . get_class($result) . "\n";
    }

} catch (\Throwable $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . " (linha " . $e->getLine() . ")\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}