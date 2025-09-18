<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\UserManagement;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\UserManagement\UserManagementModule;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para UserManagementModule
 *
 * Testa todas as funcionalidades do módulo incluindo:
 * - Implementação da ModuleInterface
 * - Inicialização e lifecycle do módulo
 * - Lazy loading de services
 * - Factory Pattern integration
 * - Delegação para services
 * - Health checks e métricas
 * - Error handling
 *
 * Cobertura: 100% dos métodos públicos
 */
class UserManagementModuleTest extends TestCase
{
    private UserManagementModule $module;
    private MockInterface $factory;
    private MockInterface $userService;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup mocks
        $this->sdk = Mockery::mock(ClubifyCheckoutSDK::class);
        $this->config = Mockery::mock(Configuration::class);
        $this->logger = Mockery::mock(Logger::class);
        $this->factory = Mockery::mock(UserServiceFactory::class);
        $this->userService = Mockery::mock(UserService::class);

        // Setup default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Create module instance
        $this->module = new UserManagementModule($this->sdk);
    }

    public function testImplementsModuleInterface(): void
    {
        $this->assertInstanceOf(ModuleInterface::class, $this->module);
    }

    public function testGetName(): void
    {
        $result = $this->module->getName();
        $this->assertEquals('user_management', $result);
    }

    public function testGetVersion(): void
    {
        $result = $this->module->getVersion();
        $this->assertEquals('2.0.0', $result);
    }

    public function testGetDependencies(): void
    {
        $result = $this->module->getDependencies();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testIsInitializedBeforeInitialization(): void
    {
        $result = $this->module->isInitialized();
        $this->assertFalse($result);
    }

    public function testInitialize(): void
    {
        // Arrange
        $this->config->shouldReceive('getTenantId')
            ->once()
            ->andReturn('test-tenant');

        $this->logger->shouldReceive('info')
            ->with('User Management module initialized', [
                'module' => 'user_management',
                'version' => '2.0.0',
                'tenant_id' => 'test-tenant'
            ])
            ->once();

        // Act
        $this->module->initialize($this->config, $this->logger);

        // Assert
        $this->assertTrue($this->module->isInitialized());
    }

    public function testIsAvailableAfterInitialization(): void
    {
        // Arrange
        $this->initializeModule();

        // Act
        $result = $this->module->isAvailable();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsAvailableBeforeInitialization(): void
    {
        // Act
        $result = $this->module->isAvailable();

        // Assert
        $this->assertFalse($result);
    }

    public function testGetStatus(): void
    {
        // Arrange
        $this->initializeModule();

        // Act
        $result = $this->module->getStatus();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('user_management', $result['name']);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertTrue($result['initialized']);
        $this->assertTrue($result['available']);
        $this->assertIsArray($result['services_loaded']);
        $this->assertFalse($result['services_loaded']['user']); // Not loaded yet
        $this->assertFalse($result['factory_loaded']); // Not loaded yet
        $this->assertIsInt($result['timestamp']);
    }

    public function testCleanup(): void
    {
        // Arrange
        $this->initializeModule();

        $this->logger->shouldReceive('info')
            ->with('User Management module cleaned up')
            ->once();

        // Act
        $this->module->cleanup();

        // Assert
        $this->assertFalse($this->module->isInitialized());
    }

    public function testIsHealthyWhenInitializedAndServiceHealthy(): void
    {
        // Arrange
        $this->initializeModule();

        // Act
        $result = $this->module->isHealthy();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsHealthyWhenNotInitialized(): void
    {
        // Act
        $result = $this->module->isHealthy();

        // Assert
        $this->assertFalse($result);
    }

    public function testIsHealthyWhenServiceUnhealthy(): void
    {
        // Arrange
        $this->initializeModule();

        // Load user service first by calling a method that uses it
        $this->setupUserServiceMock();
        $this->userService->shouldReceive('isHealthy')
            ->once()
            ->andReturn(false);

        // Force service loading
        $this->loadUserService();

        // Act
        $result = $this->module->isHealthy();

        // Assert
        $this->assertFalse($result);
    }

    public function testIsHealthyWithException(): void
    {
        // Arrange
        $this->initializeModule();

        // Setup service to throw exception during health check
        $this->setupUserServiceMock();
        $this->userService->shouldReceive('isHealthy')
            ->once()
            ->andThrow(new \Exception('Health check failed'));

        $this->logger->shouldReceive('error')
            ->with('UserManagementModule health check failed', ['error' => 'Health check failed'])
            ->once();

        // Force service loading
        $this->loadUserService();

        // Act
        $result = $this->module->isHealthy();

        // Assert
        $this->assertFalse($result);
    }

    public function testGetStats(): void
    {
        // Arrange
        $this->initializeModule();

        // Act
        $result = $this->module->getStats();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('user_management', $result['module']);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertTrue($result['initialized']);
        $this->assertTrue($result['healthy']);
        $this->assertIsArray($result['services']);
        $this->assertNull($result['services']['user']); // Service not loaded yet
        $this->assertNull($result['factory_stats']); // Factory not loaded yet
        $this->assertIsInt($result['timestamp']);
    }

    public function testCreateUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userData = $this->generateUserData();
        $expectedResult = [
            'success' => true,
            'user_id' => 'user_123',
            'user' => $userData
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('createUser')
            ->with($userData)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->createUser($userData);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testCreateUserRequiresInitialization(): void
    {
        // Arrange
        $userData = $this->generateUserData();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User Management module is not initialized');

        $this->module->createUser($userData);
    }

    public function testGetUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $userData = $this->generateUserData(['id' => $userId]);
        $expectedResult = [
            'success' => true,
            'user' => $userData
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('getUser')
            ->with($userId)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->getUser($userId);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testUpdateUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $updateData = ['name' => 'Updated Name'];
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'user' => $this->generateUserData(['id' => $userId, 'name' => 'Updated Name'])
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('updateUser')
            ->with($userId, $updateData)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->updateUser($userId, $updateData);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeleteUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'deleted_at' => date('c')
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('deleteUser')
            ->with($userId)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->deleteUser($userId);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testListUsers(): void
    {
        // Arrange
        $this->initializeModule();
        $filters = ['status' => 'active'];
        $expectedResult = [
            'success' => true,
            'users' => [
                $this->generateUserData(['status' => 'active']),
                $this->generateUserData(['status' => 'active'])
            ],
            'total' => 2,
            'filters' => $filters
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('listUsers')
            ->with($filters)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->listUsers($filters);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testUpdateUserProfile(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $profileData = ['bio' => 'Updated bio'];
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'profile' => $this->generateUserData(['id' => $userId, 'bio' => 'Updated bio']),
            'updated_at' => date('c')
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('updateUserProfile')
            ->with($userId, $profileData)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->updateUserProfile($userId, $profileData);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetUserRoles(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'roles' => ['admin', 'user'],
            'permissions' => ['read', 'write']
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('getUserRoles')
            ->with($userId)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->getUserRoles($userId);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testFindUserByEmail(): void
    {
        // Arrange
        $this->initializeModule();
        $email = 'test@example.com';
        $expectedResult = [
            'success' => true,
            'user' => $this->generateUserData(['email' => $email])
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('findUserByEmail')
            ->with($email)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->findUserByEmail($email);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testChangePassword(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $newPassword = 'newSecurePassword123';
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Password changed successfully'
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('changePassword')
            ->with($userId, $newPassword)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->changePassword($userId, $newPassword);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testActivateUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'status' => 'active'
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('activateUser')
            ->with($userId)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->activateUser($userId);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeactivateUser(): void
    {
        // Arrange
        $this->initializeModule();
        $userId = 'user_123';
        $expectedResult = [
            'success' => true,
            'user_id' => $userId,
            'status' => 'inactive'
        ];

        $this->setupUserServiceMock();
        $this->userService->shouldReceive('deactivateUser')
            ->with($userId)
            ->once()
            ->andReturn($expectedResult);

        // Act
        $result = $this->module->deactivateUser($userId);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    public function testLazyLoadingOfFactory(): void
    {
        // Arrange
        $this->initializeModule();
        $userData = $this->generateUserData();

        $this->sdk->shouldReceive('createUserServiceFactory')
            ->once()
            ->andReturn($this->factory);

        $this->factory->shouldReceive('create')
            ->with('user')
            ->once()
            ->andReturn($this->userService);

        $this->userService->shouldReceive('createUser')
            ->with($userData)
            ->once()
            ->andReturn(['success' => true]);

        // Act - First call should create factory
        $this->module->createUser($userData);

        // Factory should be created only once, even with multiple calls
        $this->module->createUser($userData);
    }

    public function testLazyLoadingOfUserService(): void
    {
        // Arrange
        $this->initializeModule();
        $this->setupFactoryMock();

        // Act - First call should create service
        $result1 = $this->module->createUser($this->generateUserData());
        $result2 = $this->module->getUser('user_123');

        // Service should be created only once
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
    }

    private function initializeModule(): void
    {
        $this->config->shouldReceive('getTenantId')
            ->andReturn('test-tenant')
            ->byDefault();

        $this->logger->shouldReceive('info')
            ->andReturnNull()
            ->byDefault();

        $this->module->initialize($this->config, $this->logger);
    }

    private function setupFactoryMock(): void
    {
        $this->sdk->shouldReceive('createUserServiceFactory')
            ->once()
            ->andReturn($this->factory);

        $this->factory->shouldReceive('create')
            ->with('user')
            ->once()
            ->andReturn($this->userService);
    }

    private function setupUserServiceMock(): void
    {
        $this->setupFactoryMock();

        // Setup default service responses
        $this->userService->shouldReceive('createUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('getUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('updateUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('deleteUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('listUsers')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('updateUserProfile')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('getUserRoles')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('findUserByEmail')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('changePassword')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('activateUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('deactivateUser')
            ->andReturn(['success' => true])
            ->byDefault();

        $this->userService->shouldReceive('isHealthy')
            ->andReturn(true)
            ->byDefault();
    }

    private function loadUserService(): void
    {
        // Call a method that forces user service loading
        try {
            $this->module->createUser($this->generateUserData());
        } catch (\Exception $e) {
            // Ignore exceptions, we just want to force service loading
        }
    }

    private function setupDefaultMockBehaviors(): void
    {
        // Logger mocks
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        // Config mocks
        $this->config->shouldReceive('getTenantId')->andReturn('test-tenant')->byDefault();
    }
}
