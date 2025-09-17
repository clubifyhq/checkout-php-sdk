<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Subscriptions\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de assinatura
 *
 * Representa uma assinatura ativa com informações de plano,
 * billing, ciclo de vida e métricas.
 */
class SubscriptionData extends BaseData
{
    public string $id;
    public string $customer_id;
    public string $plan_id;
    public string $status = 'active';
    public float $current_period_amount;
    public DateTime $current_period_start;
    public DateTime $current_period_end;
    public ?DateTime $trial_end = null;
    public ?DateTime $cancel_at = null;
    public ?DateTime $canceled_at = null;
    public string $billing_cycle = 'monthly';
    public string $currency = 'BRL';
    public array $metadata = [];
    public ?string $payment_method_id = null;
    public int $quantity = 1;
    public ?float $discount_amount = null;
    public ?string $coupon_code = null;
    public DateTime $created_at;
    public DateTime $updated_at;
    public ?DateTime $next_billing_date = null;
    public float $total_revenue = 0.0;
    public int $billing_cycles_completed = 0;

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'customer_id' => ['required', 'string'],
            'plan_id' => ['required', 'string'],
            'status' => ['in:active,inactive,canceled,past_due,trialing,paused'],
            'current_period_amount' => ['required', 'numeric', 'min:0'],
            'current_period_start' => ['required', 'date'],
            'current_period_end' => ['required', 'date'],
            'trial_end' => ['nullable', 'date'],
            'cancel_at' => ['nullable', 'date'],
            'canceled_at' => ['nullable', 'date'],
            'billing_cycle' => ['in:daily,weekly,monthly,quarterly,yearly'],
            'currency' => ['string', 'max:3'],
            'metadata' => ['array'],
            'payment_method_id' => ['nullable', 'string'],
            'quantity' => ['integer', 'min:1'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
            'next_billing_date' => ['nullable', 'date'],
            'total_revenue' => ['numeric', 'min:0'],
            'billing_cycles_completed' => ['integer', 'min:0'],
        ];
    }

    /**
     * Verifica se a assinatura está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se está em período de trial
     */
    public function isInTrial(): bool
    {
        return $this->status === 'trialing' || 
               ($this->trial_end && $this->trial_end > new DateTime());
    }

    /**
     * Verifica se foi cancelada
     */
    public function isCanceled(): bool
    {
        return $this->status === 'canceled' || $this->canceled_at !== null;
    }

    /**
     * Verifica se está pausada
     */
    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    /**
     * Verifica se tem pagamento em atraso
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Obtém dias restantes do trial
     */
    public function getTrialDaysRemaining(): int
    {
        if (!$this->trial_end) {
            return 0;
        }
        
        $now = new DateTime();
        if ($this->trial_end <= $now) {
            return 0;
        }
        
        return $now->diff($this->trial_end)->days;
    }

    /**
     * Obtém dias até próxima cobrança
     */
    public function getDaysUntilNextBilling(): int
    {
        $nextBilling = $this->next_billing_date ?? $this->current_period_end;
        $now = new DateTime();
        
        if ($nextBilling <= $now) {
            return 0;
        }
        
        return $now->diff($nextBilling)->days;
    }

    /**
     * Calcula valor com desconto
     */
    public function getDiscountedAmount(): float
    {
        if (!$this->discount_amount) {
            return $this->current_period_amount;
        }
        
        return max(0, $this->current_period_amount - $this->discount_amount);
    }

    /**
     * Calcula valor total da assinatura (quantidade * preço)
     */
    public function getTotalAmount(): float
    {
        return $this->getDiscountedAmount() * $this->quantity;
    }

    /**
     * Obtém Revenue Per User (RPU)
     */
    public function getRevenuePerUser(): float
    {
        if ($this->billing_cycles_completed === 0) {
            return 0.0;
        }
        
        return $this->total_revenue / $this->billing_cycles_completed;
    }

    /**
     * Verifica se pode ser cancelada
     */
    public function canBeCanceled(): bool
    {
        return in_array($this->status, ['active', 'trialing', 'paused', 'past_due']);
    }

    /**
     * Verifica se pode fazer upgrade/downgrade
     */
    public function canChangeplan(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    /**
     * Formata informações para billing
     */
    public function toBillingFormat(): array
    {
        return [
            'subscription_id' => $this->id,
            'customer_id' => $this->customer_id,
            'plan_id' => $this->plan_id,
            'amount' => $this->getTotalAmount(),
            'currency' => $this->currency,
            'billing_cycle' => $this->billing_cycle,
            'next_billing_date' => $this->next_billing_date?->format('Y-m-d'),
            'payment_method_id' => $this->payment_method_id,
            'quantity' => $this->quantity,
            'discount_amount' => $this->discount_amount,
            'status' => $this->status,
        ];
    }

    /**
     * Formata para analytics
     */
    public function toAnalyticsFormat(): array
    {
        return [
            'subscription_id' => $this->id,
            'customer_id' => $this->customer_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'mrr' => $this->calculateMRR(),
            'arr' => $this->calculateARR(),
            'ltv' => $this->calculateLTV(),
            'billing_cycle' => $this->billing_cycle,
            'is_trial' => $this->isInTrial(),
            'days_active' => $this->getDaysActive(),
            'total_revenue' => $this->total_revenue,
            'billing_cycles_completed' => $this->billing_cycles_completed,
        ];
    }

    /**
     * Calcula Monthly Recurring Revenue (MRR)
     */
    public function calculateMRR(): float
    {
        $monthlyAmount = match($this->billing_cycle) {
            'daily' => $this->getTotalAmount() * 30,
            'weekly' => $this->getTotalAmount() * 4.33,
            'monthly' => $this->getTotalAmount(),
            'quarterly' => $this->getTotalAmount() / 3,
            'yearly' => $this->getTotalAmount() / 12,
            default => 0
        };
        
        return round($monthlyAmount, 2);
    }

    /**
     * Calcula Annual Recurring Revenue (ARR)
     */
    public function calculateARR(): float
    {
        return $this->calculateMRR() * 12;
    }

    /**
     * Calcula Lifetime Value (LTV) estimado
     */
    public function calculateLTV(): float
    {
        // LTV simplificado: MRR * 12 (assumindo 1 ano de vida)
        // Em produção seria mais sofisticado
        return $this->calculateMRR() * 12;
    }

    /**
     * Obtém dias ativo
     */
    public function getDaysActive(): int
    {
        return $this->created_at->diff(new DateTime())->days;
    }
}
