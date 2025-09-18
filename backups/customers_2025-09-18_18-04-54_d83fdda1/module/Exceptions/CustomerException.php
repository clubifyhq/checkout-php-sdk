<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\Exceptions;

use Clubify\Checkout\Core\BaseException;

/**
 * Exceção base para operações de clientes
 */
class CustomerException extends BaseException
{
    protected string $type = 'customer_error';
}
