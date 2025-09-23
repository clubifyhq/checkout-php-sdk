<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Webhooks\Services\WebhookService;
use Clubify\Checkout\Modules\Webhooks\Services\TestingService;

/**
 * Exemplo completo de integração com webhooks
 *
 * Este exemplo demonstra:
 * - Como criar webhooks com eventos suportados
 * - Validação de URLs de webhook
 * - Teste de conectividade
 * - Simulação de eventos
 * - Debugging e monitoramento
 */

// Configuração do SDK
$sdk = new ClubifyCheckoutSDK([
    'api_key' => 'sua_api_key_aqui',
    'environment' => 'sandbox', // ou 'production'
    'organization_id' => 'sua_organization_id',
]);

$webhookService = $sdk->webhooks();
$testingService = $sdk->webhooks()->testing();

echo "=== EXEMPLO DE INTEGRAÇÃO COM WEBHOOKS ===\n\n";

try {
    // 1. Verificar eventos suportados
    echo "1. Eventos suportados:\n";
    $supportedEvents = $webhookService->getSupportedEvents();
    foreach ($supportedEvents as $event) {
        echo "   - {$event}\n";
    }
    echo "\n";

    // 2. Validar URL de webhook antes de criar
    echo "2. Validando URL de webhook...\n";
    $webhookUrl = 'https://webhook.site/unique-id-here';
    $urlValidation = $webhookService->validateUrl($webhookUrl);

    echo "   URL: {$webhookUrl}\n";
    echo "   Acessível: " . ($urlValidation['accessible'] ? 'Sim' : 'Não') . "\n";
    echo "   Código HTTP: {$urlValidation['response_code']}\n";
    echo "   Tempo de resposta: {$urlValidation['response_time']}ms\n";

    if (!$urlValidation['accessible']) {
        echo "   ⚠️ Erro: {$urlValidation['error']}\n";
        echo "   Não é possível criar webhook com esta URL.\n\n";
        exit(1);
    }
    echo "   ✅ URL válida!\n\n";

    // 3. Criar webhook com múltiplos eventos
    echo "3. Criando webhook...\n";
    $webhookData = [
        'url' => $webhookUrl,
        'events' => [
            'order.paid',
            'order.created',
            'order.cancelled',
            'payment.failed',
            'customer.created',
            'cart.abandoned',
        ],
        'secret' => bin2hex(random_bytes(32)), // Secret seguro
        'active' => true,
        'description' => 'Webhook de exemplo para eventos principais',
        'timeout' => 30,
        'headers' => [
            'X-Custom-Header' => 'ClubifyWebhook',
            'Content-Type' => 'application/json',
        ],
        'organization_id' => 'sua_organization_id',
    ];

    $webhook = $webhookService->create($webhookData);
    $webhookId = $webhook['id'];

    echo "   ✅ Webhook criado com sucesso!\n";
    echo "   ID: {$webhookId}\n";
    echo "   URL: {$webhook['url']}\n";
    echo "   Eventos: " . implode(', ', $webhook['events']) . "\n\n";

    // 4. Testar webhook criado
    echo "4. Testando webhook...\n";
    $testResult = $testingService->testWebhook($webhookId);

    echo "   Teste ID: {$testResult['test_id']}\n";
    echo "   Sucesso geral: " . ($testResult['success'] ? 'Sim' : 'Não') . "\n";
    echo "   Duração: " . round($testResult['duration'], 3) . "s\n";
    echo "   Testes passaram: {$testResult['summary']['passed']}/{$testResult['summary']['total']}\n";

    if (!$testResult['success']) {
        echo "   ⚠️ Alguns testes falharam:\n";
        foreach ($testResult['tests'] as $testName => $test) {
            if (!$test['success']) {
                echo "     - {$test['name']}: {$test['error']}\n";
            }
        }
    }
    echo "\n";

    // 5. Simular evento order.paid
    echo "5. Simulando evento 'order.paid'...\n";
    $orderPaidSimulation = $testingService->simulateEvent($webhookId, 'order.paid', [
        'orderId' => 'test_order_12345',
        'customer' => [
            'name' => 'João da Silva',
            'email' => 'joao@example.com',
        ],
        'total' => 19900, // R$ 199,00 em centavos
    ]);

    if ($orderPaidSimulation['success']) {
        echo "   ✅ Evento simulado com sucesso!\n";
        echo "   Tempo de resposta: {$orderPaidSimulation['response_time']}ms\n";
        echo "   Código HTTP: {$orderPaidSimulation['response_code']}\n";
    } else {
        echo "   ❌ Falha na simulação: {$orderPaidSimulation['error']}\n";
    }
    echo "\n";

    // 6. Simular evento de carrinho abandonado
    echo "6. Simulando evento 'cart.abandoned'...\n";
    $cartAbandonedSimulation = $testingService->simulateEvent($webhookId, 'cart.abandoned');

    if ($cartAbandonedSimulation['success']) {
        echo "   ✅ Evento simulado com sucesso!\n";
    } else {
        echo "   ❌ Falha na simulação: {$cartAbandonedSimulation['error']}\n";
    }
    echo "\n";

    // 7. Gerar relatório de debug
    echo "7. Gerando relatório de debug...\n";
    $debugReport = $testingService->generateDebugReport($webhookId);

    if (isset($debugReport['error'])) {
        echo "   ❌ Erro ao gerar relatório: {$debugReport['error']}\n";
    } else {
        echo "   ✅ Relatório gerado com sucesso!\n";
        echo "   Entregas recentes: " . count($debugReport['recent_deliveries']) . "\n";
        echo "   Falhas analisadas: {$debugReport['failure_analysis']['total_failures']}\n";

        if (!empty($debugReport['recommendations'])) {
            echo "   Recomendações:\n";
            foreach ($debugReport['recommendations'] as $recommendation) {
                echo "     - {$recommendation}\n";
            }
        }
    }
    echo "\n";

    // 8. Validar configuração de webhook
    echo "8. Validando configuração do webhook...\n";
    $validation = $testingService->validateWebhookConfiguration($webhook);

    echo "   Configuração válida: " . ($validation['valid'] ? 'Sim' : 'Não') . "\n";

    if (!empty($validation['errors'])) {
        echo "   Erros:\n";
        foreach ($validation['errors'] as $error) {
            echo "     - {$error}\n";
        }
    }

    if (!empty($validation['warnings'])) {
        echo "   Avisos:\n";
        foreach ($validation['warnings'] as $warning) {
            echo "     - {$warning}\n";
        }
    }

    if (!empty($validation['suggestions'])) {
        echo "   Sugestões:\n";
        foreach ($validation['suggestions'] as $suggestion) {
            echo "     - {$suggestion}\n";
        }
    }
    echo "\n";

    // 9. Listar estatísticas do webhook
    echo "9. Estatísticas do webhook...\n";
    $stats = $webhookService->getStats($webhookId);

    if (isset($stats['error'])) {
        echo "   ❌ Erro ao obter estatísticas: {$stats['error']}\n";
    } else {
        echo "   Total de entregas: {$stats['total_deliveries']}\n";
        echo "   Entregas bem-sucedidas: {$stats['successful_deliveries']}\n";
        echo "   Entregas falhadas: {$stats['failed_deliveries']}\n";
        echo "   Taxa de sucesso: " . round($stats['success_rate'] ?? 0, 2) . "%\n";
        echo "   Tempo médio de resposta: " . ($stats['average_response_time'] ?? 0) . "ms\n";
    }
    echo "\n";

    // 10. Exemplo de payload de webhook recebido
    echo "10. Exemplo de payload que seu endpoint receberá:\n";
    echo generateExampleWebhookPayload();
    echo "\n";

    echo "=== INTEGRAÇÃO CONCLUÍDA COM SUCESSO! ===\n";
    echo "Webhook ID: {$webhookId}\n";
    echo "Verifique seu endpoint em: {$webhookUrl}\n";

} catch (Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    echo "Trace: {$e->getTraceAsString()}\n";
}

/**
 * Gera exemplo de payload de webhook order.paid
 */
function generateExampleWebhookPayload(): string
{
    $payload = [
        'event' => 'order.paid',
        'id' => 'event_' . uniqid(),
        'timestamp' => date('c'),
        'data' => [
            'eventType' => 'order.paid',
            'orderId' => 'order_123456789',
            'partnerId' => 'partner_abc',
            'organizationId' => 'org_xyz',
            'customer' => [
                'customerId' => 'cust_789',
                'name' => 'João Silva',
                'email' => 'joao@exemplo.com',
                'phone' => '+55 (11) 99999-9999',
                'document' => '12345678901'
            ],
            'items' => [
                [
                    'productId' => 'prod_digital_001',
                    'name' => 'Curso de Marketing Digital',
                    'quantity' => 1,
                    'unitPrice' => 19900,
                    'totalPrice' => 19900,
                    'type' => 'digital',
                    'imageUrl' => 'https://exemplo.com/curso-cover.jpg'
                ]
            ],
            'subtotal' => 19900,
            'shippingCost' => 0,
            'discount' => 1990,
            'total' => 17910,
            'currency' => 'BRL',
            'payment' => [
                'method' => 'credit_card',
                'brand' => 'visa',
                'lastFourDigits' => '1234',
                'installments' => 3,
                'status' => 'paid',
                'paidAt' => '2025-09-23T10:30:00Z'
            ],
            'orderDate' => '2025-09-23T10:30:00Z',
            'priority' => 'high',
            'correlationId' => 'corr_uuid_12345',
            'metadata' => [
                'utm_source' => 'google',
                'utm_medium' => 'cpc',
                'utm_campaign' => 'black_friday',
                'affiliate_code' => 'PARTNER001'
            ]
        ],
        'metadata' => [
            'source' => 'clubify-checkout',
            'version' => '1.0',
            'attempt' => 1,
            'test_mode' => false
        ]
    ];

    return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}