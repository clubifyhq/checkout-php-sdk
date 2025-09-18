# 🚀 Exemplos Práticos - Arquitetura Híbrida SDK

## 📋 Visão Geral

Este documento apresenta **exemplos práticos** de implementação da arquitetura híbrida (Repository + Factory Pattern) no SDK Clubify Checkout. Os exemplos demonstram cenários reais e patterns aplicados.

---

## 🏗️ Exemplo 1: Implementação Completa de OrderManagement

Vamos implementar um módulo completo de gerenciamento de pedidos seguindo nossa arquitetura.

### 1.1 Repository Interface

```php
<?php
// src/Modules/OrderManagement/Contracts/OrderRepositoryInterface.php

namespace Clubify\Checkout\Modules\OrderManagement\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

interface OrderRepositoryInterface extends RepositoryInterface
{
    /**
     * Find order by order number
     */
    public function findByOrderNumber(string $orderNumber): ?array;

    /**
     * Find orders by customer
     */
    public function findByCustomer(string $customerId, array $filters = []): array;

    /**
     * Update order status
     */
    public function updateStatus(string $orderId, string $status): bool;

    /**
     * Get order statistics
     */
    public function getOrderStats(array $filters = []): array;

    /**
     * Process payment for order
     */
    public function processPayment(string $orderId, array $paymentData): array;

    /**
     * Add item to order
     */
    public function addItem(string $orderId, array $itemData): array;

    /**
     * Remove item from order
     */
    public function removeItem(string $orderId, string $itemId): bool;

    /**
     * Apply coupon to order
     */
    public function applyCoupon(string $orderId, string $couponCode): array;
}
```

### 1.2 Repository Implementation

```php
<?php
// src/Modules/OrderManagement/Repositories/ApiOrderRepository.php

namespace Clubify\Checkout\Modules\OrderManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\OrderManagement\Contracts\OrderRepositoryInterface;
use Clubify\Checkout\Modules\OrderManagement\Exceptions\OrderNotFoundException;
use Clubify\Checkout\Exceptions\HttpException;

class ApiOrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    protected function getEndpoint(): string
    {
        return '/orders';
    }

    protected function getResourceName(): string
    {
        return 'order';
    }

    protected function getServiceName(): string
    {
        return 'order-management';
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("order:number:{$orderNumber}"),
            function () use ($orderNumber) {
                $response = $this->httpClient->get("/orders/search", [
                    'order_number' => $orderNumber
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find order by number: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['orders'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    public function findByCustomer(string $customerId, array $filters = []): array
    {
        $filters['customer_id'] = $customerId;
        return $this->findAll($filters);
    }

    public function updateStatus(string $orderId, string $status): bool
    {
        return $this->executeWithMetrics('update_order_status', function () use ($orderId, $status) {
            $response = $this->httpClient->patch("/orders/{$orderId}/status", [
                'status' => $status
            ]);

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateOrderCache($orderId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Order.StatusUpdated', [
                    'order_id' => $orderId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    public function getOrderStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("order:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $response = $this->httpClient->get("/orders/stats", $filters);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get order statistics: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            600 // 10 minutes cache for statistics
        );
    }

    public function processPayment(string $orderId, array $paymentData): array
    {
        return $this->executeWithMetrics('process_order_payment', function () use ($orderId, $paymentData) {
            $response = $this->httpClient->post("/orders/{$orderId}/payment", $paymentData);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to process payment: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Invalidate order cache as payment status changed
            $this->invalidateOrderCache($orderId);

            // Dispatch payment event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Order.PaymentProcessed', [
                'order_id' => $orderId,
                'payment_id' => $result['payment_id'] ?? null,
                'amount' => $paymentData['amount'] ?? 0,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    public function addItem(string $orderId, array $itemData): array
    {
        return $this->executeWithMetrics('add_order_item', function () use ($orderId, $itemData) {
            $response = $this->httpClient->post("/orders/{$orderId}/items", $itemData);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to add item to order: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            // Invalidate order cache as items changed
            $this->invalidateOrderCache($orderId);

            return $response->getData();
        });
    }

    public function removeItem(string $orderId, string $itemId): bool
    {
        return $this->executeWithMetrics('remove_order_item', function () use ($orderId, $itemId) {
            $response = $this->httpClient->delete("/orders/{$orderId}/items/{$itemId}");

            if ($response->isSuccessful()) {
                // Invalidate order cache as items changed
                $this->invalidateOrderCache($orderId);
                return true;
            }

            return false;
        });
    }

    public function applyCoupon(string $orderId, string $couponCode): array
    {
        return $this->executeWithMetrics('apply_order_coupon', function () use ($orderId, $couponCode) {
            $response = $this->httpClient->post("/orders/{$orderId}/coupon", [
                'coupon_code' => $couponCode
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to apply coupon: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            // Invalidate order cache as pricing changed
            $this->invalidateOrderCache($orderId);

            return $response->getData();
        });
    }

    private function invalidateOrderCache(string $orderId): void
    {
        $patterns = [
            $this->getCacheKey("order:{$orderId}"),
            $this->getCacheKey("order:*:{$orderId}"),
            $this->getCacheKey("order:stats:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }
}
```

### 1.3 Service Implementation

```php
<?php
// src/Modules/OrderManagement/Services/OrderService.php

namespace Clubify\Checkout\Modules\OrderManagement\Services;

use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\OrderManagement\Contracts\OrderRepositoryInterface;
use Clubify\Checkout\Modules\OrderManagement\DTOs\OrderData;
use Clubify\Checkout\Modules\OrderManagement\Exceptions\OrderNotFoundException;
use Clubify\Checkout\Modules\OrderManagement\Exceptions\OrderValidationException;

class OrderService implements ServiceInterface
{
    // Valid status transitions
    private const STATUS_TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['completed', 'returned'],
        'completed' => [],
        'cancelled' => [],
        'returned' => ['refunded'],
        'refunded' => []
    ];

    public function __construct(
        private OrderRepositoryInterface $repository,
        private Logger $logger
    ) {}

    public function getName(): string
    {
        return 'order_service';
    }

    public function getVersion(): string
    {
        return '2.0.0';
    }

    public function isHealthy(): bool
    {
        try {
            $this->repository->count();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('OrderService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'repository_type' => get_class($this->repository),
            'timestamp' => time()
        ];
    }

    /**
     * Create a new order with validation
     */
    public function createOrder(array $orderData): array
    {
        $this->logger->info('Creating order', [
            'customer_id' => $orderData['customer_id'] ?? 'unknown'
        ]);

        try {
            // Validate and prepare order data
            $order = new OrderData($orderData);
            $order->validate();

            // Apply business rules for creation
            $this->applyCreationBusinessRules($order);

            // Generate order number
            $order->orderNumber = $this->generateOrderNumber();

            // Calculate totals
            $this->calculateOrderTotals($order);

            // Create order
            $createdOrder = $this->repository->create($order->toArray());

            $this->logger->info('Order created successfully', [
                'order_id' => $createdOrder['id'],
                'order_number' => $createdOrder['order_number'],
                'total' => $createdOrder['total']
            ]);

            // Post-creation processing
            $this->postCreationProcessing($createdOrder);

            return [
                'success' => true,
                'order_id' => $createdOrder['id'],
                'order' => $createdOrder
            ];

        } catch (OrderValidationException $e) {
            $this->logger->warning('Order validation failed', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to create order', [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
            throw $e;
        }
    }

    /**
     * Process order payment
     */
    public function processPayment(string $orderId, array $paymentData): array
    {
        $this->logger->info('Processing order payment', [
            'order_id' => $orderId,
            'payment_method' => $paymentData['method'] ?? 'unknown'
        ]);

        try {
            // Verify order exists and is payable
            $order = $this->getOrderForPayment($orderId);

            // Validate payment data
            $this->validatePaymentData($paymentData, $order);

            // Process payment through repository
            $result = $this->repository->processPayment($orderId, $paymentData);

            $this->logger->info('Order payment processed successfully', [
                'order_id' => $orderId,
                'payment_id' => $result['payment_id'] ?? null
            ]);

            // Update order status if payment successful
            if ($result['status'] === 'approved') {
                $this->updateOrderStatus($orderId, 'confirmed');
            }

            return [
                'success' => true,
                'payment_result' => $result
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process order payment', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Update order status with business rules validation
     */
    public function updateOrderStatus(string $orderId, string $newStatus): array
    {
        $this->logger->info('Updating order status', [
            'order_id' => $orderId,
            'new_status' => $newStatus
        ]);

        try {
            // Get current order
            $currentOrder = $this->repository->findById($orderId);
            if (!$currentOrder) {
                throw OrderNotFoundException::byId($orderId);
            }

            // Validate status transition
            $this->validateStatusTransition($currentOrder['status'], $newStatus);

            // Apply status change business rules
            $this->applyStatusChangeRules($currentOrder, $newStatus);

            // Update status
            $updated = $this->repository->updateStatus($orderId, $newStatus);

            if ($updated) {
                $this->logger->info('Order status updated successfully', [
                    'order_id' => $orderId,
                    'old_status' => $currentOrder['status'],
                    'new_status' => $newStatus
                ]);

                // Trigger status-specific workflows
                $this->triggerStatusWorkflows($orderId, $newStatus, $currentOrder);

                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'old_status' => $currentOrder['status'],
                    'new_status' => $newStatus
                ];
            }

            throw new \Exception('Failed to update order status');

        } catch (OrderNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update order status', [
                'order_id' => $orderId,
                'status' => $newStatus,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get order analytics with caching
     */
    public function getOrderAnalytics(array $filters = []): array
    {
        $this->logger->debug('Getting order analytics', ['filters' => $filters]);

        try {
            $stats = $this->repository->getOrderStats($filters);

            // Enrich with calculated metrics
            $stats['conversion_rate'] = $this->calculateConversionRate($stats);
            $stats['average_order_value'] = $this->calculateAverageOrderValue($stats);

            return [
                'success' => true,
                'analytics' => $stats
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get order analytics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // Private helper methods for business logic
    private function applyCreationBusinessRules(OrderData $order): void
    {
        // Example: Apply minimum order value
        if ($order->subtotal < 10.00) {
            throw new OrderValidationException('Minimum order value is $10.00');
        }

        // Example: Validate customer credit limit
        $this->validateCustomerCreditLimit($order);
    }

    private function validateCustomerCreditLimit(OrderData $order): void
    {
        // Business rule: Check if customer has enough credit
        // This would typically call another service/repository
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function calculateOrderTotals(OrderData $order): void
    {
        // Calculate subtotal from items
        $subtotal = 0;
        foreach ($order->items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $order->subtotal = $subtotal;

        // Calculate tax
        $order->tax = $subtotal * 0.1; // 10% tax rate

        // Calculate shipping
        $order->shipping = $this->calculateShipping($order);

        // Calculate total
        $order->total = $order->subtotal + $order->tax + $order->shipping;
    }

    private function calculateShipping(OrderData $order): float
    {
        // Example shipping calculation
        return $order->subtotal > 100 ? 0 : 9.99;
    }

    private function getOrderForPayment(string $orderId): array
    {
        $order = $this->repository->findById($orderId);
        if (!$order) {
            throw OrderNotFoundException::byId($orderId);
        }

        if (!in_array($order['status'], ['pending', 'confirmed'])) {
            throw new OrderValidationException(
                "Order {$orderId} is not in a payable status (current: {$order['status']})"
            );
        }

        return $order;
    }

    private function validatePaymentData(array $paymentData, array $order): void
    {
        if (empty($paymentData['method'])) {
            throw new OrderValidationException('Payment method is required');
        }

        if (empty($paymentData['amount']) || $paymentData['amount'] != $order['total']) {
            throw new OrderValidationException('Payment amount must match order total');
        }
    }

    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        if (!isset(self::STATUS_TRANSITIONS[$currentStatus])) {
            throw new OrderValidationException("Invalid current status: {$currentStatus}");
        }

        if (!in_array($newStatus, self::STATUS_TRANSITIONS[$currentStatus])) {
            throw new OrderValidationException(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'"
            );
        }
    }

    private function applyStatusChangeRules(array $order, string $newStatus): void
    {
        // Example: Validate inventory before confirming
        if ($newStatus === 'confirmed') {
            $this->validateInventoryAvailability($order);
        }

        // Example: Validate shipping info before processing
        if ($newStatus === 'processing') {
            $this->validateShippingInfo($order);
        }
    }

    private function validateInventoryAvailability(array $order): void
    {
        // Would check inventory levels for each item
    }

    private function validateShippingInfo(array $order): void
    {
        // Would validate shipping address and method
    }

    private function triggerStatusWorkflows(string $orderId, string $status, array $order): void
    {
        switch ($status) {
            case 'confirmed':
                // Trigger inventory reservation
                // Send confirmation email
                break;
            case 'shipped':
                // Send tracking info
                break;
            case 'delivered':
                // Send delivery confirmation
                // Start return window timer
                break;
        }
    }

    private function calculateConversionRate(array $stats): float
    {
        if (empty($stats['total_visitors']) || $stats['total_visitors'] == 0) {
            return 0;
        }

        return round(($stats['completed_orders'] / $stats['total_visitors']) * 100, 2);
    }

    private function calculateAverageOrderValue(array $stats): float
    {
        if (empty($stats['completed_orders']) || $stats['completed_orders'] == 0) {
            return 0;
        }

        return round($stats['total_revenue'] / $stats['completed_orders'], 2);
    }

    private function postCreationProcessing(array $order): void
    {
        // Send order confirmation email
        // Create inventory reservations
        // Trigger analytics events
        // etc.
    }
}
```

### 1.4 Factory Implementation

```php
<?php
// src/Modules/OrderManagement/Factories/OrderServiceFactory.php

namespace Clubify\Checkout\Modules\OrderManagement\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\OrderManagement\Services\OrderService;
use Clubify\Checkout\Modules\OrderManagement\Repositories\ApiOrderRepository;

class OrderServiceFactory implements FactoryInterface
{
    private array $services = [];
    private array $repositories = [];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function create(string $type, array $config = []): object
    {
        if (isset($this->services[$type])) {
            return $this->services[$type];
        }

        switch ($type) {
            case 'order':
                $repository = $this->createRepository('order');
                $this->services[$type] = new OrderService($repository, $this->logger);
                break;

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        return $this->services[$type];
    }

    public function getSupportedTypes(): array
    {
        return ['order'];
    }

    private function createRepository(string $type): object
    {
        if (isset($this->repositories[$type])) {
            return $this->repositories[$type];
        }

        $repositoryClass = match ($type) {
            'order' => ApiOrderRepository::class,
            default => throw new \InvalidArgumentException("Repository type '{$type}' is not yet implemented")
        };

        $this->repositories[$type] = new $repositoryClass(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $this->repositories[$type];
    }
}
```

---

## 🔄 Exemplo 2: Pattern de Fallback com Composite Repository

Este exemplo mostra como implementar um sistema de fallback entre múltiplas fontes de dados.

### 2.1 Composite Repository

```php
<?php

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;

class CompositeUserRepository implements UserRepositoryInterface
{
    private array $repositories = [];
    private array $priorities = [];

    public function __construct(
        private Logger $logger
    ) {}

    public function addRepository(UserRepositoryInterface $repository, int $priority = 0): void
    {
        $this->repositories[] = $repository;
        $this->priorities[] = $priority;

        // Sort by priority (higher priority first)
        array_multisort($this->priorities, SORT_DESC, $this->repositories);
    }

    public function findById(string $id): ?array
    {
        foreach ($this->repositories as $index => $repository) {
            try {
                $this->logger->debug('Trying repository for findById', [
                    'repository' => get_class($repository),
                    'user_id' => $id,
                    'priority' => $this->priorities[$index]
                ]);

                $result = $repository->findById($id);

                if ($result !== null) {
                    $this->logger->debug('Found user in repository', [
                        'repository' => get_class($repository),
                        'user_id' => $id
                    ]);

                    // Populate higher priority caches
                    $this->populateHigherPriorityCaches($index, $id, $result);

                    return $result;
                }

            } catch (\Exception $e) {
                $this->logger->warning('Repository failed for findById', [
                    'repository' => get_class($repository),
                    'user_id' => $id,
                    'error' => $e->getMessage()
                ]);
                // Continue to next repository
                continue;
            }
        }

        $this->logger->info('User not found in any repository', ['user_id' => $id]);
        return null;
    }

    public function create(array $data): array
    {
        $lastException = null;

        foreach ($this->repositories as $repository) {
            try {
                $this->logger->debug('Trying repository for create', [
                    'repository' => get_class($repository)
                ]);

                $result = $repository->create($data);

                // Propagate to other repositories
                $this->propagateToOtherRepositories($repository, 'create', [$data]);

                return $result;

            } catch (\Exception $e) {
                $this->logger->warning('Repository failed for create', [
                    'repository' => get_class($repository),
                    'error' => $e->getMessage()
                ]);
                $lastException = $e;
                continue;
            }
        }

        throw $lastException ?? new \Exception('All repositories failed for create operation');
    }

    private function populateHigherPriorityCaches(int $currentIndex, string $id, array $data): void
    {
        // Populate caches with higher priority than current
        for ($i = 0; $i < $currentIndex; $i++) {
            $repository = $this->repositories[$i];

            // Only populate if it's a cache repository
            if ($this->isCacheRepository($repository)) {
                try {
                    $repository->create($data);
                    $this->logger->debug('Populated higher priority cache', [
                        'cache_repository' => get_class($repository),
                        'user_id' => $id
                    ]);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to populate cache', [
                        'cache_repository' => get_class($repository),
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    private function propagateToOtherRepositories(UserRepositoryInterface $excludeRepo, string $method, array $args): void
    {
        foreach ($this->repositories as $repository) {
            if ($repository === $excludeRepo) {
                continue;
            }

            try {
                // Async propagation would be better in production
                call_user_func_array([$repository, $method], $args);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to propagate to repository', [
                    'repository' => get_class($repository),
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function isCacheRepository(UserRepositoryInterface $repository): bool
    {
        return strpos(get_class($repository), 'Cache') !== false;
    }

    // Implement other methods following the same pattern...
}
```

### 2.2 Usage Example

```php
<?php

// Example of setting up composite repository with fallback chain
$compositeRepo = new CompositeUserRepository($logger);

// Add repositories in order of preference
$compositeRepo->addRepository(new CacheUserRepository(...), 100);  // Highest priority
$compositeRepo->addRepository(new ApiUserRepository(...), 50);     // Medium priority
$compositeRepo->addRepository(new DatabaseUserRepository(...), 10); // Fallback

// Use through service
$userService = new UserService($compositeRepo, $logger);

// Will try cache first, then API, then database
$user = $userService->getUser('user_123');
```

---

## 🧪 Exemplo 3: Testing Strategies

### 3.1 Repository Testing com HTTP Mocking

```php
<?php

class ApiOrderRepositoryTest extends TestCase
{
    private ApiOrderRepository $repository;
    private MockInterface $httpClient;
    private MockInterface $cache;
    private MockInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->httpClient = Mockery::mock(Client::class);
        $this->cache = Mockery::mock(CacheManagerInterface::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

        // Setup default behaviors
        $this->cache->shouldReceive('has')->andReturn(false)->byDefault();
        $this->cache->shouldReceive('get')->andReturn(null)->byDefault();
        $this->cache->shouldReceive('set')->andReturn(true)->byDefault();
        $this->cache->shouldReceive('delete')->andReturn(true)->byDefault();

        $this->repository = new ApiOrderRepository(
            Mockery::mock(Configuration::class),
            Mockery::mock(Logger::class),
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    public function testCreateOrderSuccess(): void
    {
        // Arrange
        $orderData = [
            'customer_id' => 'cust_123',
            'items' => [
                ['product_id' => 'prod_1', 'quantity' => 2, 'price' => 29.99]
            ],
            'total' => 59.98
        ];

        $expectedResponse = array_merge($orderData, [
            'id' => 'order_123',
            'order_number' => 'ORD-2024-123456',
            'status' => 'pending',
            'created_at' => '2024-01-01T00:00:00Z'
        ]);

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getData')->andReturn($expectedResponse);
        $mockResponse->shouldReceive('getStatusCode')->andReturn(201);

        $this->httpClient->shouldReceive('post')
            ->with('/orders', $orderData)
            ->once()
            ->andReturn($mockResponse);

        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('Clubify.Checkout.Order.Created', Mockery::type('array'))
            ->once();

        // Act
        $result = $this->repository->create($orderData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
        $this->assertEquals('order_123', $result['id']);
        $this->assertEquals('pending', $result['status']);
    }

    public function testProcessPaymentWithEventDispatching(): void
    {
        // Arrange
        $orderId = 'order_123';
        $paymentData = [
            'method' => 'credit_card',
            'amount' => 59.98,
            'card_token' => 'tok_123456'
        ];

        $paymentResult = [
            'payment_id' => 'pay_789',
            'status' => 'approved',
            'transaction_id' => 'txn_456'
        ];

        $mockResponse = Mockery::mock();
        $mockResponse->shouldReceive('isSuccessful')->andReturn(true);
        $mockResponse->shouldReceive('getData')->andReturn($paymentResult);

        $this->httpClient->shouldReceive('post')
            ->with("/orders/{$orderId}/payment", $paymentData)
            ->once()
            ->andReturn($mockResponse);

        // Expect cache invalidation
        $this->cache->shouldReceive('delete')
            ->with(Mockery::pattern('/order:' . $orderId . '/'))
            ->atLeast()->once();

        // Expect event dispatching
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('Clubify.Checkout.Order.PaymentProcessed', [
                'order_id' => $orderId,
                'payment_id' => 'pay_789',
                'amount' => 59.98,
                'timestamp' => Mockery::type('int')
            ])
            ->once();

        // Act
        $result = $this->repository->processPayment($orderId, $paymentData);

        // Assert
        $this->assertEquals($paymentResult, $result);
        $this->assertEquals('approved', $result['status']);
    }
}
```

### 3.2 Service Testing com Business Logic

```php
<?php

class OrderServiceTest extends TestCase
{
    private OrderService $orderService;
    private MockInterface $repository;
    private MockInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(OrderRepositoryInterface::class);
        $this->logger = Mockery::mock(Logger::class);

        $this->orderService = new OrderService($this->repository, $this->logger);

        // Default logger behavior
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();
    }

    public function testCreateOrderWithBusinessRules(): void
    {
        // Arrange
        $orderData = [
            'customer_id' => 'cust_123',
            'items' => [
                [
                    'product_id' => 'prod_1',
                    'quantity' => 2,
                    'price' => 29.99,
                    'name' => 'Product 1'
                ]
            ]
        ];

        $expectedOrderForCreation = [
            'customer_id' => 'cust_123',
            'items' => $orderData['items'],
            'order_number' => Mockery::pattern('/^ORD-\d{4}-\d{6}$/'),
            'subtotal' => 59.98,
            'tax' => 5.998, // 10% tax
            'shipping' => 0, // Free shipping over $100 = false, so $9.99, but subtotal is 59.98
            'total' => Mockery::type('float'),
            'status' => 'pending'
        ];

        $createdOrder = array_merge($expectedOrderForCreation, [
            'id' => 'order_123',
            'created_at' => '2024-01-01T00:00:00Z'
        ]);

        $this->repository->shouldReceive('create')
            ->with(Mockery::on(function ($data) {
                // Verify business rules were applied
                $this->assertArrayHasKey('order_number', $data);
                $this->assertArrayHasKey('subtotal', $data);
                $this->assertArrayHasKey('tax', $data);
                $this->assertArrayHasKey('shipping', $data);
                $this->assertArrayHasKey('total', $data);

                // Verify calculations
                $this->assertEquals(59.98, $data['subtotal']);
                $this->assertEquals(5.998, round($data['tax'], 3));
                $this->assertEquals(9.99, $data['shipping']); // Under $100, so shipping applies

                return true;
            }))
            ->once()
            ->andReturn($createdOrder);

        // Act
        $result = $this->orderService->createOrder($orderData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('order_123', $result['order_id']);
        $this->assertArrayHasKey('order', $result);
    }

    public function testUpdateOrderStatusWithValidation(): void
    {
        // Arrange
        $orderId = 'order_123';
        $currentOrder = [
            'id' => $orderId,
            'status' => 'pending',
            'customer_id' => 'cust_123'
        ];

        $this->repository->shouldReceive('findById')
            ->with($orderId)
            ->once()
            ->andReturn($currentOrder);

        $this->repository->shouldReceive('updateStatus')
            ->with($orderId, 'confirmed')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->orderService->updateOrderStatus($orderId, 'confirmed');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('pending', $result['old_status']);
        $this->assertEquals('confirmed', $result['new_status']);
    }

    public function testUpdateOrderStatusInvalidTransition(): void
    {
        // Arrange
        $orderId = 'order_123';
        $currentOrder = [
            'id' => $orderId,
            'status' => 'completed', // Cannot transition from completed
            'customer_id' => 'cust_123'
        ];

        $this->repository->shouldReceive('findById')
            ->with($orderId)
            ->once()
            ->andReturn($currentOrder);

        // Act & Assert
        $this->expectException(OrderValidationException::class);
        $this->expectExceptionMessage("Cannot transition from 'completed' to 'pending'");

        $this->orderService->updateOrderStatus($orderId, 'pending');
    }

    public function testCreateOrderBelowMinimumValue(): void
    {
        // Arrange
        $orderData = [
            'customer_id' => 'cust_123',
            'items' => [
                [
                    'product_id' => 'prod_1',
                    'quantity' => 1,
                    'price' => 5.00  // Below $10 minimum
                ]
            ]
        ];

        // Act & Assert
        $this->expectException(OrderValidationException::class);
        $this->expectExceptionMessage('Minimum order value is $10.00');

        $this->orderService->createOrder($orderData);
    }
}
```

---

## 🔌 Exemplo 4: Plugin System para Extensibilidade

### 4.1 Plugin Interface

```php
<?php

namespace Clubify\Checkout\Core\Plugins;

interface RepositoryPluginInterface
{
    public function beforeCreate(array $data): array;
    public function afterCreate(array $result): array;
    public function beforeUpdate(string $id, array $data): array;
    public function afterUpdate(array $result): array;
    public function beforeDelete(string $id): void;
    public function afterDelete(string $id): void;
}
```

### 4.2 Audit Plugin Implementation

```php
<?php

namespace Clubify\Checkout\Plugins;

use Clubify\Checkout\Core\Plugins\RepositoryPluginInterface;
use Clubify\Checkout\Core\Logger\Logger;

class AuditPlugin implements RepositoryPluginInterface
{
    public function __construct(
        private Logger $logger,
        private string $userId = 'system'
    ) {}

    public function beforeCreate(array $data): array
    {
        $this->logger->info('Audit: Before create operation', [
            'user_id' => $this->userId,
            'operation' => 'create',
            'data_keys' => array_keys($data),
            'timestamp' => time()
        ]);

        // Add audit fields
        $data['created_by'] = $this->userId;
        $data['created_at'] = date('c');

        return $data;
    }

    public function afterCreate(array $result): array
    {
        $this->logger->info('Audit: After create operation', [
            'user_id' => $this->userId,
            'operation' => 'create',
            'entity_id' => $result['id'] ?? 'unknown',
            'timestamp' => time()
        ]);

        return $result;
    }

    public function beforeUpdate(string $id, array $data): array
    {
        $this->logger->info('Audit: Before update operation', [
            'user_id' => $this->userId,
            'operation' => 'update',
            'entity_id' => $id,
            'updated_fields' => array_keys($data),
            'timestamp' => time()
        ]);

        // Add audit fields
        $data['updated_by'] = $this->userId;
        $data['updated_at'] = date('c');

        return $data;
    }

    public function afterUpdate(array $result): array
    {
        $this->logger->info('Audit: After update operation', [
            'user_id' => $this->userId,
            'operation' => 'update',
            'entity_id' => $result['id'] ?? 'unknown',
            'timestamp' => time()
        ]);

        return $result;
    }

    public function beforeDelete(string $id): void
    {
        $this->logger->info('Audit: Before delete operation', [
            'user_id' => $this->userId,
            'operation' => 'delete',
            'entity_id' => $id,
            'timestamp' => time()
        ]);
    }

    public function afterDelete(string $id): void
    {
        $this->logger->info('Audit: After delete operation', [
            'user_id' => $this->userId,
            'operation' => 'delete',
            'entity_id' => $id,
            'timestamp' => time()
        ]);
    }
}
```

### 4.3 Enhanced Base Repository with Plugins

```php
<?php

abstract class BaseRepository extends BaseService implements RepositoryInterface
{
    private array $plugins = [];

    public function addPlugin(RepositoryPluginInterface $plugin): void
    {
        $this->plugins[] = $plugin;
    }

    public function create(array $data): array
    {
        // Run before plugins
        foreach ($this->plugins as $plugin) {
            $data = $plugin->beforeCreate($data);
        }

        $result = $this->executeWithMetrics("create_{$this->getResourceName()}", function () use ($data) {
            $response = $this->httpClient->post($this->getEndpoint(), $data);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to create {$this->getResourceName()}: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            return $response->getData();
        });

        // Run after plugins
        foreach ($this->plugins as $plugin) {
            $result = $plugin->afterCreate($result);
        }

        return $result;
    }

    // Similar implementation for update and delete methods...
}
```

### 4.4 Usage Example

```php
<?php

// Setup repository with plugins
$userRepository = new ApiUserRepository(...);

// Add plugins
$userRepository->addPlugin(new AuditPlugin($logger, $currentUserId));
$userRepository->addPlugin(new ValidationPlugin());
$userRepository->addPlugin(new CacheWarmupPlugin($cache));

// Use repository normally - plugins run automatically
$user = $userRepository->create([
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Plugins have:
// 1. Added audit fields (created_by, created_at)
// 2. Performed additional validation
// 3. Warmed up related caches
// 4. Logged audit trail
```

---

Esses exemplos demonstram a **flexibilidade** e **poder** da arquitetura híbrida implementada no SDK Clubify Checkout, mostrando como ela suporta cenários complexos do mundo real com **testabilidade**, **extensibilidade** e **manutenibilidade**!