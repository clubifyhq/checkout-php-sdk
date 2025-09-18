<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Exceptions;

use Exception;

/**
 * Exceção para erros de validação de usuário
 *
 * Exceção específica do domínio de User Management
 * para casos onde dados de usuário não passam na validação.
 */
class UserValidationException extends Exception
{
    protected $message = 'User validation failed';

    public function __construct(string $message = null, int $code = 400, Exception $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $code, $previous);
    }
}
