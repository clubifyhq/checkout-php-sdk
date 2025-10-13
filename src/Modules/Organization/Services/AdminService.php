<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\AuthenticationException;

/**
 * Serviço de gestão de usuários admin
 *
 * Responsável pela criação e gestão de usuários administradores:
 * - Criação de usuários admin para organizações
 * - Gestão de permissões e roles
 * - Autenticação e autorização de admins
 * - Profile management e configurações
 * - User management workflows
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de admin
 * - O: Open/Closed - Extensível via roles e permissions
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de admin
 * - D: Dependency Inversion - Depende de abstrações
 */
class AdminService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'admin';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo usuário admin para uma organização
     */
    public function createAdmin(string $organizationId, array $adminData): array
    {
        return $this->executeWithMetrics('create_admin', function () use ($organizationId, $adminData) {
            $this->validateAdminData($adminData);

            // Verificar se email já existe
            if ($this->emailExists($adminData['email'])) {
                throw new ValidationException("Email '{$adminData['email']}' is already in use");
            }

            // Preparar dados do admin
            $data = array_merge($adminData, [
                'organization_id' => $organizationId,
                'role' => $adminData['role'] ?? 'admin',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'permissions' => $this->getDefaultPermissions($adminData['role'] ?? 'admin'),
                'settings' => $this->getDefaultAdminSettings()
            ]);

            // Gerar senha temporária se não fornecida
            if (empty($data['password'])) {
                $data['password'] = $this->generateTemporaryPassword();
                $data['password_reset_required'] = true;
            }

            // Criar admin via API
            $response = $this->makeHttpRequest('POST', '/admins', $data);
            $admin = ResponseHelper::getData($response);

            // Cache do admin
            $this->cache->set($this->getCacheKey("admin:{$admin['id']}"), $admin, 3600);
            $this->cache->set($this->getCacheKey("admin_email:{$admin['email']}"), $admin, 3600);

            // Dispatch evento
            $this->dispatch('admin.created', [
                'admin_id' => $admin['id'],
                'organization_id' => $organizationId,
                'email' => $admin['email'],
                'role' => $admin['role']
            ]);

            $this->logger->info('Admin user created successfully', [
                'admin_id' => $admin['id'],
                'organization_id' => $organizationId,
                'email' => $admin['email']
            ]);

            return $admin;
        });
    }

    /**
     * Obtém dados de um admin por ID
     */
    public function getAdmin(string $adminId): ?array
    {
        return $this->getCachedOrExecute(
            "admin:{$adminId}",
            fn () => $this->fetchAdminById($adminId),
            3600
        );
    }

    /**
     * Obtém admin por email
     */
    public function getAdminByEmail(string $email): ?array
    {
        return $this->getCachedOrExecute(
            "admin_email:{$email}",
            fn () => $this->fetchAdminByEmail($email),
            3600
        );
    }

    /**
     * Lista admins de uma organização
     */
    public function getAdminsByOrganization(string $organizationId): array
    {
        return $this->executeWithMetrics('get_admins_by_organization', function () use ($organizationId) {
            $response = $this->makeHttpRequest('GET', "/organizations/{$organizationId}/admins");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Atualiza dados do admin
     */
    public function updateAdmin(string $adminId, array $data): array
    {
        return $this->executeWithMetrics('update_admin', function () use ($adminId, $data) {
            $this->validateAdminUpdateData($data);

            // Se está alterando email, verifica disponibilidade
            if (isset($data['email'])) {
                $current = $this->getAdmin($adminId);
                if ($current && $current['email'] !== $data['email'] && $this->emailExists($data['email'])) {
                    throw new ValidationException("Email '{$data['email']}' is already in use");
                }
            }

            $response = $this->makeHttpRequest('PUT', "/admins/{$adminId}", $data);
            $admin = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateAdminCache($adminId);

            // Dispatch evento
            $this->dispatch('admin.updated', [
                'admin_id' => $adminId,
                'updated_fields' => array_keys($data)
            ]);

            return $admin;
        });
    }

    /**
     * Atualiza permissões do admin
     */
    public function updatePermissions(string $adminId, array $permissions): array
    {
        return $this->executeWithMetrics('update_admin_permissions', function () use ($adminId, $permissions) {
            $this->validatePermissions($permissions);

            $response = $this->makeHttpRequest('PUT', "/admins/{$adminId}/permissions", [
                'permissions' => $permissions
            ]);

            $admin = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateAdminCache($adminId);

            // Dispatch evento
            $this->dispatch('admin.permissions_updated', [
                'admin_id' => $adminId,
                'permissions' => $permissions
            ]);

            return $admin;
        });
    }

    /**
     * Altera role do admin
     */
    public function changeRole(string $adminId, string $newRole): array
    {
        return $this->executeWithMetrics('change_admin_role', function () use ($adminId, $newRole) {
            $this->validateRole($newRole);

            $permissions = $this->getDefaultPermissions($newRole);

            $response = $this->makeHttpRequest('PUT', "/admins/{$adminId}/role", [
                'role' => $newRole,
                'permissions' => $permissions
            ]);

            $admin = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateAdminCache($adminId);

            // Dispatch evento
            $this->dispatch('admin.role_changed', [
                'admin_id' => $adminId,
                'new_role' => $newRole,
                'permissions' => $permissions
            ]);

            return $admin;
        });
    }

    /**
     * Ativa um admin
     */
    public function activateAdmin(string $adminId): bool
    {
        return $this->updateAdminStatus($adminId, 'active');
    }

    /**
     * Desativa um admin
     */
    public function deactivateAdmin(string $adminId): bool
    {
        return $this->updateAdminStatus($adminId, 'inactive');
    }

    /**
     * Suspende um admin
     */
    public function suspendAdmin(string $adminId): bool
    {
        return $this->updateAdminStatus($adminId, 'suspended');
    }

    /**
     * Força reset de senha
     */
    public function forcePasswordReset(string $adminId): bool
    {
        return $this->executeWithMetrics('force_password_reset', function () use ($adminId) {
            try {
                $response = $this->makeHttpRequest('POST', "/admins/{$adminId}/force-password-reset", []);

                // Dispatch evento
                $this->dispatch('admin.password_reset_forced', [
                    'admin_id' => $adminId
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error('Failed to force password reset', [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Envia convite para admin
     */
    public function sendInvitation(string $adminId): bool
    {
        return $this->executeWithMetrics('send_admin_invitation', function () use ($adminId) {
            try {
                $response = $this->makeHttpRequest('POST', "/admins/{$adminId}/send-invitation", []);

                // Dispatch evento
                $this->dispatch('admin.invitation_sent', [
                    'admin_id' => $adminId
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error('Failed to send admin invitation', [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Verifica se admin tem permissão específica
     */
    public function hasPermission(string $adminId, string $permission): bool
    {
        $admin = $this->getAdmin($adminId);

        if (!$admin) {
            return false;
        }

        $permissions = $admin['permissions'] ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Verifica se admin tem role específica
     */
    public function hasRole(string $adminId, string $role): bool
    {
        $admin = $this->getAdmin($adminId);

        if (!$admin) {
            return false;
        }

        return $admin['role'] === $role;
    }

    /**
     * Obtém sessions ativas do admin
     */
    public function getActiveSessions(string $adminId): array
    {
        return $this->executeWithMetrics('get_admin_active_sessions', function () use ($adminId) {
            $response = $this->makeHttpRequest('GET', "/admins/{$adminId}/sessions");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Revoga todas as sessions do admin
     */
    public function revokeAllSessions(string $adminId): bool
    {
        return $this->executeWithMetrics('revoke_admin_sessions', function () use ($adminId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "/admins/{$adminId}/sessions");

                // Dispatch evento
                $this->dispatch('admin.sessions_revoked', [
                    'admin_id' => $adminId
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error('Failed to revoke admin sessions', [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Obtém logs de atividades do admin
     */
    public function getActivityLogs(string $adminId, int $limit = 50): array
    {
        return $this->executeWithMetrics('get_admin_activity_logs', function () use ($adminId, $limit) {
            $response = $this->makeHttpRequest('GET', "/admins/{$adminId}/activity-logs", [
                'limit' => $limit
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Verifica se email já existe
     */
    public function emailExists(string $email): bool
    {
        try {
            $response = $this->makeHttpRequest('GET', "/admins/email/{$email}/exists");
            $data = ResponseHelper::getData($response);
            return $data['exists'] ?? false;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Busca admin por ID via API
     */
    private function fetchAdminById(string $adminId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/admins/{$adminId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca admin por email via API
     */
    private function fetchAdminByEmail(string $email): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/admins/email/{$email}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do admin
     */
    private function updateAdminStatus(string $adminId, string $status): bool
    {
        return $this->executeWithMetrics("update_admin_status_{$status}", function () use ($adminId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "/admins/{$adminId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateAdminCache($adminId);

                // Dispatch evento
                $this->dispatch('admin.status_changed', [
                    'admin_id' => $adminId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update admin status to {$status}", [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do admin
     */
    private function invalidateAdminCache(string $adminId): void
    {
        $admin = $this->getAdmin($adminId);

        $this->cache->delete($this->getCacheKey("admin:{$adminId}"));

        if ($admin && isset($admin['email'])) {
            $this->cache->delete($this->getCacheKey("admin_email:{$admin['email']}"));
        }
    }

    /**
     * Valida dados do admin
     */
    private function validateAdminData(array $data): void
    {
        $required = ['name', 'email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for admin creation");
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format");
        }

        if (isset($data['role'])) {
            $this->validateRole($data['role']);
        }
    }

    /**
     * Valida dados de atualização do admin
     */
    private function validateAdminUpdateData(array $data): void
    {
        if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format");
        }

        if (isset($data['role'])) {
            $this->validateRole($data['role']);
        }
    }

    /**
     * Valida role
     */
    private function validateRole(string $role): void
    {
        $allowedRoles = ['super_admin', 'admin', 'editor', 'viewer'];
        if (!in_array($role, $allowedRoles)) {
            throw new ValidationException("Invalid role: {$role}");
        }
    }

    /**
     * Valida permissões
     */
    private function validatePermissions(array $permissions): void
    {
        $allowedPermissions = [
            // Wildcards
            '*', '*:*',
            // Colon-separated permissions (preferred format)
            'organization:read', 'organization:write', 'organization:delete', 'organization:*',
            'products:read', 'products:write', 'products:delete', 'products:*',
            'orders:read', 'orders:write', 'orders:refund', 'orders:*',
            'customers:read', 'customers:write', 'customers:delete', 'customers:*',
            'payments:read', 'payments:write', 'payments:refund', 'payments:*',
            'webhooks:read', 'webhooks:write', 'webhooks:delete', 'webhooks:*',
            'analytics:read', 'analytics:*',
            'reports:read', 'reports:*',
            'settings:write', 'settings:*',
            // Dot-separated permissions (backward compatibility)
            'organization.read', 'organization.write', 'organization.delete',
            'products.read', 'products.write', 'products.delete',
            'orders.read', 'orders.write', 'orders.refund',
            'customers.read', 'customers.write', 'customers.delete',
            'payments.read', 'payments.write', 'payments.refund',
            'webhooks.read', 'webhooks.write', 'webhooks.delete',
            'analytics.read', 'reports.read', 'settings.write'
        ];

        foreach ($permissions as $permission) {
            // Check if it's a wildcard permission
            if ($this->isWildcardPermission($permission)) {
                continue;
            }

            if (!in_array($permission, $allowedPermissions)) {
                throw new ValidationException("Invalid permission: {$permission}");
            }
        }
    }

    /**
     * Verifica se a permissão é um padrão wildcard válido
     */
    private function isWildcardPermission(string $permission): bool
    {
        // Aceita '*' ou '*:*'
        if ($permission === '*' || $permission === '*:*') {
            return true;
        }

        // Aceita 'resource:*' (ex: 'checkout:*')
        if (preg_match('/^[a-z_]+:\*$/', $permission)) {
            return true;
        }

        // Aceita '*:action' (ex: '*:read')
        if (preg_match('/^\*:[a-z_]+$/', $permission)) {
            return true;
        }

        return false;
    }

    /**
     * Obtém permissões padrão por role
     */
    private function getDefaultPermissions(string $role): array
    {
        return match ($role) {
            'super_admin' => ['*'],
            'admin' => [
                'organization.read', 'organization.write',
                'products.read', 'products.write', 'products.delete',
                'orders.read', 'orders.write', 'orders.refund',
                'customers.read', 'customers.write',
                'payments.read', 'payments.write', 'payments.refund',
                'webhooks.read', 'webhooks.write',
                'analytics.read', 'reports.read', 'settings.write'
            ],
            'editor' => [
                'organization.read',
                'products.read', 'products.write',
                'orders.read', 'orders.write',
                'customers.read', 'customers.write',
                'payments.read',
                'analytics.read'
            ],
            'viewer' => [
                'organization.read',
                'products.read',
                'orders.read',
                'customers.read',
                'payments.read',
                'analytics.read'
            ],
            default => ['organization.read']
        };
    }

    /**
     * Obtém configurações padrão do admin
     */
    private function getDefaultAdminSettings(): array
    {
        return [
            'language' => 'pt_BR',
            'timezone' => 'America/Sao_Paulo',
            'date_format' => 'd/m/Y',
            'notifications_email' => true,
            'notifications_dashboard' => true,
            'two_factor_enabled' => false,
            'session_timeout' => 3600
        ];
    }

    /**
     * Gera senha temporária
     */
    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(8));
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

}
