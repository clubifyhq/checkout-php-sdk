<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

enum Environment: string
{
    case DEVELOPMENT = 'development';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }

    public function isDevelopment(): bool
    {
        return $this === self::DEVELOPMENT;
    }

    public function isStaging(): bool
    {
        return $this === self::STAGING;
    }

    public function getBaseUrl(): string
    {
        return match ($this) {
            self::DEVELOPMENT => 'http://localhost:8000',
            self::STAGING => 'https://staging-api.clubify.com',
            self::PRODUCTION => 'https://api.clubify.com',
        };
    }
}