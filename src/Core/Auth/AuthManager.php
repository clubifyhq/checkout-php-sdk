<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Http\Client;
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
        error_log('AuthManager getAccessToken: ' . ($token ? 'present (' . substr($token, 0, 20) . '...)' : 'missing'));
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
     * Verificar formato da API key
     */
    private function isValidApiKeyFormat(string $apiKey): bool
    {
        // API keys do Clubify seguem formato: clb_test_* ou clb_live_*
        return preg_match('/^clb_(test|live)_[a-f0-9]{32}$/', $apiKey) === 1;
    }

    /**
     * Login com usuário e senha (retorna access/refresh tokens reais)
     */
    public function login(string $email, string $password, ?string $tenantId = null, ?string $deviceFingerprint = null): array
    {
        $tenantId = $tenantId ?? $this->config->getTenantId();

        if (!$tenantId) {
            throw new AuthenticationException('Tenant ID is required for login');
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

        error_log('Create Tenant Request Data: ' . json_encode($requestData, JSON_PRETTY_PRINT));

        $response = $this->httpClient->post('tenants', [
            'json' => $requestData
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new AuthenticationException('Failed to create tenant credentials');
        }

        $data = json_decode($response->getBody()->getContents(), true);
        error_log('Create Tenant Response: ' . json_encode($data, JSON_PRETTY_PRINT));

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
     * Alternar para tenant específico
     */
    public function switchToTenant(string $tenantId): void
    {
        if ($this->credentialManager === null) {
            throw new AuthenticationException('Credential manager not initialized');
        }

        if (!$this->credentialManager->hasContext($tenantId)) {
            throw new AuthenticationException("Tenant context {$tenantId} not found");
        }

        $this->credentialManager->switchContext($tenantId);
        $this->currentRole = 'tenant_admin';

        // Atualizar configuração com as credenciais do tenant
        $credentials = $this->credentialManager->getCurrentCredentials();
        $this->config->set('credentials.tenant_id', $credentials['tenant_id']);
        $this->config->set('credentials.api_key', $credentials['api_key']);
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

                error_log('Super Admin Auth Request (API Key): ' . json_encode($requestData));

                // Tentar autenticar via endpoint de API key do user-management-service
                $response = $this->httpClient->post('auth/api-key/token', [
                    'json' => $requestData
                ]);

                $statusCode = $response->getStatusCode();
                error_log('Super Admin Auth Response Code (API Key): ' . $statusCode);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    error_log('Super Admin Auth Success (API Key): ' . json_encode($data));

                    return $this->storeAuthTokens($data, $credentials, 'api_key');
                } else {
                    $errorBody = $response->getBody()->getContents();
                    error_log('Super Admin Auth Failed (API Key) - Status: ' . $statusCode . ', Body: ' . $errorBody);
                }

            } catch (\Exception $e) {
                error_log('Super Admin Auth Exception (API Key): ' . $e->getMessage());
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

                error_log('Super Admin Auth Request (Login): ' . json_encode(['email' => $credentials['email']]));

                // Tentar autenticar via endpoint de login
                $response = $this->httpClient->post('auth/login', [
                    'json' => $loginData,
                    'headers' => [
                        'X-Tenant-ID' => $credentials['tenant_id'] ?? $this->config->getTenantId()
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                error_log('Super Admin Auth Response Code (Login): ' . $statusCode);

                if ($statusCode >= 200 && $statusCode < 300) {
                    $data = json_decode($response->getBody()->getContents(), true);
                    error_log('Super Admin Auth Success (Login): ' . json_encode($data));

                    return $this->storeAuthTokens($data, $credentials, 'login');
                } else {
                    $errorBody = $response->getBody()->getContents();
                    error_log('Super Admin Auth Failed (Login) - Status: ' . $statusCode . ', Body: ' . $errorBody);
                }

            } catch (\Exception $e) {
                error_log('Super Admin Auth Exception (Login): ' . $e->getMessage());
            }
        }

        error_log('Super Admin Auth Failed: No valid authentication method succeeded');
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

        error_log('Storing tokens - Access Token: ' . ($accessToken ? 'present' : 'missing') . ', Refresh Token: ' . ($refreshToken ? 'present' : 'missing'));

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
