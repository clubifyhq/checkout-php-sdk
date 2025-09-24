<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de multi-tenancy
 *
 * Responsável por gerenciar tenants (inquilinos) no sistema multi-tenant:
 * - Criação e configuração de tenants
 * - Isolamento de dados por tenant
 * - Gestão de subdomínios
 * - Configurações específicas por tenant
 * - Resource allocation e limits
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de tenant
 * - O: Open/Closed - Extensível via configuration
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de tenant
 * - D: Dependency Inversion - Depende de abstrações
 */
class TenantService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'tenant';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo tenant para uma organização
     */
    public function createTenant(string $organizationId, array $tenantData): array
    {
        return $this->executeWithMetrics('create_tenant', function () use ($organizationId, $tenantData) {
            $this->validateTenantData($tenantData);

            // Preparar dados do tenant
            $data = array_merge($tenantData, [
                'organization_id' => $organizationId,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'settings' => $this->getDefaultTenantSettings()
            ]);

            // Verificar disponibilidade do subdomínio
            if (isset($data['subdomain']) && !$this->isSubdomainAvailable($data['subdomain'])) {
                throw new ValidationException("Subdomain '{$data['subdomain']}' is not available");
            }

            // Criar tenant via API
            $response = $this->makeHttpRequest('POST', '/tenants', $data);
            $tenant = ResponseHelper::getData($response);

            // Cache do tenant
            $this->cache->set($this->getCacheKey("tenant:{$tenant['id']}"), $tenant, 3600);
            $this->cache->set($this->getCacheKey("org_tenant:{$organizationId}"), $tenant, 3600);

            // Dispatch evento
            $this->dispatch('tenant.created', [
                'tenant_id' => $tenant['id'],
                'organization_id' => $organizationId,
                'subdomain' => $tenant['subdomain'] ?? null
            ]);

            $this->logger->info('Tenant created successfully', [
                'tenant_id' => $tenant['id'],
                'organization_id' => $organizationId
            ]);

            return $tenant;
        });
    }

    /**
     * Obtém dados de um tenant por ID
     */
    public function getTenant(string $tenantId): ?array
    {
        return $this->getCachedOrExecute(
            "tenant:{$tenantId}",
            fn () => $this->fetchTenantById($tenantId),
            3600
        );
    }

    /**
     * Obtém tenant por organização
     */
    public function getTenantByOrganization(string $organizationId): ?array
    {
        return $this->getCachedOrExecute(
            "org_tenant:{$organizationId}",
            fn () => $this->fetchTenantByOrganization($organizationId),
            3600
        );
    }

    /**
     * Obtém tenant por subdomínio
     */
    public function getTenantBySubdomain(string $subdomain): ?array
    {
        return $this->getCachedOrExecute(
            "subdomain_tenant:{$subdomain}",
            fn () => $this->fetchTenantBySubdomain($subdomain),
            1800
        );
    }

    /**
     * Atualiza configurações do tenant
     */
    public function updateTenantSettings(string $tenantId, array $settings): array
    {
        return $this->executeWithMetrics('update_tenant_settings', function () use ($tenantId, $settings) {
            $this->validateTenantSettings($settings);

            $response = $this->makeHttpRequest('PUT', "/tenants/{$tenantId}/settings", [
                'settings' => $settings
            ]);

            $tenant = ResponseHelper::getData($response);

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));

            // Dispatch evento
            $this->dispatch('tenant.settings_updated', [
                'tenant_id' => $tenantId,
                'settings' => $settings
            ]);

            return $tenant;
        });
    }

    /**
     * Configura limites de recursos para o tenant
     */
    public function setResourceLimits(string $tenantId, array $limits): array
    {
        return $this->executeWithMetrics('set_resource_limits', function () use ($tenantId, $limits) {
            $this->validateResourceLimits($limits);

            $response = $this->makeHttpRequest('PUT', "/tenants/{$tenantId}/limits", $limits);
            $result = ResponseHelper::getData($response);

            // Cache dos limites
            $this->cache->set($this->getCacheKey("tenant_limits:{$tenantId}"), $limits, 7200);

            // Dispatch evento
            $this->dispatch('tenant.limits_updated', [
                'tenant_id' => $tenantId,
                'limits' => $limits
            ]);

            return $result;
        });
    }

    /**
     * Obtém limites de recursos do tenant
     */
    public function getResourceLimits(string $tenantId): array
    {
        return $this->getCachedOrExecute(
            "tenant_limits:{$tenantId}",
            fn () => $this->fetchResourceLimits($tenantId),
            7200
        );
    }

    /**
     * Obtém uso atual de recursos do tenant
     */
    public function getResourceUsage(string $tenantId): array
    {
        return $this->executeWithMetrics('get_resource_usage', function () use ($tenantId) {
            $response = $this->makeHttpRequest('GET', "/tenants/{$tenantId}/usage");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Verifica se o tenant está dentro dos limites
     */
    public function isWithinLimits(string $tenantId): bool
    {
        $limits = $this->getResourceLimits($tenantId);
        $usage = $this->getResourceUsage($tenantId);

        foreach ($limits as $resource => $limit) {
            $currentUsage = $usage[$resource] ?? 0;
            if ($currentUsage > $limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ativa um tenant
     */
    public function activateTenant(string $tenantId): bool
    {
        return $this->updateTenantStatus($tenantId, 'active');
    }

    /**
     * Desativa um tenant
     */
    public function deactivateTenant(string $tenantId): bool
    {
        return $this->updateTenantStatus($tenantId, 'inactive');
    }

    /**
     * Suspende um tenant
     */
    public function suspendTenant(string $tenantId): bool
    {
        return $this->updateTenantStatus($tenantId, 'suspended');
    }

    /**
     * Verifica se um subdomínio está disponível
     */
    public function isSubdomainAvailable(string $subdomain): bool
    {
        try {
            $response = $this->makeHttpRequest('GET', "tenants/subdomain/{$subdomain}");
            $data = ResponseHelper::getData($response);
            return $data['available'] ?? false;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return true; // Se não existe, está disponível
            }
            throw $e;
        }
    }

    /**
     * Lista todos os tenants de uma organização
     */
    public function listTenantsByOrganization(string $organizationId): array
    {
        return $this->executeWithMetrics('list_tenants_by_organization', function () use ($organizationId) {
            $response = $this->makeHttpRequest('GET', "/organizations/{$organizationId}/tenants");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém estatísticas do tenant
     */
    public function getTenantStats(string $tenantId): array
    {
        return $this->executeWithMetrics('get_tenant_stats', function () use ($tenantId) {
            $response = $this->makeHttpRequest('GET', "/tenants/{$tenantId}/stats");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Busca tenant por ID via API
     */
    private function fetchTenantById(string $tenantId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/tenants/{$tenantId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca tenant por organização via API
     */
    private function fetchTenantByOrganization(string $organizationId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/organizations/{$organizationId}/tenant");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca tenant por subdomínio via API
     */
    private function fetchTenantBySubdomain(string $subdomain): ?array
    {
        try {
            return $this->makeHttpRequest('GET', "tenants/subdomain/{$subdomain}");
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca limites de recursos via API
     */
    private function fetchResourceLimits(string $tenantId): array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/tenants/{$tenantId}/limits");
            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return $this->getDefaultResourceLimits();
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do tenant
     */
    private function updateTenantStatus(string $tenantId, string $status): bool
    {
        return $this->executeWithMetrics("update_tenant_status_{$status}", function () use ($tenantId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "/tenants/{$tenantId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));

                // Dispatch evento
                $this->dispatch('tenant.status_changed', [
                    'tenant_id' => $tenantId,
                    'old_status' => 'unknown',
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update tenant status to {$status}", [
                    'tenant_id' => $tenantId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Valida dados do tenant
     */
    private function validateTenantData(array $data): void
    {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for tenant creation");
            }
        }

        if (isset($data['subdomain']) && !$this->isValidSubdomain($data['subdomain'])) {
            throw new ValidationException("Invalid subdomain format");
        }
    }

    /**
     * Valida configurações do tenant
     */
    private function validateTenantSettings(array $settings): void
    {
        $allowedSettings = [
            'timezone', 'language', 'currency', 'date_format',
            'notifications_enabled', 'api_rate_limit', 'storage_limit'
        ];

        foreach ($settings as $key => $value) {
            if (!in_array($key, $allowedSettings)) {
                throw new ValidationException("Invalid setting: {$key}");
            }
        }
    }

    /**
     * Valida limites de recursos
     */
    private function validateResourceLimits(array $limits): void
    {
        $allowedResources = [
            'api_requests_per_hour', 'storage_mb', 'users_count',
            'products_count', 'orders_per_month', 'bandwidth_mb'
        ];

        foreach ($limits as $resource => $limit) {
            if (!in_array($resource, $allowedResources)) {
                throw new ValidationException("Invalid resource: {$resource}");
            }
            if (!is_numeric($limit) || $limit < 0) {
                throw new ValidationException("Invalid limit value for {$resource}");
            }
        }
    }

    /**
     * Verifica se subdomínio é válido
     */
    private function isValidSubdomain(string $subdomain): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]{1,61}[a-z0-9]$/', $subdomain) === 1;
    }

    /**
     * Obtém configurações padrão do tenant
     */
    private function getDefaultTenantSettings(): array
    {
        return [
            'timezone' => 'America/Sao_Paulo',
            'language' => 'pt_BR',
            'currency' => 'BRL',
            'date_format' => 'd/m/Y',
            'notifications_enabled' => true,
            'api_rate_limit' => 1000,
            'storage_limit' => 1024
        ];
    }

    /**
     * Obtém limites padrão de recursos
     */
    private function getDefaultResourceLimits(): array
    {
        return [
            'api_requests_per_hour' => 10000,
            'storage_mb' => 5120,
            'users_count' => 100,
            'products_count' => 1000,
            'orders_per_month' => 10000,
            'bandwidth_mb' => 10240
        ];
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
