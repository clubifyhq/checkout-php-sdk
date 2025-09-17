<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Tenant
 *
 * Representa os dados de um tenant no sistema multi-tenant.
 * Inclui configurações de recursos, limites e isolamento.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de tenant
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class TenantData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $name = null;
    public ?string $subdomain = null;
    public ?string $status = null;
    public ?array $settings = null;
    public ?array $resource_limits = null;
    public ?array $usage_stats = null;
    public ?array $configuration = null;
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
            'subdomain' => ['string', ['min', 3], ['max', 63]],
            'status' => ['string', ['in', ['active', 'inactive', 'suspended', 'pending']]],
            'settings' => ['array'],
            'resource_limits' => ['array'],
            'usage_stats' => ['array'],
            'configuration' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém configurações do tenant
     */
    public function getSettings(): array
    {
        return $this->settings ?? [];
    }

    /**
     * Define configurações do tenant
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
     * Obtém limites de recursos
     */
    public function getResourceLimits(): array
    {
        return $this->resource_limits ?? [];
    }

    /**
     * Define limites de recursos
     */
    public function setResourceLimits(array $limits): self
    {
        $this->resource_limits = $limits;
        $this->data['resource_limits'] = $limits;
        return $this;
    }

    /**
     * Obtém um limite específico
     */
    public function getResourceLimit(string $resource, int $default = 0): int
    {
        return $this->resource_limits[$resource] ?? $default;
    }

    /**
     * Define um limite específico
     */
    public function setResourceLimit(string $resource, int $limit): self
    {
        if (!is_array($this->resource_limits)) {
            $this->resource_limits = [];
        }
        $this->resource_limits[$resource] = $limit;
        $this->data['resource_limits'] = $this->resource_limits;
        return $this;
    }

    /**
     * Obtém estatísticas de uso
     */
    public function getUsageStats(): array
    {
        return $this->usage_stats ?? [];
    }

    /**
     * Define estatísticas de uso
     */
    public function setUsageStats(array $stats): self
    {
        $this->usage_stats = $stats;
        $this->data['usage_stats'] = $stats;
        return $this;
    }

    /**
     * Obtém uso de um recurso específico
     */
    public function getResourceUsage(string $resource): int
    {
        return $this->usage_stats[$resource] ?? 0;
    }

    /**
     * Define uso de um recurso específico
     */
    public function setResourceUsage(string $resource, int $usage): self
    {
        if (!is_array($this->usage_stats)) {
            $this->usage_stats = [];
        }
        $this->usage_stats[$resource] = $usage;
        $this->data['usage_stats'] = $this->usage_stats;
        return $this;
    }

    /**
     * Verifica se está dentro dos limites para um recurso
     */
    public function isWithinLimit(string $resource): bool
    {
        $limit = $this->getResourceLimit($resource);
        $usage = $this->getResourceUsage($resource);

        return $limit === 0 || $usage <= $limit;
    }

    /**
     * Verifica se está próximo do limite (80% ou mais)
     */
    public function isNearLimit(string $resource, float $threshold = 0.8): bool
    {
        $limit = $this->getResourceLimit($resource);
        $usage = $this->getResourceUsage($resource);

        if ($limit === 0) {
            return false;
        }

        return ($usage / $limit) >= $threshold;
    }

    /**
     * Calcula percentual de uso de um recurso
     */
    public function getUsagePercentage(string $resource): float
    {
        $limit = $this->getResourceLimit($resource);
        $usage = $this->getResourceUsage($resource);

        if ($limit === 0) {
            return 0.0;
        }

        return min(100.0, ($usage / $limit) * 100);
    }

    /**
     * Obtém configuração geral do tenant
     */
    public function getConfiguration(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Define configuração geral do tenant
     */
    public function setConfiguration(array $config): self
    {
        $this->configuration = $config;
        $this->data['configuration'] = $config;
        return $this;
    }

    /**
     * Verifica se tenant está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se tenant está suspenso
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Verifica se tenant está inativo
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Verifica se tenant está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Obtém URL do subdomínio se disponível
     */
    public function getSubdomainUrl(): ?string
    {
        if (!$this->subdomain) {
            return null;
        }

        $protocol = $this->getSetting('force_https', true) ? 'https' : 'http';
        $domain = $this->getSetting('base_domain', 'checkout.clubify.com');

        return "{$protocol}://{$this->subdomain}.{$domain}";
    }

    /**
     * Verifica se subdomínio é válido
     */
    public function hasValidSubdomain(): bool
    {
        if (!$this->subdomain) {
            return false;
        }

        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $this->subdomain) === 1;
    }

    /**
     * Obtém dados para relatório de uso
     */
    public function getUsageReport(): array
    {
        $limits = $this->getResourceLimits();
        $usage = $this->getUsageStats();
        $report = [];

        foreach ($limits as $resource => $limit) {
            $currentUsage = $usage[$resource] ?? 0;
            $report[$resource] = [
                'limit' => $limit,
                'usage' => $currentUsage,
                'percentage' => $this->getUsagePercentage($resource),
                'within_limit' => $this->isWithinLimit($resource),
                'near_limit' => $this->isNearLimit($resource)
            ];
        }

        return $report;
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
            'subdomain' => $this->subdomain,
            'status' => $this->status,
            'subdomain_url' => $this->getSubdomainUrl(),
            'is_active' => $this->isActive(),
            'usage_report' => $this->getUsageReport(),
            'created_at' => $this->created_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(string $organizationId, string $name, array $additionalData = []): self
    {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'status' => 'active',
            'settings' => self::getDefaultSettings(),
            'resource_limits' => self::getDefaultResourceLimits(),
            'usage_stats' => [],
            'configuration' => []
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
     * Obtém configurações padrão
     */
    private static function getDefaultSettings(): array
    {
        return [
            'timezone' => 'America/Sao_Paulo',
            'language' => 'pt_BR',
            'currency' => 'BRL',
            'date_format' => 'd/m/Y',
            'notifications_enabled' => true,
            'api_rate_limit' => 1000,
            'storage_limit' => 1024,
            'force_https' => true,
            'base_domain' => 'checkout.clubify.com'
        ];
    }

    /**
     * Obtém limites padrão de recursos
     */
    private static function getDefaultResourceLimits(): array
    {
        return [
            'api_requests_per_hour' => 10000,
            'storage_mb' => 5120,
            'users_count' => 100,
            'products_count' => 1000,
            'orders_per_month' => 10000,
            'bandwidth_mb' => 10240,
            'webhooks_count' => 50,
            'domains_count' => 5
        ];
    }
}
