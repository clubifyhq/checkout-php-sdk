<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\SDKException;
use Psr\Log\LoggerInterface;

/**
 * Organization API Key Service
 *
 * Gerencia API keys com diferentes escopos:
 * - TENANT: Acesso restrito ao tenant específico
 * - ORGANIZATION: Acesso a toda organização e seus tenants
 * - CROSS_TENANT: Acesso multi-tenant com lista específica
 */
class OrganizationApiKeyService
{
    private Configuration $config;
    private Client $httpClient;
    private LoggerInterface $logger;

    // Constantes de escopo
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_ORGANIZATION = 'organization';
    public const SCOPE_CROSS_TENANT = 'cross_tenant';

    // Constantes de ambiente
    public const ENV_TEST = 'test';
    public const ENV_LIVE = 'live';

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
     * Gerar API Key de organização com escopo específico
     */
    public function generateOrganizationApiKey(string $organizationId, array $keyData): array
    {
        try {
            $this->validateKeyData($keyData);

            // Preparar dados da requisição
            $requestData = $this->prepareKeyGenerationData($keyData);

            // Fazer requisição para o backend
            $response = $this->makeHttpRequest('POST', "organizations/{$organizationId}/api-keys", [
                'json' => $requestData,
                'headers' => [
                    'X-Organization-Id' => $organizationId,
                    'X-User-Id' => $this->config->get('user_id', 'system')
                ]
            ]);

            if (!$response || !isset($response['data'])) {
                throw new HttpException('Failed to generate organization API key: Invalid response');
            }

            $apiKey = $response['data'];

            $this->logger->info('Organization API key generated successfully', [
                'organization_id' => $organizationId,
                'key_id' => $apiKey['keyId'] ?? 'unknown',
                'scope' => $apiKey['scope'] ?? 'unknown',
                'environment' => $apiKey['keyInfo']['environment'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'api_key' => $apiKey['apiKey'],
                'key_id' => $apiKey['keyId'],
                'hash_key' => $apiKey['hashKey'],
                'scope' => $apiKey['scope'],
                'key_info' => $apiKey['keyInfo'],
                'organization_id' => $organizationId
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate organization API key', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
                'scope' => $keyData['scope'] ?? 'unknown'
            ]);

            throw new SDKException('Failed to generate organization API key: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gerar API Key de ORGANIZAÇÃO (acesso total)
     */
    public function generateFullOrganizationKey(string $organizationId, array $keyData = []): array
    {
        $keyData['scope'] = self::SCOPE_ORGANIZATION;
        $keyData['name'] = $keyData['name'] ?? 'Organization Master Key';
        $keyData['description'] = $keyData['description'] ?? 'Full organization access key';
        $keyData['permissions'] = $keyData['permissions'] ?? [
            'organization:read',
            'organization:write',
            'tenant:read',
            'tenant:write',
            'checkout:full'
        ];

        return $this->generateOrganizationApiKey($organizationId, $keyData);
    }

    /**
     * Gerar API Key CROSS-TENANT (múltiplos tenants)
     */
    public function generateCrossTenantKey(string $organizationId, array $allowedTenantIds, array $keyData = []): array
    {
        $keyData['scope'] = self::SCOPE_CROSS_TENANT;
        $keyData['allowedTenantIds'] = $allowedTenantIds;
        $keyData['name'] = $keyData['name'] ?? 'Cross-Tenant Key';
        $keyData['description'] = $keyData['description'] ?? 'Multi-tenant access key';
        $keyData['permissions'] = $keyData['permissions'] ?? [
            'tenant:read',
            'checkout:read',
            'checkout:write'
        ];

        return $this->generateOrganizationApiKey($organizationId, $keyData);
    }

    /**
     * Gerar API Key TENANT (compatibilidade)
     */
    public function generateTenantKey(string $organizationId, string $tenantId, array $keyData = []): array
    {
        $keyData['scope'] = self::SCOPE_TENANT;
        $keyData['tenantId'] = $tenantId;
        $keyData['name'] = $keyData['name'] ?? 'Tenant Key';
        $keyData['description'] = $keyData['description'] ?? 'Tenant-specific access key';
        $keyData['permissions'] = $keyData['permissions'] ?? [
            'tenant:read',
            'checkout:read',
            'checkout:write'
        ];

        return $this->generateOrganizationApiKey($organizationId, $keyData);
    }

    /**
     * Listar API Keys da organização
     */
    public function listOrganizationApiKeys(string $organizationId, array $filters = []): array
    {
        try {
            $queryParams = $this->buildQueryParams($filters);
            $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

            $response = $this->makeHttpRequest('GET', "organizations/{$organizationId}/api-keys{$queryString}", [
                'headers' => [
                    'X-Organization-Id' => $organizationId
                ]
            ]);

            if (!$response || !isset($response['data'])) {
                throw new HttpException('Failed to list organization API keys: Invalid response');
            }

            return [
                'success' => true,
                'keys' => $response['data']['keys'],
                'total' => $response['data']['total'],
                'page' => $response['data']['page'],
                'limit' => $response['data']['limit']
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to list organization API keys', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to list organization API keys: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validar API Key (com suporte a organização)
     */
    public function validateOrganizationApiKey(string $organizationId, string $apiKey, array $options = []): array
    {
        try {
            $requestData = [
                'apiKey' => $apiKey,
                'endpoint' => $options['endpoint'] ?? null,
                'ipAddress' => $options['ip_address'] ?? null,
                'userAgent' => $options['user_agent'] ?? null
            ];

            $headers = [
                'X-Organization-Id' => $organizationId
            ];

            // Se solicitado acesso a tenant específico
            if (isset($options['tenant_id'])) {
                $headers['X-Requested-Tenant-Id'] = $options['tenant_id'];
            }

            $response = $this->makeHttpRequest('POST', "organizations/{$organizationId}/api-keys/validate", [
                'json' => $requestData,
                'headers' => $headers
            ]);

            if (!$response || !isset($response['data'])) {
                throw new HttpException('Failed to validate organization API key: Invalid response');
            }

            return [
                'success' => true,
                'validation_result' => $response['data']
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to validate organization API key', [
                'organization_id' => $organizationId,
                'key_prefix' => substr($apiKey, 0, 16) . '...',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'validation_result' => [
                    'valid' => false,
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Obter estatísticas de uso da organização
     */
    public function getOrganizationUsageStats(string $organizationId, array $options = []): array
    {
        try {
            $queryParams = [];

            if (isset($options['start_date'])) {
                $queryParams['startDate'] = $options['start_date'];
            }
            if (isset($options['end_date'])) {
                $queryParams['endDate'] = $options['end_date'];
            }
            if (isset($options['scope'])) {
                $queryParams['scope'] = $options['scope'];
            }

            $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';

            $response = $this->makeHttpRequest('GET', "organizations/{$organizationId}/api-keys/usage/statistics{$queryString}", [
                'headers' => [
                    'X-Organization-Id' => $organizationId
                ]
            ]);

            if (!$response || !isset($response['data'])) {
                throw new HttpException('Failed to get organization usage statistics: Invalid response');
            }

            return [
                'success' => true,
                'statistics' => $response['data']
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get organization usage statistics', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to get organization usage statistics: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Revogar API Key
     */
    public function revokeOrganizationApiKey(string $organizationId, string $keyId, string $reason): array
    {
        try {
            $requestData = [
                'reason' => $reason
            ];

            $response = $this->makeHttpRequest('DELETE', "organizations/{$organizationId}/api-keys/{$keyId}", [
                'json' => $requestData,
                'headers' => [
                    'X-Organization-Id' => $organizationId,
                    'X-User-Id' => $this->config->get('user_id', 'system')
                ]
            ]);

            if (!$response) {
                throw new HttpException('Failed to revoke organization API key: Invalid response');
            }

            $this->logger->info('Organization API key revoked successfully', [
                'organization_id' => $organizationId,
                'key_id' => $keyId,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'API key revoked successfully',
                'revoked_at' => $response['data']['revokedAt'] ?? date('c')
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to revoke organization API key', [
                'organization_id' => $organizationId,
                'key_id' => $keyId,
                'error' => $e->getMessage()
            ]);

            throw new SDKException('Failed to revoke organization API key: ' . $e->getMessage(), 0, $e);
        }
    }

    // ============ PRIVATE HELPER METHODS ============

    private function validateKeyData(array $keyData): void
    {
        if (empty($keyData['scope'])) {
            throw new \InvalidArgumentException('API key scope is required');
        }

        if (!in_array($keyData['scope'], [self::SCOPE_TENANT, self::SCOPE_ORGANIZATION, self::SCOPE_CROSS_TENANT])) {
            throw new \InvalidArgumentException('Invalid API key scope');
        }

        if (empty($keyData['environment'])) {
            $keyData['environment'] = self::ENV_LIVE;
        }

        if (!in_array($keyData['environment'], [self::ENV_TEST, self::ENV_LIVE])) {
            throw new \InvalidArgumentException('Invalid API key environment');
        }

        // Validações específicas por escopo
        if ($keyData['scope'] === self::SCOPE_TENANT && empty($keyData['tenantId'])) {
            throw new \InvalidArgumentException('tenantId is required for TENANT scope');
        }

        if ($keyData['scope'] === self::SCOPE_CROSS_TENANT && empty($keyData['allowedTenantIds'])) {
            throw new \InvalidArgumentException('allowedTenantIds is required for CROSS_TENANT scope');
        }
    }

    private function prepareKeyGenerationData(array $keyData): array
    {
        return [
            'name' => $keyData['name'] ?? 'Organization API Key',
            'scope' => $keyData['scope'],
            'environment' => $keyData['environment'] ?? self::ENV_LIVE,
            'tenantId' => $keyData['tenantId'] ?? null,
            'allowedTenantIds' => $keyData['allowedTenantIds'] ?? null,
            'permissions' => $keyData['permissions'] ?? ['organization:basic'],
            'description' => $keyData['description'] ?? null,
            'rateLimit' => $keyData['rateLimit'] ?? null,
            'allowedDomains' => $keyData['allowedDomains'] ?? [],
            'allowedIPs' => $keyData['allowedIPs'] ?? [],
            'expiresAt' => isset($keyData['expiresAt']) ? date('c', strtotime($keyData['expiresAt'])) : null,
            'autoRotate' => $keyData['autoRotate'] ?? false,
            'rotationInterval' => $keyData['rotationInterval'] ?? 90
        ];
    }

    private function buildQueryParams(array $filters): array
    {
        $params = [];

        if (isset($filters['scope'])) {
            $params['scope'] = $filters['scope'];
        }
        if (isset($filters['tenant_id'])) {
            $params['tenantId'] = $filters['tenant_id'];
        }
        if (isset($filters['environment'])) {
            $params['environment'] = $filters['environment'];
        }
        if (isset($filters['include_inactive'])) {
            $params['includeInactive'] = $filters['include_inactive'] ? 'true' : 'false';
        }
        if (isset($filters['limit'])) {
            $params['limit'] = (int) $filters['limit'];
        }
        if (isset($filters['offset'])) {
            $params['offset'] = (int) $filters['offset'];
        }

        return $params;
    }

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
            $this->logger->error("Organization API key HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}