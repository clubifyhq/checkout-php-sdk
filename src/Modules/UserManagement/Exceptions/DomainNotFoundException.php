<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Exceptions;

use Exception;

/**
 * Exception para domínio não encontrado
 *
 * Lançada quando um domínio solicitado não existe no sistema.
 */
class DomainNotFoundException extends Exception
{
    public function __construct(string $message = 'Domain not found', int $code = 404, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}