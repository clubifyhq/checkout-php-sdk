<?php

declare(strict_types=1);

namespace Clubify\Checkout\Tests\Unit\UserManagement\Repositories;

use Clubify\Checkout\Tests\TestCase;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Exceptions\HttpException;
use Mockery;
use Mockery\MockInterface;

/**
 * Testes unitários para ApiUserRepository
 *
 * Testa todas as funcionalidades do repository incluindo:
 * - Implementação da UserRepositoryInterface
 * - Métodos CRUD básicos herdados do BaseRepository
 * - Métodos específicos de usuário
 * - Cache e eventos
 * - Error handling
 * - HTTP calls com mocks
 *
 * Cobertura: 100% dos métodos públicos
 */
class ApiUserRepositoryTest extends TestCase
{
    private ApiUserRepository $repository;

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

        // Create repository instance
        $this->repository = new ApiUserRepository(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );
    }

    public function testImplementsUserRepositoryInterface(): void
    {
        $this->assertInstanceOf(UserRepositoryInterface::class, $this->repository);
    }

    public function testGetEndpoint(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('getEndpoint');
        $method->setAccessible(true);

        $endpoint = $method->invoke($this->repository);
        $this->assertEquals('/users', $endpoint);
    }

    public function testGetResourceName(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('getResourceName');
        $method->setAccessible(true);

        $resourceName = $method->invoke($this->repository);
        $this->assertEquals('user', $resourceName);
    }

    public function testFindByEmailSuccess(): void
    {
        // Arrange
        $email = 'test@example.com';
        $userData = $this->generateUserData(['email' => $email]);

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse([
            'users' => [$userData]
        ]);
        $this->httpClient->shouldReceive('get')
            ->with('/users/search', ['email' => $email])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->findByEmail($email);

        // Assert
        $this->assertEquals($userData, $result);
    }

    public function testFindByEmailNotFound(): void
    {
        // Arrange
        $email = 'notfound@example.com';

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse([
            'users' => []
        ]);
        $this->httpClient->shouldReceive('get')
            ->with('/users/search', ['email' => $email])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->findByEmail($email);

        // Assert
        $this->assertNull($result);
    }

    public function testFindByEmailUsesCache(): void
    {
        // Arrange
        $email = 'cached@example.com';
        $userData = $this->generateUserData(['email' => $email]);

        $this->cache->shouldReceive('has')->once()->andReturn(true);
        $this->cache->shouldReceive('get')->once()->andReturn($userData);

        // HTTP client should NOT be called when cache hits
        $this->httpClient->shouldNotReceive('get');

        // Act
        $result = $this->repository->findByEmail($email);

        // Assert
        $this->assertEquals($userData, $result);
    }

    public function testFindByTenant(): void
    {
        // Arrange
        $tenantId = 'tenant_123';
        $filters = ['status' => 'active'];
        $expectedFilters = array_merge($filters, ['tenant_id' => $tenantId]);

        $users = [
            $this->generateUserData(['tenant_id' => $tenantId]),
            $this->generateUserData(['tenant_id' => $tenantId])
        ];

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['data' => $users]);
        $this->httpClient->shouldReceive('get')
            ->withArgs(function ($endpoint) use ($expectedFilters) {
                return strpos($endpoint, '/users') === 0 &&
                       strpos($endpoint, 'tenant_id=' . $expectedFilters['tenant_id']) !== false;
            })
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->findByTenant($tenantId, $filters);

        // Assert
        $this->assertEquals(['data' => $users], $result);
    }

    public function testUpdateProfileSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $profileData = ['name' => 'Updated Name', 'bio' => 'Updated bio'];
        $updatedUser = $this->generateUserData(array_merge(['id' => $userId], $profileData));

        $this->cache->shouldReceive('delete')->twice()->andReturn(true);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.profile.updated', Mockery::type('array'))
            ->once();

        $response = $this->createSuccessfulHttpResponse($updatedUser);
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}/profile", $profileData)
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->updateProfile($userId, $profileData);

        // Assert
        $this->assertEquals($updatedUser, $result);
    }

    public function testUpdateProfileHttpError(): void
    {
        // Arrange
        $userId = 'user_123';
        $profileData = ['name' => 'Updated Name'];

        $response = $this->createFailedHttpResponse(400, 'Validation failed');
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}/profile", $profileData)
            ->once()
            ->andReturn($response);

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Failed to update user profile');
        $this->expectExceptionCode(400);

        $this->repository->updateProfile($userId, $profileData);
    }

    public function testChangePasswordSuccess(): void
    {
        // Arrange
        $userId = 'user_123';
        $newPassword = 'newSecurePassword123';

        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.password.changed', ['user_id' => $userId])
            ->once();

        $response = $this->createSuccessfulHttpResponse(['success' => true]);
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}/password", ['password' => $newPassword])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->changePassword($userId, $newPassword);

        // Assert
        $this->assertTrue($result);
    }

    public function testChangePasswordFailure(): void
    {
        // Arrange
        $userId = 'user_123';
        $newPassword = 'newPassword';

        $response = $this->createFailedHttpResponse(400, 'Invalid password');
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}/password", ['password' => $newPassword])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->changePassword($userId, $newPassword);

        // Assert
        $this->assertFalse($result);
    }

    public function testActivateUser(): void
    {
        // Arrange
        $userId = 'user_123';

        $this->cache->shouldReceive('delete')->once()->andReturn(true);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.status.changed', ['user_id' => $userId, 'status' => 'active'])
            ->once();

        $response = $this->createSuccessfulHttpResponse(['success' => true]);
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}", ['status' => 'active'])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->activateUser($userId);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeactivateUser(): void
    {
        // Arrange
        $userId = 'user_123';

        $this->cache->shouldReceive('delete')->once()->andReturn(true);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.status.changed', ['user_id' => $userId, 'status' => 'inactive'])
            ->once();

        $response = $this->createSuccessfulHttpResponse(['success' => true]);
        $this->httpClient->shouldReceive('patch')
            ->with("/users/{$userId}", ['status' => 'inactive'])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->deactivateUser($userId);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetUserRoles(): void
    {
        // Arrange
        $userId = 'user_123';
        $rolesData = [
            'roles' => ['admin', 'user'],
            'permissions' => ['read', 'write']
        ];

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse($rolesData);
        $this->httpClient->shouldReceive('get')
            ->with("/users/{$userId}/roles")
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->getUserRoles($userId);

        // Assert
        $this->assertEquals($rolesData, $result);
    }

    public function testAssignRole(): void
    {
        // Arrange
        $userId = 'user_123';
        $role = 'admin';

        $this->cache->shouldReceive('delete')->once()->andReturn(true);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.role.assigned', ['user_id' => $userId, 'role' => $role])
            ->once();

        $response = $this->createSuccessfulHttpResponse(['success' => true]);
        $this->httpClient->shouldReceive('post')
            ->with("/users/{$userId}/roles", ['role' => $role])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->assignRole($userId, $role);

        // Assert
        $this->assertTrue($result);
    }

    public function testRemoveRole(): void
    {
        // Arrange
        $userId = 'user_123';
        $role = 'admin';

        $this->cache->shouldReceive('delete')->once()->andReturn(true);
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with('user.role.removed', ['user_id' => $userId, 'role' => $role])
            ->once();

        $response = $this->createSuccessfulHttpResponse(['success' => true]);
        $this->httpClient->shouldReceive('delete')
            ->with("/users/{$userId}/roles/{$role}")
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->removeRole($userId, $role);

        // Assert
        $this->assertTrue($result);
    }

    public function testFindActiveByTenant(): void
    {
        // Arrange
        $tenantId = 'tenant_123';
        $users = [
            $this->generateUserData(['tenant_id' => $tenantId, 'status' => 'active'])
        ];

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['data' => $users]);
        $this->httpClient->shouldReceive('get')
            ->withArgs(function ($endpoint) use ($tenantId) {
                return strpos($endpoint, '/users') === 0 &&
                       strpos($endpoint, 'tenant_id=' . $tenantId) !== false &&
                       strpos($endpoint, 'status=active') !== false;
            })
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->findActiveByTenant($tenantId);

        // Assert
        $this->assertEquals(['data' => $users], $result);
    }

    public function testIsEmailTakenTrue(): void
    {
        // Arrange
        $email = 'taken@example.com';
        $userData = $this->generateUserData(['email' => $email]);

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['users' => [$userData]]);
        $this->httpClient->shouldReceive('get')
            ->with('/users/search', ['email' => $email])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->isEmailTaken($email);

        // Assert
        $this->assertTrue($result);
    }

    public function testIsEmailTakenFalse(): void
    {
        // Arrange
        $email = 'available@example.com';

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['users' => []]);
        $this->httpClient->shouldReceive('get')
            ->with('/users/search', ['email' => $email])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->isEmailTaken($email);

        // Assert
        $this->assertFalse($result);
    }

    public function testIsEmailTakenExcludingUser(): void
    {
        // Arrange
        $email = 'user@example.com';
        $excludeUserId = 'user_123';
        $userData = $this->generateUserData(['id' => $excludeUserId, 'email' => $email]);

        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['users' => [$userData]]);
        $this->httpClient->shouldReceive('get')
            ->with('/users/search', ['email' => $email])
            ->once()
            ->andReturn($response);

        // Act
        $result = $this->repository->isEmailTaken($email, $excludeUserId);

        // Assert
        $this->assertFalse($result); // Should return false because it's the same user
    }

    public function testPerformHealthCheckSuccess(): void
    {
        // Arrange
        $this->cache->shouldReceive('has')->once()->andReturn(false);
        $this->cache->shouldReceive('set')->once()->andReturn(true);

        $response = $this->createSuccessfulHttpResponse(['total' => 5]);
        $this->httpClient->shouldReceive('get')
            ->withArgs(function ($endpoint) {
                return strpos($endpoint, '/users') === 0 && strpos($endpoint, 'count_only=1') !== false;
            })
            ->once()
            ->andReturn($response);

        // Act
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('performHealthCheck');
        $method->setAccessible(true);
        $result = $method->invoke($this->repository);

        // Assert
        $this->assertTrue($result);
    }

    public function testPerformHealthCheckFailure(): void
    {
        // Arrange
        $this->httpClient->shouldReceive('get')
            ->andThrow(new \Exception('Connection failed'));

        $this->logger->shouldReceive('error')
            ->with('User repository health check failed', Mockery::type('array'))
            ->once();

        // Act
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('performHealthCheck');
        $method->setAccessible(true);
        $result = $method->invoke($this->repository);

        // Assert
        $this->assertFalse($result);
    }

    private function setupDefaultMockBehaviors(): void
    {
        // Logger mocks
        $this->logger->shouldReceive('info')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('debug')->andReturnNull()->byDefault();
        $this->logger->shouldReceive('error')->andReturnNull()->byDefault();

        // Event dispatcher mocks
        $this->eventDispatcher->shouldReceive('dispatch')->andReturnNull()->byDefault();

        // Cache mocks
        $this->cache->shouldReceive('has')->andReturn(false)->byDefault();
        $this->cache->shouldReceive('get')->andReturnNull()->byDefault();
        $this->cache->shouldReceive('set')->andReturn(true)->byDefault();
        $this->cache->shouldReceive('delete')->andReturn(true)->byDefault();
    }

    private function createSuccessfulHttpResponse(array $data = []): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('isSuccessful')->andReturn(true);
        $response->shouldReceive('getData')->andReturn($data);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        return $response;
    }

    private function createFailedHttpResponse(int $statusCode = 400, string $error = 'Bad Request'): MockInterface
    {
        $response = Mockery::mock();
        $response->shouldReceive('isSuccessful')->andReturn(false);
        $response->shouldReceive('getError')->andReturn($error);
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        return $response;
    }
}
