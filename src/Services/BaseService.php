<?php

declare(strict_types=1);

namespace Clubify\Checkout\Services;

use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

/**
 * Classe base para Services
 *
 * Fornece funcionalidades básicas que todos os services devem ter.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas funcionalidades básicas de service
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por subclasses
 * - D: Dependency Inversion - Depende de abstrações
 */
abstract class BaseService implements ServiceInterface
{
    protected Configuration $config;
    protected Logger $logger;
    protected Client $httpClient;
    protected CacheManagerInterface $cache;
    protected EventDispatcherInterface $eventDispatcher;
    protected array $metrics = [];
    protected bool $initialized = false;

    public function __construct(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
        $this->initialized = true;

        $this->logger->info('Service initialized', [
            'service' => static::class,
            'config' => $this->getServiceName()
        ]);
    }

    /**
     * Obtém o nome do serviço
     */
    public function getName(): string
    {
        return $this->getServiceName();
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return $this->getServiceVersion();
    }

    /**
     * Verifica se o serviço está saudável (health check)
     */
    public function isHealthy(): bool
    {
        return $this->initialized && $this->performHealthCheck();
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return $this->config->get($this->getServiceName(), []);
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized && $this->checkAvailability();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->getServiceName(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'config' => $this->getConfig(),
            'metrics' => $this->getMetrics(),
            'timestamp' => time()
        ];
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return array_merge([
            'requests_count' => 0,
            'errors_count' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'last_activity' => null
        ], $this->metrics);
    }

    /**
     * Incrementa uma métrica
     */
    protected function incrementMetric(string $name, int $value = 1): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = 0;
        }
        $this->metrics[$name] += $value;
        $this->metrics['last_activity'] = time();
    }

    /**
     * Define uma métrica
     */
    protected function setMetric(string $name, mixed $value): void
    {
        $this->metrics[$name] = $value;
        $this->metrics['last_activity'] = time();
    }

    /**
     * Executa uma operação com logging e métricas
     */
    protected function executeWithMetrics(string $operation, callable $callback): mixed
    {
        $startTime = microtime(true);
        $this->incrementMetric('requests_count');

        try {
            $this->logger->debug("Starting operation: {$operation}", [
                'service' => $this->getServiceName(),
                'operation' => $operation
            ]);

            $result = $callback();

            $duration = microtime(true) - $startTime;
            $this->setMetric("operation_{$operation}_duration", $duration);

            $this->logger->debug("Operation completed: {$operation}", [
                'service' => $this->getServiceName(),
                'operation' => $operation,
                'duration' => $duration
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->incrementMetric('errors_count');
            $duration = microtime(true) - $startTime;

            $this->logger->error("Operation failed: {$operation}", [
                'service' => $this->getServiceName(),
                'operation' => $operation,
                'error' => $e->getMessage(),
                'duration' => $duration,
                'exception' => get_class($e)
            ]);

            throw $e;
        }
    }

    /**
     * Obtém dados do cache ou executa callback
     */
    protected function getCachedOrExecute(string $key, callable $callback, int $ttl = 3600): mixed
    {
        $cacheKey = $this->getCacheKey($key);

        if ($this->cache->has($cacheKey)) {
            $this->incrementMetric('cache_hits');
            return $this->cache->get($cacheKey);
        }

        $result = $callback();
        $this->cache->set($cacheKey, $result, $ttl);
        $this->incrementMetric('cache_misses');

        return $result;
    }

    /**
     * Gera uma chave de cache específica do serviço
     */
    protected function getCacheKey(string $key): string
    {
        return sprintf('%s:%s', $this->getServiceName(), $key);
    }

    /**
     * Dispara um evento
     */
    protected function dispatch(string $eventName, array $data = []): void
    {
        $this->eventDispatcher->dispatch($eventName, array_merge($data, [
            'service' => $this->getServiceName(),
            'timestamp' => time()
        ]));
    }

    /**
     * Valida dados de entrada
     */
    protected function validateInput(array $data, array $rules): bool
    {
        foreach ($rules as $field => $rule) {
            if ($rule === 'required' && !isset($data[$field])) {
                throw new \InvalidArgumentException("Required field '{$field}' is missing");
            }
        }

        return true;
    }

    /**
     * Obtém o nome do serviço
     */
    abstract protected function getServiceName(): string;

    /**
     * Obtém a versão do serviço
     */
    abstract protected function getServiceVersion(): string;

    /**
     * Verifica disponibilidade específica do serviço
     */
    protected function checkAvailability(): bool
    {
        return true;
    }

    /**
     * Realiza health check específico do serviço
     */
    protected function performHealthCheck(): bool
    {
        return $this->checkAvailability();
    }
}
