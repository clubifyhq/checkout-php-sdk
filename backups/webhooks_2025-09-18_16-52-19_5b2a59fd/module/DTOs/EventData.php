<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\DTOs;

use Clubify\Checkout\Core\DTOs\BaseDTO;

/**
 * DTO para dados de evento de webhook
 *
 * Representa a estrutura de um evento que será
 * enviado via webhook, incluindo metadados,
 * payload e informações de rastreamento.
 */
class EventData extends BaseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $data,
        public readonly string $source = 'clubify-checkout',
        public readonly string $version = '1.0',
        public readonly ?string $timestamp = null,
        public readonly ?string $organizationId = null,
        public readonly ?string $userId = null,
        public readonly ?string $correlationId = null,
        public readonly ?string $causationId = null,
        public readonly array $metadata = [],
        public readonly array $context = [],
        public readonly array $tags = [],
        public readonly int $attempt = 1,
        public readonly ?string $previousEventId = null,
        public readonly bool $testMode = false,
        public readonly array $delivery = [],
        public readonly ?string $createdAt = null
    ) {
        $this->validate();
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? uniqid('event_', true),
            type: $data['type'] ?? '',
            data: $data['data'] ?? [],
            source: $data['source'] ?? 'clubify-checkout',
            version: $data['version'] ?? '1.0',
            timestamp: $data['timestamp'] ?? date('c'),
            organizationId: $data['organization_id'] ?? null,
            userId: $data['user_id'] ?? null,
            correlationId: $data['correlation_id'] ?? null,
            causationId: $data['causation_id'] ?? null,
            metadata: $data['metadata'] ?? [],
            context: $data['context'] ?? [],
            tags: $data['tags'] ?? [],
            attempt: $data['attempt'] ?? 1,
            previousEventId: $data['previous_event_id'] ?? null,
            testMode: $data['test_mode'] ?? false,
            delivery: $data['delivery'] ?? [],
            createdAt: $data['created_at'] ?? date('Y-m-d H:i:s')
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data,
            'source' => $this->source,
            'version' => $this->version,
            'timestamp' => $this->timestamp,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'correlation_id' => $this->correlationId,
            'causation_id' => $this->causationId,
            'metadata' => $this->metadata,
            'context' => $this->context,
            'tags' => $this->tags,
            'attempt' => $this->attempt,
            'previous_event_id' => $this->previousEventId,
            'test_mode' => $this->testMode,
            'delivery' => $this->delivery,
            'created_at' => $this->createdAt,
        ];
    }

    /**
     * Converte para payload de webhook
     */
    public function toWebhookPayload(): array
    {
        return [
            'event' => $this->type,
            'id' => $this->id,
            'timestamp' => $this->timestamp,
            'data' => $this->data,
            'metadata' => [
                'source' => $this->source,
                'version' => $this->version,
                'organization_id' => $this->organizationId,
                'user_id' => $this->userId,
                'correlation_id' => $this->correlationId,
                'causation_id' => $this->causationId,
                'attempt' => $this->attempt,
                'test_mode' => $this->testMode,
                'context' => $this->context,
                'tags' => $this->tags,
                'delivery' => $this->delivery,
            ] + $this->metadata,
        ];
    }

    /**
     * Cria evento de retry
     */
    public function withRetryAttempt(int $attempt, array $deliveryInfo = []): self
    {
        $data = $this->toArray();
        $data['attempt'] = $attempt;
        $data['delivery'] = array_merge($this->delivery, $deliveryInfo);

        return self::fromArray($data);
    }

    /**
     * Adiciona contexto
     */
    public function withContext(array $context): self
    {
        $data = $this->toArray();
        $data['context'] = array_merge($this->context, $context);

        return self::fromArray($data);
    }

    /**
     * Adiciona metadados
     */
    public function withMetadata(array $metadata): self
    {
        $data = $this->toArray();
        $data['metadata'] = array_merge($this->metadata, $metadata);

        return self::fromArray($data);
    }

    /**
     * Adiciona tags
     */
    public function withTags(array $tags): self
    {
        $data = $this->toArray();
        $data['tags'] = array_unique(array_merge($this->tags, $tags));

        return self::fromArray($data);
    }

    /**
     * Verifica se é evento de teste
     */
    public function isTest(): bool
    {
        return $this->testMode || str_contains($this->type, 'test');
    }

    /**
     * Verifica se é retry
     */
    public function isRetry(): bool
    {
        return $this->attempt > 1;
    }

    /**
     * Obtém categoria do evento
     */
    public function getCategory(): string
    {
        $parts = explode('.', $this->type);
        return $parts[0] ?? 'unknown';
    }

    /**
     * Obtém ação do evento
     */
    public function getAction(): string
    {
        $parts = explode('.', $this->type);
        return $parts[1] ?? 'unknown';
    }

    /**
     * Verifica se evento é crítico
     */
    public function isCritical(): bool
    {
        $criticalEvents = [
            'payment.failed',
            'order.cancelled',
            'user.deleted',
            'system.error',
            'security.breach',
        ];

        return in_array($this->type, $criticalEvents) ||
               in_array('critical', $this->tags);
    }

    /**
     * Obtém prioridade do evento
     */
    public function getPriority(): string
    {
        if ($this->isCritical()) {
            return 'high';
        }

        $highPriorityEvents = [
            'payment.completed',
            'order.created',
            'user.registered',
        ];

        if (in_array($this->type, $highPriorityEvents)) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Obtém TTL do evento (em segundos)
     */
    public function getTTL(): int
    {
        return match ($this->getPriority()) {
            'high' => 3600, // 1 hora
            'medium' => 7200, // 2 horas
            'low' => 86400, // 24 horas
        };
    }

    /**
     * Verifica se evento expirou
     */
    public function isExpired(): bool
    {
        if (!$this->timestamp) {
            return false;
        }

        $eventTime = strtotime($this->timestamp);
        $ttl = $this->getTTL();

        return (time() - $eventTime) > $ttl;
    }

    /**
     * Calcula hash do evento
     */
    public function getHash(): string
    {
        $payload = [
            'type' => $this->type,
            'data' => $this->data,
            'organization_id' => $this->organizationId,
            'correlation_id' => $this->correlationId,
        ];

        return hash('sha256', json_encode($payload, JSON_SORT_KEYS));
    }

    /**
     * Verifica se é duplicata de outro evento
     */
    public function isDuplicateOf(EventData $other): bool
    {
        return $this->type === $other->type &&
               $this->organizationId === $other->organizationId &&
               $this->correlationId === $other->correlationId &&
               $this->getHash() === $other->getHash();
    }

    /**
     * Obtém tamanho do payload em bytes
     */
    public function getPayloadSize(): int
    {
        return strlen(json_encode($this->toWebhookPayload()));
    }

    /**
     * Verifica se payload excede limite
     */
    public function exceedsPayloadLimit(int $limitBytes = 1048576): bool // 1MB padrão
    {
        return $this->getPayloadSize() > $limitBytes;
    }

    /**
     * Sanitiza dados sensíveis
     */
    public function sanitizeForLogging(): array
    {
        $sanitized = $this->toArray();

        // Remove campos sensíveis
        $sensitiveFields = [
            'password',
            'secret',
            'token',
            'api_key',
            'credit_card',
            'cpf',
            'cnpj',
            'email',
        ];

        $sanitized['data'] = $this->sanitizeArray($sanitized['data'], $sensitiveFields);
        $sanitized['metadata'] = $this->sanitizeArray($sanitized['metadata'], $sensitiveFields);
        $sanitized['context'] = $this->sanitizeArray($sanitized['context'], $sensitiveFields);

        return $sanitized;
    }

    /**
     * Obtém resumo do evento
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'category' => $this->getCategory(),
            'action' => $this->getAction(),
            'priority' => $this->getPriority(),
            'size' => $this->getPayloadSize(),
            'attempt' => $this->attempt,
            'test_mode' => $this->testMode,
            'timestamp' => $this->timestamp,
            'organization_id' => $this->organizationId,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * Cria evento de falha
     */
    public static function createFailureEvent(
        string $originalEventId,
        string $error,
        array $context = []
    ): self {
        return self::fromArray([
            'type' => 'webhook.delivery.failed',
            'data' => [
                'original_event_id' => $originalEventId,
                'error' => $error,
                'failed_at' => date('c'),
            ],
            'context' => $context,
            'tags' => ['failure', 'webhook'],
        ]);
    }

    /**
     * Cria evento de sucesso
     */
    public static function createSuccessEvent(
        string $originalEventId,
        array $deliveryInfo = []
    ): self {
        return self::fromArray([
            'type' => 'webhook.delivery.success',
            'data' => [
                'original_event_id' => $originalEventId,
                'delivered_at' => date('c'),
            ],
            'delivery' => $deliveryInfo,
            'tags' => ['success', 'webhook'],
        ]);
    }

    /**
     * Sanitiza array removendo campos sensíveis
     */
    private function sanitizeArray(array $data, array $sensitiveFields): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value, $sensitiveFields);
            } elseif ($this->isSensitiveField($keyLower, $sensitiveFields)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Verifica se campo é sensível
     */
    private function isSensitiveField(string $field, array $sensitiveFields): bool
    {
        foreach ($sensitiveFields as $sensitive) {
            if (str_contains($field, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida dados do evento
     */
    protected function validate(): void
    {
        if (empty($this->id)) {
            throw new \InvalidArgumentException('ID do evento é obrigatório');
        }

        if (empty($this->type)) {
            throw new \InvalidArgumentException('Tipo do evento é obrigatório');
        }

        if (!preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/i', $this->type)) {
            throw new \InvalidArgumentException('Tipo do evento deve seguir o formato "categoria.acao"');
        }

        if (!is_array($this->data)) {
            throw new \InvalidArgumentException('Dados do evento devem ser um array');
        }

        if ($this->attempt < 1) {
            throw new \InvalidArgumentException('Tentativa deve ser maior que 0');
        }

        if ($this->attempt > 50) {
            throw new \InvalidArgumentException('Número excessivo de tentativas');
        }

        // Valida timestamp se fornecido
        if ($this->timestamp && !strtotime($this->timestamp)) {
            throw new \InvalidArgumentException('Timestamp inválido');
        }

        // Valida tamanho do payload
        if ($this->getPayloadSize() > 10 * 1024 * 1024) { // 10MB
            throw new \InvalidArgumentException('Payload do evento muito grande');
        }
    }
}
