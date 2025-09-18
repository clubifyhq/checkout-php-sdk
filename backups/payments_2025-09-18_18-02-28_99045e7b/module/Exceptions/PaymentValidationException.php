<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Exceptions;

use Exception;

/**
 * Payment Validation Exception
 *
 * Thrown when payment data validation fails.
 *
 * @package Clubify\Checkout\Modules\Payments\Exceptions
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class PaymentValidationException extends Exception
{
    private array $errors = [];

    /**
     * Constructor
     */
    public function __construct(
        string $message = 'Payment validation failed',
        array $errors = [],
        int $code = 400,
        ?Exception $previous = null
    ) {
        $this->errors = $errors;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create exception for required field
     */
    public static function forRequiredField(string $field): self
    {
        return new self(
            "Required field '{$field}' is missing",
            [$field => "Required field '{$field}' is missing"]
        );
    }

    /**
     * Create exception for invalid field
     */
    public static function forInvalidField(string $field, $value, string $reason = 'Invalid value'): self
    {
        return new self(
            "Invalid value for field '{$field}': {$reason}",
            [$field => $reason]
        );
    }

    /**
     * Create exception for invalid amount
     */
    public static function forInvalidAmount($amount): self
    {
        return new self(
            "Invalid payment amount: {$amount}",
            ['amount' => 'Payment amount must be a positive number']
        );
    }

    /**
     * Create exception for invalid currency
     */
    public static function forInvalidCurrency(string $currency): self
    {
        return new self(
            "Invalid currency: {$currency}",
            ['currency' => 'Currency must be a valid ISO 4217 code']
        );
    }

    /**
     * Create exception for invalid payment method
     */
    public static function forInvalidPaymentMethod(string $method): self
    {
        return new self(
            "Invalid payment method: {$method}",
            ['payment_method' => 'Payment method is not supported']
        );
    }

    /**
     * Create exception for multiple validation errors
     */
    public static function forMultipleErrors(array $errors): self
    {
        $message = 'Payment validation failed with ' . count($errors) . ' errors';
        return new self($message, $errors);
    }
}