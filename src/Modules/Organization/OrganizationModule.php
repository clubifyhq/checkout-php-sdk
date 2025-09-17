<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Organization;

use ClubifyCheckout\Contracts\ModuleInterface;
use ClubifyCheckout\Core\Config\Configuration;
use ClubifyCheckout\Core\Logger\LoggerInterface;
use ClubifyCheckout\Core\Http\Client;
use ClubifyCheckout\Core\Cache\CacheManagerInterface;
use ClubifyCheckout\Core\Events\EventDispatcherInterface;
use ClubifyCheckout\Modules\Organization\Services\TenantService;
use ClubifyCheckout\Modules\Organization\Services\AdminService;
use ClubifyCheckout\Modules\Organization\Services\ApiKeyService;
use ClubifyCheckout\Modules\Organization\Services\DomainService;
use ClubifyCheckout\Modules\Organization\Repositories\OrganizationRepository;

/**
 * Módulo de Organização
 *
 * Responsável pela gestão completa de organizações, incluindo:
 * - Setup completo de organização
 * - Tenant management (multi-tenancy)
 * - Admin user creation e gestão
 * - API key generation e validação
 * - Domain configuration customizada
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas organizações
 * - O: Open/Closed - Extensível via services
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrganizationModule implements ModuleInterface
{
    private Configuration $config;
    private LoggerInterface $logger;
    private bool $initialized = false;

    private ?OrganizationRepository $repository = null;
    private ?TenantService $tenantService = null;
    private ?AdminService $adminService = null;
    private ?ApiKeyService $apiKeyService = null;
    private ?DomainService $domainService = null;

    private Client $httpClient;
    private CacheManagerInterface $cache;
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, LoggerInterface $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Organization module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
        ]);
    }

    /**
     * Define as dependências necessárias
     */
    public function setDependencies(
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'organization';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array
    {
        return [
            'http_client' => Client::class,
            'cache' => CacheManagerInterface::class,
            'event_dispatcher' => EventDispatcherInterface::class
        ];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized &&
               $this->httpClient !== null &&
               $this->cache !== null &&
               $this->eventDispatcher !== null;
    }

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services' => [
                'tenant' => $this->tenantService !== null,
                'admin' => $this->adminService !== null,
                'api_key' => $this->apiKeyService !== null,
                'domain' => $this->domainService !== null
            ],
            'repository' => $this->repository !== null,
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->repository = null;
        $this->tenantService = null;
        $this->adminService = null;
        $this->apiKeyService = null;
        $this->domainService = null;
        $this->initialized = false;

        $this->logger->info('Organization module cleaned up');
    }

    /**
     * Obtém o repository de organizações (lazy loading)
     */
    public function getRepository(): OrganizationRepository
    {
        if ($this->repository === null) {
            $this->repository = new OrganizationRepository(
                $this->config,
                $this->logger,
                $this->httpClient
            );
        }

        return $this->repository;
    }

    /**
     * Obtém o serviço de tenant (lazy loading)
     */
    public function tenant(): TenantService
    {
        if ($this->tenantService === null) {
            $this->tenantService = new TenantService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->tenantService;
    }

    /**
     * Obtém o serviço de admin (lazy loading)
     */
    public function admin(): AdminService
    {
        if ($this->adminService === null) {
            $this->adminService = new AdminService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->adminService;
    }

    /**
     * Obtém o serviço de API keys (lazy loading)
     */
    public function apiKey(): ApiKeyService
    {
        if ($this->apiKeyService === null) {
            $this->apiKeyService = new ApiKeyService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->apiKeyService;
    }

    /**
     * Obtém o serviço de domínios (lazy loading)
     */
    public function domain(): DomainService
    {
        if ($this->domainService === null) {
            $this->domainService = new DomainService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->domainService;
    }

    /**
     * Setup completo de uma nova organização
     */
    public function setupOrganization(array $organizationData): array
    {
        $this->logger->info('Starting organization setup', $organizationData);

        try {
            // 1. Criar a organização
            $organization = $this->getRepository()->create($organizationData);

            // 2. Configurar tenant
            $tenant = $this->tenant()->createTenant($organization['id'], [
                'name' => $organizationData['name'],
                'subdomain' => $organizationData['subdomain'] ?? null
            ]);

            // 3. Criar usuário admin
            $admin = $this->admin()->createAdmin($organization['id'], [
                'name' => $organizationData['admin_name'],
                'email' => $organizationData['admin_email']
            ]);

            // 4. Gerar API keys iniciais
            $apiKeys = $this->apiKey()->generateInitialKeys($organization['id']);

            // 5. Configurar domínio se fornecido
            $domain = null;
            if (!empty($organizationData['domain'])) {
                $domain = $this->domain()->configure($organization['id'], $organizationData['domain']);
            }

            $result = [
                'organization' => $organization,
                'tenant' => $tenant,
                'admin' => $admin,
                'api_keys' => $apiKeys,
                'domain' => $domain
            ];

            $this->eventDispatcher->dispatch('organization.setup.completed', $result);

            $this->logger->info('Organization setup completed', [
                'organization_id' => $organization['id']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Organization setup failed', [
                'error' => $e->getMessage(),
                'data' => $organizationData
            ]);

            $this->eventDispatcher->dispatch('organization.setup.failed', [
                'error' => $e->getMessage(),
                'data' => $organizationData
            ]);

            throw $e;
        }
    }
}