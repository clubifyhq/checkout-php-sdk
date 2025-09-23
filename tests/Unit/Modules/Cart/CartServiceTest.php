<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Modules\Cart;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Cart\Services\CartService;
use Clubify\Checkout\Modules\Cart\Services\ItemService;
use Clubify\Checkout\Modules\Cart\Services\NavigationService;
use Clubify\Checkout\Modules\Cart\Services\PromotionService;
use Clubify\Checkout\Modules\Cart\Services\OneClickService;
use Clubify\Checkout\Modules\Cart\Contracts\CartRepositoryInterface;
use Clubify\Checkout\Modules\Cart\DTOs\CartData;
use Clubify\Checkout\Modules\Cart\DTOs\ItemData;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Mockery;
use Mockery\MockInterface;

/**
 * Comprehensive Cart Module Service Tests
 *
 * Tests all Cart module services:
 * - CartService: Main cart operations (CRUD, calculations, conversions)
 * - ItemService: Item management (add, remove, update)
 * - NavigationService: Flow navigation and step management
 * - PromotionService: Promotion and discount handling
 * - OneClickService: One-click checkout functionality
 *
 * Test Coverage:
 * - Unit tests for all public methods
 * - Integration testing between services
 * - Mock testing for repository layer
 * - Performance testing for cache and lazy loading
 * - Error handling and edge cases
 * - Factory pattern validation
 *
 * @covers \Clubify\Checkout\Modules\Cart\Services\CartService
 * @covers \Clubify\Checkout\Modules\Cart\Services\ItemService
 * @covers \Clubify\Checkout\Modules\Cart\Services\NavigationService
 * @covers \Clubify\Checkout\Modules\Cart\Services\PromotionService
 * @covers \Clubify\Checkout\Modules\Cart\Services\OneClickService
 * @group unit
 * @group cart
 * @group services
 * @group comprehensive
 */
class CartServiceTest extends TestCase
{
    private CartService $cartService;
    private ItemService $itemService;
    private NavigationService $navigationService;
    private PromotionService $promotionService;
    private OneClickService $oneClickService;
    private MockInterface $repositoryMock;
    private MockInterface $cacheMock;
    private MockInterface $eventDispatcherMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->repositoryMock = Mockery::mock(CartRepositoryInterface::class);
        $this->cacheMock = Mockery::mock(\Psr\Cache\CacheItemPoolInterface::class);
        $this->eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);

        // Setup default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Create Cart services
        $this->cartService = new CartService(
            $this->repositoryMock,
            $this->logger,
            $this->cacheMock,
            ['timeout' => 30, 'max_items' => 50]
        );

        $this->itemService = new ItemService(
            $this->repositoryMock,
            $this->logger,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->navigationService = new NavigationService(
            $this->repositoryMock,
            $this->logger,
            $this->cacheMock,
            $this->config
        );

        $this->promotionService = new PromotionService(
            $this->repositoryMock,
            $this->logger,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->oneClickService = new OneClickService(
            $this->repositoryMock,
            $this->logger,
            $this->cacheMock,
            $this->config,
            $this->eventDispatcherMock
        );
    }

    // ============================================
    // CART SERVICE TESTS
    // ============================================

    public function testCartServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(CartService::class, $this->cartService);
    }

    public function testCreateCartSuccessfully(): void
    {
        // Arrange
        $sessionId = 'session_' . uniqid();
        $cartData = [
            'customer_id' => 'cust_123',
            'currency' => 'BRL',
            'metadata' => ['source' => 'test']
        ];

        $expectedCart = array_merge($cartData, [
            'id' => 'cart_' . uniqid(),
            'session_id' => $sessionId,
            'status' => 'active',
            'type' => 'standard',
            'items' => [],
            'totals' => ['subtotal' => 0, 'total' => 0],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) use ($sessionId) {
                return $data['session_id'] === $sessionId;
            })
            ->andReturn($expectedCart);

        // Act
        $result = $this->cartService->create($sessionId, $cartData);

        // Assert
        $this->assertEquals($expectedCart, $result);
        $this->assertEquals($sessionId, $result['session_id']);
        $this->assertEquals('active', $result['status']);
    }

    public function testCreateCartValidatesSessionId(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Session ID is required');

        // Act
        $this->cartService->create('', []);
    }

    public function testFindCartById(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $cartData = $this->generateCartData(['id' => $cartId]);

        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($cartId)
            ->andReturn($cartData);

        // Act
        $result = $this->cartService->find($cartId);

        // Assert
        $this->assertEquals($cartData, $result);
        $this->assertEquals($cartId, $result['id']);
    }

    public function testFindCartBySession(): void
    {
        // Arrange
        $sessionId = 'session_123';
        $cartData = $this->generateCartData(['session_id' => $sessionId]);

        $this->repositoryMock
            ->shouldReceive('findBySession')
            ->once()
            ->with($sessionId)
            ->andReturn($cartData);

        // Act
        $result = $this->cartService->findBySession($sessionId);

        // Assert
        $this->assertEquals($cartData, $result);
        $this->assertEquals($sessionId, $result['session_id']);
    }

    public function testUpdateCart(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $updateData = ['customer_id' => 'cust_456', 'currency' => 'USD'];
        $updatedCart = $this->generateCartData(array_merge(['id' => $cartId], $updateData));

        $this->repositoryMock
            ->shouldReceive('update')
            ->once()
            ->with($cartId, $updateData)
            ->andReturn($updatedCart);

        // Act
        $result = $this->cartService->update($cartId, $updateData);

        // Assert
        $this->assertEquals($updatedCart, $result);
        $this->assertEquals('cust_456', $result['customer_id']);
        $this->assertEquals('USD', $result['currency']);
    }

    public function testDeleteCart(): void
    {
        // Arrange
        $cartId = 'cart_123';

        $this->repositoryMock
            ->shouldReceive('delete')
            ->once()
            ->with($cartId)
            ->andReturn(true);

        // Act
        $result = $this->cartService->delete($cartId);

        // Assert
        $this->assertTrue($result);
    }

    public function testCalculateTotals(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $cartData = $this->generateCartData(['id' => $cartId]);
        $totals = [
            'subtotal' => 9999,
            'discount' => 1000,
            'taxes' => 800,
            'shipping' => 500,
            'total' => 10299
        ];

        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($cartId)
            ->andReturn($cartData);

        $this->repositoryMock
            ->shouldReceive('getTotalsSummary')
            ->once()
            ->with($cartId)
            ->andReturn($totals);

        $this->repositoryMock
            ->shouldReceive('update')
            ->once()
            ->with($cartId, ['totals' => $totals])
            ->andReturn(array_merge($cartData, ['totals' => $totals]));

        // Act
        $result = $this->cartService->calculateTotals($cartId);

        // Assert
        $this->assertEquals($totals, $result['totals']);
    }

    public function testConvertToOrder(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $conversionResult = [
            'cart_id' => $cartId,
            'order_id' => 'order_456',
            'status' => 'converted',
            'converted_at' => date('Y-m-d H:i:s')
        ];

        $this->repositoryMock
            ->shouldReceive('convertToOrder')
            ->once()
            ->with($cartId)
            ->andReturn($conversionResult);

        // Act
        $result = $this->cartService->convertToOrder($cartId);

        // Assert
        $this->assertEquals($conversionResult, $result);
        $this->assertEquals('order_456', $result['order_id']);
        $this->assertEquals('converted', $result['status']);
    }

    // ============================================
    // ITEM SERVICE TESTS
    // ============================================

    public function testItemServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ItemService::class, $this->itemService);
    }

    public function testAddItemToCart(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $itemData = [
            'product_id' => 'prod_456',
            'name' => 'Test Product',
            'price' => 9999,
            'quantity' => 2
        ];

        $updatedCart = $this->generateCartData([
            'id' => $cartId,
            'items' => [array_merge($itemData, ['id' => 'item_789'])]
        ]);

        $this->repositoryMock
            ->shouldReceive('countItems')
            ->once()
            ->with($cartId)
            ->andReturn(0);

        $this->repositoryMock
            ->shouldReceive('addItem')
            ->once()
            ->with($cartId, $itemData)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.item.added', Mockery::type('array'));

        // Act
        $result = $this->itemService->addToCart($cartId, $itemData);

        // Assert
        $this->assertEquals($updatedCart, $result);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('prod_456', $result['items'][0]['product_id']);
    }

    public function testAddItemFailsWhenMaxItemsExceeded(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $itemData = ['product_id' => 'prod_456', 'quantity' => 1];

        $this->repositoryMock
            ->shouldReceive('countItems')
            ->once()
            ->with($cartId)
            ->andReturn(50); // Max items reached

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limite de 50 itens excedido');

        // Act
        $this->itemService->addToCart($cartId, $itemData);
    }

    public function testRemoveItemFromCart(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $itemId = 'item_456';
        $updatedCart = $this->generateCartData(['id' => $cartId, 'items' => []]);

        $this->repositoryMock
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.item.removed', Mockery::type('array'));

        // Act
        $result = $this->itemService->removeFromCart($cartId, $itemId);

        // Assert
        $this->assertEquals($updatedCart, $result);
        $this->assertEmpty($result['items']);
    }

    public function testUpdateItemInCart(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $itemId = 'item_456';
        $updates = ['quantity' => 3, 'price' => 8999];
        $updatedCart = $this->generateCartData(['id' => $cartId]);

        $this->repositoryMock
            ->shouldReceive('updateItem')
            ->once()
            ->with($cartId, $itemId, $updates)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.item.updated', Mockery::type('array'));

        // Act
        $result = $this->itemService->updateInCart($cartId, $itemId, $updates);

        // Assert
        $this->assertEquals($updatedCart, $result);
    }

    public function testUpdateItemWithZeroQuantityRemovesItem(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $itemId = 'item_456';
        $updates = ['quantity' => 0];
        $updatedCart = $this->generateCartData(['id' => $cartId, 'items' => []]);

        $this->repositoryMock
            ->shouldReceive('removeItem')
            ->once()
            ->with($cartId, $itemId)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.item.removed', Mockery::type('array'));

        // Act
        $result = $this->itemService->updateInCart($cartId, $itemId, $updates);

        // Assert
        $this->assertEquals($updatedCart, $result);
    }

    // ============================================
    // NAVIGATION SERVICE TESTS
    // ============================================

    public function testNavigationServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(NavigationService::class, $this->navigationService);
    }

    public function testStartFlowNavigation(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $context = ['utm_source' => 'facebook', 'referrer' => 'social'];
        $navigationData = [
            'id' => 'nav_456',
            'offer_id' => $offerId,
            'status' => 'started',
            'current_step' => 'product_selection',
            'context' => $context,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->repositoryMock
            ->shouldReceive('startFlowNavigation')
            ->once()
            ->with($offerId, $context)
            ->andReturn($navigationData);

        // Act
        $result = $this->navigationService->startFlow($offerId, $context);

        // Assert
        $this->assertEquals($navigationData, $result);
        $this->assertEquals($offerId, $result['offer_id']);
        $this->assertEquals('started', $result['status']);
        $this->assertEquals('product_selection', $result['current_step']);
    }

    public function testContinueFlowNavigation(): void
    {
        // Arrange
        $navigationId = 'nav_123';
        $stepData = [
            'step' => 'payment_info',
            'data' => ['payment_method' => 'credit_card'],
            'completed' => true
        ];
        $updatedNavigation = [
            'id' => $navigationId,
            'status' => 'in_progress',
            'current_step' => 'payment_info',
            'steps_completed' => ['product_selection', 'payment_info'],
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->repositoryMock
            ->shouldReceive('continueFlowNavigation')
            ->once()
            ->with($navigationId, $stepData)
            ->andReturn($updatedNavigation);

        // Act
        $result = $this->navigationService->continueFlow($navigationId, $stepData);

        // Assert
        $this->assertEquals($updatedNavigation, $result);
        $this->assertEquals('in_progress', $result['status']);
        $this->assertEquals('payment_info', $result['current_step']);
    }

    public function testCompleteFlowNavigation(): void
    {
        // Arrange
        $navigationId = 'nav_123';
        $completionData = [
            'order_id' => 'order_456',
            'conversion_data' => ['revenue' => 9999],
            'completed_at' => date('Y-m-d H:i:s')
        ];

        $this->repositoryMock
            ->shouldReceive('completeFlowNavigation')
            ->once()
            ->with($navigationId, $completionData)
            ->andReturn(array_merge($completionData, [
                'id' => $navigationId,
                'status' => 'completed'
            ]));

        // Act
        $result = $this->navigationService->completeFlow($navigationId, $completionData);

        // Assert
        $this->assertEquals('completed', $result['status']);
        $this->assertEquals('order_456', $result['order_id']);
    }

    // ============================================
    // PROMOTION SERVICE TESTS
    // ============================================

    public function testPromotionServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PromotionService::class, $this->promotionService);
    }

    public function testApplyPromotionSuccessfully(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $promotionCode = 'SAVE10';
        $validationResult = ['valid' => true, 'discount_amount' => 1000];
        $updatedCart = $this->generateCartData([
            'id' => $cartId,
            'promotions' => [['code' => $promotionCode, 'discount_amount' => 1000]]
        ]);

        $this->repositoryMock
            ->shouldReceive('validatePromotion')
            ->once()
            ->with($promotionCode, $cartId)
            ->andReturn($validationResult);

        $this->repositoryMock
            ->shouldReceive('applyPromotion')
            ->once()
            ->with($cartId, $promotionCode)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.promotion.applied', Mockery::type('array'));

        // Act
        $result = $this->promotionService->apply($cartId, $promotionCode);

        // Assert
        $this->assertEquals($updatedCart, $result);
        $this->assertCount(1, $result['promotions']);
        $this->assertEquals($promotionCode, $result['promotions'][0]['code']);
    }

    public function testApplyPromotionFailsWithInvalidCode(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $promotionCode = 'INVALID';
        $validationResult = ['valid' => false, 'error' => 'Promotion code not found'];

        $this->repositoryMock
            ->shouldReceive('validatePromotion')
            ->once()
            ->with($promotionCode, $cartId)
            ->andReturn($validationResult);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Promotion code not found');

        // Act
        $this->promotionService->apply($cartId, $promotionCode);
    }

    public function testRemovePromotion(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $updatedCart = $this->generateCartData(['id' => $cartId, 'promotions' => []]);

        $this->repositoryMock
            ->shouldReceive('removePromotion')
            ->once()
            ->with($cartId)
            ->andReturn($updatedCart);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.promotion.removed', Mockery::type('array'));

        // Act
        $result = $this->promotionService->remove($cartId);

        // Assert
        $this->assertEquals($updatedCart, $result);
        $this->assertEmpty($result['promotions']);
    }

    // ============================================
    // ONE-CLICK SERVICE TESTS
    // ============================================

    public function testOneClickServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(OneClickService::class, $this->oneClickService);
    }

    public function testProcessOneClickCheckout(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $paymentData = [
            'payment_method_id' => 'pm_456',
            'customer_id' => 'cust_789',
            'billing_address' => ['country' => 'BR']
        ];
        $processingResult = [
            'cart_id' => $cartId,
            'order_id' => 'order_321',
            'payment_status' => 'completed',
            'transaction_id' => 'txn_654'
        ];

        $this->repositoryMock
            ->shouldReceive('processOneClickCheckout')
            ->once()
            ->with($cartId, $paymentData)
            ->andReturn($processingResult);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('cart.one_click.processed', Mockery::type('array'));

        // Act
        $result = $this->oneClickService->process($cartId, $paymentData);

        // Assert
        $this->assertEquals($processingResult, $result);
        $this->assertEquals('order_321', $result['order_id']);
        $this->assertEquals('completed', $result['payment_status']);
    }

    public function testValidateOneClickEligibility(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $customerId = 'cust_456';
        $eligibilityResult = [
            'eligible' => true,
            'saved_payment_methods' => [
                ['id' => 'pm_123', 'type' => 'credit_card', 'last4' => '1234']
            ],
            'reasons' => []
        ];

        $this->repositoryMock
            ->shouldReceive('validateOneClickEligibility')
            ->once()
            ->with($cartId, $customerId)
            ->andReturn($eligibilityResult);

        // Act
        $result = $this->oneClickService->validateEligibility($cartId, $customerId);

        // Assert
        $this->assertTrue($result['eligible']);
        $this->assertCount(1, $result['saved_payment_methods']);
    }

    // ============================================
    // INTEGRATION AND PERFORMANCE TESTS
    // ============================================

    public function testCartServiceIntegrationWithAllSubServices(): void
    {
        // Arrange
        $sessionId = 'session_integration_' . uniqid();
        $cartData = ['customer_id' => 'cust_123'];
        $cart = $this->generateCartData(['id' => 'cart_integration', 'session_id' => $sessionId]);
        $itemData = ['product_id' => 'prod_456', 'quantity' => 1, 'price' => 9999];

        // Mock cart creation
        $this->repositoryMock
            ->shouldReceive('create')
            ->once()
            ->andReturn($cart);

        // Mock item addition
        $this->repositoryMock
            ->shouldReceive('countItems')
            ->andReturn(0);
        $this->repositoryMock
            ->shouldReceive('addItem')
            ->andReturn(array_merge($cart, ['items' => [$itemData]]));

        // Mock promotion application
        $this->repositoryMock
            ->shouldReceive('validatePromotion')
            ->andReturn(['valid' => true, 'discount_amount' => 1000]);
        $this->repositoryMock
            ->shouldReceive('applyPromotion')
            ->andReturn(array_merge($cart, ['promotions' => [['code' => 'SAVE10']]]));

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->times(2); // item added + promotion applied

        // Act
        $createdCart = $this->cartService->create($sessionId, $cartData);
        $cartWithItem = $this->itemService->addToCart($createdCart['id'], $itemData);
        $cartWithPromotion = $this->promotionService->apply($cartWithItem['id'], 'SAVE10');

        // Assert
        $this->assertEquals($sessionId, $createdCart['session_id']);
        $this->assertCount(1, $cartWithItem['items']);
        $this->assertCount(1, $cartWithPromotion['promotions']);
    }

    public function testCachePerformanceWithLazyLoading(): void
    {
        // Arrange
        $cartId = 'cart_cache_test';
        $cartData = $this->generateCartData(['id' => $cartId]);

        // First call should hit repository
        $this->repositoryMock
            ->shouldReceive('find')
            ->once()
            ->with($cartId)
            ->andReturn($cartData);

        // Cache mock for lazy loading test
        $cacheItem = Mockery::mock(\Psr\Cache\CacheItemInterface::class);
        $cacheItem->shouldReceive('isHit')->andReturn(false, true); // Miss first, hit second
        $cacheItem->shouldReceive('get')->andReturn(null, $cartData);
        $cacheItem->shouldReceive('set')->with($cartData)->andReturnSelf();
        $cacheItem->shouldReceive('expiresAfter')->andReturnSelf();

        $this->cacheMock
            ->shouldReceive('getItem')
            ->times(2)
            ->andReturn($cacheItem);
        $this->cacheMock
            ->shouldReceive('save')
            ->once()
            ->andReturn(true);

        // Act - First call loads from repository and caches
        $result1 = $this->cartService->find($cartId);

        // Second call should use cache
        $result2 = $this->cartService->find($cartId);

        // Assert
        $this->assertEquals($cartData, $result1);
        $this->assertEquals($cartData, $result2);
    }

    public function testErrorHandlingInAllServices(): void
    {
        // Test CartService error handling
        $this->repositoryMock
            ->shouldReceive('find')
            ->andThrow(new \Exception('Database connection failed'));

        $result = $this->cartService->find('cart_error');
        $this->assertNull($result);

        // Test ItemService error handling
        $this->repositoryMock
            ->shouldReceive('addItem')
            ->andThrow(new \Exception('Invalid item data'));

        $this->expectException(\Exception::class);
        $this->itemService->addToCart('cart_123', ['invalid' => 'data']);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function setupDefaultMockBehaviors(): void
    {
        // Logger mocks
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        // Event dispatcher mocks
        $this->eventDispatcherMock->shouldReceive('dispatch')->andReturnNull()->byDefault();

        // Cache mocks
        $cacheItem = Mockery::mock(\Psr\Cache\CacheItemInterface::class);
        $cacheItem->shouldReceive('isHit')->andReturn(false)->byDefault();
        $cacheItem->shouldReceive('get')->andReturnNull()->byDefault();
        $cacheItem->shouldReceive('set')->andReturnSelf()->byDefault();
        $cacheItem->shouldReceive('expiresAfter')->andReturnSelf()->byDefault();

        $this->cacheMock->shouldReceive('getItem')->andReturn($cacheItem)->byDefault();
        $this->cacheMock->shouldReceive('save')->andReturn(true)->byDefault();
        $this->cacheMock->shouldReceive('deleteItem')->andReturn(true)->byDefault();
    }

    private function generateCartData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cart_' . uniqid(),
            'session_id' => 'session_' . uniqid(),
            'customer_id' => 'cust_' . uniqid(),
            'status' => 'active',
            'type' => 'standard',
            'items' => [],
            'totals' => [
                'subtotal' => 0,
                'discount' => 0,
                'taxes' => 0,
                'shipping' => 0,
                'total' => 0
            ],
            'currency' => 'BRL',
            'promotions' => [],
            'metadata' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    private function generateItemData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'item_' . uniqid(),
            'product_id' => 'prod_' . uniqid(),
            'name' => 'Test Product',
            'price' => 9999,
            'quantity' => 1,
            'currency' => 'BRL'
        ], $overrides);
    }
}