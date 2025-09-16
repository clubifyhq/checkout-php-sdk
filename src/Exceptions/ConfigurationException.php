<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

class ConfigurationException extends SDKException
{
    public function __construct(
        string $message = 'Configuration error',
        int $code = 500,
        ?\Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
            $context,
            'CONFIG_ERROR'
        );
    }
}