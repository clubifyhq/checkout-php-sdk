<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use ClubifyCheckout\Services\BaseService;
use Clubify\Checkout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;
use ClubifyCheckout\Utils\Validators\EmailValidator;
use ClubifyCheckout\Utils\Validators\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de configuração de webhooks
 *
 * Gerencia configurações globais e específicas de webhooks,
 * incluindo validação de URLs, filtros de eventos,
 * configuração de retry, rate limiting e segurança.
 */
class ConfigService extends BaseService
{
    private const CACHE_PREFIX = 'webhook_config:';
    private const CACHE_TTL = 3600; // 1 hora

    private array $defaultConfig = [
        'delivery' => [
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry_enabled' => true,
            'max_retries' => 5,
            'retry_delay' => 300, // 5 minutos
            'retry_backoff' => 'exponential',
            'retry_multiplier' => 2.0,
            'max_retry_delay' => 3600, // 1 hora
        ],
        'security' => [
            'validate_ssl' => true,
            'require_https' => true,
            'signature_required' => true,
            'signature_header' => 'X-Webhook-Signature',
            'signature_algorithm' => 'sha256',
            'allowed_domains' => [],
            'blocked_domains' => [],
            'allowed_ips' => [],
            'blocked_ips' => [],
        ],
        'rate_limiting' => [
            'enabled' => true,
            'max_requests_per_minute' => 60,
            'max_requests_per_hour' => 1000,
            'max_concurrent_requests' => 10,
            'circuit_breaker_enabled' => true,
            'circuit_breaker_threshold' => 5,
            'circuit_breaker_timeout' => 300,
        ],
        'monitoring' => [
            'log_all_deliveries' => true,
            'log_failed_only' => false,
            'alert_on_failure_rate' => 0.1, // 10%
            'alert_on_consecutive_failures' => 5,
            'metrics_enabled' => true,
            'health_check_enabled' => true,
            'health_check_interval' => 300, // 5 minutos
        ],
        'events' => [
            'allowed_events' => [],
            'blocked_events' => [],
            'event_filters' => [],
            'payload_size_limit' => 1048576, // 1MB
            'include_metadata' => true,
            'include_user_agent' => true,
        ],
        'cleanup' => [
            'auto_cleanup_enabled' => true,
            'keep_successful_logs_days' => 30,
            'keep_failed_logs_days' => 90,
            'keep_inactive_webhooks_days' => 365,
            'cleanup_interval_hours' => 24,
        ],
    ];

    public function __construct(
        private WebhookRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        private ?ValidatorInterface $urlValidator = null
    ) {
        parent::__construct($logger, $cache);
        $this->urlValidator ??= new EmailValidator(); // Temporário, idealmente seria URLValidator
    }

    /**
     * Obtém configuração global
     */
    public function getGlobalConfig(): array
    {
        return $this->withCache(
            self::CACHE_PREFIX . 'global',
            fn () => $this->repository->getGlobalConfig(),
            self::CACHE_TTL
        );
    }

    /**
     * Atualiza configuração global
     */
    public function updateGlobalConfig(array $config): bool
    {
        $validatedConfig = $this->validateConfig($config);

        $success = $this->repository->updateGlobalConfig($validatedConfig);

        if ($success) {
            $this->cache->deleteItem(self::CACHE_PREFIX . 'global');

            $this->logger->info('Configuração global de webhook atualizada', [
                'config_keys' => array_keys($validatedConfig),
            ]);
        }

        return $success;
    }

    /**
     * Obtém configuração merged (global + padrão)
     */
    public function getMergedConfig(): array
    {
        $globalConfig = $this->getGlobalConfig();
        return $this->mergeConfigs($this->defaultConfig, $globalConfig);
    }

    /**
     * Obtém configuração para webhook específico
     */
    public function getWebhookConfig(string $webhookId): array
    {
        return $this->withCache(
            self::CACHE_PREFIX . "webhook:{$webhookId}",
            function () use ($webhookId) {
                $webhook = $this->repository->findById($webhookId);
                if (!$webhook) {
                    throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
                }

                $globalConfig = $this->getMergedConfig();
                $webhookConfig = $webhook['config'] ?? [];

                return $this->mergeConfigs($globalConfig, $webhookConfig);
            },
            self::CACHE_TTL
        );
    }

    /**
     * Atualiza configuração de webhook específico
     */
    public function updateWebhookConfig(string $webhookId, array $config): bool
    {
        $validatedConfig = $this->validateConfig($config);

        $webhook = $this->repository->findById($webhookId);
        if (!$webhook) {
            throw new \InvalidArgumentException("Webhook não encontrado: {$webhookId}");
        }

        $currentConfig = $webhook['config'] ?? [];
        $mergedConfig = $this->mergeConfigs($currentConfig, $validatedConfig);

        $success = $this->repository->update($webhookId, ['config' => $mergedConfig]);

        if ($success) {
            $this->cache->deleteItem(self::CACHE_PREFIX . "webhook:{$webhookId}");

            $this->logger->info('Configuração de webhook atualizada', [
                'webhook_id' => $webhookId,
                'config_keys' => array_keys($validatedConfig),
            ]);
        }

        return $success;
    }

    /**
     * Valida URL de webhook
     */
    public function validateWebhookUrl(string $url): array
    {
        $result = [
            'valid' => false,
            'errors' => [],
            'warnings' => [],
            'metadata' => [],
        ];

        // Validação básica de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $result['errors'][] = 'URL inválida';
            return $result;
        }

        $parsedUrl = parse_url($url);

        // Verifica HTTPS se obrigatório
        $config = $this->getMergedConfig();
        if ($config['security']['require_https'] && $parsedUrl['scheme'] !== 'https') {
            $result['errors'][] = 'HTTPS é obrigatório';
            return $result;
        }

        // Verifica domínios permitidos/bloqueados
        $host = $parsedUrl['host'];

        if (!empty($config['security']['allowed_domains'])) {
            $allowed = false;
            foreach ($config['security']['allowed_domains'] as $domain) {
                if (str_ends_with($host, $domain)) {
                    $allowed = true;
                    break;
                }
            }
            if (!$allowed) {
                $result['errors'][] = "Domínio não permitido: {$host}";
                return $result;
            }
        }

        foreach ($config['security']['blocked_domains'] as $domain) {
            if (str_ends_with($host, $domain)) {
                $result['errors'][] = "Domínio bloqueado: {$host}";
                return $result;
            }
        }

        // Verifica IPs se configurado
        $ip = gethostbyname($host);

        if (!empty($config['security']['allowed_ips']) && !in_array($ip, $config['security']['allowed_ips'])) {
            $result['errors'][] = "IP não permitido: {$ip}";
            return $result;
        }

        if (in_array($ip, $config['security']['blocked_ips'])) {
            $result['errors'][] = "IP bloqueado: {$ip}";
            return $result;
        }

        // Verifica se é IP local/privado
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            $result['warnings'][] = 'URL aponta para IP privado/local';
        }

        // Testa conectividade
        try {
            $connectResult = $this->repository->validateUrl($url);
            $result['metadata']['connectivity'] = $connectResult;

            if (!$connectResult['reachable']) {
                $result['warnings'][] = 'URL não é acessível no momento';
            }
        } catch (\Exception $e) {
            $result['warnings'][] = 'Não foi possível testar conectividade: ' . $e->getMessage();
        }

        $result['valid'] = empty($result['errors']);
        $result['metadata']['ip'] = $ip;
        $result['metadata']['host'] = $host;
        $result['metadata']['scheme'] = $parsedUrl['scheme'];

        return $result;
    }

    /**
     * Verifica se evento passa pelos filtros configurados
     */
    public function eventPassesFilters(string $eventType, array $eventData, array $webhook): bool
    {
        $config = $this->getWebhookConfig($webhook['id']);

        // Verifica eventos permitidos
        if (!empty($config['events']['allowed_events']) && !in_array($eventType, $config['events']['allowed_events'])) {
            return false;
        }

        // Verifica eventos bloqueados
        if (in_array($eventType, $config['events']['blocked_events'])) {
            return false;
        }

        // Verifica filtros específicos do webhook
        if (!empty($webhook['event_filters'])) {
            foreach ($webhook['event_filters'] as $filter) {
                if (!$this->evaluateEventFilter($filter, $eventType, $eventData)) {
                    return false;
                }
            }
        }

        // Verifica filtros globais
        if (!empty($config['events']['event_filters'])) {
            foreach ($config['events']['event_filters'] as $filter) {
                if (!$this->evaluateEventFilter($filter, $eventType, $eventData)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Gera configuração padrão para novo webhook
     */
    public function generateDefaultWebhookConfig(): array
    {
        return [
            'events' => ['*'], // Todos os eventos por padrão
            'event_filters' => [],
            'retry_enabled' => true,
            'max_retries' => 3,
            'timeout' => 30,
            'active' => true,
        ];
    }

    /**
     * Exporta configuração completa
     */
    public function exportConfiguration(): array
    {
        return [
            'global_config' => $this->getGlobalConfig(),
            'default_config' => $this->defaultConfig,
            'merged_config' => $this->getMergedConfig(),
            'export_timestamp' => date('Y-m-d H:i:s'),
            'version' => '1.0.0',
        ];
    }

    /**
     * Importa configuração
     */
    public function importConfiguration(array $configData): array
    {
        $results = [
            'success' => false,
            'imported' => [],
            'errors' => [],
            'warnings' => [],
        ];

        try {
            if (isset($configData['global_config'])) {
                $this->updateGlobalConfig($configData['global_config']);
                $results['imported'][] = 'global_config';
            }

            $results['success'] = true;

            $this->logger->info('Configuração de webhook importada', [
                'imported_items' => $results['imported'],
            ]);

        } catch (\Exception $e) {
            $results['errors'][] = $e->getMessage();

            $this->logger->error('Erro ao importar configuração de webhook', [
                'error' => $e->getMessage(),
            ]);
        }

        return $results;
    }

    /**
     * Valida configuração
     */
    private function validateConfig(array $config): array
    {
        $validated = [];

        foreach ($config as $section => $values) {
            if (!isset($this->defaultConfig[$section])) {
                continue; // Ignora seções desconhecidas
            }

            $validated[$section] = [];

            foreach ($values as $key => $value) {
                if (!isset($this->defaultConfig[$section][$key])) {
                    continue; // Ignora chaves desconhecidas
                }

                $validated[$section][$key] = $this->validateConfigValue($section, $key, $value);
            }
        }

        return $validated;
    }

    /**
     * Valida valor específico de configuração
     */
    private function validateConfigValue(string $section, string $key, mixed $value): mixed
    {
        $defaultValue = $this->defaultConfig[$section][$key];

        // Validações específicas por tipo
        switch ($key) {
            case 'timeout':
            case 'connect_timeout':
            case 'retry_delay':
            case 'max_retry_delay':
                return max(1, min(3600, (int) $value)); // Entre 1s e 1h

            case 'max_retries':
                return max(0, min(10, (int) $value)); // Entre 0 e 10

            case 'retry_multiplier':
                return max(1.0, min(10.0, (float) $value)); // Entre 1x e 10x

            case 'max_requests_per_minute':
                return max(1, min(1000, (int) $value)); // Entre 1 e 1000

            case 'max_requests_per_hour':
                return max(1, min(100000, (int) $value)); // Entre 1 e 100k

            case 'payload_size_limit':
                return max(1024, min(10485760, (int) $value)); // Entre 1KB e 10MB

            case 'alert_on_failure_rate':
                return max(0.0, min(1.0, (float) $value)); // Entre 0% e 100%

            default:
                // Para outros tipos, mantém o tipo original se compatível
                if (gettype($value) === gettype($defaultValue)) {
                    return $value;
                }
                return $defaultValue;
        }
    }

    /**
     * Merge de configurações
     */
    private function mergeConfigs(array $base, array $override): array
    {
        $result = $base;

        foreach ($override as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                $result[$key] = $this->mergeConfigs($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Avalia filtro de evento
     */
    private function evaluateEventFilter(array $filter, string $eventType, array $eventData): bool
    {
        $field = $filter['field'] ?? '';
        $operator = $filter['operator'] ?? 'equals';
        $value = $filter['value'] ?? '';

        // Obtém valor do campo
        $fieldValue = $this->getFieldValue($eventData, $field);

        // Avalia operador
        return match ($operator) {
            'equals' => $fieldValue == $value,
            'not_equals' => $fieldValue != $value,
            'contains' => str_contains((string) $fieldValue, (string) $value),
            'not_contains' => !str_contains((string) $fieldValue, (string) $value),
            'starts_with' => str_starts_with((string) $fieldValue, (string) $value),
            'ends_with' => str_ends_with((string) $fieldValue, (string) $value),
            'greater_than' => $fieldValue > $value,
            'less_than' => $fieldValue < $value,
            'in' => in_array($fieldValue, (array) $value),
            'not_in' => !in_array($fieldValue, (array) $value),
            'regex' => preg_match("/{$value}/", (string) $fieldValue),
            'exists' => $fieldValue !== null,
            'not_exists' => $fieldValue === null,
            default => true,
        };
    }

    /**
     * Obtém valor de campo usando notação dot
     */
    private function getFieldValue(array $data, string $field): mixed
    {
        $keys = explode('.', $field);
        $value = $data;

        foreach ($keys as $key) {
            if (!is_array($value) || !isset($value[$key])) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }
}
