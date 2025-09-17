<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Item do Pedido
 *
 * Representa os dados de um item específico dentro de um pedido.
 * Inclui informações do produto, quantidade, preços e personalizações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de item do pedido
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderItemData extends BaseData
{
    public ?string $id = null;
    public ?string $order_id = null;
    public ?string $product_id = null;
    public ?string $variant_id = null;
    public ?string $name = null;
    public ?string $sku = null;
    public ?string $description = null;
    public ?int $quantity = null;
    public ?float $unit_price = null;
    public ?float $total_price = null;
    public ?float $discount_amount = null;
    public ?float $tax_amount = null;
    public ?string $currency = null;
    public ?array $product_data = null;
    public ?array $variant_data = null;
    public ?array $customizations = null;
    public ?array $metadata = null;
    public ?bool $is_upsell = null;
    public ?bool $is_gift = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'order_id' => ['required', 'string', ['min', 1]],
            'product_id' => ['required', 'string', ['min', 1]],
            'name' => ['required', 'string', ['min', 1], ['max', 255]],
            'quantity' => ['required', 'integer', ['min', 1]],
            'unit_price' => ['required', 'numeric', ['min', 0]],
            'total_price' => ['required', 'numeric', ['min', 0]],
            'discount_amount' => ['numeric', ['min', 0]],
            'tax_amount' => ['numeric', ['min', 0]],
            'currency' => ['required', 'string', ['in', ['BRL', 'USD', 'EUR', 'GBP']]],
            'sku' => ['string'],
            'description' => ['string'],
            'variant_id' => ['string'],
            'product_data' => ['array'],
            'variant_data' => ['array'],
            'customizations' => ['array'],
            'metadata' => ['array'],
            'is_upsell' => ['boolean'],
            'is_gift' => ['boolean'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém preço unitário formatado
     */
    public function getFormattedUnitPrice(string $currency = null): string
    {
        $currency = $currency ?? $this->currency ?? 'BRL';
        $price = $this->unit_price ?? 0;

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($price, 2, ',', '.'),
            'USD' => '$' . number_format($price, 2, '.', ','),
            'EUR' => '€' . number_format($price, 2, ',', '.'),
            'GBP' => '£' . number_format($price, 2, '.', ','),
            default => number_format($price, 2, '.', ',')
        };
    }

    /**
     * Obtém preço total formatado
     */
    public function getFormattedTotalPrice(string $currency = null): string
    {
        $currency = $currency ?? $this->currency ?? 'BRL';
        $total = $this->total_price ?? 0;

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($total, 2, ',', '.'),
            'USD' => '$' . number_format($total, 2, '.', ','),
            'EUR' => '€' . number_format($total, 2, ',', '.'),
            'GBP' => '£' . number_format($total, 2, '.', ','),
            default => number_format($total, 2, '.', ',')
        };
    }

    /**
     * Obtém desconto formatado
     */
    public function getFormattedDiscount(string $currency = null): string
    {
        if (!$this->discount_amount) {
            return '';
        }

        $currency = $currency ?? $this->currency ?? 'BRL';
        $discount = $this->discount_amount;

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($discount, 2, ',', '.'),
            'USD' => '$' . number_format($discount, 2, '.', ','),
            'EUR' => '€' . number_format($discount, 2, ',', '.'),
            'GBP' => '£' . number_format($discount, 2, '.', ','),
            default => number_format($discount, 2, '.', ',')
        };
    }

    /**
     * Calcula preço com desconto
     */
    public function getPriceAfterDiscount(): float
    {
        return ($this->total_price ?? 0) - ($this->discount_amount ?? 0);
    }

    /**
     * Obtém preço após desconto formatado
     */
    public function getFormattedPriceAfterDiscount(string $currency = null): string
    {
        $currency = $currency ?? $this->currency ?? 'BRL';
        $price = $this->getPriceAfterDiscount();

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($price, 2, ',', '.'),
            'USD' => '$' . number_format($price, 2, '.', ','),
            'EUR' => '€' . number_format($price, 2, ',', '.'),
            'GBP' => '£' . number_format($price, 2, '.', ','),
            default => number_format($price, 2, '.', ',')
        };
    }

    /**
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        if (!$this->total_price || !$this->discount_amount) {
            return 0;
        }

        return round(($this->discount_amount / $this->total_price) * 100, 2);
    }

    /**
     * Verifica se item tem desconto
     */
    public function hasDiscount(): bool
    {
        return ($this->discount_amount ?? 0) > 0;
    }

    /**
     * Verifica se é item de upsell
     */
    public function isUpsell(): bool
    {
        return $this->is_upsell === true;
    }

    /**
     * Verifica se é presente
     */
    public function isGift(): bool
    {
        return $this->is_gift === true;
    }

    /**
     * Verifica se tem variação
     */
    public function hasVariant(): bool
    {
        return !empty($this->variant_id);
    }

    /**
     * Obtém dados do produto
     */
    public function getProductData(): array
    {
        return $this->product_data ?? [];
    }

    /**
     * Obtém dados da variação
     */
    public function getVariantData(): array
    {
        return $this->variant_data ?? [];
    }

    /**
     * Obtém customizações
     */
    public function getCustomizations(): array
    {
        return $this->customizations ?? [];
    }

    /**
     * Verifica se tem customizações
     */
    public function hasCustomizations(): bool
    {
        return !empty($this->getCustomizations());
    }

    /**
     * Adiciona customização
     */
    public function addCustomization(string $key, mixed $value): self
    {
        if (!is_array($this->customizations)) {
            $this->customizations = [];
        }

        $this->customizations[$key] = $value;
        $this->data['customizations'] = $this->customizations;

        return $this;
    }

    /**
     * Remove customização
     */
    public function removeCustomization(string $key): self
    {
        if (is_array($this->customizations) && isset($this->customizations[$key])) {
            unset($this->customizations[$key]);
            $this->data['customizations'] = $this->customizations;
        }

        return $this;
    }

    /**
     * Obtém customização específica
     */
    public function getCustomization(string $key, mixed $default = null): mixed
    {
        return $this->customizations[$key] ?? $default;
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
     * Obtém peso total do item (quantidade × peso unitário)
     */
    public function getTotalWeight(): float
    {
        $unitWeight = $this->getProductData()['weight'] ?? $this->getVariantData()['weight'] ?? 0;
        return $unitWeight * ($this->quantity ?? 1);
    }

    /**
     * Obtém dimensões do item
     */
    public function getDimensions(): array
    {
        return $this->getVariantData()['dimensions'] ?? $this->getProductData()['dimensions'] ?? [];
    }

    /**
     * Verifica se item precisa de envio
     */
    public function requiresShipping(): bool
    {
        $productType = $this->getProductData()['type'] ?? 'physical';
        return $productType === 'physical';
    }

    /**
     * Obtém dados para exibição resumida
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'formatted_unit_price' => $this->getFormattedUnitPrice(),
            'formatted_total_price' => $this->getFormattedTotalPrice(),
            'has_discount' => $this->hasDiscount(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'is_upsell' => $this->isUpsell(),
            'is_gift' => $this->isGift(),
            'has_variant' => $this->hasVariant(),
            'requires_shipping' => $this->requiresShipping()
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
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_price' => $this->total_price,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'price_after_discount' => $this->getPriceAfterDiscount(),
            'formatted_unit_price' => $this->getFormattedUnitPrice(),
            'formatted_total_price' => $this->getFormattedTotalPrice(),
            'formatted_discount' => $this->getFormattedDiscount(),
            'formatted_price_after_discount' => $this->getFormattedPriceAfterDiscount(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'currency' => $this->currency,
            'product_data' => $this->getProductData(),
            'variant_data' => $this->getVariantData(),
            'customizations' => $this->getCustomizations(),
            'metadata' => $this->getMetadata(),
            'is_upsell' => $this->isUpsell(),
            'is_gift' => $this->isGift(),
            'has_variant' => $this->hasVariant(),
            'has_customizations' => $this->hasCustomizations(),
            'requires_shipping' => $this->requiresShipping(),
            'total_weight' => $this->getTotalWeight(),
            'dimensions' => $this->getDimensions(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $orderId,
        string $productId,
        string $name,
        int $quantity,
        float $unitPrice,
        string $currency = 'BRL',
        array $additionalData = []
    ): self {
        $totalPrice = $unitPrice * $quantity;

        return new self(array_merge([
            'order_id' => $orderId,
            'product_id' => $productId,
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'currency' => $currency,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'is_upsell' => false,
            'is_gift' => false,
            'customizations' => [],
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
