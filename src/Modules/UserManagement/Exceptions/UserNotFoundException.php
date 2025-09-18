<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Exceptions;

use Exception;

/**
 * Exceção para quando um usuário não é encontrado
 *
 * Exceção específica do domínio de User Management
 * para casos onde um usuário solicitado não existe.
 */
class UserNotFoundException extends Exception
{
    protected $message = 'User not found';

    public function __construct(string $message = null, int $code = 404, Exception $previous = null)
    {
        if ($message !== null) {
            $this->message = $message;
        }

        parent::__construct($this->message, $code, $previous);
    }
}
