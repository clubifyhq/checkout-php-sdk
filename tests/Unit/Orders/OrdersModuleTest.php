<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Orders;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Orders\OrdersModule;
use Clubify\Checkout\Modules\Orders\Services\OrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderStatusService;
use Clubify\Checkout\Modules\Orders\Services\UpsellOrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderAnalyticsService;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para OrdersModule
 *
 * Testa todas as funcionalidades do módulo de pedidos:
 * - Inicialização e configuração
 * - CRUD de pedidos
 * - Gestão de status
 * - Processamento de upsells
 * - Analytics e relatórios
 * - Health checks e métricas
 * - Operações em lote
 *
 * @covers \Clubify\Checkout\Modules\Orders\OrdersModule
 * @group unit
 * @group orders
 */
class OrdersModuleTest extends TestCase
{
    private OrdersModule $ordersModule;
    private MockInterface $orderService;
    private MockInterface $orderStatusService;
    private MockInterface $upsellOrderService;
    private MockInterface $orderAnalyticsService;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria módulo
        $this->ordersModule = new OrdersModule($this->sdk);

        // Mock dos services
        $this->orderService = Mockery::mock(OrderService::class);
        $this->orderStatusService = Mockery::mock(OrderStatusService::class);
        $this->upsellOrderService = Mockery::mock(UpsellOrderService::class);
        $this->orderAnalyticsService = Mockery::mock(OrderAnalyticsService::class);
    }

    /** @test */
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(OrdersModule::class, $this->ordersModule);
        $this->assertFalse($this->ordersModule->isInitialized());
        $this->assertEquals('orders', $this->ordersModule->getName());
        $this->assertEquals('1.0.0', $this->ordersModule->getVersion());
    }

    /** @test */
    public function it_can_be_initialized(): void
    {
        // Act
        $this->ordersModule->initialize($this->config, $this->logger);

        // Assert
        $this->assertTrue($this->ordersModule->isInitialized());
        $this->assertTrue($this->ordersModule->isAvailable());
        $this->assertTrue($this->ordersModule->isHealthy());
    }

    /** @test */
    public function it_returns_correct_dependencies(): void
    {
        $dependencies = $this->ordersModule->getDependencies();

        $this->assertIsArray($dependencies);
        $this->assertContains('payments', $dependencies);
        $this->assertContains('customers', $dependencies);
    }

    /** @test */
    public function it_returns_module_status(): void
    {
        $this->ordersModule->initialize($this->config, $this->logger);

        $status = $this->ordersModule->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('name', $status);
        $this->assertArrayHasKey('version', $status);
        $this->assertArrayHasKey('initialized', $status);
        $this->assertArrayHasKey('available', $status);
        $this->assertArrayHasKey('services_loaded', $status);
        $this->assertEquals('orders', $status['name']);
        $this->assertEquals('1.0.0', $status['version']);
        $this->assertTrue($status['initialized']);
        $this->assertTrue($status['available']);
    }

    /** @test */
    public function it_throws_exception_when_not_initialized(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Orders module is not initialized');

        $this->ordersModule->createOrder($this->generateOrderData());
    }

    /** @test */
    public function it_can_create_order(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderData = $this->generateOrderData();
        $expectedResponse = ['id' => $orderData['id'], 'status' => 'created'];

        // Mock do service interno (através de reflexão para testar lazy loading)
        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('create')
            ->once()
            ->with($orderData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->createOrder($orderData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_order(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $expectedOrder = $this->generateOrderData(['id' => $orderId]);

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('get')
            ->once()
            ->with($orderId)
            ->andReturn($expectedOrder);

        // Act
        $result = $this->ordersModule->getOrder($orderId);

        // Assert
        $this->assertEquals($expectedOrder, $result);
    }

    /** @test */
    public function it_can_update_order(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $updateData = ['status' => 'shipped'];
        $expectedResponse = ['id' => $orderId, 'status' => 'shipped'];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('update')
            ->once()
            ->with($orderId, $updateData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->updateOrder($orderId, $updateData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_list_orders(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $filters = ['status' => 'pending'];
        $page = 2;
        $limit = 50;
        $expectedResponse = [
            'data' => [$this->generateOrderData(), $this->generateOrderData()],
            'total' => 150,
            'page' => $page,
            'limit' => $limit
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('list')
            ->once()
            ->with($filters, $page, $limit)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->listOrders($filters, $page, $limit);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_update_order_status(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $newStatus = 'shipped';
        $expectedResponse = ['success' => true, 'old_status' => 'pending', 'new_status' => $newStatus];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderStatusService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderStatusService);

        $this->orderStatusService
            ->shouldReceive('updateStatus')
            ->once()
            ->with($orderId, $newStatus)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->updateOrderStatus($orderId, $newStatus);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_cancel_order(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $reason = 'Customer request';

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderStatusService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderStatusService);

        $this->orderStatusService
            ->shouldReceive('cancel')
            ->once()
            ->with($orderId, $reason)
            ->andReturn(true);

        // Act
        $result = $this->ordersModule->cancelOrder($orderId, $reason);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_order_history(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $expectedHistory = [
            ['status' => 'pending', 'date' => '2024-01-01 10:00:00'],
            ['status' => 'confirmed', 'date' => '2024-01-01 10:30:00'],
            ['status' => 'shipped', 'date' => '2024-01-01 14:00:00']
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderStatusService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderStatusService);

        $this->orderStatusService
            ->shouldReceive('getStatusHistory')
            ->once()
            ->with($orderId)
            ->andReturn($expectedHistory);

        // Act
        $result = $this->ordersModule->getOrderStatusHistory($orderId);

        // Assert
        $this->assertEquals($expectedHistory, $result);
    }

    /** @test */
    public function it_can_process_upsell(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderId = 'order_123';
        $upsellData = ['product_id' => 'prod_456', 'quantity' => 1];
        $expectedResponse = ['success' => true, 'upsell_id' => 'upsell_789'];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('upsellOrderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->upsellOrderService);

        $this->upsellOrderService
            ->shouldReceive('processUpsell')
            ->once()
            ->with($orderId, $upsellData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->processOrderUpsell($orderId, $upsellData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_analytics(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $filters = ['date_from' => '2024-01-01', 'date_to' => '2024-01-31'];
        $expectedAnalytics = [
            'total_orders' => 150,
            'total_revenue' => 45000,
            'avg_order_value' => 300,
            'conversion_rate' => 3.5
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderAnalyticsService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderAnalyticsService);

        $this->orderAnalyticsService
            ->shouldReceive('getAnalytics')
            ->once()
            ->with($filters)
            ->andReturn($expectedAnalytics);

        // Act
        $result = $this->ordersModule->getOrderAnalytics($filters);

        // Assert
        $this->assertEquals($expectedAnalytics, $result);
    }

    /** @test */
    public function it_can_get_revenue_statistics(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $period = 'monthly';
        $expectedStats = [
            'current_period' => 15000,
            'previous_period' => 12000,
            'growth_rate' => 25.0,
            'trend' => 'up'
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderAnalyticsService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderAnalyticsService);

        $this->orderAnalyticsService
            ->shouldReceive('getRevenueStatistics')
            ->once()
            ->with($period)
            ->andReturn($expectedStats);

        // Act
        $result = $this->ordersModule->getRevenueStatistics($period);

        // Assert
        $this->assertEquals($expectedStats, $result);
    }

    /** @test */
    public function it_performs_health_check(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $expectedHealth = [
            'healthy' => true,
            'status_code' => 200,
            'response_time' => '120ms',
            'dependencies' => ['payments' => true, 'customers' => true]
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn($expectedHealth);

        // Act
        $result = $this->ordersModule->performHealthCheck();

        // Assert
        $this->assertEquals($expectedHealth, $result);
    }

    /** @test */
    public function it_can_bulk_update_orders(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderIds = ['order_1', 'order_2', 'order_3'];
        $updateData = ['status' => 'shipped'];
        $expectedResponse = [
            'results' => [
                'order_1' => ['success' => true],
                'order_2' => ['success' => true],
                'order_3' => ['success' => false, 'error' => 'Order not found']
            ],
            'summary' => ['total' => 3, 'successful' => 2, 'failed' => 1]
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('bulkUpdate')
            ->once()
            ->with($orderIds, $updateData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->ordersModule->bulkUpdateOrders($orderIds, $updateData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_cleanup_properly(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);

        // Act
        $this->ordersModule->cleanup();

        // Assert
        $this->assertFalse($this->ordersModule->isInitialized());
        $this->assertFalse($this->ordersModule->isAvailable());
    }

    /** @test */
    public function it_returns_stats(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);

        // Act
        $stats = $this->ordersModule->getStats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('module', $stats);
        $this->assertArrayHasKey('version', $stats);
        $this->assertArrayHasKey('initialized', $stats);
        $this->assertArrayHasKey('healthy', $stats);
        $this->assertArrayHasKey('timestamp', $stats);
        $this->assertEquals('orders', $stats['module']);
        $this->assertEquals('1.0.0', $stats['version']);
        $this->assertTrue($stats['initialized']);
        $this->assertTrue($stats['healthy']);
    }

    /** @test */
    public function it_handles_invalid_operations_gracefully(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('get')
            ->once()
            ->with('invalid_order_id')
            ->andReturn(null);

        // Act
        $result = $this->ordersModule->getOrder('invalid_order_id');

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_validates_order_data(): void
    {
        // Arrange
        $this->ordersModule->initialize($this->config, $this->logger);
        $orderData = ['invalid' => 'data'];
        $expectedValidation = [
            'valid' => false,
            'errors' => ['Missing required field: customer_id', 'Missing required field: items']
        ];

        $reflection = new \ReflectionClass($this->ordersModule);
        $serviceProperty = $reflection->getProperty('orderService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->ordersModule, $this->orderService);

        $this->orderService
            ->shouldReceive('validate')
            ->once()
            ->with($orderData)
            ->andReturn($expectedValidation);

        // Act
        $result = $this->ordersModule->validateOrderData($orderData);

        // Assert
        $this->assertEquals($expectedValidation, $result);
    }
}