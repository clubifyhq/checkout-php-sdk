<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Config;

use Clubify\Checkout\Enums\Environment;
use Clubify\Checkout\Exceptions\ConfigurationException;

/**
 * Sistema de configuração centralizada do Clubify Checkout SDK
 *
 * Gerencia todas as configurações do SDK com merge automático,
 * validação e acesso tipo-seguro às propriedades.
 */
class Configuration implements ConfigurationInterface
{
    private array $config = [];
    private array $defaults = [];

    public function __construct(array $config = [])
    {
        $this->initializeDefaults();
        $this->merge($config);
        $this->validate();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getNestedValue($this->config, $key) ?? $default;
    }

    public function set(string $key, mixed $value): self
    {
        $this->setNestedValue($this->config, $key, $value);
        return $this;
    }

    public function has(string $key): bool
    {
        return $this->getNestedValue($this->config, $key) !== null;
    }

    public function merge(array $config): self
    {
        $this->config = $this->deepMerge($this->config, $config);
        return $this;
    }

    public function toArray(): array
    {
        return $this->config;
    }

    public function getEnvironment(): string
    {
        return $this->get('credentials.environment', Environment::PRODUCTION->value);
    }

    public function getTenantId(): ?string
    {
        // Primeiro tentar credenciais padrão
        $tenantId = $this->get('credentials.tenant_id');

        // Se não encontrou e há configuração super_admin, usar tenant_id do super_admin
        if (empty($tenantId)) {
            $tenantId = $this->get('super_admin.tenant_id');
        }

        // Retornar null se for string vazia
        return empty($tenantId) ? null : $tenantId;
    }

    public function getApiKey(): ?string
    {
        return $this->get('credentials.api_key');
    }

    public function getBaseUrl(): string
    {
        // Try multiple configuration paths for flexibility
        $customUrl = $this->get('endpoints.base_url')
                  ?? $this->get('api.base_url')
                  ?? $this->get('base_url');

        if ($customUrl) {
            $normalizedUrl = rtrim($customUrl, '/');

            // If custom URL doesn't include /api/v1, add it
            if (!str_ends_with($normalizedUrl, '/api/v1')) {
                $normalizedUrl .= '/api/v1';
            }

            return $normalizedUrl;
        }

        $environment = Environment::from($this->getEnvironment());
        return $environment->getBaseUrl();
    }

    public function getTimeout(): int
    {
        return $this->get('http.timeout', 30000);
    }

    public function getMaxRetries(): int
    {
        return $this->get('retry.attempts', 3);
    }

    public function isDebugEnabled(): bool
    {
        return $this->get('debug', false) ||
               $this->getEnvironment() === Environment::DEVELOPMENT->value;
    }

    /**
     * Obter configurações específicas do cache
     */
    public function getCacheConfig(): array
    {
        return $this->get('cache', []);
    }

    /**
     * Obter configurações específicas do logger
     */
    public function getLoggerConfig(): array
    {
        return $this->get('logging', []);
    }

    /**
     * Obter configurações específicas de retry
     */
    public function getRetryConfig(): array
    {
        return $this->get('retry', []);
    }

    /**
     * Obter configurações específicas de HTTP
     */
    public function getHttpConfig(): array
    {
        return $this->get('http', []);
    }

    /**
     * Obter headers HTTP padrão
     */
    public function getDefaultHeaders(): array
    {
        $headers = [
            'User-Agent' => 'ClubifyCheckoutSDK-PHP/1.0.0',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-SDK-Version' => '1.0.0',
            'X-SDK-Language' => 'php',
        ];

        // Adicionar X-Tenant-Id se disponível
        $tenantId = $this->getTenantId();
        if ($tenantId) {
            $headers['X-Tenant-Id'] = $tenantId;
        }

        // Nota: O Authorization header será adicionado dinamicamente pelo AuthManager
        // através do método Client::getRequestHeaders() quando o token estiver disponível

        return $headers;
    }

    /**
     * Verificar se está em ambiente de produção
     */
    public function isProduction(): bool
    {
        return $this->getEnvironment() === Environment::PRODUCTION->value;
    }

    /**
     * Verificar se está em ambiente de desenvolvimento
     */
    public function isDevelopment(): bool
    {
        return $this->getEnvironment() === Environment::DEVELOPMENT->value;
    }

    /**
     * Definir credenciais de super admin
     */
    public function setSuperAdminCredentials(array $credentials): self
    {
        $this->set('super_admin', $credentials);
        return $this;
    }

    /**
     * Obter credenciais de super admin
     */
    public function getSuperAdminCredentials(): ?array
    {
        return $this->get('super_admin');
    }

    /**
     * Adicionar credenciais de tenant
     */
    public function addTenantCredentials(string $tenantId, array $credentials): self
    {
        $this->set("tenants.{$tenantId}", $credentials);
        return $this;
    }

    /**
     * Obter credenciais de tenant específico
     */
    public function getTenantCredentials(string $tenantId): ?array
    {
        return $this->get("tenants.{$tenantId}");
    }

    /**
     * Obter todos os tenants configurados
     */
    public function getAllTenants(): array
    {
        return $this->get('tenants', []);
    }

    /**
     * Verificar se há credenciais de super admin
     */
    public function hasSuperAdminCredentials(): bool
    {
        $credentials = $this->getSuperAdminCredentials();
        return $credentials !== null && isset($credentials['api_key']);
    }

    /**
     * Verificar se tenant específico está configurado
     */
    public function hasTenantCredentials(string $tenantId): bool
    {
        $credentials = $this->getTenantCredentials($tenantId);
        return $credentials !== null && isset($credentials['api_key']);
    }

    /**
     * Inicializar configurações padrão
     */
    private function initializeDefaults(): void
    {
        $this->defaults = [
            'credentials' => [
                'environment' => Environment::PRODUCTION->value,
            ],
            'http' => [
                'timeout' => 30000,
                'connect_timeout' => 10000,
                'verify_ssl' => true,
            ],
            'retry' => [
                'attempts' => 3,
                'delay' => 1000,
                'backoff' => 'exponential',
                'max_delay' => 30000,
            ],
            'cache' => [
                'enabled' => true,
                'default_ttl' => 600,
                'max_size' => 1000,
                'adapter' => 'array',
            ],
            'logging' => [
                'enabled' => true,
                'level' => 'info',
                'channels' => ['file'],
                'format' => 'json',
            ],
            'features' => [
                'cache' => true,
                'logging' => true,
                'events' => true,
                'validation' => true,
            ],
            'debug' => false,
        ];

        $this->config = $this->defaults;
    }

    /**
     * Validar configuração
     */
    private function validate(): void
    {
        // Validar ambiente
        $environment = $this->getEnvironment();
        if (!in_array($environment, [
            Environment::DEVELOPMENT->value,
            Environment::SANDBOX->value,
            Environment::STAGING->value,
            Environment::PRODUCTION->value
        ])) {
            throw new ConfigurationException(
                "Invalid environment: {$environment}",
                0,
                null,
                ['valid_environments' => ['development', 'sandbox', 'staging', 'production']]
            );
        }

        // Validar timeouts
        if ($this->getTimeout() <= 0) {
            throw new ConfigurationException('HTTP timeout must be greater than 0');
        }

        // Validar retry attempts
        if ($this->getMaxRetries() < 0) {
            throw new ConfigurationException('Retry attempts must be >= 0');
        }

        // Validar tenant_id se fornecido (tratar string vazia como null)
        $tenantId = $this->getTenantId();
        if ($tenantId !== null && $tenantId !== '' && (!is_string($tenantId) || empty($tenantId))) {
            throw new ConfigurationException('Tenant ID must be a non-empty string');
        }

        // Validar api_key se fornecido (tratar string vazia como null)
        $apiKey = $this->getApiKey();
        if ($apiKey !== null && $apiKey !== '' && (!is_string($apiKey) || empty($apiKey))) {
            throw new ConfigurationException('API Key must be a non-empty string');
        }
    }

    /**
     * Fazer merge profundo de arrays
     */
    private function deepMerge(array $array1, array $array2): array
    {
        $merged = $array1;

        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->deepMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * Obter valor usando notação de ponto (dot notation)
     */
    private function getNestedValue(array $array, string $key): mixed
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? null;
        }

        $keys = explode('.', $key);
        $value = $array;

        foreach ($keys as $nestedKey) {
            if (!is_array($value) || !array_key_exists($nestedKey, $value)) {
                return null;
            }
            $value = $value[$nestedKey];
        }

        return $value;
    }

    /**
     * Definir valor usando notação de ponto (dot notation)
     */
    private function setNestedValue(array &$array, string $key, mixed $value): void
    {
        if (strpos($key, '.') === false) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $nestedKey) {
            if (!isset($current[$nestedKey]) || !is_array($current[$nestedKey])) {
                $current[$nestedKey] = [];
            }
            $current = &$current[$nestedKey];
        }

        $current = $value;
    }
}
