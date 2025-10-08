<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Modules\UserManagement\Contracts\TenantRepositoryInterface;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Repository para operações de tenant via API
 *
 * Implementa TenantRepositoryInterface estendendo BaseRepository
 * para fornecer operações específicas de tenant/organizações com chamadas HTTP reais.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de tenants
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por outras implementações
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class ApiTenantRepository extends BaseRepository implements TenantRepositoryInterface
{
    /**
     * Obtém o endpoint base para tenants
     */
    protected function getEndpoint(): string
    {
        return 'tenants';
    }

    /**
     * Obtém o nome do recurso
     */
    protected function getResourceName(): string
    {
        return 'tenant';
    }

    /**
     * Obtém o nome do serviço para rotas
     */
    protected function getServiceName(): string
    {
        return 'user-management';
    }

    /**
     * Sobrescreve o método create para filtrar campos não permitidos pela API
     */
    public function create(array $data): array
    {
        $this->logger->info('[ApiTenantRepository] create() method called');

        // Remover campos que a API não aceita na criação
        $filteredData = $data;
        unset($filteredData['slug']);  // API gera o slug automaticamente
        unset($filteredData['status']); // API define status inicial
        unset($filteredData['created_at']); // API gera timestamp
        unset($filteredData['updated_at']); // API gera timestamp
        unset($filteredData['organization_id']); // Inferido do contexto de autenticação

        $this->logger->info('Creating tenant with filtered data', [
            'original_fields' => array_keys($data),
            'filtered_fields' => array_keys($filteredData),
            'removed_fields' => array_diff(array_keys($data), array_keys($filteredData)),
            'payload' => $filteredData
        ]);

        // Usar makeHttpRequest que retorna array ao invés de parent::create que espera ResponseInterface
        return $this->executeWithMetrics("create_{$this->getResourceName()}", function () use ($filteredData) {
            $createdData = $this->makeHttpRequest('POST', $this->getEndpoint(), $filteredData);

            // Dispatch creation event
            $this->dispatch("{$this->getResourceName()}.created", [
                'resource_id' => $createdData['id'] ?? null,
                'data' => $createdData
            ]);

            return $createdData;
        });
    }

    /**
     * Busca tenant por slug
     */
    public function findBySlug(string $slug): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenant:slug:{$slug}"),
            function () use ($slug) {
                try {
                    $data = $this->makeHttpRequest('GET', "tenants/slug/{$slug}");
                    return $data['tenant'] ?? $data;
                } catch (HttpException $e) {
                    // 404 significa que não encontrou - retornar null ao invés de lançar exceção
                    if ($e->getStatusCode() === 404) {
                        return null;
                    }
                    throw $e;
                }
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Busca tenant por domínio
     */
    public function findByDomain(string $domain): ?array
    {
        // Busca em cascata: primeiro domínio completo, depois domínio raiz
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenant:domain:{$domain}"),
            function () use ($domain) {
                // 1. Tentar primeiro com domínio completo
                $result = $this->tryFindByExactDomain($domain);
                if ($result !== null) {
                    return $result;
                }

                // 2. Se não encontrou, tentar com domínio raiz (para compatibilidade)
                $rootDomain = $this->extractRootDomain($domain);
                if ($rootDomain !== $domain) {
                    $this->logger->debug('ApiTenantRepository: Fallback to root domain', [
                        'original_domain' => $domain,
                        'root_domain' => $rootDomain
                    ]);

                    return $this->tryFindByExactDomain($rootDomain);
                }

                return null;
            },
            300
        );
    }

    /**
     * Tenta buscar tenant por domínio específico
     */
    private function tryFindByExactDomain(string $domain): ?array
    {
        try {
           
            $data = $this->makeHttpRequest('GET', "tenants/domain/{$domain}");


            // Verificar se a resposta indica sucesso
            if (isset($data['success']) && $data['success'] === false) {
                // API retornou "not found" estruturado - retornar null
                $this->logger->debug('ApiTenantRepository: API returned structured "not found"', [
                    'domain' => $domain,
                    'api_message' => $data['message'] ?? 'N/A'
                ]);
                return null;
            }

            // Priorizar 'data' depois 'tenant' para respostas de sucesso
            if (isset($data['data'])) {
                return $data['data'];
            } elseif (isset($data['tenant'])) {
                return $data['tenant'];
            } else {
                return $data;
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                $this->logger->debug('ApiTenantRepository: Tenant not found (404)', [
                    'domain' => $domain
                ]);
                return null;
            }
            throw $e;
        }
    }

    /**
     * Extrai o domínio raiz de um domínio completo
     */
    private function extractRootDomain(string $domain): string
    {
        if (preg_match('/^[^.]+\.(.+)$/', $domain, $matches)) {
            return $matches[1];
        }
        return $domain;
    }

    /**
     * Busca tenants por status
     */
    public function findByStatus(string $status): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenants:status:{$status}"),
            function () use ($status) {
                $data = $this->makeHttpRequest('GET', "tenants?" . http_build_query(['status' => $status]));
                return $data['tenants'] ?? $data['data'] ?? [];
            },
            600 // 10 minutes cache
        );
    }

    /**
     * Busca tenants por plano
     */
    public function findByPlan(string $plan): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenants:plan:{$plan}"),
            function () use ($plan) {
                $data = $this->makeHttpRequest('GET', "tenants?" . http_build_query(['plan' => $plan]));
                return $data['tenants'] ?? $data['data'] ?? [];
            },
            600 // 10 minutes cache
        );
    }

    /**
     * Atualiza configurações do tenant
     */
    public function updateSettings(string $tenantId, array $settings): array
    {
        return $this->executeWithMetrics('update_tenant_settings', function () use ($tenantId, $settings) {
            $data = $this->makeHttpRequest('PATCH', "tenants/{$tenantId}/settings", ['settings' => $settings]);

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));

            // Dispatch event
            $this->dispatch('tenant.settings.updated', [
                'tenant_id' => $tenantId,
                'settings' => $settings
            ]);

            return $data;
        });
    }

    /**
     * Adiciona domínio ao tenant
     */
    public function addDomain(string $tenantId, array $domainData): array
    {
        return $this->executeWithMetrics('add_tenant_domain', function () use ($tenantId, $domainData) {
            $data = $this->makeHttpRequest('POST', "tenants/{$tenantId}/domains", $domainData);

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));
            if (isset($domainData['domain'])) {
                $this->cache->delete($this->getCacheKey("tenant:domain:{$domainData['domain']}"));
            }

            // Dispatch event
            $this->dispatch('tenant.domain.added', [
                'tenant_id' => $tenantId,
                'domain_data' => $domainData
            ]);

            return $data;
        });
    }

    /**
     * Remove domínio do tenant
     */
    public function removeDomain(string $tenantId, string $domain): bool
    {
        return $this->executeWithMetrics('remove_tenant_domain', function () use ($tenantId, $domain) {
            $response = $this->httpClient->request('DELETE', "tenants/{$tenantId}/domains/{$domain}");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidar cache
                $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));
                $this->cache->delete($this->getCacheKey("tenant:domain:{$domain}"));

                // Dispatch event
                $this->dispatch('tenant.domain.removed', [
                    'tenant_id' => $tenantId,
                    'domain' => $domain
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Suspende tenant
     */
    public function suspend(string $tenantId, string $reason = ''): bool
    {
        return $this->executeWithMetrics('suspend_tenant', function () use ($tenantId, $reason) {
            $response = $this->httpClient->request('PUT', "tenants/{$tenantId}/suspend", [
                'json' => ['reason' => $reason]
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidar cache
                $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));

                // Dispatch event
                $this->dispatch('tenant.suspended', [
                    'tenant_id' => $tenantId,
                    'reason' => $reason
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Reativa tenant
     */
    public function reactivate(string $tenantId): bool
    {
        return $this->executeWithMetrics('reactivate_tenant', function () use ($tenantId) {
            $response = $this->httpClient->request('PUT', "tenants/{$tenantId}/activate");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidar cache
                $this->cache->delete($this->getCacheKey("tenant:{$tenantId}"));

                // Dispatch event
                $this->dispatch('tenant.reactivated', [
                    'tenant_id' => $tenantId
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Verifica se slug está disponível
     */
    public function isSlugAvailable(string $slug, ?string $excludeTenantId = null): bool
    {
        $this->logger->info('[ApiTenantRepository] isSlugAvailable called', ['slug' => $slug]);
        $tenant = $this->findBySlug($slug);
        $this->logger->info('[ApiTenantRepository] findBySlug returned', ['tenant_is_null' => $tenant === null]);

        if (!$tenant) {
            return true;
        }

        // Se temos um tenant para excluir da verificação
        if ($excludeTenantId && ($tenant['id'] ?? null) === $excludeTenantId) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se domínio está disponível
     */
    public function isDomainAvailable(string $domain, ?string $excludeTenantId = null): bool
    {
        $tenant = $this->findByDomain($domain);

        if (!$tenant) {
            return true;
        }

        // Se temos um tenant para excluir da verificação
        if ($excludeTenantId && ($tenant['id'] ?? null) === $excludeTenantId) {
            return true;
        }

        return false;
    }

    /**
     * Obtém estatísticas dos tenants
     */
    public function getTenantStats(): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenant_stats"),
            function () {
                try {
                    $data = $this->makeHttpRequest('GET', 'tenants/stats');
                    return $data['stats'] ?? $data;
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to get tenant stats', [
                        'error' => $e->getMessage()
                    ]);

                    return [
                        'total' => 0,
                        'active' => 0,
                        'inactive' => 0,
                        'suspended' => 0,
                        'by_plan' => []
                    ];
                }
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Busca tenants próximos ao vencimento do plano
     */
    public function findExpiringPlans(int $daysThreshold = 30): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("tenants:expiring:{$daysThreshold}"),
            function () use ($daysThreshold) {
                $data = $this->makeHttpRequest('GET', "tenants/expiring?" . http_build_query([
                    'days_threshold' => $daysThreshold
                ]));
                return $data['tenants'] ?? $data['data'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Cria uma organização (alias para create)
     */
    public function createOrganization(array $organizationData): array
    {
        return $this->executeWithMetrics('create_organization', function () use ($organizationData) {
            // Remover campos que a API não aceita na criação
            $filteredData = $organizationData;
            unset($filteredData['slug']);  // API gera o slug automaticamente
            unset($filteredData['status']); // API define status inicial
            unset($filteredData['created_at']); // API gera timestamp
            unset($filteredData['updated_at']); // API gera timestamp
            unset($filteredData['organization_id']); // Inferido do contexto de autenticação

            $this->logger->info('Creating tenant with filtered data', [
                'original_fields' => array_keys($organizationData),
                'filtered_fields' => array_keys($filteredData),
                'removed_fields' => array_diff(array_keys($organizationData), array_keys($filteredData))
            ]);

            $createdData = $this->makeHttpRequest('POST', 'tenants', $filteredData);


            // Extract tenant ID from nested data structure
            $tenantId = $createdData['data']['_id'] ?? $createdData['data']['id'] ?? $createdData['id'] ?? $createdData['_id'] ?? null;

            $this->logger->info("Organization created successfully", [
                'name' => $organizationData['name'] ?? 'unknown',
                'organization_id' => $tenantId ?? 'unknown'
            ]);

            // Dispatch creation event
            $this->dispatch("tenant.organization.created", [
                'resource_id' => $tenantId,
                'data' => $createdData
            ]);

            // Return the data with tenant_id for consistency
            $result = $createdData;
            if ($tenantId) {
                $result['tenant_id'] = $tenantId;
            }

            return $result;
        });
    }

    /**
     * Realiza health check específico do tenant repository
     */
    protected function performHealthCheck(): bool
    {
        try {
            // Test basic connectivity with tenant stats
            $this->getTenantStats();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Tenant repository health check failed', [
                'repository' => static::class,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $data = []): array
    {
        try {
            $options = [];
            if (!empty($data)) {
                $options['json'] = $data;
            }

            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $responseData = ResponseHelper::getData($response);
            if ($responseData === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $responseData;

        } catch (\Exception $e) {
            // Para erros 404, logar como info ao invés de erro (são esperados)
            if ($e instanceof HttpException && $e->getStatusCode() === 404) {
                $this->logger->info("HTTP request returned 404 (expected for search operations)", [
                    'method' => $method,
                    'uri' => $uri,
                    'status_code' => 404,
                    'service' => static::class
                ]);
            } else {
                $this->logger->error("HTTP request failed", [
                    'method' => $method,
                    'uri' => $uri,
                    'error' => $e->getMessage(),
                    'service' => static::class
                ]);
            }
            throw $e;
        }
    }
}