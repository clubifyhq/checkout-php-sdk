<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Integration;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Cart\CartModule;
use Clubify\Checkout\Modules\Offer\OfferModule;
use Clubify\Checkout\Modules\Cart\Services\CartService;
use Clubify\Checkout\Modules\Cart\Services\NavigationService;
use Clubify\Checkout\Modules\Offer\Services\OfferService;
use Clubify\Checkout\Modules\Offer\Services\UpsellService;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Mockery;
use Mockery\MockInterface;

/**
 * Cart-Offer Integration Tests
 *
 * Tests the integration between Cart and Offer modules, focusing on:
 *
 * **Flow Navigation Integration:**
 * - Starting cart from offer navigation
 * - Continuing flow navigation with cart updates
 * - Completing navigation flow with order conversion
 * - Multi-step funnel progression
 *
 * **Cross-Module Data Sharing:**
 * - Cart data synchronization with offer context
 * - Offer configuration affecting cart behavior
 * - Shared analytics and tracking data
 * - Event propagation between modules
 *
 * **Upsell and Cart Integration:**
 * - Adding upsells to existing carts
 * - Cart modifications from upsell acceptance/rejection
 * - Upsell presentation in cart context
 * - Revenue optimization workflows
 *
 * **Performance and Caching:**
 * - Cross-module cache coordination
 * - Lazy loading validation across modules
 * - Memory optimization for integrated workflows
 * - API call efficiency in cross-module operations
 *
 * **Factory Pattern Integration:**
 * - Cross-module service factory coordination
 * - Dependency injection across modules
 * - Module lifecycle management
 * - Configuration sharing and isolation
 *
 * @covers \Clubify\Checkout\Modules\Cart\CartModule
 * @covers \Clubify\Checkout\Modules\Offer\OfferModule
 * @covers \Clubify\Checkout\Modules\Cart\Services\NavigationService
 * @covers \Clubify\Checkout\Modules\Offer\Services\UpsellService
 * @group integration
 * @group cart-offer
 * @group cross-module
 * @group flow-navigation
 */
class CartOfferIntegrationTest extends TestCase
{
    private CartModule $cartModule;
    private OfferModule $offerModule;
    private ClubifyCheckoutSDK $sdk;
    private MockInterface $httpClientMock;
    private MockInterface $cacheMock;
    private MockInterface $eventDispatcherMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for shared dependencies
        $this->httpClientMock = Mockery::mock(Client::class);
        $this->cacheMock = Mockery::mock(CacheManagerInterface::class);
        $this->eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);

        // Setup default behaviors
        $this->setupDefaultMockBehaviors();

        // Create SDK instance with mocked dependencies
        $this->sdk = $this->createSDKMock();

        // Initialize modules
        $this->cartModule = new CartModule();
        $this->cartModule->initialize($this->config, $this->logger);
        $this->cartModule->setDependencies($this->httpClientMock, $this->cacheMock, $this->eventDispatcherMock);

        $this->offerModule = new OfferModule();
        $this->offerModule->initialize($this->config, $this->logger);
        $this->offerModule->setDependencies($this->httpClientMock, $this->cacheMock, $this->eventDispatcherMock);
    }

    // ============================================
    // FLOW NAVIGATION INTEGRATION TESTS
    // ============================================

    public function testStartCartFromOfferNavigation(): void
    {
        // Arrange
        $offerId = 'offer_flow_123';
        $sessionId = 'session_flow_' . uniqid();
        $offerData = $this->generateOfferData([
            'id' => $offerId,
            'type' => 'funnel',
            'name' => 'Multi-Step Funnel Offer',
            'products' => [
                ['id' => 'prod_main', 'name' => 'Main Product', 'price' => 9999]
            ]
        ]);
        $navigationData = [
            'id' => 'nav_123',
            'offer_id' => $offerId,
            'status' => 'started',
            'current_step' => 'product_selection',
            'context' => ['utm_source' => 'facebook']
        ];
        $cartData = [
            'id' => 'cart_flow_123',
            'session_id' => $sessionId,
            'type' => 'flow',
            'offer_id' => $offerId,
            'navigation_id' => 'nav_123',
            'flow_data' => $navigationData
        ];

        // Mock offer retrieval
        $this->httpClientMock
            ->shouldReceive('get')
            ->with("/offers/{$offerId}")
            ->andReturn($this->createHttpResponseMock(200, $offerData));

        // Mock navigation start
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/offers/{$offerId}/navigation/start", Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, $navigationData));

        // Mock cart creation with flow context
        $this->httpClientMock
            ->shouldReceive('post')
            ->with('/carts', Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, $cartData));

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('flow.navigation.started', Mockery::type('array'))
            ->once();

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('cart.flow.created', Mockery::type('array'))
            ->once();

        // Act
        $navigation = $this->offerModule->navigation()->startFlow($offerId, ['utm_source' => 'facebook']);
        $cart = $this->cartModule->create($sessionId, [
            'type' => 'flow',
            'offer_id' => $offerId,
            'navigation_id' => $navigation['id'],
            'flow_data' => $navigation
        ]);

        // Assert
        $this->assertEquals($offerId, $navigation['offer_id']);
        $this->assertEquals('started', $navigation['status']);
        $this->assertEquals('flow', $cart['type']);
        $this->assertEquals($offerId, $cart['offer_id']);
        $this->assertEquals($navigation['id'], $cart['navigation_id']);
    }

    public function testContinueFlowNavigationWithCartUpdates(): void
    {
        // Arrange
        $navigationId = 'nav_123';
        $cartId = 'cart_flow_123';
        $productId = 'prod_main';

        $stepData = [
            'step' => 'product_selection',
            'data' => [
                'selected_product' => $productId,
                'quantity' => 2,
                'variant' => 'premium'
            ],
            'completed' => true
        ];

        $updatedNavigation = [
            'id' => $navigationId,
            'status' => 'in_progress',
            'current_step' => 'cart_review',
            'steps_completed' => ['product_selection'],
            'step_data' => $stepData
        ];

        $itemData = [
            'product_id' => $productId,
            'name' => 'Main Product - Premium',
            'price' => 9999,
            'quantity' => 2,
            'variant' => 'premium'
        ];

        $updatedCart = [
            'id' => $cartId,
            'navigation_id' => $navigationId,
            'items' => [$itemData],
            'totals' => ['subtotal' => 19998, 'total' => 19998]
        ];

        // Mock navigation continuation
        $this->httpClientMock
            ->shouldReceive('put')
            ->with("/navigation/{$navigationId}/continue", $stepData)
            ->andReturn($this->createHttpResponseMock(200, $updatedNavigation));

        // Mock cart item addition
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/carts/{$cartId}/items", $itemData)
            ->andReturn($this->createHttpResponseMock(200, $updatedCart));

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('flow.navigation.continued', Mockery::type('array'))
            ->once();

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('cart.flow.updated', Mockery::type('array'))
            ->once();

        // Act
        $navigation = $this->cartModule->navigation()->continueFlow($navigationId, $stepData);
        $cart = $this->cartModule->addItem($cartId, $itemData);

        // Assert
        $this->assertEquals('in_progress', $navigation['status']);
        $this->assertEquals('cart_review', $navigation['current_step']);
        $this->assertContains('product_selection', $navigation['steps_completed']);
        $this->assertCount(1, $cart['items']);
        $this->assertEquals($productId, $cart['items'][0]['product_id']);
        $this->assertEquals(2, $cart['items'][0]['quantity']);
    }

    public function testCompleteFlowNavigationWithOrderConversion(): void
    {
        // Arrange
        $navigationId = 'nav_123';
        $cartId = 'cart_flow_123';
        $orderId = 'order_456';

        $completionData = [
            'payment_method' => 'credit_card',
            'billing_address' => ['country' => 'BR'],
            'completion_timestamp' => date('Y-m-d H:i:s')
        ];

        $completedNavigation = [
            'id' => $navigationId,
            'status' => 'completed',
            'current_step' => 'confirmation',
            'order_id' => $orderId,
            'completion_data' => $completionData,
            'completed_at' => date('Y-m-d H:i:s')
        ];

        $convertedCart = [
            'id' => $cartId,
            'status' => 'converted',
            'order_id' => $orderId,
            'navigation_id' => $navigationId,
            'converted_at' => date('Y-m-d H:i:s')
        ];

        // Mock navigation completion
        $this->httpClientMock
            ->shouldReceive('put')
            ->with("/navigation/{$navigationId}/complete", $completionData)
            ->andReturn($this->createHttpResponseMock(200, $completedNavigation));

        // Mock cart conversion
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/carts/{$cartId}/convert")
            ->andReturn($this->createHttpResponseMock(200, $convertedCart));

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('flow.navigation.completed', Mockery::type('array'))
            ->once();

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('cart.flow.converted', Mockery::type('array'))
            ->once();

        // Act
        $navigation = $this->cartModule->navigation()->completeFlow($navigationId, $completionData);
        $cart = $this->cartModule->cart()->convertToOrder($cartId);

        // Assert
        $this->assertEquals('completed', $navigation['status']);
        $this->assertEquals($orderId, $navigation['order_id']);
        $this->assertEquals('converted', $cart['status']);
        $this->assertEquals($orderId, $cart['order_id']);
        $this->assertEquals($navigationId, $cart['navigation_id']);
    }

    // ============================================
    // UPSELL INTEGRATION TESTS
    // ============================================

    public function testAddUpsellToExistingCart(): void
    {
        // Arrange
        $cartId = 'cart_123';
        $offerId = 'offer_456';
        $upsellProductId = 'prod_upsell_789';

        $existingCart = [
            'id' => $cartId,
            'offer_id' => $offerId,
            'items' => [
                ['id' => 'item_1', 'product_id' => 'prod_main', 'price' => 9999, 'quantity' => 1]
            ],
            'totals' => ['subtotal' => 9999, 'total' => 9999]
        ];

        $upsellOffer = [
            'product_id' => $upsellProductId,
            'type' => 'one_time_offer',
            'discount_percentage' => 30,
            'original_price' => 4999,
            'discounted_price' => 3499,
            'title' => 'Special Addon - 30% Off!',
            'description' => 'Complete your purchase with this exclusive addon'
        ];

        $upsellItem = [
            'product_id' => $upsellProductId,
            'name' => 'Special Addon',
            'price' => 3499,
            'quantity' => 1,
            'discount_percentage' => 30,
            'original_price' => 4999
        ];

        $updatedCart = [
            'id' => $cartId,
            'offer_id' => $offerId,
            'items' => [
                ['id' => 'item_1', 'product_id' => 'prod_main', 'price' => 9999, 'quantity' => 1],
                ['id' => 'item_2', 'product_id' => $upsellProductId, 'price' => 3499, 'quantity' => 1]
            ],
            'totals' => ['subtotal' => 13498, 'total' => 13498],
            'upsells_accepted' => [$upsellProductId]
        ];

        // Mock cart retrieval
        $this->httpClientMock
            ->shouldReceive('get')
            ->with("/carts/{$cartId}")
            ->andReturn($this->createHttpResponseMock(200, $existingCart));

        // Mock upsell offer retrieval
        $this->httpClientMock
            ->shouldReceive('get')
            ->with("/offers/{$offerId}/upsells/{$upsellProductId}")
            ->andReturn($this->createHttpResponseMock(200, $upsellOffer));

        // Mock cart item addition with upsell
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/carts/{$cartId}/items", $upsellItem)
            ->andReturn($this->createHttpResponseMock(200, $updatedCart));

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('upsell.accepted', Mockery::type('array'))
            ->once();

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('cart.upsell.added', Mockery::type('array'))
            ->once();

        // Act
        $cart = $this->cartModule->cart()->find($cartId);
        $upsell = $this->offerModule->upsells()->getOfferUpsell($offerId, $upsellProductId);
        $updatedCart = $this->cartModule->addItem($cartId, $upsellItem);

        // Assert
        $this->assertEquals($cartId, $cart['id']);
        $this->assertEquals($offerId, $cart['offer_id']);
        $this->assertEquals(30, $upsell['discount_percentage']);
        $this->assertCount(2, $updatedCart['items']);
        $this->assertEquals($upsellProductId, $updatedCart['items'][1]['product_id']);
        $this->assertEquals(3499, $updatedCart['items'][1]['price']);
        $this->assertContains($upsellProductId, $updatedCart['upsells_accepted']);
    }

    public function testUpsellSequenceInFunnelFlow(): void
    {
        // Arrange
        $offerId = 'offer_funnel_123';
        $cartId = 'cart_funnel_456';
        $navigationId = 'nav_funnel_789';

        $funnelSteps = [
            'main_product' => ['product_id' => 'prod_main', 'price' => 9999],
            'upsell_1' => ['product_id' => 'prod_upsell_1', 'price' => 4999, 'discount' => 20],
            'upsell_2' => ['product_id' => 'prod_upsell_2', 'price' => 2999, 'discount' => 30],
            'downsell' => ['product_id' => 'prod_downsell', 'price' => 1999, 'discount' => 50]
        ];

        // Mock each step of the funnel flow
        foreach ($funnelSteps as $step => $product) {
            $stepData = [
                'step' => $step,
                'data' => ['action' => 'accepted', 'product' => $product],
                'completed' => true
            ];

            $this->httpClientMock
                ->shouldReceive('put')
                ->with("/navigation/{$navigationId}/continue", $stepData)
                ->andReturn($this->createHttpResponseMock(200, [
                    'id' => $navigationId,
                    'current_step' => $step,
                    'status' => $step === 'downsell' ? 'completed' : 'in_progress'
                ]));

            if ($step !== 'downsell') { // All but the last step add to cart
                $this->httpClientMock
                    ->shouldReceive('post')
                    ->with("/carts/{$cartId}/items", Mockery::type('array'))
                    ->andReturn($this->createHttpResponseMock(200, [
                        'id' => $cartId,
                        'items' => [/* items array */]
                    ]));
            }
        }

        // Events for each upsell
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('upsell.sequence.step', Mockery::type('array'))
            ->times(4);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('funnel.completed', Mockery::type('array'))
            ->once();

        // Act & Assert - simulate the entire funnel flow
        foreach ($funnelSteps as $step => $product) {
            $stepData = [
                'step' => $step,
                'data' => ['action' => 'accepted', 'product' => $product],
                'completed' => true
            ];

            $navigation = $this->cartModule->navigation()->continueFlow($navigationId, $stepData);

            if ($step !== 'downsell') {
                $cart = $this->cartModule->addItem($cartId, [
                    'product_id' => $product['product_id'],
                    'price' => $product['price'],
                    'quantity' => 1
                ]);
                $this->assertEquals($cartId, $cart['id']);
            }

            $this->assertEquals($step, $navigation['current_step']);
        }

        $this->assertEquals('completed', $navigation['status']);
    }

    // ============================================
    // CROSS-MODULE DATA SHARING TESTS
    // ============================================

    public function testSharedAnalyticsAndTrackingData(): void
    {
        // Arrange
        $sessionId = 'session_analytics_' . uniqid();
        $offerId = 'offer_analytics_123';
        $cartId = 'cart_analytics_456';

        $sharedTrackingData = [
            'utm_source' => 'google',
            'utm_campaign' => 'summer_sale_2023',
            'utm_medium' => 'cpc',
            'referrer' => 'https://google.com/search',
            'user_agent' => 'Mozilla/5.0...',
            'ip_address' => '192.168.1.1',
            'session_start' => date('Y-m-d H:i:s')
        ];

        $cartWithTracking = [
            'id' => $cartId,
            'session_id' => $sessionId,
            'offer_id' => $offerId,
            'analytics_data' => [
                'events' => [
                    ['event' => 'cart_started', 'timestamp' => time(), 'data' => $sharedTrackingData]
                ]
            ],
            'utm_data' => $sharedTrackingData
        ];

        $offerWithTracking = [
            'id' => $offerId,
            'analytics' => [
                'tracking_enabled' => true,
                'events' => [
                    ['event' => 'offer_viewed', 'timestamp' => time(), 'data' => $sharedTrackingData]
                ]
            ]
        ];

        // Mock cart creation with tracking data
        $this->httpClientMock
            ->shouldReceive('post')
            ->with('/carts', Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, $cartWithTracking));

        // Mock offer analytics update
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/offers/{$offerId}/analytics/event", Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(200, $offerWithTracking));

        // Cross-module analytics events
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('analytics.cart.started', Mockery::type('array'))
            ->once();

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->with('analytics.offer.viewed', Mockery::type('array'))
            ->once();

        // Act
        $cart = $this->cartModule->create($sessionId, [
            'offer_id' => $offerId,
            'analytics_data' => ['events' => [
                ['event' => 'cart_started', 'timestamp' => time(), 'data' => $sharedTrackingData]
            ]],
            'utm_data' => $sharedTrackingData
        ]);

        $offer = $this->offerModule->offers()->trackEvent($offerId, 'offer_viewed', $sharedTrackingData);

        // Assert
        $this->assertEquals($offerId, $cart['offer_id']);
        $this->assertEquals($sharedTrackingData, $cart['utm_data']);
        $this->assertNotEmpty($cart['analytics_data']['events']);
        $this->assertEquals('cart_started', $cart['analytics_data']['events'][0]['event']);

        $this->assertTrue($offer['analytics']['tracking_enabled']);
        $this->assertNotEmpty($offer['analytics']['events']);
        $this->assertEquals('offer_viewed', $offer['analytics']['events'][0]['event']);
    }

    public function testEventPropagationBetweenModules(): void
    {
        // Arrange
        $cartId = 'cart_events_123';
        $offerId = 'offer_events_456';

        // Setup event listeners for cross-module communication
        $cartEvents = [];
        $offerEvents = [];

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->withArgs(function ($event, $data) use (&$cartEvents, &$offerEvents) {
                if (strpos($event, 'cart.') === 0) {
                    $cartEvents[] = ['event' => $event, 'data' => $data];
                } elseif (strpos($event, 'offer.') === 0) {
                    $offerEvents[] = ['event' => $event, 'data' => $data];
                }
                return true;
            });

        // Mock cart and offer operations
        $this->httpClientMock
            ->shouldReceive('post')
            ->andReturn($this->createHttpResponseMock(200, ['id' => $cartId]));

        $this->httpClientMock
            ->shouldReceive('put')
            ->andReturn($this->createHttpResponseMock(200, ['id' => $offerId]));

        // Act - Perform operations that should trigger cross-module events
        $this->cartModule->create('session_events_123', ['offer_id' => $offerId]);
        $this->offerModule->offers()->activate($offerId);

        // Assert
        $this->assertNotEmpty($cartEvents);
        $this->assertNotEmpty($offerEvents);

        // Verify specific events were dispatched
        $cartEventNames = array_column($cartEvents, 'event');
        $offerEventNames = array_column($offerEvents, 'event');

        $this->assertContains('cart.created', $cartEventNames);
        $this->assertContains('offer.status_changed', $offerEventNames);
    }

    // ============================================
    // PERFORMANCE AND CACHING TESTS
    // ============================================

    public function testCrosModuleCacheCoordination(): void
    {
        // Arrange
        $offerId = 'offer_cache_123';
        $cartId = 'cart_cache_456';
        $cacheKey1 = "cart:cart:{$cartId}";
        $cacheKey2 = "offer:offer:{$offerId}";

        $offerData = $this->generateOfferData(['id' => $offerId]);
        $cartData = $this->generateCartData(['id' => $cartId, 'offer_id' => $offerId]);

        // Mock cache operations - both modules should coordinate caching
        $this->cacheMock
            ->shouldReceive('has')
            ->with($cacheKey1)
            ->andReturn(false, true); // Miss first, hit second

        $this->cacheMock
            ->shouldReceive('has')
            ->with($cacheKey2)
            ->andReturn(false, true);

        $this->cacheMock
            ->shouldReceive('get')
            ->with($cacheKey1)
            ->andReturn(null, $cartData);

        $this->cacheMock
            ->shouldReceive('get')
            ->with($cacheKey2)
            ->andReturn(null, $offerData);

        $this->cacheMock
            ->shouldReceive('set')
            ->with($cacheKey1, $cartData, Mockery::type('int'))
            ->andReturn(true);

        $this->cacheMock
            ->shouldReceive('set')
            ->with($cacheKey2, $offerData, Mockery::type('int'))
            ->andReturn(true);

        // Mock HTTP calls that should only happen once due to caching
        $this->httpClientMock
            ->shouldReceive('get')
            ->with("/carts/{$cartId}")
            ->once()
            ->andReturn($this->createHttpResponseMock(200, $cartData));

        $this->httpClientMock
            ->shouldReceive('get')
            ->with("/offers/{$offerId}")
            ->once()
            ->andReturn($this->createHttpResponseMock(200, $offerData));

        // Act - First calls should hit API and cache
        $cart1 = $this->cartModule->cart()->find($cartId);
        $offer1 = $this->offerModule->offers()->get($offerId);

        // Second calls should use cache
        $cart2 = $this->cartModule->cart()->find($cartId);
        $offer2 = $this->offerModule->offers()->get($offerId);

        // Assert
        $this->assertEquals($cartData, $cart1);
        $this->assertEquals($cartData, $cart2);
        $this->assertEquals($offerData, $offer1);
        $this->assertEquals($offerData, $offer2);
    }

    public function testLazyLoadingValidationAcrossModules(): void
    {
        // Arrange - Test that modules properly lazy load their dependencies
        $this->assertFalse($this->cartModule->isAvailable());
        $this->assertFalse($this->offerModule->isAvailable());

        // Act - Access services should trigger lazy loading
        $cartService = $this->cartModule->cart();
        $offerService = $this->offerModule->offers();

        // Assert - Modules should now be available
        $this->assertTrue($this->cartModule->isAvailable());
        $this->assertTrue($this->offerModule->isAvailable());

        // Verify services are properly instantiated
        $this->assertInstanceOf(CartService::class, $cartService);
        $this->assertInstanceOf(OfferService::class, $offerService);

        // Verify status includes all services
        $cartStatus = $this->cartModule->getStatus();
        $offerStatus = $this->offerModule->getStatus();

        $this->assertTrue($cartStatus['available']);
        $this->assertTrue($cartStatus['services']['cart']);
        $this->assertTrue($offerStatus['available']);
        $this->assertTrue($offerStatus['services']['offer']);
    }

    // ============================================
    // FACTORY PATTERN INTEGRATION TESTS
    // ============================================

    public function testModuleFactoryCoordination(): void
    {
        // Arrange
        $cartFactory = $this->cartModule->getServiceFactory();
        $offerFactory = $this->offerModule->getServiceFactory();

        // Act - Create services through factories
        $cartService = $cartFactory->createCartService();
        $itemService = $cartFactory->createItemService();
        $offerService = $offerFactory->createOfferService();
        $upsellService = $offerFactory->createUpsellService();

        // Assert - All services should be properly configured
        $this->assertInstanceOf(CartService::class, $cartService);
        $this->assertInstanceOf(\Clubify\Checkout\Modules\Cart\Services\ItemService::class, $itemService);
        $this->assertInstanceOf(OfferService::class, $offerService);
        $this->assertInstanceOf(UpsellService::class, $upsellService);

        // Verify services are healthy
        $this->assertTrue($cartService->isHealthy());
        $this->assertTrue($offerService->isHealthy());
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
        $this->cacheMock->shouldReceive('has')->andReturn(false)->byDefault();
        $this->cacheMock->shouldReceive('get')->andReturnNull()->byDefault();
        $this->cacheMock->shouldReceive('set')->andReturn(true)->byDefault();
        $this->cacheMock->shouldReceive('delete')->andReturn(true)->byDefault();
    }

    private function createHttpResponseMock(int $statusCode = 200, array $data = []): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        $response->shouldReceive('getData')->andReturn($data);
        return $response;
    }

    private function generateOfferData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'offer_' . uniqid(),
            'name' => 'Test Offer',
            'type' => 'single_product',
            'status' => 'active',
            'products' => [
                ['id' => 'prod_123', 'name' => 'Product 1', 'price' => 9999]
            ],
            'pricing' => ['total_price' => 9999],
            'created_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    private function generateCartData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cart_' . uniqid(),
            'session_id' => 'session_' . uniqid(),
            'status' => 'active',
            'type' => 'standard',
            'items' => [],
            'totals' => ['subtotal' => 0, 'total' => 0],
            'created_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }
}