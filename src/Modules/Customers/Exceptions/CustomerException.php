<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers\Exceptions;

use ClubifyCheckout\Core\BaseException;

/**
 * Exceção base para operações de clientes
 */
class CustomerException extends BaseException
{
    protected string $type = 'customer_error';
}