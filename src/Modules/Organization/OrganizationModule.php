<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\Organization\Services\TenantService;
use Clubify\Checkout\Modules\Organization\Services\AdminService;
use Clubify\Checkout\Modules\Organization\Services\ApiKeyService;
use Clubify\Checkout\Modules\Organization\Services\DomainService;
use Clubify\Checkout\Modules\Organization\Repositories\OrganizationRepository;

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
    private Logger $logger;
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
    public function initialize(Configuration $config, Logger $logger): void
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
     * Setup completo de uma nova organização (versão simplificada para demo)
     */
    public function setupOrganization(array $organizationData): array
    {
        $this->logger->info('Starting organization setup', $organizationData);

        try {
            // Versão simplificada para demonstração
            $organizationId = uniqid('org_');

            $result = [
                'success' => true,
                'organization' => [
                    'id' => $organizationId,
                    'name' => $organizationData['name'] ?? 'Demo Organization',
                    'slug' => strtolower(str_replace(' ', '-', $organizationData['name'] ?? 'demo')),
                    'created_at' => time(),
                    'status' => 'active'
                ],
                'tenant' => [
                    'id' => uniqid('tenant_'),
                    'organization_id' => $organizationId,
                    'subdomain' => $organizationData['subdomain'] ?? null,
                    'created_at' => time()
                ],
                'admin' => [
                    'id' => uniqid('admin_'),
                    'organization_id' => $organizationId,
                    'name' => $organizationData['admin_name'] ?? 'Demo Admin',
                    'email' => $organizationData['admin_email'] ?? 'admin@demo.com',
                    'role' => 'organization_admin',
                    'created_at' => time()
                ],
                'api_keys' => [
                    'public_key' => 'clb_live_' . uniqid(),
                    'test_key' => 'clb_test_' . uniqid(),
                    'created_at' => time()
                ],
                'domain' => !empty($organizationData['domain']) ? [
                    'domain' => $organizationData['domain'],
                    'status' => 'pending_verification',
                    'created_at' => time()
                ] : null
            ];

            $this->logger->info('Organization setup completed', [
                'organization_id' => $organizationId
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Organization setup failed', [
                'error' => $e->getMessage(),
                'data' => $organizationData
            ]);

            throw $e;
        }
    }

    /**
     * Método de conveniência para setup completo (alias para setupOrganization)
     *
     * @param array $organizationData Dados da organização
     * @return array Resultado do setup
     */
    public function setupComplete(array $organizationData): array
    {
        return $this->setupOrganization($organizationData);
    }
}