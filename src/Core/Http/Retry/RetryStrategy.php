<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Http\Retry;

use Clubify\Checkout\Exceptions\HttpException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Estratégia de retry para requisições HTTP
 */
class RetryStrategy
{
    private int $maxAttempts;
    private int $baseDelay;
    private string $backoffType;
    private int $maxDelay;
    private array $retryableStatusCodes;

    public function __construct(
        int $maxAttempts = 3,
        int $baseDelay = 1000,
        string $backoffType = 'exponential',
        int $maxDelay = 30000,
        array $retryableStatusCodes = [408, 429, 500, 502, 503, 504]
    ) {
        $this->maxAttempts = max(0, $maxAttempts);
        $this->baseDelay = max(0, $baseDelay);
        $this->backoffType = $backoffType;
        $this->maxDelay = $maxDelay;
        $this->retryableStatusCodes = $retryableStatusCodes;
    }

    /**
     * Verificar se deve tentar novamente
     */
    public function shouldRetry(
        int $attempt,
        ?\Throwable $exception = null,
        ?ResponseInterface $response = null,
        ?RequestInterface $request = null
    ): bool {
        // Não tentar se já excedeu o máximo
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        // Se há resposta, verificar status code
        if ($response) {
            return in_array($response->getStatusCode(), $this->retryableStatusCodes);
        }

        // Se há exceção, verificar se é retryable
        if ($exception) {
            // Erros de rede geralmente são retryable
            if ($exception instanceof HttpException) {
                return $exception->isRetryable();
            }

            // Outros tipos de erro
            return $this->isRetryableException($exception);
        }

        return false;
    }

    /**
     * Calcular delay para próxima tentativa
     */
    public function calculateDelay(int $attempt): int
    {
        $delay = match ($this->backoffType) {
            'exponential' => $this->calculateExponentialDelay($attempt),
            'linear' => $this->calculateLinearDelay($attempt),
            'fixed' => $this->baseDelay,
            default => $this->baseDelay,
        };

        return min($delay, $this->maxDelay);
    }

    /**
     * Obter número máximo de tentativas
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Executar delay (pode ser sobrescrito para testes)
     */
    public function delay(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    /**
     * Calcular delay exponencial
     */
    private function calculateExponentialDelay(int $attempt): int
    {
        return $this->baseDelay * (2 ** ($attempt - 1));
    }

    /**
     * Calcular delay linear
     */
    private function calculateLinearDelay(int $attempt): int
    {
        return $this->baseDelay * $attempt;
    }

    /**
     * Verificar se exceção é retryable
     */
    private function isRetryableException(\Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        $retryableMessages = [
            'connection timeout',
            'connection refused',
            'network unreachable',
            'temporary failure',
            'curl error',
            'timeout',
        ];

        foreach ($retryableMessages as $retryableMessage) {
            if (str_contains($message, $retryableMessage)) {
                return true;
            }
        }

        return false;
    }
}