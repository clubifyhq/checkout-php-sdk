<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\Subscriptions;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\Subscriptions\SubscriptionsModule;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Subscriptions\Services\BillingService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionMetricsService;
use Clubify\Checkout\Modules\Subscriptions\Services\SubscriptionLifecycleService;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para SubscriptionsModule
 *
 * Testa todas as funcionalidades do módulo de assinaturas:
 * - Inicialização e configuração
 * - CRUD de assinaturas
 * - Gestão de planos
 * - Lifecycle de assinaturas
 * - Cobrança e billing
 * - Métricas e analytics
 * - Health checks
 *
 * @covers \Clubify\Checkout\Modules\Subscriptions\SubscriptionsModule
 * @group unit
 * @group subscriptions
 */
class SubscriptionsModuleTest extends TestCase
{
    private SubscriptionsModule $subscriptionsModule;
    private MockInterface $subscriptionService;
    private MockInterface $subscriptionPlanService;
    private MockInterface $billingService;
    private MockInterface $subscriptionMetricsService;
    private MockInterface $subscriptionLifecycleService;

    protected function setUp(): void
    {
        parent::setUp();

        // Cria módulo
        $this->subscriptionsModule = new SubscriptionsModule($this->sdk);

        // Mock dos services
        $this->subscriptionService = Mockery::mock(SubscriptionService::class);
        $this->subscriptionPlanService = Mockery::mock(SubscriptionPlanService::class);
        $this->billingService = Mockery::mock(BillingService::class);
        $this->subscriptionMetricsService = Mockery::mock(SubscriptionMetricsService::class);
        $this->subscriptionLifecycleService = Mockery::mock(SubscriptionLifecycleService::class);
    }

    /** @test */
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(SubscriptionsModule::class, $this->subscriptionsModule);
        $this->assertFalse($this->subscriptionsModule->isInitialized());
        $this->assertEquals('subscriptions', $this->subscriptionsModule->getName());
        $this->assertEquals('1.0.0', $this->subscriptionsModule->getVersion());
    }

    /** @test */
    public function it_can_be_initialized(): void
    {
        // Act
        $this->subscriptionsModule->initialize($this->config, $this->logger);

        // Assert
        $this->assertTrue($this->subscriptionsModule->isInitialized());
        $this->assertTrue($this->subscriptionsModule->isAvailable());
        $this->assertTrue($this->subscriptionsModule->isHealthy());
    }

    /** @test */
    public function it_returns_correct_dependencies(): void
    {
        $dependencies = $this->subscriptionsModule->getDependencies();

        $this->assertIsArray($dependencies);
        $this->assertContains('payments', $dependencies);
        $this->assertContains('customers', $dependencies);
        $this->assertContains('products', $dependencies);
    }

    /** @test */
    public function it_throws_exception_when_not_initialized(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Subscriptions module is not initialized');

        $this->subscriptionsModule->createSubscription($this->generateSubscriptionData());
    }

    /** @test */
    public function it_can_create_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionData = $this->generateSubscriptionData();
        $expectedResponse = ['id' => $subscriptionData['id'], 'status' => 'active'];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionService);

        $this->subscriptionService
            ->shouldReceive('create')
            ->once()
            ->with($subscriptionData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->createSubscription($subscriptionData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';
        $expectedSubscription = $this->generateSubscriptionData(['id' => $subscriptionId]);

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionService);

        $this->subscriptionService
            ->shouldReceive('get')
            ->once()
            ->with($subscriptionId)
            ->andReturn($expectedSubscription);

        // Act
        $result = $this->subscriptionsModule->getSubscription($subscriptionId);

        // Assert
        $this->assertEquals($expectedSubscription, $result);
    }

    /** @test */
    public function it_can_list_subscriptions(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $filters = ['status' => 'active', 'customer_id' => 'cust_123'];
        $page = 1;
        $limit = 20;
        $expectedResponse = [
            'data' => [$this->generateSubscriptionData(), $this->generateSubscriptionData()],
            'total' => 50,
            'page' => $page,
            'limit' => $limit
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionService);

        $this->subscriptionService
            ->shouldReceive('list')
            ->once()
            ->with($filters, $page, $limit)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->listSubscriptions($filters, $page, $limit);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_pause_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('pause')
            ->once()
            ->with($subscriptionId)
            ->andReturn(true);

        // Act
        $result = $this->subscriptionsModule->pauseSubscription($subscriptionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_resume_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('resume')
            ->once()
            ->with($subscriptionId)
            ->andReturn(true);

        // Act
        $result = $this->subscriptionsModule->resumeSubscription($subscriptionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_cancel_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';
        $reason = 'Customer request';
        $immediateCancel = false;

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('cancel')
            ->once()
            ->with($subscriptionId, $reason, $immediateCancel)
            ->andReturn(true);

        // Act
        $result = $this->subscriptionsModule->cancelSubscription($subscriptionId, $reason, $immediateCancel);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_upgrade_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';
        $newPlanId = 'plan_premium';
        $expectedResponse = [
            'success' => true,
            'old_plan' => 'plan_basic',
            'new_plan' => $newPlanId,
            'prorated_amount' => 1500
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('upgrade')
            ->once()
            ->with($subscriptionId, $newPlanId)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->upgradeSubscription($subscriptionId, $newPlanId);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_downgrade_subscription(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';
        $newPlanId = 'plan_basic';
        $expectedResponse = [
            'success' => true,
            'old_plan' => 'plan_premium',
            'new_plan' => $newPlanId,
            'effective_date' => '2024-02-01'
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('downgrade')
            ->once()
            ->with($subscriptionId, $newPlanId)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->downgradeSubscription($subscriptionId, $newPlanId);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_create_subscription_plan(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $planData = [
            'id' => 'plan_123',
            'name' => 'Premium Plan',
            'price' => 2999,
            'currency' => 'BRL',
            'billing_cycle' => 'monthly',
            'features' => ['feature1', 'feature2']
        ];
        $expectedResponse = array_merge($planData, ['created_at' => date('Y-m-d H:i:s')]);

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionPlanService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionPlanService);

        $this->subscriptionPlanService
            ->shouldReceive('create')
            ->once()
            ->with($planData)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->createSubscriptionPlan($planData);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_get_subscription_plan(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $planId = 'plan_123';
        $expectedPlan = [
            'id' => $planId,
            'name' => 'Premium Plan',
            'price' => 2999,
            'currency' => 'BRL'
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionPlanService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionPlanService);

        $this->subscriptionPlanService
            ->shouldReceive('get')
            ->once()
            ->with($planId)
            ->andReturn($expectedPlan);

        // Act
        $result = $this->subscriptionsModule->getSubscriptionPlan($planId);

        // Assert
        $this->assertEquals($expectedPlan, $result);
    }

    /** @test */
    public function it_can_list_subscription_plans(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $filters = ['active' => true];
        $expectedPlans = [
            'data' => [
                ['id' => 'plan_1', 'name' => 'Basic Plan'],
                ['id' => 'plan_2', 'name' => 'Premium Plan']
            ],
            'total' => 2
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionPlanService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionPlanService);

        $this->subscriptionPlanService
            ->shouldReceive('list')
            ->once()
            ->with($filters)
            ->andReturn($expectedPlans);

        // Act
        $result = $this->subscriptionsModule->listSubscriptionPlans($filters);

        // Assert
        $this->assertEquals($expectedPlans, $result);
    }

    /** @test */
    public function it_can_process_billing(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';
        $expectedResponse = [
            'success' => true,
            'invoice_id' => 'inv_456',
            'amount_charged' => 2999,
            'next_billing_date' => '2024-02-15'
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('billingService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->billingService);

        $this->billingService
            ->shouldReceive('processBilling')
            ->once()
            ->with($subscriptionId)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->processBilling($subscriptionId);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }

    /** @test */
    public function it_can_retry_failed_billing(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionId = 'sub_123';

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('billingService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->billingService);

        $this->billingService
            ->shouldReceive('retryFailedBilling')
            ->once()
            ->with($subscriptionId)
            ->andReturn(true);

        // Act
        $result = $this->subscriptionsModule->retryFailedBilling($subscriptionId);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_subscription_metrics(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $filters = ['date_from' => '2024-01-01', 'date_to' => '2024-01-31'];
        $expectedMetrics = [
            'total_subscriptions' => 150,
            'active_subscriptions' => 120,
            'cancelled_subscriptions' => 20,
            'paused_subscriptions' => 10,
            'monthly_recurring_revenue' => 35000,
            'churn_rate' => 2.5,
            'growth_rate' => 15.0
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionMetricsService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionMetricsService);

        $this->subscriptionMetricsService
            ->shouldReceive('getMetrics')
            ->once()
            ->with($filters)
            ->andReturn($expectedMetrics);

        // Act
        $result = $this->subscriptionsModule->getSubscriptionMetrics($filters);

        // Assert
        $this->assertEquals($expectedMetrics, $result);
    }

    /** @test */
    public function it_can_get_churn_analysis(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $period = 'monthly';
        $expectedAnalysis = [
            'churn_rate' => 3.2,
            'revenue_churn' => 4500,
            'top_cancellation_reasons' => [
                'price' => 35,
                'feature_limitations' => 25,
                'competitor' => 20,
                'other' => 20
            ],
            'churn_prediction' => [
                'at_risk_subscribers' => 15,
                'confidence_score' => 0.85
            ]
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionMetricsService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionMetricsService);

        $this->subscriptionMetricsService
            ->shouldReceive('getChurnAnalysis')
            ->once()
            ->with($period)
            ->andReturn($expectedAnalysis);

        // Act
        $result = $this->subscriptionsModule->getChurnAnalysis($period);

        // Assert
        $this->assertEquals($expectedAnalysis, $result);
    }

    /** @test */
    public function it_can_get_revenue_forecast(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $months = 6;
        $expectedForecast = [
            'current_mrr' => 35000,
            'projected_mrr' => [
                '2024-02' => 36500,
                '2024-03' => 38200,
                '2024-04' => 39800,
                '2024-05' => 41500,
                '2024-06' => 43300,
                '2024-07' => 45100
            ],
            'growth_assumptions' => [
                'new_subscribers_per_month' => 25,
                'churn_rate' => 2.5,
                'upgrade_rate' => 8.0
            ]
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionMetricsService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionMetricsService);

        $this->subscriptionMetricsService
            ->shouldReceive('getRevenueForecast')
            ->once()
            ->with($months)
            ->andReturn($expectedForecast);

        // Act
        $result = $this->subscriptionsModule->getRevenueForecast($months);

        // Assert
        $this->assertEquals($expectedForecast, $result);
    }

    /** @test */
    public function it_performs_health_check(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $expectedHealth = [
            'healthy' => true,
            'status_code' => 200,
            'services' => [
                'subscription_service' => 'ok',
                'billing_service' => 'ok',
                'metrics_service' => 'ok'
            ],
            'response_time' => '150ms'
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionService);

        $this->subscriptionService
            ->shouldReceive('healthCheck')
            ->once()
            ->andReturn($expectedHealth);

        // Act
        $result = $this->subscriptionsModule->performHealthCheck();

        // Assert
        $this->assertEquals($expectedHealth, $result);
    }

    /** @test */
    public function it_returns_module_status(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);

        // Act
        $status = $this->subscriptionsModule->getStatus();

        // Assert
        $this->assertIsArray($status);
        $this->assertArrayHasKey('name', $status);
        $this->assertArrayHasKey('version', $status);
        $this->assertArrayHasKey('initialized', $status);
        $this->assertArrayHasKey('available', $status);
        $this->assertArrayHasKey('services_loaded', $status);
        $this->assertEquals('subscriptions', $status['name']);
        $this->assertEquals('1.0.0', $status['version']);
        $this->assertTrue($status['initialized']);
        $this->assertTrue($status['available']);
    }

    /** @test */
    public function it_can_cleanup_properly(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);

        // Act
        $this->subscriptionsModule->cleanup();

        // Assert
        $this->assertFalse($this->subscriptionsModule->isInitialized());
        $this->assertFalse($this->subscriptionsModule->isAvailable());
    }

    /** @test */
    public function it_handles_bulk_operations(): void
    {
        // Arrange
        $this->subscriptionsModule->initialize($this->config, $this->logger);
        $subscriptionIds = ['sub_1', 'sub_2', 'sub_3'];
        $action = 'pause';
        $expectedResponse = [
            'results' => [
                'sub_1' => ['success' => true],
                'sub_2' => ['success' => true],
                'sub_3' => ['success' => false, 'error' => 'Subscription not found']
            ],
            'summary' => ['total' => 3, 'successful' => 2, 'failed' => 1]
        ];

        $reflection = new \ReflectionClass($this->subscriptionsModule);
        $serviceProperty = $reflection->getProperty('subscriptionLifecycleService');
        $serviceProperty->setAccessible(true);
        $serviceProperty->setValue($this->subscriptionsModule, $this->subscriptionLifecycleService);

        $this->subscriptionLifecycleService
            ->shouldReceive('bulkAction')
            ->once()
            ->with($subscriptionIds, $action)
            ->andReturn($expectedResponse);

        // Act
        $result = $this->subscriptionsModule->bulkSubscriptionAction($subscriptionIds, $action);

        // Assert
        $this->assertEquals($expectedResponse, $result);
    }
}