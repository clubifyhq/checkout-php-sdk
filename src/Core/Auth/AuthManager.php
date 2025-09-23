<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Security\SecurityValidator;
use Clubify\Checkout\Exceptions\AuthenticationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Gerenciador de autenticação do Clubify SDK
 *
 * Gerencia tokens JWT, refresh automático e headers de autorização.
 */
class AuthManager implements AuthManagerInterface
{
    private Client $httpClient;
    private ConfigurationInterface $config;
    private TokenStorageInterface $tokenStorage;
    private JWTHandler $jwtHandler;
    private ?array $userInfo = null;
    private ?CredentialManager $credentialManager = null;
    private string $currentRole = 'tenant_admin';

    public function __construct(
        Client $httpClient,
        ConfigurationInterface $config,
        ?TokenStorageInterface $tokenStorage = null,
        ?JWTHandler $jwtHandler = null,
        ?CredentialManager $credentialManager = null
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->tokenStorage = $tokenStorage ?? new TokenStorage();
        $this->jwtHandler = $jwtHandler ?? new JWTHandler();
        $this->credentialManager = $credentialManager;
    }

    public function authenticate(?string $tenantId = null, ?string $apiKey = null): bool
    {
        $tenantId = $tenantId ?? $this->config->getTenantId();
        $apiKey = $apiKey ?? $this->config->getApiKey();

        if (!$tenantId || !$apiKey) {
            throw new AuthenticationException('Tenant ID and API key are required for authentication');
        }

        // Security: Validate API key format before processing
        if (!SecurityValidator::validateApiKey($apiKey)) {
            throw new AuthenticationException('Invalid API key format');
        }

        // Security: Validate tenant ID format
        if (!SecurityValidator::validateUuid($tenantId)) {
            throw new AuthenticationException('Invalid tenant ID format');
        }

        try {
            // Configuração validada - removido debug logging por segurança

            // Tentar autenticar via API key e obter access token
            $tokenResult = $this->authenticateWithApiKey($apiKey, $tenantId);

            if ($tokenResult) {
                return true;
            }

            // Fallback: Apenas validar a API key (sem access token)
            $isValidApiKey = $this->validateApiKey($apiKey, $tenantId);

            if (!$isValidApiKey) {
                throw new AuthenticationException('Invalid API key or tenant ID');
            }

            // Armazenar informações básicas do tenant (sem access token)
            $this->userInfo = [
                'tenant_id' => $tenantId,
                'api_key' => substr($apiKey, 0, 10) . '...',
                'environment' => $this->config->getEnvironment(),
                'authenticated_at' => date('Y-m-d H:i:s'),
                'auth_type' => 'api_key_validated',
                'requires_user_login' => true
            ];

            return true;

        } catch (\Exception $e) {
            throw new AuthenticationException(
                'Authentication failed: ' . $e->getMessage(),
                500,
                $e,
                ['tenant_id' => $tenantId]
            );
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->tokenStorage->hasValidAccessToken();
    }

    /**
     * Verifica se a API key foi validada (mas pode não ter access token)
     */
    public function isApiKeyValidated(): bool
    {
        return $this->userInfo !== null &&
               isset($this->userInfo['auth_type']) &&
               $this->userInfo['auth_type'] === 'api_key_validated';
    }

    /**
     * Verifica se precisa fazer login de usuário
     */
    public function requiresUserLogin(): bool
    {
        return !$this->isAuthenticated() && $this->isApiKeyValidated();
    }

    public function getAccessToken(): ?string
    {
        // Tentar refresh automático se necessário
        if ($this->shouldRefreshToken()) {
            $this->refreshToken();
        }

        $token = $this->tokenStorage->getAccessToken();
        // Security: Remove debug logging that could expose sensitive tokens
        return $token;
    }

    public function getRefreshToken(): ?string
    {
        return $this->tokenStorage->getRefreshToken();
    }

    public function refreshToken(): bool
    {
        $refreshToken = $this->tokenStorage->getRefreshToken();

        if (!$refreshToken) {
            throw new AuthenticationException('No refresh token available');
        }

        try {
            $response = $this->httpClient->post('auth/refresh', [
                'json' => [
                    'refreshToken' => $refreshToken
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new AuthenticationException('Token refresh failed: Invalid refresh token');
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['accessToken'])) {
                throw new AuthenticationException('Invalid refresh response: missing access token');
            }

            // Armazenar novo access token
            $this->tokenStorage->storeAccessToken(
                $data['accessToken'],
                $data['expiresIn'] ?? 3600
            );

            // Atualizar refresh token se fornecido (token rotation)
            if (isset($data['refreshToken'])) {
                $this->tokenStorage->storeRefreshToken($data['refreshToken']);
            }

            return true;

        } catch (HttpException $e) {
            // Se refresh falhou, limpar tokens
            $this->logout();

            throw new AuthenticationException(
                'Token refresh failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function logout(): void
    {
        $this->tokenStorage->clear();
        $this->userInfo = null;
    }

    public function getAuthorizationHeader(): array
    {
        $token = $this->getAccessToken();

        if (!$token) {
            return [];
        }

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    public function isTokenExpired(): bool
    {
        return $this->tokenStorage->isAccessTokenExpired();
    }

    public function getUserInfo(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        if ($this->userInfo === null) {
            $this->loadUserInfo();
        }

        return $this->userInfo;
    }

    public function willExpireIn(int $seconds): bool
    {
        return $this->tokenStorage->willAccessTokenExpireIn($seconds);
    }

    /**
     * Verificar se deve fazer refresh do token
     */
    private function shouldRefreshToken(): bool
    {
        // Se não tem access token válido e tem refresh token
        if (!$this->tokenStorage->hasValidAccessToken() && $this->tokenStorage->hasRefreshToken()) {
            return true;
        }

        // Se access token vai expirar em menos de 5 minutos
        if ($this->tokenStorage->willAccessTokenExpireIn(300)) {
            return true;
        }

        return false;
    }

    /**
     * Carregar informações do usuário autenticado
     */
    private function loadUserInfo(): void
    {
        if (!$this->isAuthenticated()) {
            return;
        }

        try {
            // Tentar obter do token JWT primeiro
            $token = $this->tokenStorage->getAccessToken();
            if ($token) {
                $payload = $this->jwtHandler->getUnsafePayload($token);
                $this->userInfo = [
                    'tenant_id' => $payload['tenant_id'] ?? null,
                    'user_id' => $payload['sub'] ?? null,
                    'email' => $payload['email'] ?? null,
                    'name' => $payload['name'] ?? null,
                    'roles' => $payload['roles'] ?? [],
                    'exp' => $payload['exp'] ?? null,
                ];
                return;
            }

            // Fallback: fazer requisição à API
            $response = $this->httpClient->get('/auth/me');
            $this->userInfo = json_decode((string) $response->getBody(), true);

        } catch (\Exception) {
            // Se falhar, usar dados mínimos
            $this->userInfo = [
                'tenant_id' => $this->config->getTenantId(),
            ];
        }
    }

    /**
     * Obter token storage (para testes ou configuração customizada)
     */
    public function getTokenStorage(): TokenStorageInterface
    {
        return $this->tokenStorage;
    }

    /**
     * Definir token storage customizado
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Verificar se tem permissão específica
     */
    public function hasPermission(string $permission): bool
    {
        $userInfo = $this->getUserInfo();

        if (!$userInfo || !isset($userInfo['roles'])) {
            return false;
        }

        // Verificar se tem a permissão diretamente ou via role
        $roles = $userInfo['roles'];

        return in_array($permission, $roles) ||
               in_array('admin', $roles) ||
               in_array('super_admin', $roles);
    }

    /**
     * Obter tenant ID do usuário autenticado
     */
    public function getAuthenticatedTenantId(): ?string
    {
        $userInfo = $this->getUserInfo();
        return $userInfo['tenant_id'] ?? null;
    }

    /**
     * Validar API key via endpoint
     */
    private function validateApiKey(string $apiKey, string $tenantId): bool
    {
        try {
            // Fazer requisição com payload (X-Tenant-ID já incluído nos headers padrão)
            $response = $this->httpClient->post('api-keys/public/validate', [
                'json' => [
                    'apiKey' => $apiKey,
                    'endpoint' => '/users',
                    'clientIp' => '127.0.0.1'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                // Log do erro para debug
                // API Key validation failed
                return false;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $isValid = ($data['isValid'] ?? false);
            $returnedTenantId = ($data['tenantId'] ?? null);

            // API Key validation completed

            return $isValid && $returnedTenantId === $tenantId;

        } catch (\Exception $e) {
            // Log do erro para debug
            // API Key validation exception occurred

            // Se endpoint não existe ou falha, fazer fallback
            // Para compatibilidade, considerar válido se API key tem formato correto
            return $this->isValidApiKeyFormat($apiKey);
        }
    }

    /**
     * Verificar formato da API key - Enhanced with SecurityValidator
     */
    private function isValidApiKeyFormat(string $apiKey): bool
    {
        return SecurityValidator::validateApiKey($apiKey);
    }

    /**
     * Login com usuário e senha (retorna access/refresh tokens reais)
     */
    public function login(string $email, string $password, ?string $tenantId = null, ?string $deviceFingerprint = null): array
    {
        // Security: Validate inputs
        if (!SecurityValidator::validateEmail($email)) {
            throw new AuthenticationException('Invalid email format');
        }

        $passwordValidation = SecurityValidator::validatePasswordStrength($password);
        if (!$passwordValidation['valid']) {
            throw new AuthenticationException('Password does not meet security requirements: ' . implode(', ', $passwordValidation['errors']));
        }

        $tenantId = $tenantId ?? $this->config->getTenantId();

        if (!$tenantId) {
            throw new AuthenticationException('Tenant ID is required for login');
        }

        // Security: Validate tenant ID format
        if (!SecurityValidator::validateUuid($tenantId)) {
            throw new AuthenticationException('Invalid tenant ID format');
        }

        // Security: Rate limiting for login attempts
        $identifier = $email . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (!SecurityValidator::checkRateLimit($identifier, 5, 900)) { // 5 attempts per 15 minutes
            throw new AuthenticationException('Too many login attempts. Please try again later.');
        }

        try {
            $loginData = [
                'email' => $email,
                'password' => $password
            ];

            if ($deviceFingerprint) {
                $loginData['deviceFingerprint'] = $deviceFingerprint;
            }

            // Fazer login via endpoint correto (X-Tenant-ID já incluído nos headers padrão)
            $response = $this->httpClient->post('auth/login', [
                'json' => $loginData
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new AuthenticationException('Login failed: Invalid credentials');
            }

            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['accessToken']) || !isset($data['refreshToken'])) {
                throw new AuthenticationException('Invalid login response: missing tokens');
            }

            // Armazenar tokens reais
            $this->tokenStorage->storeAccessToken(
                $data['accessToken'],
                $data['expiresIn'] ?? 3600
            );

            $this->tokenStorage->storeRefreshToken($data['refreshToken']);

            // Armazenar informações do usuário
            $this->userInfo = [
                'user_id' => $data['user']['id'] ?? null,
                'email' => $data['user']['email'] ?? $email,
                'name' => $data['user']['name'] ?? null,
                'tenant_id' => $tenantId,
                'roles' => $data['user']['roles'] ?? [],
                'authenticated_at' => date('Y-m-d H:i:s'),
                'auth_type' => 'user_login'
            ];

            return $data;

        } catch (HttpException $e) {
            throw new AuthenticationException(
                'Login failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                ['email' => $email, 'tenant_id' => $tenantId]
            );
        }
    }

    /**
     * Tenta autenticar usando API key para obter access token
     */
    private function authenticateWithApiKey(string $apiKey, string $tenantId): bool
    {
        try {
            // Simplified authentication flow - single endpoint strategy
            $endpoint = $this->getAuthEndpointForContext($tenantId);

            $response = $this->httpClient->post($endpoint, [
                'json' => [
                    'api_key' => $apiKey,
                    'tenant_id' => $tenantId,
                    'grant_type' => 'api_key'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 200 && $statusCode < 300) {
                $data = json_decode($response->getBody()->getContents(), true);

                if (isset($data['access_token']) || isset($data['accessToken'])) {
                    $accessToken = $data['access_token'] ?? $data['accessToken'];
                    $refreshToken = $data['refresh_token'] ?? $data['refreshToken'] ?? null;
                    $expiresIn = $data['expires_in'] ?? $data['expiresIn'] ?? 3600;

                    // Armazenar tokens
                    $this->tokenStorage->storeAccessToken($accessToken, $expiresIn);
                    if ($refreshToken) {
                        $this->tokenStorage->storeRefreshToken($refreshToken);
                    }

                    // Armazenar informações do usuário (sem dados sensíveis)
                    $this->userInfo = [
                        'tenant_id' => $tenantId,
                        'environment' => $this->config->getEnvironment(),
                        'authenticated_at' => date('Y-m-d H:i:s'),
                        'auth_type' => 'api_key_token',
                        'requires_user_login' => false
                    ];

                    // Authentication successful
                    return true;
                }
            }

            // Authentication failed - invalid response
            return false;

        } catch (\Exception $e) {
            // Authentication failed with exception
            return false;
        }
    }

    /**
     * Autenticar como super admin
     */
    public function authenticateAsSuperAdmin(array $credentials): bool
    {
        if ($this->credentialManager === null) {
            // Criar storage temporário se não foi fornecido
            $storageDir = sys_get_temp_dir() . '/clubify_auth_storage';
            $encryptionKey = hash('sha256', 'clubify_auth_encryption_key_' . time());
            $storage = new EncryptedFileStorage($storageDir, $encryptionKey);
            $this->credentialManager = new CredentialManager($storage);
        }

        // Validar transição de role para super admin
        $this->validateRoleTransition($this->currentRole, 'super_admin');

        // Autenticar usando credenciais super admin
        $authResult = $this->authenticateWithSuperAdminCredentials($credentials);

        if ($authResult) {
            $this->credentialManager->addSuperAdminContext($credentials);
            $this->credentialManager->switchContext('super_admin');

            // Log role transition para auditoria
            $this->logRoleTransition($this->currentRole, 'super_admin');

            $this->currentRole = 'super_admin';
            return true;
        }

        return false;
    }

    /**
     * Criar credenciais de tenant (apenas super admin)
     */
    public function createTenantCredentials(string $organizationName, array $tenantData): array
    {
        $this->requireSuperAdminRole();

        // Criar tenant e usuário tenant_admin via API (baseado no CreateTenantDto)
        $requestData = [
            'name' => $organizationName,
            'domain' => $tenantData['custom_domain'] ?? $tenantData['subdomain'] . '.clubify.com',
            'subdomain' => $tenantData['subdomain'],
            'description' => $tenantData['description'] ?? null,
            'plan' => $tenantData['plan'] ?? 'starter',
            'contact' => [
                'email' => $tenantData['admin_email'],
                'phone' => $tenantData['admin_phone'] ?? null,
                'website' => $tenantData['website'] ?? null,
                'supportEmail' => $tenantData['support_email'] ?? null
            ]
            // Removidos campos não aceitos pelo DTO: settings, features, initialUser
        ];

        // Security: Remove sensitive data logging in production

        $response = $this->httpClient->post('tenants', [
            'json' => $requestData
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AuthenticationException('Failed to create tenant credentials');
        }

        $data = json_decode($response->getBody()->getContents(), true);
        // Security: Remove sensitive response logging in production

        // Verificar se a resposta tem a estrutura esperada
        if (isset($data['success']) && $data['success'] && isset($data['data'])) {
            $tenantData = $data['data'];
            $tenantId = $tenantData['_id'] ?? $tenantData['id'] ?? null;

            if ($tenantId) {
                // Adicionar contexto do novo tenant (sem API key pois não é retornada na criação)
                $this->credentialManager->addTenantContext($tenantId, [
                    'tenant_id' => $tenantId,
                    'name' => $tenantData['name'] ?? null,
                    'domain' => $tenantData['domain'] ?? null,
                    'subdomain' => $tenantData['subdomain'] ?? null,
                    'created_at' => time()
                ]);
            }

            return [
                'success' => true,
                'tenant' => $tenantData,
                'organization' => $tenantData // Alias para compatibilidade
            ];
        }

        return $data;
    }

    /**
     * Registrar tenant existente para permitir alternância de contexto
     */
    public function registerExistingTenant(string $tenantId, array $tenantData = []): array
    {
        if ($this->credentialManager === null) {
            throw new AuthenticationException('Credential manager not initialized');
        }

        // Validar entrada
        if (empty($tenantId) || !is_string($tenantId)) {
            throw new AuthenticationException('Valid tenant ID is required');
        }

        // Se o contexto já existe, retornar informações sobre ele
        if ($this->credentialManager->hasContext($tenantId)) {
            return [
                'success' => true,
                'existed' => true,
                'has_api_key' => $this->credentialManager->hasValidApiKey($tenantId),
                'message' => 'Tenant context already registered'
            ];
        }

        $registrationResult = [
            'success' => false,
            'existed' => false,
            'has_api_key' => false,
            'message' => '',
            'warnings' => []
        ];

        try {
            // Primeiro validar se tenant existe através da API
            $tenantExists = $this->validateTenantExists($tenantId);
            if (!$tenantExists) {
                throw new AuthenticationException("Tenant {$tenantId} does not exist or is not accessible");
            }

            // Tentar obter credenciais completas com estratégias múltiplas
            $apiKey = $this->resolveTenantApiKey($tenantId, $tenantData);

            // Registrar contexto com as informações disponíveis
            $this->credentialManager->addTenantContext($tenantId, [
                'tenant_id' => $tenantId,
                'name' => $tenantData['name'] ?? "Tenant {$tenantId}",
                'domain' => $tenantData['domain'] ?? $tenantData['custom_domain'] ?? null,
                'subdomain' => $tenantData['subdomain'] ?? null,
                'api_key' => $apiKey,
                'created_at' => time(),
                'registration_method' => 'existing_tenant'
            ]);

            $registrationResult['success'] = true;
            $registrationResult['has_api_key'] = !empty($apiKey);
            $registrationResult['message'] = 'Tenant context registered successfully';

            if (empty($apiKey)) {
                $registrationResult['warnings'][] = 'No API key available - context switching will be limited';
            }

        } catch (AuthenticationException $e) {
            // Re-throw authentication exceptions
            throw $e;
        } catch (\Exception $e) {
            // Security: Log registration errors without exposing sensitive data
            $this->logSecurityEvent('tenant_registration_error', [
                'tenant_id' => $tenantId,
                'error_type' => get_class($e)
            ]);

            // Register with limited information
            $this->credentialManager->addTenantContext($tenantId, [
                'tenant_id' => $tenantId,
                'name' => $tenantData['name'] ?? "Tenant {$tenantId}",
                'domain' => $tenantData['domain'] ?? $tenantData['custom_domain'] ?? null,
                'subdomain' => $tenantData['subdomain'] ?? null,
                'api_key' => null,
                'created_at' => time(),
                'registration_method' => 'fallback'
            ]);

            $registrationResult['success'] = true;
            $registrationResult['has_api_key'] = false;
            $registrationResult['message'] = 'Tenant context registered with limited information';
            $registrationResult['warnings'][] = 'Could not retrieve full tenant credentials';
        }

        return $registrationResult;
    }

    /**
     * Validar se tenant existe
     */
    private function validateTenantExists(string $tenantId): bool
    {
        try {
            $response = $this->httpClient->get("tenants/{$tenantId}");
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resolver API key do tenant usando múltiplas estratégias
     */
    private function resolveTenantApiKey(string $tenantId, array $tenantData): ?string
    {
        // Estratégia 1: Usar API key fornecida nos dados
        if (!empty($tenantData['api_key'])) {
            return $tenantData['api_key'];
        }

        // Estratégia 2: Tentar obter da API
        try {
            $response = $this->httpClient->get("tenants/{$tenantId}");
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $data = json_decode($response->getBody()->getContents(), true);
                $tenantInfo = $data['data'] ?? $data;
                return $tenantInfo['api_key'] ?? null;
            }
        } catch (\Exception $e) {
            // Falhou silenciosamente, tentará próxima estratégia
        }

        // Estratégia 3: Verificar se existe em cache/storage local
        // TODO: Implementar se houver cache local

        return null;
    }

    /**
     * Alternar para tenant específico com validações robustas
     */
    public function switchToTenant(string $tenantId): array
    {
        if ($this->credentialManager === null) {
            throw new AuthenticationException('Credential manager not initialized');
        }

        // Validar entrada
        if (empty($tenantId)) {
            throw new AuthenticationException('Tenant ID is required');
        }

        // Verificar se contexto existe
        if (!$this->credentialManager->hasContext($tenantId)) {
            throw new AuthenticationException("Tenant context {$tenantId} not found. Register tenant first using registerExistingTenant()");
        }

        // Salvar estado atual para rollback se necessário
        $previousContext = $this->credentialManager->getActiveContext();
        $previousRole = $this->currentRole;

        try {
            // Verificar se tem API key válida
            if (!$this->credentialManager->hasValidApiKey($tenantId)) {
                // Try to obtain credentials for this tenant if missing
                $credentialResult = $this->attemptCredentialRetrieval($tenantId);

                if (!$credentialResult['success']) {
                    throw new AuthenticationException("Tenant {$tenantId} does not have valid API key for authentication. " . $credentialResult['message']);
                }
            }

            // Alternar contexto
            $this->credentialManager->switchContext($tenantId);
            $this->currentRole = 'tenant_admin';

            // Obter credenciais do novo contexto
            $credentials = $this->credentialManager->getCurrentCredentials();

            // Validar que as credenciais são válidas
            if (empty($credentials['tenant_id']) || empty($credentials['api_key'])) {
                throw new AuthenticationException('Invalid tenant credentials after context switch');
            }

            // Atualizar configuração
            $this->config->set('credentials.tenant_id', $credentials['tenant_id']);
            $this->config->set('credentials.api_key', $credentials['api_key']);

            // Log da mudança para auditoria
            $this->logRoleTransition('super_admin', 'tenant_admin', [
                'tenant_id' => $tenantId,
                'switch_method' => 'manual'
            ]);

            return [
                'success' => true,
                'previous_context' => $previousContext,
                'current_context' => $tenantId,
                'current_role' => $this->currentRole,
                'message' => "Successfully switched to tenant {$tenantId}"
            ];

        } catch (\Exception $e) {
            // Rollback em caso de erro
            try {
                if ($previousContext) {
                    $this->credentialManager->switchContext($previousContext);
                }
                $this->currentRole = $previousRole;
            } catch (\Exception $rollbackError) {
                // Security: Log rollback failures without exposing sensitive data
                $this->logSecurityEvent('context_rollback_failure', [
                    'error_type' => get_class($rollbackError)
                ]);
            }

            throw new AuthenticationException("Failed to switch to tenant {$tenantId}: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Attempt to retrieve credentials for a tenant that doesn't have API key
     */
    private function attemptCredentialRetrieval(string $tenantId): array
    {
        try {
            // First try to get API keys for this tenant
            $response = $this->httpClient->get("api-keys");

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $data = json_decode($response->getBody()->getContents(), true);
                $apiKeys = $data['data'] ?? $data['api_keys'] ?? $data;

                if (is_array($apiKeys) && count($apiKeys) > 0) {
                    $apiKey = $apiKeys[0] ?? null;
                    if ($apiKey && isset($apiKey['key'])) {
                        // Update the credential manager with the found API key
                        $this->credentialManager->addTenantContext($tenantId, [
                            'tenant_id' => $tenantId,
                            'api_key' => $apiKey['key'],
                            'api_key_id' => $apiKey['id'] ?? $apiKey['_id'] ?? null,
                            'name' => $apiKey['name'] ?? "Tenant {$tenantId}"
                        ]);

                        return [
                            'success' => true,
                            'message' => 'API key found and credentials updated'
                        ];
                    }
                }
            }

            // Fallback: try to get tenant info to see if it has embedded api_key
            $tenantResponse = $this->httpClient->get("tenants/{$tenantId}");
            if ($tenantResponse->getStatusCode() >= 200 && $tenantResponse->getStatusCode() < 300) {
                $tenantData = json_decode($tenantResponse->getBody()->getContents(), true);
                $tenant = $tenantData['data'] ?? $tenantData;

                if (isset($tenant['api_key']) && !empty($tenant['api_key'])) {
                    $this->credentialManager->addTenantContext($tenantId, [
                        'tenant_id' => $tenantId,
                        'api_key' => $tenant['api_key'],
                        'name' => $tenant['name'] ?? "Tenant {$tenantId}",
                        'domain' => $tenant['domain'] ?? null,
                        'subdomain' => $tenant['subdomain'] ?? null
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Credentials retrieved from tenant data'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'No API key found for tenant - try using super admin to provision credentials'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve tenant credentials: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Alternar para super admin
     */
    public function switchToSuperAdmin(): void
    {
        if ($this->credentialManager === null) {
            throw new AuthenticationException('Credential manager not initialized');
        }

        if (!$this->credentialManager->hasContext('super_admin')) {
            throw new AuthenticationException('Super admin context not found');
        }

        $this->credentialManager->switchContext('super_admin');
        $this->currentRole = 'super_admin';
    }

    /**
     * Obter role atual
     */
    public function getCurrentRole(): string
    {
        return $this->currentRole;
    }

    /**
     * Verificar se está em modo super admin
     */
    public function isSuperAdminMode(): bool
    {
        return $this->currentRole === 'super_admin' &&
               $this->credentialManager !== null &&
               $this->credentialManager->isSuperAdminMode();
    }

    /**
     * Obter contextos disponíveis
     */
    public function getAvailableContexts(): array
    {
        if ($this->credentialManager === null) {
            return [];
        }

        return $this->credentialManager->getContextStats();
    }

    /**
     * Definir credential manager
     */
    public function setCredentialManager(CredentialManager $credentialManager): void
    {
        $this->credentialManager = $credentialManager;
    }

    /**
     * Obter credential manager
     */
    public function getCredentialManager(): ?CredentialManager
    {
        return $this->credentialManager;
    }

    /**
     * Validar se é super admin
     */
    private function requireSuperAdminRole(): void
    {
        if ($this->currentRole !== 'super_admin') {
            throw new AuthenticationException('Super admin role required for this operation');
        }
    }

    /**
     * Autenticar com credenciais de super admin
     */
    private function authenticateWithSuperAdminCredentials(array $credentials): bool
    {
        // Tentar primeiro com API key se disponível
        if (isset($credentials['api_key'])) {
            try {
                $requestData = [
                    'api_key' => $credentials['api_key'],
                    'tenant_id' => $credentials['tenant_id'] ?? $this->config->getTenantId(),
                    'grant_type' => 'api_key'
                ];

                // Security: Remove sensitive debug logging
                // Tentar autenticar via endpoint de API key do user-management-service
                $response = $this->httpClient->post('auth/api-key/token', [
                    'json' => $requestData
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    return $this->storeAuthTokens($data, $credentials, 'api_key');
                }

            } catch (\Exception $e) {
                // Security: Avoid exposing sensitive authentication details in logs
                $this->logSecurityEvent('auth_failure', [
                    'method' => 'api_key',
                    'error_type' => get_class($e)
                ]);
            }
        }

        // Fallback: tentar com credenciais de usuário/senha
        if (isset($credentials['email']) && isset($credentials['password'])) {
            try {
                $loginData = [
                    'email' => $credentials['email'],
                    'password' => $credentials['password'],
                    'rememberMe' => true
                ];

                // Security: Remove sensitive debug logging
                // Tentar autenticar via endpoint de login
                $response = $this->httpClient->post('auth/login', [
                    'json' => $loginData,
                    'headers' => [
                        'X-Tenant-ID' => $credentials['tenant_id'] ?? $this->config->getTenantId()
                    ]
                ]);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 200 && $statusCode < 300) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    return $this->storeAuthTokens($data, $credentials, 'login');
                }

            } catch (\Exception $e) {
                // Security: Avoid exposing sensitive authentication details in logs
                $this->logSecurityEvent('auth_failure', [
                    'method' => 'login',
                    'error_type' => get_class($e)
                ]);
            }
        }

        // Security: Log authentication failure securely
        $this->logSecurityEvent('auth_failure', [
            'method' => 'all_methods_failed',
            'available_methods' => array_keys(array_filter([
                'api_key' => isset($credentials['api_key']),
                'login' => isset($credentials['email'], $credentials['password'])
            ]))
        ]);
        return false;
    }

    /**
     * Armazenar tokens de autenticação
     */
    private function storeAuthTokens(array $data, array $credentials, string $authType): bool
    {
        // Extrair tokens dependendo do formato da resposta
        $accessToken = $data['access_token'] ?? $data['tokens']['accessToken'] ?? null;
        $refreshToken = $data['refresh_token'] ?? $data['tokens']['refreshToken'] ?? null;
        $expiresIn = $data['expires_in'] ?? 3600;

        // Security: Remove sensitive token logging

        // Se retornou tokens, armazenar
        if ($accessToken) {
            $this->tokenStorage->storeAccessToken($accessToken, $expiresIn);
        }

        if ($refreshToken) {
            $this->tokenStorage->storeRefreshToken($refreshToken);
        }

        // Armazenar informações do super admin
        $this->userInfo = [
            'user_id' => $data['user']['id'] ?? null,
            'username' => $data['user']['username'] ?? $credentials['username'] ?? $credentials['email'],
            'email' => $data['user']['email'] ?? $credentials['email'],
            'role' => 'super_admin',
            'permissions' => $data['user']['permissions'] ?? [],
            'authenticated_at' => date('Y-m-d H:i:s'),
            'auth_type' => $authType
        ];

        return true;
    }

    /**
     * Validar transição de roles com controles de segurança
     */
    private function validateRoleTransition(string $fromRole, string $toRole): void
    {
        // Validar escalação de privilégios para super admin
        if ($toRole === 'super_admin' && $fromRole !== 'super_admin') {
            // Durante inicialização como super admin, permitir transição se credenciais forem fornecidas
            // A validação real das credenciais acontece em authenticateWithSuperAdminCredentials
            if ($fromRole === 'tenant_admin' && !$this->hasValidSuperAdminCredentials()) {
                // Permitir inicialização inicial - validação será feita depois
                return;
            }

            // Verificar rate limiting para transições de super admin
            if ($this->isSuperAdminTransitionRateLimited()) {
                throw new AuthenticationException('Super admin transition rate limit exceeded');
            }
        }

        // Validar downgrades de role
        if ($fromRole === 'super_admin' && $toRole !== 'super_admin') {
            // Log downgrade para auditoria
            $this->logSecurityEvent('role_downgrade', [
                'from_role' => $fromRole,
                'to_role' => $toRole,
                'timestamp' => time()
            ]);
        }

        // Validar contexto atual
        if ($fromRole !== 'guest' && !$this->credentialManager->hasContext($fromRole)) {
            throw new AuthenticationException("Invalid current role context: {$fromRole}");
        }
    }

    /**
     * Log role transitions para auditoria
     */
    private function logRoleTransition(string $fromRole, string $toRole): void
    {
        $event = [
            'event' => 'role_transition',
            'from_role' => $fromRole,
            'to_role' => $toRole,
            'user_id' => $this->getCurrentUserId(),
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];

        // Use structured logging
        if (function_exists('logger')) {
            try {
                logger()->info('Role transition', $event);
            } catch (\Throwable $e) {
                // Fallback se houver problema com o logger
                error_log('SECURITY: Role transition - ' . json_encode($event));
            }
        } else {
            // Fallback para sistemas sem Laravel
            error_log('SECURITY: Role transition - ' . json_encode($event));
        }
    }

    /**
     * Verificar se possui credenciais válidas de super admin
     */
    private function hasValidSuperAdminCredentials(): bool
    {
        return $this->credentialManager &&
               $this->credentialManager->hasContext('super_admin') &&
               $this->credentialManager->isSuperAdminMode();
    }

    /**
     * Verificar rate limiting para transições super admin
     */
    private function isSuperAdminTransitionRateLimited(): bool
    {
        // Implementação simples - máximo 5 tentativas por hora
        $cacheKey = 'super_admin_transitions:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

        // Se não há sistema de cache, permitir (fail-open)
        if (!function_exists('cache')) {
            return false;
        }

        $attempts = cache($cacheKey, 0);

        if ($attempts >= 5) {
            return true;
        }

        // Incrementar contador
        cache([$cacheKey => $attempts + 1], 3600); // 1 hora TTL

        return false;
    }

    /**
     * Obter ID do usuário atual para auditoria
     */
    private function getCurrentUserId(): ?string
    {
        $credentials = $this->credentialManager->getCurrentCredentials();
        return $credentials['user_id'] ?? $credentials['username'] ?? null;
    }

    /**
     * Log eventos de segurança
     */
    private function logSecurityEvent(string $eventType, array $data): void
    {
        $event = array_merge([
            'event_type' => $eventType,
            'timestamp' => time(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $data);

        if (function_exists('logger')) {
            try {
                logger()->warning("Security event: {$eventType}", $event);
            } catch (\Throwable $e) {
                // Fallback se houver problema com o logger
                error_log('SECURITY: ' . $eventType . ' - ' . json_encode($event));
            }
        } else {
            error_log('SECURITY: ' . $eventType . ' - ' . json_encode($event));
        }
    }

    /**
     * Determinar endpoint de autenticação baseado no contexto
     */
    private function getAuthEndpointForContext(string $tenantId): string
    {
        // FIXED: Always use the correct API key authentication endpoint
        // The endpoint /api/v1/auth/api-key/token is the only one implemented
        return 'auth/api-key/token';
    }
}
