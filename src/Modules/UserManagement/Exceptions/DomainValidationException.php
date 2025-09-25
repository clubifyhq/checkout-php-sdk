<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Exceptions;

use Exception;

/**
 * Exception para erros de validação de domínio
 *
 * Lançada quando dados de domínio são inválidos ou não atendem
 * aos critérios de validação necessários.
 */
class DomainValidationException extends Exception
{
    public function __construct(string $message = 'Domain validation failed', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}