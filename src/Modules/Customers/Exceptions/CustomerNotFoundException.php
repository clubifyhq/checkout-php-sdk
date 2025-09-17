<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\Exceptions;

/**
 * Exceção para cliente não encontrado
 */
class CustomerNotFoundException extends CustomerException
{
    protected string $type = 'customer_not_found';
}