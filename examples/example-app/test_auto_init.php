<?php

require_once 'vendor/autoload.php';

use App\Helpers\ClubifySDKHelper;

echo "=== Teste de Auto-Inicialização ===\n\n";

try {
    echo "1. Obtendo instância do SDK (deve auto-inicializar)...\n";
    $sdk = ClubifySDKHelper::getInstance();

    echo "2. Verificando se foi inicializado automaticamente...\n";
    $isInitialized = $sdk->isInitialized();
    echo "   Inicializado: " . ($isInitialized ? 'SIM' : 'NÃO') . "\n\n";

    if ($isInitialized) {
        echo "✅ Auto-inicialização funcionou!\n";
        echo "   - SDK foi criado e inicializado automaticamente\n";
        echo "   - Não foi necessário chamar initialize() manualmente\n";
    } else {
        echo "❌ Auto-inicialização falhou\n";
        echo "   - SDK foi criado mas não foi inicializado\n";
        echo "   - Seria necessário chamar initialize() manualmente\n";
    }

} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}