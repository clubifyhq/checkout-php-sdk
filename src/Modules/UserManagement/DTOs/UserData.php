<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de usuário enterprise
 *
 * Representa um usuário do sistema com informações de segurança,
 * permissões e autenticação avançada.
 */
class UserData extends BaseData
{
    public string $id;
    public string $email;
    public string $name;
    public ?string $avatar_url = null;
    public string $status = 'active';
    public array $roles = [];
    public array $permissions = [];
    public ?string $tenant_id = null;
    public bool $mfa_enabled = false;
    public bool $passkey_enabled = false;
    public array $passkeys = [];
    public ?DateTime $last_login_at = null;
    public ?DateTime $password_changed_at = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;
    public array $metadata = [];
    public array $preferences = [];
    public ?string $timezone = null;
    public ?string $language = 'en';

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'avatar_url' => ['nullable', 'string', 'max:500'],
            'status' => ['in:active,inactive,suspended,pending'],
            'roles' => ['array'],
            'permissions' => ['array'],
            'tenant_id' => ['nullable', 'string'],
            'mfa_enabled' => ['boolean'],
            'passkey_enabled' => ['boolean'],
            'passkeys' => ['array'],
            'last_login_at' => ['nullable', 'date'],
            'password_changed_at' => ['nullable', 'date'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
            'metadata' => ['array'],
            'preferences' => ['array'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'language' => ['nullable', 'string', 'max:5'],
        ];
    }

    /**
     * Verifica se o usuário tem uma role específica
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * Verifica se o usuário tem uma permissão específica
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * Verifica se o usuário está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se MFA está habilitado
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfa_enabled;
    }

    /**
     * Verifica se Passkeys está habilitado
     */
    public function hasPasskeysEnabled(): bool
    {
        return $this->passkey_enabled && !empty($this->passkeys);
    }

    /**
     * Obtém dados seguros (sem informações sensíveis)
     */
    public function toSafeArray(): array
    {
        $data = $this->toArray();

        // Remover dados sensíveis
        unset($data['permissions']);
        unset($data['passkeys']);

        // Mascarar metadados sensíveis
        if (isset($data['metadata'])) {
            $data['metadata'] = $this->removeSensitiveMetadata($data['metadata']);
        }

        return $data;
    }

    /**
     * Remove metadados sensíveis
     */
    private function removeSensitiveMetadata(array $metadata): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'api_key', 'private'];

        foreach ($sensitiveKeys as $key) {
            if (isset($metadata[$key])) {
                $metadata[$key] = '[REDACTED]';
            }
        }

        return $metadata;
    }
}
