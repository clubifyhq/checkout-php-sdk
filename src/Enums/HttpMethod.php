<?php

declare(strict_types=1);

namespace Clubify\Checkout\Enums;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';
    case OPTIONS = 'OPTIONS';

    public function isIdempotent(): bool
    {
        return match ($this) {
            self::GET, self::HEAD, self::OPTIONS, self::PUT, self::DELETE => true,
            self::POST, self::PATCH => false,
        };
    }

    public function allowsBody(): bool
    {
        return match ($this) {
            self::POST, self::PUT, self::PATCH => true,
            self::GET, self::DELETE, self::HEAD, self::OPTIONS => false,
        };
    }
}
