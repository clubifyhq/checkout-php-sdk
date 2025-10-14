<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para planos de assinatura
 */
class SubscriptionPlanData extends BaseData
{
    public string $id;
    public string $name;
    public ?string $description = null;
    public string $tier = 'basic'; // Tier: basic, standard, premium, enterprise
    public float $amount;
    public string $currency = 'BRL';
    public string $billing_cycle = 'monthly';
    public ?int $trial_days = null;
    public bool $is_active = true;
    public array $features = [];
    public array $prices = []; // Array de preços (suporte para múltiplos preços)
    public array $metadata = [];
    public DateTime $created_at;
    public DateTime $updated_at;

    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'tier' => ['in:basic,standard,premium,enterprise'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['string', 'max:3'],
            'billing_cycle' => ['in:daily,weekly,monthly,quarterly,yearly'],
            'trial_days' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'features' => ['array'],
            'prices' => ['array'],
            'metadata' => ['array'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ];
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features);
    }

    public function calculateMRR(): float
    {
        return match($this->billing_cycle) {
            'daily' => $this->amount * 30,
            'weekly' => $this->amount * 4.33,
            'monthly' => $this->amount,
            'quarterly' => $this->amount / 3,
            'yearly' => $this->amount / 12,
            default => 0
        };
    }
}
