<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Http\ResponseHelper;
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
        LoggerInterface $logger
    ) {
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
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

            // Fazer requisição de autenticação
            $response = $this->makeHttpRequest('POST', 'auth/api-key/organization/token', [
                'json' => $requestData
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

            return [
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
}