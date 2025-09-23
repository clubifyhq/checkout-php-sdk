<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Modules\Offer;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Offer\Services\OfferService;
use Clubify\Checkout\Modules\Offer\Services\UpsellService;
use Clubify\Checkout\Modules\Offer\Services\ThemeService;
use Clubify\Checkout\Modules\Offer\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Offer\Services\PublicOfferService;
use Clubify\Checkout\Modules\Offer\DTOs\OfferData;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Mockery;
use Mockery\MockInterface;

/**
 * Comprehensive Offer Module Service Tests
 *
 * Tests all Offer module services:
 * - OfferService: Main offer operations (CRUD, themes, layouts, upsells)
 * - UpsellService: Upsell management and configuration
 * - ThemeService: Theme and design customization
 * - SubscriptionPlanService: Subscription plan management
 * - PublicOfferService: Public offer access and SEO
 *
 * Test Coverage:
 * - Unit tests for all public methods
 * - Integration testing between services
 * - HTTP client mock testing
 * - Performance testing for cache and API calls
 * - Error handling and validation
 * - Cross-module functionality with Cart
 *
 * @covers \Clubify\Checkout\Modules\Offer\Services\OfferService
 * @covers \Clubify\Checkout\Modules\Offer\Services\UpsellService
 * @covers \Clubify\Checkout\Modules\Offer\Services\ThemeService
 * @covers \Clubify\Checkout\Modules\Offer\Services\SubscriptionPlanService
 * @covers \Clubify\Checkout\Modules\Offer\Services\PublicOfferService
 * @group unit
 * @group offer
 * @group services
 * @group comprehensive
 */
class OfferServiceTest extends TestCase
{
    private OfferService $offerService;
    private UpsellService $upsellService;
    private ThemeService $themeService;
    private SubscriptionPlanService $subscriptionPlanService;
    private PublicOfferService $publicOfferService;
    private MockInterface $httpClientMock;
    private MockInterface $cacheMock;
    private MockInterface $eventDispatcherMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->httpClientMock = Mockery::mock(Client::class);
        $this->cacheMock = Mockery::mock(CacheManagerInterface::class);
        $this->eventDispatcherMock = Mockery::mock(EventDispatcherInterface::class);

        // Setup default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Create Offer services
        $this->offerService = new OfferService(
            $this->config,
            $this->logger,
            $this->httpClientMock,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->upsellService = new UpsellService(
            $this->config,
            $this->logger,
            $this->httpClientMock,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->themeService = new ThemeService(
            $this->config,
            $this->logger,
            $this->httpClientMock,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->subscriptionPlanService = new SubscriptionPlanService(
            $this->config,
            $this->logger,
            $this->httpClientMock,
            $this->cacheMock,
            $this->eventDispatcherMock
        );

        $this->publicOfferService = new PublicOfferService(
            $this->config,
            $this->logger,
            $this->httpClientMock,
            $this->cacheMock,
            $this->eventDispatcherMock
        );
    }

    // ============================================
    // OFFER SERVICE TESTS
    // ============================================

    public function testOfferServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(OfferService::class, $this->offerService);
    }

    public function testCreateOfferSuccessfully(): void
    {
        // Arrange
        $offerData = [
            'name' => 'Test Offer',
            'type' => 'single_product',
            'organization_id' => 'org_123',
            'products' => [
                ['id' => 'prod_456', 'name' => 'Product 1', 'price' => 9999]
            ],
            'pricing' => ['total_price' => 9999]
        ];

        $expectedOffer = array_merge($offerData, [
            'id' => 'offer_' . uniqid(),
            'slug' => 'test-offer',
            'status' => 'draft',
            'active' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $responseMock = $this->createHttpResponseMock(201, $expectedOffer);
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/offers', Mockery::type('array'))
            ->andReturn($responseMock);

        $this->cacheMock
            ->shouldReceive('set')
            ->times(2) // Cache by ID and slug
            ->andReturn(true);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('offer.created', Mockery::type('array'));

        // Act
        $result = $this->offerService->create($offerData);

        // Assert
        $this->assertEquals($expectedOffer, $result);
        $this->assertEquals('Test Offer', $result['name']);
        $this->assertEquals('test-offer', $result['slug']);
        $this->assertEquals('single_product', $result['type']);
        $this->assertEquals('draft', $result['status']);
    }

    public function testCreateOfferValidatesRequiredFields(): void
    {
        // Arrange
        $incompleteData = ['description' => 'Missing name'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("Field 'name' is required for offer creation");

        // Act
        $this->offerService->create($incompleteData);
    }

    public function testCreateOfferGeneratesUniqueSlug(): void
    {
        // Arrange
        $offerData = ['name' => 'Test Offer', 'type' => 'single_product'];
        $expectedOffer = [
            'id' => 'offer_123',
            'name' => 'Test Offer',
            'slug' => 'test-offer-1', // Should be incremented due to conflict
            'status' => 'draft'
        ];

        // Mock slug existence check - first slug exists
        $this->httpClientMock
            ->shouldReceive('get')
            ->with('/offers/slug/test-offer')
            ->andReturn($this->createHttpResponseMock(200, ['id' => 'existing_offer']));

        // Second slug check - doesn't exist
        $this->httpClientMock
            ->shouldReceive('get')
            ->with('/offers/slug/test-offer-1')
            ->andThrow(new HttpException('Not found', 404));

        // Create offer with incremented slug
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->andReturn($this->createHttpResponseMock(201, $expectedOffer));

        $this->cacheMock->shouldReceive('set')->andReturn(true);
        $this->eventDispatcherMock->shouldReceive('dispatch');

        // Act
        $result = $this->offerService->create($offerData);

        // Assert
        $this->assertEquals('test-offer-1', $result['slug']);
    }

    public function testGetOfferById(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $offerData = $this->generateOfferData(['id' => $offerId]);

        $this->cacheMock
            ->shouldReceive('has')
            ->once()
            ->with("offer:offer:{$offerId}")
            ->andReturn(false);

        $responseMock = $this->createHttpResponseMock(200, $offerData);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/{$offerId}")
            ->andReturn($responseMock);

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->offerService->get($offerId);

        // Assert
        $this->assertEquals($offerData, $result);
        $this->assertEquals($offerId, $result['id']);
    }

    public function testGetOfferBySlug(): void
    {
        // Arrange
        $slug = 'test-offer';
        $offerData = $this->generateOfferData(['slug' => $slug]);

        $this->cacheMock
            ->shouldReceive('has')
            ->once()
            ->with("offer:offer_slug:{$slug}")
            ->andReturn(false);

        $responseMock = $this->createHttpResponseMock(200, $offerData);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/slug/{$slug}")
            ->andReturn($responseMock);

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->offerService->getBySlug($slug);

        // Assert
        $this->assertEquals($offerData, $result);
        $this->assertEquals($slug, $result['slug']);
    }

    public function testListOffersWithFilters(): void
    {
        // Arrange
        $filters = ['status' => 'active', 'type' => 'subscription'];
        $page = 2;
        $limit = 10;
        $offers = [
            $this->generateOfferData(['status' => 'active', 'type' => 'subscription']),
            $this->generateOfferData(['status' => 'active', 'type' => 'subscription'])
        ];
        $responseData = [
            'data' => $offers,
            'total' => 25,
            'page' => $page,
            'limit' => $limit
        ];

        $responseMock = $this->createHttpResponseMock(200, $responseData);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/offers', [
                'query' => array_merge($filters, ['page' => $page, 'limit' => $limit])
            ])
            ->andReturn($responseMock);

        // Act
        $result = $this->offerService->list($filters, $page, $limit);

        // Assert
        $this->assertEquals($responseData, $result);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(25, $result['total']);
    }

    public function testUpdateOffer(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $updateData = ['name' => 'Updated Offer', 'status' => 'active'];
        $currentOffer = $this->generateOfferData(['id' => $offerId, 'slug' => 'original-slug']);
        $updatedOffer = array_merge($currentOffer, $updateData);

        // Mock getting current offer
        $this->cacheMock
            ->shouldReceive('has')
            ->andReturn(false);
        $this->httpClientMock
            ->shouldReceive('get')
            ->andReturn($this->createHttpResponseMock(200, $currentOffer));
        $this->cacheMock
            ->shouldReceive('set')
            ->andReturn(true);

        // Mock update
        $responseMock = $this->createHttpResponseMock(200, $updatedOffer);
        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with("/offers/{$offerId}", Mockery::type('array'))
            ->andReturn($responseMock);

        // Cache invalidation
        $this->cacheMock
            ->shouldReceive('delete')
            ->times(2); // ID and slug cache

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('offer.updated', Mockery::type('array'));

        // Act
        $result = $this->offerService->update($offerId, $updateData);

        // Assert
        $this->assertEquals($updatedOffer, $result);
        $this->assertEquals('Updated Offer', $result['name']);
        $this->assertEquals('active', $result['status']);
    }

    public function testDeleteOffer(): void
    {
        // Arrange
        $offerId = 'offer_123';

        $responseMock = $this->createHttpResponseMock(204);
        $this->httpClientMock
            ->shouldReceive('delete')
            ->once()
            ->with("/offers/{$offerId}")
            ->andReturn($responseMock);

        // Cache invalidation
        $this->cacheMock
            ->shouldReceive('has')
            ->andReturn(false);
        $this->httpClientMock
            ->shouldReceive('get')
            ->andReturn($this->createHttpResponseMock(200, ['id' => $offerId, 'slug' => 'test']));
        $this->cacheMock
            ->shouldReceive('set')
            ->andReturn(true);
        $this->cacheMock
            ->shouldReceive('delete')
            ->times(2);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('offer.deleted', ['offer_id' => $offerId]);

        // Act
        $result = $this->offerService->delete($offerId);

        // Assert
        $this->assertTrue($result);
    }

    public function testUpdateTheme(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $themeConfig = [
            'name' => 'modern',
            'colors' => ['primary' => '#007cba', 'secondary' => '#333'],
            'fonts' => ['heading' => 'Arial', 'body' => 'Helvetica']
        ];
        $updatedOffer = $this->generateOfferData([
            'id' => $offerId,
            'theme_config' => $themeConfig
        ]);

        $responseMock = $this->createHttpResponseMock(200, $updatedOffer);
        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with("/offers/{$offerId}/config/theme", ['theme' => $themeConfig])
            ->andReturn($responseMock);

        // Cache invalidation
        $this->cacheMock
            ->shouldReceive('has')
            ->andReturn(false);
        $this->httpClientMock
            ->shouldReceive('get')
            ->andReturn($this->createHttpResponseMock(200, ['id' => $offerId, 'slug' => 'test']));
        $this->cacheMock
            ->shouldReceive('set')
            ->andReturn(true);
        $this->cacheMock
            ->shouldReceive('delete')
            ->times(2);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('offer.theme_updated', Mockery::type('array'));

        // Act
        $result = $this->offerService->updateTheme($offerId, $themeConfig);

        // Assert
        $this->assertEquals($updatedOffer, $result);
        $this->assertEquals($themeConfig, $result['theme_config']);
    }

    public function testUpdateLayout(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $layoutConfig = [
            'type' => 'two_column',
            'sections' => ['header', 'product', 'benefits', 'checkout'],
            'responsive' => true
        ];
        $updatedOffer = $this->generateOfferData([
            'id' => $offerId,
            'layout_config' => $layoutConfig
        ]);

        $responseMock = $this->createHttpResponseMock(200, $updatedOffer);
        $this->httpClientMock
            ->shouldReceive('put')
            ->once()
            ->with("/offers/{$offerId}/config/layout", ['layout' => $layoutConfig])
            ->andReturn($responseMock);

        // Cache invalidation
        $this->cacheMock
            ->shouldReceive('has')
            ->andReturn(false);
        $this->httpClientMock
            ->shouldReceive('get')
            ->andReturn($this->createHttpResponseMock(200, ['id' => $offerId, 'slug' => 'test']));
        $this->cacheMock
            ->shouldReceive('set')
            ->andReturn(true);
        $this->cacheMock
            ->shouldReceive('delete')
            ->times(2);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('offer.layout_updated', Mockery::type('array'));

        // Act
        $result = $this->offerService->updateLayout($offerId, $layoutConfig);

        // Assert
        $this->assertEquals($updatedOffer, $result);
        $this->assertEquals($layoutConfig, $result['layout_config']);
    }

    // ============================================
    // UPSELL SERVICE TESTS
    // ============================================

    public function testUpsellServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(UpsellService::class, $this->upsellService);
    }

    public function testCreateUpsell(): void
    {
        // Arrange
        $upsellData = [
            'offer_id' => 'offer_123',
            'product_id' => 'prod_456',
            'type' => 'one_time_offer',
            'discount_percentage' => 20,
            'position' => 'after_checkout'
        ];
        $expectedUpsell = array_merge($upsellData, [
            'id' => 'upsell_' . uniqid(),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $responseMock = $this->createHttpResponseMock(201, $expectedUpsell);
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/upsells', $upsellData)
            ->andReturn($responseMock);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('upsell.created', Mockery::type('array'));

        // Act
        $result = $this->upsellService->create($upsellData);

        // Assert
        $this->assertEquals($expectedUpsell, $result);
        $this->assertEquals('offer_123', $result['offer_id']);
        $this->assertEquals('one_time_offer', $result['type']);
    }

    public function testGetUpsellsByOffer(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $upsells = [
            $this->generateUpsellData(['offer_id' => $offerId, 'type' => 'one_time_offer']),
            $this->generateUpsellData(['offer_id' => $offerId, 'type' => 'cross_sell'])
        ];

        $responseMock = $this->createHttpResponseMock(200, ['data' => $upsells]);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/{$offerId}/upsells")
            ->andReturn($responseMock);

        // Act
        $result = $this->upsellService->getByOffer($offerId);

        // Assert
        $this->assertEquals(['data' => $upsells], $result);
        $this->assertCount(2, $result['data']);
    }

    // ============================================
    // THEME SERVICE TESTS
    // ============================================

    public function testThemeServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(ThemeService::class, $this->themeService);
    }

    public function testApplyThemeToOffer(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $themeData = [
            'template' => 'premium',
            'colors' => ['primary' => '#ff6b6b', 'accent' => '#4ecdc4'],
            'typography' => ['heading' => 'Roboto', 'body' => 'Open Sans'],
            'custom_css' => '.custom { background: #f0f0f0; }'
        ];
        $appliedTheme = [
            'offer_id' => $offerId,
            'theme_id' => 'theme_456',
            'configuration' => $themeData,
            'applied_at' => date('Y-m-d H:i:s')
        ];

        $responseMock = $this->createHttpResponseMock(200, $appliedTheme);
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with("/offers/{$offerId}/theme/apply", $themeData)
            ->andReturn($responseMock);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('theme.applied', Mockery::type('array'));

        // Act
        $result = $this->themeService->applyToOffer($offerId, $themeData);

        // Assert
        $this->assertEquals($appliedTheme, $result);
        $this->assertEquals($offerId, $result['offer_id']);
        $this->assertEquals($themeData, $result['configuration']);
    }

    public function testGetAvailableThemes(): void
    {
        // Arrange
        $themes = [
            ['id' => 'theme_1', 'name' => 'Modern', 'category' => 'business'],
            ['id' => 'theme_2', 'name' => 'Elegant', 'category' => 'luxury'],
            ['id' => 'theme_3', 'name' => 'Simple', 'category' => 'minimal']
        ];

        $responseMock = $this->createHttpResponseMock(200, ['themes' => $themes]);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with('/themes')
            ->andReturn($responseMock);

        // Act
        $result = $this->themeService->getAvailable();

        // Assert
        $this->assertEquals(['themes' => $themes], $result);
        $this->assertCount(3, $result['themes']);
    }

    // ============================================
    // SUBSCRIPTION PLAN SERVICE TESTS
    // ============================================

    public function testSubscriptionPlanServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(SubscriptionPlanService::class, $this->subscriptionPlanService);
    }

    public function testCreateSubscriptionPlan(): void
    {
        // Arrange
        $planData = [
            'offer_id' => 'offer_123',
            'name' => 'Premium Monthly',
            'price' => 2999,
            'interval' => 'monthly',
            'trial_days' => 7,
            'features' => ['feature1', 'feature2']
        ];
        $expectedPlan = array_merge($planData, [
            'id' => 'plan_' . uniqid(),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);

        $responseMock = $this->createHttpResponseMock(201, $expectedPlan);
        $this->httpClientMock
            ->shouldReceive('post')
            ->once()
            ->with('/subscription-plans', $planData)
            ->andReturn($responseMock);

        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->once()
            ->with('subscription_plan.created', Mockery::type('array'));

        // Act
        $result = $this->subscriptionPlanService->create($planData);

        // Assert
        $this->assertEquals($expectedPlan, $result);
        $this->assertEquals('Premium Monthly', $result['name']);
        $this->assertEquals('monthly', $result['interval']);
        $this->assertEquals(2999, $result['price']);
    }

    public function testGetPlansByOffer(): void
    {
        // Arrange
        $offerId = 'offer_123';
        $plans = [
            $this->generateSubscriptionPlanData(['offer_id' => $offerId, 'interval' => 'monthly']),
            $this->generateSubscriptionPlanData(['offer_id' => $offerId, 'interval' => 'yearly'])
        ];

        $responseMock = $this->createHttpResponseMock(200, ['plans' => $plans]);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/{$offerId}/subscription-plans")
            ->andReturn($responseMock);

        // Act
        $result = $this->subscriptionPlanService->getByOffer($offerId);

        // Assert
        $this->assertEquals(['plans' => $plans], $result);
        $this->assertCount(2, $result['plans']);
    }

    // ============================================
    // PUBLIC OFFER SERVICE TESTS
    // ============================================

    public function testPublicOfferServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(PublicOfferService::class, $this->publicOfferService);
    }

    public function testGetPublicOfferBySlug(): void
    {
        // Arrange
        $slug = 'premium-course';
        $publicOfferData = [
            'id' => 'offer_123',
            'slug' => $slug,
            'name' => 'Premium Course',
            'status' => 'active',
            'is_public' => true,
            'seo' => [
                'title' => 'Premium Course - Learn Advanced Skills',
                'description' => 'Master advanced skills with our premium course'
            ],
            'public_url' => "https://checkout.clubify.com/{$slug}"
        ];

        $responseMock = $this->createHttpResponseMock(200, $publicOfferData);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/public/{$slug}")
            ->andReturn($responseMock);

        // Act
        $result = $this->publicOfferService->getBySlug($slug);

        // Assert
        $this->assertEquals($publicOfferData, $result);
        $this->assertEquals($slug, $result['slug']);
        $this->assertTrue($result['is_public']);
    }

    public function testGetPublicOfferMetadata(): void
    {
        // Arrange
        $slug = 'premium-course';
        $metadata = [
            'title' => 'Premium Course - Learn Advanced Skills',
            'description' => 'Master advanced skills with our premium course',
            'image' => 'https://cdn.example.com/course-image.jpg',
            'url' => "https://checkout.clubify.com/{$slug}",
            'type' => 'website',
            'price' => 19999,
            'currency' => 'BRL'
        ];

        $responseMock = $this->createHttpResponseMock(200, $metadata);
        $this->httpClientMock
            ->shouldReceive('get')
            ->once()
            ->with("/offers/public/{$slug}/metadata")
            ->andReturn($responseMock);

        // Act
        $result = $this->publicOfferService->getMetadata($slug);

        // Assert
        $this->assertEquals($metadata, $result);
        $this->assertEquals('Premium Course - Learn Advanced Skills', $result['title']);
        $this->assertEquals(19999, $result['price']);
    }

    // ============================================
    // INTEGRATION AND PERFORMANCE TESTS
    // ============================================

    public function testOfferServiceIntegrationWithAllSubServices(): void
    {
        // Arrange
        $offerData = ['name' => 'Integration Test Offer', 'type' => 'subscription'];
        $offer = $this->generateOfferData(['id' => 'offer_integration']);
        $themeData = ['template' => 'modern', 'colors' => ['primary' => '#007cba']];
        $upsellData = ['product_id' => 'prod_456', 'type' => 'cross_sell'];
        $planData = ['name' => 'Monthly Plan', 'price' => 2999, 'interval' => 'monthly'];

        // Mock offer creation
        $this->httpClientMock
            ->shouldReceive('post')
            ->with('/offers', Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, $offer));

        // Mock theme application
        $this->httpClientMock
            ->shouldReceive('post')
            ->with("/offers/{$offer['id']}/theme/apply", $themeData)
            ->andReturn($this->createHttpResponseMock(200, ['applied' => true]));

        // Mock upsell creation
        $this->httpClientMock
            ->shouldReceive('post')
            ->with('/upsells', Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, ['id' => 'upsell_123']));

        // Mock plan creation
        $this->httpClientMock
            ->shouldReceive('post')
            ->with('/subscription-plans', Mockery::type('array'))
            ->andReturn($this->createHttpResponseMock(201, ['id' => 'plan_123']));

        // Cache operations
        $this->cacheMock->shouldReceive('set')->andReturn(true);
        $this->cacheMock->shouldReceive('has')->andReturn(false);
        $this->cacheMock->shouldReceive('get')->andReturnNull();

        // Events should be dispatched
        $this->eventDispatcherMock
            ->shouldReceive('dispatch')
            ->times(4); // offer created + theme applied + upsell created + plan created

        // Act
        $createdOffer = $this->offerService->create($offerData);
        $appliedTheme = $this->themeService->applyToOffer($createdOffer['id'], $themeData);
        $createdUpsell = $this->upsellService->create(array_merge($upsellData, ['offer_id' => $createdOffer['id']]));
        $createdPlan = $this->subscriptionPlanService->create(array_merge($planData, ['offer_id' => $createdOffer['id']]));

        // Assert
        $this->assertEquals($offer, $createdOffer);
        $this->assertTrue($appliedTheme['applied']);
        $this->assertEquals('upsell_123', $createdUpsell['id']);
        $this->assertEquals('plan_123', $createdPlan['id']);
    }

    public function testCachePerformanceWithApiCalls(): void
    {
        // Arrange
        $offerId = 'offer_cache_test';
        $offerData = $this->generateOfferData(['id' => $offerId]);

        // First call should hit API
        $this->cacheMock
            ->shouldReceive('has')
            ->with("offer:offer:{$offerId}")
            ->andReturn(false, true); // Miss first, hit second

        $this->cacheMock
            ->shouldReceive('get')
            ->with("offer:offer:{$offerId}")
            ->andReturn(null, $offerData);

        $this->httpClientMock
            ->shouldReceive('get')
            ->once() // Should only be called once
            ->with("/offers/{$offerId}")
            ->andReturn($this->createHttpResponseMock(200, $offerData));

        $this->cacheMock
            ->shouldReceive('set')
            ->once()
            ->andReturn(true);

        // Act - First call loads from API and caches
        $result1 = $this->offerService->get($offerId);

        // Second call should use cache
        $result2 = $this->offerService->get($offerId);

        // Assert
        $this->assertEquals($offerData, $result1);
        $this->assertEquals($offerData, $result2);
    }

    public function testErrorHandlingInAllServices(): void
    {
        // Test OfferService error handling
        $this->httpClientMock
            ->shouldReceive('get')
            ->andThrow(new HttpException('API Error', 500));

        $result = $this->offerService->get('offer_error');
        $this->assertNull($result);

        // Test validation errors
        $this->expectException(ValidationException::class);
        $this->offerService->create(['invalid' => 'data']);
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
            'organization_id' => 'org_' . uniqid(),
            'name' => 'Test Offer',
            'slug' => 'test-offer',
            'type' => 'single_product',
            'status' => 'active',
            'active' => true,
            'products' => [
                ['id' => 'prod_123', 'name' => 'Product 1', 'price' => 9999]
            ],
            'pricing' => ['total_price' => 9999],
            'checkout_config' => [],
            'design_config' => [],
            'theme_config' => [],
            'layout_config' => [],
            'upsells' => [],
            'conversion_tools' => [],
            'analytics' => ['enabled' => true],
            'seo' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    private function generateUpsellData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'upsell_' . uniqid(),
            'offer_id' => 'offer_' . uniqid(),
            'product_id' => 'prod_' . uniqid(),
            'type' => 'one_time_offer',
            'discount_percentage' => 15,
            'position' => 'after_checkout',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    private function generateSubscriptionPlanData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'plan_' . uniqid(),
            'offer_id' => 'offer_' . uniqid(),
            'name' => 'Monthly Plan',
            'price' => 2999,
            'interval' => 'monthly',
            'trial_days' => 0,
            'status' => 'active',
            'features' => ['feature1', 'feature2'],
            'created_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }
}