<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO para dados de Oferta
 *
 * Representa uma oferta completa com produtos, configurações, upsells,
 * temas, layouts e todas as configurações de conversão.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de oferta
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OfferData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $name = null;
    public ?string $slug = null;
    public ?string $description = null;
    public ?string $status = null;
    public ?string $type = null;
    public ?array $products = null;
    public ?array $pricing = null;
    public ?array $checkout_config = null;
    public ?array $design_config = null;
    public ?array $theme_config = null;
    public ?array $layout_config = null;
    public ?array $upsells = null;
    public ?array $conversion_tools = null;
    public ?array $targeting = null;
    public ?array $analytics = null;
    public ?array $seo = null;
    public ?array $integrations = null;
    public ?string $domain = null;
    public ?bool $active = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?array $limits = null;
    public ?array $notifications = null;
    public ?array $tracking = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'organization_id' => ['string', ['min', 1]],
            'name' => ['required', 'string', ['min', 2], ['max', 255]],
            'slug' => ['string', ['min', 2], ['max', 100]],
            'description' => ['string', ['max', 1000]],
            'status' => ['string', ['in', ['draft', 'active', 'paused', 'archived']]],
            'type' => ['required', 'string', ['in', ['single_product', 'bundle', 'subscription', 'funnel']]],
            'products' => ['array'],
            'pricing' => ['array'],
            'checkout_config' => ['array'],
            'design_config' => ['array'],
            'theme_config' => ['array'],
            'layout_config' => ['array'],
            'upsells' => ['array'],
            'conversion_tools' => ['array'],
            'targeting' => ['array'],
            'analytics' => ['array'],
            'seo' => ['array'],
            'integrations' => ['array'],
            'domain' => ['string'],
            'active' => ['boolean'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'limits' => ['array'],
            'notifications' => ['array'],
            'tracking' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém produtos da oferta
     */
    public function getProducts(): array
    {
        return $this->products ?? [];
    }

    /**
     * Adiciona produto à oferta
     */
    public function addProduct(array $product): self
    {
        if (!is_array($this->products)) {
            $this->products = [];
        }

        $this->products[] = $product;
        $this->data['products'] = $this->products;

        return $this;
    }

    /**
     * Remove produto da oferta
     */
    public function removeProduct(string $productId): self
    {
        if (is_array($this->products)) {
            $this->products = array_filter(
                $this->products,
                fn ($product) => $product['id'] !== $productId
            );
            $this->products = array_values($this->products);
            $this->data['products'] = $this->products;
        }

        return $this;
    }

    /**
     * Verifica se tem produto específico
     */
    public function hasProduct(string $productId): bool
    {
        foreach ($this->getProducts() as $product) {
            if ($product['id'] === $productId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtém produto específico
     */
    public function getProduct(string $productId): ?array
    {
        foreach ($this->getProducts() as $product) {
            if ($product['id'] === $productId) {
                return $product;
            }
        }
        return null;
    }

    /**
     * Obtém configuração de preços
     */
    public function getPricing(): array
    {
        return $this->pricing ?? [];
    }

    /**
     * Define configuração de preços
     */
    public function setPricing(array $pricing): self
    {
        $this->pricing = $pricing;
        $this->data['pricing'] = $pricing;
        return $this;
    }

    /**
     * Obtém preço total da oferta
     */
    public function getTotalPrice(): float
    {
        $pricing = $this->getPricing();
        return $pricing['total_price'] ?? 0.0;
    }

    /**
     * Obtém preço original (sem desconto)
     */
    public function getOriginalPrice(): float
    {
        $pricing = $this->getPricing();
        return $pricing['original_price'] ?? $this->getTotalPrice();
    }

    /**
     * Obtém percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        $original = $this->getOriginalPrice();
        $total = $this->getTotalPrice();

        if ($original <= 0 || $total >= $original) {
            return 0.0;
        }

        return round((($original - $total) / $original) * 100, 2);
    }

    /**
     * Verifica se está em promoção
     */
    public function isOnSale(): bool
    {
        return $this->getDiscountPercentage() > 0;
    }

    /**
     * Obtém configuração do checkout
     */
    public function getCheckoutConfig(): array
    {
        return $this->checkout_config ?? [];
    }

    /**
     * Define configuração do checkout
     */
    public function setCheckoutConfig(array $config): self
    {
        $this->checkout_config = $config;
        $this->data['checkout_config'] = $config;
        return $this;
    }

    /**
     * Obtém configuração de design
     */
    public function getDesignConfig(): array
    {
        return $this->design_config ?? [];
    }

    /**
     * Define configuração de design
     */
    public function setDesignConfig(array $config): self
    {
        $this->design_config = $config;
        $this->data['design_config'] = $config;
        return $this;
    }

    /**
     * Obtém configuração de tema
     */
    public function getThemeConfig(): array
    {
        return $this->theme_config ?? [];
    }

    /**
     * Define configuração de tema
     */
    public function setThemeConfig(array $config): self
    {
        $this->theme_config = $config;
        $this->data['theme_config'] = $config;
        return $this;
    }

    /**
     * Obtém configuração de layout
     */
    public function getLayoutConfig(): array
    {
        return $this->layout_config ?? [];
    }

    /**
     * Define configuração de layout
     */
    public function setLayoutConfig(array $config): self
    {
        $this->layout_config = $config;
        $this->data['layout_config'] = $config;
        return $this;
    }

    /**
     * Obtém upsells configurados
     */
    public function getUpsells(): array
    {
        return $this->upsells ?? [];
    }

    /**
     * Adiciona upsell
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
     * Remove upsell
     */
    public function removeUpsell(string $upsellId): self
    {
        if (is_array($this->upsells)) {
            $this->upsells = array_filter(
                $this->upsells,
                fn ($upsell) => $upsell['id'] !== $upsellId
            );
            $this->upsells = array_values($this->upsells);
            $this->data['upsells'] = $this->upsells;
        }

        return $this;
    }

    /**
     * Verifica se tem upsells
     */
    public function hasUpsells(): bool
    {
        return !empty($this->getUpsells());
    }

    /**
     * Obtém ferramentas de conversão
     */
    public function getConversionTools(): array
    {
        return $this->conversion_tools ?? [];
    }

    /**
     * Define ferramentas de conversão
     */
    public function setConversionTools(array $tools): self
    {
        $this->conversion_tools = $tools;
        $this->data['conversion_tools'] = $tools;
        return $this;
    }

    /**
     * Verifica se ferramenta de conversão está ativa
     */
    public function hasConversionTool(string $tool): bool
    {
        $tools = $this->getConversionTools();
        return isset($tools[$tool]) && $tools[$tool]['enabled'] === true;
    }

    /**
     * Obtém configuração de targeting
     */
    public function getTargeting(): array
    {
        return $this->targeting ?? [];
    }

    /**
     * Define configuração de targeting
     */
    public function setTargeting(array $targeting): self
    {
        $this->targeting = $targeting;
        $this->data['targeting'] = $targeting;
        return $this;
    }

    /**
     * Obtém analytics configurados
     */
    public function getAnalytics(): array
    {
        return $this->analytics ?? [];
    }

    /**
     * Define analytics
     */
    public function setAnalytics(array $analytics): self
    {
        $this->analytics = $analytics;
        $this->data['analytics'] = $analytics;
        return $this;
    }

    /**
     * Verifica se analytics está habilitado
     */
    public function hasAnalytics(): bool
    {
        $analytics = $this->getAnalytics();
        return !empty($analytics) && ($analytics['enabled'] ?? false);
    }

    /**
     * Obtém configuração SEO
     */
    public function getSeo(): array
    {
        return $this->seo ?? [];
    }

    /**
     * Define configuração SEO
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
        return $this->seo['description'] ?? $this->description ?? '';
    }

    /**
     * Obtém integrações configuradas
     */
    public function getIntegrations(): array
    {
        return $this->integrations ?? [];
    }

    /**
     * Define integrações
     */
    public function setIntegrations(array $integrations): self
    {
        $this->integrations = $integrations;
        $this->data['integrations'] = $integrations;
        return $this;
    }

    /**
     * Verifica se tem integração específica
     */
    public function hasIntegration(string $integration): bool
    {
        $integrations = $this->getIntegrations();
        return isset($integrations[$integration]) && $integrations[$integration]['enabled'] === true;
    }

    /**
     * Obtém limites configurados
     */
    public function getLimits(): array
    {
        return $this->limits ?? [];
    }

    /**
     * Define limites
     */
    public function setLimits(array $limits): self
    {
        $this->limits = $limits;
        $this->data['limits'] = $limits;
        return $this;
    }

    /**
     * Verifica se oferta está ativa
     */
    public function isActive(): bool
    {
        if (!$this->active) {
            return false;
        }

        if ($this->status !== 'active') {
            return false;
        }

        // Verifica data de início
        if ($this->start_date && strtotime($this->start_date) > time()) {
            return false;
        }

        // Verifica data de fim
        if ($this->end_date && strtotime($this->end_date) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se é uma oferta de produto único
     */
    public function isSingleProduct(): bool
    {
        return $this->type === 'single_product';
    }

    /**
     * Verifica se é um bundle
     */
    public function isBundle(): bool
    {
        return $this->type === 'bundle';
    }

    /**
     * Verifica se é uma assinatura
     */
    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    /**
     * Verifica se é um funil
     */
    public function isFunnel(): bool
    {
        return $this->type === 'funnel';
    }

    /**
     * Obtém URL da oferta
     */
    public function getUrl(): string
    {
        $domain = $this->domain ?? 'checkout.clubify.com';
        $slug = $this->slug ?? $this->id;

        return "https://{$domain}/{$slug}";
    }

    /**
     * Obtém dados para exibição pública
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'type' => $this->type,
            'products' => $this->getProducts(),
            'pricing' => $this->getPricing(),
            'total_price' => $this->getTotalPrice(),
            'original_price' => $this->getOriginalPrice(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'is_on_sale' => $this->isOnSale(),
            'checkout_config' => $this->getCheckoutConfig(),
            'design_config' => $this->getDesignConfig(),
            'theme_config' => $this->getThemeConfig(),
            'layout_config' => $this->getLayoutConfig(),
            'has_upsells' => $this->hasUpsells(),
            'conversion_tools' => $this->getConversionTools(),
            'seo_title' => $this->getSeoTitle(),
            'seo_description' => $this->getSeoDescription(),
            'url' => $this->getUrl(),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * Obtém dados para administração
     */
    public function toAdmin(): array
    {
        return array_merge($this->toPublic(), [
            'organization_id' => $this->organization_id,
            'upsells' => $this->getUpsells(),
            'targeting' => $this->getTargeting(),
            'analytics' => $this->getAnalytics(),
            'integrations' => $this->getIntegrations(),
            'limits' => $this->getLimits(),
            'notifications' => $this->notifications,
            'tracking' => $this->tracking,
            'active' => $this->active,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'has_analytics' => $this->hasAnalytics(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ]);
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $organizationId,
        string $name,
        string $type,
        array $products = [],
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'type' => $type,
            'products' => $products,
            'status' => 'draft',
            'active' => false,
            'checkout_config' => [],
            'design_config' => [],
            'theme_config' => [],
            'layout_config' => [],
            'upsells' => [],
            'conversion_tools' => [],
            'targeting' => [],
            'analytics' => ['enabled' => false],
            'seo' => [],
            'integrations' => [],
            'limits' => [],
            'notifications' => [],
            'tracking' => []
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