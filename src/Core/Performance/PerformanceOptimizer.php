<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Performance;

use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Cache\CacheStrategies;
use Psr\Log\LoggerInterface;

/**
 * Performance Optimizer for Clubify SDK
 *
 * Provides performance monitoring, optimization utilities, and metrics tracking
 * to ensure response times <200ms with cache enabled.
 *
 * Features:
 * - Performance monitoring and metrics collection
 * - Memory usage optimization
 * - Query optimization and batching
 * - Lazy loading management
 * - Response time tracking
 * - Cache hit rate analysis
 * - Memory leak detection
 * - Performance benchmarking
 */
class PerformanceOptimizer
{
    private array $metrics = [];
    private array $timers = [];
    private array $memorySnapshots = [];
    private array $queryLog = [];
    private array $cacheStats = [];
    private float $targetResponseTime = 0.2; // 200ms target
    private int $memoryLimit;
    private bool $profilingEnabled = false;

    public function __construct(
        private CacheManagerInterface $cache,
        private LoggerInterface $logger,
        array $config = []
    ) {
        $this->memoryLimit = $config['memory_limit_mb'] ?? 256;
        $this->targetResponseTime = $config['target_response_time'] ?? 0.2;
        $this->profilingEnabled = $config['profiling_enabled'] ?? false;

        $this->initializeMetrics();
    }

    // ===============================
    // PERFORMANCE MONITORING
    // ===============================

    /**
     * Start performance timer
     */
    public function startTimer(string $operation): void
    {
        $this->timers[$operation] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'peak_memory_start' => memory_get_peak_usage(true)
        ];
    }

    /**
     * Stop performance timer and record metrics
     */
    public function stopTimer(string $operation): float
    {
        if (!isset($this->timers[$operation])) {
            return 0.0;
        }

        $timer = $this->timers[$operation];
        $duration = microtime(true) - $timer['start'];
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];
        $peakMemory = memory_get_peak_usage(true);

        // Record metrics
        $this->recordOperationMetrics($operation, [
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'peak_memory' => $peakMemory,
            'exceeded_target' => $duration > $this->targetResponseTime
        ]);

        // Log slow operations
        if ($duration > $this->targetResponseTime) {
            $this->logger->warning('Slow operation detected', [
                'operation' => $operation,
                'duration' => $duration,
                'target' => $this->targetResponseTime,
                'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2)
            ]);
        }

        unset($this->timers[$operation]);
        return $duration;
    }

    /**
     * Execute operation with automatic performance monitoring
     */
    public function monitor(string $operation, callable $callback): mixed
    {
        $this->startTimer($operation);

        try {
            $result = $callback();
            $this->stopTimer($operation);
            return $result;
        } catch (\Exception $e) {
            $this->stopTimer($operation);
            $this->recordError($operation, $e);
            throw $e;
        }
    }

    // ===============================
    // MEMORY OPTIMIZATION
    // ===============================

    /**
     * Take memory snapshot
     */
    public function takeMemorySnapshot(string $label): void
    {
        $this->memorySnapshots[$label] = [
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => $this->memoryLimit * 1024 * 1024
        ];
    }

    /**
     * Analyze memory usage between snapshots
     */
    public function analyzeMemoryUsage(string $startLabel, string $endLabel): array
    {
        if (!isset($this->memorySnapshots[$startLabel], $this->memorySnapshots[$endLabel])) {
            return [];
        }

        $start = $this->memorySnapshots[$startLabel];
        $end = $this->memorySnapshots[$endLabel];

        $analysis = [
            'memory_increase' => $end['memory_usage'] - $start['memory_usage'],
            'duration' => $end['timestamp'] - $start['timestamp'],
            'memory_rate' => 0,
            'peak_increase' => $end['peak_memory'] - $start['peak_memory'],
            'memory_usage_percentage' => ($end['memory_usage'] / $end['memory_limit']) * 100,
            'potential_leak' => false
        ];

        if ($analysis['duration'] > 0) {
            $analysis['memory_rate'] = $analysis['memory_increase'] / $analysis['duration'];
        }

        // Detect potential memory leaks
        if ($analysis['memory_increase'] > 5 * 1024 * 1024) { // 5MB increase
            $analysis['potential_leak'] = true;
            $this->logger->warning('Potential memory leak detected', $analysis);
        }

        return $analysis;
    }

    /**
     * Force garbage collection and memory cleanup
     */
    public function cleanupMemory(): array
    {
        $memoryBefore = memory_get_usage(true);

        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
        } else {
            $collected = 0;
        }

        $memoryAfter = memory_get_usage(true);
        $freed = $memoryBefore - $memoryAfter;

        $result = [
            'memory_before_mb' => round($memoryBefore / 1024 / 1024, 2),
            'memory_after_mb' => round($memoryAfter / 1024 / 1024, 2),
            'memory_freed_mb' => round($freed / 1024 / 1024, 2),
            'cycles_collected' => $collected
        ];

        $this->logger->debug('Memory cleanup performed', $result);
        return $result;
    }

    // ===============================
    // CACHE OPTIMIZATION
    // ===============================

    /**
     * Analyze cache performance
     */
    public function analyzeCachePerformance(): array
    {
        $cacheMetrics = $this->cache->getMetrics();

        $analysis = [
            'hit_rate' => $cacheMetrics['hit_rate_percentage'] ?? 0,
            'total_requests' => ($cacheMetrics['hits'] ?? 0) + ($cacheMetrics['misses'] ?? 0),
            'cache_efficiency' => 'unknown',
            'recommendations' => []
        ];

        // Analyze cache efficiency
        if ($analysis['hit_rate'] >= 80) {
            $analysis['cache_efficiency'] = 'excellent';
        } elseif ($analysis['hit_rate'] >= 60) {
            $analysis['cache_efficiency'] = 'good';
        } elseif ($analysis['hit_rate'] >= 40) {
            $analysis['cache_efficiency'] = 'fair';
            $analysis['recommendations'][] = 'Consider increasing cache TTL for stable data';
        } else {
            $analysis['cache_efficiency'] = 'poor';
            $analysis['recommendations'][] = 'Review cache strategies and increase cache usage';
            $analysis['recommendations'][] = 'Implement cache warming for frequently accessed data';
        }

        // Memory usage recommendations
        if (($cacheMetrics['memory_usage_mb'] ?? 0) > 100) {
            $analysis['recommendations'][] = 'Consider enabling cache compression';
        }

        return $analysis;
    }

    /**
     * Optimize cache strategies based on usage patterns
     */
    public function optimizeCacheStrategies(array $usagePatterns): array
    {
        $optimizations = [];

        foreach ($usagePatterns as $pattern => $data) {
            $accessFrequency = $data['access_frequency'] ?? 'medium';
            $dataVolatility = $data['volatility'] ?? 'medium';
            $averageSize = $data['average_size'] ?? 1024;

            $recommendedTtl = CacheStrategies::recommendStrategy([
                'volatility' => $dataVolatility,
                'importance' => $data['importance'] ?? 'medium',
                'access_pattern' => $accessFrequency,
                'size' => $averageSize > 10240 ? 'large' : 'medium'
            ]);

            $optimizations[$pattern] = [
                'current_ttl' => $data['current_ttl'] ?? 3600,
                'recommended_ttl' => $recommendedTtl,
                'reason' => $this->getOptimizationReason($accessFrequency, $dataVolatility),
                'should_compress' => $averageSize > 5120, // 5KB threshold
                'priority' => $this->calculateCachePriority($data)
            ];
        }

        return $optimizations;
    }

    // ===============================
    // QUERY OPTIMIZATION
    // ===============================

    /**
     * Log API query for analysis
     */
    public function logQuery(string $endpoint, array $params, float $duration): void
    {
        $this->queryLog[] = [
            'endpoint' => $endpoint,
            'params' => $params,
            'duration' => $duration,
            'timestamp' => microtime(true),
            'slow' => $duration > 0.5 // 500ms threshold
        ];

        // Keep only last 100 queries to prevent memory issues
        if (count($this->queryLog) > 100) {
            array_shift($this->queryLog);
        }
    }

    /**
     * Analyze query patterns for optimization opportunities
     */
    public function analyzeQueryPatterns(): array
    {
        if (empty($this->queryLog)) {
            return ['message' => 'No query data available'];
        }

        $analysis = [
            'total_queries' => count($this->queryLog),
            'slow_queries' => 0,
            'average_duration' => 0,
            'most_frequent_endpoints' => [],
            'slowest_queries' => [],
            'recommendations' => []
        ];

        $durations = [];
        $endpointCounts = [];

        foreach ($this->queryLog as $query) {
            $durations[] = $query['duration'];

            if ($query['slow']) {
                $analysis['slow_queries']++;
            }

            $endpoint = $query['endpoint'];
            $endpointCounts[$endpoint] = ($endpointCounts[$endpoint] ?? 0) + 1;
        }

        $analysis['average_duration'] = array_sum($durations) / count($durations);

        // Most frequent endpoints
        arsort($endpointCounts);
        $analysis['most_frequent_endpoints'] = array_slice($endpointCounts, 0, 5, true);

        // Slowest queries
        usort($this->queryLog, fn($a, $b) => $b['duration'] <=> $a['duration']);
        $analysis['slowest_queries'] = array_slice($this->queryLog, 0, 5);

        // Generate recommendations
        if ($analysis['slow_queries'] > count($this->queryLog) * 0.2) {
            $analysis['recommendations'][] = 'High number of slow queries detected - consider caching';
        }

        if ($analysis['average_duration'] > 0.3) {
            $analysis['recommendations'][] = 'Average query time is high - implement query batching';
        }

        return $analysis;
    }

    /**
     * Suggest query batching opportunities
     */
    public function suggestQueryBatching(): array
    {
        $suggestions = [];
        $endpointGroups = [];

        // Group queries by endpoint and time proximity
        foreach ($this->queryLog as $query) {
            $timeSlot = floor($query['timestamp'] / 5); // 5-second windows
            $key = $query['endpoint'] . '_' . $timeSlot;

            if (!isset($endpointGroups[$key])) {
                $endpointGroups[$key] = [];
            }
            $endpointGroups[$key][] = $query;
        }

        // Find batching opportunities
        foreach ($endpointGroups as $key => $queries) {
            if (count($queries) >= 3) { // 3+ queries in 5 seconds
                $endpoint = explode('_', $key)[0];
                $suggestions[] = [
                    'endpoint' => $endpoint,
                    'query_count' => count($queries),
                    'total_duration' => array_sum(array_column($queries, 'duration')),
                    'potential_savings' => count($queries) * 0.1, // Estimated overhead per query
                    'recommendation' => 'Implement batch endpoint for ' . $endpoint
                ];
            }
        }

        return $suggestions;
    }

    // ===============================
    // LAZY LOADING OPTIMIZATION
    // ===============================

    /**
     * Manage lazy loading with performance tracking
     */
    public function lazyLoad(string $identifier, callable $loader, array $options = []): mixed
    {
        $cacheKey = 'lazy_load:' . $identifier;
        $ttl = $options['ttl'] ?? CacheStrategies::getStrategy('api', 'response');

        return $this->monitor("lazy_load:{$identifier}", function() use ($cacheKey, $loader, $ttl) {
            // Check cache first
            if ($this->cache->has($cacheKey)) {
                $this->incrementMetric('lazy_load_cache_hits');
                return $this->cache->get($cacheKey);
            }

            // Load data
            $this->incrementMetric('lazy_load_cache_misses');
            $data = $loader();

            // Cache result
            $this->cache->set($cacheKey, $data, $ttl);

            return $data;
        });
    }

    /**
     * Preload data based on prediction patterns
     */
    public function preload(array $predictions): array
    {
        $results = [];

        foreach ($predictions as $identifier => $config) {
            if ($config['probability'] >= 0.7) { // High probability threshold
                try {
                    $this->lazyLoad($identifier, $config['loader'], $config['options'] ?? []);
                    $results[$identifier] = 'preloaded';
                } catch (\Exception $e) {
                    $results[$identifier] = 'failed: ' . $e->getMessage();
                }
            }
        }

        return $results;
    }

    // ===============================
    // METRICS AND REPORTING
    // ===============================

    /**
     * Get comprehensive performance report
     */
    public function getPerformanceReport(): array
    {
        return [
            'summary' => [
                'target_response_time' => $this->targetResponseTime,
                'operations_monitored' => count($this->metrics),
                'memory_snapshots' => count($this->memorySnapshots),
                'queries_logged' => count($this->queryLog),
            ],
            'performance_metrics' => $this->getPerformanceMetrics(),
            'memory_analysis' => $this->getMemoryAnalysis(),
            'cache_analysis' => $this->analyzeCachePerformance(),
            'query_analysis' => $this->analyzeQueryPatterns(),
            'recommendations' => $this->generateRecommendations(),
            'timestamp' => time()
        ];
    }

    /**
     * Get current performance metrics
     */
    public function getPerformanceMetrics(): array
    {
        $totalOperations = array_sum(array_column($this->metrics, 'count'));
        $slowOperations = 0;
        $totalDuration = 0;

        foreach ($this->metrics as $operation => $data) {
            $slowOperations += $data['slow_count'] ?? 0;
            $totalDuration += $data['total_duration'] ?? 0;
        }

        return [
            'total_operations' => $totalOperations,
            'slow_operations' => $slowOperations,
            'slow_operation_percentage' => $totalOperations > 0 ? ($slowOperations / $totalOperations) * 100 : 0,
            'average_response_time' => $totalOperations > 0 ? $totalDuration / $totalOperations : 0,
            'target_response_time' => $this->targetResponseTime,
            'performance_score' => $this->calculatePerformanceScore(),
            'operations_detail' => $this->metrics
        ];
    }

    // ===============================
    // PRIVATE UTILITY METHODS
    // ===============================

    private function initializeMetrics(): void
    {
        $this->metrics = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'lazy_load_cache_hits' => 0,
            'lazy_load_cache_misses' => 0,
            'memory_cleanups' => 0,
            'errors' => 0
        ];
    }

    private function recordOperationMetrics(string $operation, array $data): void
    {
        if (!isset($this->metrics[$operation])) {
            $this->metrics[$operation] = [
                'count' => 0,
                'total_duration' => 0,
                'slow_count' => 0,
                'average_duration' => 0,
                'peak_memory' => 0
            ];
        }

        $metrics = &$this->metrics[$operation];
        $metrics['count']++;
        $metrics['total_duration'] += $data['duration'];
        $metrics['average_duration'] = $metrics['total_duration'] / $metrics['count'];
        $metrics['peak_memory'] = max($metrics['peak_memory'], $data['peak_memory']);

        if ($data['exceeded_target']) {
            $metrics['slow_count']++;
        }
    }

    private function recordError(string $operation, \Exception $e): void
    {
        $this->incrementMetric('errors');
        $this->logger->error('Operation error', [
            'operation' => $operation,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }

    private function incrementMetric(string $name): void
    {
        $this->metrics[$name] = ($this->metrics[$name] ?? 0) + 1;
    }

    private function getOptimizationReason(string $frequency, string $volatility): string
    {
        if ($frequency === 'frequent' && $volatility === 'low') {
            return 'High access frequency with low volatility - increase TTL';
        } elseif ($frequency === 'rare' && $volatility === 'high') {
            return 'Low access frequency with high volatility - decrease TTL';
        } elseif ($volatility === 'high') {
            return 'High volatility data - shorter cache duration recommended';
        } else {
            return 'Balanced cache strategy appropriate';
        }
    }

    private function calculateCachePriority(array $data): string
    {
        $score = 0;

        // Frequency score
        $frequency = $data['access_frequency'] ?? 'medium';
        $score += match($frequency) {
            'frequent' => 3,
            'occasional' => 2,
            'rare' => 1,
            default => 2
        };

        // Size score (smaller = higher priority for memory efficiency)
        $size = $data['average_size'] ?? 1024;
        $score += $size < 1024 ? 2 : ($size < 10240 ? 1 : 0);

        // Importance score
        $importance = $data['importance'] ?? 'medium';
        $score += match($importance) {
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 2
        };

        return match(true) {
            $score >= 7 => 'high',
            $score >= 5 => 'medium',
            default => 'low'
        };
    }

    private function calculatePerformanceScore(): float
    {
        $totalOperations = array_sum(array_column($this->metrics, 'count'));
        if ($totalOperations === 0) {
            return 100.0;
        }

        $slowOperations = 0;
        foreach ($this->metrics as $data) {
            $slowOperations += $data['slow_count'] ?? 0;
        }

        $slowPercentage = ($slowOperations / $totalOperations) * 100;
        return max(0, 100 - $slowPercentage);
    }

    private function getMemoryAnalysis(): array
    {
        if (empty($this->memorySnapshots)) {
            return ['message' => 'No memory snapshots available'];
        }

        $snapshots = array_values($this->memorySnapshots);
        $first = $snapshots[0];
        $last = end($snapshots);

        return [
            'total_snapshots' => count($this->memorySnapshots),
            'memory_trend' => $last['memory_usage'] - $first['memory_usage'],
            'peak_memory_mb' => round(max(array_column($snapshots, 'peak_memory')) / 1024 / 1024, 2),
            'current_memory_mb' => round($last['memory_usage'] / 1024 / 1024, 2),
            'memory_limit_mb' => round($this->memoryLimit, 2),
            'memory_usage_percentage' => ($last['memory_usage'] / ($this->memoryLimit * 1024 * 1024)) * 100
        ];
    }

    private function generateRecommendations(): array
    {
        $recommendations = [];

        // Performance recommendations
        $performanceScore = $this->calculatePerformanceScore();
        if ($performanceScore < 80) {
            $recommendations[] = [
                'type' => 'performance',
                'priority' => 'high',
                'message' => 'Performance score is below 80% - review slow operations'
            ];
        }

        // Cache recommendations
        $cacheAnalysis = $this->analyzeCachePerformance();
        if ($cacheAnalysis['hit_rate'] < 60) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'message' => 'Cache hit rate is below 60% - review cache strategies'
            ];
        }

        // Memory recommendations
        $memoryAnalysis = $this->getMemoryAnalysis();
        if (isset($memoryAnalysis['memory_usage_percentage']) && $memoryAnalysis['memory_usage_percentage'] > 80) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'message' => 'Memory usage is above 80% - consider increasing limits or optimizing memory usage'
            ];
        }

        return $recommendations;
    }
}