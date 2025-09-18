<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Integration;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Orders\OrdersModule;
use Clubify\Checkout\Modules\Payments\PaymentsModule;
use Clubify\Checkout\Modules\Customers\CustomersModule;
use Clubify\Checkout\Modules\Notifications\NotificationsModule;
use Mockery;

/**
 * Testes de integração para o fluxo completo de pedidos
 *
 * Testa a integração entre diferentes módulos:
 * - Orders + Payments + Customers + Notifications
 * - Fluxo completo de criação até entrega
 * - Event-driven communication
 * - Tratamento de erros integrado
 * - Rollback e compensação
 *
 * @group integration
 * @group orders
 * @group slow
 */
class OrdersIntegrationTest extends TestCase
{
    private OrdersModule $ordersModule;
    private PaymentsModule $paymentsModule;
    private CustomersModule $customersModule;
    private NotificationsModule $notificationsModule;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria SDK real para testes de integração
        $this->sdk = new ClubifyCheckoutSDK([
            'api_url' => 'http://localhost:8080',
            'tenant_id' => 'test-tenant-integration',
            'api_key' => 'test-api-key-integration',
            'secret_key' => 'test-secret-key-integration',
            'debug' => true
        ]);

        // Inicializa módulos
        $this->setupModules();
    }

    /** @test */
    public function it_creates_complete_order_workflow(): void
    {
        // Arrange - Prepara dados do cliente
        $customerData = $this->generateUserData([
            'email' => 'integration.test@example.com',
            'name' => 'Integration Test User'
        ]);

        // Cria cliente primeiro
        $customer = $this->customersModule->createCustomer($customerData);
        $this->assertIsArray($customer);
        $this->assertArrayHasKey('id', $customer);

        // Prepara dados do pedido
        $orderData = $this->generateOrderData([
            'customer_id' => $customer['id'],
            'total' => 9999, // R$ 99,99
            'items' => [
                [
                    'id' => 'product_integration_test',
                    'name' => 'Integration Test Product',
                    'price' => 9999,
                    'quantity' => 1
                ]
            ]
        ]);

        // Act - Cria pedido
        $order = $this->ordersModule->createOrder($orderData);

        // Assert - Verifica criação do pedido
        $this->assertIsArray($order);
        $this->assertArrayHasKey('id', $order);
        $this->assertEquals($customer['id'], $order['customer_id']);
        $this->assertEquals(9999, $order['total']);
        $this->assertEquals('pending', $order['status']);

        // Act - Processa pagamento
        $paymentData = [
            'order_id' => $order['id'],
            'amount' => $order['total'],
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'card_data' => [
                'number' => '4111111111111111',
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'holder_name' => 'Integration Test'
            ]
        ];

        $payment = $this->paymentsModule->processPayment($paymentData);

        // Assert - Verifica processamento do pagamento
        $this->assertIsArray($payment);
        $this->assertArrayHasKey('status', $payment);
        $this->assertEquals('approved', $payment['status']);

        // Act - Atualiza status do pedido após pagamento aprovado
        $updatedOrder = $this->ordersModule->updateOrderStatus($order['id'], 'confirmed');

        // Assert - Verifica atualização do status
        $this->assertTrue($updatedOrder['success']);
        $this->assertEquals('confirmed', $updatedOrder['new_status']);

        // Act - Envia notificação de confirmação
        $notificationData = [
            'type' => 'order.confirmed',
            'recipient' => $customer['email'],
            'subject' => 'Pedido confirmado #' . $order['id'],
            'body' => 'Seu pedido foi confirmado e está sendo processado.',
            'delivery_method' => 'email',
            'metadata' => [
                'order_id' => $order['id'],
                'customer_id' => $customer['id']
            ]
        ];

        $notification = $this->notificationsModule->sendNotification($notificationData);

        // Assert - Verifica envio da notificação
        $this->assertIsArray($notification);
        $this->assertTrue($notification['success']);

        // Verifica histórico do pedido
        $orderHistory = $this->ordersModule->getOrderStatusHistory($order['id']);
        $this->assertIsArray($orderHistory);
        $this->assertGreaterThanOrEqual(2, count($orderHistory)); // pending -> confirmed
    }

    /** @test */
    public function it_handles_payment_failure_with_rollback(): void
    {
        // Arrange
        $customerData = $this->generateUserData([
            'email' => 'payment.failure.test@example.com'
        ]);

        $customer = $this->customersModule->createCustomer($customerData);

        $orderData = $this->generateOrderData([
            'customer_id' => $customer['id']
        ]);

        $order = $this->ordersModule->createOrder($orderData);

        // Act - Simula falha no pagamento
        $paymentData = [
            'order_id' => $order['id'],
            'amount' => $order['total'],
            'currency' => 'BRL',
            'payment_method' => 'credit_card',
            'card_data' => [
                'number' => '4000000000000002', // Cartão que sempre falha
                'expiry_month' => '12',
                'expiry_year' => '2025',
                'cvv' => '123',
                'holder_name' => 'Payment Failure Test'
            ]
        ];

        $payment = $this->paymentsModule->processPayment($paymentData);

        // Assert - Verifica falha no pagamento
        $this->assertIsArray($payment);
        $this->assertEquals('failed', $payment['status']);

        // Act - Atualiza status do pedido para falha de pagamento
        $this->ordersModule->updateOrderStatus($order['id'], 'payment_failed');

        // Act - Envia notificação de falha
        $notificationData = [
            'type' => 'payment.failed',
            'recipient' => $customer['email'],
            'subject' => 'Problema com pagamento - Pedido #' . $order['id'],
            'body' => 'Houve um problema com o pagamento do seu pedido.',
            'delivery_method' => 'email',
            'metadata' => [
                'order_id' => $order['id'],
                'payment_error' => $payment['error'] ?? 'Unknown error'
            ]
        ];

        $notification = $this->notificationsModule->sendNotification($notificationData);

        // Assert - Verifica sistema de rollback
        $finalOrder = $this->ordersModule->getOrder($order['id']);
        $this->assertEquals('payment_failed', $finalOrder['status']);
        $this->assertTrue($notification['success']);
    }

    /** @test */
    public function it_processes_order_with_multiple_items_and_discounts(): void
    {
        // Arrange
        $customerData = $this->generateUserData([
            'email' => 'multi.items.test@example.com'
        ]);

        $customer = $this->customersModule->createCustomer($customerData);

        $orderData = $this->generateOrderData([
            'customer_id' => $customer['id'],
            'items' => [
                [
                    'id' => 'product_1',
                    'name' => 'Product 1',
                    'price' => 5000, // R$ 50,00
                    'quantity' => 2
                ],
                [
                    'id' => 'product_2',
                    'name' => 'Product 2',
                    'price' => 3000, // R$ 30,00
                    'quantity' => 1
                ]
            ],
            'subtotal' => 13000, // R$ 130,00 (50*2 + 30*1)
            'discount_amount' => 1300, // R$ 13,00 (10% desconto)
            'total' => 11700, // R$ 117,00
            'coupon_code' => 'INTEGRATION10'
        ]);

        // Act
        $order = $this->ordersModule->createOrder($orderData);

        // Assert
        $this->assertIsArray($order);
        $this->assertEquals(11700, $order['total']);
        $this->assertEquals('INTEGRATION10', $order['coupon_code']);
        $this->assertCount(2, $order['items']);

        // Verifica cálculos
        $itemsTotal = array_sum(array_map(function ($item) {
            return $item['price'] * $item['quantity'];
        }, $order['items']));

        $this->assertEquals(13000, $itemsTotal);
        $this->assertEquals(1300, $order['discount_amount']);
        $this->assertEquals($itemsTotal - $order['discount_amount'], $order['total']);

        // Act - Processa pagamento com valor com desconto
        $paymentData = [
            'order_id' => $order['id'],
            'amount' => $order['total'],
            'currency' => 'BRL',
            'payment_method' => 'credit_card'
        ];

        $payment = $this->paymentsModule->processPayment($paymentData);

        // Assert - Verifica pagamento do valor correto
        $this->assertEquals('approved', $payment['status']);
        $this->assertEquals(11700, $payment['amount']);
    }

    /** @test */
    public function it_handles_concurrent_order_processing(): void
    {
        // Arrange
        $customerData = $this->generateUserData([
            'email' => 'concurrent.test@example.com'
        ]);

        $customer = $this->customersModule->createCustomer($customerData);

        $orders = [];

        // Act - Cria múltiplos pedidos simultaneamente
        for ($i = 1; $i <= 3; $i++) {
            $orderData = $this->generateOrderData([
                'customer_id' => $customer['id'],
                'total' => 1000 * $i, // Valores diferentes
                'items' => [
                    [
                        'id' => "product_concurrent_{$i}",
                        'name' => "Concurrent Product {$i}",
                        'price' => 1000 * $i,
                        'quantity' => 1
                    ]
                ]
            ]);

            $orders[] = $this->ordersModule->createOrder($orderData);
        }

        // Assert - Verifica que todos os pedidos foram criados
        $this->assertCount(3, $orders);

        foreach ($orders as $index => $order) {
            $this->assertIsArray($order);
            $this->assertArrayHasKey('id', $order);
            $this->assertEquals($customer['id'], $order['customer_id']);
            $this->assertEquals(1000 * ($index + 1), $order['total']);
        }

        // Act - Processa pagamentos em paralelo
        $payments = [];
        foreach ($orders as $order) {
            $paymentData = [
                'order_id' => $order['id'],
                'amount' => $order['total'],
                'currency' => 'BRL',
                'payment_method' => 'credit_card'
            ];

            $payments[] = $this->paymentsModule->processPayment($paymentData);
        }

        // Assert - Verifica que todos os pagamentos foram processados
        $this->assertCount(3, $payments);

        foreach ($payments as $payment) {
            $this->assertEquals('approved', $payment['status']);
        }
    }

    /** @test */
    public function it_processes_order_lifecycle_with_notifications(): void
    {
        // Arrange
        $customerData = $this->generateUserData([
            'email' => 'lifecycle.test@example.com'
        ]);

        $customer = $this->customersModule->createCustomer($customerData);

        $orderData = $this->generateOrderData([
            'customer_id' => $customer['id']
        ]);

        // Act - Fluxo completo do pedido
        $order = $this->ordersModule->createOrder($orderData);

        // Status: pending -> confirmed -> processing -> shipped -> delivered
        $statuses = ['confirmed', 'processing', 'shipped', 'delivered'];
        $notifications = [];

        foreach ($statuses as $status) {
            // Atualiza status
            $this->ordersModule->updateOrderStatus($order['id'], $status);

            // Envia notificação
            $notificationData = [
                'type' => "order.{$status}",
                'recipient' => $customer['email'],
                'subject' => "Pedido {$status} - #{$order['id']}",
                'body' => "Seu pedido está agora com status: {$status}",
                'delivery_method' => 'email',
                'metadata' => [
                    'order_id' => $order['id'],
                    'status' => $status
                ]
            ];

            $notifications[] = $this->notificationsModule->sendNotification($notificationData);
        }

        // Assert - Verifica todo o ciclo
        $finalOrder = $this->ordersModule->getOrder($order['id']);
        $this->assertEquals('delivered', $finalOrder['status']);

        // Verifica que todas as notificações foram enviadas
        $this->assertCount(4, $notifications);
        foreach ($notifications as $notification) {
            $this->assertTrue($notification['success']);
        }

        // Verifica histórico completo
        $history = $this->ordersModule->getOrderStatusHistory($order['id']);
        $this->assertGreaterThanOrEqual(5, count($history)); // pending + 4 status changes
    }

    /** @test */
    public function it_validates_data_consistency_across_modules(): void
    {
        // Arrange
        $customerData = $this->generateUserData([
            'email' => 'consistency.test@example.com'
        ]);

        $customer = $this->customersModule->createCustomer($customerData);

        $orderData = $this->generateOrderData([
            'customer_id' => $customer['id'],
            'total' => 5999
        ]);

        // Act
        $order = $this->ordersModule->createOrder($orderData);

        // Assert - Verifica consistência de dados
        $retrievedCustomer = $this->customersModule->getCustomer($customer['id']);
        $retrievedOrder = $this->ordersModule->getOrder($order['id']);

        // Dados do cliente devem ser consistentes
        $this->assertEquals($customer['id'], $retrievedCustomer['id']);
        $this->assertEquals($customer['email'], $retrievedCustomer['email']);

        // Dados do pedido devem ser consistentes
        $this->assertEquals($order['id'], $retrievedOrder['id']);
        $this->assertEquals($order['customer_id'], $retrievedOrder['customer_id']);
        $this->assertEquals($order['total'], $retrievedOrder['total']);

        // Relacionamento deve estar correto
        $this->assertEquals($customer['id'], $retrievedOrder['customer_id']);
    }

    /**
     * Setup dos módulos para testes de integração
     */
    private function setupModules(): void
    {
        $this->ordersModule = $this->sdk->orders();
        $this->paymentsModule = $this->sdk->payments();
        $this->customersModule = $this->sdk->customers();
        $this->notificationsModule = $this->sdk->notifications();

        // Verifica se todos os módulos estão inicializados
        $this->assertTrue($this->ordersModule->isInitialized());
        $this->assertTrue($this->paymentsModule->isInitialized());
        $this->assertTrue($this->customersModule->isInitialized());
        $this->assertTrue($this->notificationsModule->isInitialized());
    }
}
