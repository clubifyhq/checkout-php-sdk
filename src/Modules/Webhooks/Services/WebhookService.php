<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\Services;

use ClubifyCheckout\Services\BaseService;
use Clubify\Checkout\Modules\Webhooks\Repositories\WebhookRepositoryInterface;
use Clubify\Checkout\Modules\Webhooks\DTOs\WebhookData;
use Clubify\Checkout\Modules\Webhooks\Exceptions\WebhookNotFoundException;
use Clubify\Checkout\Modules\Webhooks\Exceptions\InvalidWebhookException;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
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
class WebhookService extends BaseService
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
        'order.created',
        'order.updated',
        'order.completed',
        'order.cancelled',
        'payment.completed',
        'payment.failed',
        'customer.created',
        'customer.updated',
        'product.created',
        'product.updated',
        'checkout.abandoned',
        'subscription.created',
        'subscription.cancelled',
    ];

    public function __construct(
        private WebhookRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache = null
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Cria novo webhook
     */
    public function create(array $webhookData): array
    {
        return $this->executeWithMetrics('create', function () use ($webhookData) {
            // Valida dados
            $this->validateWebhookData($webhookData);

            // Verifica limite por organização
            $this->validateOrganizationLimit($webhookData['organization_id'] ?? null);

            // Verifica se URL já existe
            $this->validateUniqueUrl($webhookData['url']);

            // Valida conectividade da URL
            $this->validateUrlConnectivity($webhookData['url']);

            // Sanitiza dados
            $sanitizedData = $this->sanitizeWebhookData($webhookData);

            // Gera secret se não fornecido
            if (empty($sanitizedData['secret'])) {
                $sanitizedData['secret'] = $this->generateSecret();
            }

            // Adiciona metadados padrão
            $sanitizedData = $this->addDefaultMetadata($sanitizedData);

            // Cria webhook
            $webhook = $this->repository->create($sanitizedData);

            // Dispara evento
            $this->dispatchEvent('webhook.created', $webhook);

            // Invalida cache
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null);

            $this->logger->info('Webhook criado com sucesso', [
                'webhook_id' => $webhook['id'],
                'url' => $webhook['url'],
                'events' => $webhook['events'],
            ]);

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
                throw new WebhookNotFoundException("Webhook não encontrado: {$webhookId}");
            }

            // Valida dados de atualização
            $this->validateUpdateData($updateData);

            // Verifica URL se mudou
            if (isset($updateData['url']) && $updateData['url'] !== $existingWebhook['url']) {
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
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);

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
                throw new WebhookNotFoundException("Webhook não encontrado: {$webhookId}");
            }

            // Remove webhook
            $result = $this->repository->delete($webhookId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('webhook.deleted', $webhook);

                // Invalida cache
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);

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
                throw new WebhookNotFoundException("Webhook não encontrado: {$webhookId}");
            }

            if ($webhook['active']) {
                return true; // Já está ativo
            }

            // Valida conectividade antes de ativar
            $this->validateUrlConnectivity($webhook['url']);

            // Reseta contador de falhas
            $this->repository->resetFailureCount($webhookId);

            // Ativa webhook
            $result = $this->repository->activate($webhookId);

            if ($result) {
                // Dispara evento
                $this->dispatchEvent('webhook.activated', $webhook);

                // Invalida cache
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);

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
                throw new WebhookNotFoundException("Webhook não encontrado: {$webhookId}");
            }

            if (!$webhook['active']) {
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
                $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);

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
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);

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
            $this->invalidateWebhookCache($webhook['organization_id'] ?? null, $webhookId);
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

        if ($existing && $existing['id'] !== $excludeId) {
            throw new InvalidWebhookException("Já existe um webhook com esta URL: {$url}");
        }
    }

    /**
     * Valida conectividade da URL
     */
    private function validateUrlConnectivity(string $url): void
    {
        $validation = $this->repository->validateUrl($url);

        if (!$validation['accessible']) {
            throw new InvalidWebhookException("URL não acessível: {$validation['error']}");
        }

        if ($validation['response_code'] < 200 || $validation['response_code'] >= 300) {
            throw new InvalidWebhookException("URL retornou código HTTP inválido: {$validation['response_code']}");
        }
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
            throw new InvalidWebhookException("Limite de webhooks por organização excedido ({$count}/" . self::MAX_WEBHOOKS_PER_ORG . ")");
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
     * Invalida cache relacionado aos webhooks
     */
    private function invalidateWebhookCache(string $organizationId = null, string $webhookId = null): void
    {
        $patterns = [
            "webhooks:*",
            "webhook:stats:*",
        ];

        if ($organizationId) {
            $patterns[] = "webhooks:org:{$organizationId}";
        }

        if ($webhookId) {
            $patterns[] = "webhook:{$webhookId}";
        }

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
}
