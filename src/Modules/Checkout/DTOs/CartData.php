<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Carrinho
 *
 * Representa um carrinho de compras com itens,
 * totais, cupons e configurações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de carrinho
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class CartData extends BaseData
{
    public ?string $id = null;
    public ?string $session_id = null;
    public ?string $customer_id = null;
    public ?string $organization_id = null;
    public ?string $type = null;
    public ?string $status = null;
    public ?array $items = null;
    public ?array $totals = null;
    public ?array $coupon = null;
    public ?array $shipping_data = null;
    public ?array $billing_data = null;
    public ?array $taxes = null;
    public ?array $fees = null;
    public ?array $discounts = null;
    public ?string $currency = null;
    public ?array $metadata = null;
    public ?string $order_id = null;
    public ?string $expires_at = null;
    public ?string $abandoned_at = null;
    public ?string $converted_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'session_id' => ['required', 'string', ['min', 1]],
            'customer_id' => ['string'],
            'organization_id' => ['string'],
            'type' => ['string', ['in', ['standard', 'one_click', 'subscription', 'recurring']]],
            'status' => ['string', ['in', ['active', 'processing', 'completed', 'abandoned', 'expired']]],
            'items' => ['array'],
            'totals' => ['array'],
            'coupon' => ['array'],
            'shipping_data' => ['array'],
            'billing_data' => ['array'],
            'taxes' => ['array'],
            'fees' => ['array'],
            'discounts' => ['array'],
            'currency' => ['string', ['in', ['BRL', 'USD', 'EUR', 'ARS', 'CLP', 'PEN', 'COP', 'MXN']]],
            'metadata' => ['array'],
            'order_id' => ['string'],
            'expires_at' => ['date'],
            'abandoned_at' => ['date'],
            'converted_at' => ['date'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém itens do carrinho
     */
    public function getItems(): array
    {
        return $this->items ?? [];
    }

    /**
     * Adiciona item ao carrinho
     */
    public function addItem(array $item): self
    {
        if (!is_array($this->items)) {
            $this->items = [];
        }

        // Verifica se item já existe
        foreach ($this->items as &$existingItem) {
            if ($existingItem['product_id'] === $item['product_id']) {
                $existingItem['quantity'] += $item['quantity'] ?? 1;
                $this->data['items'] = $this->items;
                return $this;
            }
        }

        // Adiciona novo item
        $item['id'] = $item['id'] ?? uniqid('item_');
        $item['added_at'] = date('Y-m-d H:i:s');
        $this->items[] = $item;
        $this->data['items'] = $this->items;

        return $this;
    }

    /**
     * Remove item do carrinho
     */
    public function removeItem(string $itemId): self
    {
        if (is_array($this->items)) {
            $this->items = array_filter(
                $this->items,
                fn ($item) => $item['id'] !== $itemId
            );
            $this->items = array_values($this->items);
            $this->data['items'] = $this->items;
        }

        return $this;
    }

    /**
     * Atualiza item do carrinho
     */
    public function updateItem(string $itemId, array $updates): self
    {
        if (is_array($this->items)) {
            foreach ($this->items as &$item) {
                if ($item['id'] === $itemId) {
                    $item = array_merge($item, $updates);
                    break;
                }
            }
            $this->data['items'] = $this->items;
        }

        return $this;
    }

    /**
     * Obtém item específico
     */
    public function getItem(string $itemId): ?array
    {
        foreach ($this->getItems() as $item) {
            if ($item['id'] === $itemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Conta itens no carrinho
     */
    public function getItemCount(): int
    {
        return count($this->getItems());
    }

    /**
     * Conta quantidade total de produtos
     */
    public function getTotalQuantity(): int
    {
        return array_sum(array_column($this->getItems(), 'quantity'));
    }

    /**
     * Limpa itens do carrinho
     */
    public function clearItems(): self
    {
        $this->items = [];
        $this->data['items'] = [];
        return $this;
    }

    /**
     * Verifica se carrinho está vazio
     */
    public function isEmpty(): bool
    {
        return $this->getItemCount() === 0;
    }

    /**
     * Obtém totais do carrinho
     */
    public function getTotals(): array
    {
        return $this->totals ?? [
            'subtotal' => 0.0,
            'discount' => 0.0,
            'taxes' => 0.0,
            'shipping' => 0.0,
            'fees' => 0.0,
            'total' => 0.0
        ];
    }

    /**
     * Define totais do carrinho
     */
    public function setTotals(array $totals): self
    {
        $this->totals = $totals;
        $this->data['totals'] = $totals;
        return $this;
    }

    /**
     * Obtém subtotal
     */
    public function getSubtotal(): float
    {
        return $this->totals['subtotal'] ?? 0.0;
    }

    /**
     * Obtém desconto total
     */
    public function getDiscount(): float
    {
        return $this->totals['discount'] ?? 0.0;
    }

    /**
     * Obtém total de taxas
     */
    public function getTaxes(): float
    {
        return $this->totals['taxes'] ?? 0.0;
    }

    /**
     * Obtém valor do frete
     */
    public function getShipping(): float
    {
        return $this->totals['shipping'] ?? 0.0;
    }

    /**
     * Obtém total de taxas adicionais
     */
    public function getFees(): float
    {
        return $this->totals['fees'] ?? 0.0;
    }

    /**
     * Obtém total geral
     */
    public function getTotal(): float
    {
        return $this->totals['total'] ?? 0.0;
    }

    /**
     * Obtém moeda
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'BRL';
    }

    /**
     * Obtém total formatado
     */
    public function getFormattedTotal(): string
    {
        return $this->formatCurrency($this->getTotal());
    }

    /**
     * Obtém subtotal formatado
     */
    public function getFormattedSubtotal(): string
    {
        return $this->formatCurrency($this->getSubtotal());
    }

    /**
     * Formata valor na moeda
     */
    public function formatCurrency(float $amount): string
    {
        $currency = $this->getCurrency();

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($amount, 2, ',', '.'),
            'USD' => '$' . number_format($amount, 2, '.', ','),
            'EUR' => '€' . number_format($amount, 2, ',', '.'),
            'ARS' => 'AR$ ' . number_format($amount, 2, ',', '.'),
            'CLP' => 'CL$ ' . number_format($amount, 0, ',', '.'),
            'PEN' => 'S/ ' . number_format($amount, 2, '.', ','),
            'COP' => 'CO$ ' . number_format($amount, 0, ',', '.'),
            'MXN' => 'MX$ ' . number_format($amount, 2, '.', ','),
            default => number_format($amount, 2, '.', ',')
        };
    }

    /**
     * Obtém cupom aplicado
     */
    public function getCoupon(): ?array
    {
        return $this->coupon;
    }

    /**
     * Define cupom
     */
    public function setCoupon(array $coupon): self
    {
        $this->coupon = $coupon;
        $this->data['coupon'] = $coupon;
        return $this;
    }

    /**
     * Remove cupom
     */
    public function removeCoupon(): self
    {
        $this->coupon = null;
        $this->data['coupon'] = null;
        return $this;
    }

    /**
     * Verifica se tem cupom aplicado
     */
    public function hasCoupon(): bool
    {
        return $this->coupon !== null;
    }

    /**
     * Obtém código do cupom
     */
    public function getCouponCode(): ?string
    {
        return $this->coupon['code'] ?? null;
    }

    /**
     * Obtém desconto do cupom
     */
    public function getCouponDiscount(): float
    {
        return $this->coupon['discount_amount'] ?? 0.0;
    }

    /**
     * Obtém dados de envio
     */
    public function getShippingData(): array
    {
        return $this->shipping_data ?? [];
    }

    /**
     * Define dados de envio
     */
    public function setShippingData(array $shippingData): self
    {
        $this->shipping_data = $shippingData;
        $this->data['shipping_data'] = $shippingData;
        return $this;
    }

    /**
     * Obtém endereço de envio
     */
    public function getShippingAddress(): array
    {
        return $this->shipping_data['address'] ?? [];
    }

    /**
     * Obtém método de envio
     */
    public function getShippingMethod(): ?string
    {
        return $this->shipping_data['method'] ?? null;
    }

    /**
     * Obtém dados de cobrança
     */
    public function getBillingData(): array
    {
        return $this->billing_data ?? [];
    }

    /**
     * Define dados de cobrança
     */
    public function setBillingData(array $billingData): self
    {
        $this->billing_data = $billingData;
        $this->data['billing_data'] = $billingData;
        return $this;
    }

    /**
     * Obtém endereço de cobrança
     */
    public function getBillingAddress(): array
    {
        return $this->billing_data['address'] ?? [];
    }

    /**
     * Obtém configurações de taxa
     */
    public function getTaxConfig(): array
    {
        return $this->taxes ?? [];
    }

    /**
     * Define configurações de taxa
     */
    public function setTaxConfig(array $taxes): self
    {
        $this->taxes = $taxes;
        $this->data['taxes'] = $taxes;
        return $this;
    }

    /**
     * Obtém taxas adicionais
     */
    public function getFeesConfig(): array
    {
        return $this->fees ?? [];
    }

    /**
     * Define taxas adicionais
     */
    public function setFeesConfig(array $fees): self
    {
        $this->fees = $fees;
        $this->data['fees'] = $fees;
        return $this;
    }

    /**
     * Obtém configurações de desconto
     */
    public function getDiscounts(): array
    {
        return $this->discounts ?? [];
    }

    /**
     * Define configurações de desconto
     */
    public function setDiscounts(array $discounts): self
    {
        $this->discounts = $discounts;
        $this->data['discounts'] = $discounts;
        return $this;
    }

    /**
     * Obtém metadados
     */
    public function getMetadata(): array
    {
        return $this->metadata ?? [];
    }

    /**
     * Define metadados
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        $this->data['metadata'] = $metadata;
        return $this;
    }

    /**
     * Obtém valor de metadado
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Define valor de metadado
     */
    public function setMetadataValue(string $key, mixed $value): self
    {
        if (!is_array($this->metadata)) {
            $this->metadata = [];
        }

        $this->metadata[$key] = $value;
        $this->data['metadata'] = $this->metadata;

        return $this;
    }

    /**
     * Verifica se carrinho está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se carrinho foi abandonado
     */
    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    /**
     * Verifica se carrinho foi convertido
     */
    public function isConverted(): bool
    {
        return $this->status === 'completed' && !empty($this->order_id);
    }

    /**
     * Verifica se carrinho expirou
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expires_at && strtotime($this->expires_at) < time()) {
            return true;
        }

        return false;
    }

    /**
     * Verifica se carrinho é one-click
     */
    public function isOneClick(): bool
    {
        return $this->type === 'one_click';
    }

    /**
     * Verifica se carrinho é padrão
     */
    public function isStandard(): bool
    {
        return $this->type === 'standard';
    }

    /**
     * Verifica se carrinho requer envio
     */
    public function requiresShipping(): bool
    {
        foreach ($this->getItems() as $item) {
            if ($item['requires_shipping'] ?? true) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtém peso total
     */
    public function getTotalWeight(): float
    {
        $totalWeight = 0.0;

        foreach ($this->getItems() as $item) {
            $weight = $item['weight'] ?? 0.0;
            $quantity = $item['quantity'] ?? 1;
            $totalWeight += $weight * $quantity;
        }

        return $totalWeight;
    }

    /**
     * Obtém produtos únicos
     */
    public function getUniqueProducts(): array
    {
        $products = [];

        foreach ($this->getItems() as $item) {
            $productId = $item['product_id'];
            if (!isset($products[$productId])) {
                $products[$productId] = $item;
            }
        }

        return array_values($products);
    }

    /**
     * Obtém resumo do carrinho
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'type' => $this->type,
            'status' => $this->status,
            'item_count' => $this->getItemCount(),
            'total_quantity' => $this->getTotalQuantity(),
            'subtotal' => $this->getSubtotal(),
            'discount' => $this->getDiscount(),
            'total' => $this->getTotal(),
            'formatted_total' => $this->getFormattedTotal(),
            'currency' => $this->getCurrency(),
            'has_coupon' => $this->hasCoupon(),
            'coupon_code' => $this->getCouponCode(),
            'requires_shipping' => $this->requiresShipping(),
            'is_empty' => $this->isEmpty(),
            'is_active' => $this->isActive(),
            'is_converted' => $this->isConverted(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Converte para array para exibição
     */
    public function toDisplay(): array
    {
        return [
            'id' => $this->id,
            'session_id' => $this->session_id,
            'type' => $this->type,
            'status' => $this->status,
            'items' => $this->getItems(),
            'totals' => $this->getTotals(),
            'coupon' => $this->getCoupon(),
            'shipping_data' => $this->getShippingData(),
            'billing_data' => $this->getBillingData(),
            'currency' => $this->getCurrency(),
            'summary' => $this->getSummary()
        ];
    }

    /**
     * Cria instância para criação
     */
    public static function forCreation(string $sessionId, array $data = []): self
    {
        return new self(array_merge([
            'session_id' => $sessionId,
            'type' => 'standard',
            'status' => 'active',
            'items' => [],
            'totals' => [
                'subtotal' => 0.0,
                'discount' => 0.0,
                'taxes' => 0.0,
                'shipping' => 0.0,
                'fees' => 0.0,
                'total' => 0.0
            ],
            'currency' => 'BRL',
            'shipping_data' => [],
            'billing_data' => [],
            'taxes' => [],
            'fees' => [],
            'discounts' => [],
            'metadata' => []
        ], $data));
    }

    /**
     * Cria instância a partir de dados da API
     */
    public static function fromApi(array $apiData): self
    {
        return new self($apiData);
    }
}
