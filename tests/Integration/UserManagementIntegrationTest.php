<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Integration;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\UserManagement\UserManagementModule;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserNotFoundException;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserValidationException;

/**
 * Testes de integração para UserManagement
 *
 * Testa a integração completa entre todos os componentes:
 * - SDK -> Module -> Factory -> Service -> Repository
 * - Ciclo de vida completo do usuário
 * - Fluxos de sucesso e erro
 * - Integração real entre componentes
 * - Dependency injection funcionando
 *
 * Usa mocks para HTTP calls mas testa integração real dos componentes
 */
class UserManagementIntegrationTest extends TestCase
{
    private UserManagementModule $userManagement;

    protected function setUp(): void
    {
        parent::setUp();

        // Create real SDK instance with test configuration
        $config = [
            'credentials' => [
                'tenant_id' => 'test_tenant_integration',
                'api_key' => 'test_api_key_integration',
                'secret_key' => 'test_secret_key_integration'
            ],
            'environment' => 'test',
            'api_url' => 'http://localhost:8080',
            'timeout' => 30,
            'retry_attempts' => 3,
            'debug' => true
        ];

        $this->sdk = new ClubifyCheckoutSDK($config);
        $this->sdk->initialize();

        $this->userManagement = $this->sdk->userManagement();
    }

    public function testFullUserLifecycleIntegration(): void
    {
        // Arrange
        $userData = [
            'name' => 'Integration Test User',
            'email' => 'integration.test.' . uniqid() . '@example.com',
            'password' => 'securePassword123',
            'role' => 'user',
            'tenant_id' => 'test_tenant_integration'
        ];

        // Setup HTTP client mock for the entire lifecycle
        $this->setupHttpClientForLifecycle($userData);

        // Act & Assert - Create user
        $createResult = $this->userManagement->createUser($userData);
        $this->assertTrue($createResult['success']);
        $this->assertArrayHasKey('user_id', $createResult);
        $userId = $createResult['user_id'];

        // Act & Assert - Get user
        $getResult = $this->userManagement->getUser($userId);
        $this->assertTrue($getResult['success']);
        $this->assertEquals($userData['email'], $getResult['user']['email']);
        $this->assertEquals($userData['name'], $getResult['user']['name']);

        // Act & Assert - Update user
        $updateData = ['name' => 'Updated Integration Test User'];
        $updateResult = $this->userManagement->updateUser($userId, $updateData);
        $this->assertTrue($updateResult['success']);
        $this->assertEquals($userId, $updateResult['user_id']);

        // Act & Assert - Update user profile
        $profileData = ['bio' => 'Integration test bio', 'avatar_url' => 'https://example.com/avatar.jpg'];
        $profileResult = $this->userManagement->updateUserProfile($userId, $profileData);
        $this->assertTrue($profileResult['success']);
        $this->assertEquals($userId, $profileResult['user_id']);

        // Act & Assert - Get user roles
        $rolesResult = $this->userManagement->getUserRoles($userId);
        $this->assertTrue($rolesResult['success']);
        $this->assertEquals($userId, $rolesResult['user_id']);
        $this->assertIsArray($rolesResult['roles']);

        // Act & Assert - Find user by email
        $findResult = $this->userManagement->findUserByEmail($userData['email']);
        $this->assertTrue($findResult['success']);
        $this->assertEquals($userData['email'], $findResult['user']['email']);

        // Act & Assert - Change password
        $passwordResult = $this->userManagement->changePassword($userId, 'newSecurePassword456');
        $this->assertTrue($passwordResult['success']);
        $this->assertEquals($userId, $passwordResult['user_id']);

        // Act & Assert - Activate user
        $activateResult = $this->userManagement->activateUser($userId);
        $this->assertTrue($activateResult['success']);
        $this->assertEquals('active', $activateResult['status']);

        // Act & Assert - Deactivate user
        $deactivateResult = $this->userManagement->deactivateUser($userId);
        $this->assertTrue($deactivateResult['success']);
        $this->assertEquals('inactive', $deactivateResult['status']);

        // Act & Assert - List users
        $listResult = $this->userManagement->listUsers(['status' => 'inactive']);
        $this->assertTrue($listResult['success']);
        $this->assertIsArray($listResult['users']);

        // Act & Assert - Delete user
        $deleteResult = $this->userManagement->deleteUser($userId);
        $this->assertTrue($deleteResult['success']);
        $this->assertEquals($userId, $deleteResult['user_id']);
        $this->assertArrayHasKey('deleted_at', $deleteResult);
    }

    public function testDependencyInjectionIntegration(): void
    {
        // Test that all components are properly injected and working together

        // Act - Get factory
        $factory = $this->sdk->createUserServiceFactory();

        // Assert - Factory is correct type
        $this->assertInstanceOf(UserServiceFactory::class, $factory);

        // Act - Create service from factory
        $service = $factory->create('user');

        // Assert - Service is correct type
        $this->assertInstanceOf(UserService::class, $service);

        // Act - Test service functionality
        $this->assertIsString($service->getName());
        $this->assertIsString($service->getVersion());
        $this->assertIsArray($service->getMetrics());
        $this->assertIsArray($service->getConfig());
        $this->assertIsBool($service->isHealthy());
        $this->assertIsBool($service->isAvailable());
        $this->assertIsArray($service->getStatus());
    }

    public function testModuleHealthAndMetrics(): void
    {
        // Act
        $isHealthy = $this->userManagement->isHealthy();
        $status = $this->userManagement->getStatus();
        $stats = $this->userManagement->getStats();

        // Assert
        $this->assertIsBool($isHealthy);
        $this->assertIsArray($status);
        $this->assertIsArray($stats);

        // Assert status structure
        $this->assertEquals('user_management', $status['name']);
        $this->assertEquals('2.0.0', $status['version']);
        $this->assertTrue($status['initialized']);
        $this->assertTrue($status['available']);
        $this->assertIsArray($status['services_loaded']);

        // Assert stats structure
        $this->assertEquals('user_management', $stats['module']);
        $this->assertEquals('2.0.0', $stats['version']);
        $this->assertTrue($stats['initialized']);
        $this->assertIsArray($stats['services']);
    }

    public function testErrorHandlingIntegration(): void
    {
        // Test UserNotFoundException
        $this->setupHttpClientForNotFound();

        $this->expectException(UserNotFoundException::class);
        $this->userManagement->getUser('nonexistent_user');
    }

    public function testValidationErrorIntegration(): void
    {
        // Test UserValidationException
        $invalidUserData = [
            'name' => '', // Invalid empty name
            'email' => 'invalid-email', // Invalid email format
            'password' => '123' // Too short password
        ];

        $this->expectException(UserValidationException::class);
        $this->userManagement->createUser($invalidUserData);
    }

    public function testCacheIntegrationFlow(): void
    {
        // Arrange
        $email = 'cache.test.' . uniqid() . '@example.com';
        $userData = $this->generateUserData(['email' => $email]);

        // Setup HTTP client to expect only one call (cache should handle subsequent calls)
        $this->setupHttpClientForCacheTest($userData);

        // Act - First call should hit HTTP, second should use cache
        $result1 = $this->userManagement->findUserByEmail($email);
        $result2 = $this->userManagement->findUserByEmail($email);

        // Assert
        $this->assertEquals($result1, $result2);
        $this->assertTrue($result1['success']);
        $this->assertEquals($email, $result1['user']['email']);
    }

    public function testLazyLoadingIntegration(): void
    {
        // Arrange - Get initial stats
        $initialStats = $this->userManagement->getStats();

        // Assert - Services not loaded initially
        $this->assertNull($initialStats['services']['user']);
        $this->assertNull($initialStats['factory_stats']);

        // Act - Call a method that forces service loading
        $userData = $this->generateUserData();
        $this->setupHttpClientForCreate($userData);
        $this->userManagement->createUser($userData);

        // Assert - Services should now be loaded
        $afterStats = $this->userManagement->getStats();
        $this->assertNotNull($afterStats['services']['user']);
        $this->assertNotNull($afterStats['factory_stats']);
    }

    public function testFactorySingletonBehavior(): void
    {
        // Act - Create multiple services of same type
        $factory = $this->sdk->createUserServiceFactory();
        $service1 = $factory->create('user');
        $service2 = $factory->create('user');

        // Assert - Should be same instance (singleton)
        $this->assertSame($service1, $service2);
    }

    public function testFactoryStats(): void
    {
        // Act
        $factory = $this->sdk->createUserServiceFactory();
        $initialStats = $factory->getStats();

        // Assert initial state
        $this->assertEquals(0, $initialStats['created_services']);
        $this->assertEquals(0, $initialStats['created_repositories']);

        // Act - Create service
        $factory->create('user');
        $afterStats = $factory->getStats();

        // Assert after creation
        $this->assertEquals(1, $afterStats['created_services']);
        $this->assertEquals(1, $afterStats['created_repositories']);
        $this->assertContains('user', $afterStats['created_service_types']);
        $this->assertContains('user', $afterStats['created_repository_types']);
    }

    public function testModuleCleanupIntegration(): void
    {
        // Arrange - Load services
        $userData = $this->generateUserData();
        $this->setupHttpClientForCreate($userData);
        $this->userManagement->createUser($userData);

        $beforeCleanup = $this->userManagement->getStats();
        $this->assertNotNull($beforeCleanup['services']['user']);

        // Act - Cleanup
        $this->userManagement->cleanup();

        // Assert - Services should be cleaned up
        $this->assertFalse($this->userManagement->isInitialized());
        $this->assertFalse($this->userManagement->isAvailable());
    }

    public function testSDKUserManagementIntegration(): void
    {
        // Test that SDK properly initializes and provides UserManagement module

        // Act
        $module = $this->sdk->userManagement();

        // Assert
        $this->assertInstanceOf(UserManagementModule::class, $module);
        $this->assertTrue($module->isInitialized());
        $this->assertTrue($module->isAvailable());
        $this->assertEquals('user_management', $module->getName());
        $this->assertEquals('2.0.0', $module->getVersion());
    }

    private function setupHttpClientForLifecycle(array $userData): void
    {
        $userId = 'user_' . uniqid();
        $createdUser = array_merge($userData, ['id' => $userId]);

        // Mock responses for the entire lifecycle
        $responses = [
            // Create user - check email not taken
            $this->createSuccessfulHttpResponse(['users' => []]),
            // Create user - actual creation
            $this->createSuccessfulHttpResponse($createdUser),
            // Get user
            $this->createSuccessfulHttpResponse($createdUser),
            // Update user
            $this->createSuccessfulHttpResponse(array_merge($createdUser, ['name' => 'Updated Integration Test User'])),
            // Update profile
            $this->createSuccessfulHttpResponse(array_merge($createdUser, ['bio' => 'Integration test bio'])),
            // Get user roles
            $this->createSuccessfulHttpResponse(['roles' => ['user'], 'permissions' => ['read']]),
            // Find by email
            $this->createSuccessfulHttpResponse(['users' => [$createdUser]]),
            // Change password
            $this->createSuccessfulHttpResponse(['success' => true]),
            // Activate user
            $this->createSuccessfulHttpResponse(['success' => true]),
            // Deactivate user
            $this->createSuccessfulHttpResponse(['success' => true]),
            // List users
            $this->createSuccessfulHttpResponse(['data' => [$createdUser]]),
            // Count users
            $this->createSuccessfulHttpResponse(['total' => 1]),
            // Delete user
            $this->createSuccessfulHttpResponse(['success' => true])
        ];

        $this->httpClient->shouldReceive('get')
            ->andReturn(...$responses)
            ->byDefault();

        $this->httpClient->shouldReceive('post')
            ->andReturn(...$responses)
            ->byDefault();

        $this->httpClient->shouldReceive('put')
            ->andReturn(...$responses)
            ->byDefault();

        $this->httpClient->shouldReceive('patch')
            ->andReturn(...$responses)
            ->byDefault();

        $this->httpClient->shouldReceive('delete')
            ->andReturn(...$responses)
            ->byDefault();
    }

    private function setupHttpClientForNotFound(): void
    {
        $response = $this->createFailedHttpResponse(404, 'User not found');

        $this->httpClient->shouldReceive('get')
            ->andReturn($response);
    }

    private function setupHttpClientForCreate(array $userData): void
    {
        $responses = [
            // Check email not taken
            $this->createSuccessfulHttpResponse(['users' => []]),
            // Create user
            $this->createSuccessfulHttpResponse(array_merge($userData, ['id' => 'user_' . uniqid()]))
        ];

        $this->httpClient->shouldReceive('get')
            ->andReturn($responses[0]);

        $this->httpClient->shouldReceive('post')
            ->andReturn($responses[1]);
    }

    private function setupHttpClientForCacheTest(array $userData): void
    {
        $response = $this->createSuccessfulHttpResponse(['users' => [$userData]]);

        // Should only be called once due to caching
        $this->httpClient->shouldReceive('get')
            ->once()
            ->andReturn($response);
    }

    private function createSuccessfulHttpResponse(array $data = []): \Mockery\MockInterface
    {
        $response = \Mockery::mock();
        $response->shouldReceive('isSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn($data);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        return $response;
    }

    private function createFailedHttpResponse(int $statusCode = 400, string $error = 'Bad Request'): \Mockery\MockInterface
    {
        $response = \Mockery::mock();
        $response->shouldReceive('isSuccessful')->andReturn(false);
        $response->shouldReceive('getError')->andReturn($error);
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        return $response;
    }
}
