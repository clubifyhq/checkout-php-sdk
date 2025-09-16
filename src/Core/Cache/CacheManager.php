<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Cache;

use Clubify\Checkout\Core\Config\ConfigurationInterface;

/**
 * Cache Manager simples para o Clubify SDK
 */
class CacheManager implements CacheManagerInterface
{
    private array $cache = [];
    private array $expiration = [];
    private ConfigurationInterface $config;

    public function __construct(ConfigurationInterface $config)
    {
        $this->config = $config;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key];
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        $this->cache[$key] = $value;

        if ($ttl > 0) {
            $this->expiration[$key] = time() + $ttl;
        } else {
            unset($this->expiration[$key]);
        }

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // Verificar expiração
        if (isset($this->expiration[$key]) && time() > $this->expiration[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expiration[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiration = [];
        return true;
    }

    public function getMultiple(array $keys, mixed $default = null): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple(array $values, int $ttl = 0): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }
        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
        return true;
    }
}