<?php

// Bootstrap Laravel
require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Helpers\ClubifySDKHelper;

echo "=== DEMO: Auto-Inicialização do Clubify SDK ===\n\n";

echo "🔧 Configuração atual:\n";
echo "   AUTO_INITIALIZE: " . (config('clubify-checkout.features.auto_initialize') ? 'HABILITADO' : 'DESABILITADO') . "\n";
echo "   TENANT_ID: " . env('CLUBIFY_CHECKOUT_TENANT_ID') . "\n";
echo "   ENVIRONMENT: " . env('CLUBIFY_CHECKOUT_ENVIRONMENT') . "\n\n";

// Reset para garantir teste limpo
ClubifySDKHelper::reset();

echo "1️⃣ Obtendo instância do SDK pela primeira vez...\n";
try {
    $startTime = microtime(true);
    $sdk = ClubifySDKHelper::getInstance();
    $endTime = microtime(true);

    echo "   ✅ Instância criada em " . round(($endTime - $startTime) * 1000, 2) . "ms\n";
    echo "   🔍 Status de inicialização: " . ($sdk->isInitialized() ? 'INICIALIZADO' : 'NÃO INICIALIZADO') . "\n\n";

    if ($sdk->isInitialized()) {
        echo "2️⃣ Testando funcionalidades do SDK...\n";

        // Teste de status
        echo "   📊 Verificando status geral...\n";
        $status = $sdk->getStatus();
        echo "   📈 Status: " . json_encode($status) . "\n\n";

        echo "3️⃣ Verificando informações de autenticação...\n";
        $authManager = $sdk->getAuthManager();
        echo "   🔐 Autenticado: " . ($authManager->isAuthenticated() ? 'SIM' : 'NÃO') . "\n";

        if ($authManager->isAuthenticated()) {
            $userInfo = $authManager->getUserInfo();
            echo "   👤 Tenant ID: " . $userInfo['tenant_id'] . "\n";
            echo "   🌍 Ambiente: " . $userInfo['environment'] . "\n";
        }

        echo "\n🎉 SUCESSO: Auto-inicialização funcionou perfeitamente!\n";
        echo "   - SDK foi instanciado e inicializado automaticamente\n";
        echo "   - Não foi necessário chamar initialize() manualmente\n";
        echo "   - Todas as funcionalidades estão disponíveis imediatamente\n";

    } else {
        echo "❌ FALHA: SDK foi criado mas não foi inicializado automaticamente\n";
        echo "   - Seria necessário chamar initialize() manualmente\n";
    }

} catch (\Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "💡 DICA: Para desabilitar a auto-inicialização, defina:\n";
echo "   CLUBIFY_CHECKOUT_AUTO_INITIALIZE=false no arquivo .env\n";
echo str_repeat("=", 60) . "\n";