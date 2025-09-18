<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Exceptions;

use Exception;

/**
 * Card Not Found Exception
 *
 * Thrown when a card cannot be found.
 *
 * @package Clubify\Checkout\Modules\Payments\Exceptions
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class CardNotFoundException extends Exception
{
    /**
     * Constructor
     */
    public function __construct(
        string $message = 'Card not found',
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for card ID
     */
    public static function forId(string $cardId): self
    {
        return new self("Card with ID '{$cardId}' not found");
    }

    /**
     * Create exception for card token
     */
    public static function forToken(string $token): self
    {
        return new self("Card with token '{$token}' not found");
    }

    /**
     * Create exception for customer
     */
    public static function forCustomer(string $customerId): self
    {
        return new self("No cards found for customer '{$customerId}'");
    }

    /**
     * Create exception for fingerprint
     */
    public static function forFingerprint(string $fingerprint): self
    {
        return new self("Card with fingerprint '{$fingerprint}' not found");
    }
}
