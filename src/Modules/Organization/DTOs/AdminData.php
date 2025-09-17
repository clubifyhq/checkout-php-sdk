<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Organization\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Admin
 *
 * Representa os dados de um usuário administrador no sistema.
 * Inclui informações de autenticação, permissões e configurações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de admin
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class AdminData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $role = null;
    public ?string $status = null;
    public ?array $permissions = null;
    public ?array $settings = null;
    public ?array $profile = null;
    public ?string $last_login_at = null;
    public ?string $password_reset_required = null;
    public ?string $two_factor_enabled = null;
    public ?array $sessions = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'organization_id' => ['required', 'string', ['min', 1]],
            'name' => ['required', 'string', ['min', 2], ['max', 100]],
            'email' => ['required', 'email', ['max', 255]],
            'role' => ['required', 'string', ['in', ['super_admin', 'admin', 'editor', 'viewer']]],
            'status' => ['string', ['in', ['active', 'inactive', 'suspended', 'pending']]],
            'permissions' => ['array'],
            'settings' => ['array'],
            'profile' => ['array'],
            'last_login_at' => ['date'],
            'password_reset_required' => ['boolean'],
            'two_factor_enabled' => ['boolean'],
            'sessions' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém permissões do admin
     */
    public function getPermissions(): array
    {
        return $this->permissions ?? [];
    }

    /**
     * Define permissões do admin
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        $this->data['permissions'] = $permissions;
        return $this;
    }

    /**
     * Verifica se tem uma permissão específica
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getPermissions();
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Adiciona uma permissão
     */
    public function addPermission(string $permission): self
    {
        $permissions = $this->getPermissions();
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->setPermissions($permissions);
        }
        return $this;
    }

    /**
     * Remove uma permissão
     */
    public function removePermission(string $permission): self
    {
        $permissions = $this->getPermissions();
        $key = array_search($permission, $permissions);
        if ($key !== false) {
            unset($permissions[$key]);
            $this->setPermissions(array_values($permissions));
        }
        return $this;
    }

    /**
     * Verifica se tem uma role específica
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Verifica se é super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Verifica se é admin
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    /**
     * Verifica se é editor
     */
    public function isEditor(): bool
    {
        return $this->hasRole('editor');
    }

    /**
     * Verifica se é viewer
     */
    public function isViewer(): bool
    {
        return $this->hasRole('viewer');
    }

    /**
     * Obtém configurações do admin
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Define configurações do admin
     */
    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        $this->data['settings'] = $settings;
        return $this;
    }

    /**
     * Obtém uma configuração específica
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Define uma configuração específica
     */
    public function setSetting(string $key, mixed $value): self
    {
        if (!is_array($this->settings)) {
            $this->settings = [];
        }
        $this->settings[$key] = $value;
        $this->data['settings'] = $this->settings;
        return $this;
    }

    /**
     * Obtém dados do perfil
     */
    public function getProfile(): array
    {
        return $this->profile ?? [];
    }

    /**
     * Define dados do perfil
     */
    public function setProfile(array $profile): self
    {
        $this->profile = $profile;
        $this->data['profile'] = $profile;
        return $this;
    }

    /**
     * Obtém um dado específico do perfil
     */
    public function getProfileData(string $key, mixed $default = null): mixed
    {
        return $this->profile[$key] ?? $default;
    }

    /**
     * Define um dado específico do perfil
     */
    public function setProfileData(string $key, mixed $value): self
    {
        if (!is_array($this->profile)) {
            $this->profile = [];
        }
        $this->profile[$key] = $value;
        $this->data['profile'] = $this->profile;
        return $this;
    }

    /**
     * Verifica se admin está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se admin está inativo
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Verifica se admin está suspenso
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Verifica se admin está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se precisa trocar a senha
     */
    public function requiresPasswordReset(): bool
    {
        return $this->password_reset_required === 'true' || $this->password_reset_required === true;
    }

    /**
     * Verifica se tem two-factor habilitado
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled === 'true' || $this->two_factor_enabled === true;
    }

    /**
     * Obtém sessões ativas
     */
    public function getSessions(): array
    {
        return $this->sessions ?? [];
    }

    /**
     * Define sessões ativas
     */
    public function setSessions(array $sessions): self
    {
        $this->sessions = $sessions;
        $this->data['sessions'] = $sessions;
        return $this;
    }

    /**
     * Obtém número de sessões ativas
     */
    public function getActiveSessionsCount(): int
    {
        return count($this->getSessions());
    }

    /**
     * Verifica se teve login recente (últimas 24h)
     */
    public function hasRecentLogin(int $hours = 24): bool
    {
        if (!$this->last_login_at) {
            return false;
        }

        $lastLogin = strtotime($this->last_login_at);
        $threshold = time() - ($hours * 3600);

        return $lastLogin >= $threshold;
    }

    /**
     * Obtém tempo desde último login em horas
     */
    public function getHoursSinceLastLogin(): ?int
    {
        if (!$this->last_login_at) {
            return null;
        }

        $lastLogin = strtotime($this->last_login_at);
        return (int) floor((time() - $lastLogin) / 3600);
    }

    /**
     * Verifica se pode acessar organização específica
     */
    public function canAccessOrganization(string $organizationId): bool
    {
        return $this->organization_id === $organizationId || $this->isSuperAdmin();
    }

    /**
     * Obtém nível de acesso (numérico para comparação)
     */
    public function getAccessLevel(): int
    {
        return match ($this->role) {
            'super_admin' => 100,
            'admin' => 80,
            'editor' => 60,
            'viewer' => 40,
            default => 0
        };
    }

    /**
     * Verifica se tem nível de acesso maior ou igual ao especificado
     */
    public function hasAccessLevel(string $role): bool
    {
        $requiredLevel = match ($role) {
            'super_admin' => 100,
            'admin' => 80,
            'editor' => 60,
            'viewer' => 40,
            default => 0
        };

        return $this->getAccessLevel() >= $requiredLevel;
    }

    /**
     * Obtém dados de segurança
     */
    public function getSecurityInfo(): array
    {
        return [
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
            'password_reset_required' => $this->requiresPasswordReset(),
            'active_sessions' => $this->getActiveSessionsCount(),
            'last_login_hours_ago' => $this->getHoursSinceLastLogin(),
            'has_recent_login' => $this->hasRecentLogin(),
            'access_level' => $this->getAccessLevel()
        ];
    }

    /**
     * Obtém dados para exportação
     */
    public function toExport(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'permissions_count' => count($this->getPermissions()),
            'is_active' => $this->isActive(),
            'access_level' => $this->getAccessLevel(),
            'security_info' => $this->getSecurityInfo(),
            'created_at' => $this->created_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $organizationId,
        string $name,
        string $email,
        string $role = 'admin',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => 'active',
            'permissions' => self::getDefaultPermissions($role),
            'settings' => self::getDefaultSettings(),
            'profile' => [],
            'password_reset_required' => true,
            'two_factor_enabled' => false,
            'sessions' => []
        ], $additionalData));
    }

    /**
     * Cria instância a partir de dados da API
     */
    public static function fromApi(array $apiData): self
    {
        return new self($apiData);
    }

    /**
     * Obtém permissões padrão por role
     */
    private static function getDefaultPermissions(string $role): array
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
    private static function getDefaultSettings(): array
    {
        return [
            'language' => 'pt_BR',
            'timezone' => 'America/Sao_Paulo',
            'date_format' => 'd/m/Y',
            'notifications_email' => true,
            'notifications_dashboard' => true,
            'two_factor_enabled' => false,
            'session_timeout' => 3600,
            'theme' => 'light',
            'items_per_page' => 20
        ];
    }
}