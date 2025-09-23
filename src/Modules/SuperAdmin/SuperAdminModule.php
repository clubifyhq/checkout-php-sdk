<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\SuperAdmin;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\SuperAdmin\Services\TenantManagementService;
use Clubify\Checkout\Modules\SuperAdmin\Services\OrganizationCreationService;
use Clubify\Checkout\Exceptions\SDKException;

/**
 * Módulo Super Admin
 *
 * Responsável pela gestão de super admin, incluindo:
 * - Criação e gerenciamento de organizações
 * - Gerenciamento de tenants
 * - Administração de credenciais
 * - Supervisão geral do sistema
 */
class SuperAdminModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    private ?TenantManagementService $tenantManagement = null;
    private ?OrganizationCreationService $organizationCreation = null;

    private ?Client $httpClient = null;
    private ?CacheManagerInterface $cache = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('SuperAdmin module initialized', [
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
        return 'super_admin';
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
                'tenant_management' => $this->tenantManagement !== null,
                'organization_creation' => $this->organizationCreation !== null
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->tenantManagement = null;
        $this->organizationCreation = null;
        $this->initialized = false;

        $this->logger->info('SuperAdmin module cleaned up');
    }

    /**
     * Criar organização
     */
    public function createOrganization(array $data): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->post('super-admin/organizations', [
                'json' => $data
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to create organization');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Organization created successfully', [
                'organization_id' => $result['organization']['id'] ?? 'unknown'
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create organization', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);

            throw new SDKException('Organization creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Listar tenants
     */
    public function listTenants(array $filters = []): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->get('tenants', [
                'query' => $filters
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to list tenants');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to list tenants', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);

            throw new SDKException('Failed to list tenants: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obter credenciais de tenant
     */
    public function getTenantCredentials(string $tenantId): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->get("tenants/{$tenantId}");

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to get tenant credentials');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get tenant credentials', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to get tenant credentials: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Provisionar credenciais de acesso para tenant existente
     * Cria usuário tenant_admin e API key se necessário
     */
    public function provisionTenantCredentials(string $tenantId, array $adminData = []): array
    {
        $this->ensureInitialized();

        try {
            $payload = [
                'tenant_id' => $tenantId,
                'admin_email' => $adminData['admin_email'] ?? "admin@tenant-{$tenantId}.local",
                'admin_name' => $adminData['admin_name'] ?? 'Tenant Administrator',
                'admin_password' => $adminData['admin_password'] ?? $this->generateSecurePassword(),
                'generate_api_key' => true
            ];

            $response = $this->httpClient->post("tenants/{$tenantId}/provision", [
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to provision tenant credentials');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Tenant credentials provisioned successfully', [
                'tenant_id' => $tenantId,
                'admin_email' => $payload['admin_email']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to provision tenant credentials', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to provision tenant credentials: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gerar senha segura para usuário admin
     */
    private function generateSecurePassword(int $length = 16): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Buscar tenant por domínio
     */
    public function getTenantByDomain(string $domain): ?array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->get("tenants/domain/{$domain}");

            $statusCode = $response->getStatusCode();
            if ($statusCode === 404) {
                // Tenant não encontrado
                return null;
            }

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to get tenant by domain');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Tenant found by domain', [
                'domain' => $domain,
                'tenant_id' => $result['data']['_id'] ?? $result['id'] ?? 'unknown'
            ]);

            return $result;

        } catch (\Exception $e) {
            // Se for 404, retorna null (tenant não encontrado)
            if (strpos($e->getMessage(), '404') !== false) {
                return null;
            }

            $this->logger->error('Failed to get tenant by domain', [
                'error' => $e->getMessage(),
                'domain' => $domain
            ]);

            throw new SDKException('Failed to get tenant by domain: ' . $e->getMessage(), 0, $e);
        }
    }

    // Nota: O endpoint regenerate-api-key foi movido para o módulo de API Keys
    // Usar: POST /api-keys/{keyId}/rotate no módulo ApiKeys quando implementado

    /**
     * Obter estatísticas gerais
     */
    public function getSystemStats(int $timeoutSeconds = 10): array
    {
        $this->ensureInitialized();

        try {
            // Usar timeout customizado para esta operação específica
            $response = $this->httpClient->get('tenants/stats', [
                'timeout' => $timeoutSeconds
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to get system stats');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get system stats', [
                'error' => $e->getMessage(),
                'timeout' => $timeoutSeconds
            ]);

            throw new SDKException('Failed to get system stats: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Suspender tenant
     */
    public function suspendTenant(string $tenantId, string $reason = ''): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->put("tenants/{$tenantId}/suspend", [
                'json' => [
                    'reason' => $reason
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to suspend tenant');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Tenant suspended successfully', [
                'tenant_id' => $tenantId,
                'reason' => $reason
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to suspend tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to suspend tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Reativar tenant
     */
    public function reactivateTenant(string $tenantId): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->put("tenants/{$tenantId}/activate");

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to reactivate tenant');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Tenant reactivated successfully', [
                'tenant_id' => $tenantId
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to reactivate tenant', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to reactivate tenant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Garante que as dependências estão inicializadas
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
     * Verificar se está inicializado
     */
    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            throw new SDKException('SuperAdmin module not initialized');
        }

        $this->ensureDependenciesInitialized();
    }
}