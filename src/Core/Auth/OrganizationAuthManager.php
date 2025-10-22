<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Exceptions\AuthenticationException;
use Clubify\Checkout\Exceptions\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Organization Authentication Manager
 *
 * Gerencia autenticação usando Organization-Level API Keys
 * Suporta todos os escopos: ORGANIZATION, CROSS_TENANT, TENANT
 */
class OrganizationAuthManager
{
    private Configuration $config;
    private Client $httpClient;
    private LoggerInterface $logger;
    private ?CacheManagerInterface $cache = null;

    private ?string $accessToken = null;
    private ?string $refreshToken = null;
    private ?int $tokenExpires = null;
    private ?string $organizationId = null;
    private ?string $tenantId = null;
    private ?string $scope = null;
    private ?array $permissions = null;
    private ?array $accessibleTenants = null;

    public function __construct(
        Configuration $config,
        Client $httpClient,
        LoggerInterface $logger,
        ?CacheManagerInterface $cache = null
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Autenticar usando Organization API Key
     */
    public function authenticateWithOrganizationApiKey(
        string $organizationId,
        string $apiKey,
        ?string $tenantId = null
    ): array {
        try {
            // ✅ IMPROVEMENT: Check cached token first
            $cacheKey = $this->getOrgAuthTokenCacheKey($organizationId, $apiKey, $tenantId);
            $cachedAuthData = $this->getCachedAuthToken($cacheKey);

            if ($cachedAuthData) {
                $this->logger->info('Using cached organization access token', [
                    'organization_id' => $organizationId,
                    'tenant_id' => $tenantId,
                    'scope' => $cachedAuthData['scope'] ?? 'unknown',
                    'expires_in' => $cachedAuthData['expires_in'] ?? 'unknown'
                ]);

                // Restore from cache
                $this->accessToken = $cachedAuthData['access_token'];
                $this->refreshToken = $cachedAuthData['refresh_token'] ?? null;
                $this->tokenExpires = time() + ($cachedAuthData['expires_in'] ?? 3600);
                $this->organizationId = $cachedAuthData['organization_id'];
                $this->tenantId = $cachedAuthData['tenant_id'] ?? null;
                $this->scope = $cachedAuthData['scope'];
                $this->permissions = $cachedAuthData['permissions'] ?? [];
                $this->accessibleTenants = $cachedAuthData['accessible_tenants'] ?? [];

                // Update SDK configuration
                $this->config->set('access_token', $this->accessToken);
                $this->config->set('organization_id', $this->organizationId);
                $this->config->set('tenant_id', $this->tenantId);
                $this->config->set('api_key_scope', $this->scope);

                return $cachedAuthData;
            }

            $this->logger->info('Starting organization API key authentication', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'key_prefix' => substr($apiKey, 0, 16) . '...'
            ]);

            // Preparar dados da requisição
            $requestData = [
                'api_key' => $apiKey,
                'organization_id' => $organizationId,
                'grant_type' => 'organization_api_key'
            ];

            // Adicionar tenant ID se especificado (para scoped access)
            if ($tenantId) {
                $requestData['tenant_id'] = $tenantId;
            }

            // ✅ IMPROVEMENT: Incluir headers obrigatórios na requisição de autenticação
            // X-Organization-Id é obrigatório (organização está sempre presente)
            // X-Tenant-Id é OPCIONAL (organização está acima de tenant na hierarquia)
            $headers = [
                'X-Organization-Id' => $organizationId
            ];

            // ✅ CORRECTION: X-Tenant-Id é opcional - só enviar quando especificado
            // Casos de uso:
            // - ORGANIZATION scope: sem tenant_id (acesso a toda organização)
            // - CROSS_TENANT scope: com tenant_id (acesso a tenant específico)
            // - TENANT scope: com tenant_id (acesso limitado ao tenant)
            if ($tenantId) {
                $headers['X-Tenant-Id'] = $tenantId;
            }

            // Fazer requisição de autenticação
            $response = $this->makeHttpRequest('POST', 'auth/api-key/organization/token', [
                'json' => $requestData,
                'headers' => $headers  // Headers com X-Tenant-Id opcional
            ]);

            if (!$response || !isset($response['access_token'])) {
                throw new AuthenticationException('Invalid authentication response');
            }

            // Armazenar tokens e contexto
            $this->accessToken = $response['access_token'];
            $this->refreshToken = $response['refresh_token'] ?? null;
            $this->tokenExpires = time() + ($response['expires_in'] ?? 3600);
            $this->organizationId = $response['organization_id'];
            $this->tenantId = $response['tenant_id'] ?? null;
            $this->scope = $response['scope'];
            $this->permissions = $response['permissions'] ?? [];
            $this->accessibleTenants = $response['accessible_tenants'] ?? [];

            // Atualizar configuração do SDK
            $this->config->set('access_token', $this->accessToken);
            $this->config->set('organization_id', $this->organizationId);
            $this->config->set('tenant_id', $this->tenantId);
            $this->config->set('api_key_scope', $this->scope);

            $this->logger->info('Organization API key authentication successful', [
                'organization_id' => $this->organizationId,
                'tenant_id' => $this->tenantId,
                'scope' => $this->scope,
                'permissions_count' => count($this->permissions),
                'accessible_tenants_count' => count($this->accessibleTenants),
                'token_expires_in' => $response['expires_in']
            ]);

            $result = [
                'success' => true,
                'access_token' => $this->accessToken,
                'refresh_token' => $this->refreshToken,
                'expires_in' => $response['expires_in'],
                'token_type' => $response['token_type'] ?? 'Bearer',
                'scope' => $this->scope,
                'organization_id' => $this->organizationId,
                'tenant_id' => $this->tenantId,
                'permissions' => $this->permissions,
                'accessible_tenants' => $this->accessibleTenants,
                'key_info' => $response['key_info'] ?? null
            ];

            // ✅ IMPROVEMENT: Cache authentication data
            $this->cacheAuthToken($organizationId, $apiKey, $tenantId, $result, $response['expires_in'] ?? 3600);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Organization API key authentication failed', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            throw new AuthenticationException('Organization API key authentication failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Autenticar com Organization Key (escopo total)
     */
    public function authenticateWithFullOrganizationAccess(string $organizationId, string $apiKey): array
    {
        return $this->authenticateWithOrganizationApiKey($organizationId, $apiKey);
    }

    /**
     * Autenticar com Cross-Tenant Key
     */
    public function authenticateWithCrossTenantAccess(
        string $organizationId,
        string $apiKey,
        string $targetTenantId
    ): array {
        return $this->authenticateWithOrganizationApiKey($organizationId, $apiKey, $targetTenantId);
    }

    /**
     * Autenticar com Tenant Key (compatibilidade)
     */
    public function authenticateWithTenantAccess(
        string $organizationId,
        string $apiKey,
        string $tenantId
    ): array {
        return $this->authenticateWithOrganizationApiKey($organizationId, $apiKey, $tenantId);
    }

    /**
     * Verificar se token está válido
     */
    public function isAuthenticated(): bool
    {
        return $this->accessToken !== null &&
               $this->tokenExpires !== null &&
               $this->tokenExpires > time();
    }

    /**
     * Verificar se tem acesso a um tenant específico
     */
    public function hasAccessToTenant(string $tenantId): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        switch ($this->scope) {
            case 'organization':
                // Organization keys têm acesso a todos os tenants
                return true;

            case 'cross_tenant':
                // Cross-tenant keys verificam lista de tenants permitidos
                return in_array($tenantId, $this->accessibleTenants ?? []);

            case 'tenant':
                // Tenant keys só têm acesso ao próprio tenant
                return $this->tenantId === $tenantId;

            default:
                return false;
        }
    }

    /**
     * Verificar se tem uma permissão específica
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? []);
    }

    /**
     * Verificar se tem qualquer uma das permissões
     */
    public function hasAnyPermission(array $permissions): bool
    {
        return !empty(array_intersect($permissions, $this->permissions ?? []));
    }

    /**
     * Obter token de acesso atual
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Obter contexto organizacional
     */
    public function getOrganizationContext(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'tenant_id' => $this->tenantId,
            'scope' => $this->scope,
            'permissions' => $this->permissions,
            'accessible_tenants' => $this->accessibleTenants,
            'token_expires' => $this->tokenExpires,
            'is_authenticated' => $this->isAuthenticated()
        ];
    }

    /**
     * Alterar contexto de tenant (para organization e cross-tenant keys)
     */
    public function switchTenantContext(string $tenantId): bool
    {
        if (!$this->hasAccessToTenant($tenantId)) {
            $this->logger->warning('Attempt to switch to unauthorized tenant', [
                'current_scope' => $this->scope,
                'current_tenant' => $this->tenantId,
                'requested_tenant' => $tenantId,
                'accessible_tenants' => $this->accessibleTenants
            ]);
            return false;
        }

        $this->tenantId = $tenantId;
        $this->config->set('tenant_id', $tenantId);

        $this->logger->info('Tenant context switched', [
            'new_tenant_id' => $tenantId,
            'scope' => $this->scope
        ]);

        return true;
    }

    /**
     * Refresh token se necessário
     */
    public function refreshTokenIfNeeded(): bool
    {
        if (!$this->refreshToken) {
            return false;
        }

        // Se token expira em menos de 5 minutos, renovar
        if ($this->tokenExpires && ($this->tokenExpires - time()) < 300) {
            return $this->refreshAccessToken();
        }

        return true;
    }

    /**
     * Renovar access token usando refresh token
     */
    private function refreshAccessToken(): bool
    {
        try {
            if (!$this->refreshToken) {
                return false;
            }

            $response = $this->makeHttpRequest('POST', 'auth/refresh', [
                'json' => [
                    'refreshToken' => $this->refreshToken
                ]
            ]);

            if (isset($response['access_token'])) {
                $this->accessToken = $response['access_token'];
                $this->tokenExpires = time() + ($response['expires_in'] ?? 3600);
                $this->config->set('access_token', $this->accessToken);

                $this->logger->info('Access token refreshed successfully');
                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Token refresh failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Limpar autenticação
     */
    public function clearAuthentication(): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->tokenExpires = null;
        $this->organizationId = null;
        $this->tenantId = null;
        $this->scope = null;
        $this->permissions = null;
        $this->accessibleTenants = null;

        // Limpar configuração
        $this->config->remove('access_token');
        $this->config->remove('organization_id');
        $this->config->remove('tenant_id');
        $this->config->remove('api_key_scope');

        $this->logger->info('Authentication cleared');
    }

    /**
     * Obter headers de autenticação para requisições
     */
    public function getAuthHeaders(): array
    {
        $headers = [];

        if ($this->accessToken) {
            $headers['Authorization'] = 'Bearer ' . $this->accessToken;
        }

        if ($this->organizationId) {
            $headers['X-Organization-Id'] = $this->organizationId;
        }

        if ($this->tenantId) {
            $headers['X-Tenant-Id'] = $this->tenantId;
        }

        return $headers;
    }

    // ============ PRIVATE METHODS ============

    private function makeHttpRequest(string $method, string $uri, array $options = []): array
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
            $this->logger->error("Organization auth HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ✅ IMPROVEMENT: Generate cache key for organization auth token
     */
    private function getOrgAuthTokenCacheKey(string $organizationId, string $apiKey, ?string $tenantId): string
    {
        // Use hash to avoid storing sensitive API key in cache key
        $keyHash = hash('sha256', $organizationId . ':' . $apiKey . ':' . ($tenantId ?? 'all'));
        return 'org_auth_token:' . $keyHash;
    }

    /**
     * ✅ IMPROVEMENT: Get cached auth token
     */
    private function getCachedAuthToken(string $cacheKey): ?array
    {
        if (!$this->cache) {
            return null;
        }

        try {
            $cached = $this->cache->get($cacheKey);
            if (!$cached) {
                return null;
            }

            // Validate cached data structure
            if (!isset($cached['access_token'], $cached['expires_in'])) {
                $this->logger->warning('Invalid cached organization auth data structure', [
                    'cache_key' => $cacheKey
                ]);
                $this->cache->delete($cacheKey);
                return null;
            }

            return $cached;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve cached organization auth token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * ✅ IMPROVEMENT: Cache auth token with TTL
     */
    private function cacheAuthToken(
        string $organizationId,
        string $apiKey,
        ?string $tenantId,
        array $authData,
        int $expiresIn
    ): void {
        if (!$this->cache) {
            return;
        }

        try {
            $cacheKey = $this->getOrgAuthTokenCacheKey($organizationId, $apiKey, $tenantId);

            // Use 5 minute buffer to avoid using token that's about to expire
            $cacheTtl = max(0, $expiresIn - 300);

            $this->cache->set($cacheKey, $authData, $cacheTtl);

            $this->logger->debug('Organization auth token cached', [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'cache_ttl' => $cacheTtl,
                'expires_in' => $expiresIn
            ]);
        } catch (\Exception $e) {
            // Log error but don't fail authentication if caching fails
            $this->logger->warning('Failed to cache organization auth token', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * ✅ IMPROVEMENT: Set cache manager
     */
    public function setCacheManager(?CacheManagerInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * ✅ IMPROVEMENT: Get cache manager
     */
    public function getCacheManager(): ?CacheManagerInterface
    {
        return $this->cache;
    }
}