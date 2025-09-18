<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Exceptions;

use Exception;

/**
 * Payment Not Found Exception
 *
 * Thrown when a payment cannot be found.
 *
 * @package Clubify\Checkout\Modules\Payments\Exceptions
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class PaymentNotFoundException extends Exception
{
    /**
     * Constructor
     */
    public function __construct(
        string $message = 'Payment not found',
        int $code = 404,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for payment ID
     */
    public static function forId(string $paymentId): self
    {
        return new self("Payment with ID '{$paymentId}' not found");
    }

    /**
     * Create exception for payment token
     */
    public static function forToken(string $token): self
    {
        return new self("Payment with token '{$token}' not found");
    }

    /**
     * Create exception for customer
     */
    public static function forCustomer(string $customerId): self
    {
        return new self("No payments found for customer '{$customerId}'");
    }

    /**
     * Create exception for order
     */
    public static function forOrder(string $orderId): self
    {
        return new self("No payment found for order '{$orderId}'");
    }
}