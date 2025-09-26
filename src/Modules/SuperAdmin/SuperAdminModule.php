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
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService as UserManagementTenantService;
use Clubify\Checkout\Modules\Organization\Services\ApiKeyService;
use Clubify\Checkout\Modules\Organization\Services\TenantService as OrganizationTenantService;
use Clubify\Checkout\Exceptions\SDKException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Core\Http\ResponseHelper;

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

    // Centralized Services (Dependency Injection)
    private ?UserService $userService = null;
    private ?UserManagementTenantService $userManagementTenantService = null;
    private ?ApiKeyService $apiKeyService = null;
    private ?OrganizationTenantService $organizationTenantService = null;

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
     * Injeta os serviços centralizados (Dependency Injection)
     *
     * Esta abordagem remove a duplicação de código e centraliza
     * a funcionalidade nos módulos apropriados
     */
    public function setCentralizedServices(
        UserService $userService,
        UserManagementTenantService $userManagementTenantService,
        ApiKeyService $apiKeyService,
        OrganizationTenantService $organizationTenantService
    ): void {
        $this->userService = $userService;
        $this->userManagementTenantService = $userManagementTenantService;
        $this->apiKeyService = $apiKeyService;
        $this->organizationTenantService = $organizationTenantService;

        $this->logger->debug('SuperAdmin: Centralized services injected', [
            'services' => [
                'user_service' => $userService->getName(),
                'user_management_tenant_service' => get_class($userManagementTenantService),
                'api_key_service' => get_class($apiKeyService),
                'organization_tenant_service' => get_class($organizationTenantService)
            ]
        ]);
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
            // Se temos o TenantService do UserManagement disponível, usar ele
            if ($this->userManagementTenantService !== null) {
                $this->logger->info('Creating organization via UserManagement TenantService', [
                    'data_keys' => array_keys($data)
                ]);

                $result = $this->userManagementTenantService->createOrganization($data);

                $this->logger->info('Organization created successfully via TenantService', [
                    'organization_id' => $result['organization']['id'] ?? $result['tenant_id'] ?? 'unknown',
                    'success' => $result['success'] ?? false
                ]);

                // Padronizar formato de resposta
                return [
                    'success' => $result['success'] ?? true,
                    'organization' => $result['organization'] ?? $result['tenant'] ?? $result,
                    'tenant_id' => $result['tenant_id'] ?? $result['organization']['id'] ?? null,
                    'via_service' => 'UserManagement TenantService'
                ];
            }

            // Fallback para chamada HTTP direta se o service não estiver disponível
            $this->logger->warning('UserManagement TenantService not available, falling back to direct HTTP call');

            $result = $this->makeHttpRequest('POST', 'organizations', [
                'json' => $data
            ]);

            $this->logger->info('Organization created successfully via direct HTTP', [
                'organization_id' => $result['organization']['id'] ?? 'unknown'
            ]);

            return array_merge($result, ['via_service' => 'Direct HTTP']);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create organization', [
                'error' => $e->getMessage(),
                'data' => $data,
                'service_available' => $this->userManagementTenantService !== null
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
            $result = $this->makeHttpRequest('GET', 'tenants', [
                'query' => $filters
            ]);

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
            $result = $this->makeHttpRequest('GET', "tenants/{$tenantId}");

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
    public function getTenantByDomain(string $domain): array
    {
        $this->ensureInitialized();

        try {
            // Usar TenantService do UserManagement se disponível
            if ($this->userManagementTenantService !== null) {
                $this->logger->info('Getting tenant by domain via UserManagement TenantService', [
                    'domain' => $domain
                ]);

                $result = $this->userManagementTenantService->getTenantByDomain($domain);

                // Debug log para verificar estrutura
                $this->logger->debug('TenantService result structure', [
                    'domain' => $domain,
                    'result_keys' => array_keys($result),
                    'success' => $result['success'] ?? 'not_set',
                    'has_tenant' => isset($result['tenant']) ? 'yes' : 'no',
                    'has_data' => isset($result['data']) ? 'yes' : 'no'
                ]);

                // A API pode retornar tanto 'tenant' quanto 'data' como campo principal
                $tenantData = null;
                if ($result['success'] === true) {
                    if (isset($result['tenant'])) {
                        $tenantData = $result['tenant'];
                    } elseif (isset($result['data'])) {
                        $tenantData = $result['data'];
                    }
                }

                if ($result['success'] === true && $tenantData !== null) {
                    $this->logger->info('Tenant found by domain via TenantService', [
                        'domain' => $domain,
                        'tenant_id' => $tenantData['_id'] ?? $tenantData['id'] ?? 'unknown'
                    ]);

                    return [
                        'success' => true,
                        'tenant' => $tenantData,
                        'message' => 'Tenant found successfully',
                        'via_service' => 'UserManagement TenantService'
                    ];
                } else {
                    // Tenant não encontrado pelo TenantService - retornar resposta estruturada
                    $this->logger->info('Tenant not found by domain via TenantService', [
                        'domain' => $domain,
                        'tenant_service_message' => $result['message'] ?? 'N/A'
                    ]);

                    return [
                        'success' => false,
                        'tenant' => null,
                        'message' => $result['message'] ?? "Tenant not found for domain: {$domain}",
                        'via_service' => 'UserManagement TenantService'
                    ];
                }
            }

            // Fallback para chamada HTTP direta
            $this->logger->warning('UserManagement TenantService not available, falling back to direct HTTP call', [
                'domain' => $domain
            ]);

            $result = $this->makeHttpRequest('GET', "tenants/domain/{$domain}");

            $this->logger->info('Tenant found by domain via direct HTTP', [
                'domain' => $domain,
                'tenant_id' => $result['data']['_id'] ?? $result['id'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'tenant' => $result,
                'message' => 'Tenant found successfully',
                'via_service' => 'Direct HTTP'
            ];

        } catch (HttpException $e) {
            // Se for 404, retorna mensagem amigável ao invés de null
            if ($e->getStatusCode() === 404) {
                $this->logger->info('Tenant not found by domain (404)', [
                    'domain' => $domain
                ]);

                return [
                    'success' => false,
                    'tenant' => null,
                    'message' => "Tenant not found for domain: {$domain}",
                    'error_code' => 404,
                    'via_service' => 'Direct HTTP'
                ];
            }
            throw $e;
        } catch (\Exception $e) {
            // Para outros tipos de erro, verifica se é 404 na mensagem
            if (strpos($e->getMessage(), '404') !== false) {
                $this->logger->info('Tenant not found by domain (404 in message)', [
                    'domain' => $domain
                ]);

                return [
                    'success' => false,
                    'tenant' => null,
                    'message' => "Tenant not found for domain: {$domain}",
                    'error_code' => 404,
                    'via_service' => 'Direct HTTP'
                ];
            }

            $this->logger->error('Failed to get tenant by domain', [
                'error' => $e->getMessage(),
                'domain' => $domain
            ]);

            return [
                'success' => false,
                'tenant' => null,
                'message' => "Error searching for tenant by domain: {$domain}",
                'error' => $e->getMessage(),
                'via_service' => 'Direct HTTP'
            ];
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

            $response = $this->makeHttpRequest('POST', "api-keys/{$apiKeyId}/rotate", [
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
            $response = $this->makeHttpRequest('GET', "api-keys/{$apiKeyId}");

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

            $response = $this->makeHttpRequest('POST', 'api-keys/public/validate', [
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
     * Verificar se usuário existe no tenant
     */
    public function checkUserExists(string $email, string $tenantId): array
    {
        $this->ensureInitialized();

        try {
            // Primeiro tentar buscar usuário por email no contexto do tenant
            $result = $this->makeHttpRequest('GET', 'users', [
                'query' => [
                    'email' => $email,
                    'limit' => 1
                ],
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ]
            ]);

            // makeHttpRequest já retorna o array processado
            $users = $result['data'] ?? $result['users'] ?? $result;

            if (is_array($users) && count($users) > 0) {
                $user = $users[0];
                return [
                    'exists' => true,
                    'user' => $user,
                    'user_id' => $user['id'] ?? $user['_id'] ?? null,
                    'roles' => $user['roles'] ?? [],
                    'message' => 'User found in tenant'
                ];
            }

            return [
                'exists' => false,
                'message' => 'User not found in tenant'
            ];

        } catch (\Exception $e) {
            $this->logger->warning('Error checking user existence', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            // Return false to allow creation attempt
            return [
                'exists' => false,
                'error' => $e->getMessage(),
                'message' => 'Could not verify user existence - will attempt creation'
            ];
        }
    }

    /**
     * Criar usuário com role tenant_admin para um tenant (com verificação prévia)
     */
    public function createTenantAdmin(string $tenantId, array $userData): array
    {
        $this->ensureInitialized();

        $email = $userData['email'];

        // STEP 1: Verificar se usuário já existe
        $userCheck = $this->checkUserExists($email, $tenantId);

        if ($userCheck['exists']) {
            $this->logger->info('User already exists, returning existing user data', [
                'email' => $email,
                'tenant_id' => $tenantId,
                'user_id' => $userCheck['user_id']
            ]);

            return [
                'data' => $userCheck['user'],
                'existed' => true,
                'message' => 'User already exists in tenant'
            ];
        }

        try {
            // STEP 2: Criar novo usuário se não existe
            $payload = [
                'email' => $email,
                'firstName' => $userData['firstName'] ?? $userData['name'] ?? 'Tenant',
                'lastName' => $userData['lastName'] ?? 'Administrator',
                'password' => $userData['password'],
                'roles' => ['tenant_admin'],
                'tenantId' => $tenantId
            ];

            $response = $this->makeHttpRequest('POST', 'users', [
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

            $result['existed'] = false;
            return $result;

        } catch (\Exception $e) {
            // STEP 3: Handle 409 conflicts gracefully
            if ($this->is409ConflictError($e)) {
                $this->logger->info('User creation conflict detected, re-checking existence', [
                    'email' => $email,
                    'tenant_id' => $tenantId
                ]);

                // Re-check user existence after conflict
                $recheckResult = $this->checkUserExists($email, $tenantId);
                if ($recheckResult['exists']) {
                    return [
                        'data' => $recheckResult['user'],
                        'existed' => true,
                        'message' => 'User already exists (detected after 409 conflict)'
                    ];
                }
            }

            $this->logger->error('Failed to create tenant admin user', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'email' => $email
            ]);

            throw new SDKException('Failed to create tenant admin user: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if exception is a 409 conflict error
     */
    private function is409ConflictError(\Exception $e): bool
    {
        $message = $e->getMessage();
        return strpos($message, '409') !== false ||
               strpos($message, 'Conflict') !== false ||
               strpos($message, 'already exists') !== false ||
               strpos($message, 'duplicate') !== false;
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
            $response = $this->makeHttpRequest('POST', 'api-keys', [
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

            // 1. Criar ou obter usuário tenant_admin existente
            $userResult = $this->createTenantAdmin($tenantId, $adminData);
            $userData = $userResult['data'] ?? $userResult;
            $userId = $userData['id'] ?? $userData['_id'] ?? null;
            $userExisted = $userResult['existed'] ?? false;

            if (!$userId) {
                throw new SDKException('Failed to get user ID from creation/retrieval response');
            }

            // 2. Verificar se já existe API key para o tenant
            $existingApiKey = $this->checkExistingApiKey($tenantId);

            if ($existingApiKey['exists'] && !empty($existingApiKey['api_key'])) {
                $this->logger->info('Using existing API key for tenant', [
                    'tenant_id' => $tenantId,
                    'api_key_id' => $existingApiKey['api_key_id'],
                    'key_preview' => substr($existingApiKey['api_key'], 0, 8) . '...'
                ]);

                return [
                    'success' => true,
                    'message' => $userExisted ?
                        'Tenant credentials already exist - retrieved successfully' :
                        'User created, existing API key found',
                    'user' => [
                        'id' => $userId,
                        'email' => $userData['email'] ?? $adminData['email'],
                        'firstName' => $userData['firstName'] ?? $adminData['firstName'],
                        'lastName' => $userData['lastName'] ?? $adminData['lastName'],
                        'fullName' => ($userData['firstName'] ?? $adminData['firstName']) . ' ' . ($userData['lastName'] ?? $adminData['lastName']),
                        'password' => $userExisted ? '[EXISTING]' : $adminData['password'],
                        'roles' => $userData['roles'] ?? ['tenant_admin'],
                        'existed' => $userExisted
                    ],
                    'api_key' => [
                        'id' => $existingApiKey['api_key_id'],
                        'key' => $existingApiKey['api_key'],
                        'name' => $existingApiKey['name'],
                        'existed' => true
                    ],
                    'tenant_id' => $tenantId
                ];
            }

            // 3. Criar nova API key se não existe
            $apiKeyData = [
                'name' => $options['api_key_name'] ?? 'Tenant Admin API Key',
                'description' => $options['api_key_description'] ?? 'API key for tenant admin operations',
                'environment' => $options['environment'] ?? 'production'
            ];

            $apiKeyResult = $this->createApiKey($tenantId, $apiKeyData);

            // Debug logging da resposta completa
            $this->logger->debug('API key creation response structure', [
                'tenant_id' => $tenantId,
                'response_keys' => array_keys($apiKeyResult),
                'response_preview' => array_map(function($value) {
                    return is_string($value) ? substr($value, 0, 50) . '...' : gettype($value);
                }, $apiKeyResult)
            ]);

            // Múltiplas tentativas de parsing da estrutura de resposta
            $apiKeyResponseData = $apiKeyResult['data'] ?? $apiKeyResult;
            $apiKey = $apiKeyResponseData['key'] ??
                     $apiKeyResponseData['apiKey'] ??
                     $apiKeyResponseData['api_key'] ??
                     $apiKeyResult['key'] ??
                     $apiKeyResult['apiKey'] ??
                     $apiKeyResult['api_key'] ?? null;

            $apiKeyId = $apiKeyResponseData['id'] ??
                       $apiKeyResponseData['_id'] ??
                       $apiKeyResponseData['apiKeyId'] ??
                       $apiKeyResult['id'] ??
                       $apiKeyResult['_id'] ??
                       $apiKeyResult['apiKeyId'] ?? null;

            // Se ainda não conseguiu extrair a API key, tentar buscar a recém-criada
            if (!$apiKey) {
                $this->logger->warning('Failed to extract API key from creation response, attempting fallback retrieval', [
                    'tenant_id' => $tenantId,
                    'response_structure' => json_encode($apiKeyResult, JSON_PRETTY_PRINT)
                ]);

                // Fallback: tentar obter API key de outras propriedades da resposta
                $fallbackApiKey = $apiKeyResult['api_key'] ??
                                 $apiKeyResult['key'] ??
                                 $apiKeyResult['token'] ?? null;
                if ($fallbackApiKey['exists']) {
                    $apiKey = $fallbackApiKey['api_key'];
                    $apiKeyId = $fallbackApiKey['api_key_id'];

                    $this->logger->info('API key retrieved via fallback method', [
                        'tenant_id' => $tenantId,
                        'api_key_id' => $apiKeyId
                    ]);
                }
            }

            if (!$apiKey) {
                throw new SDKException('Failed to get API key from creation response and fallback retrieval');
            }

            $this->logger->info('Tenant credentials provisioned successfully', [
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'user_existed' => $userExisted,
                'api_key_id' => $apiKeyId,
                'admin_email' => $adminData['email']
            ]);

            return [
                'success' => true,
                'message' => $userExisted ?
                    'User already existed - API key created successfully' :
                    'Tenant credentials provisioned successfully',
                'user' => [
                    'id' => $userId,
                    'email' => $userData['email'] ?? $adminData['email'],
                    'firstName' => $userData['firstName'] ?? $adminData['firstName'],
                    'lastName' => $userData['lastName'] ?? $adminData['lastName'],
                    'fullName' => ($userData['firstName'] ?? $adminData['firstName']) . ' ' . ($userData['lastName'] ?? $adminData['lastName']),
                    'password' => $userExisted ? '[EXISTING]' : $adminData['password'],
                    'roles' => $userData['roles'] ?? ['tenant_admin'],
                    'existed' => $userExisted
                ],
                'api_key' => [
                    'id' => $apiKeyId,
                    'key' => $apiKey,
                    'name' => $apiKeyData['name'],
                    'existed' => false
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
     * Verificar se já existe API key para o tenant
     */
    private function checkExistingApiKey(string $tenantId): array
    {
        try {
            $result = $this->makeHttpRequest('GET', 'api-keys', [
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ]
            ]);

            if ($result) {
                $apiKeys = $result['data'] ?? $result['api_keys'] ?? $result;

                if (is_array($apiKeys) && count($apiKeys) > 0) {
                    // Retornar a primeira API key encontrada
                    $apiKey = $apiKeys[0] ?? null;
                    if ($apiKey) {
                        return [
                            'exists' => true,
                            'api_key' => $apiKey['key'] ?? null,
                            'api_key_id' => $apiKey['id'] ?? $apiKey['_id'] ?? null,
                            'name' => $apiKey['name'] ?? 'Existing API Key'
                        ];
                    }
                }
            }

            return ['exists' => false];

        } catch (\Exception $e) {
            $this->logger->warning('Error checking existing API keys', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return ['exists' => false];
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
            $result = $this->makeHttpRequest('GET', 'tenants/stats', [
                'timeout' => $timeoutSeconds
            ]);

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
            $result = $this->makeHttpRequest('PUT', "tenants/{$tenantId}/suspend", [
                'json' => [
                    'reason' => $reason
                ]
            ]);

            if (!$result) {
                throw new SDKException('Failed to suspend tenant');
            }

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
            $result = $this->makeHttpRequest('PUT', "tenants/{$tenantId}/activate");

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

    // ============================================================================
    // ORCHESTRATION METHODS - Using Centralized Services
    // ============================================================================

    /**
     * Provisiona credenciais de tenant usando serviços centralizados
     *
     * Esta é a nova implementação que usa os serviços centralizados
     * ao invés de duplicar código. Mantém backward compatibility.
     */
    public function provisionTenantCredentialsV2(string $tenantId, array $options = []): array
    {
        $this->ensureInitialized();
        $this->ensureCentralizedServicesAvailable();

        try {
            $adminData = $this->prepareAdminUserData($options);

            // 1. Usar UserService centralizado para criar/verificar usuário
            $userResult = $this->orchestrateUserCreation($adminData, $tenantId);

            // 2. Usar ApiKeyService centralizado para criar/verificar API key
            $apiKeyResult = $this->orchestrateApiKeyCreation($tenantId, $options);

            // 3. Consolidar e retornar resultado orquestrado
            return $this->consolidateProvisioningResult($userResult, $apiKeyResult, $tenantId);

        } catch (\Exception $e) {
            $this->logger->error('SuperAdmin: Failed to provision tenant credentials via centralized services', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId
            ]);

            throw new SDKException('Failed to provision tenant credentials: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Prepara dados do usuário admin a partir das opções fornecidas
     */
    private function prepareAdminUserData(array $options): array
    {
        // Campos obrigatórios para criação de usuário admin
        $requiredFields = ['admin_email', 'admin_name'];

        foreach ($requiredFields as $field) {
            if (empty($options[$field])) {
                throw new SDKException("Missing required field for admin user: {$field}");
            }
        }

        // Extrair firstName e lastName do admin_name
        $nameParts = explode(' ', trim($options['admin_name']), 2);
        $firstName = $nameParts[0];
        $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

        // Usar senha fornecida ou gerar uma temporária
        $password = $options['admin_password'] ?? $this->generateTemporaryPassword();

        return [
            'email' => $options['admin_email'],
            'firstName' => $firstName,
            'lastName' => $lastName,
            'password' => $password,
            'fullName' => $options['admin_name']
        ];
    }

    /**
     * Gera uma senha temporária segura
     */
    private function generateTemporaryPassword(): string
    {
        $length = 16;
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $password;
    }

    /**
     * Orquestra a criação de usuário usando UserService centralizado
     */
    private function orchestrateUserCreation(array $adminData, string $tenantId): array
    {
        // Primeiro verificar se usuário já existe
        $existingUser = $this->userService->findUserByEmail($adminData['email']);

        if ($existingUser['success'] && $existingUser['user']) {
            $this->logger->info('SuperAdmin: Using existing user for tenant admin', [
                'user_id' => $existingUser['user']['id'],
                'email' => $adminData['email']
            ]);

            return [
                'user' => $existingUser['user'],
                'existed' => true
            ];
        }

        // Criar novo usuário usando serviço centralizado com tenantId
        $userCreationResult = $this->userService->createUser([
            'email' => $adminData['email'],
            'firstName' => $adminData['firstName'],
            'lastName' => $adminData['lastName'],
            'password' => $adminData['password'],
            'roles' => ['tenant_admin'],
            'status' => 'active',
            'source' => 'super_admin_provisioning'
        ], $tenantId);

        if (!$userCreationResult['success']) {
            throw new SDKException('Failed to create user via centralized service');
        }

        return [
            'user' => $userCreationResult['user'],
            'existed' => false
        ];
    }

    /**
     * Orquestra a criação de API key usando ApiKeyService centralizado
     */
    private function orchestrateApiKeyCreation(string $tenantId, array $options): array
    {
        // Verificar se já existe API key para o tenant
        $existingKeys = $this->apiKeyService->getApiKeysByOrganization($tenantId);

        if ($existingKeys && count($existingKeys) > 0) {
            $activeKey = null;
            foreach ($existingKeys as $key) {
                if (($key['status'] ?? '') === 'active') {
                    $activeKey = $key;
                    break;
                }
            }

            if ($activeKey) {
                $this->logger->info('SuperAdmin: Using existing API key for tenant', [
                    'tenant_id' => $tenantId,
                    'api_key_id' => $activeKey['id']
                ]);

                return [
                    'api_key' => $activeKey,
                    'existed' => true
                ];
            }
        }

        // Criar nova API key usando serviço centralizado
        $keyData = [
            'name' => $options['api_key_name'] ?? 'Tenant Admin API Key',
            'description' => $options['api_key_description'] ?? 'API key for tenant admin operations',
            'type' => $options['api_key_type'] ?? 'production',
            'environment' => $options['environment'] ?? 'production',
            'permissions' => [
                'checkout.read',
                'checkout.write',
                'products.read',
                'orders.read',
                'orders.write',
                'payments.read',
                'payments.write',
                'customers.read',
                'customers.write',
                'webhooks.read'
            ]
        ];

        $apiKeyResult = $this->apiKeyService->generateApiKey($tenantId, $keyData);

        if (!$apiKeyResult || !isset($apiKeyResult['key'])) {
            throw new SDKException('Failed to create API key via centralized service');
        }

        return [
            'api_key' => $apiKeyResult,
            'existed' => false
        ];
    }

    /**
     * Consolida os resultados da orquestração
     */
    private function consolidateProvisioningResult(array $userResult, array $apiKeyResult, string $tenantId): array
    {
        return [
            'success' => true,
            'message' => sprintf(
                'Tenant credentials provisioned via centralized services - User: %s, API Key: %s',
                $userResult['existed'] ? 'existed' : 'created',
                $apiKeyResult['existed'] ? 'existed' : 'created'
            ),
            'user' => $userResult['user'],
            'api_key' => $apiKeyResult['api_key'],
            'tenant_id' => $tenantId,
            'orchestration' => [
                'user_existed' => $userResult['existed'],
                'api_key_existed' => $apiKeyResult['existed'],
                'services_used' => [
                    'user_service' => $this->userService->getName(),
                    'user_management_tenant_service' => get_class($this->userManagementTenantService),
                    'api_key_service' => get_class($this->apiKeyService)
                ]
            ]
        ];
    }

    /**
     * Garante que os serviços centralizados estão disponíveis
     */
    private function ensureCentralizedServicesAvailable(): void
    {
        if (!$this->userService) {
            throw new SDKException('UserService not injected. Call setCentralizedServices() first.');
        }

        if (!$this->userManagementTenantService) {
            throw new SDKException('UserManagement TenantService not injected. Call setCentralizedServices() first.');
        }

        if (!$this->apiKeyService) {
            throw new SDKException('ApiKeyService not injected. Call setCentralizedServices() first.');
        }

        if (!$this->organizationTenantService) {
            throw new SDKException('OrganizationTenantService not injected. Call setCentralizedServices() first.');
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
            $tenantResult = $this->makeHttpRequest('GET', "tenants/{$tenantId}");
                $tenantData = $tenantResult['data'] ?? $tenantResult;

                // Verificar se já tem API key nos dados do tenant
                if (isset($tenantData['api_key']) && !empty($tenantData['api_key'])) {
                    return [
                        'has_credentials' => true,
                        'api_key' => $tenantData['api_key'],
                        'message' => 'Tenant has API key available'
                    ];
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

    /**
     * Alternar para tenant específico (compatibilidade com exemplo)
     *
     * Este método é uma ponte para o método do SDK principal
     */
    public function switchToTenant(string $tenantId): array
    {
        $this->ensureInitialized();

        $this->logger->info('SuperAdmin: Switching to tenant context', [
            'tenant_id' => $tenantId
        ]);

        // Retornar um resultado simulado para compatibilidade
        // O contexto real deve ser gerenciado pelo SDK principal
        return [
            'success' => true,
            'message' => 'Tenant context switch requested - use SDK main switchToTenant() method',
            'current_tenant_id' => $tenantId,
            'current_role' => 'tenant_admin',
            'note' => 'This is a compatibility method. Use $sdk->switchToTenant() for actual switching.'
        ];
    }

    /**
     * Voltar para contexto de super admin (compatibilidade com exemplo)
     *
     * Este método é uma ponte para o método do SDK principal
     */
    public function switchToSuperAdmin(): array
    {
        $this->ensureInitialized();

        $this->logger->info('SuperAdmin: Switching to super admin context');

        // Retornar um resultado simulado para compatibilidade
        // O contexto real deve ser gerenciado pelo SDK principal
        return [
            'success' => true,
            'message' => 'Super admin context switch requested - use SDK main switchToSuperAdmin() method',
            'current_role' => 'super_admin',
            'note' => 'This is a compatibility method. Use $sdk->switchToSuperAdmin() for actual switching.'
        ];
    }

    /**
     * Gestão automatizada de credenciais para tenant
     *
     * Busca credenciais existentes e cria automaticamente se não existir.
     * Retorna sempre uma chave válida com role tenant_admin.
     */
    public function ensureTenantCredentials(string $tenantId, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            $this->logger->info('Starting automated tenant credentials management', [
                'tenant_id' => $tenantId,
                'options' => array_keys($options)
            ]);

            // 1. Buscar credenciais existentes
            $existingCredentials = $this->getTenantApiCredentials($tenantId);

            if ($existingCredentials) {
                $this->logger->info('Existing tenant credentials found', [
                    'tenant_id' => $tenantId,
                    'key_id' => $existingCredentials['api_key_id'] ?? 'N/A',
                    'age_days' => $existingCredentials['key_age_days'] ?? 'N/A'
                ]);

                // Verificar se precisa rotacionar
                $maxAge = $options['max_key_age_days'] ?? 90;
                $keyAge = $existingCredentials['key_age_days'] ?? 0;

                if (is_numeric($keyAge) && $keyAge > $maxAge && ($options['auto_rotate'] ?? false)) {
                    $this->logger->warning("API key is old ({$keyAge} days), rotating...", [
                        'tenant_id' => $tenantId,
                        'max_age' => $maxAge
                    ]);

                    $rotationResult = $this->rotateApiKey($existingCredentials['api_key_id'], array_merge([
                        'gracePeriodHours' => 24,
                        'forceRotation' => false
                    ], $options));

                    // Adaptar formato de resposta
                    return array_merge($existingCredentials, [
                        'api_key' => $rotationResult['new_api_key'] ?? $existingCredentials['api_key'],
                        'secret_key' => $rotationResult['new_secret_key'] ?? $existingCredentials['secret_key'],
                        'hash_key' => $rotationResult['new_hash_key'] ?? $existingCredentials['hash_key'],
                        'created_at' => date('Y-m-d H:i:s'),
                        'key_age_days' => 0,
                        'is_rotated' => true
                    ]);
                }

                return $existingCredentials;
            }

            // 2. Criar novas credenciais
            $this->logger->warning('No tenant credentials found, creating new API key', [
                'tenant_id' => $tenantId
            ]);

            return $this->createTenantApiKey($tenantId, $options);

        } catch (\Exception $e) {
            $this->logger->error('Failed to ensure tenant credentials', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to ensure tenant credentials: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Busca credenciais existentes do tenant (melhorado)
     */
    public function getTenantApiCredentials(string $tenantId): ?array
    {
        $this->ensureInitialized();

        try {
            $this->logger->debug('Searching for tenant API credentials', [
                'tenant_id' => $tenantId
            ]);

            // Buscar através da API do user-management-service
            $response = $this->makeHttpRequest('GET', "api-keys", [
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ],
                'query' => [
                    'limit' => 10 // Buscar mais para filtrar depois
                ]
            ]);

            if (!empty($response['api_keys'])) {
                // Filtrar chaves para encontrar a do tenant específico
                foreach ($response['api_keys'] as $key) {
                    // Verificar se é chave do tenant correto (assumindo que há campo tenant_id na resposta)
                    if (isset($key['tenant_id']) && $key['tenant_id'] === $tenantId) {
                        return [
                            'api_key' => $key['key'] ?? null,
                            'api_key_id' => $key['id'] ?? null,
                            'secret_key' => $key['secret'] ?? null,
                            'hash_key' => $key['hash'] ?? null,
                            'role' => $key['role'] ?? 'tenant_admin',
                            'permissions' => $key['permissions'] ?? [],
                            'scopes' => $key['scopes'] ?? [],
                            'created_at' => $key['created_at'] ?? null,
                            'key_age_days' => $this->calculateKeyAge($key['created_at'] ?? null),
                            'status' => $key['status'] ?? 'active'
                        ];
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->warning('Error searching for tenant API credentials', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Cria nova chave de API para tenant com permissões completas
     */
    public function createTenantApiKey(string $tenantId, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            $this->logger->info('Creating new tenant API key', [
                'tenant_id' => $tenantId
            ]);

            // Configuração seguindo o CreateApiKeyDto do user-management-service
            $keyConfig = array_merge([
                'name' => "Tenant Admin Key - " . date('Y-m-d H:i:s'),
                'description' => 'Auto-generated tenant admin key with full permissions',
                'environment' => 'test', // Valor válido conforme enum: 'test' | 'live'
                'allowedOrigins' => $options['allowed_origins'] ?? ['*'],
                'allowedIps' => !empty($options['ip_whitelist']) ? explode(',', $options['ip_whitelist']) : null,
                'permissions' => [
                    'tenants:read',
                    'tenants:write',
                    'tenants:delete',
                    'users:read',
                    'users:write',
                    'users:delete',
                    'orders:read',
                    'orders:write',
                    'orders:cancel',
                    'orders:refund',
                    'products:read',
                    'products:write',
                    'products:delete',
                    'products:publish',
                    'payments:process',
                    'payments:refund',
                    'payments:view',
                    'payments:export',
                    'analytics:view',
                    'analytics:export',
                    'analytics:configure',
                    'webhooks:read',
                    'webhooks:write',
                    'webhooks:delete',
                    'webhooks:test',
                    'api_keys:read',
                    'api_keys:write',
                    'api_keys:rotate',
                    'settings:read',
                    'settings:write',
                    'settings:configure'
                ],
                'rateLimiting' => [
                    'requestsPerMinute' => 1000,
                    'requestsPerHour' => 50000,
                    'requestsPerDay' => 1000000
                ]
            ], $options['key_config'] ?? []);

            // Criar via API do user-management-service
            $response = $this->makeHttpRequest('POST', 'api-keys', [
                'json' => $keyConfig,
                'headers' => [
                    'X-Tenant-Id' => $tenantId
                ]
            ]);

            if ($response['success'] ?? false) {
                $this->logger->info('Tenant API key created successfully', [
                    'tenant_id' => $tenantId,
                    'key_id' => $response['key_id'] ?? 'N/A'
                ]);

                return [
                    'api_key' => $response['data']['apiKey'] ?? $response['api_key'] ?? null,
                    'api_key_id' => $response['data']['keyId'] ?? $response['key_id'] ?? null,
                    'secret_key' => $response['data']['secret'] ?? $response['secret_key'] ?? null,
                    'hash_key' => $response['data']['hashKey'] ?? $response['hash_key'] ?? null,
                    'role' => 'tenant_admin',
                    'permissions' => $keyConfig['permissions'],
                    'scopes' => $keyConfig['permissions'], // Usar permissions como scopes
                    'created_at' => date('Y-m-d H:i:s'),
                    'key_age_days' => 0,
                    'status' => 'active',
                    'is_new' => true
                ];
            }

            throw new SDKException('Failed to create API key: API returned unsuccessful response');

        } catch (\Exception $e) {
            $this->logger->error('Failed to create tenant API key', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to create tenant API key: ' . $e->getMessage(), 0, $e);
        }
    }


    /**
     * Lista todas as chaves de API para um tenant
     */
    public function listApiKeys(array $filters = []): array
    {
        $this->ensureInitialized();

        try {
            $response = $this->makeHttpRequest('GET', 'api-keys', [
                'query' => $filters
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Failed to list API keys', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to list API keys: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Agenda revogação de uma chave de API
     */
    public function scheduleKeyRevocation(string $keyId, array $options = []): array
    {
        $this->ensureInitialized();

        try {
            $this->logger->info('Scheduling API key revocation', [
                'key_id' => $keyId,
                'delay_hours' => $options['delay_hours'] ?? 24
            ]);

            $revocationConfig = array_merge([
                'delay_hours' => 24,
                'reason' => 'Scheduled revocation after rotation'
            ], $options);

            $response = $this->makeHttpRequest('POST', "api-keys/{$keyId}/schedule-revocation", [
                'json' => $revocationConfig
            ]);

            return $response;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to schedule key revocation', [
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);

            // Não falhar se agendamento não funcionar
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcula a idade de uma chave em dias
     */
    private function calculateKeyAge(?string $createdAt): int
    {
        if (!$createdAt) {
            return 0;
        }

        try {
            $created = new \DateTime($createdAt);
            $now = new \DateTime();
            return (int) $created->diff($now)->days;
        } catch (\Exception $e) {
            return 0;
        }
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
