<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Services;

use Clubify\Checkout\Core\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Notifications\DTOs\WebhookConfigData;
use Clubify\Checkout\Modules\Notifications\Enums\NotificationType;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Serviço de configuração de webhooks para notificações
 *
 * Responsável pela gestão completa de configurações de webhooks:
 * - CRUD de configurações de webhook
 * - Validação de URLs e conectividade
 * - Gestão de eventos e filtros
 * - Teste de entrega
 * - Validação de configurações
 * - Health checks e monitoramento
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas configuração de webhooks
 * - O: Open/Closed - Extensível via novos validadores
 * - L: Liskov Substitution - Estende BaseService
 * - I: Interface Segregation - Métodos específicos
 * - D: Dependency Inversion - Depende de abstrações
 */
class WebhookConfigService extends BaseService implements ServiceInterface
{
    private const CACHE_PREFIX = 'webhook_configs:';
    private const STATS_CACHE_TTL = 300; // 5 minutos

    private array $defaultConfig = [
        'timeout' => 30,
        'max_retries' => 3,
        'retry_delay' => 5,
        'verify_ssl' => true,
        'active' => true,
        'success_codes' => [200, 201, 202, 204],
        'headers' => [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ClubifyCheckout-PHP-SDK/1.0'
        ]
    ];

    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        Configuration $config,
        Logger $logger
    ) {
        parent::__construct($config, $logger);
    }

    /**
     * Obtém o nome do serviço
     */
    public function getName(): string
    {
        return 'webhook_config';
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se o serviço está saudável (health check)
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->makeHttpRequest('GET', '/notifications/webhooks/health');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::STATS_CACHE_TTL,
            'default_config' => $this->defaultConfig,
            'service_name' => $this->getName(),
            'service_version' => $this->getVersion()
        ];
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::STATS_CACHE_TTL,
            'default_config' => $this->defaultConfig,
            'service_name' => $this->getName(),
            'service_version' => $this->getVersion()
        ];
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        $healthy = $this->isHealthy();
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'healthy' => $healthy,
            'available' => $this->isAvailable(),
            'metrics' => $this->getMetrics(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Cria uma nova configuração de webhook
     * Endpoint: POST webhooks/configurations
     */
    public function create(array $configData): array
    {
        $this->validateInitialization();

        try {
            $this->logger->info('Criando configuração de webhook', [
                'partnerId' => $configData['partnerId'] ?? 'unknown',
                'name' => $configData['name'] ?? 'unnamed',
                'endpointCount' => count($configData['endpoints'] ?? [])
            ]);

            // Envia para API (sem /api/v1 pois já está no base_url)
            $response = $this->makeHttpRequest('POST', 'webhooks/configurations', [
                'json' => $configData
            ]);

            $result = $response; // makeHttpRequest already returns array

            // Cache da configuração
            if (isset($result['_id'])) {
                $this->cacheConfig($result['_id'], $result);
            }

            $this->logger->info('Configuração de webhook criada', [
                'config_id' => $result['_id'] ?? 'unknown',
                'partnerId' => $configData['partnerId'] ?? 'unknown'
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao criar configuração de webhook', [
                'config_data' => $configData,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Obtém configuração de webhook por ID
     * Endpoint: GET webhooks/configurations/:id
     */
    public function getById(string $id): ?array
    {
        $this->validateInitialization();

        // Verifica cache primeiro
        $cached = $this->getCachedConfig($id);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', "webhooks/configurations/{$id}");
            $data = $response; // makeHttpRequest already returns array

            // Handle new structured response format from notification-service
            if (isset($data['found']) && $data['found'] === false) {
                $this->logger->info('Webhook configuration not found', [
                    'id' => $id,
                    'message' => $data['message'] ?? 'No webhook configuration found'
                ]);
                return null;
            }

            // Extract config from new response structure
            $config = isset($data['config']) ? $data['config'] : $data;

            // Cache o resultado
            $this->cacheConfig($id, $config);

            return $config;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter configuração de webhook por ID', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Atualiza configuração de webhook por ID
     * Endpoint: PUT webhooks/configurations/:id
     */
    public function updateById(string $id, array $updateData): array
    {
        $this->validateInitialization();

        try {
            $this->logger->info('Atualizando configuração de webhook por ID', [
                'id' => $id,
                'hasEndpoints' => isset($updateData['endpoints'])
            ]);

            $response = $this->makeHttpRequest('PUT', "webhooks/configurations/{$id}", [
                'json' => $updateData
            ]);

            $result = $response; // makeHttpRequest already returns array

            // Atualiza cache
            $this->cacheConfig($id, $result);

            $this->logger->info('Configuração de webhook atualizada', [
                'config_id' => $id
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao atualizar configuração de webhook', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Remove configuração de webhook por ID
     * Endpoint: DELETE webhooks/configurations/:id
     */
    public function deleteById(string $id): bool
    {
        $this->validateInitialization();

        try {
            $this->makeHttpRequest('DELETE', "webhooks/configurations/{$id}");

            // Remove do cache
            $this->invalidateCachedConfig($id);

            $this->logger->info('Configuração de webhook removida', [
                'id' => $id
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao remover configuração de webhook', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get webhook configuration by tenant ID (new primary method)
     *
     * @param string $tenantId Tenant ID
     * @return array|null
     */
    public function getByTenantId(string $tenantId): ?array
    {
        $this->validateInitialization();

        $cacheKey = "webhook_config_{$tenantId}";

        // Check cache first
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', "webhooks/configurations/tenant/{$tenantId}");
            $data = $response; // makeHttpRequest already returns array

            // Handle new structured response format from notification-service
            if (isset($data['found']) && $data['found'] === false) {
                $this->logger->info('Webhook configuration not found for tenant', [
                    'tenant_id' => $tenantId,
                    'message' => $data['message'] ?? 'No webhook configuration found'
                ]);
                return null;
            }

            // Extract config from new response structure
            $config = null;
            if (isset($data['configs']) && is_array($data['configs']) && !empty($data['configs'])) {
                // Multiple configs, return first one
                $config = $data['configs'][0];
            } elseif (isset($data['config'])) {
                // Single config
                $config = $data['config'];
            } else {
                // Fallback for old response format
                $config = $data;
            }

            // Cache the result
            if ($config !== null) {
                $this->setCache($cacheKey, $config, 300); // 5 minutes TTL
            }

            $this->logger->info('Configuração de webhook obtida por tenantId', [
                'tenant_id' => $tenantId
            ]);

            return $config;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter configuração de webhook por tenantId', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * @deprecated Use getByTenantId() instead. Will be removed in v3.0.0
     */
    public function getByPartnerId(string $partnerId): ?array
    {
        error_log("[DEPRECATED] WebhookConfigService::getByPartnerId() is deprecated. Use getByTenantId() instead.");
        return $this->getByTenantId($partnerId);
    }

    /**
     * Get webhook configuration for a specific event and tenant
     *
     * @param string $tenantId Tenant ID
     * @param string $eventType Event type
     * @param string $configName Configuration name (optional)
     * @return array|null
     */
    public function getWebhookConfigForEvent(
        string $tenantId,
        string $eventType,
        string $configName = 'Default Configuration'
    ): ?array {
        $this->validateInitialization();

        try {
            // Use new endpoint structure: GET /tenant/{tenantId}/configs/{configName}/events/{eventType}
            $endpoint = "webhooks/configurations/tenant/{$tenantId}/configs/{$configName}/events/{$eventType}";
            $response = $this->makeHttpRequest('GET', $endpoint);
            $data = $response; // makeHttpRequest already returns array

            // Handle new structured response format from notification-service
            if (isset($data['found']) && $data['found'] === false) {
                $this->logger->info('Webhook configuration not found for event', [
                    'tenant_id' => $tenantId,
                    'event_type' => $eventType,
                    'config_name' => $configName,
                    'message' => $data['message'] ?? 'No webhook configuration found'
                ]);
                return null;
            }

            // Extract config from new response structure
            if (isset($data['endpoint'])) {
                $this->logger->info('Configuração de webhook obtida para evento específico', [
                    'tenant_id' => $tenantId,
                    'event_type' => $eventType,
                    'config_name' => $configName
                ]);
                return $data['endpoint'];
            }

            // Fallback for old response format (backward compatibility)
            $this->logger->info('Configuração de webhook obtida para evento específico', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'config_name' => $configName
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter configuração de webhook para evento', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'config_name' => $configName,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * @deprecated Use getWebhookConfigForEvent() with tenantId instead
     */
    public function getWebhookConfig(string $partnerId, string $eventType): ?array
    {
        error_log("[DEPRECATED] WebhookConfigService::getWebhookConfig() is deprecated. Use getWebhookConfigForEvent() instead.");
        return $this->getWebhookConfigForEvent($partnerId, $eventType);
    }

    /**
     * Testa um webhook
     * Endpoint: POST webhooks/configurations/tenant/:tenantId/test
     */
    public function testWebhook(string $partnerId, array $testData): array
    {
        $this->validateInitialization();

        try {
            $this->logger->info('Testando webhook para tenant', [
                'tenantId' => $partnerId,
                'eventType' => $testData['eventType'] ?? 'unknown'
            ]);

            $response = $this->makeHttpRequest('POST', "webhooks/configurations/tenant/{$partnerId}/test", [
                'json' => $testData
            ]);

            $result = $response; // makeHttpRequest already returns array

            $this->logger->info('Teste de webhook completado', [
                'tenantId' => $partnerId,
                'success' => $result['success'] ?? false,
                'responseTime' => $result['responseTime'] ?? 0
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao testar webhook', [
                'tenantId' => $partnerId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test webhook delivery from notification-service
     *
     * This method triggers a test webhook from the notification-service to your application.
     * It uses the authenticated user's JWT token for authorization.
     *
     * Endpoint: POST /api/v1/notifications/test-webhook
     *
     * @param string $eventType Event type to test (e.g., 'order.paid', 'payment.approved')
     * @param array $customData Custom event data payload
     * @param string|null $webhookUrl Optional webhook URL override (defaults to configured URL)
     * @return array Test result with success status, response time, and details
     *
     * @example
     * ```php
     * $result = $sdk->notifications()->testWebhookDelivery(
     *     'order.paid',
     *     ['orderId' => '123', 'amount' => 99.99],
     *     'https://app.clubify.develop/api/webhooks/clubify-checkout'
     * );
     *
     * // Result format:
     * [
     *     'success' => true,
     *     'statusCode' => 200,
     *     'responseTime' => 145,
     *     'responseBody' => '{"status":"received"}',
     *     'error' => null,
     *     'testData' => [...],
     *     'timestamp' => '2025-10-16T12:00:00Z'
     * ]
     * ```
     */
    public function testWebhookDelivery(
        string $eventType,
        array $customData = [],
        ?string $webhookUrl = null
    ): array {
        $this->validateInitialization();

        $startTime = microtime(true);

        try {
            $tenantId = $this->config->getTenantId();
            $organizationId = $this->config->getOrganizationId();

            if (empty($tenantId)) {
                throw new \RuntimeException('Tenant ID is required for webhook testing');
            }

            // Get webhook URL from configuration if not provided
            if ($webhookUrl === null) {
                $webhookConfig = $this->getByTenantId($tenantId);

                if ($webhookConfig === null || empty($webhookConfig['endpoints'])) {
                    throw new \RuntimeException('No webhook configuration found for tenant. Please configure a webhook endpoint first.');
                }

                // Find endpoint for this event type
                $endpoint = null;
                foreach ($webhookConfig['endpoints'] as $ep) {
                    if (isset($ep['eventType']) && $ep['eventType'] === $eventType) {
                        $endpoint = $ep;
                        break;
                    }
                }

                if ($endpoint === null || empty($endpoint['url'])) {
                    throw new \RuntimeException("No webhook URL configured for event type: {$eventType}");
                }

                $webhookUrl = $endpoint['url'];
            }

            // Build test payload
            $testPayload = [
                'event' => $eventType,
                'id' => 'evt_test_' . uniqid(),
                'timestamp' => time(),
                'data' => array_merge([
                    'test' => true,
                    'tenant_id' => $tenantId,
                    'organization_id' => $organizationId,
                ], $customData)
            ];

            // Build request body for notification-service
            $requestBody = [
                'partnerId' => 'clubify-checkout',
                'webhookUrl' => $webhookUrl,
                'testData' => $testPayload
            ];

            $this->logger->info('Testing webhook delivery via notification-service', [
                'tenant_id' => $tenantId,
                'organization_id' => $organizationId,
                'event_type' => $eventType,
                'webhook_url' => $webhookUrl
            ]);

            // Call notification-service test endpoint
            $response = $this->makeHttpRequest('POST', 'notifications/test-webhook', [
                'json' => $requestBody
            ]);

            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $result = [
                'success' => true,
                'statusCode' => $response['statusCode'] ?? 200,
                'responseTime' => round($responseTime, 2),
                'responseBody' => $response['responseBody'] ?? null,
                'webhookUrl' => $webhookUrl,
                'eventType' => $eventType,
                'testData' => $testPayload,
                'error' => null,
                'timestamp' => date('c')
            ];

            $this->logger->info('Webhook delivery test completed successfully', [
                'tenant_id' => $tenantId,
                'event_type' => $eventType,
                'response_time_ms' => $result['responseTime'],
                'status_code' => $result['statusCode']
            ]);

            return $result;

        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->logger->error('Webhook delivery test failed', [
                'tenant_id' => $this->config->getTenantId(),
                'event_type' => $eventType,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
                'response_time_ms' => round($responseTime, 2)
            ]);

            return [
                'success' => false,
                'statusCode' => 0,
                'responseTime' => round($responseTime, 2),
                'responseBody' => null,
                'webhookUrl' => $webhookUrl,
                'eventType' => $eventType,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Test webhook delivery using public API key authentication
     *
     * This method is similar to testWebhookDelivery() but uses API key authentication
     * instead of JWT token. Useful for testing webhooks without user authentication.
     *
     * Endpoint: POST /api/v1/public/notifications/webhook/test
     *
     * @param string $apiKey Public API key
     * @param string $eventType Event type to test
     * @param array $customData Custom event data payload
     * @param string|null $webhookUrl Optional webhook URL override
     * @return array Test result with success status, response time, and details
     */
    public function testWebhookDeliveryWithApiKey(
        string $apiKey,
        string $eventType,
        array $customData = [],
        ?string $webhookUrl = null
    ): array {
        $this->validateInitialization();

        $startTime = microtime(true);

        try {
            $tenantId = $this->config->getTenantId();
            $organizationId = $this->config->getOrganizationId();

            if (empty($organizationId)) {
                throw new \RuntimeException('Organization ID is required for webhook testing with API key');
            }

            // Get webhook URL from configuration if not provided
            if ($webhookUrl === null) {
                $webhookConfig = $this->getByTenantId($tenantId);

                if ($webhookConfig === null || empty($webhookConfig['endpoints'])) {
                    throw new \RuntimeException('No webhook configuration found. Please configure a webhook endpoint first.');
                }

                // Find endpoint for this event type
                $endpoint = null;
                foreach ($webhookConfig['endpoints'] as $ep) {
                    if (isset($ep['eventType']) && $ep['eventType'] === $eventType) {
                        $endpoint = $ep;
                        break;
                    }
                }

                if ($endpoint === null || empty($endpoint['url'])) {
                    throw new \RuntimeException("No webhook URL configured for event type: {$eventType}");
                }

                $webhookUrl = $endpoint['url'];
            }

            // Build test payload
            $testPayload = [
                'event' => $eventType,
                'id' => 'evt_test_' . uniqid(),
                'timestamp' => time(),
                'data' => array_merge([
                    'test' => true,
                    'tenant_id' => $tenantId,
                    'organization_id' => $organizationId,
                ], $customData)
            ];

            // Build request body for notification-service
            $requestBody = [
                'partnerId' => 'clubify-checkout',
                'webhookUrl' => $webhookUrl,
                'testData' => $testPayload
            ];

            $this->logger->info('Testing webhook delivery via notification-service (API key)', [
                'organization_id' => $organizationId,
                'event_type' => $eventType,
                'webhook_url' => $webhookUrl
            ]);

            // Call notification-service public test endpoint with API key
            $httpClient = $this->getHttpClient();
            $response = $httpClient->request('POST', 'public/notifications/webhook/test', [
                'json' => $requestBody,
                'headers' => [
                    'x-api-key' => $apiKey,
                    'x-organization-id' => $organizationId,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $responseData = ResponseHelper::getData($response);
            $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            $result = [
                'success' => true,
                'statusCode' => $response->getStatusCode(),
                'responseTime' => round($responseTime, 2),
                'responseBody' => $responseData['responseBody'] ?? null,
                'webhookUrl' => $webhookUrl,
                'eventType' => $eventType,
                'testData' => $testPayload,
                'error' => null,
                'timestamp' => date('c')
            ];

            $this->logger->info('Webhook delivery test (API key) completed successfully', [
                'organization_id' => $organizationId,
                'event_type' => $eventType,
                'response_time_ms' => $result['responseTime'],
                'status_code' => $result['statusCode']
            ]);

            return $result;

        } catch (\Exception $e) {
            $responseTime = (microtime(true) - $startTime) * 1000;

            $this->logger->error('Webhook delivery test (API key) failed', [
                'organization_id' => $this->config->getOrganizationId(),
                'event_type' => $eventType,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage(),
                'response_time_ms' => round($responseTime, 2)
            ]);

            return [
                'success' => false,
                'statusCode' => 0,
                'responseTime' => round($responseTime, 2),
                'responseBody' => null,
                'webhookUrl' => $webhookUrl,
                'eventType' => $eventType,
                'error' => $e->getMessage(),
                'timestamp' => date('c')
            ];
        }
    }

    /**
     * Lista todas as configurações de webhook (Admin)
     * Endpoint: GET webhooks/configurations
     */
    public function findAll(int $page = 1, int $limit = 50): array
    {
        $this->validateInitialization();

        try {
            $params = [
                'page' => $page,
                'limit' => $limit
            ];

            $response = $this->makeHttpRequest('GET', 'webhooks/configurations', [
                'query' => $params
            ]);

            $data = $response; // makeHttpRequest already returns array

            $this->logger->info('Configurações de webhook listadas', [
                'total' => $data['total'] ?? 0,
                'page' => $page,
                'limit' => $limit
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao listar configurações de webhook', [
                'page' => $page,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);

            return [
                'configurations' => [],
                'total' => 0,
                'page' => $page,
                'pages' => 0
            ];
        }
    }

    /**
     * Obtém métricas de entrega de webhook
     * Endpoint: GET webhooks/configurations/tenant/:tenantId/metrics
     */
    public function getWebhookMetrics(string $partnerId): array
    {
        $this->validateInitialization();

        try {
            $response = $this->makeHttpRequest('GET', "webhooks/configurations/tenant/{$partnerId}/metrics");
            $data = $response; // makeHttpRequest already returns array

            // Handle new structured response format from notification-service
            if (isset($data['found']) && $data['found'] === false) {
                $this->logger->info('Webhook configuration not found for metrics', [
                    'tenantId' => $partnerId,
                    'message' => $data['message'] ?? 'No webhook configuration found'
                ]);
                return [
                    'totalDeliveries' => 0,
                    'successfulDeliveries' => 0,
                    'failedDeliveries' => 0,
                    'successRate' => 0,
                    'isHealthy' => false
                ];
            }

            // Extract metrics from new response structure
            $metrics = isset($data['config']) ? $data['config'] : $data;

            $this->logger->info('Métricas de webhook obtidas', [
                'tenantId' => $partnerId,
                'totalDeliveries' => $metrics['totalDeliveries'] ?? 0,
                'successRate' => $metrics['successRate'] ?? 0
            ]);

            return $metrics;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter métricas de webhook', [
                'tenantId' => $partnerId,
                'error' => $e->getMessage()
            ]);

            return [
                'totalDeliveries' => 0,
                'successfulDeliveries' => 0,
                'failedDeliveries' => 0,
                'successRate' => 0,
                'isHealthy' => false
            ];
        }
    }

    /**
     * Valida configuração de webhook
     * Endpoint: POST webhooks/configurations/tenant/:tenantId/validate
     */
    public function validateConfiguration(string $partnerId, array $configData): array
    {
        $this->validateInitialization();

        try {
            $response = $this->makeHttpRequest('POST', "webhooks/configurations/tenant/{$partnerId}/validate", [
                'json' => $configData
            ]);

            $data = $response; // makeHttpRequest already returns array

            $this->logger->info('Configuração de webhook validada', [
                'tenantId' => $partnerId,
                'valid' => $data['valid'] ?? false,
                'errorCount' => count($data['errors'] ?? [])
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao validar configuração de webhook', [
                'tenantId' => $partnerId,
                'error' => $e->getMessage()
            ]);

            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => []
            ];
        }
    }

    /**
     * Obtém uma configuração de webhook
     */
    public function get(string $configId): ?array
    {
        $this->validateInitialization();

        // Verifica cache primeiro
        $cached = $this->getCachedConfig($configId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', "/notifications/webhook/config/{$configId}");
            $data = $response; // makeHttpRequest already returns array

            // Cache o resultado
            $this->cacheConfig($configId, $data);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter configuração de webhook', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Atualiza uma configuração de webhook
     */
    public function update(string $configId, array $configData): array
    {
        $this->validateInitialization();

        try {
            // Obtém configuração atual
            $currentConfig = $this->get($configId);
            if ($currentConfig === null) {
                throw new \InvalidArgumentException("Configuração de webhook não encontrada: {$configId}");
            }

            // Merge com dados atuais
            $mergedData = array_merge($currentConfig, $configData);
            $webhookConfig = WebhookConfigData::fromArray($mergedData);

            $this->logger->info('Atualizando configuração de webhook', [
                'config_id' => $configId,
                'name' => $webhookConfig->name,
                'changes' => array_keys($configData)
            ]);

            // Valida URL se foi alterada
            if (isset($configData['url'])) {
                $urlValidation = $this->validateUrl($webhookConfig->url);
                if (!$urlValidation['valid']) {
                    throw new \InvalidArgumentException('URL inválida: ' . implode(', ', $urlValidation['errors']));
                }
            }

            // Valida eventos se foram alterados
            if (isset($configData['events'])) {
                $this->validateEvents($webhookConfig->events);
            }

            // Envia para API
            $response = $this->makeHttpRequest('PUT', "/notifications/webhook/config/{$configId}", [
                'json' => $configData
            ]);

            $result = $response; // makeHttpRequest already returns array

            // Atualiza cache
            $this->cacheConfig($configId, $result);

            $this->logger->info('Configuração de webhook atualizada', [
                'config_id' => $configId,
                'name' => $webhookConfig->name
            ]);

            // Dispara evento
            $this->dispatchEvent('webhook_config.updated', [
                'config_id' => $configId,
                'changes' => $configData,
                'result' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao atualizar configuração de webhook', [
                'config_id' => $configId,
                'config_data' => $configData,
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    /**
     * Remove uma configuração de webhook
     */
    public function delete(string $configId): bool
    {
        $this->validateInitialization();

        try {
            $response = $this->makeHttpRequest('DELETE', "/notifications/webhook/config/{$configId}");

            if ($response->getStatusCode() === 200) {
                // Remove do cache
                $this->invalidateCachedConfig($configId);

                $this->logger->info('Configuração de webhook removida', [
                    'config_id' => $configId
                ]);

                // Dispara evento
                $this->dispatchEvent('webhook_config.deleted', [
                    'config_id' => $configId
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao remover configuração de webhook', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Lista configurações de webhook
     */
    public function list(array $filters = []): array
    {
        $this->validateInitialization();

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/webhook/configs', [
                'query' => $filters
            ]);

            $data = $response; // makeHttpRequest already returns array

            $this->logger->info('Configurações de webhook listadas', [
                'total' => $data['total'] ?? count($data['data'] ?? []),
                'filters' => $filters
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao listar configurações de webhook', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'total' => 0
            ];
        }
    }

    /**
     * Testa um webhook
     */
    public function test(string $configId, array $testData = []): array
    {
        $this->validateInitialization();

        try {
            $config = $this->get($configId);
            if ($config === null) {
                throw new \InvalidArgumentException("Configuração de webhook não encontrada: {$configId}");
            }

            $payload = array_merge([
                'test' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'config_id' => $configId,
                'webhook_name' => $config['name'] ?? 'Unnamed'
            ], $testData);

            $response = $this->makeHttpRequest('POST', '/notifications/test-webhook', [
                'json' => [
                    'config_id' => $configId,
                    'test_data' => $payload
                ]
            ]);

            $result = [
                'success' => $response->getStatusCode() < 300,
                'status_code' => $response->getStatusCode(),
                'response' => $response->toArray(),
                'test_data' => $payload,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Atualiza último teste na configuração
            $this->updateLastTestResult($configId, $result);

            $this->logger->info('Teste de webhook executado', [
                'config_id' => $configId,
                'success' => $result['success'],
                'status_code' => $result['status_code']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro no teste de webhook', [
                'config_id' => $configId,
                'test_data' => $testData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'test_data' => $testData,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Valida uma configuração de webhook
     */
    public function validate(array $configData): array
    {
        try {
            $webhookConfig = WebhookConfigData::fromArray(array_merge(
                $this->defaultConfig,
                $configData
            ));

            $validations = [
                'valid_structure' => true,
                'valid_url' => false,
                'valid_events' => false,
                'valid_headers' => true,
                'errors' => [],
                'warnings' => []
            ];

            // Valida URL
            $urlValidation = $this->validateUrl($webhookConfig->url);
            $validations['valid_url'] = $urlValidation['valid'];
            $validations['url_details'] = $urlValidation;

            if (!$urlValidation['valid']) {
                $validations['errors'] = array_merge($validations['errors'], $urlValidation['errors']);
            }

            if (!empty($urlValidation['warnings'])) {
                $validations['warnings'] = array_merge($validations['warnings'], $urlValidation['warnings']);
            }

            // Valida eventos
            try {
                $this->validateEvents($webhookConfig->events);
                $validations['valid_events'] = true;
            } catch (\Exception $e) {
                $validations['valid_events'] = false;
                $validations['errors'][] = $e->getMessage();
            }

            // Valida headers
            if ($webhookConfig->headers !== null) {
                foreach ($webhookConfig->headers as $name => $value) {
                    if (!is_string($name) || !is_string($value)) {
                        $validations['valid_headers'] = false;
                        $validations['errors'][] = 'Headers devem ser strings';
                        break;
                    }
                }
            }

            $validations['is_valid'] = $validations['valid_structure']
                && $validations['valid_url']
                && $validations['valid_events']
                && $validations['valid_headers'];

            return $validations;

        } catch (\Exception $e) {
            return [
                'valid_structure' => false,
                'is_valid' => false,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Obtém tipos de eventos disponíveis
     */
    public function getAvailableEventTypes(): array
    {
        return NotificationType::all();
    }

    /**
     * Configura eventos para um webhook
     */
    public function configureEvents(string $configId, array $eventTypes): bool
    {
        $this->validateInitialization();

        try {
            // Valida eventos
            $this->validateEvents($eventTypes);

            return $this->update($configId, ['events' => $eventTypes])['id'] === $configId;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao configurar eventos de webhook', [
                'config_id' => $configId,
                'events' => $eventTypes,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Verifica conectividade de um webhook
     */
    public function checkConnectivity(string $configId): array
    {
        $this->validateInitialization();

        try {
            $config = $this->get($configId);
            if ($config === null) {
                throw new \InvalidArgumentException("Configuração de webhook não encontrada: {$configId}");
            }

            $webhookConfig = WebhookConfigData::fromArray($config);

            $startTime = microtime(true);

            // Faz uma requisição simples para verificar conectividade
            $ch = curl_init();
            curl_setopt_array($ch, $webhookConfig->getCurlOptions());
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['ping' => true]));
            curl_setopt($ch, CURLOPT_HEADER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            $responseTime = microtime(true) - $startTime;

            curl_close($ch);

            $result = [
                'reachable' => $error === '' && $httpCode > 0,
                'http_code' => $httpCode,
                'response_time' => $responseTime,
                'error' => $error ?: null,
                'timestamp' => date('Y-m-d H:i:s'),
                'success_codes' => $webhookConfig->getSuccessCodes(),
                'is_success_code' => $webhookConfig->isSuccessCode($httpCode)
            ];

            $this->logger->info('Conectividade de webhook verificada', [
                'config_id' => $configId,
                'reachable' => $result['reachable'],
                'http_code' => $httpCode,
                'response_time' => $responseTime
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao verificar conectividade de webhook', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);

            return [
                'reachable' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Valida URL de webhook
     */
    private function validateUrl(string $url): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => []
        ];

        // Validação básica de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['errors'][] = 'URL inválida';
            return $result;
        }

        $parsedUrl = parse_url($url);

        // Verifica se é HTTP/HTTPS
        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'])) {
            $result['errors'][] = 'URL deve usar protocolo HTTP ou HTTPS';
            return $result;
        }

        // Recomenda HTTPS
        if ($parsedUrl['scheme'] === 'http') {
            $result['warnings'][] = 'Recomendamos usar HTTPS para maior segurança';
        }

        // Verifica se não é localhost/IP privado em produção
        $host = $parsedUrl['host'] ?? '';
        if (in_array($host, ['localhost', '127.0.0.1', '::1'])) {
            $result['warnings'][] = 'URL aponta para localhost';
        }

        $result['valid'] = empty($result['errors']);

        return $result;
    }

    /**
     * Valida eventos
     */
    private function validateEvents(array $events): void
    {
        if (empty($events)) {
            throw new \InvalidArgumentException('Pelo menos um evento deve ser configurado');
        }

        $availableEvents = NotificationType::all();

        foreach ($events as $event) {
            if ($event !== '*' && !in_array($event, $availableEvents)) {
                throw new \InvalidArgumentException("Evento inválido: {$event}");
            }
        }
    }

    /**
     * Atualiza resultado do último teste
     */
    private function updateLastTestResult(string $configId, array $testResult): void
    {
        try {
            $this->update($configId, [
                'last_tested_at' => date('Y-m-d H:i:s'),
                'last_test_result' => $testResult['success'] ? 'success' : 'failure'
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Erro ao atualizar resultado do teste', [
                'config_id' => $configId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cache de configuração
     */
    private function cacheConfig(string $configId, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . $configId;
        $this->setCache($cacheKey, $data, self::STATS_CACHE_TTL);
    }

    /**
     * Obtém configuração do cache
     */
    private function getCachedConfig(string $configId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $configId;
        return $this->getFromCache($cacheKey);
    }

    /**
     * Invalida cache de configuração
     */
    private function invalidateCachedConfig(string $configId): void
    {
        $cacheKey = self::CACHE_PREFIX . $configId;
        $this->deleteFromCache($cacheKey);
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $httpClient = $this->getHttpClient();
            $response = $httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new \RuntimeException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new \RuntimeException("Failed to decode response data from {$uri}");
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
