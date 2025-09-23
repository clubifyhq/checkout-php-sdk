<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class HttpException extends SDKException
{
    protected ?RequestInterface $request = null;
    protected ?ResponseInterface $response = null;

    public function __construct(
        string $message = 'HTTP request failed',
        int $code = 0,
        ?\Throwable $previous = null,
        ?RequestInterface $request = null,
        ?ResponseInterface $response = null,
        array $context = []
    ) {
        $this->request = $request;
        $this->response = $response;

        $contextData = array_merge($context, [
            'request_method' => $request?->getMethod(),
            'request_uri' => (string) $request?->getUri(),
            'response_status' => $response?->getStatusCode(),
        ]);

        parent::__construct(
            $message,
            $code ?: $response?->getStatusCode() ?: 0,
            $previous,
            $contextData,
            'HTTP_ERROR'
        );
    }

    public function getRequest(): ?RequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): ?int
    {
        return $this->response?->getStatusCode();
    }

    public function getResponseBody(): ?string
    {
        if (!$this->response) {
            return null;
        }

        return (string) $this->response->getBody();
    }

    public function isClientError(): bool
    {
        $statusCode = $this->getStatusCode();
        return $statusCode !== null && $statusCode >= 400 && $statusCode < 500;
    }

    public function isServerError(): bool
    {
        $statusCode = $this->getStatusCode();
        return $statusCode !== null && $statusCode >= 500;
    }

    public function isRetryable(): bool
    {
        $statusCode = $this->getStatusCode();

        return $statusCode === null || // Network error
               $statusCode >= 500 || // Server error
               $statusCode === 429 || // Rate limit
               $statusCode === 408; // Request timeout
    }

    /**
     * Check if this is a conflict error (HTTP 409)
     */
    public function isConflict(): bool
    {
        return $this->getStatusCode() === 409;
    }

    /**
     * Check if this is a not found error (HTTP 404)
     */
    public function isNotFound(): bool
    {
        return $this->getStatusCode() === 404;
    }

    /**
     * Check if this is an unauthorized error (HTTP 401)
     */
    public function isUnauthorized(): bool
    {
        return $this->getStatusCode() === 401;
    }

    /**
     * Check if this is a forbidden error (HTTP 403)
     */
    public function isForbidden(): bool
    {
        return $this->getStatusCode() === 403;
    }

    /**
     * Check if this is a validation error (HTTP 422)
     */
    public function isValidationError(): bool
    {
        return $this->getStatusCode() === 422;
    }
}
