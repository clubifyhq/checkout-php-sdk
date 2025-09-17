<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Exceptions;

/**
 * Exception para falhas na entrega de webhooks
 *
 * Representa erros específicos que ocorrem durante
 * a entrega de webhooks, incluindo timeouts,
 * erros de rede e respostas inválidas.
 */
class WebhookDeliveryException extends WebhookException
{
    public function __construct(
        string $message,
        int $code = 0,
        array $context = [],
        public readonly ?string $webhookId = null,
        public readonly ?string $deliveryId = null,
        public readonly ?int $statusCode = null,
        public readonly ?float $responseTime = null,
        public readonly ?string $endpoint = null,
        public readonly bool $isRetryable = true,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, array_merge($context, [
            'webhook_id' => $this->webhookId,
            'delivery_id' => $this->deliveryId,
            'status_code' => $this->statusCode,
            'response_time' => $this->responseTime,
            'endpoint' => $this->endpoint,
            'retryable' => $this->isRetryable,
        ]), $previous);
    }

    /**
     * Cria exception de timeout
     */
    public static function timeout(
        string $webhookId,
        string $endpoint,
        float $timeout,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Timeout na entrega do webhook após {$timeout}s",
            408,
            [
                'timeout_seconds' => $timeout,
                'error_type' => 'timeout',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: true
        );
    }

    /**
     * Cria exception de erro de conexão
     */
    public static function connectionError(
        string $webhookId,
        string $endpoint,
        string $error,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Erro de conexão na entrega do webhook: {$error}",
            502,
            [
                'connection_error' => $error,
                'error_type' => 'connection_error',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: true
        );
    }

    /**
     * Cria exception de SSL
     */
    public static function sslError(
        string $webhookId,
        string $endpoint,
        string $error,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Erro SSL na entrega do webhook: {$error}",
            495,
            [
                'ssl_error' => $error,
                'error_type' => 'ssl_error',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: false // SSL errors geralmente não são retryable
        );
    }

    /**
     * Cria exception de resposta HTTP inválida
     */
    public static function invalidResponse(
        string $webhookId,
        string $endpoint,
        int $statusCode,
        float $responseTime,
        ?string $deliveryId = null
    ): self {
        $isRetryable = $statusCode >= 500 || $statusCode === 429; // Server errors e rate limiting

        return new self(
            "Resposta HTTP inválida: {$statusCode}",
            $statusCode,
            [
                'error_type' => 'invalid_response',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            statusCode: $statusCode,
            responseTime: $responseTime,
            endpoint: $endpoint,
            isRetryable: $isRetryable
        );
    }

    /**
     * Cria exception de payload muito grande
     */
    public static function payloadTooLarge(
        string $webhookId,
        int $payloadSize,
        int $maxSize,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Payload muito grande: {$payloadSize} bytes (máximo: {$maxSize})",
            413,
            [
                'payload_size' => $payloadSize,
                'max_size' => $maxSize,
                'error_type' => 'payload_too_large',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            isRetryable: false // Payload size won't change on retry
        );
    }

    /**
     * Cria exception de rate limit
     */
    public static function rateLimited(
        string $webhookId,
        string $endpoint,
        ?int $retryAfter = null,
        ?string $deliveryId = null
    ): self {
        $message = "Rate limit excedido para webhook";
        if ($retryAfter) {
            $message .= ", retry após {$retryAfter}s";
        }

        return new self(
            $message,
            429,
            [
                'retry_after' => $retryAfter,
                'error_type' => 'rate_limited',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            statusCode: 429,
            endpoint: $endpoint,
            isRetryable: true
        );
    }

    /**
     * Cria exception de circuit breaker aberto
     */
    public static function circuitBreakerOpen(
        string $webhookId,
        string $endpoint,
        int $opensUntil,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Circuit breaker aberto até " . date('Y-m-d H:i:s', $opensUntil),
            503,
            [
                'opens_until' => $opensUntil,
                'opens_until_formatted' => date('Y-m-d H:i:s', $opensUntil),
                'error_type' => 'circuit_breaker_open',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: true
        );
    }

    /**
     * Cria exception de assinatura inválida
     */
    public static function invalidSignature(
        string $webhookId,
        string $endpoint,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Falha na geração da assinatura HMAC",
            500,
            [
                'error_type' => 'signature_error',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: false // Signature errors won't change on retry
        );
    }

    /**
     * Cria exception de máximo de retries atingido
     */
    public static function maxRetriesReached(
        string $webhookId,
        string $endpoint,
        int $maxRetries,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Máximo de {$maxRetries} tentativas atingido para webhook",
            500,
            [
                'max_retries' => $maxRetries,
                'error_type' => 'max_retries_reached',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: false
        );
    }

    /**
     * Cria exception de endpoint bloqueado
     */
    public static function endpointBlocked(
        string $webhookId,
        string $endpoint,
        string $reason,
        ?string $deliveryId = null
    ): self {
        return new self(
            "Endpoint bloqueado: {$reason}",
            403,
            [
                'block_reason' => $reason,
                'error_type' => 'endpoint_blocked',
            ],
            webhookId: $webhookId,
            deliveryId: $deliveryId,
            endpoint: $endpoint,
            isRetryable: false
        );
    }

    /**
     * Verifica se erro é temporário
     */
    public function isTemporary(): bool
    {
        $temporaryCodes = [408, 429, 502, 503, 504];
        return $this->isRetryable && in_array($this->getCode(), $temporaryCodes);
    }

    /**
     * Verifica se é erro de cliente
     */
    public function isClientError(): bool
    {
        return $this->getCode() >= 400 && $this->getCode() < 500;
    }

    /**
     * Verifica se é erro de servidor
     */
    public function isServerError(): bool
    {
        return $this->getCode() >= 500;
    }

    /**
     * Obtém delay sugerido para retry
     */
    public function getSuggestedRetryDelay(): int
    {
        $context = $this->getContext();

        // Se response tem Retry-After header
        if (isset($context['retry_after'])) {
            return (int) $context['retry_after'];
        }

        // Delays baseados no tipo de erro
        return match ($this->getCode()) {
            408 => 30,   // Timeout - retry em 30s
            429 => 60,   // Rate limit - retry em 1min
            502 => 30,   // Bad gateway - retry em 30s
            503 => 60,   // Service unavailable - retry em 1min
            504 => 45,   // Gateway timeout - retry em 45s
            default => 300, // Outros erros - retry em 5min
        };
    }

    /**
     * Converte para array para logging
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'webhook_id' => $this->webhookId,
            'delivery_id' => $this->deliveryId,
            'endpoint' => $this->endpoint,
            'status_code' => $this->statusCode,
            'response_time' => $this->responseTime,
            'retryable' => $this->isRetryable,
            'temporary' => $this->isTemporary(),
            'suggested_retry_delay' => $this->getSuggestedRetryDelay(),
            'context' => $this->getContext(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}