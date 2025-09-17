<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Item do Carrinho
 *
 * Representa um item individual dentro do carrinho
 * com todas as suas propriedades e configurações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de item
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class ItemData extends BaseData
{
    public ?string $id = null;
    public ?string $cart_id = null;
    public ?string $product_id = null;
    public ?string $variant_id = null;
    public ?string $offer_id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $sku = null;
    public ?float $price = null;
    public ?float $original_price = null;
    public ?float $cost_price = null;
    public ?int $quantity = null;
    public ?float $weight = null;
    public ?array $dimensions = null;
    public ?array $attributes = null;
    public ?array $variant_attributes = null;
    public ?string $image_url = null;
    public ?array $images = null;
    public ?string $category = null;
    public ?array $tags = null;
    public ?bool $requires_shipping = null;
    public ?bool $is_digital = null;
    public ?bool $is_subscription = null;
    public ?array $subscription_config = null;
    public ?array $discount = null;
    public ?array $taxes = null;
    public ?array $fees = null;
    public ?array $pricing_rules = null;
    public ?array $inventory = null;
    public ?array $customization = null;
    public ?array $metadata = null;
    public ?string $added_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'cart_id' => ['string'],
            'product_id' => ['required', 'string', ['min', 1]],
            'variant_id' => ['string'],
            'offer_id' => ['string'],
            'name' => ['required', 'string', ['min', 1], ['max', 255]],
            'description' => ['string', ['max', 1000]],
            'sku' => ['string', ['max', 100]],
            'price' => ['required', 'numeric', ['min', 0]],
            'original_price' => ['numeric', ['min', 0]],
            'cost_price' => ['numeric', ['min', 0]],
            'quantity' => ['required', 'integer', ['min', 1], ['max', 999]],
            'weight' => ['numeric', ['min', 0]],
            'dimensions' => ['array'],
            'attributes' => ['array'],
            'variant_attributes' => ['array'],
            'image_url' => ['string'],
            'images' => ['array'],
            'category' => ['string', ['max', 100]],
            'tags' => ['array'],
            'requires_shipping' => ['boolean'],
            'is_digital' => ['boolean'],
            'is_subscription' => ['boolean'],
            'subscription_config' => ['array'],
            'discount' => ['array'],
            'taxes' => ['array'],
            'fees' => ['array'],
            'pricing_rules' => ['array'],
            'inventory' => ['array'],
            'customization' => ['array'],
            'metadata' => ['array'],
            'added_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém preço unitário
     */
    public function getPrice(): float
    {
        return $this->price ?? 0.0;
    }

    /**
     * Obtém preço original (antes de descontos)
     */
    public function getOriginalPrice(): float
    {
        return $this->original_price ?? $this->getPrice();
    }

    /**
     * Obtém preço de custo
     */
    public function getCostPrice(): float
    {
        return $this->cost_price ?? 0.0;
    }

    /**
     * Obtém quantidade
     */
    public function getQuantity(): int
    {
        return $this->quantity ?? 1;
    }

    /**
     * Define quantidade
     */
    public function setQuantity(int $quantity): self
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantidade deve ser maior que zero');
        }

        $this->quantity = $quantity;
        $this->data['quantity'] = $quantity;
        return $this;
    }

    /**
     * Calcula subtotal do item (preço × quantidade)
     */
    public function getSubtotal(): float
    {
        return $this->getPrice() * $this->getQuantity();
    }

    /**
     * Calcula subtotal original (preço original × quantidade)
     */
    public function getOriginalSubtotal(): float
    {
        return $this->getOriginalPrice() * $this->getQuantity();
    }

    /**
     * Calcula desconto total do item
     */
    public function getDiscountAmount(): float
    {
        return $this->getOriginalSubtotal() - $this->getSubtotal();
    }

    /**
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        $originalSubtotal = $this->getOriginalSubtotal();

        if ($originalSubtotal <= 0) {
            return 0.0;
        }

        $discountAmount = $this->getDiscountAmount();
        return round(($discountAmount / $originalSubtotal) * 100, 2);
    }

    /**
     * Verifica se item tem desconto
     */
    public function hasDiscount(): bool
    {
        return $this->getDiscountAmount() > 0;
    }

    /**
     * Obtém peso total do item
     */
    public function getTotalWeight(): float
    {
        return ($this->weight ?? 0.0) * $this->getQuantity();
    }

    /**
     * Obtém dimensões do produto
     */
    public function getDimensions(): array
    {
        return $this->dimensions ?? [];
    }

    /**
     * Obtém largura
     */
    public function getWidth(): float
    {
        return $this->dimensions['width'] ?? 0.0;
    }

    /**
     * Obtém altura
     */
    public function getHeight(): float
    {
        return $this->dimensions['height'] ?? 0.0;
    }

    /**
     * Obtém profundidade
     */
    public function getDepth(): float
    {
        return $this->dimensions['depth'] ?? 0.0;
    }

    /**
     * Obtém atributos do produto
     */
    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    /**
     * Obtém valor de atributo específico
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
     * Obtém atributos da variação
     */
    public function getVariantAttributes(): array
    {
        return $this->variant_attributes ?? [];
    }

    /**
     * Obtém valor de atributo da variação
     */
    public function getVariantAttribute(string $key, mixed $default = null): mixed
    {
        return $this->variant_attributes[$key] ?? $default;
    }

    /**
     * Define atributo da variação
     */
    public function setVariantAttribute(string $key, mixed $value): self
    {
        if (!is_array($this->variant_attributes)) {
            $this->variant_attributes = [];
        }

        $this->variant_attributes[$key] = $value;
        $this->data['variant_attributes'] = $this->variant_attributes;

        return $this;
    }

    /**
     * Obtém imagens do produto
     */
    public function getImages(): array
    {
        return $this->images ?? [];
    }

    /**
     * Obtém imagem principal
     */
    public function getMainImage(): ?string
    {
        if ($this->image_url) {
            return $this->image_url;
        }

        $images = $this->getImages();
        return !empty($images) ? $images[0]['url'] ?? null : null;
    }

    /**
     * Obtém tags do produto
     */
    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    /**
     * Verifica se tem tag específica
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTags());
    }

    /**
     * Adiciona tag
     */
    public function addTag(string $tag): self
    {
        if (!is_array($this->tags)) {
            $this->tags = [];
        }

        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->data['tags'] = $this->tags;
        }

        return $this;
    }

    /**
     * Verifica se requer envio
     */
    public function requiresShipping(): bool
    {
        return $this->requires_shipping ?? !$this->isDigital();
    }

    /**
     * Verifica se é produto digital
     */
    public function isDigital(): bool
    {
        return $this->is_digital ?? false;
    }

    /**
     * Verifica se é assinatura
     */
    public function isSubscription(): bool
    {
        return $this->is_subscription ?? false;
    }

    /**
     * Obtém configuração da assinatura
     */
    public function getSubscriptionConfig(): array
    {
        return $this->subscription_config ?? [];
    }

    /**
     * Obtém período da assinatura
     */
    public function getSubscriptionPeriod(): ?string
    {
        return $this->subscription_config['period'] ?? null;
    }

    /**
     * Obtém intervalo da assinatura
     */
    public function getSubscriptionInterval(): int
    {
        return $this->subscription_config['interval'] ?? 1;
    }

    /**
     * Obtém configuração de desconto
     */
    public function getDiscount(): array
    {
        return $this->discount ?? [];
    }

    /**
     * Define configuração de desconto
     */
    public function setDiscount(array $discount): self
    {
        $this->discount = $discount;
        $this->data['discount'] = $discount;
        return $this;
    }

    /**
     * Obtém configuração de taxas
     */
    public function getTaxes(): array
    {
        return $this->taxes ?? [];
    }

    /**
     * Define configuração de taxas
     */
    public function setTaxes(array $taxes): self
    {
        $this->taxes = $taxes;
        $this->data['taxes'] = $taxes;
        return $this;
    }

    /**
     * Calcula taxas do item
     */
    public function calculateTaxes(): float
    {
        $taxes = $this->getTaxes();
        $subtotal = $this->getSubtotal();
        $totalTax = 0.0;

        foreach ($taxes as $tax) {
            if ($tax['type'] === 'percentage') {
                $totalTax += $subtotal * ($tax['rate'] / 100);
            } else {
                $totalTax += ($tax['amount'] ?? 0.0) * $this->getQuantity();
            }
        }

        return $totalTax;
    }

    /**
     * Obtém configuração de taxas adicionais
     */
    public function getFees(): array
    {
        return $this->fees ?? [];
    }

    /**
     * Define configuração de taxas adicionais
     */
    public function setFees(array $fees): self
    {
        $this->fees = $fees;
        $this->data['fees'] = $fees;
        return $this;
    }

    /**
     * Calcula taxas adicionais
     */
    public function calculateFees(): float
    {
        $fees = $this->getFees();
        $subtotal = $this->getSubtotal();
        $totalFees = 0.0;

        foreach ($fees as $fee) {
            if ($fee['type'] === 'percentage') {
                $totalFees += $subtotal * ($fee['rate'] / 100);
            } else {
                $totalFees += ($fee['amount'] ?? 0.0) * $this->getQuantity();
            }
        }

        return $totalFees;
    }

    /**
     * Obtém dados de inventário
     */
    public function getInventory(): array
    {
        return $this->inventory ?? [];
    }

    /**
     * Define dados de inventário
     */
    public function setInventory(array $inventory): self
    {
        $this->inventory = $inventory;
        $this->data['inventory'] = $inventory;
        return $this;
    }

    /**
     * Verifica se está em estoque
     */
    public function isInStock(): bool
    {
        $inventory = $this->getInventory();

        if (!($inventory['track_quantity'] ?? false)) {
            return true; // Não rastreia estoque
        }

        $available = $inventory['quantity'] ?? 0;
        return $available >= $this->getQuantity();
    }

    /**
     * Obtém quantidade disponível
     */
    public function getAvailableQuantity(): int
    {
        $inventory = $this->getInventory();
        return $inventory['quantity'] ?? 0;
    }

    /**
     * Obtém customizações do item
     */
    public function getCustomization(): array
    {
        return $this->customization ?? [];
    }

    /**
     * Define customizações
     */
    public function setCustomization(array $customization): self
    {
        $this->customization = $customization;
        $this->data['customization'] = $customization;
        return $this;
    }

    /**
     * Adiciona customização
     */
    public function addCustomization(string $key, mixed $value): self
    {
        if (!is_array($this->customization)) {
            $this->customization = [];
        }

        $this->customization[$key] = $value;
        $this->data['customization'] = $this->customization;

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
     * Calcula total do item (subtotal + taxas + taxas adicionais)
     */
    public function getTotal(): float
    {
        return $this->getSubtotal() + $this->calculateTaxes() + $this->calculateFees();
    }

    /**
     * Obtém resumo do item
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'price' => $this->getPrice(),
            'original_price' => $this->getOriginalPrice(),
            'quantity' => $this->getQuantity(),
            'subtotal' => $this->getSubtotal(),
            'discount_amount' => $this->getDiscountAmount(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'taxes' => $this->calculateTaxes(),
            'fees' => $this->calculateFees(),
            'total' => $this->getTotal(),
            'weight' => $this->weight,
            'total_weight' => $this->getTotalWeight(),
            'requires_shipping' => $this->requiresShipping(),
            'is_digital' => $this->isDigital(),
            'is_subscription' => $this->isSubscription(),
            'is_in_stock' => $this->isInStock(),
            'main_image' => $this->getMainImage(),
            'category' => $this->category,
            'added_at' => $this->added_at
        ];
    }

    /**
     * Cria instância para carrinho
     */
    public static function forCart(string $productId, string $name, float $price, int $quantity = 1, array $additionalData = []): self
    {
        return new self(array_merge([
            'product_id' => $productId,
            'name' => $name,
            'price' => $price,
            'original_price' => $price,
            'quantity' => $quantity,
            'requires_shipping' => true,
            'is_digital' => false,
            'is_subscription' => false,
            'attributes' => [],
            'variant_attributes' => [],
            'tags' => [],
            'images' => [],
            'discount' => [],
            'taxes' => [],
            'fees' => [],
            'inventory' => ['track_quantity' => false],
            'customization' => [],
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