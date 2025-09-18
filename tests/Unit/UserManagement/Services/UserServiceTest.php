<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\UserManagement\Services;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;
use Clubify\Checkout\Modules\UserManagement\DTOs\UserData;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserNotFoundException;
use Clubify\Checkout\Modules\UserManagement\Exceptions\UserValidationException;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Logger\Logger;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para UserService
 *
 * Testa todas as funcionalidades do service incluindo:
 * - Implementação da ServiceInterface
 * - Business logic de usuários
 * - Validação de dados
 * - Error handling com exceptions específicas
 * - Integração com repository via dependency injection
 * - Health checks e métricas
 *
 * Cobertura: 100% dos métodos públicos
 */
class UserServiceTest extends TestCase
{
    private UserService $userService;
    private MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup mocks
        $this->repository = Mockery::mock(UserRepositoryInterface::class);
        $this->logger = Mockery::mock(Logger::class);

        // Setup default mock behaviors
        $this->setupDefaultMockBehaviors();

        // Create service instance
        $this->userService = new UserService($this->repository, $this->logger);
    }

    public function testImplementsServiceInterface(): void
    {
        $this->assertInstanceOf(ServiceInterface::class, $this->userService);
    }

    public function testGetName(): void
    {
        $result = $this->userService->getName();
        $this->assertEquals('user_service', $result);
    }

    public function testGetVersion(): void
    {
        $result = $this->userService->getVersion();
        $this->assertEquals('2.0.0', $result);
    }

    public function testIsHealthySuccess(): void
    {
        // Arrange
        $this->repository->shouldReceive('count')
            ->once()
            ->andReturn(5);

        // Act
        $result = $this->userService->isHealthy();

        // Assert
        $this->assertTrue($result);
    }

    public function testIsHealthyFailure(): void
    {
        // Arrange
        $this->repository->shouldReceive('count')
            ->once()
            ->andThrow(new \Exception('Database connection failed'));

        $this->logger->shouldReceive('error')
            ->with('UserService health check failed', ['error' => 'Database connection failed'])
            ->once();

        // Act
        $result = $this->userService->isHealthy();

        // Assert
        $this->assertFalse($result);
    }

    public function testGetMetrics(): void
    {
        // Arrange
        $this->repository->shouldReceive('count')
            ->once()
            ->andReturn(10);

        // Act
        $result = $this->userService->getMetrics();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('user_service', $result['service']);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertTrue($result['healthy']);
        $this->assertStringContainsString('Repository', $result['repository_type']);
        $this->assertIsInt($result['timestamp']);
    }

    public function testCreateUserSuccess(): void
    {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'securePassword123'
        ];

        $createdUser = $this->generateUserData([
            'id' => 'user_123',
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        $this->repository->shouldReceive('isEmailTaken')
            ->with('john@example.com')
            ->once()
            ->andReturn(false);

        $this->repository->shouldReceive('create')
            ->once()
            ->andReturn($createdUser);

        $this->logger->shouldReceive('info')
            ->with('Creating user', ['email' => 'john@example.com'])
            ->once();

        $this->logger->shouldReceive('info')
            ->with('User created successfully', [
                'user_id' => 'user_123',
                'email' => 'john@example.com'
            ])
            ->once();

        // Act
        $result = $this->userService->createUser($userData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('user_123', $result['user_id']);
        $this->assertEquals($createdUser, $result['user']);
    }

    public function testCreateUserDuplicateEmail(): void
    {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123'
        ];

        $this->repository->shouldReceive('isEmailTaken')
            ->with('existing@example.com')
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('info')
            ->with('Creating user', ['email' => 'existing@example.com'])
            ->once();

        $this->logger->shouldReceive('warning')
            ->with('User validation failed', Mockery::type('array'))
            ->once();

        // Act & Assert
        $this->expectException(UserValidationException::class);
        $this->expectExceptionMessage('User with this email already exists');

        $this->userService->createUser($userData);
    }

    public function testCreateUserValidationError(): void
    {
        // Arrange
        $userData = [
            'name' => '', // Invalid empty name
            'email' => 'invalid-email', // Invalid email format
            'password' => '123' // Too short password
        ];

        $this->logger->shouldReceive('info')
            ->with('Creating user', ['email' => 'invalid-email'])
            ->once();

        $this->logger->shouldReceive('warning')
            ->with('User validation failed', Mockery::type('array'))
            ->once();

        // Act & Assert
        $this->expectException(UserValidationException::class);

        $this->userService->createUser($userData);
    }

    public function testGetUserSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $userData = $this->generateUserData(['id' => $userId]);

        $this->repository->shouldReceive('findById')
            ->with($userId)
            ->once()
            ->andReturn($userData);

        // Act
        $result = $this->userService->getUser($userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userData, $result['user']);
    }

    public function testGetUserNotFound(): void
    {
        // Arrange
        $userId = 'nonexistent_user';

        $this->repository->shouldReceive('findById')
            ->with($userId)
            ->once()
            ->andReturn(null);

        $this->logger->shouldReceive('warning')
            ->with('User not found', ['user_id' => $userId])
            ->once();

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage("User with ID {$userId} not found");

        $this->userService->getUser($userId);
    }

    public function testUpdateUserSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $updateData = ['name' => 'Updated Name'];
        $updatedUser = $this->generateUserData([
            'id' => $userId,
            'name' => 'Updated Name'
        ]);

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('update')
            ->with($userId, $updateData)
            ->once()
            ->andReturn($updatedUser);

        $this->logger->shouldReceive('info')
            ->with('User updated successfully', [
                'user_id' => $userId,
                'updated_fields' => ['name']
            ])
            ->once();

        // Act
        $result = $this->userService->updateUser($userId, $updateData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals($updatedUser, $result['user']);
    }

    public function testUpdateUserNotFound(): void
    {
        // Arrange
        $userId = 'nonexistent_user';
        $updateData = ['name' => 'New Name'];

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(false);

        $this->logger->shouldReceive('warning')
            ->with('Cannot update user', Mockery::type('array'))
            ->once();

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage("User with ID {$userId} not found");

        $this->userService->updateUser($userId, $updateData);
    }

    public function testUpdateUserEmailTaken(): void
    {
        // Arrange
        $userId = 'user_123';
        $updateData = ['email' => 'taken@example.com'];

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('isEmailTaken')
            ->with('taken@example.com', $userId)
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('warning')
            ->with('Cannot update user', Mockery::type('array'))
            ->once();

        // Act & Assert
        $this->expectException(UserValidationException::class);
        $this->expectExceptionMessage('Email is already in use by another user');

        $this->userService->updateUser($userId, $updateData);
    }

    public function testDeleteUserSuccess(): void
    {
        // Arrange
        $userId = 'user_123';

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('delete')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('info')
            ->with('User deleted successfully', ['user_id' => $userId])
            ->once();

        // Act
        $result = $this->userService->deleteUser($userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertArrayHasKey('deleted_at', $result);
    }

    public function testDeleteUserNotFound(): void
    {
        // Arrange
        $userId = 'nonexistent_user';

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(false);

        $this->logger->shouldReceive('warning')
            ->with('Cannot delete user - not found', ['user_id' => $userId])
            ->once();

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage("User with ID {$userId} not found");

        $this->userService->deleteUser($userId);
    }

    public function testListUsersSuccess(): void
    {
        // Arrange
        $filters = ['status' => 'active'];
        $users = [
            $this->generateUserData(['status' => 'active']),
            $this->generateUserData(['status' => 'active'])
        ];

        $this->repository->shouldReceive('findBy')
            ->with($filters)
            ->once()
            ->andReturn(['data' => $users]);

        $this->repository->shouldReceive('count')
            ->with($filters)
            ->once()
            ->andReturn(2);

        // Act
        $result = $this->userService->listUsers($filters);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($users, $result['users']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals($filters, $result['filters']);
    }

    public function testUpdateUserProfileSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $profileData = ['bio' => 'Updated bio', 'avatar_url' => 'https://example.com/avatar.jpg'];
        $updatedProfile = array_merge($this->generateUserData(['id' => $userId]), $profileData);

        $this->repository->shouldReceive('updateProfile')
            ->with($userId, $profileData)
            ->once()
            ->andReturn($updatedProfile);

        $this->logger->shouldReceive('info')
            ->with('User profile updated successfully', [
                'user_id' => $userId,
                'updated_fields' => ['bio', 'avatar_url']
            ])
            ->once();

        // Act
        $result = $this->userService->updateUserProfile($userId, $profileData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals($updatedProfile, $result['profile']);
        $this->assertArrayHasKey('updated_at', $result);
    }

    public function testGetUserRolesSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $rolesData = [
            'roles' => ['admin', 'user'],
            'permissions' => ['read', 'write', 'delete']
        ];

        $this->repository->shouldReceive('getUserRoles')
            ->with($userId)
            ->once()
            ->andReturn($rolesData);

        // Act
        $result = $this->userService->getUserRoles($userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals(['admin', 'user'], $result['roles']);
        $this->assertEquals(['read', 'write', 'delete'], $result['permissions']);
    }

    public function testFindUserByEmailSuccess(): void
    {
        // Arrange
        $email = 'test@example.com';
        $userData = $this->generateUserData(['email' => $email]);

        $this->repository->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn($userData);

        // Act
        $result = $this->userService->findUserByEmail($email);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userData, $result['user']);
    }

    public function testFindUserByEmailNotFound(): void
    {
        // Arrange
        $email = 'notfound@example.com';

        $this->repository->shouldReceive('findByEmail')
            ->with($email)
            ->once()
            ->andReturn(null);

        $this->logger->shouldReceive('warning')
            ->with('User not found by email', ['email' => $email])
            ->once();

        // Act & Assert
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage("User with email {$email} not found");

        $this->userService->findUserByEmail($email);
    }

    public function testChangePasswordSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $newPassword = 'newSecurePassword123';

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('changePassword')
            ->with($userId, $newPassword)
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('info')
            ->with('Password changed successfully', ['user_id' => $userId])
            ->once();

        // Act
        $result = $this->userService->changePassword($userId, $newPassword);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals('Password changed successfully', $result['message']);
    }

    public function testActivateUserSuccess(): void
    {
        // Arrange
        $userId = 'user_123';

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('activateUser')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('info')
            ->with('User activated successfully', ['user_id' => $userId])
            ->once();

        // Act
        $result = $this->userService->activateUser($userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals('active', $result['status']);
    }

    public function testDeactivateUserSuccess(): void
    {
        // Arrange
        $userId = 'user_123';

        $this->repository->shouldReceive('exists')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->repository->shouldReceive('deactivateUser')
            ->with($userId)
            ->once()
            ->andReturn(true);

        $this->logger->shouldReceive('info')
            ->with('User deactivated successfully', ['user_id' => $userId])
            ->once();

        // Act
        $result = $this->userService->deactivateUser($userId);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals($userId, $result['user_id']);
        $this->assertEquals('inactive', $result['status']);
    }

    public function testGetConfig(): void
    {
        // Act
        $result = $this->userService->getConfig();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('user_service', $result['service']);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertArrayHasKey('repository', $result);
    }

    public function testIsAvailable(): void
    {
        // Arrange
        $this->repository->shouldReceive('count')
            ->once()
            ->andReturn(5);

        // Act
        $result = $this->userService->isAvailable();

        // Assert
        $this->assertTrue($result);
    }

    public function testGetStatus(): void
    {
        // Arrange
        $this->repository->shouldReceive('count')
            ->once()
            ->andReturn(5);

        // Act
        $result = $this->userService->getStatus();

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('user_service', $result['service']);
        $this->assertEquals('2.0.0', $result['version']);
        $this->assertTrue($result['healthy']);
        $this->assertTrue($result['available']);
        $this->assertArrayHasKey('repository', $result);
        $this->assertIsInt($result['timestamp']);
    }

    private function setupDefaultMockBehaviors(): void
    {
        // Logger mocks
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('warning')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        // Repository mocks
        $this->repository->shouldReceive('count')->andReturn(0)->byDefault();
    }
}
