<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Pedido
 *
 * Representa os dados de um pedido no sistema.
 * Inclui informações do cliente, itens, pagamento, entrega e status.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de pedido
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $order_number = null;
    public ?string $customer_id = null;
    public ?array $customer = null;
    public ?array $items = null;
    public ?float $subtotal = null;
    public ?float $tax_amount = null;
    public ?float $shipping_amount = null;
    public ?float $discount_amount = null;
    public ?float $total_amount = null;
    public ?string $currency = null;
    public ?string $status = null;
    public ?string $payment_status = null;
    public ?string $fulfillment_status = null;
    public ?array $payment_details = null;
    public ?array $shipping_address = null;
    public ?array $billing_address = null;
    public ?array $shipping_method = null;
    public ?array $discounts = null;
    public ?array $coupons = null;
    public ?array $upsells = null;
    public ?array $tracking_info = null;
    public ?array $notes = null;
    public ?array $metadata = null;
    public ?array $status_history = null;
    public ?string $source = null;
    public ?string $channel = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;
    public ?string $completed_at = null;
    public ?string $cancelled_at = null;
    public ?array $cancellation_reason = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'organization_id' => ['required', 'string', ['min', 1]],
            'customer_id' => ['required', 'string', ['min', 1]],
            'items' => ['required', 'array', ['min', 1]],
            'subtotal' => ['required', 'numeric', ['min', 0]],
            'tax_amount' => ['numeric', ['min', 0]],
            'shipping_amount' => ['numeric', ['min', 0]],
            'discount_amount' => ['numeric', ['min', 0]],
            'total_amount' => ['required', 'numeric', ['min', 0]],
            'currency' => ['required', 'string', ['in', ['BRL', 'USD', 'EUR', 'GBP']]],
            'status' => ['required', 'string', ['in', ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded']]],
            'payment_status' => ['required', 'string', ['in', ['pending', 'authorized', 'paid', 'partially_paid', 'failed', 'cancelled', 'refunded']]],
            'fulfillment_status' => ['string', ['in', ['pending', 'processing', 'shipped', 'partially_shipped', 'delivered', 'cancelled']]],
            'payment_details' => ['array'],
            'shipping_address' => ['array'],
            'billing_address' => ['array'],
            'shipping_method' => ['array'],
            'discounts' => ['array'],
            'coupons' => ['array'],
            'upsells' => ['array'],
            'tracking_info' => ['array'],
            'notes' => ['array'],
            'metadata' => ['array'],
            'source' => ['string', ['in', ['web', 'mobile', 'api', 'admin']]],
            'channel' => ['string'],
            'created_at' => ['date'],
            'updated_at' => ['date'],
            'completed_at' => ['date'],
            'cancelled_at' => ['date']
        ];
    }

    /**
     * Obtém total formatado
     */
    public function getFormattedTotal(string $currency = null): string
    {
        $currency = $currency ?? $this->currency ?? 'BRL';
        $total = $this->total_amount ?? 0;

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($total, 2, ',', '.'),
            'USD' => '$' . number_format($total, 2, '.', ','),
            'EUR' => '€' . number_format($total, 2, ',', '.'),
            'GBP' => '£' . number_format($total, 2, '.', ','),
            default => number_format($total, 2, '.', ',')
        };
    }

    /**
     * Obtém subtotal formatado
     */
    public function getFormattedSubtotal(string $currency = null): string
    {
        $currency = $currency ?? $this->currency ?? 'BRL';
        $subtotal = $this->subtotal ?? 0;

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($subtotal, 2, ',', '.'),
            'USD' => '$' . number_format($subtotal, 2, '.', ','),
            'EUR' => '€' . number_format($subtotal, 2, ',', '.'),
            'GBP' => '£' . number_format($subtotal, 2, '.', ','),
            default => number_format($subtotal, 2, '.', ',')
        };
    }

    /**
     * Verifica se pedido está pendente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica se pedido está em processamento
     */
    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Verifica se pedido foi enviado
     */
    public function isShipped(): bool
    {
        return $this->status === 'shipped';
    }

    /**
     * Verifica se pedido foi entregue
     */
    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Verifica se pedido foi cancelado
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica se pedido foi reembolsado
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    /**
     * Verifica se pedido está completo
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['delivered', 'completed']);
    }

    /**
     * Verifica se pagamento está pendente
     */
    public function isPaymentPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Verifica se pagamento foi autorizado
     */
    public function isPaymentAuthorized(): bool
    {
        return $this->payment_status === 'authorized';
    }

    /**
     * Verifica se pagamento foi realizado
     */
    public function isPaymentPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Verifica se pagamento falhou
     */
    public function isPaymentFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Obtém quantidade total de itens
     */
    public function getTotalQuantity(): int
    {
        if (!is_array($this->items)) {
            return 0;
        }

        return array_sum(array_column($this->items, 'quantity'));
    }

    /**
     * Obtém itens do pedido
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * Adiciona item ao pedido
     */
    public function addItem(array $item): self
    {
        if (!is_array($this->items)) {
            $this->items = [];
        }

        $this->items[] = $item;
        $this->data['items'] = $this->items;

        return $this;
    }

    /**
     * Remove item do pedido
     */
    public function removeItem(string $itemId): self
    {
        if (is_array($this->items)) {
            $this->items = array_filter($this->items, fn($item) => $item['id'] !== $itemId);
            $this->items = array_values($this->items);
            $this->data['items'] = $this->items;
        }

        return $this;
    }

    /**
     * Obtém descontos aplicados
     */
    public function getDiscounts(): array
    {
        return $this->discounts ?? [];
    }

    /**
     * Obtém cupons aplicados
     */
    public function getCoupons(): array
    {
        return $this->coupons ?? [];
    }

    /**
     * Obtém upsells do pedido
     */
    public function getUpsells(): array
    {
        return $this->upsells ?? [];
    }

    /**
     * Verifica se tem upsells
     */
    public function hasUpsells(): bool
    {
        return !empty($this->getUpsells());
    }

    /**
     * Adiciona upsell ao pedido
     */
    public function addUpsell(array $upsell): self
    {
        if (!is_array($this->upsells)) {
            $this->upsells = [];
        }

        $this->upsells[] = $upsell;
        $this->data['upsells'] = $this->upsells;

        return $this;
    }

    /**
     * Remove upsell do pedido
     */
    public function removeUpsell(string $upsellId): self
    {
        if (is_array($this->upsells)) {
            $this->upsells = array_filter($this->upsells, fn($upsell) => $upsell['id'] !== $upsellId);
            $this->upsells = array_values($this->upsells);
            $this->data['upsells'] = $this->upsells;
        }

        return $this;
    }

    /**
     * Obtém endereço de entrega
     */
    public function getShippingAddress(): ?array
    {
        return $this->shipping_address;
    }

    /**
     * Obtém endereço de cobrança
     */
    public function getBillingAddress(): ?array
    {
        return $this->billing_address;
    }

    /**
     * Obtém método de envio
     */
    public function getShippingMethod(): ?array
    {
        return $this->shipping_method;
    }

    /**
     * Obtém detalhes do pagamento
     */
    public function getPaymentDetails(): ?array
    {
        return $this->payment_details;
    }

    /**
     * Obtém informações de rastreamento
     */
    public function getTrackingInfo(): ?array
    {
        return $this->tracking_info;
    }

    /**
     * Obtém notas do pedido
     */
    public function getNotes(): array
    {
        return $this->notes ?? [];
    }

    /**
     * Adiciona nota ao pedido
     */
    public function addNote(string $note, string $author = 'system'): self
    {
        if (!is_array($this->notes)) {
            $this->notes = [];
        }

        $this->notes[] = [
            'note' => $note,
            'author' => $author,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->data['notes'] = $this->notes;

        return $this;
    }

    /**
     * Obtém histórico de status
     */
    public function getStatusHistory(): array
    {
        return $this->status_history ?? [];
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
    public function getMetadata(string $key, mixed $default = null): mixed
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
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        if (!$this->subtotal || !$this->discount_amount) {
            return 0;
        }

        return round(($this->discount_amount / $this->subtotal) * 100, 2);
    }

    /**
     * Obtém dados para exibição resumida
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'total_amount' => $this->total_amount,
            'formatted_total' => $this->getFormattedTotal(),
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'items_count' => count($this->getItems()),
            'total_quantity' => $this->getTotalQuantity(),
            'has_upsells' => $this->hasUpsells(),
            'created_at' => $this->created_at
        ];
    }

    /**
     * Obtém dados para administração
     */
    public function toAdmin(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'order_number' => $this->order_number,
            'customer_id' => $this->customer_id,
            'customer' => $this->customer,
            'items' => $this->getItems(),
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'formatted_total' => $this->getFormattedTotal(),
            'currency' => $this->currency,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'fulfillment_status' => $this->fulfillment_status,
            'payment_details' => $this->getPaymentDetails(),
            'shipping_address' => $this->getShippingAddress(),
            'billing_address' => $this->getBillingAddress(),
            'shipping_method' => $this->getShippingMethod(),
            'discounts' => $this->getDiscounts(),
            'coupons' => $this->getCoupons(),
            'upsells' => $this->getUpsells(),
            'tracking_info' => $this->getTrackingInfo(),
            'notes' => $this->getNotes(),
            'metadata' => $this->getMetadata(),
            'status_history' => $this->getStatusHistory(),
            'source' => $this->source,
            'channel' => $this->channel,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $organizationId,
        string $customerId,
        array $items,
        float $totalAmount,
        string $currency = 'BRL',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'customer_id' => $customerId,
            'items' => $items,
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'status' => 'pending',
            'payment_status' => 'pending',
            'fulfillment_status' => 'pending',
            'source' => 'api',
            'items' => [],
            'discounts' => [],
            'coupons' => [],
            'upsells' => [],
            'notes' => [],
            'metadata' => []
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