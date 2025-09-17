<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Preços
 *
 * Representa configurações avançadas de preços incluindo descontos,
 * parcelamento, moedas, promoções e estratégias de pricing.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de preço
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class PricingData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $offer_id = null;
    public ?string $product_id = null;
    public ?string $name = null;
    public ?string $type = null;
    public ?float $base_price = null;
    public ?float $sale_price = null;
    public ?float $original_price = null;
    public ?string $currency = null;
    public ?array $discounts = null;
    public ?array $installments = null;
    public ?array $promotions = null;
    public ?array $bulk_pricing = null;
    public ?array $dynamic_pricing = null;
    public ?array $geographic_pricing = null;
    public ?array $time_based_pricing = null;
    public ?array $customer_pricing = null;
    public ?array $taxes = null;
    public ?array $fees = null;
    public ?array $shipping = null;
    public ?array $rules = null;
    public ?array $conditions = null;
    public ?array $limits = null;
    public ?bool $active = null;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $priority = null;
    public ?array $metadata = null;
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
            'type' => ['required', 'string', ['in', ['fixed', 'discount', 'promotion', 'dynamic', 'bulk', 'subscription']]],
            'base_price' => ['required', 'numeric', ['min', 0]],
            'sale_price' => ['numeric', ['min', 0]],
            'original_price' => ['numeric', ['min', 0]],
            'currency' => ['string', ['in', ['BRL', 'USD', 'EUR', 'ARS', 'CLP', 'PEN', 'COP', 'MXN']]],
            'offer_id' => ['string'],
            'product_id' => ['string'],
            'discounts' => ['array'],
            'installments' => ['array'],
            'promotions' => ['array'],
            'bulk_pricing' => ['array'],
            'dynamic_pricing' => ['array'],
            'geographic_pricing' => ['array'],
            'time_based_pricing' => ['array'],
            'customer_pricing' => ['array'],
            'taxes' => ['array'],
            'fees' => ['array'],
            'shipping' => ['array'],
            'rules' => ['array'],
            'conditions' => ['array'],
            'limits' => ['array'],
            'active' => ['boolean'],
            'start_date' => ['date'],
            'end_date' => ['date'],
            'priority' => ['integer', ['min', 0], ['max', 100]],
            'metadata' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém preço base
     */
    public function getBasePrice(): float
    {
        return $this->base_price ?? 0.0;
    }

    /**
     * Obtém preço de venda (final)
     */
    public function getSalePrice(): float
    {
        return $this->sale_price ?? $this->getBasePrice();
    }

    /**
     * Obtém preço original (antes de promoções)
     */
    public function getOriginalPrice(): float
    {
        return $this->original_price ?? $this->getBasePrice();
    }

    /**
     * Obtém moeda
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'BRL';
    }

    /**
     * Obtém preço formatado
     */
    public function getFormattedPrice(?float $price = null): string
    {
        $price = $price ?? $this->getSalePrice();
        $currency = $this->getCurrency();

        return match ($currency) {
            'BRL' => 'R$ ' . number_format($price, 2, ',', '.'),
            'USD' => '$' . number_format($price, 2, '.', ','),
            'EUR' => '€' . number_format($price, 2, ',', '.'),
            'ARS' => 'AR$ ' . number_format($price, 2, ',', '.'),
            'CLP' => 'CL$ ' . number_format($price, 0, ',', '.'),
            'PEN' => 'S/ ' . number_format($price, 2, '.', ','),
            'COP' => 'CO$ ' . number_format($price, 0, ',', '.'),
            'MXN' => 'MX$ ' . number_format($price, 2, '.', ','),
            default => number_format($price, 2, '.', ',')
        };
    }

    /**
     * Obtém preço base formatado
     */
    public function getFormattedBasePrice(): string
    {
        return $this->getFormattedPrice($this->getBasePrice());
    }

    /**
     * Obtém preço original formatado
     */
    public function getFormattedOriginalPrice(): string
    {
        return $this->getFormattedPrice($this->getOriginalPrice());
    }

    /**
     * Calcula percentual de desconto
     */
    public function getDiscountPercentage(): float
    {
        $original = $this->getOriginalPrice();
        $sale = $this->getSalePrice();

        if ($original <= 0 || $sale >= $original) {
            return 0.0;
        }

        return round((($original - $sale) / $original) * 100, 2);
    }

    /**
     * Calcula valor do desconto
     */
    public function getDiscountAmount(): float
    {
        return $this->getOriginalPrice() - $this->getSalePrice();
    }

    /**
     * Verifica se está em promoção
     */
    public function isOnSale(): bool
    {
        return $this->getDiscountPercentage() > 0;
    }

    /**
     * Obtém configurações de desconto
     */
    public function getDiscounts(): array
    {
        return $this->discounts ?? [];
    }

    /**
     * Adiciona desconto
     */
    public function addDiscount(array $discount): self
    {
        if (!is_array($this->discounts)) {
            $this->discounts = [];
        }

        $this->discounts[] = $discount;
        $this->data['discounts'] = $this->discounts;

        return $this;
    }

    /**
     * Remove desconto
     */
    public function removeDiscount(string $discountId): self
    {
        if (is_array($this->discounts)) {
            $this->discounts = array_filter(
                $this->discounts,
                fn ($discount) => $discount['id'] !== $discountId
            );
            $this->discounts = array_values($this->discounts);
            $this->data['discounts'] = $this->discounts;
        }

        return $this;
    }

    /**
     * Verifica se tem desconto específico
     */
    public function hasDiscount(string $discountId): bool
    {
        foreach ($this->getDiscounts() as $discount) {
            if ($discount['id'] === $discountId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtém configurações de parcelamento
     */
    public function getInstallments(): array
    {
        return $this->installments ?? [];
    }

    /**
     * Define configurações de parcelamento
     */
    public function setInstallments(array $installments): self
    {
        $this->installments = $installments;
        $this->data['installments'] = $installments;
        return $this;
    }

    /**
     * Verifica se permite parcelamento
     */
    public function allowsInstallments(): bool
    {
        $installments = $this->getInstallments();
        return !empty($installments) && ($installments['enabled'] ?? false);
    }

    /**
     * Obtém número máximo de parcelas
     */
    public function getMaxInstallments(): int
    {
        $installments = $this->getInstallments();
        return $installments['max_installments'] ?? 1;
    }

    /**
     * Calcula valor da parcela
     */
    public function getInstallmentAmount(int $installments = 1): float
    {
        if ($installments <= 0) {
            return 0.0;
        }

        $price = $this->getSalePrice();
        $installmentConfig = $this->getInstallments();

        // Verifica se há juros
        if (!empty($installmentConfig['interest_rate']) && $installments > ($installmentConfig['interest_free_installments'] ?? 1)) {
            $interestRate = $installmentConfig['interest_rate'] / 100;
            $monthlyRate = $interestRate / 12;

            // Fórmula de juros compostos
            $amount = $price * ($monthlyRate * pow(1 + $monthlyRate, $installments)) / (pow(1 + $monthlyRate, $installments) - 1);
        } else {
            $amount = $price / $installments;
        }

        return round($amount, 2);
    }

    /**
     * Obtém valor total com parcelas (incluindo juros)
     */
    public function getTotalWithInstallments(int $installments = 1): float
    {
        return $this->getInstallmentAmount($installments) * $installments;
    }

    /**
     * Obtém promoções ativas
     */
    public function getPromotions(): array
    {
        return $this->promotions ?? [];
    }

    /**
     * Define promoções
     */
    public function setPromotions(array $promotions): self
    {
        $this->promotions = $promotions;
        $this->data['promotions'] = $promotions;
        return $this;
    }

    /**
     * Verifica se tem promoções ativas
     */
    public function hasActivePromotions(): bool
    {
        foreach ($this->getPromotions() as $promotion) {
            if ($promotion['active'] && $this->isPromotionValid($promotion)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Verifica se promoção é válida
     */
    private function isPromotionValid(array $promotion): bool
    {
        $now = time();

        if (!empty($promotion['start_date']) && strtotime($promotion['start_date']) > $now) {
            return false;
        }

        if (!empty($promotion['end_date']) && strtotime($promotion['end_date']) < $now) {
            return false;
        }

        return true;
    }

    /**
     * Obtém configuração de preços em massa
     */
    public function getBulkPricing(): array
    {
        return $this->bulk_pricing ?? [];
    }

    /**
     * Define preços em massa
     */
    public function setBulkPricing(array $bulkPricing): self
    {
        $this->bulk_pricing = $bulkPricing;
        $this->data['bulk_pricing'] = $bulkPricing;
        return $this;
    }

    /**
     * Calcula preço baseado na quantidade
     */
    public function getPriceForQuantity(int $quantity): float
    {
        $bulkPricing = $this->getBulkPricing();

        if (empty($bulkPricing) || $quantity <= 0) {
            return $this->getSalePrice();
        }

        $basePrice = $this->getSalePrice();
        $bestPrice = $basePrice;

        foreach ($bulkPricing as $tier) {
            if ($quantity >= $tier['min_quantity']) {
                if ($tier['type'] === 'percentage') {
                    $discountedPrice = $basePrice * (1 - $tier['discount'] / 100);
                } else {
                    $discountedPrice = $tier['price'];
                }

                if ($discountedPrice < $bestPrice) {
                    $bestPrice = $discountedPrice;
                }
            }
        }

        return $bestPrice;
    }

    /**
     * Obtém configuração de preços dinâmicos
     */
    public function getDynamicPricing(): array
    {
        return $this->dynamic_pricing ?? [];
    }

    /**
     * Define preços dinâmicos
     */
    public function setDynamicPricing(array $dynamicPricing): self
    {
        $this->dynamic_pricing = $dynamicPricing;
        $this->data['dynamic_pricing'] = $dynamicPricing;
        return $this;
    }

    /**
     * Obtém configuração de preços geográficos
     */
    public function getGeographicPricing(): array
    {
        return $this->geographic_pricing ?? [];
    }

    /**
     * Define preços geográficos
     */
    public function setGeographicPricing(array $geographicPricing): self
    {
        $this->geographic_pricing = $geographicPricing;
        $this->data['geographic_pricing'] = $geographicPricing;
        return $this;
    }

    /**
     * Obtém preço para país específico
     */
    public function getPriceForCountry(string $countryCode): float
    {
        $geoPricing = $this->getGeographicPricing();

        if (isset($geoPricing[$countryCode])) {
            return $geoPricing[$countryCode]['price'] ?? $this->getSalePrice();
        }

        return $this->getSalePrice();
    }

    /**
     * Obtém configuração de preços baseados em tempo
     */
    public function getTimeBasedPricing(): array
    {
        return $this->time_based_pricing ?? [];
    }

    /**
     * Define preços baseados em tempo
     */
    public function setTimeBasedPricing(array $timeBasedPricing): self
    {
        $this->time_based_pricing = $timeBasedPricing;
        $this->data['time_based_pricing'] = $timeBasedPricing;
        return $this;
    }

    /**
     * Obtém preço para horário específico
     */
    public function getPriceForTime(\DateTime $dateTime = null): float
    {
        $dateTime = $dateTime ?? new \DateTime();
        $timePricing = $this->getTimeBasedPricing();

        if (empty($timePricing)) {
            return $this->getSalePrice();
        }

        $hour = (int)$dateTime->format('H');
        $dayOfWeek = (int)$dateTime->format('w');

        foreach ($timePricing as $rule) {
            if (isset($rule['hours']) && in_array($hour, $rule['hours'])) {
                return $rule['price'] ?? $this->getSalePrice();
            }

            if (isset($rule['days']) && in_array($dayOfWeek, $rule['days'])) {
                return $rule['price'] ?? $this->getSalePrice();
            }
        }

        return $this->getSalePrice();
    }

    /**
     * Obtém configuração de preços por cliente
     */
    public function getCustomerPricing(): array
    {
        return $this->customer_pricing ?? [];
    }

    /**
     * Define preços por cliente
     */
    public function setCustomerPricing(array $customerPricing): self
    {
        $this->customer_pricing = $customerPricing;
        $this->data['customer_pricing'] = $customerPricing;
        return $this;
    }

    /**
     * Obtém preço para cliente específico
     */
    public function getPriceForCustomer(string $customerId): float
    {
        $customerPricing = $this->getCustomerPricing();

        if (isset($customerPricing[$customerId])) {
            return $customerPricing[$customerId]['price'] ?? $this->getSalePrice();
        }

        return $this->getSalePrice();
    }

    /**
     * Obtém configuração de taxas
     */
    public function getTaxes(): array
    {
        return $this->taxes ?? [];
    }

    /**
     * Define taxas
     */
    public function setTaxes(array $taxes): self
    {
        $this->taxes = $taxes;
        $this->data['taxes'] = $taxes;
        return $this;
    }

    /**
     * Calcula total de taxas
     */
    public function getTotalTaxes(): float
    {
        $taxes = $this->getTaxes();
        $totalTax = 0.0;
        $baseAmount = $this->getSalePrice();

        foreach ($taxes as $tax) {
            if ($tax['type'] === 'percentage') {
                $totalTax += $baseAmount * ($tax['rate'] / 100);
            } else {
                $totalTax += $tax['amount'];
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
     * Define taxas adicionais
     */
    public function setFees(array $fees): self
    {
        $this->fees = $fees;
        $this->data['fees'] = $fees;
        return $this;
    }

    /**
     * Calcula total de taxas adicionais
     */
    public function getTotalFees(): float
    {
        $fees = $this->getFees();
        $totalFees = 0.0;
        $baseAmount = $this->getSalePrice();

        foreach ($fees as $fee) {
            if ($fee['type'] === 'percentage') {
                $totalFees += $baseAmount * ($fee['rate'] / 100);
            } else {
                $totalFees += $fee['amount'];
            }
        }

        return $totalFees;
    }

    /**
     * Obtém configuração de frete
     */
    public function getShipping(): array
    {
        return $this->shipping ?? [];
    }

    /**
     * Define configuração de frete
     */
    public function setShipping(array $shipping): self
    {
        $this->shipping = $shipping;
        $this->data['shipping'] = $shipping;
        return $this;
    }

    /**
     * Calcula preço total (com taxas e frete)
     */
    public function getTotalPrice(): float
    {
        $basePrice = $this->getSalePrice();
        $taxes = $this->getTotalTaxes();
        $fees = $this->getTotalFees();

        $shipping = $this->getShipping();
        $shippingCost = $shipping['cost'] ?? 0.0;

        return $basePrice + $taxes + $fees + $shippingCost;
    }

    /**
     * Verifica se o pricing está ativo
     */
    public function isActive(): bool
    {
        if (!$this->active) {
            return false;
        }

        $now = time();

        if ($this->start_date && strtotime($this->start_date) > $now) {
            return false;
        }

        if ($this->end_date && strtotime($this->end_date) < $now) {
            return false;
        }

        return true;
    }

    /**
     * Verifica se é pricing fixo
     */
    public function isFixed(): bool
    {
        return $this->type === 'fixed';
    }

    /**
     * Verifica se é pricing de desconto
     */
    public function isDiscount(): bool
    {
        return $this->type === 'discount';
    }

    /**
     * Verifica se é pricing promocional
     */
    public function isPromotion(): bool
    {
        return $this->type === 'promotion';
    }

    /**
     * Verifica se é pricing dinâmico
     */
    public function isDynamic(): bool
    {
        return $this->type === 'dynamic';
    }

    /**
     * Verifica se é pricing em massa
     */
    public function isBulk(): bool
    {
        return $this->type === 'bulk';
    }

    /**
     * Verifica se é pricing de assinatura
     */
    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    /**
     * Obtém dados para exibição
     */
    public function toDisplay(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'base_price' => $this->getBasePrice(),
            'sale_price' => $this->getSalePrice(),
            'original_price' => $this->getOriginalPrice(),
            'currency' => $this->getCurrency(),
            'formatted_price' => $this->getFormattedPrice(),
            'formatted_base_price' => $this->getFormattedBasePrice(),
            'formatted_original_price' => $this->getFormattedOriginalPrice(),
            'discount_percentage' => $this->getDiscountPercentage(),
            'discount_amount' => $this->getDiscountAmount(),
            'is_on_sale' => $this->isOnSale(),
            'allows_installments' => $this->allowsInstallments(),
            'max_installments' => $this->getMaxInstallments(),
            'total_taxes' => $this->getTotalTaxes(),
            'total_fees' => $this->getTotalFees(),
            'total_price' => $this->getTotalPrice(),
            'has_active_promotions' => $this->hasActivePromotions(),
            'is_active' => $this->isActive()
        ];
    }

    /**
     * Cria instância com preço fixo
     */
    public static function createFixed(
        string $organizationId,
        string $name,
        float $price,
        string $currency = 'BRL',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'type' => 'fixed',
            'base_price' => $price,
            'sale_price' => $price,
            'original_price' => $price,
            'currency' => $currency,
            'active' => true,
            'priority' => 50,
            'discounts' => [],
            'installments' => ['enabled' => false],
            'promotions' => [],
            'taxes' => [],
            'fees' => [],
            'shipping' => []
        ], $additionalData));
    }

    /**
     * Cria instância com desconto
     */
    public static function createWithDiscount(
        string $organizationId,
        string $name,
        float $originalPrice,
        float $salePrice,
        string $currency = 'BRL',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'name' => $name,
            'type' => 'discount',
            'base_price' => $originalPrice,
            'sale_price' => $salePrice,
            'original_price' => $originalPrice,
            'currency' => $currency,
            'active' => true,
            'priority' => 70,
            'discounts' => [],
            'installments' => ['enabled' => false],
            'promotions' => [],
            'taxes' => [],
            'fees' => [],
            'shipping' => []
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
