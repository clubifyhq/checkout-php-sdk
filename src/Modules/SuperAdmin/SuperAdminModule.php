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

    /**
     * Rotacionar API key de um tenant
     */
    public function rotateApiKey(string $apiKeyId, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            $payload = [
                'gracePeriodHours' => $options['gracePeriodHours'] ?? 24,
                'forceRotation' => $options['forceRotation'] ?? false
            ];

            $response = $this->httpClient->post("api-keys/{$apiKeyId}/rotate", [
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to rotate API key');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('API key rotated successfully', [
                'api_key_id' => $apiKeyId,
                'grace_period_hours' => $payload['gracePeriodHours']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to rotate API key', [
                'error' => $e->getMessage(),
                'api_key_id' => $apiKeyId
            ]);

            throw new SDKException('Failed to rotate API key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Obter informações de uma API key específica
     */
    public function getApiKeyInfo(string $apiKeyId): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->httpClient->get("api-keys/{$apiKeyId}");

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to get API key info');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get API key info', [
                'error' => $e->getMessage(),
                'api_key_id' => $apiKeyId
            ]);

            throw new SDKException('Failed to get API key info: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validar uma API key
     */
    public function validateApiKey(string $apiKeyValue, string $endpoint = '/checkout', string $clientIp = null): array
    {
        $this->ensureInitialized();

        try {
            $payload = [
                'apiKey' => $apiKeyValue,
                'endpoint' => $endpoint
            ];

            if ($clientIp) {
                $payload['clientIp'] = $clientIp;
            }

            $response = $this->httpClient->post('api-keys/public/validate', [
                'json' => $payload
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to validate API key');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to validate API key', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);

            throw new SDKException('Failed to validate API key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Criar usuário com role tenant_admin para um tenant
     */
    public function createTenantAdmin(string $tenantId, array $userData): array
    {
        $this->ensureInitialized();

        try {
            // Estrutura correta baseada na validação da API
            $payload = [
                'email' => $userData['email'],
                'firstName' => $userData['firstName'] ?? $userData['name'] ?? 'Tenant',
                'lastName' => $userData['lastName'] ?? 'Administrator',
                'password' => $userData['password'],
                'roles' => ['tenant_admin'],
                'tenantId' => $tenantId
            ];

            // Adicionar cabeçalho X-Tenant-Id para contexto correto
            $response = $this->httpClient->post('users', [
                'json' => $payload,
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to create tenant admin user');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('Tenant admin user created successfully', [
                'tenant_id' => $tenantId,
                'user_id' => $result['data']['id'] ?? $result['id'] ?? 'unknown',
                'email' => $payload['email']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create tenant admin user', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'email' => $userData['email'] ?? 'unknown'
            ]);

            throw new SDKException('Failed to create tenant admin user: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Criar API key para um tenant
     */
    public function createApiKey(string $tenantId, array $keyData = []): array
    {
        $this->ensureInitialized();

        try {
            $payload = [
                'name' => $keyData['name'] ?? 'Tenant Admin API Key',
                'description' => $keyData['description'] ?? 'API key for tenant admin operations',
                'environment' => $keyData['environment'] ?? 'test',
                'allowedOrigins' => $keyData['allowedOrigins'] ?? ['*'],
                'rateLimiting' => $keyData['rateLimiting'] ?? [
                    'requestsPerMinute' => 120,
                    'requestsPerHour' => 5000,
                    'requestsPerDay' => 50000
                ],
                'permissions' => $keyData['permissions'] ?? [
                    'integration:advanced',
                    'customer:read',
                    'customer:write',
                    'analytics:read',
                    'user:write',
                    'api-key:write',
                    'tenant:write',
                    'products:read',
                    'products:write',
                    'checkout:read',
                    'checkout:write'
                ]
            ];

            // Usar cabeçalho X-Tenant-Id para contexto
            $response = $this->httpClient->post('api-keys', [
                'json' => $payload,
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new SDKException('Failed to create API key');
            }

            $result = json_decode($response->getBody()->getContents(), true);

            $this->logger->info('API key created successfully', [
                'tenant_id' => $tenantId,
                'api_key_id' => $result['data']['id'] ?? $result['id'] ?? 'unknown',
                'name' => $payload['name']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create API key', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to create API key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Provisionar credenciais completas para um tenant (usuário admin + API key)
     */
    public function provisionTenantCredentials(string $tenantId, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            // Dados do usuário admin (estrutura correta da API)
            $fullName = $options['admin_name'] ?? 'Tenant Administrator';
            $nameParts = explode(' ', $fullName, 2);

            $adminData = [
                'email' => $options['admin_email'] ?? "admin@tenant-{$tenantId}.local",
                'firstName' => $nameParts[0] ?? 'Tenant',
                'lastName' => $nameParts[1] ?? 'Administrator',
                'password' => $options['admin_password'] ?? $this->generateSecurePassword()
            ];

            // 1. Criar usuário tenant_admin
            $userResult = $this->createTenantAdmin($tenantId, $adminData);
            $userId = $userResult['data']['id'] ?? $userResult['id'] ?? null;

            if (!$userId) {
                throw new SDKException('Failed to get user ID from creation response');
            }

            // 2. Criar API key para o tenant
            $apiKeyData = [
                'name' => $options['api_key_name'] ?? 'Tenant Admin API Key',
                'description' => $options['api_key_description'] ?? 'API key for tenant admin operations',
                'environment' => $options['environment'] ?? 'production'
            ];

            $apiKeyResult = $this->createApiKey($tenantId, $apiKeyData);
            $apiKey = $apiKeyResult['data']['key'] ?? $apiKeyResult['key'] ?? null;
            $apiKeyId = $apiKeyResult['data']['id'] ?? $apiKeyResult['id'] ?? null;

            if (!$apiKey) {
                throw new SDKException('Failed to get API key from creation response');
            }

            $this->logger->info('Tenant credentials provisioned successfully', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'api_key_id' => $apiKeyId,
                'admin_email' => $adminData['email']
            ]);

            return [
                'success' => true,
                'message' => 'Tenant credentials provisioned successfully',
                'user' => [
                    'id' => $userId,
                    'email' => $adminData['email'],
                    'firstName' => $adminData['firstName'],
                    'lastName' => $adminData['lastName'],
                    'fullName' => $adminData['firstName'] . ' ' . $adminData['lastName'],
                    'password' => $adminData['password'], // Retorna apenas para configuração inicial
                    'roles' => ['tenant_admin']
                ],
                'api_key' => [
                    'id' => $apiKeyId,
                    'key' => $apiKey,
                    'name' => $apiKeyData['name']
                ],
                'tenant_id' => $tenantId
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to provision tenant credentials', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to provision tenant credentials: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gerar senha segura que atende aos critérios da política
     */
    private function generateSecurePassword(int $length = 16): string
    {
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $numbers = '0123456789';
        $specials = '!@#$%^&*';

        // Garantir pelo menos um de cada tipo
        $password = '';
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $specials[random_int(0, strlen($specials) - 1)];

        // Preencher o resto aleatoriamente
        $allChars = $uppercase . $lowercase . $numbers . $specials;
        for ($i = 4; $i < $length; $i++) {
            $password .= $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Embaralhar a senha
        return str_shuffle($password);
    }

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

    /**
     * Verificar se tenant tem credenciais de acesso
     */
    public function checkTenantCredentials(string $tenantId): array
    {
        $this->ensureInitialized();

        try {
            // Primeiro tentar obter informações detalhadas do tenant
            $tenantResponse = $this->httpClient->get("tenants/{$tenantId}");
            $tenantStatusCode = $tenantResponse->getStatusCode();

            if ($tenantStatusCode >= 200 && $tenantStatusCode < 300) {
                $tenantResult = json_decode($tenantResponse->getBody()->getContents(), true);
                $tenantData = $tenantResult['data'] ?? $tenantResult;

                // Verificar se já tem API key nos dados do tenant
                if (isset($tenantData['api_key']) && !empty($tenantData['api_key'])) {
                    return [
                        'has_credentials' => true,
                        'api_key' => $tenantData['api_key'],
                        'message' => 'Tenant has API key available'
                    ];
                }
            }

            return [
                'has_credentials' => false,
                'message' => 'Tenant needs manual credential setup',
                'instructions' => [
                    'step1' => 'Create admin user via POST /users with role tenant_admin',
                    'step2' => 'Create API key via POST /api-keys for the tenant',
                    'step3' => 'Use the API key for tenant context switching'
                ]
            ];

        } catch (\Exception $e) {
            return [
                'has_credentials' => false,
                'error' => $e->getMessage(),
                'message' => 'Could not verify tenant credentials'
            ];
        }
    }
}