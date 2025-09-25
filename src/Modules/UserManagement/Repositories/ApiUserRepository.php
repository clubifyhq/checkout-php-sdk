<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Core\Http\ResponseHelper;
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
    public function findByEmail(string $email, ?string $tenantId = null): ?array
    {
        $cacheKey = $tenantId
            ? "user:email:{$email}:tenant:{$tenantId}"
            : "user:email:{$email}";

        return $this->getCachedOrExecute(
            $this->getCacheKey($cacheKey),
            function () use ($email, $tenantId) {
                $params = ['email' => $email];
                if ($tenantId) {
                    $params['tenant_id'] = $tenantId;
                }

                $headers = [];
                if ($tenantId) {
                    $headers['X-Tenant-Id'] = $tenantId;
                }

                $endpoint = "users/search/advanced?" . http_build_query($params);
                $data = $this->makeHttpRequestWithHeaders('GET', $endpoint, [], $headers);
                if (!$data) {
                    return null;
                }
                else {
                    foreach($data['users'] as $user) {
                        if ($user['email'] === $email){
                            return $user;
                        }
                    }
                    return null;
                }
                //$this->logger->error("Resultado busca", [$data]);
                
                return null;
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
            $response = $this->makeHttpRequest('PATCH', "users/{$userId}/profile", $profileData);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    'Failed to update user profile',
                    $response->getStatusCode()
                );
            }

            // Invalidate user cache
            $this->cache->delete($this->getCacheKey("user:{$userId}"));
            $this->cache->delete($this->getCacheKey("user:email:" . ($profileData['email'] ?? '')));

            $updatedData = ResponseHelper::getData($response);

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
            $response = $this->makeHttpRequest('PATCH', "users/{$userId}/password", [
                'password' => $newPassword
            ]);

            if (ResponseHelper::isSuccessful($response)) {
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
                $response = $this->makeHttpRequest('GET', "users/{$userId}/roles");

                if (ResponseHelper::isSuccessful($response)) {
                    return ResponseHelper::getData($response);
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
            $response = $this->makeHttpRequest('POST', "users/{$userId}/roles", ['role' => $role]);

            if (ResponseHelper::isSuccessful($response)) {
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
            $response = $this->makeHttpRequest('DELETE', "users/{$userId}/roles/{$role}");

            if (ResponseHelper::isSuccessful($response)) {
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
    public function isEmailTaken(string $email, ?string $excludeUserId = null, ?string $tenantId = null): bool
    {
        $user = $this->findByEmail($email, $tenantId);

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

                $response = $this->makeHttpRequest('GET', "users/by-role?" . http_build_query($filters));
                return ResponseHelper::isSuccessful($response) ? ResponseHelper::getData($response) : [];
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
                $response = $this->makeHttpRequest('GET', "users/stats?" . http_build_query($filters));

                if (ResponseHelper::isSuccessful($response)) {
                    return ResponseHelper::getData($response);
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
     * Verifica se a senha está correta
     */
    public function verifyPassword(string $email, string $password): bool
    {
        return $this->executeWithMetrics('verify_password', function () use ($email, $password) {
            $response = $this->makeHttpRequest('POST', "users/verify-password", [
                'email' => $email,
                'password' => $password
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                $data = ResponseHelper::getData($response);
                return $data['valid'] ?? false;
            }

            return false;
        });
    }

    /**
     * Atualiza status do usuário
     */
    private function updateUserStatus(string $userId, string $status): bool
    {
        return $this->executeWithMetrics('update_user_status', function () use ($userId, $status) {
            $response = $this->makeHttpRequest('PATCH', "users/{$userId}", ['status' => $status]);

            if (ResponseHelper::isSuccessful($response)) {
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

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * Método para verificar resposta HTTP (compatibilidade)
     */
    protected function isSuccessfulResponse($response): bool
    {
        return ResponseHelper::isSuccessful($response);
    }

    /**
     * Método para extrair dados da resposta (compatibilidade)
     */
    protected function extractResponseData($response): ?array
    {
        return ResponseHelper::getData($response);
    }

    /**
     * Cria um novo usuário com headers customizados
     */
    public function createWithHeaders(array $data, array $headers = []): array
    {
        return $this->executeWithMetrics("create_user_with_headers", function () use ($data, $headers) {
            $response = $this->makeHttpRequestWithHeaders('POST', $this->getEndpoint(), $data, $headers);
            $this->logger->error("Resposta da criacao: ", $response);
            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to create user: " . ResponseHelper::getErrorMessage($response),
                    $response->getStatusCode()
                );
            }

            $createdData = ResponseHelper::getData($response);

            // Dispatch creation event
            $this->dispatch("user.created", [
                'resource_id' => $createdData['id'] ?? null,
                'data' => $createdData,
                'headers' => array_keys($headers)
            ]);

            return $createdData;
        });
    }

    /**
     * Método para fazer requisições HTTP com headers customizados
     */
    protected function makeHttpRequestWithHeaders(string $method, string $uri, array $data = [], array $customHeaders = []): array
    {
        try {
            $options = [];

            if (!empty($data)) {
                $options['json'] = $data;
            }

            if (!empty($customHeaders)) {
                $options['headers'] = $customHeaders;
            }

            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $responseData = ResponseHelper::getData($response);
            if ($responseData === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $responseData;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request with custom headers failed", [
                'method' => $method,
                'uri' => $uri,
                'custom_headers' => array_keys($customHeaders),
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

}
