<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de lote de eventos de tracking
 *
 * Representa um conjunto de eventos para processamento em lote,
 * otimizando performance e reduzindo o número de requisições HTTP.
 *
 * Funcionalidades principais:
 * - Validação de lote de eventos
 * - Compactação automática
 * - Deduplicatação de eventos
 * - Controle de tamanho do lote
 * - Metadados do lote
 *
 * Campos obrigatórios:
 * - events: Array de eventos
 * - batch_id: ID único do lote
 * - timestamp: Timestamp de criação do lote
 *
 * Campos opcionais:
 * - session_id: ID da sessão (se todos eventos forem da mesma sessão)
 * - user_id: ID do usuário (se todos eventos forem do mesmo usuário)
 * - compression: Tipo de compressão aplicada
 * - metadata: Metadados do lote
 */
class BatchEventData extends BaseData
{
    public array $events = [];
    public string $batch_id;
    public DateTime $timestamp;
    public ?string $session_id = null;
    public ?string $user_id = null;
    public ?string $organization_id = null;
    public ?string $compression = null;
    public array $metadata = [];
    public string $version = '1.0';
    public int $event_count = 0;
    public ?array $stats = null;

    /**
     * Construtor com validação automática
     */
    public function __construct(array $data = [])
    {
        // Sanitizar dados antes de processar
        $data = $this->sanitizeData($data);
        
        parent::__construct($data);
        
        // Validar dados após construir
        $this->validate();
        
        // Calcular estatísticas
        $this->calculateStats();
    }

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'events' => ['required', 'array', 'min:1'],
            'batch_id' => ['required', 'string', 'min:1'],
            'timestamp' => ['required', 'date'],
            'session_id' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'organization_id' => ['nullable', 'string'],
            'compression' => ['nullable', 'string'],
            'metadata' => ['array'],
            'version' => ['string'],
            'event_count' => ['integer', 'min:0'],
            'stats' => ['nullable', 'array'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Garantir timestamp
        if (!isset($data['timestamp'])) {
            $data['timestamp'] = new DateTime();
        } elseif (is_string($data['timestamp'])) {
            $data['timestamp'] = new DateTime($data['timestamp']);
        }

        // Garantir batch_id
        if (!isset($data['batch_id'])) {
            $data['batch_id'] = uniqid('batch_', true);
        }

        // Garantir arrays
        $data['events'] = $data['events'] ?? [];
        $data['metadata'] = $data['metadata'] ?? [];
        
        // Calcular event_count
        $data['event_count'] = count($data['events']);

        return $data;
    }

    /**
     * Adiciona evento ao lote
     */
    public function addEvent(EventData $event): void
    {
        $this->events[] = $event->toArray();
        $this->event_count = count($this->events);
        $this->calculateStats();
    }

    /**
     * Adiciona múltiplos eventos ao lote
     */
    public function addEvents(array $events): void
    {
        foreach ($events as $event) {
            if ($event instanceof EventData) {
                $this->events[] = $event->toArray();
            } elseif (is_array($event)) {
                $eventData = new EventData($event);
                $this->events[] = $eventData->toArray();
            }
        }
        
        $this->event_count = count($this->events);
        $this->calculateStats();
    }

    /**
     * Remove eventos duplicados
     */
    public function deduplicate(): int
    {
        $originalCount = $this->event_count;
        $uniqueEvents = [];
        $seen = [];
        
        foreach ($this->events as $event) {
            $key = $this->generateEventKey($event);
            if (!isset($seen[$key])) {
                $uniqueEvents[] = $event;
                $seen[$key] = true;
            }
        }
        
        $this->events = $uniqueEvents;
        $this->event_count = count($this->events);
        $this->calculateStats();
        
        return $originalCount - $this->event_count;
    }

    /**
     * Verifica se o lote está cheio
     */
    public function isFull(int $maxSize = 100): bool
    {
        return $this->event_count >= $maxSize;
    }

    /**
     * Verifica se o lote está vazio
     */
    public function isEmpty(): bool
    {
        return $this->event_count === 0;
    }

    /**
     * Obtém tamanho do lote em bytes
     */
    public function getSizeInBytes(): int
    {
        return strlen(json_encode($this->events));
    }

    /**
     * Verifica se precisa ser comprimido
     */
    public function shouldCompress(int $threshold = 1024): bool
    {
        return $this->getSizeInBytes() > $threshold;
    }

    /**
     * Aplica compressão ao lote
     */
    public function compress(string $method = 'gzip'): bool
    {
        if ($method === 'gzip' && function_exists('gzencode')) {
            $compressed = gzencode(json_encode($this->events));
            if ($compressed !== false) {
                $this->events = ['compressed' => base64_encode($compressed)];
                $this->compression = $method;
                return true;
            }
        }
        
        return false;
    }

    /**
     * Descomprime o lote
     */
    public function decompress(): bool
    {
        if ($this->compression === 'gzip' && 
            isset($this->events['compressed']) &&
            function_exists('gzdecode')) {
            
            $compressed = base64_decode($this->events['compressed']);
            $decompressed = gzdecode($compressed);
            
            if ($decompressed !== false) {
                $this->events = json_decode($decompressed, true);
                $this->compression = null;
                $this->event_count = count($this->events);
                return true;
            }
        }
        
        return false;
    }

    /**
     * Filtra eventos por tipo
     */
    public function filterByEventType(string $eventType): array
    {
        return array_filter($this->events, function($event) use ($eventType) {
            return ($event['event_type'] ?? '') === $eventType;
        });
    }

    /**
     * Agrupa eventos por tipo
     */
    public function groupByEventType(): array
    {
        $groups = [];
        
        foreach ($this->events as $event) {
            $type = $event['event_type'] ?? 'unknown';
            if (!isset($groups[$type])) {
                $groups[$type] = [];
            }
            $groups[$type][] = $event;
        }
        
        return $groups;
    }

    /**
     * Obtém estatísticas do lote
     */
    public function getStats(): array
    {
        return $this->stats ?? [];
    }

    /**
     * Obtém eventos de conversão
     */
    public function getConversionEvents(): array
    {
        $conversionTypes = [
            'purchase_completed',
            'subscription_created',
            'trial_started',
            'signup_completed',
            'lead_generated'
        ];
        
        return array_filter($this->events, function($event) use ($conversionTypes) {
            return in_array($event['event_type'] ?? '', $conversionTypes);
        });
    }

    /**
     * Calcula valor total dos eventos
     */
    public function getTotalValue(): float
    {
        $total = 0.0;
        
        foreach ($this->events as $event) {
            $value = $event['metadata']['value'] ?? 
                    $event['metadata']['amount'] ?? 0;
            $total += (float) $value;
        }
        
        return $total;
    }

    /**
     * Exporta para formato de analytics
     */
    public function toAnalyticsFormat(): array
    {
        return [
            'batch_id' => $this->batch_id,
            'timestamp' => $this->timestamp->format('c'),
            'event_count' => $this->event_count,
            'session_id' => $this->session_id,
            'user_id' => $this->user_id,
            'organization_id' => $this->organization_id,
            'total_value' => $this->getTotalValue(),
            'conversion_events' => count($this->getConversionEvents()),
            'event_types' => array_keys($this->groupByEventType()),
            'size_bytes' => $this->getSizeInBytes(),
            'compressed' => $this->compression !== null,
            'stats' => $this->getStats(),
            'events' => $this->events,
        ];
    }

    /**
     * Calcula estatísticas do lote
     */
    private function calculateStats(): void
    {
        if (empty($this->events)) {
            $this->stats = [];
            return;
        }
        
        $eventTypes = $this->groupByEventType();
        $conversionEvents = $this->getConversionEvents();
        
        $this->stats = [
            'total_events' => $this->event_count,
            'unique_event_types' => count($eventTypes),
            'event_type_distribution' => array_map('count', $eventTypes),
            'conversion_events' => count($conversionEvents),
            'conversion_rate' => $this->event_count > 0 ? 
                round((count($conversionEvents) / $this->event_count) * 100, 2) : 0,
            'total_value' => $this->getTotalValue(),
            'size_bytes' => $this->getSizeInBytes(),
            'time_span' => $this->calculateTimeSpan(),
        ];
    }

    /**
     * Calcula intervalo de tempo dos eventos
     */
    private function calculateTimeSpan(): array
    {
        if (empty($this->events)) {
            return ['min' => null, 'max' => null, 'duration_seconds' => 0];
        }
        
        $timestamps = array_map(function($event) {
            return new DateTime($event['timestamp'] ?? 'now');
        }, $this->events);
        
        $min = min($timestamps);
        $max = max($timestamps);
        
        return [
            'min' => $min->format('c'),
            'max' => $max->format('c'),
            'duration_seconds' => $max->getTimestamp() - $min->getTimestamp(),
        ];
    }

    /**
     * Gera chave única para um evento (para deduplicatação)
     */
    private function generateEventKey(array $event): string
    {
        $keyParts = [
            $event['event_type'] ?? '',
            $event['session_id'] ?? '',
            $event['timestamp'] ?? '',
            $event['page_url'] ?? '',
        ];
        
        return md5(implode('|', $keyParts));
    }
}
