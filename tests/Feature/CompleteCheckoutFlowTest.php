<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Feature;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\ClubifyCheckoutSDK;

/**
 * Teste Feature completo do fluxo de checkout
 *
 * Simula um fluxo real de checkout end-to-end:
 * - Criação de produto e oferta
 * - Seleção de produtos pelo cliente
 * - Checkout com múltiplas opções
 * - Processamento de pagamento
 * - Gestão de pedidos
 * - Notificações automáticas
 * - Analytics e tracking
 *
 * Este teste representa um cenário de uso real do SDK.
 *
 * @group feature
 * @group checkout
 * @group e2e
 * @group slow
 */
class CompleteCheckoutFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Cria SDK configurado para ambiente de teste
        $this->sdk = new ClubifyCheckoutSDK([
            'api_url' => $_ENV['CLUBIFY_API_URL'] ?? 'http://localhost:8080',
            'tenant_id' => $_ENV['CLUBIFY_TENANT_ID'] ?? 'test-tenant-feature',
            'api_key' => $_ENV['CLUBIFY_API_KEY'] ?? 'test-api-key-feature',
            'secret_key' => $_ENV['CLUBIFY_SECRET_KEY'] ?? 'test-secret-key-feature',
            'debug' => true,
            'timeout' => 30,
            'retry_attempts' => 3
        ]);

        // Verifica se o SDK está funcionando
        $this->assertTrue($this->sdk->isInitialized());
    }

    /** @test */
    public function it_processes_complete_checkout_flow_successfully(): void
    {
        // ===== FASE 1: PREPARAÇÃO DO CATÁLOGO =====

        // 1.1 - Cria produto principal
        $productData = [
            'id' => 'prod_feature_test_' . uniqid(),
            'name' => 'Curso Online de PHP Avançado',
            'description' => 'Curso completo de PHP com práticas avançadas e projetos reais.',
            'price' => 19900, // R$ 199,00
            'currency' => 'BRL',
            'category' => 'education',
            'digital' => true,
            'metadata' => [
                'duration' => '40 horas',
                'modules' => 12,
                'level' => 'advanced'
            ]
        ];

        $product = $this->sdk->products()->createProduct($productData);
        $this->assertIsArray($product);
        $this->assertEquals($productData['name'], $product['name']);

        // 1.2 - Cria produto upsell
        $upsellProductData = [
            'id' => 'prod_upsell_' . uniqid(),
            'name' => 'Mentoria 1:1 - 2 horas',
            'description' => 'Sessão individual de mentoria com instrutor especializado.',
            'price' => 29900, // R$ 299,00
            'currency' => 'BRL',
            'category' => 'mentoring',
            'digital' => true
        ];

        $upsellProduct = $this->sdk->products()->createProduct($upsellProductData);
        $this->assertIsArray($upsellProduct);

        // 1.3 - Cria oferta completa com order bump
        $offerData = [
            'id' => 'offer_feature_test_' . uniqid(),
            'name' => 'Oferta Especial - Curso PHP + Bônus',
            'description' => 'Oferta limitada com desconto especial',
            'products' => [$product['id']],
            'order_bump' => [
                'product_id' => $upsellProduct['id'],
                'discount_percentage' => 20,
                'position' => 'after_products'
            ],
            'checkout_config' => [
                'theme' => 'modern',
                'layout' => 'single_column',
                'steps' => ['customer_info', 'payment', 'confirmation']
            ],
            'pricing' => [
                'base_price' => 19900,
                'discount_percentage' => 15, // 15% desconto
                'final_price' => 16915, // R$ 169,15
                'valid_until' => date('Y-m-d H:i:s', strtotime('+7 days'))
            ]
        ];

        $offer = $this->sdk->products()->createOffer($offerData);
        $this->assertIsArray($offer);
        $this->assertEquals($offerData['name'], $offer['name']);

        // ===== FASE 2: SIMULAÇÃO DO CLIENTE =====

        // 2.1 - Registra cliente
        $customerData = [
            'email' => 'cliente.feature.test+' . uniqid() . '@example.com',
            'name' => 'João Silva',
            'phone' => '+5511999888777',
            'document' => '12345678901',
            'metadata' => [
                'source' => 'organic',
                'utm_campaign' => 'feature_test',
                'utm_medium' => 'automated_test'
            ]
        ];

        $customer = $this->sdk->customers()->createCustomer($customerData);
        $this->assertIsArray($customer);
        $this->assertEquals($customerData['email'], $customer['email']);

        // 2.2 - Inicia tracking da jornada
        $sessionId = 'sess_' . uniqid();

        // Tracking: Visualização da página
        $this->sdk->tracking()->trackEvent([
            'event_type' => 'page_view',
            'user_id' => $customer['id'],
            'session_id' => $sessionId,
            'properties' => [
                'page' => '/offer/' . $offer['id'],
                'offer_id' => $offer['id'],
                'referrer' => 'https://google.com',
                'user_agent' => 'Mozilla/5.0 (Test Feature Bot)'
            ]
        ]);

        // Tracking: Início do checkout
        $this->sdk->tracking()->trackEvent([
            'event_type' => 'checkout_started',
            'user_id' => $customer['id'],
            'session_id' => $sessionId,
            'properties' => [
                'offer_id' => $offer['id'],
                'products' => [$product['id']],
                'total_value' => 16915
            ]
        ]);

        // ===== FASE 3: PROCESSO DE CHECKOUT =====

        // 3.1 - Inicia checkout
        $checkoutData = [
            'customer_id' => $customer['id'],
            'offer_id' => $offer['id'],
            'products' => [
                [
                    'product_id' => $product['id'],
                    'quantity' => 1,
                    'price' => 16915 // Preço com desconto
                ]
            ],
            'order_bump_accepted' => true, // Cliente aceita order bump
            'shipping_address' => [
                'street' => 'Rua das Flores, 123',
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'country' => 'BR'
            ],
            'metadata' => [
                'session_id' => $sessionId,
                'source' => 'web',
                'device' => 'desktop'
            ]
        ];

        $checkout = $this->sdk->checkout()->initializeCheckout($checkoutData);
        $this->assertIsArray($checkout);
        $this->assertArrayHasKey('checkout_id', $checkout);

        // 3.2 - Calcula total com order bump
        $expectedTotal = 16915 + (29900 * 0.8); // Produto principal + order bump com 20% desconto
        $this->assertEquals($expectedTotal, $checkout['total_amount']);

        // 3.3 - Processa pagamento
        $paymentData = [
            'checkout_id' => $checkout['checkout_id'],
            'payment_method' => 'credit_card',
            'amount' => $checkout['total_amount'],
            'currency' => 'BRL',
            'installments' => 3,
            'card_data' => [
                'number' => '4111111111111111',
                'holder_name' => 'JOAO SILVA',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123'
            ],
            'billing_address' => [
                'street' => 'Rua das Flores, 123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'postal_code' => '01234-567',
                'country' => 'BR'
            ]
        ];

        $payment = $this->sdk->payments()->processPayment($paymentData);
        $this->assertIsArray($payment);
        $this->assertEquals('approved', $payment['status']);
        $this->assertArrayHasKey('transaction_id', $payment);

        // ===== FASE 4: CRIAÇÃO E GESTÃO DO PEDIDO =====

        // 4.1 - Cria pedido após pagamento aprovado
        $orderData = [
            'customer_id' => $customer['id'],
            'checkout_id' => $checkout['checkout_id'],
            'payment_id' => $payment['transaction_id'],
            'status' => 'confirmed',
            'items' => [
                [
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'price' => 16915,
                    'quantity' => 1,
                    'type' => 'product'
                ],
                [
                    'product_id' => $upsellProduct['id'],
                    'name' => $upsellProduct['name'],
                    'price' => 23920, // 29900 * 0.8
                    'quantity' => 1,
                    'type' => 'order_bump'
                ]
            ],
            'total' => $checkout['total_amount'],
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'metadata' => [
                'session_id' => $sessionId,
                'offer_id' => $offer['id'],
                'order_bump_accepted' => true
            ]
        ];

        $order = $this->sdk->orders()->createOrder($orderData);
        $this->assertIsArray($order);
        $this->assertEquals($orderData['total'], $order['total']);
        $this->assertCount(2, $order['items']); // Produto principal + order bump

        // ===== FASE 5: NOTIFICAÇÕES AUTOMÁTICAS =====

        // 5.1 - Notificação de confirmação para o cliente
        $customerNotification = [
            'type' => 'order.confirmed',
            'recipient' => $customer['email'],
            'subject' => 'Pedido confirmado - ' . $order['id'],
            'body' => $this->generateOrderConfirmationEmail($order, $customer),
            'delivery_method' => 'email',
            'template_id' => 'order_confirmation',
            'template_data' => [
                'customer_name' => $customer['name'],
                'order_id' => $order['id'],
                'products' => $order['items'],
                'total' => $order['total'],
                'access_instructions' => 'Você receberá as instruções de acesso em breve.'
            ],
            'metadata' => [
                'order_id' => $order['id'],
                'customer_id' => $customer['id']
            ]
        ];

        $notification = $this->sdk->notifications()->sendNotification($customerNotification);
        $this->assertIsArray($notification);
        $this->assertTrue($notification['success']);

        // 5.2 - Notificação interna para a equipe
        $internalNotification = [
            'type' => 'order.created',
            'recipient' => 'vendas@empresa.com',
            'subject' => 'Nova venda realizada - ' . $order['id'],
            'body' => $this->generateInternalOrderNotification($order, $customer),
            'delivery_method' => 'email',
            'metadata' => [
                'order_id' => $order['id'],
                'order_value' => $order['total'],
                'customer_new' => true
            ]
        ];

        $internalNotificationResult = $this->sdk->notifications()->sendNotification($internalNotification);
        $this->assertTrue($internalNotificationResult['success']);

        // ===== FASE 6: TRACKING E ANALYTICS =====

        // 6.1 - Tracking de conversão
        $this->sdk->tracking()->trackEvent([
            'event_type' => 'purchase',
            'user_id' => $customer['id'],
            'session_id' => $sessionId,
            'properties' => [
                'order_id' => $order['id'],
                'value' => $order['total'],
                'currency' => 'BRL',
                'products' => array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'],
                        'name' => $item['name'],
                        'price' => $item['price'],
                        'quantity' => $item['quantity']
                    ];
                }, $order['items']),
                'order_bump_accepted' => true,
                'payment_method' => 'credit_card',
                'conversion_time' => time() - strtotime($checkout['created_at'])
            ]
        ]);

        // 6.2 - Analytics de performance
        $analytics = $this->sdk->orders()->getOrderAnalytics([
            'date_from' => date('Y-m-d'),
            'date_to' => date('Y-m-d'),
            'customer_id' => $customer['id']
        ]);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_orders', $analytics);
        $this->assertGreaterThanOrEqual(1, $analytics['total_orders']);

        // ===== FASE 7: GESTÃO PÓS-VENDA =====

        // 7.1 - Atualiza status do pedido para processamento
        $statusUpdate = $this->sdk->orders()->updateOrderStatus($order['id'], 'processing');
        $this->assertTrue($statusUpdate['success']);
        $this->assertEquals('processing', $statusUpdate['new_status']);

        // 7.2 - Envia notificação de processamento
        $processingNotification = [
            'type' => 'order.processing',
            'recipient' => $customer['email'],
            'subject' => 'Seu pedido está sendo processado',
            'body' => 'Estamos preparando seu pedido para entrega.',
            'delivery_method' => 'email',
            'metadata' => ['order_id' => $order['id']]
        ];

        $processingNotificationResult = $this->sdk->notifications()->sendNotification($processingNotification);
        $this->assertTrue($processingNotificationResult['success']);

        // 7.3 - Simula entrega (para produtos digitais)
        $deliveryUpdate = $this->sdk->orders()->updateOrderStatus($order['id'], 'delivered');
        $this->assertTrue($deliveryUpdate['success']);

        // 7.4 - Envia credenciais de acesso
        $accessNotification = [
            'type' => 'order.delivered',
            'recipient' => $customer['email'],
            'subject' => 'Acesso liberado - Curso PHP Avançado',
            'body' => $this->generateAccessCredentialsEmail($order, $customer),
            'delivery_method' => 'email',
            'template_id' => 'digital_delivery',
            'template_data' => [
                'customer_name' => $customer['name'],
                'access_url' => 'https://plataforma.exemplo.com/curso/php-avancado',
                'login' => $customer['email'],
                'password' => 'senha_temporaria_123',
                'support_email' => 'suporte@empresa.com'
            ],
            'metadata' => ['order_id' => $order['id']]
        ];

        $accessNotificationResult = $this->sdk->notifications()->sendNotification($accessNotification);
        $this->assertTrue($accessNotificationResult['success']);

        // ===== FASE 8: VERIFICAÇÕES FINAIS =====

        // 8.1 - Verifica histórico completo do pedido
        $orderHistory = $this->sdk->orders()->getOrderStatusHistory($order['id']);
        $this->assertIsArray($orderHistory);
        $this->assertGreaterThanOrEqual(3, count($orderHistory)); // confirmed -> processing -> delivered

        // 8.2 - Verifica estatísticas do cliente
        $customerStats = $this->sdk->customers()->getCustomerStatistics($customer['id']);
        $this->assertIsArray($customerStats);
        $this->assertEquals(1, $customerStats['total_orders']);
        $this->assertEquals($order['total'], $customerStats['total_spent']);

        // 8.3 - Verifica logs de notificações
        $notificationLogs = $this->sdk->notifications()->getNotificationLogs([
            'customer_email' => $customer['email'],
            'order_id' => $order['id']
        ]);
        $this->assertIsArray($notificationLogs);
        $this->assertGreaterThanOrEqual(3, count($notificationLogs['data'])); // 3+ notificações enviadas

        // 8.4 - Verifica eventos de tracking
        $trackingEvents = $this->sdk->tracking()->getEventAnalytics([
            'user_id' => $customer['id'],
            'session_id' => $sessionId
        ]);
        $this->assertIsArray($trackingEvents);
        $this->assertGreaterThanOrEqual(3, $trackingEvents['total_events']); // page_view, checkout_started, purchase

        // ===== VALIDAÇÃO FINAL =====

        // Verifica que todos os dados estão consistentes
        $finalOrder = $this->sdk->orders()->getOrder($order['id']);
        $finalCustomer = $this->sdk->customers()->getCustomer($customer['id']);

        $this->assertEquals('delivered', $finalOrder['status']);
        $this->assertEquals($customer['id'], $finalOrder['customer_id']);
        $this->assertEquals($order['total'], $finalOrder['total']);
        $this->assertEquals($customer['email'], $finalCustomer['email']);

        // Sucesso! Fluxo completo executado sem erros
        $this->assertTrue(true, 'Fluxo completo de checkout executado com sucesso!');
    }

    /**
     * Gera email de confirmação do pedido
     */
    private function generateOrderConfirmationEmail(array $order, array $customer): string
    {
        $itemsList = implode("\n", array_map(function ($item) {
            return "- {$item['name']} - R$ " . number_format($item['price'] / 100, 2, ',', '.');
        }, $order['items']));

        return "Olá {$customer['name']},\n\n" .
               "Seu pedido #{$order['id']} foi confirmado com sucesso!\n\n" .
               "Produtos adquiridos:\n{$itemsList}\n\n" .
               "Total: R$ " . number_format($order['total'] / 100, 2, ',', '.') . "\n\n" .
               "Você receberá as instruções de acesso em breve.\n\n" .
               "Obrigado pela sua compra!";
    }

    /**
     * Gera notificação interna de nova venda
     */
    private function generateInternalOrderNotification(array $order, array $customer): string
    {
        return "Nova venda realizada!\n\n" .
               "Pedido: {$order['id']}\n" .
               "Cliente: {$customer['name']} ({$customer['email']})\n" .
               "Valor: R$ " . number_format($order['total'] / 100, 2, ',', '.') . "\n" .
               "Produtos: " . count($order['items']) . " item(s)\n" .
               "Data: " . date('d/m/Y H:i:s');
    }

    /**
     * Gera email com credenciais de acesso
     */
    private function generateAccessCredentialsEmail(array $order, array $customer): string
    {
        return "Olá {$customer['name']},\n\n" .
               "Seu acesso ao curso foi liberado!\n\n" .
               "Dados de acesso:\n" .
               "URL: https://plataforma.exemplo.com/curso/php-avancado\n" .
               "Login: {$customer['email']}\n" .
               "Senha: senha_temporaria_123\n\n" .
               "Recomendamos alterar sua senha no primeiro acesso.\n\n" .
               "Bons estudos!";
    }
}
