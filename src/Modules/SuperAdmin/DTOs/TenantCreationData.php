<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\SuperAdmin\DTOs;

use Clubify\Checkout\Contracts\ValidatableInterface;
use Clubify\Checkout\Exceptions\ValidationException;

/**
 * DTO para dados de criação de tenant
 */
class TenantCreationData implements ValidatableInterface
{
    public function __construct(
        public readonly string $organizationName,
        public readonly string $adminEmail,
        public readonly string $adminName,
        public readonly ?string $subdomain = null,
        public readonly ?string $customDomain = null,
        public readonly array $settings = [],
        public readonly array $features = []
    ) {
        $this->validate();
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            organizationName: $data['organization_name'] ?? '',
            adminEmail: $data['admin_email'] ?? '',
            adminName: $data['admin_name'] ?? '',
            subdomain: $data['subdomain'] ?? null,
            customDomain: $data['custom_domain'] ?? null,
            settings: $data['settings'] ?? [],
            features: $data['features'] ?? []
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'organization_name' => $this->organizationName,
            'admin_email' => $this->adminEmail,
            'admin_name' => $this->adminName,
            'subdomain' => $this->subdomain,
            'custom_domain' => $this->customDomain,
            'settings' => $this->settings,
            'features' => $this->features
        ];
    }

    /**
     * Validar dados de criação
     */
    public function validate(): void
    {
        if (empty($this->organizationName)) {
            throw new ValidationException('Organization name is required');
        }

        if (strlen($this->organizationName) < 2) {
            throw new ValidationException('Organization name must be at least 2 characters long');
        }

        if (empty($this->adminEmail)) {
            throw new ValidationException('Admin email is required');
        }

        if (!filter_var($this->adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid admin email format');
        }

        if (empty($this->adminName)) {
            throw new ValidationException('Admin name is required');
        }

        if (strlen($this->adminName) < 2) {
            throw new ValidationException('Admin name must be at least 2 characters long');
        }

        // Validar subdomain se fornecido
        if ($this->subdomain !== null) {
            if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $this->subdomain)) {
                throw new ValidationException('Invalid subdomain format');
            }
        }

        // Validar custom domain se fornecido
        if ($this->customDomain !== null) {
            if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $this->customDomain)) {
                throw new ValidationException('Invalid custom domain format');
            }
        }
    }
}