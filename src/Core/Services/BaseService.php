<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Services;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;

/**
 * Base Service Class for Notification Services
 *
 * Provides core functionality for services that only need:
 * - Configuration management
 * - Logging capabilities
 * - HTTP client access (via SDK/injected)
 * - Cache operations
 *
 * This is a lightweight base service specifically for notification-related
 * services that get their HTTP client from the SDK instance.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Fornece apenas recursos básicos
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Substituível por subclasses
 * - D: Dependency Inversion - Depende de abstrações
 */
abstract class BaseService
{
    protected Configuration $config;
    protected Logger $logger;
    protected ?Client $httpClient = null;
    protected ?CacheManagerInterface $cache = null;
    protected bool $initialized = false;

    /**
     * Base constructor for notification services
     *
     * @param Configuration $config Configuration instance
     * @param Logger $logger Logger instance
     */
    public function __construct(
        Configuration $config,
        Logger $logger
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->debug('Service initialized', [
            'service' => static::class,
            'config_tenant' => $config->getTenantId()
        ]);
    }

    /**
     * Set HTTP client (injected by SDK or factory)
     *
     * @param Client $httpClient HTTP client instance
     * @return void
     */
    public function setHttpClient(Client $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Set cache manager (injected by SDK or factory)
     *
     * @param CacheManagerInterface $cache Cache manager instance
     * @return void
     */
    public function setCacheManager(CacheManagerInterface $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get configuration instance
     *
     * @return Configuration
     */
    protected function getConfiguration(): Configuration
    {
        return $this->config;
    }

    /**
     * Get logger instance
     *
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Get HTTP client instance
     *
     * @return Client
     * @throws \RuntimeException If HTTP client not set
     */
    protected function getHttpClient(): Client
    {
        if ($this->httpClient === null) {
            throw new \RuntimeException('HTTP client not set. Ensure service is properly initialized.');
        }
        return $this->httpClient;
    }

    /**
     * Get cache manager instance
     *
     * @return CacheManagerInterface
     * @throws \RuntimeException If cache not set
     */
    protected function getCacheManager(): CacheManagerInterface
    {
        if ($this->cache === null) {
            throw new \RuntimeException('Cache manager not set. Ensure service is properly initialized.');
        }
        return $this->cache;
    }

    /**
     * Validate service is initialized
     *
     * @return void
     * @throws \RuntimeException If service not initialized
     */
    protected function validateInitialization(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException(static::class . ' is not initialized');
        }
    }

    /**
     * Get value from cache
     *
     * @param string $key Cache key
     * @return mixed|null Cached value or null
     */
    protected function getFromCache(string $key): mixed
    {
        if ($this->cache === null) {
            return null;
        }

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
     * Save value to cache
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int $ttl Time to live in seconds
     * @return bool Success status
     */
    protected function setCache(string $key, mixed $value, int $ttl = 300): bool
    {
        if ($this->cache === null) {
            return false;
        }

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
     * Delete value from cache
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    protected function deleteFromCache(string $key): bool
    {
        if ($this->cache === null) {
            return false;
        }

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
     * Clear cache by pattern
     *
     * @param string $pattern Cache key pattern
     * @return void
     */
    protected function clearCachePattern(string $pattern): void
    {
        if ($this->cache === null) {
            return;
        }

        try {
            $this->logger->debug('Clearing cache pattern', ['pattern' => $pattern]);

            // If cache manager supports deleteByPattern, use it
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
     * Dispatch an event
     *
     * @param string $eventName Event name
     * @param array $data Event data
     * @return void
     */
    protected function dispatchEvent(string $eventName, array $data = []): void
    {
        // For now, just log the event since this BaseService doesn't have event dispatcher
        $this->logger->debug("Event dispatched: {$eventName}", array_merge($data, [
            'service' => static::class,
            'timestamp' => time()
        ]));
    }

    /**
     * Clear all cache
     *
     * @return bool Success status
     */
    protected function clearCache(): bool
    {
        if ($this->cache === null) {
            return false;
        }

        try {
            return $this->cache->clear();
        } catch (\Throwable $e) {
            $this->logger->error('Cache clear error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Log debug message
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($message, array_merge($context, [
            'service' => static::class
        ]));
    }

    /**
     * Log info message
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, [
            'service' => static::class
        ]));
    }

    /**
     * Log warning message
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge($context, [
            'service' => static::class
        ]));
    }

    /**
     * Log error message
     *
     * @param string $message Log message
     * @param array $context Log context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, array_merge($context, [
            'service' => static::class
        ]));
    }
}
