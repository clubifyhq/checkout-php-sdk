<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Webhooks;

use ClubifyCheckout\Contracts\ModuleInterface;
use ClubifyCheckout\Modules\Webhooks\Services\WebhookService;
use ClubifyCheckout\Modules\Webhooks\Services\ConfigService;
use ClubifyCheckout\Modules\Webhooks\Services\TestingService;
use ClubifyCheckout\Modules\Webhooks\Services\DeliveryService;
use ClubifyCheckout\Modules\Webhooks\Services\RetryService;
use ClubifyCheckout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;
use ClubifyCheckout\Modules\Webhooks\Repositories\WebhookRepository;
use ClubifyCheckout\Core\Http\Client;
use ClubifyCheckout\Core\Events\EventDispatcherInterface;
use ClubifyCheckout\Core\Cache\CacheManagerInterface;
use ClubifyCheckout\Utils\Crypto\HMACSignature;
use Psr\Log\LoggerInterface;

/**
 * Módulo de Webhooks
 *
 * Módulo completo para gerenciamento de webhooks incluindo
 * configuração, entrega, retry automático, validação de
 * assinaturas e sistema de testes abrangente.
 *
 * Funcionalidades principais:
 * - Configuração de endpoints webhook
 * - Entrega confiável com retry automático
 * - Validação de assinatura HMAC
 * - Sistema de testes e debug
 * - Logs de auditoria completos
 * - Rate limiting e circuit breaker
 * - Múltiplos formatos de payload
 * - Filtros de eventos
 *
 * Componentes:
 * - WebhookService: Operações CRUD de webhooks
 * - ConfigService: Configuração e validação
 * - DeliveryService: Entrega de webhooks
 * - RetryService: Gerenciamento de retry
 * - TestingService: Testes e debugging
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gerenciamento de webhooks
 * - O: Open/Closed - Extensível via interfaces
 * - L: Liskov Substitution - Substituível por outras implementações
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class WebhooksModule implements ModuleInterface
{
    private ?WebhookService $webhookService = null;
    private ?ConfigService $configService = null;
    private ?TestingService $testingService = null;
    private ?DeliveryService $deliveryService = null;
    private ?RetryService $retryService = null;

    private array $stats = [
        'webhooks_created' => 0,
        'webhooks_delivered' => 0,
        'webhooks_failed' => 0,
        'webhooks_retried' => 0,
        'total_requests' => 0,
        'avg_response_time' => 0.0,
    ];

    public function __construct(
        private Client $httpClient,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheManagerInterface $cache,
        private HMACSignature $hmacSignature,
        private ?WebhookRepositoryInterface $repository = null
    ) {
        $this->repository ??= new WebhookRepository($this->httpClient);
    }

    /**
     * Obtém serviço de webhooks
     */
    public function webhooks(): WebhookService
    {
        if ($this->webhookService === null) {
            $this->webhookService = new WebhookService(
                $this->repository,
                $this->logger,
                $this->cache
            );
        }

        return $this->webhookService;
    }

    /**
     * Obtém serviço de configuração
     */
    public function config(): ConfigService
    {
        if ($this->configService === null) {
            $this->configService = new ConfigService(
                $this->repository,
                $this->logger,
                $this->cache
            );
        }

        return $this->configService;
    }

    /**
     * Obtém serviço de entrega
     */
    public function delivery(): DeliveryService
    {
        if ($this->deliveryService === null) {
            $this->deliveryService = new DeliveryService(
                $this->httpClient,
                $this->hmacSignature,
                $this->eventDispatcher,
                $this->logger,
                $this->cache
            );
        }

        return $this->deliveryService;
    }

    /**
     * Obtém serviço de retry
     */
    public function retry(): RetryService
    {
        if ($this->retryService === null) {
            $this->retryService = new RetryService(
                $this->delivery(),
                $this->repository,
                $this->logger,
                $this->cache
            );
        }

        return $this->retryService;
    }

    /**
     * Obtém serviço de testes
     */
    public function testing(): TestingService
    {
        if ($this->testingService === null) {
            $this->testingService = new TestingService(
                $this->delivery(),
                $this->repository,
                $this->logger
            );
        }

        return $this->testingService;
    }

    /**
     * Configura webhook completo
     */
    public function setupWebhook(array $webhookData): array
    {
        return $this->executeWithMetrics('setupWebhook', function () use ($webhookData) {
            // Cria webhook
            $webhook = $this->webhooks()->create($webhookData);

            // Testa conectividade se habilitado
            if ($webhookData['test_on_create'] ?? true) {
                $testResult = $this->testing()->testWebhook($webhook['id']);
                $webhook['test_result'] = $testResult;

                if (!$testResult['success']) {
                    $this->logger->warning('Webhook criado mas teste de conectividade falhou', [
                        'webhook_id' => $webhook['id'],
                        'test_result' => $testResult,
                    ]);
                }
            }

            // Dispara evento
            $this->eventDispatcher->dispatch('webhook.setup.completed', [
                'webhook' => $webhook,
                'success' => true,
            ]);

            $this->stats['webhooks_created']++;

            $this->logger->info('Webhook configurado com sucesso', [
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'events' => $webhook['events'],
            ]);

            return $webhook;
        });
    }

    /**
     * Entrega evento para webhooks
     */
    public function deliverEvent(string $eventType, array $eventData, array $options = []): array
    {
        return $this->executeWithMetrics('deliverEvent', function () use ($eventType, $eventData, $options) {
            // Busca webhooks para o evento
            $webhooks = $this->webhooks()->findByEvent($eventType);

            if (empty($webhooks)) {
                $this->logger->debug('Nenhum webhook configurado para evento', [
                    'event_type' => $eventType,
                ]);

                return [
                    'event_type' => $eventType,
                    'webhooks_found' => 0,
                    'deliveries' => [],
                ];
            }

            $deliveries = [];

            foreach ($webhooks as $webhook) {
                try {
                    // Verifica se webhook está ativo
                    if (!$webhook['active']) {
                        continue;
                    }

                    // Aplica filtros se configurados
                    if (!$this->config()->eventPassesFilters($eventType, $eventData, $webhook)) {
                        continue;
                    }

                    // Entrega evento
                    $delivery = $this->delivery()->deliver($webhook, $eventType, $eventData, $options);
                    $deliveries[] = $delivery;

                    if ($delivery['success']) {
                        $this->stats['webhooks_delivered']++;
                    } else {
                        $this->stats['webhooks_failed']++;

                        // Agenda retry se configurado
                        if ($webhook['retry_enabled'] ?? true) {
                            $this->retry()->scheduleRetry($webhook['id'], $delivery['id']);
                            $this->stats['webhooks_retried']++;
                        }
                    }

                } catch (\Exception $e) {
                    $this->logger->error('Erro na entrega de webhook', [
                        'webhook_id' => $webhook['id'],
                        'event_type' => $eventType,
                        'error' => $e->getMessage(),
                    ]);

                    $deliveries[] = [
                        'webhook_id' => $webhook['id'],
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];

                    $this->stats['webhooks_failed']++;
                }
            }

            // Dispara evento de entrega concluída
            $this->eventDispatcher->dispatch('webhook.event.delivered', [
                'event_type' => $eventType,
                'webhooks_count' => count($webhooks),
                'deliveries_count' => count($deliveries),
                'success_count' => count(array_filter($deliveries, fn($d) => $d['success'])),
            ]);

            $this->logger->info('Evento entregue para webhooks', [
                'event_type' => $eventType,
                'webhooks_count' => count($webhooks),
                'deliveries_count' => count($deliveries),
            ]);

            return [
                'event_type' => $eventType,
                'webhooks_found' => count($webhooks),
                'deliveries' => $deliveries,
            ];
        });
    }

    /**
     * Processa retries pendentes
     */
    public function processRetries(int $limit = 100): array
    {
        return $this->executeWithMetrics('processRetries', function () use ($limit) {
            return $this->retry()->processPendingRetries($limit);
        });
    }

    /**
     * Valida assinatura de webhook
     */
    public function validateSignature(string $payload, string $signature, string $secret): bool
    {
        return $this->hmacSignature->verifyWebhook($payload, $signature, $secret);
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        $baseStats = [
            'total_webhooks' => $this->webhooks()->count(),
            'active_webhooks' => $this->webhooks()->count(['active' => true]),
            'failed_deliveries_24h' => $this->delivery()->getFailedCount('24 hours'),
            'retry_queue_size' => $this->retry()->getQueueSize(),
        ];

        return array_merge($baseStats, $this->stats);
    }

    /**
     * Verifica saúde do módulo
     */
    public function isHealthy(): bool
    {
        try {
            // Verifica se repository está funcionando
            $this->repository->findAll(['limit' => 1]);

            // Verifica se há muitas falhas recentes
            $failureRate = $this->delivery()->getFailureRate('1 hour');
            if ($failureRate > 0.5) { // Mais de 50% de falhas na última hora
                return false;
            }

            // Verifica se fila de retry não está muito grande
            $queueSize = $this->retry()->getQueueSize();
            if ($queueSize > 1000) { // Mais de 1000 retries pendentes
                return false;
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Health check do WebhooksModule falhou', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Limpa dados antigos
     */
    public function cleanup(int $daysToKeep = 30): array
    {
        return $this->executeWithMetrics('cleanup', function () use ($daysToKeep) {
            $deleted = [
                'webhooks' => 0,
                'deliveries' => 0,
                'retries' => 0,
            ];

            // Remove webhooks inativos muito antigos
            $deleted['webhooks'] = $this->webhooks()->deleteOldInactive($daysToKeep);

            // Remove logs de entrega antigos
            $deleted['deliveries'] = $this->delivery()->cleanupOldDeliveries($daysToKeep);

            // Remove retries antigos
            $deleted['retries'] = $this->retry()->cleanupOldRetries($daysToKeep);

            $this->logger->info('Cleanup do WebhooksModule concluído', [
                'days_to_keep' => $daysToKeep,
                'deleted' => $deleted,
            ]);

            return $deleted;
        });
    }

    /**
     * Executa operação com métricas
     */
    private function executeWithMetrics(string $operation, callable $callback): mixed
    {
        $startTime = microtime(true);

        try {
            $result = $callback();

            $duration = microtime(true) - $startTime;
            $this->updateMetrics($operation, $duration, true);

            return $result;

        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->updateMetrics($operation, $duration, false);

            throw $e;
        }
    }

    /**
     * Atualiza métricas
     */
    private function updateMetrics(string $operation, float $duration, bool $success): void
    {
        $this->stats['total_requests']++;

        // Atualiza tempo médio de resposta
        $currentAvg = $this->stats['avg_response_time'];
        $totalRequests = $this->stats['total_requests'];
        $this->stats['avg_response_time'] = (($currentAvg * ($totalRequests - 1)) + $duration) / $totalRequests;

        // Log de performance
        if ($duration > 1.0) { // Mais de 1 segundo
            $this->logger->warning('Operação webhook lenta', [
                'operation' => $operation,
                'duration' => $duration,
                'success' => $success,
            ]);
        }
    }

    /**
     * Obtém configuração padrão do módulo
     */
    public function getDefaultConfig(): array
    {
        return [
            'delivery' => [
                'timeout' => 30,
                'retry_enabled' => true,
                'max_retries' => 5,
                'retry_delay' => 300, // 5 minutos
                'signature_header' => 'X-Webhook-Signature',
            ],
            'security' => [
                'validate_ssl' => true,
                'allowed_domains' => [],
                'blocked_domains' => [],
            ],
            'rate_limiting' => [
                'enabled' => true,
                'max_requests_per_minute' => 60,
            ],
            'monitoring' => [
                'log_all_deliveries' => true,
                'alert_on_failure_rate' => 0.1, // 10%
            ],
        ];
    }

    /**
     * Aplica configuração ao módulo
     */
    public function configure(array $config): void
    {
        $this->config()->updateGlobalConfig($config);

        $this->logger->info('Configuração do WebhooksModule atualizada', [
            'config_keys' => array_keys($config),
        ]);
    }

    /**
     * Exporta configuração atual
     */
    public function exportConfig(): array
    {
        return $this->config()->getGlobalConfig();
    }
}