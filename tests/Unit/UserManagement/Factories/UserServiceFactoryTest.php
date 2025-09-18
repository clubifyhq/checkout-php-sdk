<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\UserManagement\Factories;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository;
use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para UserServiceFactory
 *
 * Testa todas as funcionalidades da factory incluindo:
 * - Implementação da FactoryInterface
 * - Criação de services com dependency injection
 * - Singleton pattern (cache de services)
 * - Suporte a tipos específicos
 * - Error handling para tipos não suportados
 * - Gerenciamento de cache
 *
 * Cobertura: 100% dos métodos públicos
 */
class UserServiceFactoryTest extends TestCase
{
    private UserServiceFactory $factory;
    private MockInterface $config;
    private MockInterface $logger;
    private MockInterface $httpClient;
    private MockInterface $cache;
    private MockInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup mocks
        $this->config = Mockery::mock(Configuration::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->httpClient = Mockery::mock(Client::class);
        $this->cache = Mockery::mock(CacheManagerInterface::class);
        $this->eventDispatcher = Mockery::mock(EventDispatcherInterface::class);

        // Setup default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Create factory instance
        $this->factory = new UserServiceFactory(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    public function testImplementsFactoryInterface(): void
    {
        $this->assertInstanceOf(FactoryInterface::class, $this->factory);
    }

    public function testGetSupportedTypes(): void
    {
        // Act
        $supportedTypes = $this->factory->getSupportedTypes();

        // Assert
        $this->assertIsArray($supportedTypes);
        $this->assertContains('user', $supportedTypes);
        $this->assertContains('auth', $supportedTypes);
        $this->assertContains('passkey', $supportedTypes);
        $this->assertContains('tenant', $supportedTypes);
        $this->assertContains('role', $supportedTypes);
        $this->assertContains('session', $supportedTypes);
    }

    public function testCreateUserServiceSuccess(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')
            ->with('Creating UserManagement service', [
                'type' => 'user',
                'config' => []
            ])
            ->once();

        $this->logger->shouldReceive('debug')
            ->with('Repository created', Mockery::type('array'))
            ->once();

        $this->logger->shouldReceive('info')
            ->with('UserManagement service created successfully', Mockery::type('array'))
            ->once();

        // Act
        $service = $this->factory->create('user');

        // Assert
        $this->assertInstanceOf(UserService::class, $service);
    }

    public function testCreateUserServiceSingleton(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('info')->once();

        // Act
        $service1 = $this->factory->create('user');
        $service2 = $this->factory->create('user'); // Should return same instance

        // Assert
        $this->assertSame($service1, $service2);
    }

    public function testCreateUserServiceWithConfig(): void
    {
        // Arrange
        $config = ['timeout' => 60, 'retry_count' => 5];

        $this->logger->shouldReceive('debug')
            ->with('Creating UserManagement service', [
                'type' => 'user',
                'config' => $config
            ])
            ->once();

        $this->logger->shouldReceive('debug')
            ->with('Repository created', Mockery::type('array'))
            ->once();

        $this->logger->shouldReceive('info')
            ->with('UserManagement service created successfully', Mockery::type('array'))
            ->once();

        // Act
        $service = $this->factory->create('user', $config);

        // Assert
        $this->assertInstanceOf(UserService::class, $service);
    }

    public function testCreateUnsupportedServiceType(): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Repository type 'auth' is not yet implemented. Currently only 'user' is available.");

        $this->factory->create('auth');
    }

    public function testCreateInvalidServiceType(): void
    {
        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service type 'invalid_type' is not supported");

        $this->factory->create('invalid_type');
    }

    public function testIsTypeSupported(): void
    {
        // Act & Assert
        $this->assertTrue($this->factory->isTypeSupported('user'));
        $this->assertTrue($this->factory->isTypeSupported('auth'));
        $this->assertTrue($this->factory->isTypeSupported('passkey'));
        $this->assertFalse($this->factory->isTypeSupported('invalid_type'));
    }

    public function testClearCache(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('info')->twice(); // once for create, once for clear

        // Create a service first
        $service1 = $this->factory->create('user');

        $this->logger->shouldReceive('info')
            ->with('UserServiceFactory cache cleared')
            ->once();

        // Act
        $this->factory->clearCache();

        // Create service again after cache clear
        $service2 = $this->factory->create('user');

        // Assert
        $this->assertNotSame($service1, $service2); // Should be different instances after cache clear
    }

    public function testGetStats(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('info')->once();

        // Create a service to populate cache
        $this->factory->create('user');

        // Act
        $stats = $this->factory->getStats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('supported_types', $stats);
        $this->assertArrayHasKey('created_services', $stats);
        $this->assertArrayHasKey('created_repositories', $stats);
        $this->assertArrayHasKey('types', $stats);
        $this->assertArrayHasKey('created_service_types', $stats);
        $this->assertArrayHasKey('created_repository_types', $stats);

        $this->assertGreaterThan(0, $stats['supported_types']);
        $this->assertEquals(1, $stats['created_services']);
        $this->assertEquals(1, $stats['created_repositories']);
        $this->assertContains('user', $stats['types']);
        $this->assertContains('user', $stats['created_service_types']);
        $this->assertContains('user', $stats['created_repository_types']);
    }

    public function testGetStatsEmptyCache(): void
    {
        // Act
        $stats = $this->factory->getStats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['created_services']);
        $this->assertEquals(0, $stats['created_repositories']);
        $this->assertEmpty($stats['created_service_types']);
        $this->assertEmpty($stats['created_repository_types']);
    }

    public function testRepositorySingleton(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->times(4);
        $this->logger->shouldReceive('info')->twice();

        // Act - Create two different services that use the same repository type
        $service1 = $this->factory->create('user');

        // Clear services cache but keep repositories
        $reflection = new \ReflectionClass($this->factory);
        $servicesProperty = $reflection->getProperty('services');
        $servicesProperty->setAccessible(true);
        $servicesProperty->setValue($this->factory, []);

        $service2 = $this->factory->create('user');

        // Assert - Both services should use the same repository instance
        // We can't directly test this without reflection, but we can verify through stats
        $stats = $this->factory->getStats();
        $this->assertEquals(1, $stats['created_repositories']); // Only one repository created
    }

    public function testCreateWithDependencyInjection(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('info')->once();

        // Act
        $service = $this->factory->create('user');

        // Assert
        $this->assertInstanceOf(UserService::class, $service);

        // Verify service has correct dependencies through reflection
        $reflection = new \ReflectionClass($service);

        // Check if service was constructed with repository and logger
        $constructor = $reflection->getConstructor();
        $parameters = $constructor->getParameters();

        $this->assertCount(2, $parameters);
        $this->assertEquals('repository', $parameters[0]->getName());
        $this->assertEquals('logger', $parameters[1]->getName());
    }

    public function testResolveRepositoryClassForUser(): void
    {
        // Arrange
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('resolveRepositoryClass');
        $method->setAccessible(true);

        // Act
        $className = $method->invoke($this->factory, 'user');

        // Assert
        $this->assertEquals(ApiUserRepository::class, $className);
    }

    public function testResolveRepositoryClassForUnsupportedType(): void
    {
        // Arrange
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('resolveRepositoryClass');
        $method->setAccessible(true);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Repository type 'auth' is not yet implemented");

        $method->invoke($this->factory, 'auth');
    }

    public function testResolveRepositoryClassForInvalidType(): void
    {
        // Arrange
        $reflection = new \ReflectionClass($this->factory);
        $method = $reflection->getMethod('resolveRepositoryClass');
        $method->setAccessible(true);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Repository type 'invalid' is not yet implemented");

        $method->invoke($this->factory, 'invalid');
    }

    public function testCreateRepositoryWithAllDependencies(): void
    {
        // Arrange
        $this->logger->shouldReceive('debug')->twice();
        $this->logger->shouldReceive('info')->once();

        // Act
        $service = $this->factory->create('user');

        // Assert
        $this->assertInstanceOf(UserService::class, $service);

        // Verify that all dependencies were properly injected
        // This is implicitly tested by the fact that service creation succeeds
        // and follows the constructor requirements
    }

    private function setupDefaultMockBehaviors(): void
    {
        // Logger mocks
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        // Event dispatcher mocks
        $this->eventDispatcher->shouldReceive('dispatch')->andReturnNull()->byDefault();

        // Cache mocks
        $this->cache->shouldReceive('has')->andReturn(false)->byDefault();
        $this->cache->shouldReceive('get')->andReturnNull()->byDefault();
        $this->cache->shouldReceive('set')->andReturn(true)->byDefault();
        $this->cache->shouldReceive('delete')->andReturn(true)->byDefault();

        // HTTP Client mocks
        $response = Mockery::mock();
        $response->shouldReceive('isSuccessful')->andReturn(true)->byDefault();
        $response->shouldReceive('getData')->andReturn([])->byDefault();
        $response->shouldReceive('getStatusCode')->andReturn(200)->byDefault();

        $this->httpClient->shouldReceive('get')->andReturn($response)->byDefault();
        $this->httpClient->shouldReceive('post')->andReturn($response)->byDefault();
        $this->httpClient->shouldReceive('put')->andReturn($response)->byDefault();
        $this->httpClient->shouldReceive('patch')->andReturn($response)->byDefault();
        $this->httpClient->shouldReceive('delete')->andReturn($response)->byDefault();
    }
}