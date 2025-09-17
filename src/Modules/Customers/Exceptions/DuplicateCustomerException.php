<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers\Exceptions;

/**
 * Exceção para cliente duplicado
 */
class DuplicateCustomerException extends CustomerException
{
    protected string $type = 'duplicate_customer';
}