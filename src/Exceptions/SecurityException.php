<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

/**
 * Security Exception for Clubify SDK
 *
 * Thrown when security-related validation fails:
 * - CSRF token validation
 * - Input validation failures
 * - Rate limiting violations
 * - Encryption/decryption errors
 */
class SecurityException extends \Exception
{
    public function __construct(
        string $message = 'Security validation failed',
        int $code = 403,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}