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

    public function __construct(
        Client $httpClient,
        ConfigurationInterface $config,
        ?TokenStorageInterface $tokenStorage = null,
        ?JWTHandler $jwtHandler = null
    ) {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->tokenStorage = $tokenStorage ?? new TokenStorage();
        $this->jwtHandler = $jwtHandler ?? new JWTHandler();
    }

    public function authenticate(?string $tenantId = null, ?string $apiKey = null): bool
    {
        $tenantId = $tenantId ?? $this->config->getTenantId();
        $apiKey = $apiKey ?? $this->config->getApiKey();

        if (!$tenantId || !$apiKey) {
            throw new AuthenticationException('Tenant ID and API key are required for authentication');
        }

        try {
            $response = $this->httpClient->post('/auth/token', [
                'json' => [
                    'tenant_id' => $tenantId,
                    'api_key' => $apiKey,
                    'grant_type' => 'api_key',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new AuthenticationException('Invalid authentication response');
            }

            // Armazenar tokens
            $this->tokenStorage->storeAccessToken(
                $data['access_token'],
                $data['expires_in'] ?? 3600
            );

            if (isset($data['refresh_token'])) {
                $this->tokenStorage->storeRefreshToken($data['refresh_token']);
            }

            // Carregar informações do usuário
            $this->loadUserInfo();

            return true;

        } catch (HttpException $e) {
            if ($e->isClientError()) {
                throw new AuthenticationException(
                    'Authentication failed: Invalid credentials',
                    401,
                    $e,
                    ['tenant_id' => $tenantId]
                );
            }

            throw new AuthenticationException(
                'Authentication failed: ' . $e->getMessage(),
                $e->getCode(),
                $e,
                ['tenant_id' => $tenantId]
            );
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->tokenStorage->hasValidAccessToken();
    }

    public function getAccessToken(): ?string
    {
        // Tentar refresh automático se necessário
        if ($this->shouldRefreshToken()) {
            $this->refreshToken();
        }

        return $this->tokenStorage->getAccessToken();
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
            $response = $this->httpClient->post('/auth/refresh', [
                'json' => [
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ],
            ]);

            $data = json_decode((string) $response->getBody(), true);

            if (!isset($data['access_token'])) {
                throw new AuthenticationException('Invalid refresh response');
            }

            // Armazenar novo access token
            $this->tokenStorage->storeAccessToken(
                $data['access_token'],
                $data['expires_in'] ?? 3600
            );

            // Atualizar refresh token se fornecido
            if (isset($data['refresh_token'])) {
                $this->tokenStorage->storeRefreshToken($data['refresh_token']);
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
}