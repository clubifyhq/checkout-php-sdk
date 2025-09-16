<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Cache;

interface CacheManagerInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttl = 0): bool;

    public function has(string $key): bool;

    public function delete(string $key): bool;

    public function clear(): bool;

    public function getMultiple(array $keys, mixed $default = null): array;

    public function setMultiple(array $values, int $ttl = 0): bool;

    public function deleteMultiple(array $keys): bool;
}