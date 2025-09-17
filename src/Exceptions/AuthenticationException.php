<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

class AuthenticationException extends SDKException
{
    public function __construct(
        string $message = 'Authentication failed',
        int $code = 401,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            $context,
            'AUTH_ERROR'
        );
    }
}
