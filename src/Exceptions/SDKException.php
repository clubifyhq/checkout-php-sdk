<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

use Exception;
use Throwable;

class SDKException extends Exception
{
    protected array $context = [];
    protected ?string $errorCode = null;

    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        ?string $errorCode = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
        $this->errorCode = $errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'error_code' => $this->getErrorCode(),
            'context' => $this->getContext(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }
}