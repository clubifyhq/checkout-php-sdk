<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Status do Pedido
 *
 * Representa os dados de um status específico no histórico do pedido.
 * Inclui informações do status, timestamp, usuário responsável e metadados.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de status do pedido
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderStatusData extends BaseData
{
    public ?string $id = null;
    public ?string $order_id = null;
    public ?string $status = null;
    public ?string $previous_status = null;
    public ?string $reason = null;
    public ?string $comment = null;
    public ?string $changed_by = null;
    public ?string $changed_by_type = null; // 'user', 'system', 'webhook', 'api'
    public ?array $metadata = null;
    public ?bool $notify_customer = null;
    public ?string $notification_sent_at = null;
    public ?array $external_data = null; // Dados de sistemas externos (correios, etc)
    public ?string $created_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'order_id' => ['required', 'string', ['min', 1]],
            'status' => ['required', 'string', ['in', [
                'pending', 'processing', 'shipped', 'delivered',
                'cancelled', 'refunded', 'partially_shipped',
                'partially_refunded', 'returned', 'exchanged'
            ]]],
            'previous_status' => ['string'],
            'reason' => ['string', ['max', 500]],
            'comment' => ['string', ['max', 1000]],
            'changed_by' => ['string'],
            'changed_by_type' => ['string', ['in', ['user', 'system', 'webhook', 'api', 'customer']]],
            'metadata' => ['array'],
            'notify_customer' => ['boolean'],
            'notification_sent_at' => ['date'],
            'external_data' => ['array'],
            'created_at' => ['required', 'date']
        ];
    }

    /**
     * Obtém status formatado para exibição
     */
    public function getFormattedStatus(): string
    {
        return match ($this->status) {
            'pending' => 'Pendente',
            'processing' => 'Processando',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'partially_shipped' => 'Parcialmente Enviado',
            'partially_refunded' => 'Parcialmente Reembolsado',
            'returned' => 'Devolvido',
            'exchanged' => 'Trocado',
            default => ucfirst($this->status ?? '')
        };
    }

    /**
     * Obtém cor do status para interface
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'processing' => 'blue',
            'shipped' => 'purple',
            'delivered' => 'green',
            'cancelled' => 'red',
            'refunded' => 'orange',
            'partially_shipped' => 'indigo',
            'partially_refunded' => 'orange',
            'returned' => 'gray',
            'exchanged' => 'teal',
            default => 'gray'
        };
    }

    /**
     * Obtém ícone do status
     */
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'pending' => 'clock',
            'processing' => 'cog',
            'shipped' => 'truck',
            'delivered' => 'check-circle',
            'cancelled' => 'x-circle',
            'refunded' => 'arrow-left',
            'partially_shipped' => 'truck',
            'partially_refunded' => 'arrow-left',
            'returned' => 'arrow-right',
            'exchanged' => 'refresh',
            default => 'circle'
        };
    }

    /**
     * Verifica se é status final
     */
    public function isFinalStatus(): bool
    {
        return in_array($this->status, [
            'delivered', 'cancelled', 'refunded', 'returned', 'exchanged'
        ]);
    }

    /**
     * Verifica se é status de sucesso
     */
    public function isSuccessStatus(): bool
    {
        return in_array($this->status, ['delivered', 'exchanged']);
    }

    /**
     * Verifica se é status de erro/problema
     */
    public function isErrorStatus(): bool
    {
        return in_array($this->status, ['cancelled', 'refunded', 'returned']);
    }

    /**
     * Verifica se é status em andamento
     */
    public function isInProgressStatus(): bool
    {
        return in_array($this->status, ['processing', 'shipped', 'partially_shipped']);
    }

    /**
     * Verifica se mudança foi feita pelo sistema
     */
    public function isSystemChange(): bool
    {
        return $this->changed_by_type === 'system';
    }

    /**
     * Verifica se mudança foi feita por usuário
     */
    public function isUserChange(): bool
    {
        return $this->changed_by_type === 'user';
    }

    /**
     * Verifica se mudança foi feita via API
     */
    public function isApiChange(): bool
    {
        return $this->changed_by_type === 'api';
    }

    /**
     * Verifica se mudança foi feita via webhook
     */
    public function isWebhookChange(): bool
    {
        return $this->changed_by_type === 'webhook';
    }

    /**
     * Verifica se cliente deve ser notificado
     */
    public function shouldNotifyCustomer(): bool
    {
        return $this->notify_customer === true;
    }

    /**
     * Verifica se notificação foi enviada
     */
    public function wasNotificationSent(): bool
    {
        return !empty($this->notification_sent_at);
    }

    /**
     * Obtém metadados
     */
    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Obtém metadado específico
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Define metadado
     */
    public function setMetadata(string $key, mixed $value): self
    {
        if (!is_array($this->metadata)) {
            $this->metadata = [];
        }

        $this->metadata[$key] = $value;
        $this->data['metadata'] = $this->metadata;

        return $this;
    }

    /**
     * Obtém dados externos
     */
    public function getExternalData(): array
    {
        return $this->external_data ?? [];
    }

    /**
     * Obtém dado externo específico
     */
    public function getExternalDataValue(string $key, mixed $default = null): mixed
    {
        return $this->external_data[$key] ?? $default;
    }

    /**
     * Define dado externo
     */
    public function setExternalData(string $key, mixed $value): self
    {
        if (!is_array($this->external_data)) {
            $this->external_data = [];
        }

        $this->external_data[$key] = $value;
        $this->data['external_data'] = $this->external_data;

        return $this;
    }

    /**
     * Obtém código de rastreamento dos dados externos
     */
    public function getTrackingCode(): ?string
    {
        return $this->getExternalDataValue('tracking_code');
    }

    /**
     * Obtém transportadora dos dados externos
     */
    public function getCarrier(): ?string
    {
        return $this->getExternalDataValue('carrier');
    }

    /**
     * Obtém URL de rastreamento
     */
    public function getTrackingUrl(): ?string
    {
        return $this->getExternalDataValue('tracking_url');
    }

    /**
     * Obtém estimativa de entrega
     */
    public function getEstimatedDelivery(): ?string
    {
        return $this->getExternalDataValue('estimated_delivery');
    }

    /**
     * Obtém tempo desde a mudança de status
     */
    public function getTimeAgo(): string
    {
        if (!$this->created_at) {
            return '';
        }

        $now = new \DateTime();
        $statusTime = new \DateTime($this->created_at);
        $diff = $now->diff($statusTime);

        if ($diff->days > 0) {
            return $diff->days . ' dia(s) atrás';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hora(s) atrás';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minuto(s) atrás';
        } else {
            return 'Agora mesmo';
        }
    }

    /**
     * Obtém data formatada
     */
    public function getFormattedDate(string $format = 'd/m/Y H:i'): string
    {
        if (!$this->created_at) {
            return '';
        }

        return date($format, strtotime($this->created_at));
    }

    /**
     * Obtém descrição da mudança
     */
    public function getChangeDescription(): string
    {
        $description = "Status alterado para: {$this->getFormattedStatus()}";

        if ($this->previous_status) {
            $previousFormatted = match ($this->previous_status) {
                'pending' => 'Pendente',
                'processing' => 'Processando',
                'shipped' => 'Enviado',
                'delivered' => 'Entregue',
                'cancelled' => 'Cancelado',
                'refunded' => 'Reembolsado',
                default => ucfirst($this->previous_status)
            };
            $description = "Status alterado de '{$previousFormatted}' para '{$this->getFormattedStatus()}'";
        }

        if ($this->reason) {
            $description .= " - Motivo: {$this->reason}";
        }

        return $description;
    }

    /**
     * Obtém dados para timeline
     */
    public function toTimeline(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'formatted_status' => $this->getFormattedStatus(),
            'status_color' => $this->getStatusColor(),
            'status_icon' => $this->getStatusIcon(),
            'description' => $this->getChangeDescription(),
            'reason' => $this->reason,
            'comment' => $this->comment,
            'changed_by' => $this->changed_by,
            'changed_by_type' => $this->changed_by_type,
            'is_final' => $this->isFinalStatus(),
            'is_success' => $this->isSuccessStatus(),
            'is_error' => $this->isErrorStatus(),
            'is_in_progress' => $this->isInProgressStatus(),
            'tracking_code' => $this->getTrackingCode(),
            'carrier' => $this->getCarrier(),
            'tracking_url' => $this->getTrackingUrl(),
            'time_ago' => $this->getTimeAgo(),
            'formatted_date' => $this->getFormattedDate(),
            'created_at' => $this->created_at
        ];
    }

    /**
     * Obtém dados completos
     */
    public function toFull(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'status' => $this->status,
            'previous_status' => $this->previous_status,
            'formatted_status' => $this->getFormattedStatus(),
            'status_color' => $this->getStatusColor(),
            'status_icon' => $this->getStatusIcon(),
            'reason' => $this->reason,
            'comment' => $this->comment,
            'changed_by' => $this->changed_by,
            'changed_by_type' => $this->changed_by_type,
            'metadata' => $this->getMetadata(),
            'notify_customer' => $this->notify_customer,
            'notification_sent_at' => $this->notification_sent_at,
            'was_notification_sent' => $this->wasNotificationSent(),
            'external_data' => $this->getExternalData(),
            'tracking_code' => $this->getTrackingCode(),
            'carrier' => $this->getCarrier(),
            'tracking_url' => $this->getTrackingUrl(),
            'estimated_delivery' => $this->getEstimatedDelivery(),
            'is_final_status' => $this->isFinalStatus(),
            'is_success_status' => $this->isSuccessStatus(),
            'is_error_status' => $this->isErrorStatus(),
            'is_in_progress_status' => $this->isInProgressStatus(),
            'is_system_change' => $this->isSystemChange(),
            'is_user_change' => $this->isUserChange(),
            'is_api_change' => $this->isApiChange(),
            'is_webhook_change' => $this->isWebhookChange(),
            'change_description' => $this->getChangeDescription(),
            'time_ago' => $this->getTimeAgo(),
            'formatted_date' => $this->getFormattedDate(),
            'created_at' => $this->created_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $orderId,
        string $status,
        string $changedBy = 'system',
        string $changedByType = 'system',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'order_id' => $orderId,
            'status' => $status,
            'changed_by' => $changedBy,
            'changed_by_type' => $changedByType,
            'notify_customer' => false,
            'metadata' => [],
            'external_data' => [],
            'created_at' => date('Y-m-d H:i:s')
        ], $additionalData));
    }

    /**
     * Cria instância a partir de dados da API
     */
    public static function fromApi(array $apiData): self
    {
        return new self($apiData);
    }
}
