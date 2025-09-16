<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

class ValidationException extends SDKException
{
    protected array $validationErrors = [];

    public function __construct(
        string $message = 'Validation failed',
        array $validationErrors = [],
        int $code = 422,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        $this->validationErrors = $validationErrors;
        
        parent::__construct(
            $message,
            $code,
            $previous,
            array_merge($context, ['validation_errors' => $validationErrors]),
            'VALIDATION_ERROR'
        );
    }

    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function hasError(string $field): bool
    {
        return isset($this->validationErrors[$field]);
    }

    public function getError(string $field): ?array
    {
        return $this->validationErrors[$field] ?? null;
    }

    public function getFirstError(string $field): ?string
    {
        $errors = $this->getError($field);
        return is_array($errors) && !empty($errors) ? $errors[0] : null;
    }
}