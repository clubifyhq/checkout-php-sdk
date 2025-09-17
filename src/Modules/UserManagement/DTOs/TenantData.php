<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de tenant (organização)
 *
 * Representa uma organização/tenant no sistema multi-tenant,
 * incluindo configurações de segurança e domínios customizados.
 */
class TenantData extends BaseData
{
    public string $id;
    public string $name;
    public string $slug;
    public ?string $description = null;
    public string $status = 'active';
    public array $domains = [];
    public array $settings = [];
    public array $features = [];
    public string $plan = 'basic';
    public ?DateTime $plan_expires_at = null;
    public bool $passkeys_enabled = true;
    public bool $mfa_required = false;
    public array $allowed_countries = [];
    public ?string $logo_url = null;
    public ?string $primary_color = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;
    public array $metadata = [];

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'slug' => ['required', 'string', 'min:2', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['in:active,inactive,suspended,pending'],
            'domains' => ['array'],
            'settings' => ['array'],
            'features' => ['array'],
            'plan' => ['in:basic,pro,enterprise'],
            'plan_expires_at' => ['nullable', 'date'],
            'passkeys_enabled' => ['boolean'],
            'mfa_required' => ['boolean'],
            'allowed_countries' => ['array'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['nullable', 'string', 'max:7'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
            'metadata' => ['array'],
        ];
    }

    /**
     * Verifica se o tenant está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se o plano está expirado
     */
    public function isPlanExpired(): bool
    {
        if (!$this->plan_expires_at) {
            return false;
        }
        
        return $this->plan_expires_at < new DateTime();
    }

    /**
     * Verifica se uma feature está habilitada
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features);
    }

    /**
     * Verifica se um domínio está configurado
     */
    public function hasDomain(string $domain): bool
    {
        return in_array($domain, array_column($this->domains, 'domain'));
    }

    /**
     * Obtém configuração específica
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Verifica se é plano enterprise
     */
    public function isEnterprise(): bool
    {
        return $this->plan === 'enterprise';
    }
}
