<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Repository para operações de usuário via API
 *
 * Implementa UserRepositoryInterface estendendo BaseRepository
 * para fornecer operações específicas de usuário com chamadas HTTP reais.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de usuários
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por outras implementações
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class ApiUserRepository extends BaseRepository implements UserRepositoryInterface
{
    /**
     * Obtém o endpoint base para usuários
     */
    protected function getEndpoint(): string
    {
        return 'users';
    }

    /**
     * Obtém o nome do recurso
     */
    protected function getResourceName(): string
    {
        return 'user';
    }

    /**
     * Obtém o nome do serviço para rotas
     */
    protected function getServiceName(): string
    {
        return 'user-management';
    }

    /**
     * Busca usuário por email
     */
    public function findByEmail(string $email): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("user:email:{$email}"),
            function () use ($email) {
                $response = $this->httpClient->get("users/search/advanced", ['email' => $email]);

                if (!$response->isSuccessful()) {
                    return null;
                }

                $data = $response->getData();
                return $data['users'][0] ?? $data['data'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Busca usuários por tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findBy($filters);
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateProfile(string $userId, array $profileData): array
    {
        return $this->executeWithMetrics('update_user_profile', function () use ($userId, $profileData) {
            $response = $this->httpClient->patch("users/{$userId}/profile", $profileData);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    'Failed to update user profile: ' . $response->getError(),
                    $response->getStatusCode()
                );
            }

            // Invalidate user cache
            $this->cache->delete($this->getCacheKey("user:{$userId}"));
            $this->cache->delete($this->getCacheKey("user:email:" . ($profileData['email'] ?? '')));

            $updatedData = $response->getData();

            // Dispatch profile update event
            $this->dispatch('user.profile.updated', [
                'user_id' => $userId,
                'profile_data' => $updatedData
            ]);

            return $updatedData;
        });
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(string $userId, string $newPassword): bool
    {
        return $this->executeWithMetrics('change_password', function () use ($userId, $newPassword) {
            $response = $this->httpClient->patch("users/{$userId}/password", [
                'password' => $newPassword
            ]);

            if ($response->isSuccessful()) {
                // Dispatch password change event
                $this->dispatch('user.password.changed', [
                    'user_id' => $userId
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Ativa usuário
     */
    public function activateUser(string $userId): bool
    {
        return $this->updateUserStatus($userId, 'active');
    }

    /**
     * Desativa usuário
     */
    public function deactivateUser(string $userId): bool
    {
        return $this->updateUserStatus($userId, 'inactive');
    }

    /**
     * Obtém roles do usuário
     */
    public function getUserRoles(string $userId): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("user_roles:{$userId}"),
            function () use ($userId) {
                $response = $this->httpClient->get("users/{$userId}/roles");

                if ($response->isSuccessful()) {
                    return $response->getData();
                }

                return ['roles' => [], 'permissions' => []];
            },
            600 // 10 minutes cache for roles
        );
    }

    /**
     * Atribui role ao usuário
     */
    public function assignRole(string $userId, string $role): bool
    {
        return $this->executeWithMetrics('assign_role', function () use ($userId, $role) {
            $response = $this->httpClient->post("users/{$userId}/roles", ['role' => $role]);

            if ($response->isSuccessful()) {
                // Invalidate roles cache
                $this->cache->delete($this->getCacheKey("user_roles:{$userId}"));

                // Dispatch role assignment event
                $this->dispatch('user.role.assigned', [
                    'user_id' => $userId,
                    'role' => $role
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Remove role do usuário
     */
    public function removeRole(string $userId, string $role): bool
    {
        return $this->executeWithMetrics('remove_role', function () use ($userId, $role) {
            $response = $this->httpClient->delete("users/{$userId}/roles/{$role}");

            if ($response->isSuccessful()) {
                // Invalidate roles cache
                $this->cache->delete($this->getCacheKey("user_roles:{$userId}"));

                // Dispatch role removal event
                $this->dispatch('user.role.removed', [
                    'user_id' => $userId,
                    'role' => $role
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Busca usuários ativos por tenant
     */
    public function findActiveByTenant(string $tenantId): array
    {
        return $this->findBy([
            'tenant_id' => $tenantId,
            'status' => 'active'
        ]);
    }

    /**
     * Verifica se email já está em uso
     */
    public function isEmailTaken(string $email, ?string $excludeUserId = null): bool
    {
        $user = $this->findByEmail($email);

        if (!$user) {
            return false;
        }

        // Se temos um usuário para excluir da verificação
        if ($excludeUserId && ($user['id'] ?? null) === $excludeUserId) {
            return false;
        }

        return true;
    }

    /**
     * Busca usuários por role específica
     */
    public function findByRole(string $role, ?string $tenantId = null): array
    {
        $cacheKey = $this->getCacheKey("users:role:{$role}" . ($tenantId ? ":tenant:{$tenantId}" : ''));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($role, $tenantId) {
                $filters = ['role' => $role];
                if ($tenantId) {
                    $filters['tenant_id'] = $tenantId;
                }

                $response = $this->httpClient->get("users/by-role?" . http_build_query($filters));
                return $response->isSuccessful() ? $response->getData() : [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Obtém estatísticas de usuários
     */
    public function getUserStats(?string $tenantId = null): array
    {
        $cacheKey = $this->getCacheKey("user_stats" . ($tenantId ? ":tenant:{$tenantId}" : ''));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($tenantId) {
                $filters = $tenantId ? ['tenant_id' => $tenantId] : [];
                $response = $this->httpClient->get("users/stats?" . http_build_query($filters));

                if ($response->isSuccessful()) {
                    return $response->getData();
                }

                return [
                    'total' => 0,
                    'active' => 0,
                    'inactive' => 0,
                    'by_role' => []
                ];
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Atualiza status do usuário
     */
    private function updateUserStatus(string $userId, string $status): bool
    {
        return $this->executeWithMetrics('update_user_status', function () use ($userId, $status) {
            $response = $this->httpClient->patch("users/{$userId}", ['status' => $status]);

            if ($response->isSuccessful()) {
                // Invalidate user cache
                $this->cache->delete($this->getCacheKey("user:{$userId}"));

                // Dispatch status change event
                $this->dispatch('user.status.changed', [
                    'user_id' => $userId,
                    'status' => $status
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Realiza health check específico do user repository
     */
    protected function performHealthCheck(): bool
    {
        try {
            // Test basic connectivity with user count
            $this->count();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('User repository health check failed', [
                'repository' => static::class,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}