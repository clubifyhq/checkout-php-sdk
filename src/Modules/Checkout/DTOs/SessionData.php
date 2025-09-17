<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Checkout\DTOs;

use ClubifyCheckout\Data\BaseData;

/**
 * DTO para dados de Sessão de Checkout
 *
 * Representa uma sessão de checkout com todos os dados
 * necessários para o processo completo.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de sessão
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class SessionData extends BaseData
{
    public ?string $id = null;
    public ?string $organization_id = null;
    public ?string $token = null;
    public ?string $type = null;
    public ?string $status = null;
    public ?string $current_step = null;
    public ?array $customer_data = null;
    public ?array $product_data = null;
    public ?array $cart_data = null;
    public ?array $payment_data = null;
    public ?array $shipping_data = null;
    public ?array $billing_data = null;
    public ?array $flow_config = null;
    public ?array $step_data = null;
    public ?array $completed_steps = null;
    public ?array $events = null;
    public ?array $metadata = null;
    public ?array $tracking = null;
    public ?string $user_agent = null;
    public ?string $ip_address = null;
    public ?string $referrer = null;
    public ?string $utm_source = null;
    public ?string $utm_medium = null;
    public ?string $utm_campaign = null;
    public ?string $cart_id = null;
    public ?string $order_id = null;
    public ?string $expires_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'organization_id' => ['required', 'string', ['min', 1]],
            'token' => ['string', ['min', 10]],
            'type' => ['string', ['in', ['standard', 'one_click', 'subscription', 'recurring']]],
            'status' => ['string', ['in', ['initiated', 'active', 'processing', 'completed', 'abandoned', 'expired', 'failed', 'error']]],
            'current_step' => ['string'],
            'customer_data' => ['array'],
            'product_data' => ['array'],
            'cart_data' => ['array'],
            'payment_data' => ['array'],
            'shipping_data' => ['array'],
            'billing_data' => ['array'],
            'flow_config' => ['array'],
            'step_data' => ['array'],
            'completed_steps' => ['array'],
            'events' => ['array'],
            'metadata' => ['array'],
            'tracking' => ['array'],
            'user_agent' => ['string', ['max', 500]],
            'ip_address' => ['string', ['max', 45]],
            'referrer' => ['string', ['max', 1000]],
            'utm_source' => ['string', ['max', 100]],
            'utm_medium' => ['string', ['max', 100]],
            'utm_campaign' => ['string', ['max', 100]],
            'cart_id' => ['string'],
            'order_id' => ['string'],
            'expires_at' => ['date'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém dados do cliente
     */
    public function getCustomerData(): array
    {
        return $this->customer_data ?? [];
    }

    /**
     * Define dados do cliente
     */
    public function setCustomerData(array $customerData): self
    {
        $this->customer_data = $customerData;
        $this->data['customer_data'] = $customerData;
        return $this;
    }

    /**
     * Obtém email do cliente
     */
    public function getCustomerEmail(): ?string
    {
        return $this->customer_data['email'] ?? null;
    }

    /**
     * Obtém nome do cliente
     */
    public function getCustomerName(): ?string
    {
        return $this->customer_data['name'] ?? null;
    }

    /**
     * Obtém telefone do cliente
     */
    public function getCustomerPhone(): ?string
    {
        return $this->customer_data['phone'] ?? null;
    }

    /**
     * Obtém dados do produto
     */
    public function getProductData(): array
    {
        return $this->product_data ?? [];
    }

    /**
     * Define dados do produto
     */
    public function setProductData(array $productData): self
    {
        $this->product_data = $productData;
        $this->data['product_data'] = $productData;
        return $this;
    }

    /**
     * Obtém dados do carrinho
     */
    public function getCartData(): array
    {
        return $this->cart_data ?? [];
    }

    /**
     * Define dados do carrinho
     */
    public function setCartData(array $cartData): self
    {
        $this->cart_data = $cartData;
        $this->data['cart_data'] = $cartData;
        return $this;
    }

    /**
     * Obtém dados de pagamento
     */
    public function getPaymentData(): array
    {
        return $this->payment_data ?? [];
    }

    /**
     * Define dados de pagamento
     */
    public function setPaymentData(array $paymentData): self
    {
        $this->payment_data = $paymentData;
        $this->data['payment_data'] = $paymentData;
        return $this;
    }

    /**
     * Obtém método de pagamento
     */
    public function getPaymentMethod(): ?string
    {
        return $this->payment_data['method'] ?? null;
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
     * Obtém configuração do flow
     */
    public function getFlowConfig(): array
    {
        return $this->flow_config ?? [];
    }

    /**
     * Define configuração do flow
     */
    public function setFlowConfig(array $flowConfig): self
    {
        $this->flow_config = $flowConfig;
        $this->data['flow_config'] = $flowConfig;
        return $this;
    }

    /**
     * Obtém dados do step
     */
    public function getStepData(string $step = null): array
    {
        if ($step) {
            return $this->step_data[$step] ?? [];
        }
        return $this->step_data ?? [];
    }

    /**
     * Define dados do step
     */
    public function setStepData(string $step, array $data): self
    {
        if (!is_array($this->step_data)) {
            $this->step_data = [];
        }

        $this->step_data[$step] = $data;
        $this->data['step_data'] = $this->step_data;
        return $this;
    }

    /**
     * Obtém steps completados
     */
    public function getCompletedSteps(): array
    {
        return $this->completed_steps ?? [];
    }

    /**
     * Adiciona step como completado
     */
    public function addCompletedStep(string $step): self
    {
        if (!is_array($this->completed_steps)) {
            $this->completed_steps = [];
        }

        if (!in_array($step, $this->completed_steps)) {
            $this->completed_steps[] = $step;
            $this->data['completed_steps'] = $this->completed_steps;
        }

        return $this;
    }

    /**
     * Verifica se step foi completado
     */
    public function isStepCompleted(string $step): bool
    {
        return in_array($step, $this->getCompletedSteps());
    }

    /**
     * Obtém eventos da sessão
     */
    public function getEvents(): array
    {
        return $this->events ?? [];
    }

    /**
     * Adiciona evento à sessão
     */
    public function addEvent(array $event): self
    {
        if (!is_array($this->events)) {
            $this->events = [];
        }

        $event['timestamp'] = $event['timestamp'] ?? time();
        $event['id'] = $event['id'] ?? uniqid('evt_');

        $this->events[] = $event;
        $this->data['events'] = $this->events;

        return $this;
    }

    /**
     * Obtém último evento
     */
    public function getLastEvent(): ?array
    {
        $events = $this->getEvents();
        return !empty($events) ? end($events) : null;
    }

    /**
     * Obtém eventos por tipo
     */
    public function getEventsByType(string $type): array
    {
        return array_filter($this->getEvents(), fn($event) => $event['type'] === $type);
    }

    /**
     * Obtém dados de tracking
     */
    public function getTracking(): array
    {
        return $this->tracking ?? [];
    }

    /**
     * Define dados de tracking
     */
    public function setTracking(array $tracking): self
    {
        $this->tracking = $tracking;
        $this->data['tracking'] = $tracking;
        return $this;
    }

    /**
     * Obtém parâmetros UTM
     */
    public function getUtmParameters(): array
    {
        return [
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign
        ];
    }

    /**
     * Define parâmetros UTM
     */
    public function setUtmParameters(string $source = null, string $medium = null, string $campaign = null): self
    {
        $this->utm_source = $source;
        $this->utm_medium = $medium;
        $this->utm_campaign = $campaign;

        $this->data['utm_source'] = $source;
        $this->data['utm_medium'] = $medium;
        $this->data['utm_campaign'] = $campaign;

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
     * Obtém valor de metadado específico
     */
    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Define valor de metadado específico
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
     * Verifica se sessão é one-click
     */
    public function isOneClick(): bool
    {
        return $this->type === 'one_click';
    }

    /**
     * Verifica se sessão é padrão
     */
    public function isStandard(): bool
    {
        return $this->type === 'standard';
    }

    /**
     * Verifica se sessão é de assinatura
     */
    public function isSubscription(): bool
    {
        return $this->type === 'subscription';
    }

    /**
     * Verifica se sessão está ativa
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se sessão foi completada
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Verifica se sessão foi abandonada
     */
    public function isAbandoned(): bool
    {
        return $this->status === 'abandoned';
    }

    /**
     * Verifica se sessão expirou
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
     * Verifica se sessão está válida
     */
    public function isValid(): bool
    {
        return $this->isActive() && !$this->isExpired();
    }

    /**
     * Obtém tempo restante em segundos
     */
    public function getTimeRemaining(): int
    {
        if (!$this->expires_at) {
            return -1; // Sem expiração
        }

        $remaining = strtotime($this->expires_at) - time();
        return max(0, $remaining);
    }

    /**
     * Obtém progresso da sessão (0-100)
     */
    public function getProgress(): float
    {
        $flowConfig = $this->getFlowConfig();
        $steps = $flowConfig['steps'] ?? [];

        if (empty($steps)) {
            return 0.0;
        }

        $completedSteps = count($this->getCompletedSteps());
        return round(($completedSteps / count($steps)) * 100, 2);
    }

    /**
     * Obtém resumo da sessão
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'type' => $this->type,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'progress' => $this->getProgress(),
            'customer_email' => $this->getCustomerEmail(),
            'payment_method' => $this->getPaymentMethod(),
            'is_valid' => $this->isValid(),
            'time_remaining' => $this->getTimeRemaining(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Cria instância para criação
     */
    public static function forCreation(string $organizationId, array $data = []): self
    {
        return new self(array_merge([
            'organization_id' => $organizationId,
            'type' => 'standard',
            'status' => 'initiated',
            'current_step' => 'product_selection',
            'customer_data' => [],
            'product_data' => [],
            'cart_data' => [],
            'payment_data' => [],
            'shipping_data' => [],
            'billing_data' => [],
            'flow_config' => [],
            'step_data' => [],
            'completed_steps' => [],
            'events' => [],
            'metadata' => [],
            'tracking' => []
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