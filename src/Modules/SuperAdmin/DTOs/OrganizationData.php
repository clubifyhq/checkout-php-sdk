<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\SuperAdmin\DTOs;

use Clubify\Checkout\Contracts\ValidatableInterface;
use Clubify\Checkout\Exceptions\ValidationException;

/**
 * DTO para dados de organização
 */
class OrganizationData implements ValidatableInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly string $industry,
        public readonly string $country,
        public readonly ?string $website = null,
        public readonly ?string $logo = null,
        public readonly array $contacts = [],
        public readonly array $billing = [],
        public readonly array $preferences = []
    ) {
        $this->validate();
    }

    /**
     * Criar a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            slug: $data['slug'] ?? '',
            description: $data['description'] ?? '',
            industry: $data['industry'] ?? '',
            country: $data['country'] ?? '',
            website: $data['website'] ?? null,
            logo: $data['logo'] ?? null,
            contacts: $data['contacts'] ?? [],
            billing: $data['billing'] ?? [],
            preferences: $data['preferences'] ?? []
        );
    }

    /**
     * Converter para array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'industry' => $this->industry,
            'country' => $this->country,
            'website' => $this->website,
            'logo' => $this->logo,
            'contacts' => $this->contacts,
            'billing' => $this->billing,
            'preferences' => $this->preferences
        ];
    }

    /**
     * Gerar slug automaticamente a partir do nome
     */
    public static function generateSlug(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');

        return $slug;
    }

    /**
     * Validar dados da organização
     */
    public function validate(): void
    {
        if (empty($this->name)) {
            throw new ValidationException('Organization name is required');
        }

        if (strlen($this->name) < 2) {
            throw new ValidationException('Organization name must be at least 2 characters long');
        }

        if (empty($this->slug)) {
            throw new ValidationException('Organization slug is required');
        }

        if (!preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $this->slug)) {
            throw new ValidationException('Invalid organization slug format');
        }

        if (empty($this->description)) {
            throw new ValidationException('Organization description is required');
        }

        if (strlen($this->description) < 10) {
            throw new ValidationException('Organization description must be at least 10 characters long');
        }

        if (empty($this->industry)) {
            throw new ValidationException('Organization industry is required');
        }

        if (empty($this->country)) {
            throw new ValidationException('Organization country is required');
        }

        if (strlen($this->country) !== 2) {
            throw new ValidationException('Country must be a 2-letter ISO code');
        }

        // Validar website se fornecido
        if ($this->website !== null && !filter_var($this->website, FILTER_VALIDATE_URL)) {
            throw new ValidationException('Invalid website URL format');
        }

        // Validar contatos se fornecidos
        if (!empty($this->contacts)) {
            foreach ($this->contacts as $contact) {
                if (isset($contact['email']) && !filter_var($contact['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new ValidationException('Invalid contact email format');
                }
            }
        }
    }
}