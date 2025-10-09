<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core;

use Psr\Log\LoggerInterface;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;

/**
 * Base Service Class
 *
 * Classe base simplificada para services que precisam de logger e cache.
 */
abstract class BaseService
{
    protected LoggerInterface $logger;
    protected CacheManagerInterface $cache;

    public function __construct(
        LoggerInterface $logger,
        CacheManagerInterface $cache
    ) {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Obtém valor do cache
     */
    protected function getFromCache(string $key): mixed
    {
        try {
            return $this->cache->get($key);
        } catch (\Throwable $e) {
            $this->logger->error('Cache get error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Salva valor no cache
     */
    protected function setCache(string $key, mixed $value, int $ttl = 300): bool
    {
        try {
            return $this->cache->set($key, $value, $ttl);
        } catch (\Throwable $e) {
            $this->logger->error('Cache set error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Remove item do cache
     */
    protected function deleteFromCache(string $key): bool
    {
        try {
            return $this->cache->delete($key);
        } catch (\Throwable $e) {
            $this->logger->error('Cache delete error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Limpa cache por padrão
     */
    protected function clearCachePattern(string $pattern): void
    {
        try {
            $this->logger->debug('Clearing cache pattern', ['pattern' => $pattern]);

            // Se o cache manager suporta deleteByPattern, use-o
            if (method_exists($this->cache, 'deleteByPattern')) {
                $this->cache->deleteByPattern($pattern);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear pattern error', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Limpa todo o cache
     */
    protected function clearCache(): bool
    {
        try {
            return $this->cache->clear();
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
