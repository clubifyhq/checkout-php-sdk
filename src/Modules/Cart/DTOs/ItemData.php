<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO para dados de Item de Carrinho
 *
 * Representa um item específico dentro do carrinho,
 * incluindo todas as informações necessárias para
 * processamento, exibição e cálculos.
 *
 * Funcionalidades:
 * - Dados básicos de produto
 * - Variações e personalizações
 * - Preços e quantidades
 * - Dados de envio e estoque
 * - Configurações especiais
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de item
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Interface específica de item
 * - D: Dependency Inversion - Depende de abstrações
 */
class ItemData extends BaseData
{
    // Identificadores
    public ?string $id = null;
    public ?string $cart_id = null;
    public ?string $product_id = null;
    public ?string $variant_id = null;
    public ?string $sku = null;

    // Informações básicas
    public ?string $name = null;
    public ?string $description = null;
    public ?string $category = null;
    public ?string $brand = null;
    public ?string $image_url = null;
    public ?string $product_url = null;

    // Preços e valores
    public ?float $price = null;
    public ?float $original_price = null;
    public ?float $cost_price = null;
    public ?float $total_price = null;
    public ?string $currency = null;

    // Quantidade e disponibilidade
    public ?int $quantity = null;
    public ?int $available_quantity = null;
    public ?int $max_quantity = null;
    public ?int $min_quantity = null;

    // Dimensões e peso
    public ?float $weight = null;
    public ?float $length = null;
    public ?float $width = null;
    public ?float $height = null;
    public ?string $weight_unit = null;
    public ?string $dimension_unit = null;

    // Variações e personalizações
    public ?array $variations = null;
    public ?array $customizations = null;
    public ?array $attributes = null;

    // Configurações de envio e estoque
    public ?bool $requires_shipping = null;
    public ?bool $is_digital = null;
    public ?bool $is_subscription = null;
    public ?bool $track_stock = null;
    public ?string $stock_status = null;

    // Taxas e descontos específicos do item
    public ?array $taxes = null;
    public ?array $discounts = null;
    public ?float $discount_amount = null;

    // Dados do fornecedor/vendedor
    public ?string $vendor_id = null;
    public ?string $vendor_name = null;

    // Metadados e configurações
    public ?array $metadata = null;
    public ?array $options = null;

    // Timestamps
    public ?string $added_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'product_id' => ['required', 'string', ['min', 1]],
            'name' => ['required', 'string', ['min', 1]],
            'price' => ['required', 'numeric', ['min', 0]],
            'quantity' => ['required', 'integer', ['min', 1]],
            'cart_id' => ['string'],
            'variant_id' => ['string'],
            'sku' => ['string'],
            'description' => ['string'],
            'category' => ['string'],
            'brand' => ['string'],
            'image_url' => ['url'],
            'product_url' => ['url'],
            'original_price' => ['numeric', ['min', 0]],
            'cost_price' => ['numeric', ['min', 0]],
            'total_price' => ['numeric', ['min', 0]],
            'currency' => ['string', ['in', ['BRL', 'USD', 'EUR', 'ARS', 'CLP', 'PEN', 'COP', 'MXN']]],
            'available_quantity' => ['integer', ['min', 0]],
            'max_quantity' => ['integer', ['min', 1]],
            'min_quantity' => ['integer', ['min', 1]],
            'weight' => ['numeric', ['min', 0]],
            'length' => ['numeric', ['min', 0]],
            'width' => ['numeric', ['min', 0]],
            'height' => ['numeric', ['min', 0]],
            'weight_unit' => ['string', ['in', ['kg', 'g', 'lb', 'oz']]],
            'dimension_unit' => ['string', ['in', ['cm', 'mm', 'in', 'ft']]],
            'variations' => ['array'],
            'customizations' => ['array'],
            'attributes' => ['array'],
            'requires_shipping' => ['boolean'],
            'is_digital' => ['boolean'],
            'is_subscription' => ['boolean'],
            'track_stock' => ['boolean'],
            'stock_status' => ['string', ['in', ['in_stock', 'out_of_stock', 'low_stock', 'discontinued']]],
            'taxes' => ['array'],
            'discounts' => ['array'],
            'discount_amount' => ['numeric', ['min', 0]],
            'vendor_id' => ['string'],
            'vendor_name' => ['string'],
            'metadata' => ['array'],
            'options' => ['array'],
            'added_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    // ===========================================
    // MÉTODOS DE PREÇO E CÁLCULOS
    // ===========================================

    /**
     * Calcula preço total do item
     */
    public function calculateTotalPrice(): float
    {
        $price = $this->getEffectivePrice();
        $quantity = $this->quantity ?? 1;

        return $price * $quantity;
    }

    /**
     * Obtém preço efetivo (com descontos aplicados)
     */
    public function getEffectivePrice(): float
    {
        $basePrice = $this->price ?? 0.0;
        $discountAmount = $this->discount_amount ?? 0.0;

        return max(0, $basePrice - $discountAmount);
    }

    /**
     * Verifica se item tem desconto
     */
    public function hasDiscount(): bool
    {
        return ($this->discount_amount ?? 0) > 0 || !empty($this->discounts);
    }

    /**
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        $originalPrice = $this->original_price ?? $this->price ?? 0;
        $discountAmount = $this->discount_amount ?? 0;

        if ($originalPrice <= 0) {
            return 0.0;
        }

        return ($discountAmount / $originalPrice) * 100;
    }

    /**
     * Obtém economia total
     */
    public function getTotalSavings(): float
    {
        $originalPrice = $this->original_price ?? $this->price ?? 0;
        $currentPrice = $this->getEffectivePrice();
        $quantity = $this->quantity ?? 1;

        return max(0, ($originalPrice - $currentPrice) * $quantity);
    }

    // ===========================================
    // MÉTODOS DE VARIAÇÕES E CUSTOMIZAÇÕES
    // ===========================================

    /**
     * Obtém variações do item
     */
    public function getVariations(): array
    {
        return $this->variations ?? [];
    }

    /**
     * Adiciona variação
     */
    public function addVariation(string $name, string $value): self
    {
        if (!is_array($this->variations)) {
            $this->variations = [];
        }

        $this->variations[$name] = $value;
        $this->data['variations'] = $this->variations;

        return $this;
    }

    /**
     * Remove variação
     */
    public function removeVariation(string $name): self
    {
        if (is_array($this->variations) && isset($this->variations[$name])) {
            unset($this->variations[$name]);
            $this->data['variations'] = $this->variations;
        }

        return $this;
    }

    /**
     * Verifica se tem variação específica
     */
    public function hasVariation(string $name): bool
    {
        return isset($this->variations[$name]);
    }

    /**
     * Obtém valor de variação
     */
    public function getVariationValue(string $name): ?string
    {
        return $this->variations[$name] ?? null;
    }

    /**
     * Obtém customizações
     */
    public function getCustomizations(): array
    {
        return $this->customizations ?? [];
    }

    /**
     * Adiciona customização
     */
    public function addCustomization(string $type, mixed $value): self
    {
        if (!is_array($this->customizations)) {
            $this->customizations = [];
        }

        $this->customizations[] = [
            'type' => $type,
            'value' => $value,
            'added_at' => date('Y-m-d H:i:s')
        ];

        $this->data['customizations'] = $this->customizations;

        return $this;
    }

    // ===========================================
    // MÉTODOS DE ESTOQUE E DISPONIBILIDADE
    // ===========================================

    /**
     * Verifica se item está em estoque
     */
    public function isInStock(): bool
    {
        if (!$this->track_stock) {
            return true;
        }

        return $this->stock_status === 'in_stock' &&
               ($this->available_quantity ?? 0) >= ($this->quantity ?? 1);
    }

    /**
     * Verifica se estoque é baixo
     */
    public function isLowStock(): bool
    {
        return $this->stock_status === 'low_stock';
    }

    /**
     * Verifica se item está fora de estoque
     */
    public function isOutOfStock(): bool
    {
        return $this->stock_status === 'out_of_stock' ||
               (($this->available_quantity ?? 0) < ($this->quantity ?? 1));
    }

    /**
     * Verifica se quantidade solicitada está disponível
     */
    public function isQuantityAvailable(int $requestedQuantity = null): bool
    {
        $requested = $requestedQuantity ?? $this->quantity ?? 1;

        if (!$this->track_stock) {
            return true;
        }

        return ($this->available_quantity ?? 0) >= $requested;
    }

    /**
     * Obtém quantidade máxima disponível
     */
    public function getMaxAvailableQuantity(): int
    {
        if (!$this->track_stock) {
            return $this->max_quantity ?? 999;
        }

        $available = $this->available_quantity ?? 0;
        $max = $this->max_quantity ?? 999;

        return min($available, $max);
    }

    // ===========================================
    // MÉTODOS DE ENVIO E CARACTERÍSTICAS
    // ===========================================

    /**
     * Verifica se item requer envio
     */
    public function requiresShipping(): bool
    {
        return ($this->requires_shipping ?? true) && !$this->isDigital();
    }

    /**
     * Verifica se é produto digital
     */
    public function isDigital(): bool
    {
        return $this->is_digital === true;
    }

    /**
     * Verifica se é assinatura
     */
    public function isSubscription(): bool
    {
        return $this->is_subscription === true;
    }

    /**
     * Obtém peso total do item
     */
    public function getTotalWeight(): float
    {
        $weight = $this->weight ?? 0.0;
        $quantity = $this->quantity ?? 1;

        return $weight * $quantity;
    }

    /**
     * Obtém dimensões do item
     */
    public function getDimensions(): array
    {
        return [
            'length' => $this->length ?? 0,
            'width' => $this->width ?? 0,
            'height' => $this->height ?? 0,
            'unit' => $this->dimension_unit ?? 'cm'
        ];
    }

    /**
     * Calcula volume do item
     */
    public function getVolume(): float
    {
        $dimensions = $this->getDimensions();
        return $dimensions['length'] * $dimensions['width'] * $dimensions['height'];
    }

    // ===========================================
    // MÉTODOS DE ATRIBUTOS E METADADOS
    // ===========================================

    /**
     * Obtém atributos do item
     */
    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    /**
     * Obtém valor de atributo
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Define atributo
     */
    public function setAttribute(string $key, mixed $value): self
    {
        if (!is_array($this->attributes)) {
            $this->attributes = [];
        }

        $this->attributes[$key] = $value;
        $this->data['attributes'] = $this->attributes;

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

    // ===========================================
    // MÉTODOS DE COMPARAÇÃO E IGUALDADE
    // ===========================================

    /**
     * Verifica se item é igual a outro
     */
    public function equals(ItemData $otherItem): bool
    {
        // Compara IDs de produto
        if ($this->product_id !== $otherItem->product_id) {
            return false;
        }

        // Compara variações
        $thisVariations = $this->getVariations();
        $otherVariations = $otherItem->getVariations();

        ksort($thisVariations);
        ksort($otherVariations);

        return $thisVariations === $otherVariations;
    }

    /**
     * Verifica se pode ser combinado com outro item
     */
    public function canCombineWith(ItemData $otherItem): bool
    {
        return $this->equals($otherItem) &&
               $this->getEffectivePrice() === $otherItem->getEffectivePrice();
    }

    // ===========================================
    // MÉTODOS DE FORMATAÇÃO E DISPLAY
    // ===========================================

    /**
     * Obtém nome completo com variações
     */
    public function getFullName(): string
    {
        $name = $this->name ?? 'Item sem nome';
        $variations = $this->getVariations();

        if (empty($variations)) {
            return $name;
        }

        $variationStrings = [];
        foreach ($variations as $key => $value) {
            $variationStrings[] = "{$key}: {$value}";
        }

        return $name . ' (' . implode(', ', $variationStrings) . ')';
    }

    /**
     * Formata preço na moeda especificada
     */
    public function getFormattedPrice(): string
    {
        return $this->formatCurrency($this->getEffectivePrice());
    }

    /**
     * Formata preço total
     */
    public function getFormattedTotalPrice(): string
    {
        return $this->formatCurrency($this->calculateTotalPrice());
    }

    /**
     * Formata economia total
     */
    public function getFormattedSavings(): string
    {
        return $this->formatCurrency($this->getTotalSavings());
    }

    /**
     * Formata valor monetário
     */
    private function formatCurrency(float $amount): string
    {
        $currency = $this->currency ?? 'BRL';

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
     * Obtém resumo do item
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'name' => $this->getFullName(),
            'quantity' => $this->quantity,
            'price' => $this->getEffectivePrice(),
            'total_price' => $this->calculateTotalPrice(),
            'formatted_price' => $this->getFormattedPrice(),
            'formatted_total' => $this->getFormattedTotalPrice(),
            'has_discount' => $this->hasDiscount(),
            'savings' => $this->getTotalSavings(),
            'requires_shipping' => $this->requiresShipping(),
            'is_digital' => $this->isDigital(),
            'is_subscription' => $this->isSubscription(),
            'is_in_stock' => $this->isInStock(),
            'weight' => $this->getTotalWeight(),
            'variations_count' => count($this->getVariations()),
            'customizations_count' => count($this->getCustomizations())
        ];
    }

    /**
     * Cria instância para produto simples
     */
    public static function forProduct(string $productId, string $name, float $price, int $quantity = 1): self
    {
        return new self([
            'product_id' => $productId,
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'currency' => 'BRL',
            'requires_shipping' => true,
            'is_digital' => false,
            'track_stock' => true,
            'stock_status' => 'in_stock',
            'added_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Cria instância para produto digital
     */
    public static function forDigitalProduct(string $productId, string $name, float $price, int $quantity = 1): self
    {
        return new self([
            'product_id' => $productId,
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
            'currency' => 'BRL',
            'requires_shipping' => false,
            'is_digital' => true,
            'track_stock' => false,
            'stock_status' => 'in_stock',
            'added_at' => date('Y-m-d H:i:s')
        ]);
    }
}