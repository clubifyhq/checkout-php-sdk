<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\AuthenticationException;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Serviço de gestão de API Keys
 *
 * Responsável por gerenciar chaves de API para organizações:
 * - Geração de API keys iniciais e adicionais
 * - Validação e verificação de chaves
 * - Rotação e renovação automática
 * - Gestão de permissões por chave
 * - Rate limiting e quotas
 * - Auditoria e logs de uso
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de API keys
 * - O: Open/Closed - Extensível via tipos de chave
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de API keys
 * - D: Dependency Inversion - Depende de abstrações
 */
class ApiKeyService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'api_key';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Gera API keys iniciais para uma organização
     */
    public function generateInitialKeys(string $organizationId): array
    {
        return $this->executeWithMetrics('generate_initial_keys', function () use ($organizationId) {
            $keys = [];

            // Gerar chave de produção
            $keys['production'] = $this->generateApiKey($organizationId, [
                'name' => 'Production Key',
                'type' => 'production',
                'permissions' => $this->getProductionPermissions(),
                'rate_limit' => 10000,
                'expires_at' => null // Não expira
            ]);

            // Gerar chave de teste
            $keys['test'] = $this->generateApiKey($organizationId, [
                'name' => 'Test Key',
                'type' => 'test',
                'permissions' => $this->getTestPermissions(),
                'rate_limit' => 1000,
                'expires_at' => null // Não expira
            ]);

            // Gerar chave de sandbox
            $keys['sandbox'] = $this->generateApiKey($organizationId, [
                'name' => 'Sandbox Key',
                'type' => 'sandbox',
                'permissions' => $this->getSandboxPermissions(),
                'rate_limit' => 100,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 year'))
            ]);

            // Dispatch evento
            $this->dispatch('api_keys.initial_generated', [
                'organization_id' => $organizationId,
                'keys_generated' => array_keys($keys)
            ]);

            $this->logger->info('Initial API keys generated', [
                'organization_id' => $organizationId,
                'keys_count' => count($keys)
            ]);

            return $keys;
        });
    }

    /**
     * Gera uma nova API key
     */
    public function generateApiKey(string $organizationId, array $keyData): array
    {
        return $this->executeWithMetrics('generate_api_key', function () use ($organizationId, $keyData) {
            $this->validateApiKeyData($keyData);

            // Preparar dados da chave
            $data = array_merge($keyData, [
                'organization_id' => $organizationId,
                'key' => $this->generateSecureKey($keyData['type'] ?? 'standard'),
                'secret' => $this->generateSecureSecret(),
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'last_used_at' => null,
                'usage_count' => 0,
                'settings' => $this->getDefaultKeySettings()
            ]);

            // Criar API key via API
            $response = $this->makeHttpRequest('POST', 'api-keys', ['json' => $data]);
            $apiKey = $response;

            if (!$apiKey) {
                throw new HttpException('Failed to create API key: Invalid response from server');
            }

            // Cache da chave
            $this->cache->set($this->getCacheKey("api_key:{$apiKey['id']}"), $apiKey, 7200);
            $this->cache->set($this->getCacheKey("api_key_by_key:{$apiKey['key']}"), $apiKey, 7200);

            // Dispatch evento
            $this->dispatch('api_key.generated', [
                'api_key_id' => $apiKey['id'],
                'organization_id' => $organizationId,
                'type' => $apiKey['type'],
                'permissions' => $apiKey['permissions']
            ]);

            $this->logger->info('API key generated successfully', [
                'api_key_id' => $apiKey['id'],
                'organization_id' => $organizationId,
                'type' => $apiKey['type']
            ]);

            return $apiKey;
        });
    }

    /**
     * Valida uma API key
     */
    public function validateApiKey(string $apiKey): ?array
    {
        return $this->getCachedOrExecute(
            "api_key_by_key:{$apiKey}",
            fn () => $this->fetchApiKeyByKey($apiKey),
            1800
        );
    }

    /**
     * Obtém API key por ID
     */
    public function getApiKey(string $apiKeyId): ?array
    {
        return $this->getCachedOrExecute(
            "api_key:{$apiKeyId}",
            fn () => $this->fetchApiKeyById($apiKeyId),
            7200
        );
    }

    /**
     * Lista API keys de uma organização
     */
    public function getApiKeysByOrganization(string $organizationId): array
    {
        return $this->executeWithMetrics('get_api_keys_by_organization', function () use ($organizationId) {
            $response = $this->makeHttpRequest('GET', "/api-keys");
            return $response ?? [];
        });
    }

    /**
     * Atualiza permissões de uma API key
     */
    public function updatePermissions(string $apiKeyId, array $permissions): array
    {
        return $this->executeWithMetrics('update_api_key_permissions', function () use ($apiKeyId, $permissions) {
            $this->validatePermissions($permissions);

            $response = $this->makeHttpRequest('PUT', "api-keys/{$apiKeyId}/permissions", [
                'json' => [
                    'permissions' => $permissions
                ]
            ]);

            $apiKey = $response;

            if (!$apiKey) {
                throw new HttpException('Failed to update API key permissions: Invalid response from server');
            }

            // Invalidar cache
            $this->invalidateApiKeyCache($apiKeyId);

            // Dispatch evento
            $this->dispatch('api_key.permissions_updated', [
                'api_key_id' => $apiKeyId,
                'permissions' => $permissions
            ]);

            return $apiKey;
        });
    }

    /**
     * Atualiza rate limit de uma API key
     */
    public function updateRateLimit(string $apiKeyId, int $rateLimit): array
    {
        return $this->executeWithMetrics('update_api_key_rate_limit', function () use ($apiKeyId, $rateLimit) {
            if ($rateLimit < 1 || $rateLimit > 100000) {
                throw new ValidationException('Rate limit must be between 1 and 100000');
            }

            $response = $this->makeHttpRequest('PUT', "api-keys/{$apiKeyId}/rate-limit", [
                'json' => [
                    'rate_limit' => $rateLimit
                ]
            ]);

            $apiKey = $response;

            if (!$apiKey) {
                throw new HttpException('Failed to update API key rate limit: Invalid response from server');
            }

            // Invalidar cache
            $this->invalidateApiKeyCache($apiKeyId);

            // Dispatch evento
            $this->dispatch('api_key.rate_limit_updated', [
                'api_key_id' => $apiKeyId,
                'rate_limit' => $rateLimit
            ]);

            return $apiKey;
        });
    }

    /**
     * Ativa uma API key
     */
    public function activateApiKey(string $apiKeyId): bool
    {
        return $this->updateApiKeyStatus($apiKeyId, 'active');
    }

    /**
     * Desativa uma API key
     */
    public function deactivateApiKey(string $apiKeyId): bool
    {
        return $this->updateApiKeyStatus($apiKeyId, 'inactive');
    }

    /**
     * Suspende uma API key
     */
    public function suspendApiKey(string $apiKeyId): bool
    {
        return $this->updateApiKeyStatus($apiKeyId, 'suspended');
    }

    /**
     * Revoga uma API key (permanentemente)
     */
    public function revokeApiKey(string $apiKeyId): bool
    {
        return $this->updateApiKeyStatus($apiKeyId, 'revoked');
    }

    /**
     * Regenera uma API key
     */
    public function regenerateApiKey(string $apiKeyId): array
    {
        return $this->executeWithMetrics('regenerate_api_key', function () use ($apiKeyId) {
            $currentKey = $this->getApiKey($apiKeyId);

            if (!$currentKey) {
                throw new ValidationException("API key not found: {$apiKeyId}");
            }

            $newKey = $this->generateSecureKey($currentKey['type']);
            $newSecret = $this->generateSecureSecret();

            $response = $this->makeHttpRequest('PUT', "api-keys/{$apiKeyId}/regenerate", [
                'json' => [
                    'key' => $newKey,
                    'secret' => $newSecret
                ]
            ]);

            $apiKey = $response;

            if (!$apiKey) {
                throw new HttpException('Failed to regenerate API key: Invalid response from server');
            }

            // Invalidar cache
            $this->invalidateApiKeyCache($apiKeyId);

            // Dispatch evento
            $this->dispatch('api_key.regenerated', [
                'api_key_id' => $apiKeyId,
                'old_key' => $currentKey['key'],
                'new_key' => $newKey
            ]);

            $this->logger->warning('API key regenerated', [
                'api_key_id' => $apiKeyId,
                'organization_id' => $currentKey['organization_id']
            ]);

            return $apiKey;
        });
    }

    /**
     * Rotaciona API keys automaticamente
     */
    public function rotateApiKeys(string $organizationId): array
    {
        return $this->executeWithMetrics('rotate_api_keys', function () use ($organizationId) {
            $keys = $this->getApiKeysByOrganization($organizationId);
            $rotatedKeys = [];

            foreach ($keys as $key) {
                // Só rotaciona chaves que não foram usadas recentemente
                if ($this->shouldRotateKey($key)) {
                    $rotatedKeys[] = $this->regenerateApiKey($key['id']);
                }
            }

            // Dispatch evento
            $this->dispatch('api_keys.rotated', [
                'organization_id' => $organizationId,
                'rotated_count' => count($rotatedKeys)
            ]);

            return $rotatedKeys;
        });
    }

    /**
     * Obtém estatísticas de uso de uma API key
     */
    public function getApiKeyStats(string $apiKeyId): array
    {
        return $this->executeWithMetrics('get_api_key_stats', function () use ($apiKeyId) {
            $response = $this->makeHttpRequest('GET', "api-keys/{$apiKeyId}/stats");
            return $response ?? [];
        });
    }

    /**
     * Obtém logs de uso de uma API key
     */
    public function getApiKeyLogs(string $apiKeyId, int $limit = 100): array
    {
        return $this->executeWithMetrics('get_api_key_logs', function () use ($apiKeyId, $limit) {
            $response = $this->makeHttpRequest('GET', "api-keys/{$apiKeyId}/logs", [
                'query' => [
                    'limit' => $limit
                ]
            ]);
            return $response ?? [];
        });
    }

    /**
     * Verifica se API key tem permissão específica
     */
    public function hasPermission(string $apiKey, string $permission): bool
    {
        $keyData = $this->validateApiKey($apiKey);

        if (!$keyData || $keyData['status'] !== 'active') {
            return false;
        }

        $permissions = $keyData['permissions'] ?? [];
        return in_array($permission, $permissions) || in_array('*', $permissions);
    }

    /**
     * Registra uso de API key
     */
    public function recordUsage(string $apiKey, array $metadata = []): void
    {
        $this->executeWithMetrics('record_api_key_usage', function () use ($apiKey, $metadata) {
            $this->makeHttpRequest('POST', "api-keys/usage", [
                'json' => [
                    'api_key' => $apiKey,
                    'timestamp' => time(),
                    'metadata' => $metadata
                ]
            ]);
        });
    }

    /**
     * Busca API key por chave via API
     */
    private function fetchApiKeyByKey(string $apiKey): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "api-keys/validate/{$apiKey}");
            return $response;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404 || $e->getStatusCode() === 401) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca API key por ID via API
     */
    private function fetchApiKeyById(string $apiKeyId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "api-keys/{$apiKeyId}");
            return $response;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status da API key
     */
    private function updateApiKeyStatus(string $apiKeyId, string $status): bool
    {
        return $this->executeWithMetrics("update_api_key_status_{$status}", function () use ($apiKeyId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "api-keys/{$apiKeyId}/status", [
                    'json' => [
                        'status' => $status
                    ]
                ]);

                // Invalidar cache
                $this->invalidateApiKeyCache($apiKeyId);

                // Dispatch evento
                $this->dispatch('api_key.status_changed', [
                    'api_key_id' => $apiKeyId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update API key status to {$status}", [
                    'api_key_id' => $apiKeyId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache da API key
     */
    private function invalidateApiKeyCache(string $apiKeyId): void
    {
        $apiKey = $this->getApiKey($apiKeyId);

        $this->cache->delete($this->getCacheKey("api_key:{$apiKeyId}"));

        if ($apiKey && isset($apiKey['key'])) {
            $this->cache->delete($this->getCacheKey("api_key_by_key:{$apiKey['key']}"));
        }
    }

    /**
     * Verifica se chave deve ser rotacionada
     */
    private function shouldRotateKey(array $key): bool
    {
        // Rotaciona se não foi usada nos últimos 30 dias
        if (!$key['last_used_at']) {
            return true;
        }

        $lastUsed = strtotime($key['last_used_at']);
        $thirtyDaysAgo = strtotime('-30 days');

        return $lastUsed < $thirtyDaysAgo;
    }

    /**
     * Gera chave segura
     */
    private function generateSecureKey(string $type): string
    {
        $prefix = match ($type) {
            'production' => 'pk_live',
            'test' => 'pk_test',
            'sandbox' => 'pk_sandbox',
            default => 'pk'
        };

        return $prefix . '_' . bin2hex(random_bytes(24));
    }

    /**
     * Gera secret seguro
     */
    private function generateSecureSecret(): string
    {
        return 'sk_' . bin2hex(random_bytes(32));
    }

    /**
     * Valida dados da API key
     */
    private function validateApiKeyData(array $data): void
    {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for API key creation");
            }
        }

        $allowedTypes = ['production', 'test', 'sandbox', 'webhook', 'readonly'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid API key type: {$data['type']}");
        }

        if (isset($data['permissions'])) {
            $this->validatePermissions($data['permissions']);
        }
    }

    /**
     * Valida permissões
     */
    private function validatePermissions(array $permissions): void
    {
        $allowedPermissions = [
            '*', 'checkout.read', 'checkout.write', 'products.read', 'products.write',
            'orders.read', 'orders.write', 'payments.read', 'payments.write',
            'customers.read', 'customers.write', 'webhooks.read', 'webhooks.write',
            'analytics.read', 'reports.read'
        ];

        foreach ($permissions as $permission) {
            if (!in_array($permission, $allowedPermissions)) {
                throw new ValidationException("Invalid permission: {$permission}");
            }
        }
    }

    /**
     * Obtém permissões de produção
     */
    private function getProductionPermissions(): array
    {
        return [
            'checkout.read', 'checkout.write',
            'products.read',
            'orders.read', 'orders.write',
            'payments.read', 'payments.write',
            'customers.read', 'customers.write',
            'webhooks.read'
        ];
    }

    /**
     * Obtém permissões de teste
     */
    private function getTestPermissions(): array
    {
        return [
            'checkout.read', 'checkout.write',
            'products.read',
            'orders.read',
            'payments.read',
            'customers.read'
        ];
    }

    /**
     * Obtém permissões de sandbox
     */
    private function getSandboxPermissions(): array
    {
        return [
            'checkout.read',
            'products.read',
            'orders.read',
            'customers.read'
        ];
    }

    /**
     * Obtém configurações padrão da chave
     */
    private function getDefaultKeySettings(): array
    {
        return [
            'ip_whitelist' => [],
            'webhook_timeout' => 30,
            'retry_failed_webhooks' => true,
            'log_requests' => true,
            'rate_limit_window' => 3600
        ];
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
