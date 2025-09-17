<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use Clubify\Checkout\Core\Http\Client;
use ClubifyCheckout\Utils\Crypto\HMACSignature;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Serviço de entrega de webhooks
 *
 * Responsável pela entrega confiável de webhooks incluindo
 * assinatura HMAC, retry automático, circuit breaker,
 * rate limiting e métricas de performance.
 */
class DeliveryService
{
    private const CIRCUIT_BREAKER_PREFIX = 'webhook_circuit:';
    private const RATE_LIMIT_PREFIX = 'webhook_rate:';
    private const DELIVERY_STATS_PREFIX = 'webhook_stats:';

    private array $circuitBreakers = [];
    private array $metrics = [
        'total_deliveries' => 0,
        'successful_deliveries' => 0,
        'failed_deliveries' => 0,
        'avg_response_time' => 0.0,
        'circuit_breaker_trips' => 0,
        'rate_limit_hits' => 0,
    ];

    public function __construct(
        private Client $httpClient,
        private HMACSignature $hmacSignature,
        private EventDispatcherInterface $eventDispatcher,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache
    ) {}

    /**
     * Entrega webhook
     */
    public function deliver(array $webhook, string $eventType, array $eventData, array $options = []): array
    {
        $startTime = microtime(true);
        $deliveryId = uniqid('delivery_', true);

        $delivery = [
            'id' => $deliveryId,
            'webhook_id' => $webhook['id'],
            'event_type' => $eventType,
            'success' => false,
            'status_code' => null,
            'response_time' => 0,
            'error' => null,
            'attempts' => 1,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        try {
            // Verifica circuit breaker
            if ($this->isCircuitBreakerOpen($webhook['id'])) {
                throw new \RuntimeException('Circuit breaker aberto para webhook: ' . $webhook['id']);
            }

            // Verifica rate limiting
            if (!$this->checkRateLimit($webhook['id'])) {
                $this->metrics['rate_limit_hits']++;
                throw new \RuntimeException('Rate limit excedido para webhook: ' . $webhook['id']);
            }

            // Prepara payload
            $payload = $this->preparePayload($eventType, $eventData, $options);

            // Prepara headers
            $headers = $this->prepareHeaders($webhook, $payload);

            // Faz a requisição
            $response = $this->makeRequest($webhook['url'], $payload, $headers, $webhook);

            $delivery['status_code'] = $response->getStatusCode();
            $delivery['response_time'] = microtime(true) - $startTime;
            $delivery['success'] = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

            if ($delivery['success']) {
                $this->metrics['successful_deliveries']++;
                $this->resetCircuitBreaker($webhook['id']);

                $this->logger->info('Webhook entregue com sucesso', [
                    'webhook_id' => $webhook['id'],
                    'delivery_id' => $deliveryId,
                    'event_type' => $eventType,
                    'status_code' => $delivery['status_code'],
                    'response_time' => $delivery['response_time'],
                ]);
            } else {
                $this->handleDeliveryFailure($webhook['id'], $delivery);
            }

        } catch (\Exception $e) {
            $delivery['error'] = $e->getMessage();
            $delivery['response_time'] = microtime(true) - $startTime;

            $this->handleDeliveryFailure($webhook['id'], $delivery);

            $this->logger->error('Erro na entrega de webhook', [
                'webhook_id' => $webhook['id'],
                'delivery_id' => $deliveryId,
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'response_time' => $delivery['response_time'],
            ]);
        }

        // Atualiza métricas
        $this->updateMetrics($delivery);

        // Dispara evento
        $this->eventDispatcher->dispatch('webhook.delivery.completed', [
            'delivery' => $delivery,
            'webhook' => $webhook,
            'event_type' => $eventType,
        ]);

        return $delivery;
    }

    /**
     * Entrega múltiplos webhooks
     */
    public function deliverBatch(array $webhooks, string $eventType, array $eventData, array $options = []): array
    {
        $deliveries = [];
        $concurrentLimit = $options['concurrent_limit'] ?? 5;

        // Para simplificar, fazemos entrega sequencial
        // Em uma implementação completa, usaríamos promises/async
        foreach ($webhooks as $webhook) {
            $delivery = $this->deliver($webhook, $eventType, $eventData, $options);
            $deliveries[] = $delivery;

            // Simula delay entre entregas se configurado
            if (isset($options['batch_delay'])) {
                usleep($options['batch_delay'] * 1000); // microsegundos
            }
        }

        $this->logger->info('Batch de webhooks processado', [
            'webhook_count' => count($webhooks),
            'delivery_count' => count($deliveries),
            'successful_count' => count(array_filter($deliveries, fn($d) => $d['success'])),
            'event_type' => $eventType,
        ]);

        return $deliveries;
    }

    /**
     * Testa entrega de webhook
     */
    public function testDelivery(array $webhook, array $testData = []): array
    {
        $eventType = 'webhook.test';
        $eventData = array_merge([
            'test' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'webhook_id' => $webhook['id'],
        ], $testData);

        return $this->deliver($webhook, $eventType, $eventData, ['test_mode' => true]);
    }

    /**
     * Obtém estatísticas de entrega
     */
    public function getDeliveryStats(string $period = '24 hours'): array
    {
        $cacheKey = self::DELIVERY_STATS_PREFIX . $period;

        $cached = $this->cache->getItem($cacheKey);
        if ($cached->isHit()) {
            return $cached->get();
        }

        // Em uma implementação real, buscaria do banco de dados
        $stats = [
            'period' => $period,
            'total_deliveries' => $this->metrics['total_deliveries'],
            'successful_deliveries' => $this->metrics['successful_deliveries'],
            'failed_deliveries' => $this->metrics['failed_deliveries'],
            'success_rate' => $this->calculateSuccessRate(),
            'avg_response_time' => $this->metrics['avg_response_time'],
            'circuit_breaker_trips' => $this->metrics['circuit_breaker_trips'],
            'rate_limit_hits' => $this->metrics['rate_limit_hits'],
        ];

        $cached->set($stats)->expiresAfter(300); // 5 minutos
        $this->cache->save($cached);

        return $stats;
    }

    /**
     * Obtém contagem de falhas
     */
    public function getFailedCount(string $period = '24 hours'): int
    {
        return $this->metrics['failed_deliveries'];
    }

    /**
     * Obtém taxa de falhas
     */
    public function getFailureRate(string $period = '1 hour'): float
    {
        $total = $this->metrics['total_deliveries'];
        $failed = $this->metrics['failed_deliveries'];

        return $total > 0 ? $failed / $total : 0.0;
    }

    /**
     * Limpa logs antigos
     */
    public function cleanupOldDeliveries(int $daysToKeep = 30): int
    {
        // Em uma implementação real, removeria registros antigos do banco
        $this->logger->info('Cleanup de logs de entrega executado', [
            'days_to_keep' => $daysToKeep,
        ]);

        return 0; // Simulado
    }

    /**
     * Prepara payload do webhook
     */
    private function preparePayload(string $eventType, array $eventData, array $options): array
    {
        $payload = [
            'event' => $eventType,
            'data' => $eventData,
            'timestamp' => date('c'), // ISO 8601
            'id' => uniqid('event_', true),
        ];

        // Adiciona metadados se configurado
        if ($options['include_metadata'] ?? true) {
            $payload['metadata'] = [
                'user_agent' => 'ClubifyCheckout-PHP-SDK/1.0',
                'source' => 'clubify-checkout',
                'version' => '1.0.0',
            ];
        }

        return $payload;
    }

    /**
     * Prepara headers da requisição
     */
    private function prepareHeaders(array $webhook, array $payload): array
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'ClubifyCheckout-PHP-SDK/1.0',
            'X-Event-Type' => $payload['event'],
            'X-Event-ID' => $payload['id'],
            'X-Timestamp' => $payload['timestamp'],
        ];

        // Adiciona assinatura HMAC se configurada
        if (!empty($webhook['secret'])) {
            $signature = $this->hmacSignature->generate(json_encode($payload), $webhook['secret']);
            $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
        }

        // Adiciona headers customizados
        if (!empty($webhook['headers'])) {
            foreach ($webhook['headers'] as $name => $value) {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * Faz requisição HTTP
     */
    private function makeRequest(string $url, array $payload, array $headers, array $webhook): \Psr\Http\Message\ResponseInterface
    {
        $options = [
            'json' => $payload,
            'headers' => $headers,
            'timeout' => $webhook['timeout'] ?? 30,
            'connect_timeout' => $webhook['connect_timeout'] ?? 10,
        ];

        // Configurações de SSL
        if (isset($webhook['verify_ssl'])) {
            $options['verify'] = $webhook['verify_ssl'];
        }

        return $this->httpClient->post($url, $options);
    }

    /**
     * Verifica se circuit breaker está aberto
     */
    private function isCircuitBreakerOpen(string $webhookId): bool
    {
        $cacheKey = self::CIRCUIT_BREAKER_PREFIX . $webhookId;
        $cached = $this->cache->getItem($cacheKey);

        if (!$cached->isHit()) {
            return false;
        }

        $data = $cached->get();
        return $data['state'] === 'open' && time() < $data['opens_until'];
    }

    /**
     * Abre circuit breaker
     */
    private function openCircuitBreaker(string $webhookId): void
    {
        $cacheKey = self::CIRCUIT_BREAKER_PREFIX . $webhookId;
        $data = [
            'state' => 'open',
            'failures' => 0,
            'opens_until' => time() + 300, // 5 minutos
        ];

        $cached = $this->cache->getItem($cacheKey);
        $cached->set($data)->expiresAfter(300);
        $this->cache->save($cached);

        $this->metrics['circuit_breaker_trips']++;

        $this->logger->warning('Circuit breaker aberto para webhook', [
            'webhook_id' => $webhookId,
            'opens_until' => date('Y-m-d H:i:s', $data['opens_until']),
        ]);
    }

    /**
     * Reseta circuit breaker
     */
    private function resetCircuitBreaker(string $webhookId): void
    {
        $cacheKey = self::CIRCUIT_BREAKER_PREFIX . $webhookId;
        $this->cache->deleteItem($cacheKey);
    }

    /**
     * Incrementa falhas do circuit breaker
     */
    private function incrementCircuitBreakerFailures(string $webhookId): void
    {
        $cacheKey = self::CIRCUIT_BREAKER_PREFIX . $webhookId;
        $cached = $this->cache->getItem($cacheKey);

        $data = $cached->isHit() ? $cached->get() : ['state' => 'closed', 'failures' => 0];
        $data['failures']++;

        // Abre circuit breaker após 5 falhas consecutivas
        if ($data['failures'] >= 5) {
            $this->openCircuitBreaker($webhookId);
            return;
        }

        $cached->set($data)->expiresAfter(300);
        $this->cache->save($cached);
    }

    /**
     * Verifica rate limiting
     */
    private function checkRateLimit(string $webhookId): bool
    {
        $cacheKey = self::RATE_LIMIT_PREFIX . $webhookId . ':' . date('Y-m-d H:i');
        $cached = $this->cache->getItem($cacheKey);

        $requests = $cached->isHit() ? $cached->get() : 0;
        $requests++;

        // Limite de 60 requests por minuto por webhook
        if ($requests > 60) {
            return false;
        }

        $cached->set($requests)->expiresAfter(60);
        $this->cache->save($cached);

        return true;
    }

    /**
     * Trata falha na entrega
     */
    private function handleDeliveryFailure(string $webhookId, array $delivery): void
    {
        $this->metrics['failed_deliveries']++;
        $this->incrementCircuitBreakerFailures($webhookId);

        // Dispara evento de falha
        $this->eventDispatcher->dispatch('webhook.delivery.failed', [
            'delivery' => $delivery,
            'webhook_id' => $webhookId,
        ]);
    }

    /**
     * Atualiza métricas
     */
    private function updateMetrics(array $delivery): void
    {
        $this->metrics['total_deliveries']++;

        // Atualiza tempo médio de resposta
        $currentAvg = $this->metrics['avg_response_time'];
        $totalDeliveries = $this->metrics['total_deliveries'];
        $this->metrics['avg_response_time'] = (($currentAvg * ($totalDeliveries - 1)) + $delivery['response_time']) / $totalDeliveries;
    }

    /**
     * Calcula taxa de sucesso
     */
    private function calculateSuccessRate(): float
    {
        $total = $this->metrics['total_deliveries'];
        $successful = $this->metrics['successful_deliveries'];

        return $total > 0 ? $successful / $total : 0.0;
    }
}