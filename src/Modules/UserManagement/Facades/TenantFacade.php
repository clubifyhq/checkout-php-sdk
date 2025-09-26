<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Facades;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiTenantRepository;
use Clubify\Checkout\Exceptions\SDKException;

/**
 * Facade para operações de tenants
 *
 * Fornece uma interface simplificada para operações de tenants,
 * gerenciando a configuração de dependências e abstraindo
 * a complexidade da criação de services e repositories.
 */
class TenantFacade
{
    private ?TenantService $tenantService = null;
    private ?ApiTenantRepository $tenantRepository = null;

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Obtém instância do TenantService
     */
    public function service(): TenantService
    {
        if ($this->tenantService === null) {
            $this->tenantService = new TenantService(
                $this->repository(),
                $this->config,
                $this->logger
            );
        }

        return $this->tenantService;
    }

    /**
     * Obtém instância do TenantRepository
     */
    public function repository(): ApiTenantRepository
    {
        if ($this->tenantRepository === null) {
            $this->tenantRepository = new ApiTenantRepository(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->tenantRepository;
    }

    /**
     * Cria um novo tenant
     */
    public function createTenant(array $tenantData): array
    {
        return $this->service()->createTenant($tenantData);
    }

    /**
     * Cria uma organização
     */
    public function createOrganization(array $organizationData): array
    {
        return $this->service()->createOrganization($organizationData);
    }

    /**
     * Obtém um tenant por ID
     */
    public function getTenant(string $tenantId): array
    {
        return $this->service()->getTenant($tenantId);
    }

    /**
     * Obtém tenant por slug
     */
    public function getTenantBySlug(string $slug): array
    {
        return $this->service()->getTenantBySlug($slug);
    }

    /**
     * Obtém tenant por domínio
     */
    public function getTenantByDomain(string $domain): array
    {
        return $this->service()->getTenantByDomain($domain);
    }

    /**
     * Atualiza um tenant
     */
    public function updateTenant(string $tenantId, array $tenantData): array
    {
        return $this->service()->updateTenant($tenantId, $tenantData);
    }

    /**
     * Lista tenants com filtros
     */
    public function listTenants(array $filters = []): array
    {
        return $this->service()->listTenants($filters);
    }

    /**
     * Suspende um tenant
     */
    public function suspendTenant(string $tenantId, string $reason = ''): array
    {
        return $this->service()->suspendTenant($tenantId, $reason);
    }

    /**
     * Reativa um tenant
     */
    public function reactivateTenant(string $tenantId): array
    {
        return $this->service()->reactivateTenant($tenantId);
    }

    /**
     * Obtém estatísticas dos tenants
     */
    public function getTenantStats(): array
    {
        return $this->service()->getTenantStats();
    }

    /**
     * Verifica se slug está disponível
     */
    public function isSlugAvailable(string $slug, ?string $excludeTenantId = null): bool
    {
        return $this->repository()->isSlugAvailable($slug, $excludeTenantId);
    }

    /**
     * Verifica se domínio está disponível
     */
    public function isDomainAvailable(string $domain, ?string $excludeTenantId = null): bool
    {
        return $this->repository()->isDomainAvailable($domain, $excludeTenantId);
    }

    /**
     * Adiciona domínio ao tenant
     */
    public function addDomain(string $tenantId, array $domainData): array
    {
        return $this->repository()->addDomain($tenantId, $domainData);
    }

    /**
     * Remove domínio do tenant
     */
    public function removeDomain(string $tenantId, string $domain): bool
    {
        return $this->repository()->removeDomain($tenantId, $domain);
    }

    /**
     * Atualiza configurações do tenant
     */
    public function updateSettings(string $tenantId, array $settings): array
    {
        return $this->repository()->updateSettings($tenantId, $settings);
    }

    /**
     * Busca tenants próximos ao vencimento do plano
     */
    public function findExpiringPlans(int $daysThreshold = 30): array
    {
        return $this->repository()->findExpiringPlans($daysThreshold);
    }

    /**
     * Busca tenants por status
     */
    public function findByStatus(string $status): array
    {
        return $this->repository()->findByStatus($status);
    }

    /**
     * Busca tenants por plano
     */
    public function findByPlan(string $plan): array
    {
        return $this->repository()->findByPlan($plan);
    }

    /**
     * Realiza health check dos componentes
     */
    public function healthCheck(): array
    {
        try {
            $repositoryHealth = $this->repository()->healthCheck();
            $serviceHealth = true; // Service não tem health check próprio

            return [
                'healthy' => $repositoryHealth && $serviceHealth,
                'components' => [
                    'repository' => $repositoryHealth,
                    'service' => $serviceHealth
                ],
                'timestamp' => time()
            ];

        } catch (\Exception $e) {
            $this->logger->error('TenantFacade health check failed', [
                'error' => $e->getMessage(),
                'facade' => static::class
            ]);

            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'timestamp' => time()
            ];
        }
    }

    /**
     * Obtém informações de status do facade
     */
    public function getStatus(): array
    {
        return [
            'facade' => static::class,
            'service_initialized' => $this->tenantService !== null,
            'repository_initialized' => $this->tenantRepository !== null,
            'dependencies' => [
                'config' => $this->config !== null,
                'logger' => $this->logger !== null,
                'http_client' => $this->httpClient !== null,
                'cache' => $this->cache !== null,
                'event_dispatcher' => $this->eventDispatcher !== null
            ],
            'timestamp' => time()
        ];
    }
}