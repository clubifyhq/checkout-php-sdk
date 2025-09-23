<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO para dados de Upsell
 *
 * Representa um upsell com todas as configurações de exibição,
 * targeting, sequência e análise de performance.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de upsell
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class UpsellData extends BaseData
{
    public ?string $id = null;
    public ?string $offer_id = null;
    public ?string $product_id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $type = null;
    public ?string $status = null;
    public ?float $price = null;
    public ?float $original_price = null;
    public ?float $discount_percentage = null;
    public ?int $sequence_order = null;
    public ?array $display_settings = null;
    public ?array $timing_rules = null;
    public ?array $targeting_rules = null;
    public ?array $template_config = null;
    public ?array $conversion_tracking = null;
    public ?array $analytics = null;
    public ?array $ab_testing = null;
    public ?array $automation_rules = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'offer_id' => ['string'],
            'product_id' => ['required', 'string'],
            'name' => ['string', ['max', 255]],
            'description' => ['string', ['max', 1000]],
            'type' => ['required', 'string', ['in', ['one_time_offer', 'downsell', 'cross_sell', 'subscription_upgrade', 'addon']]],
            'status' => ['string', ['in', ['active', 'inactive', 'paused']]],
            'price' => ['numeric', ['min', 0]],
            'original_price' => ['numeric', ['min', 0]],
            'discount_percentage' => ['numeric', ['min', 0], ['max', 100]],
            'sequence_order' => ['integer', ['min', 1]],
            'display_settings' => ['array'],
            'timing_rules' => ['array'],
            'targeting_rules' => ['array'],
            'template_config' => ['array'],
            'conversion_tracking' => ['array'],
            'analytics' => ['array'],
            'ab_testing' => ['array'],
            'automation_rules' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém configurações de exibição
     */
    public function getDisplaySettings(): array
    {
        return $this->display_settings ?? [];
    }

    /**
     * Define configurações de exibição
     */
    public function setDisplaySettings(array $settings): self
    {
        $this->display_settings = $settings;
        $this->data['display_settings'] = $settings;
        return $this;
    }

    /**
     * Obtém regras de timing
     */
    public function getTimingRules(): array
    {
        return $this->timing_rules ?? [];
    }

    /**
     * Define regras de timing
     */
    public function setTimingRules(array $rules): self
    {
        $this->timing_rules = $rules;
        $this->data['timing_rules'] = $rules;
        return $this;
    }

    /**
     * Obtém regras de targeting
     */
    public function getTargetingRules(): array
    {
        return $this->targeting_rules ?? [];
    }

    /**
     * Define regras de targeting
     */
    public function setTargetingRules(array $rules): self
    {
        $this->targeting_rules = $rules;
        $this->data['targeting_rules'] = $rules;
        return $this;
    }

    /**
     * Obtém configuração do template
     */
    public function getTemplateConfig(): array
    {
        return $this->template_config ?? [];
    }

    /**
     * Define configuração do template
     */
    public function setTemplateConfig(array $config): self
    {
        $this->template_config = $config;
        $this->data['template_config'] = $config;
        return $this;
    }

    /**
     * Obtém configuração de tracking de conversão
     */
    public function getConversionTracking(): array
    {
        return $this->conversion_tracking ?? [];
    }

    /**
     * Define configuração de tracking de conversão
     */
    public function setConversionTracking(array $tracking): self
    {
        $this->conversion_tracking = $tracking;
        $this->data['conversion_tracking'] = $tracking;
        return $this;
    }

    /**
     * Obtém analytics do upsell
     */
    public function getAnalytics(): array
    {
        return $this->analytics ?? [];
    }

    /**
     * Define analytics do upsell
     */
    public function setAnalytics(array $analytics): self
    {
        $this->analytics = $analytics;
        $this->data['analytics'] = $analytics;
        return $this;
    }

    /**
     * Obtém configuração de A/B testing
     */
    public function getAbTesting(): array
    {
        return $this->ab_testing ?? [];
    }

    /**
     * Define configuração de A/B testing
     */
    public function setAbTesting(array $testing): self
    {
        $this->ab_testing = $testing;
        $this->data['ab_testing'] = $testing;
        return $this;
    }

    /**
     * Obtém regras de automação
     */
    public function getAutomationRules(): array
    {
        return $this->automation_rules ?? [];
    }

    /**
     * Define regras de automação
     */
    public function setAutomationRules(array $rules): self
    {
        $this->automation_rules = $rules;
        $this->data['automation_rules'] = $rules;
        return $this;
    }

    /**
     * Calcula preço com desconto
     */
    public function getDiscountedPrice(): float
    {
        if ($this->discount_percentage && $this->original_price) {
            return $this->original_price * (1 - $this->discount_percentage / 100);
        }
        return $this->price ?? 0.0;
    }

    /**
     * Obtém valor do desconto
     */
    public function getDiscountAmount(): float
    {
        if ($this->discount_percentage && $this->original_price) {
            return $this->original_price - $this->getDiscountedPrice();
        }
        return 0.0;
    }

    /**
     * Verifica se tem desconto
     */
    public function hasDiscount(): bool
    {
        return $this->discount_percentage > 0;
    }

    /**
     * Verifica se está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se está pausado
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Verifica se está inativo
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * Verifica se é one time offer
     */
    public function isOneTimeOffer(): bool
    {
        return $this->type === 'one_time_offer';
    }

    /**
     * Verifica se é downsell
     */
    public function isDownsell(): bool
    {
        return $this->type === 'downsell';
    }

    /**
     * Verifica se é cross sell
     */
    public function isCrossSell(): bool
    {
        return $this->type === 'cross_sell';
    }

    /**
     * Verifica se é upgrade de assinatura
     */
    public function isSubscriptionUpgrade(): bool
    {
        return $this->type === 'subscription_upgrade';
    }

    /**
     * Verifica se é addon
     */
    public function isAddon(): bool
    {
        return $this->type === 'addon';
    }

    /**
     * Obtém taxa de conversão
     */
    public function getConversionRate(): float
    {
        $analytics = $this->getAnalytics();
        return $analytics['conversion_rate'] ?? 0.0;
    }

    /**
     * Obtém número de impressões
     */
    public function getImpressions(): int
    {
        $analytics = $this->getAnalytics();
        return $analytics['impressions'] ?? 0;
    }

    /**
     * Obtém número de conversões
     */
    public function getConversions(): int
    {
        $analytics = $this->getAnalytics();
        return $analytics['conversions'] ?? 0;
    }

    /**
     * Obtém receita gerada
     */
    public function getRevenueGenerated(): float
    {
        $analytics = $this->getAnalytics();
        return $analytics['revenue_generated'] ?? 0.0;
    }

    /**
     * Verifica se tem A/B testing ativo
     */
    public function hasActiveAbTesting(): bool
    {
        $testing = $this->getAbTesting();
        return !empty($testing) && ($testing['status'] ?? '') === 'active';
    }

    /**
     * Verifica se tem automação configurada
     */
    public function hasAutomation(): bool
    {
        $rules = $this->getAutomationRules();
        return !empty($rules);
    }

    /**
     * Obtém tipo de template
     */
    public function getTemplateType(): string
    {
        $config = $this->getTemplateConfig();
        return $config['type'] ?? 'standard';
    }

    /**
     * Obtém dados para exibição pública
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'price' => $this->price,
            'original_price' => $this->original_price,
            'discount_percentage' => $this->discount_percentage,
            'discounted_price' => $this->getDiscountedPrice(),
            'discount_amount' => $this->getDiscountAmount(),
            'has_discount' => $this->hasDiscount(),
            'sequence_order' => $this->sequence_order,
            'display_settings' => $this->getDisplaySettings(),
            'template_type' => $this->getTemplateType(),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * Obtém dados para administração
     */
    public function toAdmin(): array
    {
        return array_merge($this->toPublic(), [
            'offer_id' => $this->offer_id,
            'product_id' => $this->product_id,
            'status' => $this->status,
            'timing_rules' => $this->getTimingRules(),
            'targeting_rules' => $this->getTargetingRules(),
            'template_config' => $this->getTemplateConfig(),
            'conversion_tracking' => $this->getConversionTracking(),
            'analytics' => $this->getAnalytics(),
            'ab_testing' => $this->getAbTesting(),
            'automation_rules' => $this->getAutomationRules(),
            'conversion_rate' => $this->getConversionRate(),
            'impressions' => $this->getImpressions(),
            'conversions' => $this->getConversions(),
            'revenue_generated' => $this->getRevenueGenerated(),
            'has_active_ab_testing' => $this->hasActiveAbTesting(),
            'has_automation' => $this->hasAutomation(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ]);
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $productId,
        string $type,
        string $name = '',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'product_id' => $productId,
            'type' => $type,
            'name' => $name,
            'status' => 'active',
            'sequence_order' => 1,
            'display_settings' => [],
            'timing_rules' => ['show_after' => 2, 'hide_after' => 30],
            'targeting_rules' => [],
            'template_config' => ['type' => 'standard'],
            'conversion_tracking' => ['enabled' => true],
            'analytics' => [
                'impressions' => 0,
                'conversions' => 0,
                'conversion_rate' => 0,
                'revenue_generated' => 0
            ],
            'ab_testing' => [],
            'automation_rules' => []
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