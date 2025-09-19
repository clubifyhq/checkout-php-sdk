<?php

/**
 * Teste específico para debugar o PaymentService
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "\n";
echo "💳 DEBUG PAYMENTSERVICE - IDENTIFICAR PROBLEMA\n";
echo "════════════════════════════════════════════════════════════════════════════════\n";

try {
    // Configuração básica
    $config = [
        'credentials' => [
            'tenant_id' => $_ENV['CLUBIFY_CHECKOUT_TENANT_ID'] ?? 'demo_tenant',
            'api_key' => $_ENV['CLUBIFY_CHECKOUT_API_KEY'] ?? 'demo_key',
            'environment' => $_ENV['CLUBIFY_CHECKOUT_ENVIRONMENT'] ?? 'sandbox'
        ],
        'http' => [
            'timeout' => 5000,
            'connect_timeout' => 3,
            'retries' => 1
        ],
        'endpoints' => [
            'base_url' => $_ENV['CLUBIFY_CHECKOUT_API_URL'] ?? 'https://checkout.svelve.com/api/v1'
        ]
    ];

    // Carregar .env se existir
    if (file_exists(__DIR__ . '/.env')) {
        $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
                $config['credentials'][strtolower(str_replace('CLUBIFY_CHECKOUT_', '', trim($key)))] = trim($value, '"\'');
            }
        }
    }

    echo "🚀 Inicializando SDK...\n";
    $sdk = new ClubifyCheckoutSDK($config);
    echo "✅ SDK inicializado\n\n";

    echo "💳 Testando módulo Payments...\n";
    $payments = $sdk->payments();
    echo "✅ Módulo Payments carregado\n\n";

    echo "🔧 Tentando processar pagamento...\n";
    $paymentData = [
        'amount' => 10000,
        'currency' => 'BRL',
        'customer_id' => 'test_customer_id',
        'payment_method' => 'credit_card'
    ];

    echo "   Dados: " . json_encode($paymentData, JSON_PRETTY_PRINT) . "\n\n";

    $result = $payments->processPayment($paymentData);

    echo "📊 Resultado:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n\n";

    // Verificar se é mock ou real
    if (isset($result['payment_id']) && str_contains($result['payment_id'], 'payment_')) {
        echo "🎭 MOCK DETECTADO: payment_id contém 'payment_'\n";
    } else {
        echo "📡 DADOS REAIS: Não parece ser mock\n";
    }

    if (isset($result['success']) && $result['success'] === false) {
        echo "❌ FALHA: " . ($result['error'] ?? 'Erro desconhecido') . "\n";
    } else {
        echo "✅ SUCESSO: Pagamento processado\n";
    }

} catch (\Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n════════════════════════════════════════════════════════════════════════════════\n";
echo "🏁 DEBUG FINALIZADO\n";