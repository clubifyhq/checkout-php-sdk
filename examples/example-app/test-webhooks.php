<?php

/**
 * Teste específico do módulo Webhooks
 *
 * Script para verificar se o problema do "secret" foi corrigido
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;

echo "\n";
echo "🔗 TESTE DO MÓDULO WEBHOOKS - FASE 3\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";

try {
    // Configuração do SDK
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

    // Carregar variáveis de ambiente
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
    echo "✅ SDK inicializado com sucesso\n\n";

    echo "🔗 Testando módulo Webhooks...\n";
    $webhooks = $sdk->webhooks();
    echo "✅ Módulo Webhooks carregado\n\n";

    echo "📋 Teste 1: Criar webhook SEM secret (deve gerar automaticamente)\n";
    $webhookData = [
        'url' => 'https://example.com/webhook-test-' . time(),
        'events' => ['payment.completed', 'order.created']
    ];

    echo "   Dados enviados: " . json_encode($webhookData, JSON_PRETTY_PRINT) . "\n";

    try {
        $result = $webhooks->createWebhook($webhookData);
        echo "✅ SUCESSO: Webhook criado sem fornecer secret!\n";
        echo "   Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";

        // Verificar se o secret foi gerado
        if (isset($result['secret']) && !empty($result['secret'])) {
            echo "✅ Secret gerado automaticamente: " . substr($result['secret'], 0, 10) . "...\n";
        } else {
            echo "❌ Secret não foi gerado automaticamente\n";
        }

    } catch (Exception $e) {
        echo "❌ FALHOU: " . $e->getMessage() . "\n";
        echo "   Tipo: " . get_class($e) . "\n";

        // Se ainda está falhando por causa do secret, mostra detalhes
        if (strpos($e->getMessage(), 'secret') !== false) {
            echo "   🔧 AINDA HÁ PROBLEMA COM SECRET - necessário investigar mais\n";
        }
    }

    echo "\n";
    echo "📋 Teste 2: Criar webhook COM secret fornecido\n";
    $webhookDataWithSecret = [
        'url' => 'https://example.com/webhook-with-secret-' . time(),
        'events' => ['payment.completed'],
        'secret' => 'my_custom_secret_12345678901234567890'
    ];

    echo "   Dados enviados: " . json_encode($webhookDataWithSecret, JSON_PRETTY_PRINT) . "\n";

    try {
        $result2 = $webhooks->createWebhook($webhookDataWithSecret);
        echo "✅ SUCESSO: Webhook criado com secret fornecido!\n";
        echo "   Resultado: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n";

    } catch (Exception $e) {
        echo "❌ FALHOU: " . $e->getMessage() . "\n";
        echo "   Tipo: " . get_class($e) . "\n";
    }

    echo "\n";
    echo "📋 Teste 3: Listar webhooks\n";
    try {
        $listResult = $webhooks->listWebhooks();
        echo "✅ SUCESSO: Lista de webhooks obtida!\n";
        echo "   Resultado: " . json_encode($listResult, JSON_PRETTY_PRINT) . "\n";

    } catch (Exception $e) {
        echo "❌ FALHOU: " . $e->getMessage() . "\n";
        echo "   Tipo: " . get_class($e) . "\n";
    }

} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO: " . $e->getMessage() . "\n";
    echo "   Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

echo "\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";
echo "🏁 TESTE DO MÓDULO WEBHOOKS CONCLUÍDO\n";
echo "═══════════════════════════════════════════════════════════════════════════════\n";