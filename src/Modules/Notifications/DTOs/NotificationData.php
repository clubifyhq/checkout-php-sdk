<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para dados de notificação
 *
 * Representa uma notificação com todos os seus dados:
 * - Informações básicas da notificação
 * - Dados do destinatário
 * - Conteúdo da mensagem
 * - Status de entrega
 * - Metadados e configurações
 * - Controle de retry e falhas
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas dados de notificação
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationData extends BaseData
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $recipient,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $status,
        public readonly string $deliveryMethod,
        public readonly ?\DateTime $scheduledAt = null,
        public readonly ?\DateTime $sentAt = null,
        public readonly ?array $metadata = null,
        public readonly int $retryCount = 0,
        public readonly ?string $lastError = null,
        public readonly ?string $webhookId = null,
        public readonly ?array $headers = null,
        public readonly ?int $priority = 1,
        public readonly ?int $timeout = 30,
        public readonly ?array $retryPolicy = null,
        public readonly ?string $templateId = null,
        public readonly ?array $templateData = null,
        public readonly ?string $campaignId = null,
        public readonly ?array $trackingData = null,
        public readonly ?\DateTime $createdAt = null,
        public readonly ?\DateTime $updatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para dados de notificação
     */
    protected function rules(): array
    {
        return [
            'id' => 'required|string|min:1|max:100',
            'type' => 'required|string|min:1|max:50',
            'recipient' => 'required|string|min:1|max:255',
            'subject' => 'required|string|min:1|max:255',
            'body' => 'required|string|min:1',
            'status' => 'required|string|in:pending,sent,delivered,failed,cancelled',
            'deliveryMethod' => 'required|string|in:email,sms,webhook,push,slack',
            'retryCount' => 'integer|min:0|max:10',
            'priority' => 'integer|min:1|max:5',
            'timeout' => 'integer|min:1|max:300',
        ];
    }

    /**
     * Converte para array seguro (remove dados sensíveis)
     */
    public function toSafeArray(): array
    {
        $data = $this->toArray();

        // Remove dados sensíveis dos logs
        unset($data['headers']);
        unset($data['templateData']);

        // Reduz tamanho do body para logs
        if (isset($data['body']) && strlen($data['body']) > 200) {
            $data['body'] = substr($data['body'], 0, 200) . '...';
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
            'type' => $this->type,
            'recipient' => $this->recipient,
            'subject' => $this->subject,
            'body' => $this->body,
            'status' => $this->status,
            'delivery_method' => $this->deliveryMethod,
            'scheduled_at' => $this->scheduledAt?->format('Y-m-d H:i:s'),
            'sent_at' => $this->sentAt?->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
            'retry_count' => $this->retryCount,
            'last_error' => $this->lastError,
            'webhook_id' => $this->webhookId,
            'headers' => $this->headers,
            'priority' => $this->priority,
            'timeout' => $this->timeout,
            'retry_policy' => $this->retryPolicy,
            'template_id' => $this->templateId,
            'template_data' => $this->templateData,
            'campaign_id' => $this->campaignId,
            'tracking_data' => $this->trackingData,
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
            type: $data['type'] ?? '',
            recipient: $data['recipient'] ?? '',
            subject: $data['subject'] ?? '',
            body: $data['body'] ?? '',
            status: $data['status'] ?? 'pending',
            deliveryMethod: $data['delivery_method'] ?? 'email',
            scheduledAt: isset($data['scheduled_at']) ? new \DateTime($data['scheduled_at']) : null,
            sentAt: isset($data['sent_at']) ? new \DateTime($data['sent_at']) : null,
            metadata: $data['metadata'] ?? null,
            retryCount: (int)($data['retry_count'] ?? 0),
            lastError: $data['last_error'] ?? null,
            webhookId: $data['webhook_id'] ?? null,
            headers: $data['headers'] ?? null,
            priority: (int)($data['priority'] ?? 1),
            timeout: (int)($data['timeout'] ?? 30),
            retryPolicy: $data['retry_policy'] ?? null,
            templateId: $data['template_id'] ?? null,
            templateData: $data['template_data'] ?? null,
            campaignId: $data['campaign_id'] ?? null,
            trackingData: $data['tracking_data'] ?? null,
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }

    /**
     * Verifica se a notificação está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se a notificação foi enviada
     */
    public function isSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered']);
    }

    /**
     * Verifica se a notificação falhou
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Verifica se a notificação foi cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica se pode fazer retry
     */
    public function canRetry(): bool
    {
        return $this->isFailed() && $this->retryCount < 10;
    }

    /**
     * Verifica se é alta prioridade
     */
    public function isHighPriority(): bool
    {
        return $this->priority <= 2;
    }

    /**
     * Verifica se está agendada
     */
    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null && $this->scheduledAt > new \DateTime();
    }

    /**
     * Verifica se expirou o timeout
     */
    public function isTimedOut(): bool
    {
        if ($this->sentAt === null) {
            return false;
        }

        $expiredAt = clone $this->sentAt;
        $expiredAt->modify("+{$this->timeout} seconds");

        return new \DateTime() > $expiredAt;
    }

    /**
     * Obtém dados para retry
     */
    public function getRetryData(): array
    {
        return [
            'notification_id' => $this->id,
            'retry_count' => $this->retryCount + 1,
            'last_error' => $this->lastError,
            'retry_policy' => $this->retryPolicy,
            'original_data' => $this->toSafeArray()
        ];
    }

    /**
     * Obtém dados de tracking
     */
    public function getTrackingData(): array
    {
        return array_merge(
            $this->trackingData ?? [],
            [
                'notification_id' => $this->id,
                'type' => $this->type,
                'delivery_method' => $this->deliveryMethod,
                'status' => $this->status,
                'retry_count' => $this->retryCount,
                'priority' => $this->priority,
                'campaign_id' => $this->campaignId
            ]
        );
    }

    /**
     * Valida e-mail do destinatário
     */
    public function hasValidEmailRecipient(): bool
    {
        if ($this->deliveryMethod !== 'email') {
            return true;
        }

        return filter_var($this->recipient, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida telefone do destinatário
     */
    public function hasValidPhoneRecipient(): bool
    {
        if ($this->deliveryMethod !== 'sms') {
            return true;
        }

        // Básico para telefones brasileiros e internacionais
        return preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $this->recipient) === 1;
    }

    /**
     * Valida URL do webhook
     */
    public function hasValidWebhookRecipient(): bool
    {
        if ($this->deliveryMethod !== 'webhook') {
            return true;
        }

        return filter_var($this->recipient, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validação completa do destinatário
     */
    public function hasValidRecipient(): bool
    {
        return $this->hasValidEmailRecipient()
            && $this->hasValidPhoneRecipient()
            && $this->hasValidWebhookRecipient();
    }
}