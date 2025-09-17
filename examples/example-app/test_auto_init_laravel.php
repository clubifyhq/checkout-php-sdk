<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\ClubifySDKHelper;

echo "=== Teste de Auto-Inicialização (Laravel Context) ===\n\n";

try {
    echo "1. Obtendo instância do SDK (deve auto-inicializar)...\n";
    $sdk = ClubifySDKHelper::getInstance();

    echo "2. Verificando se foi inicializado automaticamente...\n";
    $isInitialized = $sdk->isInitialized();
    echo "   Inicializado: " . ($isInitialized ? 'SIM' : 'NÃO') . "\n\n";

    if ($isInitialized) {
        echo "✅ Auto-inicialização funcionou!\n";
        echo "   - SDK foi criado e inicializado automaticamente\n";
        echo "   - Não foi necessário chamar initialize() manualmente\n\n";

        echo "3. Testando funcionalidade básica...\n";
        $sdkStatus = $sdk->getStatus();
        echo "   Status: " . json_encode($sdkStatus, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "❌ Auto-inicialização falhou\n";
        echo "   - SDK foi criado mas não foi inicializado\n";
        echo "   - Seria necessário chamar initialize() manualmente\n";
    }

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}