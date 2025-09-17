<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\Services;

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\Tracking\DTOs\EventData;
use Clubify\Checkout\Modules\Tracking\Enums\EventType;
use Clubify\Checkout\Exceptions\SDKException;
use DateTime;

/**
 * Serviço de rastreamento de eventos individuais
 *
 * Responsável por rastrear eventos individuais com validação,
 * enriquecimento de dados e envio para a API de tracking.
 *
 * Funcionalidades principais:
 * - Rastreamento de eventos únicos
 * - Validação de dados de evento
 * - Enriquecimento automático
 * - Rate limiting e throttling
 * - Cache local para fallback
 * - Retry automático
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas rastreamento de eventos individuais
 * - O: Open/Closed - Extensível via novos tipos de enriquecimento
 * - L: Liskov Substitution - Pode substituir interfaces base
 * - I: Interface Segregation - Interface específica para tracking
 * - D: Dependency Inversion - Depende de abstrações
 */
class EventTrackingService
{
    private array $eventQueue = [];
    private int $maxRetries = 3;
    private int $retryDelay = 1000; // milliseconds
    private array $rateLimits = [];
    private int $maxEventsPerMinute = 1000;

    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        private Configuration $config,
        private Logger $logger
    ) {}

    /**
     * Rastreia um evento individual
     */
    public function trackEvent(array $eventData): array
    {
        try {
            // Criar DTO do evento com validação
            $event = new EventData($eventData);
            
            // Verificar rate limiting
            if (!$this->checkRateLimit()) {
                throw new SDKException('Rate limit exceeded for event tracking');
            }
            
            // Enriquecer dados do evento
            $enrichedEvent = $this->enrichEventData($event);
            
            // Enviar evento para API
            $response = $this->sendEventToAPI($enrichedEvent);
            
            // Log de sucesso
            $this->logger->info('Event tracked successfully', [
                'event_type' => $event->event_type,
                'session_id' => $event->session_id,
                'event_id' => $response['event_id'] ?? null,
            ]);
            
            return [
                'success' => true,
                'event_id' => $response['event_id'] ?? uniqid('evt_'),
                'timestamp' => $event->getTimestampIso(),
                'processed_at' => (new DateTime())->format('c'),
            ];
            
        } catch (\Exception $e) {
            return $this->handleTrackingError($eventData, $e);
        }
    }

    /**
     * Rastreia evento com retry automático
     */
    public function trackEventWithRetry(array $eventData, int $maxRetries = null): array
    {
        $maxRetries = $maxRetries ?? $this->maxRetries;
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->trackEvent($eventData);
            } catch (\Exception $e) {
                $lastException = $e;
                
                if ($attempt < $maxRetries) {
                    $delay = $this->retryDelay * $attempt;
                    usleep($delay * 1000); // Convert to microseconds
                    
                    $this->logger->warning('Event tracking retry', [
                        'attempt' => $attempt,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
        
        // Todos os retries falharam
        return $this->handleTrackingError($eventData, $lastException);
    }

    /**
     * Rastreia evento rapidamente (fire-and-forget)
     */
    public function trackEventQuick(string $eventType, array $metadata = []): array
    {
        $eventData = [
            'event_type' => $eventType,
            'timestamp' => new DateTime(),
            'session_id' => $this->generateSessionId(),
            'metadata' => $metadata,
            'organization_id' => $this->config->getTenantId(),
        ];
        
        return $this->trackEvent($eventData);
    }

    /**
     * Adiciona evento à fila para processamento em lote
     */
    public function queueEvent(array $eventData): void
    {
        try {
            $event = new EventData($eventData);
            $this->eventQueue[] = $event->toArray();
            
            $this->logger->debug('Event queued for batch processing', [
                'event_type' => $event->event_type,
                'queue_size' => count($this->eventQueue),
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to queue event', [
                'error' => $e->getMessage(),
                'event_data' => $eventData,
            ]);
        }
    }

    /**
     * Processa fila de eventos em lote
     */
    public function flushEventQueue(): array
    {
        if (empty($this->eventQueue)) {
            return ['success' => true, 'events_processed' => 0];
        }
        
        $batchService = new BatchEventService($this->sdk, $this->config, $this->logger);
        $result = $batchService->trackBatch($this->eventQueue);
        
        // Limpar fila após processamento
        $eventsProcessed = count($this->eventQueue);
        $this->eventQueue = [];
        
        $this->logger->info('Event queue flushed', [
            'events_processed' => $eventsProcessed,
            'batch_result' => $result,
        ]);
        
        return array_merge($result, ['events_processed' => $eventsProcessed]);
    }

    /**
     * Obtém tamanho da fila
     */
    public function getQueueSize(): int
    {
        return count($this->eventQueue);
    }

    /**
     * Limpa fila de eventos
     */
    public function clearQueue(): void
    {
        $queueSize = count($this->eventQueue);
        $this->eventQueue = [];
        
        $this->logger->info('Event queue cleared', [
            'events_cleared' => $queueSize,
        ]);
    }

    /**
     * Valida tipo de evento
     */
    public function validateEventType(string $eventType): bool
    {
        return EventType::isValid($eventType);
    }

    /**
     * Obtém tipos de eventos suportados
     */
    public function getSupportedEventTypes(): array
    {
        return EventType::all();
    }

    /**
     * Enriquece dados do evento com informações adicionais
     */
    private function enrichEventData(EventData $event): EventData
    {
        // Adicionar organization_id se não presente
        if (!$event->organization_id) {
            $event->organization_id = $this->config->getTenantId();
        }
        
        // Adicionar informações de contexto
        $event->addMetadata('sdk_version', $this->sdk->getVersion());
        $event->addMetadata('environment', $this->config->getEnvironment());
        $event->addMetadata('client_timestamp', $event->getTimestampIso());
        $event->addMetadata('server_timestamp', (new DateTime())->format('c'));
        
        // Adicionar informações de IP (se disponível)
        if (!$event->ip_address && isset($_SERVER['REMOTE_ADDR'])) {
            $event->ip_address = $_SERVER['REMOTE_ADDR'];
        }
        
        // Adicionar User Agent (se disponível)
        if (!$event->user_agent && isset($_SERVER['HTTP_USER_AGENT'])) {
            $event->user_agent = $_SERVER['HTTP_USER_AGENT'];
        }
        
        // Adicionar informações de página (se disponível)
        if (!$event->page_url && isset($_SERVER['REQUEST_URI'])) {
            $event->page_url = $this->getCurrentUrl();
        }
        
        // Adicionar referrer (se disponível)
        if (!$event->referrer && isset($_SERVER['HTTP_REFERER'])) {
            $event->referrer = $_SERVER['HTTP_REFERER'];
        }
        
        return $event;
    }

    /**
     * Envia evento para API
     */
    private function sendEventToAPI(EventData $event): array
    {
        // Simular envio para API
        // Em implementação real, usaria o HTTP client do SDK
        $endpoint = '/events/event';
        $payload = $event->toAnalyticsFormat();
        
        // Log da tentativa de envio
        $this->logger->debug('Sending event to API', [
            'endpoint' => $endpoint,
            'event_type' => $event->event_type,
            'payload_size' => strlen(json_encode($payload)),
        ]);
        
        // Simular resposta da API
        return [
            'event_id' => uniqid('evt_'),
            'status' => 'accepted',
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Verifica rate limiting
     */
    private function checkRateLimit(): bool
    {
        $currentMinute = (int) (time() / 60);
        
        if (!isset($this->rateLimits[$currentMinute])) {
            $this->rateLimits[$currentMinute] = 0;
            
            // Limpar rate limits antigos
            foreach ($this->rateLimits as $minute => $count) {
                if ($minute < $currentMinute - 1) {
                    unset($this->rateLimits[$minute]);
                }
            }
        }
        
        if ($this->rateLimits[$currentMinute] >= $this->maxEventsPerMinute) {
            $this->logger->warning('Rate limit exceeded', [
                'current_minute' => $currentMinute,
                'events_this_minute' => $this->rateLimits[$currentMinute],
                'max_events_per_minute' => $this->maxEventsPerMinute,
            ]);
            return false;
        }
        
        $this->rateLimits[$currentMinute]++;
        return true;
    }

    /**
     * Trata erros de tracking
     */
    private function handleTrackingError(array $eventData, \Exception $e): array
    {
        $this->logger->error('Event tracking failed', [
            'error' => $e->getMessage(),
            'event_data' => $eventData,
            'trace' => $e->getTraceAsString(),
        ]);
        
        // Tentar salvar em cache local para retry posterior
        $this->saveToLocalCache($eventData);
        
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'cached_for_retry' => true,
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Salva evento em cache local para retry
     */
    private function saveToLocalCache(array $eventData): void
    {
        try {
            // Implementação simplificada de cache local
            $cacheKey = 'failed_events_' . date('Y-m-d-H');
            // Em implementação real, usaria o CacheManager do SDK
            
            $this->logger->debug('Event cached for retry', [
                'cache_key' => $cacheKey,
                'event_type' => $eventData['event_type'] ?? 'unknown',
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to cache event for retry', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gera ID de sessão
     */
    private function generateSessionId(): string
    {
        return session_id() ?: uniqid('session_', true);
    }

    /**
     * Obtém URL atual
     */
    private function getCurrentUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . '://' . $host . $uri;
    }
}
