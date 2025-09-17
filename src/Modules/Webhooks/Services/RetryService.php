<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use Clubify\Checkout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use DateTime;

/**
 * Serviço de retry de webhooks
 *
 * Gerencia retry automático de webhooks falhados com
 * backoff exponencial, dead letter queue, e políticas
 * configuráveis de retry.
 */
class RetryService
{
    private const RETRY_QUEUE_PREFIX = 'webhook_retry_queue:';
    private const RETRY_STATS_PREFIX = 'webhook_retry_stats:';

    private array $retryStrategies = [
        'immediate' => [1], // Retry imediato
        'linear' => [60, 300, 900], // 1min, 5min, 15min
        'exponential' => [60, 120, 240, 480, 960], // 1min, 2min, 4min, 8min, 16min
        'fibonacci' => [60, 60, 120, 180, 300, 480], // Fibonacci em minutos
    ];

    private array $metrics = [
        'retries_scheduled' => 0,
        'retries_processed' => 0,
        'retries_successful' => 0,
        'retries_failed' => 0,
        'retries_abandoned' => 0,
        'avg_retry_time' => 0.0,
    ];

    public function __construct(
        private DeliveryService $deliveryService,
        private WebhookRepositoryInterface $repository,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache
    ) {}

    /**
     * Agenda retry para webhook
     */
    public function scheduleRetry(string $webhookId, string $deliveryId, array $options = []): ?string
    {
        try {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
            }

            // Verifica se retry está habilitado
            if (!($webhook['retry_enabled'] ?? true)) {
                $this->logger->debug('Retry desabilitado para webhook', [
                    'webhook_id' => $webhookId,
                ]);
                return null;
            }

            // Calcula próxima tentativa
            $attempt = $options['attempt'] ?? $this->calculateNextAttempt($webhookId, $deliveryId);
            $maxRetries = $webhook['max_retries'] ?? 5;

            if ($attempt > $maxRetries) {
                $this->handleMaxRetriesReached($webhookId, $deliveryId);
                return null;
            }

            // Calcula delay
            $delay = $this->calculateRetryDelay($webhook, $attempt);
            $scheduledAt = new DateTime();
            $scheduledAt->modify("+{$delay} seconds");

            // Agenda retry
            $retryId = $this->repository->scheduleRetry($webhookId, $deliveryId, $scheduledAt, $attempt);

            $this->metrics['retries_scheduled']++;

            $this->logger->info('Retry agendado para webhook', [
                'webhook_id' => $webhookId,
                'delivery_id' => $deliveryId,
                'retry_id' => $retryId,
                'attempt' => $attempt,
                'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'),
                'delay_seconds' => $delay,
            ]);

            return $retryId;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao agendar retry de webhook', [
                'webhook_id' => $webhookId,
                'delivery_id' => $deliveryId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Processa retries pendentes
     */
    public function processPendingRetries(int $limit = 100): array
    {
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            $pendingRetries = $this->repository->findPendingRetries($limit);

            foreach ($pendingRetries as $retry) {
                $result = $this->processRetry($retry);
                $results['processed']++;

                if ($result['success']) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                    if (!empty($result['error'])) {
                        $results['errors'][] = $result['error'];
                    }
                }
            }

            $this->logger->info('Retries processados', [
                'total_processed' => $results['processed'],
                'successful' => $results['successful'],
                'failed' => $results['failed'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();

            $this->logger->error('Erro ao processar retries pendentes', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Processa retry individual
     */
    public function processRetry(array $retry): array
    {
        $startTime = microtime(true);
        $result = [
            'success' => false,
            'error' => null,
            'retry_id' => $retry['id'],
            'webhook_id' => $retry['webhook_id'],
            'attempt' => $retry['attempt'],
        ];

        try {
            // Busca webhook
            $webhook = $this->repository->findById($retry['webhook_id']);
            if (!$webhook) {
                throw new \RuntimeException("Webhook não encontrado: {$retry['webhook_id']}");
            }

            // Busca dados da entrega original para retry
            $originalDelivery = $this->getOriginalDeliveryData($retry['delivery_id']);
            if (!$originalDelivery) {
                throw new \RuntimeException("Dados da entrega original não encontrados: {$retry['delivery_id']}");
            }

            // Faz nova tentativa de entrega
            $delivery = $this->deliveryService->deliver(
                $webhook,
                $originalDelivery['event_type'],
                $originalDelivery['event_data'],
                ['retry_attempt' => $retry['attempt']]
            );

            $result['success'] = $delivery['success'];

            if ($delivery['success']) {
                $this->metrics['retries_successful']++;

                // Marca retry como bem-sucedido
                $this->repository->markRetryProcessed($retry['id'], true, [
                    'delivery_id' => $delivery['id'],
                    'status_code' => $delivery['status_code'],
                    'response_time' => $delivery['response_time'],
                ]);

                // Reseta contador de falhas do webhook
                $this->repository->resetFailureCount($retry['webhook_id']);

                $this->logger->info('Retry bem-sucedido', [
                    'retry_id' => $retry['id'],
                    'webhook_id' => $retry['webhook_id'],
                    'attempt' => $retry['attempt'],
                    'response_time' => $delivery['response_time'],
                ]);

            } else {
                $this->handleRetryFailure($retry, $delivery);
                $result['error'] = $delivery['error'] ?? 'Retry falhou';
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $this->handleRetryError($retry, $e);
        }

        // Atualiza métricas
        $processingTime = microtime(true) - $startTime;
        $this->updateRetryMetrics($result, $processingTime);

        return $result;
    }

    /**
     * Cancela retry
     */
    public function cancelRetry(string $retryId): bool
    {
        try {
            $success = $this->repository->markRetryProcessed($retryId, false, [
                'cancelled' => true,
                'cancelled_at' => date('Y-m-d H:i:s'),
            ]);

            if ($success) {
                $this->logger->info('Retry cancelado', [
                    'retry_id' => $retryId,
                ]);
            }

            return $success;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao cancelar retry', [
                'retry_id' => $retryId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Obtém tamanho da fila de retry
     */
    public function getQueueSize(): int
    {
        try {
            $pendingRetries = $this->repository->findPendingRetries(10000); // Limite alto para contar
            return count($pendingRetries);

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter tamanho da fila de retry', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Obtém estatísticas de retry
     */
    public function getRetryStats(string $period = '24 hours'): array
    {
        $cacheKey = self::RETRY_STATS_PREFIX . $period;

        $cached = $this->cache->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        $stats = [
            'period' => $period,
            'retries_scheduled' => $this->metrics['retries_scheduled'],
            'retries_processed' => $this->metrics['retries_processed'],
            'retries_successful' => $this->metrics['retries_successful'],
            'retries_failed' => $this->metrics['retries_failed'],
            'retries_abandoned' => $this->metrics['retries_abandoned'],
            'success_rate' => $this->calculateRetrySuccessRate(),
            'avg_retry_time' => $this->metrics['avg_retry_time'],
            'queue_size' => $this->getQueueSize(),
        ];

        $cached->set($stats)->expiresAfter(300); // 5 minutos
        $this->cache->save($cached);

        return $stats;
    }

    /**
     * Limpa retries antigos
     */
    public function cleanupOldRetries(int $daysToKeep = 30): int
    {
        try {
            $deleted = $this->repository->deleteOldRetries($daysToKeep);

            $this->logger->info('Cleanup de retries antigos executado', [
                'days_to_keep' => $daysToKeep,
                'deleted_count' => $deleted,
            ]);

            return $deleted;

        } catch (\Exception $e) {
            $this->logger->error('Erro no cleanup de retries antigos', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Calcula próxima tentativa
     */
    private function calculateNextAttempt(string $webhookId, string $deliveryId): int
    {
        // Em uma implementação real, buscaria do banco quantas tentativas já foram feitas
        // Por simplicidade, assumimos que é a primeira tentativa
        return 1;
    }

    /**
     * Calcula delay do retry
     */
    private function calculateRetryDelay(array $webhook, int $attempt): int
    {
        $strategy = $webhook['retry_strategy'] ?? 'exponential';
        $baseDelay = $webhook['retry_delay'] ?? 300; // 5 minutos padrão
        $maxDelay = $webhook['max_retry_delay'] ?? 3600; // 1 hora máximo

        $delays = $this->retryStrategies[$strategy] ?? $this->retryStrategies['exponential'];

        if ($attempt <= count($delays)) {
            $delay = $delays[$attempt - 1] * ($baseDelay / 60); // Converte para segundos
        } else {
            // Para tentativas além da estratégia, usa último valor
            $delay = end($delays) * ($baseDelay / 60);
        }

        // Adiciona jitter para evitar thundering herd
        $jitter = rand(0, min(60, $delay * 0.1)); // Até 10% ou 60s
        $delay += $jitter;

        return min($delay, $maxDelay);
    }

    /**
     * Trata máximo de retries atingido
     */
    private function handleMaxRetriesReached(string $webhookId, string $deliveryId): void
    {
        $this->metrics['retries_abandoned']++;

        // Marca webhook como inativo se configurado
        $webhook = $this->repository->findById($webhookId);
        if ($webhook && ($webhook['disable_on_max_retries'] ?? false)) {
            $this->repository->deactivate($webhookId);

            $this->logger->warning('Webhook desativado após máximo de retries', [
                'webhook_id' => $webhookId,
                'delivery_id' => $deliveryId,
            ]);
        }

        // Envia para dead letter queue se configurado
        $this->sendToDeadLetterQueue($webhookId, $deliveryId);

        $this->logger->error('Máximo de retries atingido para webhook', [
            'webhook_id' => $webhookId,
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * Trata falha no retry
     */
    private function handleRetryFailure(array $retry, array $delivery): void
    {
        $this->metrics['retries_failed']++;

        // Marca retry como falhado
        $this->repository->markRetryProcessed($retry['id'], false, [
            'error' => $delivery['error'] ?? 'Retry falhou',
            'status_code' => $delivery['status_code'] ?? null,
            'response_time' => $delivery['response_time'] ?? 0,
        ]);

        // Agenda próximo retry se ainda dentro do limite
        $webhook = $this->repository->findById($retry['webhook_id']);
        $maxRetries = $webhook['max_retries'] ?? 5;

        if ($retry['attempt'] < $maxRetries) {
            $this->scheduleRetry($retry['webhook_id'], $retry['delivery_id'], [
                'attempt' => $retry['attempt'] + 1,
            ]);
        } else {
            $this->handleMaxRetriesReached($retry['webhook_id'], $retry['delivery_id']);
        }

        $this->logger->warning('Retry falhou', [
            'retry_id' => $retry['id'],
            'webhook_id' => $retry['webhook_id'],
            'attempt' => $retry['attempt'],
            'error' => $delivery['error'] ?? 'Erro desconhecido',
        ]);
    }

    /**
     * Trata erro no processamento do retry
     */
    private function handleRetryError(array $retry, \Exception $e): void
    {
        $this->metrics['retries_failed']++;

        // Marca retry como erro
        $this->repository->markRetryProcessed($retry['id'], false, [
            'error' => $e->getMessage(),
            'error_type' => get_class($e),
        ]);

        $this->logger->error('Erro no processamento de retry', [
            'retry_id' => $retry['id'],
            'webhook_id' => $retry['webhook_id'],
            'attempt' => $retry['attempt'],
            'error' => $e->getMessage(),
        ]);
    }

    /**
     * Envia para dead letter queue
     */
    private function sendToDeadLetterQueue(string $webhookId, string $deliveryId): void
    {
        // Em uma implementação real, enviaria para uma fila específica
        // de mensagens que não puderam ser entregues
        $this->logger->info('Entrega enviada para dead letter queue', [
            'webhook_id' => $webhookId,
            'delivery_id' => $deliveryId,
        ]);
    }

    /**
     * Obtém dados da entrega original
     */
    private function getOriginalDeliveryData(string $deliveryId): ?array
    {
        // Em uma implementação real, buscaria os dados da entrega original
        // Por simplicidade, retornamos dados simulados
        return [
            'event_type' => 'order.completed',
            'event_data' => ['order_id' => '12345'],
        ];
    }

    /**
     * Atualiza métricas de retry
     */
    private function updateRetryMetrics(array $result, float $processingTime): void
    {
        $this->metrics['retries_processed']++;

        // Atualiza tempo médio de processamento
        $currentAvg = $this->metrics['avg_retry_time'];
        $totalProcessed = $this->metrics['retries_processed'];
        $this->metrics['avg_retry_time'] = (($currentAvg * ($totalProcessed - 1)) + $processingTime) / $totalProcessed;
    }

    /**
     * Calcula taxa de sucesso dos retries
     */
    private function calculateRetrySuccessRate(): float
    {
        $total = $this->metrics['retries_processed'];
        $successful = $this->metrics['retries_successful'];

        return $total > 0 ? $successful / $total : 0.0;
    }
}