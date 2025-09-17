<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Services;

use Clubify\Checkout\Core\Services\BaseService;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Notifications\DTOs\WebhookConfigData;
use Clubify\Checkout\Modules\Notifications\Enums\NotificationType;

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
class WebhookConfigService extends BaseService
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
     * Cria uma nova configuração de webhook
     */
    public function create(array $configData): array
    {
        $this->validateInitialization();

        try {
            // Cria DTO de configuração
            $webhookConfig = WebhookConfigData::fromArray(array_merge(
                $this->defaultConfig,
                $configData
            ));

            $this->logger->info('Criando configuração de webhook', [
                'name' => $webhookConfig->name,
                'url' => $webhookConfig->url,
                'events' => $webhookConfig->events
            ]);

            // Valida URL
            $urlValidation = $this->validateUrl($webhookConfig->url);
            if (!$urlValidation['valid']) {
                throw new \InvalidArgumentException('URL inválida: ' . implode(', ', $urlValidation['errors']));
            }

            // Valida eventos
            $this->validateEvents($webhookConfig->events);

            // Envia para API
            $response = $this->httpClient->post('/notifications/webhook/config', [
                'json' => $webhookConfig->toArray()
            ]);

            $result = $response->toArray();

            // Cache da configuração
            $this->cacheConfig($result['id'], $result);

            $this->logger->info('Configuração de webhook criada', [
                'config_id' => $result['id'],
                'name' => $webhookConfig->name
            ]);

            // Dispara evento
            $this->dispatchEvent('webhook_config.created', [
                'config' => $webhookConfig->toSafeArray(),
                'result' => $result
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
            $response = $this->httpClient->get("/notifications/webhook/config/{$configId}");
            $data = $response->toArray();

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
            $response = $this->httpClient->put("/notifications/webhook/config/{$configId}", [
                'json' => $configData
            ]);

            $result = $response->toArray();

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
            $response = $this->httpClient->delete("/notifications/webhook/config/{$configId}");

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
            $response = $this->httpClient->get('/notifications/webhook/configs', [
                'query' => $filters
            ]);

            $data = $response->toArray();

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

            $response = $this->httpClient->post('/notifications/test-webhook', [
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
        $this->cache->set($cacheKey, $data, self::STATS_CACHE_TTL);
    }

    /**
     * Obtém configuração do cache
     */
    private function getCachedConfig(string $configId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $configId;
        return $this->cache->get($cacheKey);
    }

    /**
     * Invalida cache de configuração
     */
    private function invalidateCachedConfig(string $configId): void
    {
        $cacheKey = self::CACHE_PREFIX . $configId;
        $this->cache->delete($cacheKey);
    }
}
