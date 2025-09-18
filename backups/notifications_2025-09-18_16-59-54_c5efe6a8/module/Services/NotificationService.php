<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Services;

use Clubify\Checkout\Core\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Notifications\DTOs\NotificationData;
use Clubify\Checkout\Modules\Notifications\Enums\NotificationType;

/**
 * Serviço de gestão de notificações
 *
 * Responsável pela gestão completa de notificações:
 * - Envio de notificações por diferentes canais
 * - Gestão de templates e personalização
 * - Controle de retry e falhas
 * - Tracking de entrega e métricas
 * - Validação e testes de entrega
 * - Operações em lote
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas gestão de notificações
 * - O: Open/Closed - Extensível via novos canais
 * - L: Liskov Substitution - Estende BaseService
 * - I: Interface Segregation - Métodos específicos
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationService extends BaseService implements ServiceInterface
{
    private const CACHE_PREFIX = 'notifications:';
    private const STATS_CACHE_TTL = 300; // 5 minutos

    private array $metrics = [
        'total_sent' => 0,
        'total_delivered' => 0,
        'total_failed' => 0,
        'total_retries' => 0,
        'avg_delivery_time' => 0.0
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
        return 'notification';
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
            $response = $this->httpClient->get('/notifications/health');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::STATS_CACHE_TTL,
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
     * Envia uma notificação
     */
    public function send(array $notificationData): array
    {
        $this->validateInitialization();

        $startTime = microtime(true);

        try {
            // Cria DTO de notificação
            $notification = NotificationData::fromArray($notificationData);

            $this->logger->info('Enviando notificação', [
                'notification_id' => $notification->id,
                'type' => $notification->type,
                'recipient' => $notification->recipient,
                'delivery_method' => $notification->deliveryMethod
            ]);

            // Valida dados da notificação
            if (!$notification->hasValidRecipient()) {
                throw new \InvalidArgumentException('Destinatário inválido para o método de entrega especificado');
            }

            // Prepara dados para envio
            $payload = $this->preparePayload($notification);

            // Envia através do endpoint apropriado
            $response = $this->sendThroughChannel($notification, $payload);

            // Atualiza status para enviado
            $deliveryTime = microtime(true) - $startTime;
            $response['delivery_time'] = $deliveryTime;
            $response['status'] = 'sent';

            // Atualiza métricas
            $this->updateMetrics($response, $deliveryTime);

            // Cache da notificação
            $this->cacheNotification($notification->id, $response);

            // Log de sucesso
            $this->logger->info('Notificação enviada com sucesso', [
                'notification_id' => $notification->id,
                'delivery_time' => $deliveryTime,
                'status_code' => $response['status_code'] ?? null
            ]);

            // Dispara evento
            $this->dispatchEvent('notification.sent', [
                'notification' => $notification->toSafeArray(),
                'response' => $response
            ]);

            return $response;

        } catch (\Exception $e) {
            $deliveryTime = microtime(true) - $startTime;

            $this->logger->error('Erro ao enviar notificação', [
                'notification_data' => $notificationData,
                'error' => $e->getMessage(),
                'delivery_time' => $deliveryTime
            ]);

            // Atualiza métricas de falha
            $this->metrics['total_failed']++;

            // Retorna erro
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'delivery_time' => $deliveryTime,
                'status' => 'failed'
            ];
        }
    }

    /**
     * Obtém uma notificação
     */
    public function get(string $notificationId): ?array
    {
        $this->validateInitialization();

        // Verifica cache primeiro
        $cached = $this->getCachedNotification($notificationId);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->httpClient->get("/notifications/{$notificationId}");
            $data = $response->toArray();

            // Cache o resultado
            $this->cacheNotification($notificationId, $data);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter notificação', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Lista notificações com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $this->validateInitialization();

        try {
            $params = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/notifications', ['query' => $params]);
            $data = $response->toArray();

            $this->logger->info('Notificações listadas', [
                'total' => $data['total'] ?? 0,
                'page' => $page,
                'limit' => $limit,
                'filters' => $filters
            ]);

            return $data;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao listar notificações', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'total' => 0,
                'page' => $page,
                'limit' => $limit
            ];
        }
    }

    /**
     * Reenvía uma notificação (retry)
     */
    public function retry(string $notificationId): bool
    {
        $this->validateInitialization();

        try {
            $response = $this->httpClient->post("/notifications/{$notificationId}/retry");

            if ($response->getStatusCode() === 200) {
                $this->metrics['total_retries']++;

                $this->logger->info('Notificação reenviada', [
                    'notification_id' => $notificationId
                ]);

                // Remove do cache para forçar reload
                $this->invalidateCachedNotification($notificationId);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao reenviar notificação', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Cancela uma notificação
     */
    public function cancel(string $notificationId): bool
    {
        $this->validateInitialization();

        try {
            $response = $this->httpClient->post("/notifications/{$notificationId}/cancel");

            if ($response->getStatusCode() === 200) {
                $this->logger->info('Notificação cancelada', [
                    'notification_id' => $notificationId
                ]);

                // Remove do cache
                $this->invalidateCachedNotification($notificationId);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao cancelar notificação', [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Envia múltiplas notificações em lote
     */
    public function bulkSend(array $notifications): array
    {
        $this->validateInitialization();

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($notifications as $index => $notificationData) {
            try {
                $result = $this->send($notificationData);
                $results[$index] = $result;

                if ($result['success'] ?? false) {
                    $successful++;
                } else {
                    $failed++;
                }

            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                $failed++;
            }
        }

        $this->logger->info('Envio em lote concluído', [
            'total' => count($notifications),
            'successful' => $successful,
            'failed' => $failed
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($notifications),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => count($notifications) > 0 ? $successful / count($notifications) : 0
            ]
        ];
    }

    /**
     * Reenvía múltiplas notificações
     */
    public function bulkRetry(array $notificationIds): array
    {
        $this->validateInitialization();

        $results = [];
        $successful = 0;
        $failed = 0;

        foreach ($notificationIds as $notificationId) {
            $success = $this->retry($notificationId);
            $results[$notificationId] = $success;

            if ($success) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $this->logger->info('Retry em lote concluído', [
            'total' => count($notificationIds),
            'successful' => $successful,
            'failed' => $failed
        ]);

        return [
            'results' => $results,
            'summary' => [
                'total' => count($notificationIds),
                'successful' => $successful,
                'failed' => $failed,
                'success_rate' => count($notificationIds) > 0 ? $successful / count($notificationIds) : 0
            ]
        ];
    }

    /**
     * Testa entrega de notificação
     */
    public function testDelivery(array $testData): array
    {
        $this->validateInitialization();

        try {
            $payload = array_merge([
                'test' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'test_id' => uniqid('test_', true)
            ], $testData);

            $response = $this->httpClient->post('/notifications/test', ['json' => $payload]);

            $result = [
                'success' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'response' => $response->toArray(),
                'test_data' => $payload
            ];

            $this->logger->info('Teste de entrega executado', [
                'success' => $result['success'],
                'status_code' => $result['status_code']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Erro no teste de entrega', [
                'test_data' => $testData,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'test_data' => $testData
            ];
        }
    }

    /**
     * Valida dados de notificação
     */
    public function validate(array $notificationData): array
    {
        try {
            $notification = NotificationData::fromArray($notificationData);

            $validations = [
                'valid_structure' => true,
                'valid_recipient' => $notification->hasValidRecipient(),
                'valid_type' => NotificationType::isValid($notification->type),
                'can_retry' => $notification->canRetry(),
                'is_scheduled' => $notification->isScheduled(),
                'errors' => []
            ];

            if (!$validations['valid_recipient']) {
                $validations['errors'][] = 'Destinatário inválido para o método de entrega';
            }

            if (!$validations['valid_type']) {
                $validations['errors'][] = 'Tipo de notificação inválido';
            }

            $validations['is_valid'] = empty($validations['errors']);

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
     * Executa health check do serviço
     */
    public function healthCheck(): array
    {
        try {
            $response = $this->httpClient->get('/notifications/health');

            return [
                'healthy' => $response->getStatusCode() === 200,
                'status_code' => $response->getStatusCode(),
                'response_time' => $response->getHeaders()['X-Response-Time'][0] ?? null,
                'metrics' => $this->getMetrics()
            ];

        } catch (\Exception $e) {
            return [
                'healthy' => false,
                'error' => $e->getMessage(),
                'metrics' => $this->getMetrics()
            ];
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'success_rate' => $this->calculateSuccessRate(),
            'failure_rate' => $this->calculateFailureRate(),
            'cache_stats' => $this->getCacheStats()
        ]);
    }

    /**
     * Prepara payload para envio
     */
    private function preparePayload(NotificationData $notification): array
    {
        $payload = [
            'id' => $notification->id,
            'type' => $notification->type,
            'recipient' => $notification->recipient,
            'subject' => $notification->subject,
            'body' => $notification->body,
            'delivery_method' => $notification->deliveryMethod,
            'priority' => $notification->priority,
            'timeout' => $notification->timeout,
            'headers' => $notification->headers,
            'metadata' => $notification->metadata,
            'tracking_data' => $notification->getTrackingData()
        ];

        // Adiciona dados de template se disponível
        if ($notification->templateId !== null) {
            $payload['template_id'] = $notification->templateId;
            $payload['template_data'] = $notification->templateData;
        }

        // Adiciona dados de agendamento se aplicável
        if ($notification->scheduledAt !== null) {
            $payload['scheduled_at'] = $notification->scheduledAt->format('Y-m-d H:i:s');
        }

        return $payload;
    }

    /**
     * Envia através do canal apropriado
     */
    private function sendThroughChannel(NotificationData $notification, array $payload): array
    {
        $endpoint = match($notification->deliveryMethod) {
            'email' => '/notifications/email',
            'sms' => '/notifications/sms',
            'webhook' => '/notifications/webhook',
            'push' => '/notifications/push',
            'slack' => '/notifications/slack',
            default => '/notifications/send'
        };

        $response = $this->httpClient->post($endpoint, [
            'json' => $payload,
            'timeout' => $notification->timeout
        ]);

        return [
            'success' => $response->getStatusCode() < 300,
            'status_code' => $response->getStatusCode(),
            'response' => $response->toArray(),
            'notification_id' => $notification->id,
            'delivery_method' => $notification->deliveryMethod
        ];
    }

    /**
     * Atualiza métricas
     */
    private function updateMetrics(array $response, float $deliveryTime): void
    {
        $this->metrics['total_sent']++;

        if ($response['success'] ?? false) {
            $this->metrics['total_delivered']++;
        } else {
            $this->metrics['total_failed']++;
        }

        // Atualiza tempo médio de entrega
        $total = $this->metrics['total_sent'];
        $currentAvg = $this->metrics['avg_delivery_time'];
        $this->metrics['avg_delivery_time'] = (($currentAvg * ($total - 1)) + $deliveryTime) / $total;
    }

    /**
     * Cache de notificação
     */
    private function cacheNotification(string $notificationId, array $data): void
    {
        $cacheKey = self::CACHE_PREFIX . $notificationId;
        $this->cache->set($cacheKey, $data, self::STATS_CACHE_TTL);
    }

    /**
     * Obtém notificação do cache
     */
    private function getCachedNotification(string $notificationId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . $notificationId;
        return $this->cache->get($cacheKey);
    }

    /**
     * Invalida cache de notificação
     */
    private function invalidateCachedNotification(string $notificationId): void
    {
        $cacheKey = self::CACHE_PREFIX . $notificationId;
        $this->cache->delete($cacheKey);
    }

    /**
     * Calcula taxa de sucesso
     */
    private function calculateSuccessRate(): float
    {
        $total = $this->metrics['total_sent'];
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->metrics['total_delivered'] / $total) * 100, 2);
    }

    /**
     * Calcula taxa de falha
     */
    private function calculateFailureRate(): float
    {
        $total = $this->metrics['total_sent'];
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->metrics['total_failed'] / $total) * 100, 2);
    }

    /**
     * Obtém estatísticas do cache
     */
    private function getCacheStats(): array
    {
        return [
            'cache_hits' => 0, // Implementar contadores se necessário
            'cache_misses' => 0,
            'cache_size' => 0
        ];
    }
}
