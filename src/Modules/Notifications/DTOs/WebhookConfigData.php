<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para configuração de webhook
 *
 * Representa a configuração de um webhook com todos os seus dados:
 * - Informações básicas do webhook
 * - URL de destino e configurações
 * - Eventos que devem ser enviados
 * - Headers customizados e autenticação
 * - Políticas de retry e timeout
 * - Validação e teste de conectividade
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas configuração de webhook
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class WebhookConfigData extends BaseData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $url,
        public readonly array $events,
        public readonly bool $active = true,
        public readonly ?array $headers = null,
        public readonly ?string $secret = null,
        public readonly ?array $retryPolicy = null,
        public readonly int $timeout = 30,
        public readonly ?string $description = null,
        public readonly ?array $filters = null,
        public readonly ?string $environment = null,
        public readonly int $maxRetries = 3,
        public readonly int $retryDelay = 5,
        public readonly bool $verifySSL = true,
        public readonly ?string $userAgent = null,
        public readonly ?array $successCodes = null,
        public readonly ?array $metadata = null,
        public readonly ?\DateTime $lastTestedAt = null,
        public readonly ?string $lastTestResult = null,
        public readonly ?\DateTime $createdAt = null,
        public readonly ?\DateTime $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para configuração de webhook
     */
    protected function rules(): array
    {
        return [
            'id' => 'required|string|min:1|max:100',
            'name' => 'required|string|min:1|max:100',
            'url' => 'required|url|max:500',
            'events' => 'required|array|min:1',
            'timeout' => 'integer|min:1|max:300',
            'maxRetries' => 'integer|min:0|max:10',
            'retryDelay' => 'integer|min:1|max:3600',
            'environment' => 'string|in:development,staging,production',
        ];
    }

    /**
     * Converte para array seguro (remove dados sensíveis)
     */
    public function toSafeArray(): array
    {
        $data = $this->toArray();

        // Remove dados sensíveis
        if (isset($data['secret'])) {
            $data['secret'] = '***';
        }

        if (isset($data['headers'])) {
            foreach ($data['headers'] as $key => $value) {
                if (stripos($key, 'auth') !== false || stripos($key, 'token') !== false) {
                    $data['headers'][$key] = '***';
                }
            }
        }

        return $data;
    }

    /**
     * Converte para array completo
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'url' => $this->url,
            'events' => $this->events,
            'active' => $this->active,
            'headers' => $this->headers,
            'secret' => $this->secret,
            'retry_policy' => $this->retryPolicy,
            'timeout' => $this->timeout,
            'description' => $this->description,
            'filters' => $this->filters,
            'environment' => $this->environment,
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'verify_ssl' => $this->verifySSL,
            'user_agent' => $this->userAgent,
            'success_codes' => $this->successCodes,
            'metadata' => $this->metadata,
            'last_tested_at' => $this->lastTestedAt?->format('Y-m-d H:i:s'),
            'last_test_result' => $this->lastTestResult,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            url: $data['url'] ?? '',
            events: $data['events'] ?? [],
            active: (bool)($data['active'] ?? true),
            headers: $data['headers'] ?? null,
            secret: $data['secret'] ?? null,
            retryPolicy: $data['retry_policy'] ?? null,
            timeout: (int)($data['timeout'] ?? 30),
            description: $data['description'] ?? null,
            filters: $data['filters'] ?? null,
            environment: $data['environment'] ?? null,
            maxRetries: (int)($data['max_retries'] ?? 3),
            retryDelay: (int)($data['retry_delay'] ?? 5),
            verifySSL: (bool)($data['verify_ssl'] ?? true),
            userAgent: $data['user_agent'] ?? null,
            successCodes: $data['success_codes'] ?? null,
            metadata: $data['metadata'] ?? null,
            lastTestedAt: isset($data['last_tested_at']) ? new \DateTime($data['last_tested_at']) : null,
            lastTestResult: $data['last_test_result'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }

    /**
     * Verifica se o webhook está ativo
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Verifica se o webhook tem eventos configurados
     */
    public function hasEvents(): bool
    {
        return !empty($this->events);
    }

    /**
     * Verifica se o webhook escuta um evento específico
     */
    public function listensToEvent(string $eventType): bool
    {
        return in_array($eventType, $this->events);
    }

    /**
     * Verifica se tem autenticação configurada
     */
    public function hasAuthentication(): bool
    {
        return $this->secret !== null || $this->hasAuthHeaders();
    }

    /**
     * Verifica se tem headers de autenticação
     */
    public function hasAuthHeaders(): bool
    {
        if (empty($this->headers)) {
            return false;
        }

        foreach (array_keys($this->headers) as $header) {
            if (stripos($header, 'auth') !== false || stripos($header, 'token') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verifica se tem filtros configurados
     */
    public function hasFilters(): bool
    {
        return !empty($this->filters);
    }

    /**
     * Verifica se o webhook passou no último teste
     */
    public function passedLastTest(): bool
    {
        return $this->lastTestResult === 'success';
    }

    /**
     * Verifica se precisa de novo teste
     */
    public function needsRetesting(): bool
    {
        if ($this->lastTestedAt === null) {
            return true;
        }

        // Retest se falhou no último teste
        if ($this->lastTestResult !== 'success') {
            return true;
        }

        // Retest se passou mais de 24 horas
        $dayAgo = new \DateTime('-1 day');
        return $this->lastTestedAt < $dayAgo;
    }

    /**
     * Obtém headers para requisição
     */
    public function getRequestHeaders(): array
    {
        $headers = $this->headers ?? [];

        // Adiciona User-Agent se não especificado
        if (!isset($headers['User-Agent']) && $this->userAgent !== null) {
            $headers['User-Agent'] = $this->userAgent;
        }

        // Adiciona Content-Type padrão
        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    /**
     * Obtém códigos de sucesso válidos
     */
    public function getSuccessCodes(): array
    {
        return $this->successCodes ?? [200, 201, 202, 204];
    }

    /**
     * Obtém política de retry
     */
    public function getRetryPolicy(): array
    {
        if ($this->retryPolicy !== null) {
            return $this->retryPolicy;
        }

        return [
            'max_retries' => $this->maxRetries,
            'retry_delay' => $this->retryDelay,
            'backoff_multiplier' => 2,
            'max_delay' => 300
        ];
    }

    /**
     * Verifica se um código de resposta é sucesso
     */
    public function isSuccessCode(int $statusCode): bool
    {
        return in_array($statusCode, $this->getSuccessCodes());
    }

    /**
     * Obtém configuração para cURL
     */
    public function getCurlOptions(): array
    {
        $options = [
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $this->formatHeadersForCurl(),
            CURLOPT_SSL_VERIFYPEER => $this->verifySSL,
            CURLOPT_SSL_VERIFYHOST => $this->verifySSL ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ];

        if ($this->userAgent !== null) {
            $options[CURLOPT_USERAGENT] = $this->userAgent;
        }

        return $options;
    }

    /**
     * Formata headers para cURL
     */
    private function formatHeadersForCurl(): array
    {
        $headers = [];
        foreach ($this->getRequestHeaders() as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }
        return $headers;
    }

    /**
     * Obtém assinatura HMAC para payload
     */
    public function generateSignature(string $payload): ?string
    {
        if ($this->secret === null) {
            return null;
        }

        return hash_hmac('sha256', $payload, $this->secret);
    }

    /**
     * Verifica assinatura HMAC
     */
    public function verifySignature(string $payload, string $signature): bool
    {
        if ($this->secret === null) {
            return false;
        }

        $expectedSignature = $this->generateSignature($payload);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Aplica filtros ao payload
     */
    public function applyFilters(array $payload): bool
    {
        if (empty($this->filters)) {
            return true;
        }

        foreach ($this->filters as $field => $criteria) {
            if (!$this->matchesCriteria($payload, $field, $criteria)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verifica se payload atende critério
     */
    private function matchesCriteria(array $payload, string $field, $criteria): bool
    {
        $value = $payload[$field] ?? null;

        if (is_array($criteria)) {
            return in_array($value, $criteria);
        }

        if (is_string($criteria) && strpos($criteria, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($criteria, '/'));
            return preg_match("/^{$pattern}$/", (string)$value) === 1;
        }

        return $value === $criteria;
    }

    /**
     * Obtém dados de teste
     */
    public function getTestData(): array
    {
        return [
            'webhook_id' => $this->id,
            'test_event' => 'webhook.test',
            'timestamp' => time(),
            'data' => [
                'test' => true,
                'webhook_name' => $this->name,
                'events' => $this->events
            ]
        ];
    }

    /**
     * Valida URL do webhook
     */
    public function hasValidUrl(): bool
    {
        return filter_var($this->url, FILTER_VALIDATE_URL) !== false
            && (strpos($this->url, 'https://') === 0 || strpos($this->url, 'http://') === 0);
    }

    /**
     * Verifica se é ambiente de produção
     */
    public function isProduction(): bool
    {
        return $this->environment === 'production';
    }

    /**
     * Verifica se é ambiente de desenvolvimento
     */
    public function isDevelopment(): bool
    {
        return $this->environment === 'development';
    }
}