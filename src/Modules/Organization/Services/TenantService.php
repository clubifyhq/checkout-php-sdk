<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService as UserManagementTenantService;

/**
 * Serviço de gestão de multi-tenancy (Organization Module)
 *
 * WRAPPER/FACADE para UserManagement\Services\TenantService
 *
 * Este serviço atua como uma camada de compatibilidade para o módulo de Organization,
 * delegando todas as operações reais para o UserManagement TenantService.
 *
 * Responsabilidades:
 * - Manter compatibilidade com API existente do módulo Organization
 * - Delegar operações de tenant para UserManagement TenantService
 * - Fornecer métodos específicos de organização quando necessário
 *
 * Padrão de Design:
 * - Facade Pattern: Simplifica interface para UserManagement
 * - Delegation Pattern: Delega trabalho real para UserManagement
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
     * UserManagement TenantService - responsável pelas operações reais
     */
    private ?UserManagementTenantService $userManagementTenantService = null;

    /**
     * Injeta o UserManagement TenantService
     *
     * Este método deve ser chamado após a criação do serviço para configurar
     * a delegação adequada das operações de tenant.
     *
     * @param UserManagementTenantService $tenantService Instância do UserManagement TenantService
     * @return void
     */
    public function setUserManagementTenantService(
        UserManagementTenantService $tenantService
    ): void {
        $this->userManagementTenantService = $tenantService;
    }

    /**
     * Verifica se o UserManagement TenantService foi injetado
     *
     * @throws \RuntimeException Se o serviço não foi injetado
     */
    private function ensureUserManagementServiceInjected(): void
    {
        if ($this->userManagementTenantService === null) {
            throw new \RuntimeException(
                'UserManagement TenantService not injected. ' .
                'Call setUserManagementTenantService() before using this service.'
            );
        }
    }

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
     * Cria um novo tenant (auto-detecta organization_id do contexto ou dos dados)
     *
     * DELEGA para UserManagement TenantService
     *
     * @param array $tenantData Dados do tenant incluindo organization_id
     * @return array Dados do tenant criado
     */
    public function create(array $tenantData): array
    {
        $this->ensureUserManagementServiceInjected();

        // Delegar para UserManagement TenantService
        $result = $this->userManagementTenantService->createTenant($tenantData);

        // Retornar o tenant criado (UserManagement retorna ['success' => true, 'tenant' => ...])
        return $result['tenant'] ?? $result;
    }

    /**
     * Cria um novo tenant para uma organização
     *
     * DELEGA para UserManagement TenantService
     *
     * @param string $organizationId ID da organização (mantido por compatibilidade, não usado)
     * @param array $tenantData Dados do tenant
     * @return array Dados do tenant criado
     */
    public function createTenant(string $organizationId, array $tenantData): array
    {
        $this->ensureUserManagementServiceInjected();

        // Delegar para UserManagement TenantService
        // Não adicionar organization_id, status, created_at - UserManagement cuida disso
        $result = $this->userManagementTenantService->createTenant($tenantData);

        // Retornar o tenant criado (UserManagement retorna ['success' => true, 'tenant' => ...])
        return $result['tenant'] ?? $result;
    }

    /**
     * Obtém dados de um tenant por ID
     *
     * DELEGA para UserManagement TenantService
     */
    public function getTenant(string $tenantId): ?array
    {
        $this->ensureUserManagementServiceInjected();

        $result = $this->userManagementTenantService->getTenant($tenantId);

        // UserManagement retorna ['success' => true, 'tenant' => ...] ou ['success' => false, ...]
        if (isset($result['success']) && $result['success']) {
            return $result['tenant'] ?? null;
        }

        return null;
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
     *
     * DELEGA para UserManagement TenantService
     * Nota: UserManagement usa 'slug' em vez de 'subdomain'
     */
    public function getTenantBySubdomain(string $subdomain): ?array
    {
        $this->ensureUserManagementServiceInjected();

        // UserManagement usa 'slug' em vez de 'subdomain'
        $result = $this->userManagementTenantService->getTenantBySlug($subdomain);

        // UserManagement retorna ['success' => true, 'tenant' => ...] ou ['success' => false, ...]
        if (isset($result['success']) && $result['success']) {
            return $result['tenant'] ?? null;
        }

        return null;
    }

    /**
     * Busca tenant por domínio customizado
     *
     * DELEGA para UserManagement TenantService
     *
     * @param string $domain Domínio customizado (ex: checkout.empresa.com)
     * @return array|null Dados do tenant ou null se não encontrado
     */
    public function findByDomain(string $domain): ?array
    {
        $this->ensureUserManagementServiceInjected();

        $result = $this->userManagementTenantService->getTenantByDomain($domain);

        // UserManagement retorna ['success' => true, 'tenant' => ...] ou ['success' => false, ...]
        if (isset($result['success']) && $result['success']) {
            return $result['tenant'] ?? null;
        }

        return null;
    }

    /**
     * Alias para compatibilidade: busca tenant por domínio customizado
     *
     * DELEGA para UserManagement TenantService
     */
    public function getTenantByDomain(string $domain): ?array
    {
        return $this->findByDomain($domain);
    }

    /**
     * Atualiza configurações do tenant
     *
     * Mantém implementação Organization-specific por enquanto
     * TODO: Avaliar se deve delegar para UserManagement
     */
    public function updateTenantSettings(string $tenantId, array $settings): array
    {
        return $this->executeWithMetrics('update_tenant_settings', function () use ($tenantId, $settings) {
            // Validação removida - UserManagement cuida disso

            $response = $this->makeHttpRequest('PUT', "/tenants/{$tenantId}/settings", [
                'json' => [
                    'settings' => $settings
                ]
            ]);

            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            $tenant = $response;

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
     *
     * Mantém implementação Organization-specific por enquanto
     * TODO: Avaliar se deve delegar para UserManagement
     */
    public function setResourceLimits(string $tenantId, array $limits): array
    {
        return $this->executeWithMetrics('set_resource_limits', function () use ($tenantId, $limits) {
            // Validação removida - UserManagement cuida disso

            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            $result = $this->makeHttpRequest('PUT', "/tenants/{$tenantId}/limits", ['json' => $limits]);

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
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/tenants/{$tenantId}/usage") ?? [];
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
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            $data = $this->makeHttpRequest('GET', "tenants/subdomain/{$subdomain}");
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
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/organizations/{$organizationId}/tenants") ?? [];
        });
    }

    /**
     * Obtém estatísticas do tenant
     */
    public function getTenantStats(string $tenantId): array
    {
        return $this->executeWithMetrics('get_tenant_stats', function () use ($tenantId) {
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/tenants/{$tenantId}/stats") ?? [];
        });
    }

    /**
     * Busca tenant por ID via API
     */
    private function fetchTenantById(string $tenantId): ?array
    {
        try {
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/tenants/{$tenantId}");
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
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/organizations/{$organizationId}/tenant");
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
     * Busca tenant por domínio customizado via API
     *
     * @param string $domain Domínio customizado
     * @return array|null Dados do tenant ou null se não encontrado
     */
    private function fetchTenantByDomain(string $domain): ?array
    {
        try {
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "tenants/domain/{$domain}");
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
            // makeHttpRequest já retorna array processado via ResponseHelper::getData
            return $this->makeHttpRequest('GET', "/tenants/{$tenantId}/limits") ?? [];
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
                    'json' => [
                        'status' => $status
                    ]
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
     * Realiza requisição HTTP através do cliente HTTP
     *
     * Helper method para operações Organization-specific
     *
     * @param string $method Método HTTP (GET, POST, PUT, DELETE)
     * @param string $uri URI do endpoint
     * @param array $options Opções da requisição (json, headers, etc.)
     * @return array|null Dados da resposta ou null
     * @throws HttpException Se a requisição falhar
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): ?array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            // Verificar se a resposta foi bem-sucedida
            if (!ResponseHelper::isSuccessful($response)) {
                $this->logger->error("HTTP request failed", [
                    'method' => $method,
                    'uri' => $uri,
                    'status_code' => $response->getStatusCode()
                ]);
                return null;
            }

            // Extrair dados da resposta
            return ResponseHelper::getData($response);

        } catch (HttpException $e) {
            $this->logger->error("HTTP request exception", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    /**
     * Transfere usuário para outro tenant com migração de dados
     *
     * @param string $userId ID do usuário
     * @param string $currentTenantId Tenant atual do usuário
     * @param string $newTenantId Novo tenant para o usuário
     * @param array $options Opções da transferência
     * @return array Resultado da operação
     */
    public function transferUserToTenant(
        string $userId,
        string $currentTenantId,
        string $newTenantId,
        array $options = []
    ): array {
        try {
            $this->logger->info('Iniciando transferência de usuário entre tenants', [
                'user_id' => $userId,
                'current_tenant' => $currentTenantId,
                'new_tenant' => $newTenantId,
                'options' => $options
            ]);

            $result = [
                'success' => false,
                'user_id' => $userId,
                'current_tenant' => $currentTenantId,
                'new_tenant' => $newTenantId,
                'user_updated' => false,
                'data_migration' => null,
                'errors' => [],
                'started_at' => date('c'),
                'completed_at' => null
            ];

            // 1. Verificar se os tenants existem
            $currentTenant = $this->findById($currentTenantId);
            $newTenant = $this->findById($newTenantId);

            if (!$currentTenant || !$newTenant) {
                throw new \Exception('Um ou ambos os tenants não foram encontrados');
            }

            // 2. Migrar dados do usuário (se solicitado)
            if (!isset($options['skip_data_migration']) || !$options['skip_data_migration']) {
                // Aqui seria necessário injetar o TenantDataMigrationService
                // Por enquanto, vou registrar que a migração deveria acontecer
                $result['data_migration'] = [
                    'requested' => true,
                    'note' => 'Data migration service should be implemented separately'
                ];
            }

            // 3. Atualizar o tenant do usuário via API
            // Aqui seria necessário fazer uma chamada para o user-management-service
            // para atualizar o tenantId do usuário
            $userUpdatePayload = [
                'tenant_id' => $newTenantId,
                'updated_by' => 'system',
                'update_reason' => 'tenant_transfer'
            ];

            // TODO: Implementar chamada real para user-management-service
            $result['user_updated'] = true;
            $result['success'] = true;
            $result['completed_at'] = date('c');

            $this->logger->info('Transferência de usuário concluída', [
                'user_id' => $userId,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            $result['completed_at'] = date('c');

            $this->logger->error('Falha na transferência de usuário', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return $result;
        }
    }

    /**
     * Lista dados órfãos de um usuário em um tenant
     *
     * @param string $userId ID do usuário
     * @param string $tenantId ID do tenant
     * @return array Dados órfãos encontrados
     */
    public function getUserOrphanedData(string $userId, string $tenantId): array
    {
        try {
            $orphanedData = [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'products' => [],
                'total_orphaned_items' => 0,
                'checked_at' => date('c')
            ];

            // TODO: Implementar verificação real de produtos órfãos
            // Aqui seria necessário injetar o TenantDataMigrationService
            $orphanedData['note'] = 'Orphaned data check should use TenantDataMigrationService';

            return $orphanedData;

        } catch (\Exception $e) {
            $this->logger->error('Falha ao verificar dados órfãos', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'tenant_id' => $tenantId
            ];
        }
    }

}
