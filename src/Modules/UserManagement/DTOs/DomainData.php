<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de domínio
 *
 * Representa um domínio customizado no sistema multi-tenant,
 * incluindo configurações de verificação e status.
 */
class DomainData extends BaseData
{
    public string $id;
    public string $tenant_id;
    public string $domain;
    public string $status = 'pending_verification';
    public bool $verified = false;
    public ?string $verification_token = null;
    public ?string $verification_method = 'dns';
    public ?DateTime $verified_at = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;
    public array $dns_records = [];
    public array $ssl_config = [];
    public array $settings = [];
    public array $metadata = [];

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'id' => ['nullable', 'string'],
            'tenant_id' => ['required', 'string'],
            'domain' => ['required', 'string', 'max:253'],
            'status' => ['in:pending_verification,verifying,verified,failed,suspended'],
            'verified' => ['boolean'],
            'verification_token' => ['nullable', 'string', 'max:255'],
            'verification_method' => ['nullable', 'in:dns,file,email'],
            'verified_at' => ['nullable', 'date'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
            'dns_records' => ['array'],
            'ssl_config' => ['array'],
            'settings' => ['array'],
            'metadata' => ['array'],
        ];
    }

    /**
     * Verifica se o domínio está verificado
     */
    public function isVerified(): bool
    {
        return $this->verified && $this->status === 'verified';
    }

    /**
     * Verifica se o domínio está pendente de verificação
     */
    public function isPending(): bool
    {
        return $this->status === 'pending_verification';
    }

    /**
     * Verifica se a verificação falhou
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verifica se o domínio está suspenso
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Obtém configuração específica
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Obtém registro DNS específico
     */
    public function getDnsRecord(string $type): ?array
    {
        foreach ($this->dns_records as $record) {
            if ($record['type'] === $type) {
                return $record;
            }
        }
        return null;
    }

    /**
     * Verifica se SSL está habilitado
     */
    public function hasSsl(): bool
    {
        return !empty($this->ssl_config['enabled']);
    }

    /**
     * Obtém URL completa do domínio
     */
    public function getUrl(bool $secure = true): string
    {
        $protocol = $secure && $this->hasSsl() ? 'https' : 'http';
        return "{$protocol}://{$this->domain}";
    }

    /**
     * Verifica se o domínio pode ser verificado
     */
    public function canBeVerified(): bool
    {
        return in_array($this->status, ['pending_verification', 'failed']);
    }

    /**
     * Sincroniza propriedades do objeto com o array data para validação
     */
    public function syncToDataArray(): void
    {
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            if (isset($this->$name)) {
                $this->data[$name] = $this->$name;
            }
        }
    }

    /**
     * Override da validação para sincronizar dados primeiro
     */
    public function validate(): bool
    {
        $this->syncToDataArray();
        return parent::validate();
    }
}