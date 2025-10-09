<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core;

use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Base Service Class
 *
 * Classe base simplificada para services que precisam de logger e cache.
 */
abstract class BaseService
{
    protected LoggerInterface $logger;
    protected CacheItemPoolInterface $cache;

    public function __construct(
        LoggerInterface $logger,
        CacheItemPoolInterface $cache
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
            $item = $this->cache->getItem($key);

            if ($item->isHit()) {
                return $item->get();
            }

            return null;
        } catch (InvalidArgumentException $e) {
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
            $item = $this->cache->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);

            return $this->cache->save($item);
        } catch (InvalidArgumentException $e) {
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
            return $this->cache->deleteItem($key);
        } catch (InvalidArgumentException $e) {
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
            // Nota: PSR-6 não suporta pattern matching nativamente
            // Implementações específicas de cache podem precisar de métodos customizados
            $this->logger->debug('Clearing cache pattern', ['pattern' => $pattern]);

            // Para FilesystemAdapter e similares, pode funcionar:
            if (method_exists($this->cache, 'deleteItems')) {
                // Tenta limpar itens relacionados
                // Isso pode variar dependendo da implementação do cache
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
        return $this->cache->clear();
    }
}
