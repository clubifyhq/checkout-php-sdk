<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Organização
 *
 * Representa os dados de uma organização no sistema multi-tenant.
 * Inclui informações básicas, configurações e metadados.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de organização
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrganizationData extends BaseData
{
    public ?string $id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $domain = null;
    public ?string $description = null;
    public ?string $logo_url = null;
    public ?string $website = null;
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $status = null;
    public ?string $plan = null;
    public ?string $currency = null;
    public ?string $timezone = null;
    public ?string $language = null;
    public ?array $address = null;
    public ?array $billing_info = null;
    public ?array $settings = null;
    public ?array $features = null;
    public ?array $limits = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string', ['min', 2], ['max', 255]],
            'slug' => ['string', ['min', 2], ['max', 100]],
            'domain' => ['string', ['max', 253]],
            'description' => ['string', ['max', 1000]],
            'logo_url' => ['string', ['max', 500]],
            'website' => ['string', ['max', 500]],
            'email' => ['email', ['max', 255]],
            'phone' => ['string', ['max', 20]],
            'status' => ['string', ['in', ['active', 'inactive', 'suspended', 'pending']]],
            'plan' => ['string', ['in', ['free', 'starter', 'professional', 'enterprise']]],
            'currency' => ['string', ['in', ['BRL', 'USD', 'EUR', 'GBP']]],
            'timezone' => ['string', ['max', 50]],
            'language' => ['string', ['in', ['pt_BR', 'en_US', 'es_ES', 'fr_FR']]],
            'address' => ['array'],
            'billing_info' => ['array'],
            'settings' => ['array'],
            'features' => ['array'],
            'limits' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém dados específicos do endereço
     */
    public function getAddress(): ?array
    {
        return $this->address;
    }

    /**
     * Define dados do endereço
     */
    public function setAddress(array $address): self
    {
        $this->address = $address;
        $this->data['address'] = $address;
        return $this;
    }

    /**
     * Obtém informações de cobrança
     */
    public function getBillingInfo(): ?array
    {
        return $this->billing_info;
    }

    /**
     * Define informações de cobrança
     */
    public function setBillingInfo(array $billingInfo): self
    {
        $this->billing_info = $billingInfo;
        $this->data['billing_info'] = $billingInfo;
        return $this;
    }

    /**
     * Obtém configurações da organização
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Define configurações da organização
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
     * Obtém features habilitadas
     */
    public function getFeatures(): array
    {
        return $this->features ?? [];
    }

    /**
     * Define features da organização
     */
    public function setFeatures(array $features): self
    {
        $this->features = $features;
        $this->data['features'] = $features;
        return $this;
    }

    /**
     * Verifica se uma feature está habilitada
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->getFeatures());
    }

    /**
     * Habilita uma feature
     */
    public function enableFeature(string $feature): self
    {
        $features = $this->getFeatures();
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $this->setFeatures($features);
        }
        return $this;
    }

    /**
     * Desabilita uma feature
     */
    public function disableFeature(string $feature): self
    {
        $features = $this->getFeatures();
        $key = array_search($feature, $features);
        if ($key !== false) {
            unset($features[$key]);
            $this->setFeatures(array_values($features));
        }
        return $this;
    }

    /**
     * Obtém limites da organização
     */
    public function getLimits(): array
    {
        return $this->limits ?? [];
    }

    /**
     * Define limites da organização
     */
    public function setLimits(array $limits): self
    {
        $this->limits = $limits;
        $this->data['limits'] = $limits;
        return $this;
    }

    /**
     * Obtém um limite específico
     */
    public function getLimit(string $key, int $default = 0): int
    {
        return $this->limits[$key] ?? $default;
    }

    /**
     * Define um limite específico
     */
    public function setLimit(string $key, int $value): self
    {
        if (!is_array($this->limits)) {
            $this->limits = [];
        }
        $this->limits[$key] = $value;
        $this->data['limits'] = $this->limits;
        return $this;
    }

    /**
     * Verifica se organização está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se organização está suspensa
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Obtém URL completa do domínio
     */
    public function getDomainUrl(): ?string
    {
        if (!$this->domain) {
            return null;
        }

        $protocol = $this->getSetting('force_https', true) ? 'https' : 'http';
        return "{$protocol}://{$this->domain}";
    }

    /**
     * Obtém dados para exportação
     */
    public function toExport(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'domain' => $this->domain,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'plan' => $this->plan,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'created_at' => $this->created_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(string $name, array $additionalData = []): self
    {
        return new self(array_merge([
            'name' => $name,
            'status' => 'active',
            'plan' => 'free',
            'currency' => 'BRL',
            'timezone' => 'America/Sao_Paulo',
            'language' => 'pt_BR',
            'settings' => [],
            'features' => [],
            'limits' => []
        ], $additionalData));
    }

    /**
     * Cria instância a partir de dados da API
     */
    public static function fromApi(array $apiData): self
    {
        return new self($apiData);
    }
}
