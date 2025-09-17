<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

/**
 * Enum para ambientes de execução do SDK
 *
 * Define os ambientes disponíveis e suas configurações correspondentes.
 * SANDBOX usa as mesmas configurações que DEVELOPMENT para compatibilidade.
 */
enum Environment: string
{
    case DEVELOPMENT = 'development';
    case SANDBOX = 'sandbox';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }

    public function isDevelopment(): bool
    {
        return $this === self::DEVELOPMENT || $this === self::SANDBOX;
    }

    public function isStaging(): bool
    {
        return $this === self::STAGING;
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::DEVELOPMENT, self::SANDBOX => 'http://localhost:8000',
            self::STAGING => 'https://staging-api.clubify.com',
            self::PRODUCTION => 'https://api.clubify.com',
        };
    }

    public function isSandbox(): bool
    {
        return $this === self::SANDBOX;
    }
}