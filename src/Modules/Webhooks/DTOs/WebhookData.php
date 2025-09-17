<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Webhooks\DTOs;

use ClubifyCheckout\Core\DTOs\BaseDTO;

/**
 * DTO para dados de webhook
 *
 * Representa a estrutura completa de um webhook incluindo
 * configuração, metadados, estatísticas e histórico.
 */
class WebhookData extends BaseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly array $events,
        public readonly bool $active = true,
        public readonly ?string $secret = null,
        public readonly ?string $description = null,
        public readonly array $headers = [],
        public readonly array $config = [],
        public readonly array $eventFilters = [],
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
        public readonly bool $retryEnabled = true,
        public readonly int $maxRetries = 5,
        public readonly int $retryDelay = 300,
        public readonly string $retryStrategy = 'exponential',
        public readonly float $retryMultiplier = 2.0,
        public readonly int $maxRetryDelay = 3600,
        public readonly bool $verifySSL = true,
        public readonly bool $requireHTTPS = true,
        public readonly string $signatureHeader = 'X-Webhook-Signature',
        public readonly string $signatureAlgorithm = 'sha256',
        public readonly array $allowedDomains = [],
        public readonly array $blockedDomains = [],
        public readonly array $allowedIPs = [],
        public readonly array $blockedIPs = [],
        public readonly bool $disableOnMaxRetries = false,
        public readonly ?string $organizationId = null,
        public readonly ?string $userId = null,
        public readonly array $tags = [],
        public readonly array $metadata = [],
        public readonly ?array $lastDelivery = null,
        public readonly int $failureCount = 0,
        public readonly ?string $lastFailureReason = null,
        public readonly ?string $lastFailureAt = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?WebhookStatsData $stats = null
    ) {
        $this->validate();
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        // Processa dados de estatísticas se presentes
        $stats = null;
        if (isset($data['stats']) && is_array($data['stats'])) {
            $stats = WebhookStatsData::fromArray($data['stats']);
        }

        return new self(
            id: $data['id'] ?? '',
            url: $data['url'] ?? '',
            events: $data['events'] ?? [],
            active: $data['active'] ?? true,
            secret: $data['secret'] ?? null,
            description: $data['description'] ?? null,
            headers: $data['headers'] ?? [],
            config: $data['config'] ?? [],
            eventFilters: $data['event_filters'] ?? [],
            timeout: $data['timeout'] ?? 30,
            connectTimeout: $data['connect_timeout'] ?? 10,
            retryEnabled: $data['retry_enabled'] ?? true,
            maxRetries: $data['max_retries'] ?? 5,
            retryDelay: $data['retry_delay'] ?? 300,
            retryStrategy: $data['retry_strategy'] ?? 'exponential',
            retryMultiplier: $data['retry_multiplier'] ?? 2.0,
            maxRetryDelay: $data['max_retry_delay'] ?? 3600,
            verifySSL: $data['verify_ssl'] ?? true,
            requireHTTPS: $data['require_https'] ?? true,
            signatureHeader: $data['signature_header'] ?? 'X-Webhook-Signature',
            signatureAlgorithm: $data['signature_algorithm'] ?? 'sha256',
            allowedDomains: $data['allowed_domains'] ?? [],
            blockedDomains: $data['blocked_domains'] ?? [],
            allowedIPs: $data['allowed_ips'] ?? [],
            blockedIPs: $data['blocked_ips'] ?? [],
            disableOnMaxRetries: $data['disable_on_max_retries'] ?? false,
            organizationId: $data['organization_id'] ?? null,
            userId: $data['user_id'] ?? null,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
            lastDelivery: $data['last_delivery'] ?? null,
            failureCount: $data['failure_count'] ?? 0,
            lastFailureReason: $data['last_failure_reason'] ?? null,
            lastFailureAt: $data['last_failure_at'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            stats: $stats
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'url' => $this->url,
            'events' => $this->events,
            'active' => $this->active,
            'secret' => $this->secret,
            'description' => $this->description,
            'headers' => $this->headers,
            'config' => $this->config,
            'event_filters' => $this->eventFilters,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry_enabled' => $this->retryEnabled,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'retry_strategy' => $this->retryStrategy,
            'retry_multiplier' => $this->retryMultiplier,
            'max_retry_delay' => $this->maxRetryDelay,
            'verify_ssl' => $this->verifySSL,
            'require_https' => $this->requireHTTPS,
            'signature_header' => $this->signatureHeader,
            'signature_algorithm' => $this->signatureAlgorithm,
            'allowed_domains' => $this->allowedDomains,
            'blocked_domains' => $this->blockedDomains,
            'allowed_ips' => $this->allowedIPs,
            'blocked_ips' => $this->blockedIPs,
            'disable_on_max_retries' => $this->disableOnMaxRetries,
            'organization_id' => $this->organizationId,
            'user_id' => $this->userId,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
            'last_delivery' => $this->lastDelivery,
            'failure_count' => $this->failureCount,
            'last_failure_reason' => $this->lastFailureReason,
            'last_failure_at' => $this->lastFailureAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];

        if ($this->stats !== null) {
            $data['stats'] = $this->stats->toArray();
        }

        return $data;
    }

    /**
     * Cria cópia com dados atualizados
     */
    public function withUpdates(array $updates): self
    {
        $currentData = $this->toArray();
        $mergedData = array_merge($currentData, $updates);

        return self::fromArray($mergedData);
    }

    /**
     * Verifica se webhook está configurado corretamente
     */
    public function isValid(): bool
    {
        return !empty($this->url) &&
               !empty($this->events) &&
               filter_var($this->url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Verifica se webhook está ativo e funcionando
     */
    public function isHealthy(): bool
    {
        return $this->active &&
               $this->isValid() &&
               $this->failureCount < 5; // Menos de 5 falhas consecutivas
    }

    /**
     * Obtém configuração merged
     */
    public function getMergedConfig(): array
    {
        $defaultConfig = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'retry_enabled' => $this->retryEnabled,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'retry_strategy' => $this->retryStrategy,
            'retry_multiplier' => $this->retryMultiplier,
            'max_retry_delay' => $this->maxRetryDelay,
            'verify_ssl' => $this->verifySSL,
            'require_https' => $this->requireHTTPS,
            'signature_header' => $this->signatureHeader,
            'signature_algorithm' => $this->signatureAlgorithm,
        ];

        return array_merge($defaultConfig, $this->config);
    }

    /**
     * Verifica se webhook aceita evento específico
     */
    public function acceptsEvent(string $eventType): bool
    {
        // Se tem '*', aceita todos os eventos
        if (in_array('*', $this->events)) {
            return true;
        }

        // Verifica se evento está na lista
        return in_array($eventType, $this->events);
    }

    /**
     * Obtém próximo delay de retry
     */
    public function getNextRetryDelay(int $attempt): int
    {
        $strategies = [
            'immediate' => [0],
            'linear' => [60, 300, 900, 1800, 3600],
            'exponential' => [60, 120, 240, 480, 960],
            'fibonacci' => [60, 60, 120, 180, 300, 480],
        ];

        $delays = $strategies[$this->retryStrategy] ?? $strategies['exponential'];
        $baseDelay = $this->retryDelay / 60; // Converte para minutos

        if ($attempt <= count($delays)) {
            $delay = $delays[$attempt - 1] * $baseDelay;
        } else {
            $delay = end($delays) * $baseDelay;
        }

        // Aplica multiplicador
        if ($this->retryStrategy === 'exponential' && $attempt > 1) {
            $delay *= pow($this->retryMultiplier, $attempt - 1);
        }

        // Adiciona jitter (até 10% do delay)
        $jitter = rand(0, (int)($delay * 0.1));
        $delay += $jitter;

        return min((int)$delay, $this->maxRetryDelay);
    }

    /**
     * Verifica se deve continuar retry
     */
    public function shouldRetry(int $currentAttempt): bool
    {
        return $this->retryEnabled &&
               $currentAttempt < $this->maxRetries &&
               $this->active;
    }

    /**
     * Obtém resumo de status
     */
    public function getStatusSummary(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'active' => $this->active,
            'healthy' => $this->isHealthy(),
            'failure_count' => $this->failureCount,
            'last_failure_at' => $this->lastFailureAt,
            'events_count' => count($this->events),
            'retry_enabled' => $this->retryEnabled,
        ];
    }

    /**
     * Valida dados do webhook
     */
    protected function validate(): void
    {
        if (empty($this->url)) {
            throw new \InvalidArgumentException('URL do webhook é obrigatória');
        }

        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('URL do webhook é inválida');
        }

        if (empty($this->events)) {
            throw new \InvalidArgumentException('Pelo menos um evento deve ser configurado');
        }

        if ($this->timeout < 1 || $this->timeout > 300) {
            throw new \InvalidArgumentException('Timeout deve estar entre 1 e 300 segundos');
        }

        if ($this->maxRetries < 0 || $this->maxRetries > 20) {
            throw new \InvalidArgumentException('Max retries deve estar entre 0 e 20');
        }

        if ($this->retryDelay < 1 || $this->retryDelay > 86400) {
            throw new \InvalidArgumentException('Retry delay deve estar entre 1 segundo e 1 dia');
        }

        if (!in_array($this->retryStrategy, ['immediate', 'linear', 'exponential', 'fibonacci'])) {
            throw new \InvalidArgumentException('Estratégia de retry inválida');
        }

        if ($this->retryMultiplier < 1.0 || $this->retryMultiplier > 10.0) {
            throw new \InvalidArgumentException('Multiplicador de retry deve estar entre 1.0 e 10.0');
        }

        // Valida URLs em allowedDomains e blockedDomains se necessário
        foreach (array_merge($this->allowedDomains, $this->blockedDomains) as $domain) {
            if (!empty($domain) && !filter_var("http://{$domain}", FILTER_VALIDATE_URL)) {
                throw new \InvalidArgumentException("Domínio inválido: {$domain}");
            }
        }

        // Valida IPs em allowedIPs e blockedIPs
        foreach (array_merge($this->allowedIPs, $this->blockedIPs) as $ip) {
            if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
                throw new \InvalidArgumentException("IP inválido: {$ip}");
            }
        }
    }
}