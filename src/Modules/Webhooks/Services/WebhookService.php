<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Webhooks\Repositories\ApiWebhookRepository;
use Clubify\Checkout\Modules\Webhooks\DTOs\WebhookData;
use Clubify\Checkout\Modules\Webhooks\Exceptions\WebhookException;
use InvalidArgumentException;

/**
 * Serviço de gerenciamento de webhooks
 *
 * Implementa operações CRUD e lógica de negócio para webhooks,
 * incluindo validação, ativação/desativação e monitoramento
 * de performance.
 *
 * Funcionalidades principais:
 * - CRUD completo de webhooks
 * - Validação de URLs e configurações
 * - Ativação/desativação automática
 * - Monitoramento de falhas
 * - Cache inteligente
 * - Métricas e analytics
 *
 * Regras de negócio:
 * - URLs devem ser HTTPS em produção
 * - Máximo de falhas consecutivas antes de desativar
 * - Validação de eventos suportados
 * - Rate limiting por webhook
 */
class WebhookService extends BaseService implements ServiceInterface
{
    private const MAX_CONSECUTIVE_FAILURES = 10;
    private const MAX_WEBHOOKS_PER_ORG = 50;

    private array $validationRules = [
        'url' => ['required', 'url', 'https'],
        'events' => ['required', 'array'],
        'secret' => ['required', 'string', 'min:32'],
        'active' => ['boolean'],
        'description' => ['string', 'max:255'],
    ];

    private array $supportedEvents = [
        // Order events
        'order.created',
        'order.paid',
        'order.updated',
        'order.shipped',
        'order.delivered',
        'order.completed',
        'order.cancelled',
        'order.refunded',

        // Payment events
        'payment.authorized',
        'payment.paid',
        'payment.completed',
        'payment.failed',
        'payment.refunded',
        'payment.cancelled',
        'payment.method.saved',
        'payment.action_required',
        'payment_method.changed',
        'payment_method.setup_completed',

        // Customer events
        'customer.created',
        'customer.updated',
        'customer.deleted',
        'customer.merged',
        'customer.segment.changed',
        'customer.consent.updated',
        'customer.metrics.updated',
        'customer.address.added',
        'customer.address.updated',
        'customer.address.deleted',

        // User events
        'user.preferences.updated',
        'user.data.deleted',
        'user.updated',

        // Product events
        'product.created',
        'product.updated',
        'product.deleted',

        // Checkout events
        'checkout.created',
        'checkout.confirmed',
        'checkout.payment_method_updated',
        'checkout.failed',
        'checkout.expired',

        // Cart events
        'cart.created',
        'cart.updated',
        'cart.abandoned',
        'cart.recovered',
        'cart.cleanup',
        'cart.converted',

        // Subscription events
        'subscription.created',
        'subscription.cancelled',
        'subscription.access_suspended',
        'subscription.canceled_for_nonpayment',
        'subscription.activated',
        'subscription.updated',
        'subscription.canceled',
        'subscription.trial_ending',
        'subscription.trial_converted',
        'subscription.payment_failed',
        'subscription.payment_recovered',
        'subscription.access_revoked',

        // Special events
        'dunning.email_required',
        'dunning.sms_required',
        'dunning.payment_recovered',
        'gdpr.audit',
        'coupon.validated',
        'coupon.applied',
        'promotions.detected',
        'promotion.applied',

        // OneClick Checkout events
        'oneclickcheckout.initiated',
        'oneclickcheckout.processing',
        'oneclickcheckout.completed',
        'oneclickcheckout.failed',

        // Affiliate events
        'affiliate.registered',
        'affiliate.approved',
        'affiliate.click',
        'affiliate.conversion',

        // Digital Wallet events
        'digital-wallet.payment.processed',
        'digital-wallet.payment.failed',

        // API Key events
        'api-key.generated',
        'api-key.updated',
        'api-key.revoked',
        'api-key.rotated',
    ];

    public function __construct(
        private ApiWebhookRepository $repository
    ) {
        // Parent constructor will be called by Factory with proper dependencies
    }

    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'webhook';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria novo webhook
     */
    public function create(array $webhookData): array
    {
        return $this->executeWithMetrics('create', function () use ($webhookData) {
            // Gera secret se não fornecido (ANTES da validação)
            if (empty($webhookData['secret'])) {
                $webhookData['secret'] = $this->generateSecret();
            }

            // Valida dados
            $this->validateWebhookData($webhookData);

            // Verifica limite por organização
            $organizationId = $webhookData['organization_id'] ?? $webhookData['tenant_id'] ?? null;
            $organizationId = $organizationId ?: null; // Converte false/empty string para null
            $this->validateOrganizationLimit($organizationId);

            // Verifica se URL já existe
            $this->validateUniqueUrl($webhookData['url']);

            // Valida conectividade da URL (desabilitado temporariamente para testes)
            // $this->validateUrlConnectivity($webhookData['url']);

            // Sanitiza dados
            $sanitizedData = $this->sanitizeWebhookData($webhookData);

            // Adiciona metadados padrão
            $sanitizedData = $this->addDefaultMetadata($sanitizedData);

            // Transforma dados para o formato da API do notification-service
            $apiPayload = $this->transformToApiPayload($sanitizedData);

            $this->logger->info('Creating webhook with API payload', [
                'tenant_id' => $apiPayload['tenantId'] ?? null,
                'name' => $apiPayload['name'] ?? null,
                'endpoint_count' => count($apiPayload['endpoints'] ?? []),
                'is_active' => $apiPayload['isActive'] ?? null,
                'payload_keys' => array_keys($apiPayload)
            ]);

            // Cria webhook
            $webhook = $this->repository->create($apiPayload);

            $this->logger->info('Webhook criado com sucesso', [
                'webhook_id' => $webhook['_id'] ?? $webhook['id'] ?? null,
                'tenant_id' => $webhook['tenantId'] ?? null,
                'config_name' => $webhook['name'] ?? null,
                'url' => $webhook['url'] ?? null,
                'events' => $webhook['events'] ?? [],
                'endpoint_count' => count($webhook['endpoints'] ?? [])
            ]);

            // Dispara evento
            $this->dispatchEvent('webhook.created', $webhook);

            // Invalida cache - use tenantId from webhook response
            $tenantId = $webhook['tenantId'] ?? $apiPayload['tenantId'] ?? null;
            $this->logger->debug('Invalidating webhook cache after creation', [
                'tenant_id' => $tenantId,
                'organization_id' => $webhook['organization_id'] ?? null
            ]);
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, null, $tenantId);

            return $webhook;
        });
    }

    /**
     * Busca webhook por ID
     */
    public function findById(string $webhookId): ?array
    {
        return $this->getCachedOrExecute(
            "webhook:{$webhookId}",
            fn () => $this->repository->findById($webhookId),
            300 // 5 minutos
        );
    }

    /**
     * Atualiza webhook
     */
    public function update(string $webhookId, array $updateData): array
    {
        return $this->executeWithMetrics('update', function () use ($webhookId, $updateData) {
            $existingWebhook = $this->repository->findById($webhookId);
            if (!$existingWebhook) {
                throw WebhookException::notFound($webhookId);
            }

            // Valida dados de atualização
            $this->validateUpdateData($updateData);

            // Verifica URL se mudou
            if (isset($updateData['url']) && $updateData['url'] !== ($existingWebhook['url'] ?? null)) {
                $this->validateUniqueUrl($updateData['url'], $webhookId);
                $this->validateUrlConnectivity($updateData['url']);
            }

            // Sanitiza dados
            $sanitizedData = $this->sanitizeWebhookData($updateData, true);

            // Adiciona timestamp de atualização
            $sanitizedData['updated_at'] = date('Y-m-d H:i:s');

            // Atualiza webhook
            $webhook = $this->repository->update($webhookId, $sanitizedData);

            // Dispara evento
            $this->dispatchEvent('webhook.updated', [
                'webhook' => $webhook,
                'changes' => $this->getChanges($existingWebhook, $webhook),
            ]);

            // Invalida cache
            $tenantId = $webhook['tenantId'] ?? $existingWebhook['tenantId'] ?? null;
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);

            $this->logger->info('Webhook atualizado com sucesso', [
                'webhook_id' => $webhookId,
                'updated_fields' => array_keys($sanitizedData),
            ]);

            return $webhook;
        });
    }

    /**
     * Remove webhook
     */
    public function delete(string $webhookId): bool
    {
        return $this->executeWithMetrics('delete', function () use ($webhookId) {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw WebhookException::notFound($webhookId);
            }

            // Remove webhook
            $result = $this->repository->delete($webhookId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('webhook.deleted', $webhook);

                // Invalida cache
                $tenantId = $webhook['tenantId'] ?? null;
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);

                $this->logger->info('Webhook removido com sucesso', [
                    'webhook_id' => $webhookId,
                ]);
            }

            return $result;
        });
    }

    /**
     * Busca webhooks por evento
     */
    public function findByEvent(string $eventType): array
    {
        $cacheKey = "webhooks:event:{$eventType}";

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByEvent($eventType),
            180 // 3 minutos
        );
    }

    /**
     * Busca webhooks por organização
     */
    public function findByOrganization(string $organizationId): array
    {
        if (empty($organizationId)) {
            throw WebhookException::invalidConfig('organization_id', 'Organization ID cannot be empty');
        }

        $cacheKey = "webhooks:org:{$organizationId}";

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->findByOrganization($organizationId),
            300 // 5 minutos
        );
    }

    /**
     * Ativa webhook
     */
    public function activate(string $webhookId): bool
    {
        return $this->executeWithMetrics('activate', function () use ($webhookId) {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw WebhookException::notFound($webhookId);
            }

            if ($webhook['active'] ?? false) {
                return true; // Já está ativo
            }

            // Valida conectividade antes de ativar
            if (isset($webhook['url'])) {
                $this->validateUrlConnectivity($webhook['url']);
            }

            // Reseta contador de falhas
            $this->repository->resetFailureCount($webhookId);

            // Ativa webhook
            $result = $this->repository->activate($webhookId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('webhook.activated', $webhook);

                // Invalida cache
                $tenantId = $webhook['tenantId'] ?? null;
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);

                $this->logger->info('Webhook ativado com sucesso', [
                    'webhook_id' => $webhookId,
                ]);
            }

            return $result;
        });
    }

    /**
     * Desativa webhook
     */
    public function deactivate(string $webhookId, string $reason = null): bool
    {
        return $this->executeWithMetrics('deactivate', function () use ($webhookId, $reason) {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                throw WebhookException::notFound($webhookId);
            }

            if (!($webhook['active'] ?? true)) {
                return true; // Já está inativo
            }

            // Desativa webhook
            $result = $this->repository->deactivate($webhookId);

            if ($result) {
                // Adiciona razão se fornecida
                if ($reason) {
                    $this->repository->update($webhookId, [
                        'deactivation_reason' => $reason,
                        'deactivated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                // Dispara evento
                $this->dispatchEvent('webhook.deactivated', [
                    'webhook' => $webhook,
                    'reason' => $reason,
                ]);

                // Invalida cache
                $tenantId = $webhook['tenantId'] ?? null;
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);

                $this->logger->info('Webhook desativado', [
                    'webhook_id' => $webhookId,
                    'reason' => $reason,
                ]);
            }

            return $result;
        });
    }

    /**
     * Processa falha de entrega
     */
    public function processDeliveryFailure(string $webhookId, array $failureData): void
    {
        $this->executeWithMetrics('processDeliveryFailure', function () use ($webhookId, $failureData) {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                return;
            }

            // Incrementa contador de falhas
            $this->repository->incrementFailureCount($webhookId);

            // Atualiza dados da última entrega
            $this->repository->updateLastDelivery($webhookId, [
                'last_failure_at' => date('Y-m-d H:i:s'),
                'last_failure_reason' => $failureData['error'] ?? 'Unknown error',
                'last_response_code' => $failureData['response_code'] ?? null,
            ]);

            // Verifica se deve desativar por muitas falhas
            $currentFailures = ($webhook['consecutive_failures'] ?? 0) + 1;
            if ($currentFailures >= self::MAX_CONSECUTIVE_FAILURES) {
                $this->deactivate($webhookId, 'Muitas falhas consecutivas');
            }

            // Invalida cache
            $tenantId = $webhook['tenantId'] ?? null;
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);

            $this->logger->warning('Falha na entrega de webhook', [
                'webhook_id' => $webhookId,
                'consecutive_failures' => $currentFailures,
                'error' => $failureData['error'] ?? 'Unknown',
            ]);
        });
    }

    /**
     * Processa entrega bem-sucedida
     */
    public function processDeliverySuccess(string $webhookId, array $successData): void
    {
        $this->executeWithMetrics('processDeliverySuccess', function () use ($webhookId, $successData) {
            $webhook = $this->repository->findById($webhookId);
            if (!$webhook) {
                return;
            }

            // Reseta contador de falhas
            $this->repository->resetFailureCount($webhookId);

            // Atualiza dados da última entrega
            $this->repository->updateLastDelivery($webhookId, [
                'last_success_at' => date('Y-m-d H:i:s'),
                'last_response_code' => $successData['response_code'] ?? 200,
                'last_response_time' => $successData['response_time'] ?? null,
            ]);

            // Invalida cache
            $tenantId = $webhook['tenantId'] ?? null;
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId, $tenantId);
        });
    }

    /**
     * Gera secret seguro
     */
    public function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Obtém estatísticas de webhook
     */
    public function getStats(string $webhookId): array
    {
        $cacheKey = "webhook:stats:{$webhookId}";

        return $this->getCachedOrExecute(
            $cacheKey,
            fn () => $this->repository->getWebhookStats($webhookId),
            300 // 5 minutos
        );
    }

    /**
     * Lista eventos suportados
     */
    public function getSupportedEvents(): array
    {
        return $this->supportedEvents;
    }

    /**
     * Valida dados do webhook
     */
    private function validateWebhookData(array $data): void
    {
        // Validação de campos obrigatórios
        foreach ($this->validationRules as $field => $rules) {
            if (in_array('required', $rules) && (!isset($data[$field]) || empty($data[$field]))) {
                throw new InvalidArgumentException("Campo obrigatório ausente: {$field}");
            }

            if (isset($data[$field])) {
                $this->validateField($field, $data[$field], $rules);
            }
        }

        // Valida eventos
        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
        }
    }

    /**
     * Valida eventos
     */
    private function validateEvents(array $events): void
    {
        if (empty($events)) {
            throw new InvalidArgumentException('Pelo menos um evento deve ser especificado');
        }

        foreach ($events as $event) {
            if (!in_array($event, $this->supportedEvents)) {
                throw new InvalidArgumentException("Evento não suportado: {$event}");
            }
        }
    }

    /**
     * Valida URL única
     */
    private function validateUniqueUrl(string $url, string $excludeId = null): void
    {
        $existing = $this->repository->findByUrl($url);

        if ($existing) {
            $existingId = $existing['_id'] ?? $existing['id'] ?? null;
            if ($existingId && $existingId !== $excludeId) {
                throw WebhookException::invalidUrl($url, "Já existe um webhook com esta URL");
            }
        }
    }

    /**
     * Valida conectividade da URL
     */
    private function validateUrlConnectivity(string $url): void
    {
        $validation = $this->validateUrl($url);

        if (!$validation['accessible']) {
            throw WebhookException::invalidUrl($url, "URL não acessível: {$validation['error']}");
        }

        if ($validation['response_code'] < 200 || $validation['response_code'] >= 300) {
            throw WebhookException::invalidUrl($url, "URL retornou código HTTP inválido: {$validation['response_code']}");
        }
    }

    /**
     * Valida URL de webhook
     */
    public function validateUrl(string $url): array
    {
        $result = [
            'url' => $url,
            'accessible' => false,
            'response_code' => null,
            'response_time' => null,
            'error' => null,
            'headers' => [],
            'ssl_valid' => false,
            'redirect_count' => 0,
        ];

        try {
            // Parse URL
            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host'])) {
                $result['error'] = 'URL malformada';
                return $result;
            }

            // Validate scheme
            if (!in_array($parsedUrl['scheme'], ['http', 'https'])) {
                $result['error'] = 'Esquema não suportado. Use HTTP ou HTTPS';
                return $result;
            }

            // Check for private/local networks in production
            if ($this->isProductionEnvironment() && $this->isPrivateNetwork($parsedUrl['host'])) {
                $result['error'] = 'URLs de rede privada não são permitidas em produção';
                return $result;
            }

            $startTime = microtime(true);

            // Initialize cURL
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_NOBODY => true, // HEAD request
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Clubify-Checkout-SDK/1.0 (+https://clubify.com.br)',
                CURLOPT_HEADER => true,
                CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$result) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) return $len;

                    $name = strtolower(trim($header[0]));
                    $value = trim($header[1]);
                    $result['headers'][$name] = $value;

                    return $len;
                },
            ]);

            $response = curl_exec($ch);
            $result['response_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $result['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            $result['redirect_count'] = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);

            if (curl_errno($ch)) {
                $result['error'] = curl_error($ch);
            } else {
                $result['accessible'] = $result['response_code'] >= 200 && $result['response_code'] < 500;

                // Check SSL for HTTPS URLs
                if ($parsedUrl['scheme'] === 'https') {
                    $sslInfo = curl_getinfo($ch, CURLINFO_SSL_VERIFYRESULT);
                    $result['ssl_valid'] = $sslInfo === 0;
                }
            }

            curl_close($ch);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Verifica se é ambiente de produção
     */
    private function isProductionEnvironment(): bool
    {
        return in_array(strtolower($_ENV['APP_ENV'] ?? 'production'), ['production', 'prod']);
    }

    /**
     * Verifica se host está em rede privada
     */
    private function isPrivateNetwork(string $host): bool
    {
        // Localhost variants
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'])) {
            return true;
        }

        // Private IP ranges
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip = ip2long($host);
            return ($ip >= ip2long('10.0.0.0') && $ip <= ip2long('10.255.255.255')) ||
                   ($ip >= ip2long('172.16.0.0') && $ip <= ip2long('172.31.255.255')) ||
                   ($ip >= ip2long('192.168.0.0') && $ip <= ip2long('192.168.255.255')) ||
                   ($ip >= ip2long('169.254.0.0') && $ip <= ip2long('169.254.255.255'));
        }

        return false;
    }

    /**
     * Valida limite por organização
     */
    private function validateOrganizationLimit(string $organizationId = null): void
    {
        if (!$organizationId) {
            return;
        }

        $count = $this->repository->count(['organization_id' => $organizationId]);

        if ($count >= self::MAX_WEBHOOKS_PER_ORG) {
            throw WebhookException::limitExceeded($count, self::MAX_WEBHOOKS_PER_ORG);
        }
    }

    /**
     * Sanitiza dados do webhook
     */
    private function sanitizeWebhookData(array $data, bool $isUpdate = false): array
    {
        $sanitized = [];

        // Campos de texto
        $textFields = ['url', 'description', 'secret'];
        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = trim($data[$field]);
            }
        }

        // URL para lowercase
        if (isset($sanitized['url'])) {
            $sanitized['url'] = strtolower($sanitized['url']);
        }

        // Outros campos diretos
        $directFields = ['events', 'active', 'organization_id', 'headers', 'timeout'];
        foreach ($directFields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field];
            }
        }

        return $sanitized;
    }

    /**
     * Adiciona metadados padrão
     */
    private function addDefaultMetadata(array $data): array
    {
        $data['id'] = $data['id'] ?? $this->generateWebhookId();
        $data['active'] = $data['active'] ?? true;
        $data['consecutive_failures'] = 0;
        $data['total_deliveries'] = 0;
        $data['successful_deliveries'] = 0;
        $data['headers'] = $data['headers'] ?? [];
        $data['timeout'] = $data['timeout'] ?? 30;
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Transforma dados do formato SDK para o formato da API do notification-service
     *
     * Formato SDK (antigo):
     * {
     *   "url": "https://...",
     *   "events": ["order.created", "payment.paid"],
     *   "secret": "secret",
     *   "description": "...",
     *   "organization_id": "..."
     * }
     *
     * Formato API (CreateWebhookConfigurationDto):
     * {
     *   "tenantId": "organization_id",  // ← IMPORTANT: tenantId expects organization_id!
     *   "name": "Webhook Configuration",
     *   "description": "...",
     *   "isActive": true,
     *   "endpoints": [
     *     {
     *       "eventType": "ORDER_CREATED",
     *       "url": "https://...",
     *       "secret": "secret",
     *       "isActive": true
     *     },
     *     {
     *       "eventType": "PAYMENT_PAID",
     *       "url": "https://...",
     *       "secret": "secret",
     *       "isActive": true
     *     }
     *   ]
     * }
     */
    private function transformToApiPayload(array $data): array
    {
        // FIXED: API expects organization_id as tenantId field
        // The naming is confusing but webhooks are stored with tenantId = organization_id
        $organizationId = $data['organization_id'] ?? $data['tenant_id'] ?? null;

        $this->logger->debug('Transforming webhook data to API payload', [
            'has_tenant_id' => isset($data['tenant_id']),
            'has_organization_id' => isset($data['organization_id']),
            'tenant_id_value' => $data['tenant_id'] ?? null,
            'organization_id_value' => $data['organization_id'] ?? null,
            'resolved_organization_id' => $organizationId
        ]);

        if (!$organizationId || $organizationId === false || $organizationId === '') {
            $this->logger->error('Missing or invalid organization_id for webhook creation', [
                'data_keys' => array_keys($data),
                'organization_id' => $organizationId,
                'tenant_id' => $data['tenant_id'] ?? null
            ]);
            throw WebhookException::invalidConfig('organization_id', 'Organization ID is required for webhook operations');
        }

        // Cria um endpoint para cada evento
        $events = $data['events'] ?? [];
        $endpoints = [];

        foreach ($events as $event) {
            // Mantém o evento no formato dot notation (ex: "order.created", "payment.paid")
            $endpoint = [
                'eventType' => $event,  // Usar o evento como está (dot notation)
                'url' => $data['url'],
                'secret' => $data['secret'] ?? $this->generateSecret(),
                'httpMethod' => 'POST',
                'isActive' => $data['active'] ?? true,
                'retryConfig' => [
                    'maxRetries' => 3,
                    'timeout' => ($data['timeout'] ?? 30) * 1000, // converter para ms
                    'backoffStrategy' => 'exponential'
                ]
            ];

            // Adiciona headers apenas se houver algum - caso contrário omite o campo
            // Isso evita que o PHP envie [] (array) quando a API espera {} (object)
            $headers = $data['headers'] ?? null;
            if ($headers && is_array($headers) && count($headers) > 0) {
                $endpoint['headers'] = $headers;
            }

            $endpoints[] = $endpoint;
        }

        // Gera nome baseado na URL e eventos
        $name = $data['description'] ?? 'Webhook Configuration for ' . parse_url($data['url'], PHP_URL_HOST);

        return [
            'tenantId' => $organizationId,  // FIXED: Use organization_id as tenantId
            'name' => $name,
            'description' => $data['description'] ?? '',
            'isActive' => $data['active'] ?? true,
            'endpoints' => $endpoints,
            'defaultTimeout' => ($data['timeout'] ?? 30) * 1000, // converter para ms
            'defaultMaxRetries' => 3,
            'signatureAlgorithm' => 'sha256',
            'verifySSL' => true
        ];
    }

    /**
     * Invalida cache relacionado aos webhooks
     */
    private function invalidateWebhookCache(string $organizationId = null, string $webhookId = null, string $tenantId = null): void
    {
        $patterns = [
            "webhooks:*",
            "webhook:stats:*",
        ];

        if ($organizationId) {
            $patterns[] = "webhooks:org:{$organizationId}";
        }

        // IMPORTANT: Also invalidate by tenant_id since that's what the API uses
        if ($tenantId) {
            $patterns[] = "webhook:tenant:{$tenantId}";
            $this->logger->debug('Adding tenant cache pattern for invalidation', [
                'tenant_id' => $tenantId
            ]);
        }

        if ($webhookId) {
            $patterns[] = "webhook:{$webhookId}";
        }

        $this->logger->debug('Invalidating webhook cache patterns', [
            'patterns' => $patterns,
            'organization_id' => $organizationId,
            'tenant_id' => $tenantId,
            'webhook_id' => $webhookId
        ]);

        foreach ($patterns as $pattern) {
            $this->clearCachePattern($pattern);
        }
    }

    /**
     * Gera ID único para webhook
     */
    private function generateWebhookId(): string
    {
        return 'wh_' . uniqid() . '_' . bin2hex(random_bytes(8));
    }

    /**
     * Valida campo individual
     */
    private function validateField(string $field, $value, array $rules): void
    {
        foreach ($rules as $rule) {
            switch ($rule) {
                case 'url':
                    if (!filter_var($value, FILTER_VALIDATE_URL)) {
                        throw new InvalidArgumentException("URL inválida: {$value}");
                    }
                    break;
                case 'https':
                    if (!str_starts_with($value, 'https://')) {
                        throw new InvalidArgumentException("URL deve usar HTTPS: {$value}");
                    }
                    break;
                case 'array':
                    if (!is_array($value)) {
                        throw new InvalidArgumentException("Campo {$field} deve ser um array");
                    }
                    break;
                case 'boolean':
                    if (!is_bool($value)) {
                        throw new InvalidArgumentException("Campo {$field} deve ser booleano");
                    }
                    break;
                case 'string':
                    if (!is_string($value)) {
                        throw new InvalidArgumentException("Campo {$field} deve ser string");
                    }
                    break;
            }

            if (str_starts_with($rule, 'min:')) {
                $min = (int) substr($rule, 4);
                if (strlen($value) < $min) {
                    throw new InvalidArgumentException("Campo {$field} deve ter pelo menos {$min} caracteres");
                }
            }

            if (str_starts_with($rule, 'max:')) {
                $max = (int) substr($rule, 4);
                if (strlen($value) > $max) {
                    throw new InvalidArgumentException("Campo {$field} deve ter no máximo {$max} caracteres");
                }
            }
        }
    }

    /**
     * Valida dados de atualização
     */
    private function validateUpdateData(array $data): void
    {
        foreach ($data as $field => $value) {
            if (isset($this->validationRules[$field])) {
                $rules = array_filter($this->validationRules[$field], fn ($rule) => $rule !== 'required');
                $this->validateField($field, $value, $rules);
            }
        }

        if (isset($data['events'])) {
            $this->validateEvents($data['events']);
        }
    }

    /**
     * Obtém diferenças entre webhooks
     */
    private function getChanges(array $old, array $new): array
    {
        $changes = [];

        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    /**
     * Create or update a webhook configuration
     *
     * If a configuration already exists for the organization_id, this method will
     * add the new events as endpoints to the existing configuration instead of
     * creating a duplicate (which would fail with 409 Conflict).
     *
     * @param array $webhookData Webhook configuration data
     * @return array The created or updated webhook configuration
     * @throws WebhookException
     */
    public function createOrUpdateWebhook(array $webhookData): array
    {
        try {
            $organizationId = $webhookData['organization_id'] ?? null;

            if (!$organizationId) {
                throw WebhookException::invalidConfig('organization_id', 'Organization ID is required');
            }

            // 1. Try to find existing configuration for this organization
            $existing = $this->getWebhookConfigByOrganization($organizationId);

            if ($existing) {
                $this->logger->info("Found existing webhook config for organization: {$organizationId}");

                // 2. Extract new endpoints from webhookData
                $newEndpoints = $this->extractEndpointsFromWebhookData($webhookData);

                // 3. Add new endpoints to existing configuration
                return $this->addEndpointsToConfig(
                    $existing['_id'],
                    $organizationId,
                    $existing['name'] ?? 'Default Configuration',
                    $newEndpoints
                );
            }

            // 4. If no existing config, create new one
            $this->logger->info("Creating new webhook configuration for organization: {$organizationId}");
            return $this->create($webhookData);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create or update webhook', [
                'error' => $e->getMessage(),
                'organization_id' => $organizationId ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * Extract endpoints array from webhook data format
     *
     * @param array $webhookData
     * @return array Array of endpoint objects
     */
    private function extractEndpointsFromWebhookData(array $webhookData): array
    {
        $endpoints = [];
        $events = $webhookData['events'] ?? [];
        $url = $webhookData['url'] ?? null;
        $secret = $webhookData['secret'] ?? $this->generateSecret();
        $timeout = $webhookData['timeout'] ?? 30;
        $maxRetries = $webhookData['max_retries'] ?? 3;
        $headers = $webhookData['headers'] ?? [];

        if (!$url) {
            throw WebhookException::invalidConfig('url', 'URL is required');
        }

        foreach ($events as $eventType) {
            $endpoints[] = [
                'eventType' => $eventType,
                'url' => $url,
                'secret' => $secret,
                'httpMethod' => 'POST',
                'isActive' => $webhookData['active'] ?? true,
                'headers' => $headers,
                'retryConfig' => [
                    'maxRetries' => $maxRetries,
                    'timeout' => $timeout * 1000, // Convert to ms
                    'backoffStrategy' => 'exponential'
                ]
            ];
        }

        return $endpoints;
    }

    /**
     * Add endpoints to an existing configuration
     *
     * @param string $configId Webhook configuration ID
     * @param string $organizationId Organization ID
     * @param string $configName Configuration name
     * @param array $newEndpoints Array of new endpoints to add
     * @return array Updated configuration
     */
    private function addEndpointsToConfig(
        string $configId,
        string $organizationId,
        string $configName,
        array $newEndpoints
    ): array {
        $addedCount = 0;

        foreach ($newEndpoints as $endpoint) {
            try {
                $this->repository->addEndpoint(
                    $organizationId,
                    $configName,
                    $endpoint
                );
                $addedCount++;

                $this->logger->info("Added endpoint for event: {$endpoint['eventType']}");
            } catch (\Exception $e) {
                // If endpoint already exists for this event, skip it
                if (strpos($e->getMessage(), 'duplicate') !== false ||
                    strpos($e->getMessage(), 'already exists') !== false) {
                    $this->logger->warning("Endpoint already exists for event: {$endpoint['eventType']}, skipping");
                    continue;
                }
                throw $e;
            }
        }

        $this->logger->info("Added {$addedCount} new endpoints to configuration: {$configName}");

        // Return updated configuration
        return $this->repository->findByOrganization($organizationId)[0] ?? [];
    }

    /**
     * Get webhook configuration by organization ID
     *
     * @param string $organizationId Organization ID
     * @return array|null Configuration or null if not found
     */
    public function getWebhookConfigByOrganization(string $organizationId): ?array
    {
        $configs = $this->repository->findByOrganization($organizationId);

        // Return first configuration (most common case)
        // TODO: In future, support multiple configs per organization
        return $configs[0] ?? null;
    }

    /**
     * Add a single endpoint to an existing webhook configuration
     *
     * @param string $organizationId Organization ID
     * @param string $configName Configuration name
     * @param string $eventType Event type
     * @param string $url Webhook URL
     * @param array $options Additional endpoint options
     * @return array Updated configuration
     */
    public function addEndpoint(
        string $organizationId,
        string $configName,
        string $eventType,
        string $url,
        array $options = []
    ): array {
        $endpointData = [
            'eventType' => $eventType,
            'url' => $url,
            'secret' => $options['secret'] ?? $this->generateSecret(),
            'httpMethod' => $options['http_method'] ?? 'POST',
            'isActive' => $options['active'] ?? true,
            'headers' => $options['headers'] ?? [],
            'retryConfig' => [
                'maxRetries' => $options['max_retries'] ?? 3,
                'timeout' => ($options['timeout'] ?? 30) * 1000,
                'backoffStrategy' => $options['backoff_strategy'] ?? 'exponential'
            ]
        ];

        return $this->repository->addEndpoint($organizationId, $configName, $endpointData);
    }

    /**
     * Remove an endpoint from a webhook configuration
     *
     * @param string $organizationId Organization ID
     * @param string $configName Configuration name
     * @param string $eventType Event type to remove
     * @return bool Success
     */
    public function removeEndpoint(
        string $organizationId,
        string $configName,
        string $eventType
    ): bool {
        return $this->repository->removeEndpoint($organizationId, $configName, $eventType);
    }

    /**
     * List all endpoints for a webhook configuration
     *
     * @param string $organizationId Organization ID
     * @param string $configName Configuration name (optional, defaults to first config)
     * @return array List of endpoints
     */
    public function listEndpoints(string $organizationId, string $configName = null): array
    {
        if (!$configName) {
            // Get first configuration for organization
            $config = $this->getWebhookConfigByOrganization($organizationId);
            $configName = $config['name'] ?? 'Default Configuration';
        }

        return $this->repository->listEndpoints($organizationId, $configName);
    }

    /**
     * Update an existing endpoint
     *
     * @param string $organizationId Organization ID
     * @param string $configName Configuration name
     * @param string $eventType Event type
     * @param array $updates Fields to update
     * @return array Updated configuration
     */
    public function updateEndpoint(
        string $organizationId,
        string $configName,
        string $eventType,
        array $updates
    ): array {
        // Transform updates to API format if needed
        if (isset($updates['timeout'])) {
            $updates['retryConfig']['timeout'] = $updates['timeout'] * 1000;
            unset($updates['timeout']);
        }

        return $this->repository->updateEndpoint($organizationId, $configName, $eventType, $updates);
    }
}
