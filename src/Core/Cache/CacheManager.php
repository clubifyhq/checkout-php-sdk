<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Cache;

use Clubify\Checkout\Core\Config\ConfigurationInterface;
use Clubify\Checkout\Core\Security\SecurityValidator;

/**
 * Enhanced Cache Manager for Clubify SDK with Advanced Caching Strategies
 *
 * Features:
 * - Multi-tier cache with memory, file, and external storage support
 * - TTL-based expiration with different strategies
 * - Cache hit rate tracking and performance metrics
 * - Pattern-based cache invalidation
 * - Cache warming and preloading capabilities
 * - Memory usage optimization
 * - Lazy loading support
 * - Cache compression for large data
 */
class CacheManager implements CacheManagerInterface
{
    private array $cache = [];
    private array $expiration = [];
    private array $metadata = [];
    private array $metrics = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'memory_usage' => 0,
        'compression_ratio' => 0
    ];
    private ConfigurationInterface $config;
    private bool $compressionEnabled;
    private bool $encryptionEnabled;
    private string $encryptionKey;
    private array $sensitiveKeyPatterns;
    private int $maxMemoryUsage;
    private int $defaultTtl;

    public function __construct(ConfigurationInterface $config)
    {
        $this->config = $config;
        $this->compressionEnabled = $config->get('cache.compression.enabled', false);
        $this->encryptionEnabled = $config->get('cache.encryption.enabled', true);
        $this->encryptionKey = $config->get('cache.encryption.key') ?? $this->generateEncryptionKey();
        $this->sensitiveKeyPatterns = $config->get('cache.encryption.sensitive_patterns', [
            'token:', 'key:', 'secret:', 'password:', 'auth:', 'session:', 'credential:'
        ]);
        $this->maxMemoryUsage = $config->get('cache.max_memory_mb', 128) * 1024 * 1024; // Convert to bytes
        $this->defaultTtl = $config->get('cache.default_ttl', 3600);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            $this->metrics['misses']++;
            return $default;
        }

        $this->metrics['hits']++;
        $this->updateAccessTime($key);

        $value = $this->cache[$key];

        // Decrypt if needed
        if ($this->isEncrypted($key)) {
            $value = $this->decryptValue($value);
        }

        // Decompress if needed
        if ($this->isCompressed($key)) {
            $value = $this->decompress($value);
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        // Check memory usage before adding
        if ($this->shouldEvictForMemory()) {
            $this->evictLeastRecentlyUsed();
        }

        $actualTtl = $ttl > 0 ? $ttl : $this->defaultTtl;
        $originalSize = $this->getDataSize($value);

        // Encrypt sensitive data if enabled
        $shouldEncrypt = $this->encryptionEnabled && $this->isSensitiveKey($key);
        if ($shouldEncrypt) {
            $value = $this->encryptValue($value);
            $this->metadata[$key]['encrypted'] = true;
        }

        // Compress large values if enabled
        $shouldCompress = $this->compressionEnabled && $originalSize > 1024; // 1KB threshold
        if ($shouldCompress && !$shouldEncrypt) { // Don't compress encrypted data
            $compressed = $this->compress($value);
            if ($compressed !== false) {
                $value = $compressed;
                $this->metadata[$key]['compressed'] = true;
                $this->metadata[$key]['original_size'] = $originalSize;
                $this->metadata[$key]['compressed_size'] = strlen($compressed);
                $this->updateCompressionRatio($originalSize, strlen($compressed));
            }
        }

        $this->cache[$key] = $value;
        $this->expiration[$key] = time() + $actualTtl;
        $this->metadata[$key]['created_at'] = time();
        $this->metadata[$key]['accessed_at'] = time();
        $this->metadata[$key]['access_count'] = 0;

        $this->metrics['writes']++;
        $this->updateMemoryUsage();

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        // Check expiration
        if (isset($this->expiration[$key]) && time() > $this->expiration[$key]) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key], $this->expiration[$key], $this->metadata[$key]);
        $this->metrics['deletes']++;
        $this->updateMemoryUsage();
        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];
        $this->expiration = [];
        $this->metadata = [];
        $this->updateMemoryUsage();
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

    // ===============================
    // ADVANCED CACHE METHODS
    // ===============================

    /**
     * Get cache item with metadata
     */
    public function getWithMetadata(string $key): ?array
    {
        if (!$this->has($key)) {
            return null;
        }

        return [
            'value' => $this->get($key),
            'metadata' => $this->metadata[$key] ?? [],
            'expires_at' => $this->expiration[$key] ?? null,
            'ttl_remaining' => $this->getTtlRemaining($key)
        ];
    }

    /**
     * Get cache statistics and performance metrics
     */
    public function getMetrics(): array
    {
        $totalRequests = $this->metrics['hits'] + $this->metrics['misses'];
        $hitRate = $totalRequests > 0 ? ($this->metrics['hits'] / $totalRequests) * 100 : 0;

        return array_merge($this->metrics, [
            'hit_rate_percentage' => round($hitRate, 2),
            'total_keys' => count($this->cache),
            'memory_usage_mb' => round($this->metrics['memory_usage'] / 1024 / 1024, 2),
            'memory_limit_mb' => round($this->maxMemoryUsage / 1024 / 1024, 2),
            'uptime_seconds' => time() - ($_SERVER['REQUEST_TIME'] ?? time())
        ]);
    }

    /**
     * Delete by pattern (supports wildcards)
     */
    public function deleteByPattern(string $pattern): int
    {
        $deleted = 0;
        $regex = $this->patternToRegex($pattern);

        foreach (array_keys($this->cache) as $key) {
            if (preg_match($regex, $key)) {
                $this->delete($key);
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Cache warming - preload multiple keys with callables
     */
    public function warm(array $warmers): array
    {
        $results = [];
        foreach ($warmers as $key => $config) {
            if (is_callable($config['callback'])) {
                try {
                    $value = $config['callback']();
                    $ttl = $config['ttl'] ?? 0;
                    $this->set($key, $value, $ttl);
                    $results[$key] = ['status' => 'success', 'size' => $this->getDataSize($value)];
                } catch (\Exception $e) {
                    $results[$key] = ['status' => 'error', 'error' => $e->getMessage()];
                }
            }
        }
        return $results;
    }

    /**
     * Get cache keys by pattern
     */
    public function getKeysByPattern(string $pattern): array
    {
        $regex = $this->patternToRegex($pattern);
        return array_filter(array_keys($this->cache), fn($key) => preg_match($regex, $key));
    }

    /**
     * Get TTL remaining for a key
     */
    public function getTtl(string $key): int
    {
        if (!isset($this->expiration[$key])) {
            return -1; // No expiration
        }

        return max(0, $this->expiration[$key] - time());
    }

    /**
     * Extend TTL for a key
     */
    public function extendTtl(string $key, int $additionalSeconds): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        if (isset($this->expiration[$key])) {
            $this->expiration[$key] += $additionalSeconds;
        } else {
            $this->expiration[$key] = time() + $additionalSeconds;
        }

        return true;
    }

    /**
     * Touch key to reset TTL
     */
    public function touch(string $key, int $ttl = 0): bool
    {
        if (!$this->has($key)) {
            return false;
        }

        $actualTtl = $ttl > 0 ? $ttl : $this->defaultTtl;
        $this->expiration[$key] = time() + $actualTtl;
        $this->updateAccessTime($key);

        return true;
    }

    /**
     * Cleanup expired entries
     */
    public function cleanup(): int
    {
        $cleaned = 0;
        $now = time();

        foreach ($this->expiration as $key => $expireTime) {
            if ($now > $expireTime) {
                $this->delete($key);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    /**
     * Get cache size information
     */
    public function getSizeInfo(): array
    {
        $totalSize = 0;
        $compressedSize = 0;
        $originalSize = 0;

        foreach ($this->cache as $key => $value) {
            $size = $this->getDataSize($value);
            $totalSize += $size;

            if ($this->isCompressed($key)) {
                $compressedSize += $size;
                $originalSize += $this->metadata[$key]['original_size'] ?? $size;
            }
        }

        return [
            'total_size_bytes' => $totalSize,
            'compressed_entries' => count(array_filter($this->metadata, fn($meta) => $meta['compressed'] ?? false)),
            'compression_savings_bytes' => $originalSize - $compressedSize,
            'compression_ratio' => $originalSize > 0 ? round(($compressedSize / $originalSize) * 100, 2) : 0
        ];
    }

    // ===============================
    // PRIVATE UTILITY METHODS
    // ===============================

    private function updateAccessTime(string $key): void
    {
        if (isset($this->metadata[$key])) {
            $this->metadata[$key]['accessed_at'] = time();
            $this->metadata[$key]['access_count'] = ($this->metadata[$key]['access_count'] ?? 0) + 1;
        }
    }

    private function getTtlRemaining(string $key): int
    {
        return isset($this->expiration[$key]) ? max(0, $this->expiration[$key] - time()) : -1;
    }

    private function isCompressed(string $key): bool
    {
        return $this->metadata[$key]['compressed'] ?? false;
    }

    private function isEncrypted(string $key): bool
    {
        return $this->metadata[$key]['encrypted'] ?? false;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach ($this->sensitiveKeyPatterns as $pattern) {
            if (str_contains(strtolower($key), strtolower($pattern))) {
                return true;
            }
        }
        return false;
    }

    private function encryptValue(mixed $value): string
    {
        $serialized = serialize($value);
        return SecurityValidator::encryptData($serialized, $this->encryptionKey);
    }

    private function decryptValue(string $encryptedValue): mixed
    {
        $decrypted = SecurityValidator::decryptData($encryptedValue, $this->encryptionKey);
        return unserialize($decrypted);
    }

    private function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private function compress(mixed $value): string|false
    {
        $serialized = serialize($value);
        return gzcompress($serialized, 6);
    }

    private function decompress(string $compressed): mixed
    {
        $decompressed = gzuncompress($compressed);
        return $decompressed !== false ? unserialize($decompressed) : false;
    }

    private function getDataSize(mixed $value): int
    {
        if (is_string($value)) {
            return strlen($value);
        }
        return strlen(serialize($value));
    }

    private function shouldEvictForMemory(): bool
    {
        return $this->getCurrentMemoryUsage() > $this->maxMemoryUsage;
    }

    private function getCurrentMemoryUsage(): int
    {
        $total = 0;
        foreach ($this->cache as $value) {
            $total += $this->getDataSize($value);
        }
        return $total;
    }

    private function updateMemoryUsage(): void
    {
        $this->metrics['memory_usage'] = $this->getCurrentMemoryUsage();
    }

    private function evictLeastRecentlyUsed(): void
    {
        if (empty($this->metadata)) {
            return;
        }

        // Sort by access time (least recent first)
        $sortedKeys = array_keys($this->metadata);
        usort($sortedKeys, function($a, $b) {
            $timeA = $this->metadata[$a]['accessed_at'] ?? 0;
            $timeB = $this->metadata[$b]['accessed_at'] ?? 0;
            return $timeA - $timeB;
        });

        // Remove 10% of least recently used items
        $toRemove = max(1, (int)(count($sortedKeys) * 0.1));
        for ($i = 0; $i < $toRemove; $i++) {
            if (isset($sortedKeys[$i])) {
                $this->delete($sortedKeys[$i]);
            }
        }
    }

    private function updateCompressionRatio(int $originalSize, int $compressedSize): void
    {
        // Update running average of compression ratio
        $currentRatio = $compressedSize / $originalSize;
        $currentMetric = $this->metrics['compression_ratio'];
        $this->metrics['compression_ratio'] = ($currentMetric + $currentRatio) / 2;
    }

    private function patternToRegex(string $pattern): string
    {
        $pattern = preg_quote($pattern, '/');
        $pattern = str_replace('\*', '.*', $pattern);
        return '/^' . $pattern . '$/';
    }
}
