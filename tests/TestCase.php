<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Mockery;
use Mockery\MockInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManager;
use Clubify\Checkout\Core\Events\EventDispatcher;

/**
 * Classe base para todos os testes do SDK
 *
 * Fornece funcionalidades comuns para testes:
 * - Setup e teardown do Mockery
 * - Criação de mocks padronizados
 * - Configuração de ambiente de teste
 * - Helpers para asserções
 * - Geração de dados de teste
 *
 * Segue padrões de testing:
 * - AAA (Arrange, Act, Assert)
 * - Given-When-Then
 * - Mocks limpos e isolados
 * - Dados de teste realistas
 */
abstract class TestCase extends BaseTestCase
{
    protected Configuration $config;
    protected Logger $logger;
    protected Client $httpClient;
    protected CacheManager $cache;
    protected EventDispatcher $events;
    protected ClubifyCheckoutSDK $sdk;

    /**
     * Setup executado antes de cada teste
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Inicializa mocks básicos
        $this->setupBasicMocks();

        // Configura ambiente de teste
        $this->setupTestEnvironment();

        // Cria SDK para testes
        $this->setupSDK();
    }

    /**
     * Teardown executado após cada teste
     */
    protected function tearDown(): void
    {
        // Limpa mocks do Mockery
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        // Limpa variáveis
        unset(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->events,
            $this->sdk
        );

        parent::tearDown();
    }

    /**
     * Cria mock da configuração
     */
    protected function createConfigMock(array $overrides = []): MockInterface
    {
        $mock = Mockery::mock(Configuration::class);

        $defaults = [
            'getApiUrl' => 'http://localhost:8080',
            'getTenantId' => 'test-tenant-' . uniqid(),
            'getApiKey' => 'test-api-key-' . uniqid(),
            'getSecretKey' => 'test-secret-key-' . uniqid(),
            'getTimeout' => 30,
            'getRetryAttempts' => 3,
            'isDebugMode' => true,
            'getCacheEnabled' => true,
            'getCacheTtl' => 300,
            'getEventsEnabled' => true,
        ];

        foreach (array_merge($defaults, $overrides) as $method => $value) {
            $mock->shouldReceive($method)
                 ->andReturn($value)
                 ->byDefault();
        }

        return $mock;
    }

    /**
     * Cria mock do logger
     */
    protected function createLoggerMock(): MockInterface
    {
        $mock = Mockery::mock(Logger::class);

        // Mock métodos de log básicos
        foreach (['debug', 'info', 'warning', 'error', 'critical'] as $level) {
            $mock->shouldReceive($level)
                 ->andReturnNull()
                 ->byDefault();
        }

        return $mock;
    }

    /**
     * Cria mock do cliente HTTP
     */
    protected function createHttpClientMock(): MockInterface
    {
        $mock = Mockery::mock(Client::class);

        // Mock métodos HTTP básicos
        foreach (['get', 'post', 'put', 'patch', 'delete'] as $method) {
            $mock->shouldReceive($method)
                 ->andReturn($this->createHttpResponseMock())
                 ->byDefault();
        }

        return $mock;
    }

    /**
     * Cria mock de resposta HTTP
     */
    protected function createHttpResponseMock(int $statusCode = 200, array $data = []): MockInterface
    {
        $mock = Mockery::mock();

        $mock->shouldReceive('getStatusCode')
             ->andReturn($statusCode)
             ->byDefault();

        $mock->shouldReceive('toArray')
             ->andReturn($data ?: $this->generateSampleResponse())
             ->byDefault();

        $mock->shouldReceive('getHeaders')
             ->andReturn([
                 'Content-Type' => ['application/json'],
                 'X-Response-Time' => ['120ms'],
                 'X-Request-ID' => [uniqid('req_')]
             ])
             ->byDefault();

        return $mock;
    }

    /**
     * Cria mock do cache manager
     */
    protected function createCacheManagerMock(): MockInterface
    {
        $mock = Mockery::mock(CacheManager::class);

        $mock->shouldReceive('get')
             ->andReturnNull()
             ->byDefault();

        $mock->shouldReceive('set')
             ->andReturnTrue()
             ->byDefault();

        $mock->shouldReceive('delete')
             ->andReturnTrue()
             ->byDefault();

        $mock->shouldReceive('flush')
             ->andReturnTrue()
             ->byDefault();

        return $mock;
    }

    /**
     * Cria mock do event dispatcher
     */
    protected function createEventDispatcherMock(): MockInterface
    {
        $mock = Mockery::mock(EventDispatcher::class);

        $mock->shouldReceive('dispatch')
             ->andReturnNull()
             ->byDefault();

        return $mock;
    }

    /**
     * Cria mock do SDK
     */
    protected function createSDKMock(): MockInterface
    {
        $mock = Mockery::mock(ClubifyCheckoutSDK::class);

        $mock->shouldReceive('getConfig')
             ->andReturn($this->config)
             ->byDefault();

        $mock->shouldReceive('getLogger')
             ->andReturn($this->logger)
             ->byDefault();

        $mock->shouldReceive('getHttpClient')
             ->andReturn($this->httpClient)
             ->byDefault();

        $mock->shouldReceive('getCache')
             ->andReturn($this->cache)
             ->byDefault();

        $mock->shouldReceive('getEvents')
             ->andReturn($this->events)
             ->byDefault();

        return $mock;
    }

    /**
     * Gera dados de teste para notificação
     */
    protected function generateNotificationData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'notif_' . uniqid(),
            'type' => 'checkout.completed',
            'recipient' => 'test@example.com',
            'subject' => 'Test Notification',
            'body' => 'This is a test notification body.',
            'status' => 'pending',
            'delivery_method' => 'email',
            'priority' => 1,
            'timeout' => 30,
            'retry_count' => 0,
            'metadata' => [
                'source' => 'test',
                'environment' => 'testing'
            ],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Gera dados de teste para pedido
     */
    protected function generateOrderData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'order_' . uniqid(),
            'customer_id' => 'cust_' . uniqid(),
            'status' => 'pending',
            'total' => 9999, // Em centavos
            'currency' => 'BRL',
            'items' => [
                [
                    'id' => 'item_' . uniqid(),
                    'name' => 'Test Product',
                    'price' => 9999,
                    'quantity' => 1
                ]
            ],
            'payment_method' => 'credit_card',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Gera dados de teste para assinatura
     */
    protected function generateSubscriptionData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_' . uniqid(),
            'customer_id' => 'cust_' . uniqid(),
            'plan_id' => 'plan_' . uniqid(),
            'status' => 'active',
            'current_period_start' => date('Y-m-d'),
            'current_period_end' => date('Y-m-d', strtotime('+1 month')),
            'billing_cycle' => 'monthly',
            'amount' => 2999, // Em centavos
            'currency' => 'BRL',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Gera dados de teste para tracking
     */
    protected function generateTrackingData(array $overrides = []): array
    {
        return array_merge([
            'event_type' => 'page_view',
            'user_id' => 'user_' . uniqid(),
            'session_id' => 'sess_' . uniqid(),
            'properties' => [
                'page' => '/checkout',
                'referrer' => 'https://google.com',
                'user_agent' => 'Test User Agent'
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Gera dados de teste para usuário
     */
    protected function generateUserData(array $overrides = []): array
    {
        return array_merge([
            'id' => 'user_' . uniqid(),
            'email' => 'test.' . uniqid() . '@example.com',
            'name' => 'Test User',
            'tenant_id' => 'tenant_' . uniqid(),
            'role' => 'user',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], $overrides);
    }

    /**
     * Gera resposta de exemplo da API
     */
    protected function generateSampleResponse(): array
    {
        return [
            'success' => true,
            'data' => [
                'id' => uniqid(),
                'status' => 'success',
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'metadata' => [
                'request_id' => uniqid('req_'),
                'response_time' => '120ms'
            ]
        ];
    }

    /**
     * Assert que uma exceção específica é lançada
     */
    protected function assertExceptionThrown(string $expectedException, callable $callback): void
    {
        $exceptionThrown = false;

        try {
            $callback();
        } catch (\Exception $e) {
            $this->assertInstanceOf($expectedException, $e);
            $exceptionThrown = true;
        }

        $this->assertTrue($exceptionThrown, "Expected exception {$expectedException} was not thrown");
    }

    /**
     * Assert que arrays têm estrutura similar
     */
    protected function assertArrayStructure(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            $this->assertArrayHasKey($key, $actual, "Key '{$key}' is missing in actual array");

            if (is_array($value)) {
                $this->assertIsArray($actual[$key], "Value for key '{$key}' should be an array");
                $this->assertArrayStructure($value, $actual[$key]);
            }
        }
    }

    /**
     * Assert que valor está dentro de um range
     */
    protected function assertInRange($value, $min, $max, string $message = ''): void
    {
        $this->assertGreaterThanOrEqual($min, $value, $message);
        $this->assertLessThanOrEqual($max, $value, $message);
    }

    /**
     * Setup básico dos mocks
     */
    private function setupBasicMocks(): void
    {
        $this->config = $this->createConfigMock();
        $this->logger = $this->createLoggerMock();
        $this->httpClient = $this->createHttpClientMock();
        $this->cache = $this->createCacheManagerMock();
        $this->events = $this->createEventDispatcherMock();
    }

    /**
     * Setup do ambiente de teste
     */
    private function setupTestEnvironment(): void
    {
        // Define variáveis de ambiente para teste
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['CLUBIFY_API_URL'] = 'http://localhost:8080';
        $_ENV['CLUBIFY_TENANT_ID'] = 'test-tenant';
        $_ENV['CLUBIFY_DISABLE_WEBHOOKS'] = 'true';
        $_ENV['CLUBIFY_MOCK_HTTP_CLIENT'] = 'true';

        // Configura timezone
        if (!date_default_timezone_get()) {
            date_default_timezone_set('UTC');
        }
    }

    /**
     * Setup do SDK para testes
     */
    private function setupSDK(): void
    {
        $this->sdk = $this->createSDKMock();
    }
}
