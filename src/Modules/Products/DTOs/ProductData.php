<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Produto
 *
 * Representa os dados de um produto no sistema.
 * Inclui informações básicas, preços, estoque e configurações.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de produto
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class ProductData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $sku = null;
    public ?string $description = null;
    public ?string $short_description = null;
    public ?float $price = null;
    public ?float $compare_at_price = null;
    public ?float $cost_price = null;
    public ?string $type = null;
    public ?string $status = null;
    public ?int $stock_quantity = null;
    public ?bool $track_inventory = null;
    public ?bool $allow_backorders = null;
    public ?float $weight = null;
    public ?array $dimensions = null;
    public ?string $category_id = null;
    public ?array $tags = null;
    public ?array $images = null;
    public ?array $variants = null;
    public ?array $attributes = null;
    public ?array $seo = null;
    public ?array $shipping = null;
    public ?array $tax_settings = null;
    public ?bool $featured = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'organization_id' => ['required', 'string', ['min', 1]],
            'name' => ['required', 'string', ['min', 2], ['max', 255]],
            'slug' => ['string', ['min', 2], ['max', 100]],
            'sku' => ['string', ['min', 2], ['max', 50]],
            'description' => ['string', ['max', 5000]],
            'short_description' => ['string', ['max', 500]],
            'price' => ['required', 'numeric', ['min', 0]],
            'compare_at_price' => ['numeric', ['min', 0]],
            'cost_price' => ['numeric', ['min', 0]],
            'type' => ['required', 'string', ['in', ['physical', 'digital', 'service', 'subscription']]],
            'status' => ['string', ['in', ['active', 'inactive', 'draft', 'archived']]],
            'stock_quantity' => ['integer', ['min', 0]],
            'track_inventory' => ['boolean'],
            'allow_backorders' => ['boolean'],
            'weight' => ['numeric', ['min', 0]],
            'dimensions' => ['array'],
            'category_id' => ['string'],
            'tags' => ['array'],
            'images' => ['array'],
            'variants' => ['array'],
            'attributes' => ['array'],
            'seo' => ['array'],
            'shipping' => ['array'],
            'tax_settings' => ['array'],
            'featured' => ['boolean'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém preço formatado
     */
    public function getFormattedPrice(string $currency = 'BRL'): string
    {
        if ($this->price === null) {
            return '';
        }

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($this->price, 2, ',', '.'),
            'USD' => '$' . number_format($this->price, 2, '.', ','),
            'EUR' => '€' . number_format($this->price, 2, ',', '.'),
            default => number_format($this->price, 2, '.', ',')
        };
    }

    /**
     * Obtém preço de comparação formatado
     */
    public function getFormattedComparePrice(string $currency = 'BRL'): string
    {
        if ($this->compare_at_price === null) {
            return '';
        }

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($this->compare_at_price, 2, ',', '.'),
            'USD' => '$' . number_format($this->compare_at_price, 2, '.', ','),
            'EUR' => '€' . number_format($this->compare_at_price, 2, ',', '.'),
            default => number_format($this->compare_at_price, 2, '.', ',')
        };
    }

    /**
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        if (!$this->compare_at_price || !$this->price || $this->compare_at_price <= $this->price) {
            return 0;
        }

        return round((($this->compare_at_price - $this->price) / $this->compare_at_price) * 100, 2);
    }

    /**
     * Verifica se produto está em desconto
     */
    public function isOnSale(): bool
    {
        return $this->compare_at_price && $this->price && $this->compare_at_price > $this->price;
    }

    /**
     * Verifica se produto está em estoque
     */
    public function isInStock(): bool
    {
        if (!$this->track_inventory) {
            return true;
        }

        return $this->stock_quantity > 0;
    }

    /**
     * Verifica se está com estoque baixo
     */
    public function isLowStock(int $threshold = 5): bool
    {
        if (!$this->track_inventory) {
            return false;
        }

        return $this->stock_quantity <= $threshold;
    }

    /**
     * Verifica se produto está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se produto está em destaque
     */
    public function isFeatured(): bool
    {
        return $this->featured === true;
    }

    /**
     * Verifica se é produto físico
     */
    public function isPhysical(): bool
    {
        return $this->type === 'physical';
    }

    /**
     * Verifica se é produto digital
     */
    public function isDigital(): bool
    {
        return $this->type === 'digital';
    }

    /**
     * Verifica se é serviço
     */
    public function isService(): bool
    {
        return $this->type === 'service';
    }

    /**
     * Verifica se é assinatura
     */
    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    /**
     * Obtém tags como array
     */
    public function getTags(): array
    {
        return $this->tags ?? [];
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
     * Remove tag
     */
    public function removeTag(string $tag): self
    {
        if (is_array($this->tags)) {
            $key = array_search($tag, $this->tags);
            if ($key !== false) {
                unset($this->tags[$key]);
                $this->tags = array_values($this->tags);
                $this->data['tags'] = $this->tags;
            }
        }

        return $this;
    }

    /**
     * Verifica se tem tag específica
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTags());
    }

    /**
     * Obtém imagens do produto
     */
    public function getImages(): array
    {
        return $this->images ?? [];
    }

    /**
     * Obtém primeira imagem
     */
    public function getMainImage(): ?array
    {
        $images = $this->getImages();
        return !empty($images) ? $images[0] : null;
    }

    /**
     * Adiciona imagem
     */
    public function addImage(array $image): self
    {
        if (!is_array($this->images)) {
            $this->images = [];
        }

        $this->images[] = $image;
        $this->data['images'] = $this->images;

        return $this;
    }

    /**
     * Obtém variações do produto
     */
    public function getVariants(): array
    {
        return $this->variants ?? [];
    }

    /**
     * Verifica se tem variações
     */
    public function hasVariants(): bool
    {
        return !empty($this->getVariants());
    }

    /**
     * Obtém atributos do produto
     */
    public function getAttributes(): array
    {
        return $this->attributes ?? [];
    }

    /**
     * Obtém atributo específico
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
     * Obtém configurações de SEO
     */
    public function getSeo(): array
    {
        return $this->seo ?? [];
    }

    /**
     * Define configurações de SEO
     */
    public function setSeo(array $seo): self
    {
        $this->seo = $seo;
        $this->data['seo'] = $seo;
        return $this;
    }

    /**
     * Obtém título SEO
     */
    public function getSeoTitle(): string
    {
        return $this->seo['title'] ?? $this->name ?? '';
    }

    /**
     * Obtém descrição SEO
     */
    public function getSeoDescription(): string
    {
        return $this->seo['description'] ?? $this->short_description ?? '';
    }

    /**
     * Obtém configurações de envio
     */
    public function getShipping(): array
    {
        return $this->shipping ?? [];
    }

    /**
     * Verifica se produto precisa de envio
     */
    public function requiresShipping(): bool
    {
        return $this->isPhysical() && ($this->shipping['requires_shipping'] ?? true);
    }

    /**
     * Obtém peso para envio
     */
    public function getShippingWeight(): float
    {
        return $this->weight ?? 0.0;
    }

    /**
     * Obtém dimensões para envio
     */
    public function getDimensions(): array
    {
        return $this->dimensions ?? [];
    }

    /**
     * Obtém configurações de taxa
     */
    public function getTaxSettings(): array
    {
        return $this->tax_settings ?? [];
    }

    /**
     * Verifica se produto é tributável
     */
    public function isTaxable(): bool
    {
        return $this->tax_settings['taxable'] ?? true;
    }

    /**
     * Obtém classe de taxa
     */
    public function getTaxClass(): string
    {
        return $this->tax_settings['tax_class'] ?? 'standard';
    }

    /**
     * Obtém dados para exibição na loja
     */
    public function toStorefront(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'formatted_price' => $this->getFormattedPrice(),
            'formatted_compare_price' => $this->getFormattedComparePrice(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'is_on_sale' => $this->isOnSale(),
            'type' => $this->type,
            'in_stock' => $this->isInStock(),
            'low_stock' => $this->isLowStock(),
            'featured' => $this->isFeatured(),
            'tags' => $this->getTags(),
            'images' => $this->getImages(),
            'main_image' => $this->getMainImage(),
            'variants' => $this->getVariants(),
            'has_variants' => $this->hasVariants(),
            'requires_shipping' => $this->requiresShipping(),
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription()
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
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'price' => $this->price,
            'compare_at_price' => $this->compare_at_price,
            'cost_price' => $this->cost_price,
            'type' => $this->type,
            'status' => $this->status,
            'stock_quantity' => $this->stock_quantity,
            'track_inventory' => $this->track_inventory,
            'allow_backorders' => $this->allow_backorders,
            'weight' => $this->weight,
            'dimensions' => $this->getDimensions(),
            'category_id' => $this->category_id,
            'tags' => $this->getTags(),
            'images' => $this->getImages(),
            'variants_count' => count($this->getVariants()),
            'attributes' => $this->getAttributes(),
            'seo' => $this->getSeo(),
            'shipping' => $this->getShipping(),
            'tax_settings' => $this->getTaxSettings(),
            'featured' => $this->featured,
            'is_active' => $this->isActive(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $organizationId,
        string $name,
        float $price,
        string $type,
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'price' => $price,
            'type' => $type,
            'status' => 'active',
            'track_inventory' => true,
            'allow_backorders' => false,
            'featured' => false,
            'tags' => [],
            'images' => [],
            'variants' => [],
            'attributes' => [],
            'seo' => [],
            'shipping' => [],
            'tax_settings' => ['taxable' => true, 'tax_class' => 'standard']
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