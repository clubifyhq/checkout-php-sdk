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


    public function isSandbox(): bool
    {
        return $this === self::SANDBOX;
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::DEVELOPMENT => 'https://checkout.svelve.com/api/v1',
            self::SANDBOX => 'https://checkout.svelve.com/api/v1',
            self::STAGING => 'https://checkout.svelve.com/api/v1',
            self::PRODUCTION => 'https://checkout.svelve.com/api/v1',
        };
    }

}