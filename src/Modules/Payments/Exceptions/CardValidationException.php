<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Exceptions;

use Exception;

/**
 * Card Validation Exception
 *
 * Thrown when card data validation fails.
 *
 * @package Clubify\Checkout\Modules\Payments\Exceptions
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class CardValidationException extends Exception
{
    private array $errors = [];

    /**
     * Constructor
     */
    public function __construct(
        string $message = 'Card validation failed',
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
     * Create exception for invalid card number
     */
    public static function forInvalidCardNumber(string $cardNumber): self
    {
        return new self(
            'Invalid card number',
            ['card_number' => 'Card number is invalid']
        );
    }

    /**
     * Create exception for invalid expiry date
     */
    public static function forInvalidExpiryDate(string $expiryDate): self
    {
        return new self(
            'Invalid expiry date',
            ['expiry_date' => 'Expiry date must be in MM/YY format and not expired']
        );
    }

    /**
     * Create exception for invalid CVV
     */
    public static function forInvalidCvv(string $cvv): self
    {
        return new self(
            'Invalid CVV',
            ['cvv' => 'CVV must be 3 or 4 digits']
        );
    }

    /**
     * Create exception for expired card
     */
    public static function forExpiredCard(): self
    {
        return new self(
            'Card has expired',
            ['expiry_date' => 'Card has expired']
        );
    }

    /**
     * Create exception for unsupported card brand
     */
    public static function forUnsupportedBrand(string $brand): self
    {
        return new self(
            "Unsupported card brand: {$brand}",
            ['brand' => 'Card brand is not supported']
        );
    }

    /**
     * Create exception for multiple validation errors
     */
    public static function forMultipleErrors(array $errors): self
    {
        $message = 'Card validation failed with ' . count($errors) . ' errors';
        return new self($message, $errors);
    }
}
