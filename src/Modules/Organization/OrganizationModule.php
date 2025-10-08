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
use Clubify\Checkout\Modules\Organization\Services\OrganizationSetupRollbackService;
use Clubify\Checkout\Modules\Organization\Services\OrganizationSetupRetryService;
use Clubify\Checkout\Modules\Organization\Repositories\OrganizationRepository;
use Clubify\Checkout\Modules\Organization\Exceptions\OrganizationSetupException;
use Clubify\Checkout\Exceptions\ConflictException;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService as UserManagementTenantService;

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
    private ?OrganizationSetupRollbackService $rollbackService = null;
    private ?OrganizationSetupRetryService $retryService = null;
    private ?\Clubify\Checkout\Modules\Organization\Services\OrganizationApiKeyService $organizationApiKeyService = null;

    private ?Client $httpClient = null;
    private ?CacheManagerInterface $cache = null;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?UserManagementTenantService $userManagementTenantService = null;
    private ?\Clubify\Checkout\ClubifyCheckoutSDK $sdk = null;

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
     * Inject UserManagement services for delegation
     */
    public function setUserManagementServices(
        UserManagementTenantService $tenantService
    ): void {
        $this->userManagementTenantService = $tenantService;

        // Inject into existing TenantService if already created
        if ($this->tenantService !== null) {
            $this->tenantService->setUserManagementTenantService($tenantService);
        }
    }

    /**
     * Check if UserManagement services need to be injected
     */
    public function needsUserManagementInjection(): bool
    {
        return $this->userManagementTenantService === null;
    }

    /**
     * Inject SDK reference for lazy loading of services
     */
    public function setSdk(\Clubify\Checkout\ClubifyCheckoutSDK $sdk): void
    {
        $this->sdk = $sdk;
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
        if (!$this->initialized) {
            return false;
        }

        // Garante que as dependências estão disponíveis
        $this->ensureDependenciesInitialized();

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
        $this->rollbackService = null;
        $this->retryService = null;
        $this->initialized = false;

        $this->logger->info('Organization module cleaned up');
    }

    /**
     * Obtém o repository de organizações (lazy loading)
     */
    public function getRepository(): OrganizationRepository
    {
        if ($this->repository === null) {
            $this->ensureDependenciesInitialized();
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
            $this->ensureDependenciesInitialized();
            $this->tenantService = new TenantService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            // Inject UserManagement TenantService if available
            if ($this->userManagementTenantService !== null) {
                $this->tenantService->setUserManagementTenantService(
                    $this->userManagementTenantService
                );
            }
        }

        return $this->tenantService;
    }

    /**
     * Obtém o serviço de admin (lazy loading)
     */
    public function admin(): AdminService
    {
        if ($this->adminService === null) {
            $this->ensureDependenciesInitialized();
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
            $this->ensureDependenciesInitialized();
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
            $this->ensureDependenciesInitialized();
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
     * Obtém o serviço de rollback (lazy loading)
     */
    public function rollback(): OrganizationSetupRollbackService
    {
        if ($this->rollbackService === null) {
            $this->ensureDependenciesInitialized();
            $this->rollbackService = new OrganizationSetupRollbackService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->rollbackService;
    }

    /**
     * Obtém o serviço de retry (lazy loading)
     */
    public function retry(): OrganizationSetupRetryService
    {
        if ($this->retryService === null) {
            $this->ensureDependenciesInitialized();
            $this->retryService = new OrganizationSetupRetryService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->retryService;
    }

    /**
     * Obtém o serviço de Organization API Keys (lazy loading)
     */
    public function organizationApiKey(): \Clubify\Checkout\Modules\Organization\Services\OrganizationApiKeyService
    {
        if ($this->organizationApiKeyService === null) {
            $this->ensureDependenciesInitialized();
            $this->organizationApiKeyService = new \Clubify\Checkout\Modules\Organization\Services\OrganizationApiKeyService(
                $this->config,
                $this->httpClient,
                $this->logger
            );
        }

        return $this->organizationApiKeyService;
    }

    /**
     * Garante que as dependências estão inicializadas usando classes reais
     */
    private function ensureDependenciesInitialized(): void
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new \Clubify\Checkout\Core\Http\Client($this->config, $this->logger);
        }

        if (!isset($this->cache)) {
            $this->cache = new \Clubify\Checkout\Core\Cache\CacheManager($this->config);
        }

        if (!isset($this->eventDispatcher)) {
            $this->eventDispatcher = new \Clubify\Checkout\Core\Events\EventDispatcher();
        }
    }

    /**
     * Setup completo de uma nova organização com rollback e retry automático
     *
     * @param array $organizationData Dados da organização
     * @param string|null $idempotencyKey Chave de idempotência para operação segura
     * @param bool $enableRollback Se deve executar rollback automático em caso de falha
     * @param bool $enableRetry Se deve tentar novamente em caso de falha recuperável
     * @return array Resultado do setup completo
     */
    public function setupOrganization(
        array $organizationData,
        ?string $idempotencyKey = null,
        bool $enableRollback = true,
        bool $enableRetry = true
    ): array {
        // Gerar chave de idempotência se não fornecida
        if (!$idempotencyKey) {
            $idempotencyKey = 'org_setup_' . uniqid() . '_' . hash('sha256', json_encode($organizationData));
        }

        $this->logger->info('Starting organization setup with rollback support', [
            'idempotency_key' => $idempotencyKey,
            'enable_rollback' => $enableRollback,
            'enable_retry' => $enableRetry,
            'organization_name' => $organizationData['name'] ?? 'Unknown'
        ]);

        $setupOperation = function (array $data, array $retryContext) {
            return $this->executeOrganizationSetup($data, $retryContext);
        };

        try {
            if ($enableRetry) {
                return $this->retry()->executeWithRetry($setupOperation, $idempotencyKey, $organizationData);
            } else {
                return $setupOperation($organizationData, ['idempotency_key' => $idempotencyKey]);
            }
        } catch (OrganizationSetupException $e) {
            $this->logger->error('Organization setup failed', [
                'idempotency_key' => $idempotencyKey,
                'setup_step' => $e->getSetupStep(),
                'error' => $e->getMessage(),
                'rollback_required' => $e->isRollbackRequired()
            ]);

            // Execute automatic rollback if enabled and required
            if ($enableRollback && $e->isRollbackRequired()) {
                try {
                    $rollbackResult = $this->rollback()->executeRollback($e);
                    $this->logger->info('Automatic rollback completed', [
                        'idempotency_key' => $idempotencyKey,
                        'rollback_success' => $rollbackResult['success'] ?? false
                    ]);
                } catch (\Exception $rollbackException) {
                    $this->logger->error('Automatic rollback failed', [
                        'idempotency_key' => $idempotencyKey,
                        'rollback_error' => $rollbackException->getMessage()
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Executa o setup da organização passo a passo com controle de rollback
     */
    private function executeOrganizationSetup(array $organizationData, array $retryContext): array
    {
        $completedSteps = [];
        $createdResources = [];
        $rollbackData = [];

        try {
            // Step 1: Create Organization
            $this->logger->info('Step 1: Creating organization');
            $organization = $this->createOrganizationStep($organizationData);
            $completedSteps[] = 'organization_created';
            $createdResources['organization'] = $organization['id'];
            $rollbackData = [
                'organization_id' => $organization['id'],
                'created_resources' => $createdResources
            ];

            // Step 2: Create Tenant
            $this->logger->info('Step 2: Creating tenant');
            try {
                $tenant = $this->createTenantStep($organization['id'], $organizationData);
                $completedSteps[] = 'tenant_created';
                $createdResources['tenant'] = $tenant['id'];
                $rollbackData['tenant_id'] = $tenant['id'];
                $rollbackData['created_resources'] = $createdResources;
            } catch (\Exception $e) {
                throw OrganizationSetupException::tenantCreationFailed(
                    $organization['id'],
                    $e->getMessage(),
                    $e
                );
            }

            // Step 3: Create Admin User
            $this->logger->info('Step 3: Creating admin user');
            try {
                $admin = $this->createAdminStep($organization['id'], $organizationData);
                $completedSteps[] = 'admin_created';
                $createdResources['admin'] = $admin['id'];
                $rollbackData['admin_id'] = $admin['id'];
                $rollbackData['created_resources'] = $createdResources;
            } catch (\Exception $e) {
                throw OrganizationSetupException::adminCreationFailed(
                    $organization['id'],
                    $tenant['id'],
                    $e->getMessage(),
                    $e
                );
            }

            // Step 4: Generate API Keys
            $this->logger->info('Step 4: Generating API keys');
            try {
                $apiKeys = $this->generateApiKeysStep($organization['id']);
                $completedSteps[] = 'api_keys_generated';
                $createdResources['api_keys'] = $apiKeys;
                $rollbackData['created_resources'] = $createdResources;
            } catch (\Exception $e) {
                throw OrganizationSetupException::apiKeyGenerationFailed(
                    $organization['id'],
                    $tenant['id'],
                    $admin['id'],
                    $e->getMessage(),
                    $e
                );
            }

            // Step 5: Configure Domain (optional)
            $domain = null;
            if (!empty($organizationData['domain'])) {
                $this->logger->info('Step 5: Configuring custom domain');
                try {
                    $domain = $this->configureDomainStep($organization['id'], $organizationData['domain']);
                    $completedSteps[] = 'domain_configured';
                } catch (\Exception $e) {
                    // Domain configuration is optional, so we can complete without it
                    throw OrganizationSetupException::domainConfigurationFailed(
                        $organization['id'],
                        $tenant['id'],
                        $admin['id'],
                        $apiKeys,
                        $e->getMessage(),
                        $e
                    );
                }
            }

            // Build successful result
            $result = [
                'success' => true,
                'organization' => $organization,
                'tenant' => $tenant,
                'admin' => $admin,
                'api_keys' => $apiKeys,
                'domain' => $domain,
                'setup_metadata' => [
                    'completed_steps' => $completedSteps,
                    'created_resources' => $createdResources,
                    'idempotency_key' => $retryContext['idempotency_key'] ?? null,
                    'completed_at' => time()
                ]
            ];

            $this->logger->info('Organization setup completed successfully', [
                'organization_id' => $organization['id'],
                'completed_steps' => $completedSteps
            ]);

            // Dispatch success event
            $this->eventDispatcher->emit('organization_setup.completed', $result);

            return $result;

        } catch (OrganizationSetupException $e) {
            // Re-throw setup exceptions as-is
            throw $e;
        } catch (\Exception $e) {
            // Wrap other exceptions in setup exception
            throw new OrganizationSetupException(
                'Unexpected error during organization setup: ' . $e->getMessage(),
                'unexpected_error',
                $rollbackData,
                $completedSteps,
                $e
            );
        }
    }

    /**
     * Step 1: Create Organization REAL (com campos obrigatórios para backend)
     */
    private function createOrganizationStep(array $organizationData): array
    {
        // Mapear para schema do backend (Organization.schema.ts)
        $orgData = [
            'name' => $organizationData['name'],
            'cnpj' => $organizationData['cnpj'],
            'legalName' => $organizationData['legalName'] ?? $organizationData['name'],
            'tradeName' => $organizationData['tradeName'] ?? null,
            'slug' => $organizationData['slug'] ?? $this->generateSlug($organizationData['name']),
            'type' => $organizationData['type'] ?? 'limited_company',
            'description' => $organizationData['description'] ?? null,
            'contact' => [
                'email' => $organizationData['admin_email'] ?? $organizationData['contact']['email'],
                'phone' => $organizationData['contact']['phone'] ?? null,
                'website' => $organizationData['contact']['website'] ?? null,
                'supportEmail' => $organizationData['contact']['supportEmail'] ?? null
            ],
            'address' => $organizationData['address'] ?? [
                'street' => 'Rua Exemplo',
                'number' => '123',
                'neighborhood' => 'Centro',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zipCode' => '01234-567',
                'country' => 'BR'
            ],
            'dataProtectionOfficer' => $organizationData['dataProtectionOfficer'] ?? [
                'name' => $organizationData['admin_name'] ?? 'DPO',
                'email' => $organizationData['admin_email'] ?? $organizationData['contact']['email'],
                'phone' => '11999999999'
            ],
            'lawfulBasisForProcessing' => 'contract',
            'settings' => $organizationData['settings'] ?? []
        ];

        try {
            return $this->getRepository()->create($orgData);
        } catch (ConflictException $e) {
            // If organization with same name/slug exists, this might be recoverable
            throw $e;
        }
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        return strtolower(str_replace([' ', '_'], '-',
            iconv('UTF-8', 'ASCII//TRANSLIT', $name)
        ));
    }

    /**
     * Step 2: Create Tenant
     */
    private function createTenantStep(string $organizationId, array $organizationData): array
    {
        $tenantData = [
            'name' => $organizationData['tenant_name'] ?? $organizationData['name'],
            'subdomain' => $organizationData['subdomain'] ?? null
        ];

        return $this->tenant()->createTenant($organizationId, $tenantData);
    }

    /**
     * Step 3: Create Admin User
     */
    private function createAdminStep(string $organizationId, array $organizationData): array
    {
        $adminData = [
            'name' => $organizationData['admin_name'],
            'email' => $organizationData['admin_email'],
            'password' => $organizationData['admin_password'] ?? null,
            'role' => $organizationData['admin_role'] ?? 'organization_admin'
        ];

        return $this->admin()->createAdmin($organizationId, $adminData);
    }

    /**
     * Step 4: Generate API Keys
     */
    /**
     * Step 4: Generate Organization-Level API Keys
     */
    private function generateApiKeysStep(string $organizationId): array
    {
        try {
            // Gerar API key de ORGANIZAÇÃO (acesso total)
            $organizationKey = $this->organizationApiKey()->generateFullOrganizationKey($organizationId, [
                'name' => 'Organization Master Key',
                'environment' => $this->config->getEnvironment(),
                'description' => 'Full organization access - generated during setup'
            ]);

            return [
                'organization_key' => $organizationKey,
                'scope' => 'organization',
                'access_level' => 'full'
            ];

        } catch (\Exception $e) {
            throw new HttpException('Failed to generate organization API keys: ' . $e->getMessage());
        }
    }

    /**
     * Step 5: Configure Domain (optional)
     */
    private function configureDomainStep(string $organizationId, string $domain): array
    {
        return $this->domain()->configureDomain($organizationId, $domain);
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
