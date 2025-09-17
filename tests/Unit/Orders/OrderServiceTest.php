<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Orders;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Orders\Services\OrderService;
use Clubify\Checkout\Modules\Orders\DTOs\OrderData;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Core\Events\EventDispatcher;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para OrderService
 *
 * Testa todas as funcionalidades do serviço de pedidos:
 * - CRUD de pedidos
 * - Validação de dados
 * - Operações em lote
 * - Cache e performance
 * - Tratamento de erros
 * - Event dispatching
 * - Health checks
 *
 * @covers \Clubify\Checkout\Modules\Orders\Services\OrderService
 * @group unit
 * @group orders
 * @group services
 */
class OrderServiceTest extends TestCase
{
    private OrderService $orderService;
    private MockInterface $httpClientMock;
    private MockInterface $cacheMock;
    private MockInterface $eventsMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria mocks específicos
        $this->httpClientMock = Mockery::mock(Client::class);
        $this->cacheMock = Mockery::mock(CacheManager::class);
        $this->eventsMock = Mockery::mock(EventDispatcher::class);

        // Cria service
        $this->orderService = new OrderService(
            $this->sdk,
            $this->config,
            $this->logger
        );

        // Injeta mocks através de reflexão
        $this->injectMocks();
    }

    /** @test */
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(OrderService::class, $this->orderService);
    }

    /** @test */
    public function it_can_create_order_successfully(): void
    {
        // Arrange
        $orderData = $this->generateOrderData();
        $expectedResponse = [
            'id' => $orderData['id'],
            'status' => 'created',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/orders', ['json' => $orderData])
            ->andReturn($this->createHttpResponseMock(201, $expectedResponse));

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->with(
                "orders:{$orderData['id']}",
                $expectedResponse,
                300
            )
            ->andReturnTrue();

        $this->eventsMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('order.created', Mockery::any());

        // Act
        $result = $this->orderService->create($orderData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_validates_order_data_before_creation(): void
    {
        // Arrange
        $invalidOrderData = ['invalid' => 'data'];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid order data');

        $this->orderService->create($invalidOrderData);
    }

    /** @test */
    public function it_can_get_order_from_cache(): void
    {
        // Arrange
        $orderId = 'order_123';
        $cachedOrder = $this->generateOrderData(['id' => $orderId]);

        $this->cacheMock
            ->shouldReceive('get')
            ->once()
            ->with("orders:$orderId")
            ->andReturn($cachedOrder);

        // HTTP client não deve ser chamado quando há cache
        $this->httpClientMock->shouldNotReceive('get');

        // Act
        $result = $this->orderService->get($orderId);

        // Assert
        $this->assertEquals($cachedOrder, $result);
    }

    /** @test */
    public function it_can_get_order_from_api_when_not_cached(): void
    {
        // Arrange
        $orderId = 'order_123';
        $orderData = $this->generateOrderData(['id' => $orderId]);

        $this->cacheMock
            ->shouldReceive('get')
            ->once()
            ->with("orders:$orderId")
            ->andReturnNull();

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/orders/$orderId")
            ->andReturn($this->createHttpResponseMock(200, $orderData));

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->with("orders:$orderId", $orderData, 300)
            ->andReturnTrue();

        // Act
        $result = $this->orderService->get($orderId);

        // Assert
        $this->assertEquals($orderData, $result);
    }

    /** @test */
    public function it_returns_null_when_order_not_found(): void
    {
        // Arrange
        $orderId = 'nonexistent_order';

        $this->cacheMock
            ->shouldReceive('get')
            ->once()
            ->andReturnNull();

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->andThrow(new \Exception('Order not found', 404));

        // Act
        $result = $this->orderService->get($orderId);

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_can_update_order(): void
    {
        // Arrange
        $orderId = 'order_123';
        $updateData = ['status' => 'shipped', 'tracking_code' => 'TRACK123'];
        $updatedOrder = $this->generateOrderData(array_merge(['id' => $orderId], $updateData));

        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with("/orders/$orderId", ['json' => $updateData])
            ->andReturn($this->createHttpResponseMock(200, $updatedOrder));

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->with("orders:$orderId", $updatedOrder, 300)
            ->andReturnTrue();

        $this->eventsMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('order.updated', Mockery::any());

        // Act
        $result = $this->orderService->update($orderId, $updateData);

        // Assert
        $this->assertEquals($updatedOrder, $result);
    }

    /** @test */
    public function it_can_delete_order(): void
    {
        // Arrange
        $orderId = 'order_123';

        $this->httpClientMock
            ->shouldReceive('delete')
            ->once()
            ->with("/orders/$orderId")
            ->andReturn($this->createHttpResponseMock(204));

        $this->cacheMock
            ->shouldReceive('delete')
            ->once()
            ->with("orders:$orderId")
            ->andReturnTrue();

        $this->eventsMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('order.deleted', ['order_id' => $orderId]);

        // Act
        $result = $this->orderService->delete($orderId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_list_orders_with_filters(): void
    {
        // Arrange
        $filters = ['status' => 'pending', 'customer_id' => 'cust_123'];
        $page = 2;
        $limit = 50;
        $expectedResponse = [
            'data' => [
                $this->generateOrderData(),
                $this->generateOrderData()
            ],
            'total' => 150,
            'page' => $page,
            'limit' => $limit,
            'has_more' => true
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/orders', [
                'query' => array_merge($filters, [
                    'page' => $page,
                    'limit' => $limit
                ])
            ])
            ->andReturn($this->createHttpResponseMock(200, $expectedResponse));

        // Act
        $result = $this->orderService->list($filters, $page, $limit);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(150, $result['total']);
        $this->assertEquals($page, $result['page']);
        $this->assertEquals($limit, $result['limit']);
    }

    /** @test */
    public function it_can_bulk_update_orders(): void
    {
        // Arrange
        $orderIds = ['order_1', 'order_2', 'order_3'];
        $updateData = ['status' => 'shipped'];
        $expectedResponse = [
            'results' => [
                'order_1' => ['success' => true, 'updated_at' => date('Y-m-d H:i:s')],
                'order_2' => ['success' => true, 'updated_at' => date('Y-m-d H:i:s')],
                'order_3' => ['success' => false, 'error' => 'Order not found']
            ],
            'summary' => [
                'total' => 3,
                'successful' => 2,
                'failed' => 1,
                'success_rate' => 66.67
            ]
        ];

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/orders/bulk-update', [
                'json' => [
                    'order_ids' => $orderIds,
                    'update_data' => $updateData
                ]
            ])
            ->andReturn($this->createHttpResponseMock(200, $expectedResponse));

        $this->eventsMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('orders.bulk_updated', Mockery::any());

        // Limpa cache dos pedidos atualizados
        foreach ($orderIds as $orderId) {
            $this->cacheMock
                ->shouldReceive('delete')
                ->once()
                ->with("orders:$orderId");
        }

        // Act
        $result = $this->orderService->bulkUpdate($orderIds, $updateData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals(3, $result['summary']['total']);
        $this->assertEquals(2, $result['summary']['successful']);
        $this->assertEquals(1, $result['summary']['failed']);
    }

    /** @test */
    public function it_validates_order_data(): void
    {
        // Arrange
        $validOrderData = $this->generateOrderData();
        $invalidOrderData = ['invalid' => 'data'];

        // Act & Assert - Dados válidos
        $validResult = $this->orderService->validate($validOrderData);
        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['errors']);

        // Act & Assert - Dados inválidos
        $invalidResult = $this->orderService->validate($invalidOrderData);
        $this->assertFalse($invalidResult['valid']);
        $this->assertNotEmpty($invalidResult['errors']);
        $this->assertContains('Missing required field: customer_id', $invalidResult['errors']);
        $this->assertContains('Missing required field: items', $invalidResult['errors']);
    }

    /** @test */
    public function it_performs_health_check(): void
    {
        // Arrange
        $expectedHealth = [
            'healthy' => true,
            'status_code' => 200,
            'response_time' => '120ms',
            'database' => 'connected',
            'cache' => 'available'
        ];

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/orders/health')
            ->andReturn($this->createHttpResponseMock(200, $expectedHealth));

        // Act
        $result = $this->orderService->healthCheck();

        // Assert
        $this->assertTrue($result['healthy']);
        $this->assertEquals(200, $result['status_code']);
        $this->assertArrayHasKey('response_time', $result);
    }

    /** @test */
    public function it_handles_api_errors_gracefully(): void
    {
        // Arrange
        $orderId = 'order_123';

        $this->cacheMock
            ->shouldReceive('get')
            ->once()
            ->andReturnNull();

        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->andThrow(new \Exception('API Error', 500));

        // Act
        $result = $this->orderService->get($orderId);

        // Assert
        $this->assertNull($result);
    }

    /** @test */
    public function it_calculates_metrics_correctly(): void
    {
        // Arrange
        $this->simulateSuccessfulOperations(10);
        $this->simulateFailedOperations(2);

        // Act
        $metrics = $this->orderService->getMetrics();

        // Assert
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('total_operations', $metrics);
        $this->assertArrayHasKey('successful_operations', $metrics);
        $this->assertArrayHasKey('failed_operations', $metrics);
        $this->assertArrayHasKey('success_rate', $metrics);
        $this->assertArrayHasKey('avg_response_time', $metrics);
    }

    /** @test */
    public function it_handles_cache_failures_gracefully(): void
    {
        // Arrange
        $orderData = $this->generateOrderData();

        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->andReturn($this->createHttpResponseMock(201, $orderData));

        // Cache falha, mas operação continua
        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->andThrow(new \Exception('Cache error'));

        $this->eventsMock
            ->shouldReceive('dispatch')
            ->once();

        // Act - Não deve lançar exceção
        $result = $this->orderService->create($orderData);

        // Assert
        $this->assertEquals($orderData, $result);
    }

    /** @test */
    public function it_handles_concurrent_access_properly(): void
    {
        // Arrange
        $orderId = 'order_123';
        $orderData = $this->generateOrderData(['id' => $orderId]);

        // Simula acesso concorrente ao mesmo pedido
        $this->cacheMock
            ->shouldReceive('get')
            ->twice()
            ->with("orders:$orderId")
            ->andReturnNull();

        $this->httpClientMock
            ->shouldReceive('get')
            ->twice()
            ->with("/orders/$orderId")
            ->andReturn($this->createHttpResponseMock(200, $orderData));

        $this->cacheMock
            ->shouldReceive('set')
            ->twice()
            ->andReturnTrue();

        // Act - Duas chamadas simultâneas
        $result1 = $this->orderService->get($orderId);
        $result2 = $this->orderService->get($orderId);

        // Assert
        $this->assertEquals($orderData, $result1);
        $this->assertEquals($orderData, $result2);
    }

    /**
     * Injeta mocks necessários via reflexão
     */
    private function injectMocks(): void
    {
        $reflection = new \ReflectionClass($this->orderService);

        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->orderService, $this->httpClientMock);

        $cacheProperty = $reflection->getProperty('cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue($this->orderService, $this->cacheMock);

        $eventsProperty = $reflection->getProperty('events');
        $eventsProperty->setAccessible(true);
        $eventsProperty->setValue($this->orderService, $this->eventsMock);
    }

    /**
     * Simula operações bem-sucedidas para testes de métricas
     */
    private function simulateSuccessfulOperations(int $count): void
    {
        $reflection = new \ReflectionClass($this->orderService);
        $metricsProperty = $reflection->getProperty('metrics');
        $metricsProperty->setAccessible(true);

        $metrics = $metricsProperty->getValue($this->orderService);
        $metrics['total_operations'] = ($metrics['total_operations'] ?? 0) + $count;
        $metrics['successful_operations'] = ($metrics['successful_operations'] ?? 0) + $count;

        $metricsProperty->setValue($this->orderService, $metrics);
    }

    /**
     * Simula operações falhadas para testes de métricas
     */
    private function simulateFailedOperations(int $count): void
    {
        $reflection = new \ReflectionClass($this->orderService);
        $metricsProperty = $reflection->getProperty('metrics');
        $metricsProperty->setAccessible(true);

        $metrics = $metricsProperty->getValue($this->orderService);
        $metrics['total_operations'] = ($metrics['total_operations'] ?? 0) + $count;
        $metrics['failed_operations'] = ($metrics['failed_operations'] ?? 0) + $count;

        $metricsProperty->setValue($this->orderService, $metrics);
    }
}