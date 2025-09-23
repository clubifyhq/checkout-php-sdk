<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO Aprimorado para dados de Carrinho
 *
 * Extensão do DTO original com funcionalidades adicionais
 * para suporte ao novo Cart Module e integração API.
 *
 * Funcionalidades adicionais:
 * - Suporte a navegação de fluxos
 * - Promoções avançadas
 * - Dados de one-click
 * - Analytics e tracking
 * - Validações aprimoradas
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de carrinho
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class CartData extends BaseData
{
    // Campos básicos do carrinho
    public ?string $id = null;
    public ?string $session_id = null;
    public ?string $customer_id = null;
    public ?string $organization_id = null;
    public ?string $type = null;
    public ?string $status = null;
    public ?array $items = null;
    public ?array $totals = null;

    // Campos de promoções (legacy + novo)
    public ?array $coupon = null;
    public ?array $promotions = null;
    public ?array $discounts = null;

    // Campos de dados adicionais
    public ?array $shipping_data = null;
    public ?array $billing_data = null;
    public ?array $taxes = null;
    public ?array $fees = null;
    public ?string $currency = null;
    public ?array $metadata = null;

    // Campos de navegação e fluxo
    public ?string $navigation_id = null;
    public ?array $flow_data = null;
    public ?string $offer_id = null;

    // Campos de one-click
    public ?bool $one_click_eligible = null;
    public ?array $saved_payment_methods = null;

    // Campos de conversão e analytics
    public ?string $order_id = null;
    public ?string $transaction_id = null;
    public ?array $analytics_data = null;

    // Campos de tracking
    public ?string $utm_source = null;
    public ?string $utm_campaign = null;
    public ?string $utm_medium = null;
    public ?string $referrer = null;

    // Campos de tempo
    public ?string $expires_at = null;
    public ?string $abandoned_at = null;
    public ?string $converted_at = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação aprimoradas
     */
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'session_id' => ['required', 'string', ['min', 1]],
            'customer_id' => ['string'],
            'organization_id' => ['string'],
            'type' => ['string', ['in', ['standard', 'one_click', 'subscription', 'recurring', 'flow']]],
            'status' => ['string', ['in', ['active', 'processing', 'completed', 'abandoned', 'expired', 'converting']]],
            'items' => ['array'],
            'totals' => ['array'],
            'coupon' => ['array'],
            'promotions' => ['array'],
            'discounts' => ['array'],
            'shipping_data' => ['array'],
            'billing_data' => ['array'],
            'taxes' => ['array'],
            'fees' => ['array'],
            'currency' => ['string', ['in', ['BRL', 'USD', 'EUR', 'ARS', 'CLP', 'PEN', 'COP', 'MXN']]],
            'metadata' => ['array'],
            'navigation_id' => ['string'],
            'flow_data' => ['array'],
            'offer_id' => ['string'],
            'one_click_eligible' => ['boolean'],
            'saved_payment_methods' => ['array'],
            'order_id' => ['string'],
            'transaction_id' => ['string'],
            'analytics_data' => ['array'],
            'utm_source' => ['string'],
            'utm_campaign' => ['string'],
            'utm_medium' => ['string'],
            'referrer' => ['string'],
            'expires_at' => ['date'],
            'abandoned_at' => ['date'],
            'converted_at' => ['date'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ]);
    }

    // ===========================================
    // MÉTODOS DE NAVEGAÇÃO E FLUXO
    // ===========================================

    /**
     * Verifica se carrinho está em navegação de fluxo
     */
    public function isInFlow(): bool
    {
        return !empty($this->navigation_id) || !empty($this->flow_data);
    }

    /**
     * Obtém dados de navegação
     */
    public function getFlowData(): array
    {
        return $this->flow_data ?? [];
    }

    /**
     * Define dados de navegação
     */
    public function setFlowData(array $flowData): self
    {
        $this->flow_data = $flowData;
        $this->data['flow_data'] = $flowData;
        return $this;
    }

    /**
     * Obtém ID da navegação atual
     */
    public function getNavigationId(): ?string
    {
        return $this->navigation_id;
    }

    /**
     * Define ID da navegação
     */
    public function setNavigationId(string $navigationId): self
    {
        $this->navigation_id = $navigationId;
        $this->data['navigation_id'] = $navigationId;
        return $this;
    }

    /**
     * Verifica se é carrinho de fluxo específico
     */
    public function isFlowCart(): bool
    {
        return $this->type === 'flow';
    }

    // ===========================================
    // MÉTODOS DE PROMOÇÕES AVANÇADAS
    // ===========================================

    /**
     * Obtém todas as promoções (legacy + modernas)
     */
    public function getAllPromotions(): array
    {
        $allPromotions = [];

        // Promoções modernas
        if (!empty($this->promotions)) {
            if (is_array($this->promotions[0] ?? null)) {
                $allPromotions = array_merge($allPromotions, $this->promotions);
            } else {
                $allPromotions[] = $this->promotions;
            }
        }

        // Cupom legacy
        if (!empty($this->coupon)) {
            $allPromotions[] = [
                'code' => $this->coupon['code'] ?? $this->coupon,
                'type' => 'legacy_coupon',
                'discount_amount' => $this->coupon['discount_amount'] ?? 0
            ];
        }

        return $allPromotions;
    }

    /**
     * Adiciona promoção moderna
     */
    public function addPromotion(array $promotion): self
    {
        if (!is_array($this->promotions)) {
            $this->promotions = [];
        }

        $this->promotions[] = $promotion;
        $this->data['promotions'] = $this->promotions;

        return $this;
    }

    /**
     * Remove promoção por código
     */
    public function removePromotion(string $promotionCode): self
    {
        if (is_array($this->promotions)) {
            $this->promotions = array_filter($this->promotions, function ($promotion) use ($promotionCode) {
                return ($promotion['code'] ?? '') !== $promotionCode;
            });
            $this->promotions = array_values($this->promotions);
            $this->data['promotions'] = $this->promotions;
        }

        // Remove cupom legacy se for o código
        if (($this->coupon['code'] ?? $this->coupon) === $promotionCode) {
            $this->removeCoupon();
        }

        return $this;
    }

    /**
     * Calcula desconto total de promoções
     */
    public function getTotalPromotionDiscount(): float
    {
        $totalDiscount = 0.0;

        foreach ($this->getAllPromotions() as $promotion) {
            $discount = (float) ($promotion['discount_amount'] ?? 0);
            $totalDiscount += $discount;
        }

        return $totalDiscount;
    }

    // ===========================================
    // MÉTODOS DE ONE-CLICK
    // ===========================================

    /**
     * Verifica se carrinho é elegível para one-click
     */
    public function isOneClickEligible(): bool
    {
        return $this->one_click_eligible === true;
    }

    /**
     * Define elegibilidade para one-click
     */
    public function setOneClickEligible(bool $eligible): self
    {
        $this->one_click_eligible = $eligible;
        $this->data['one_click_eligible'] = $eligible;
        return $this;
    }

    /**
     * Verifica se é carrinho one-click
     */
    public function isOneClickCart(): bool
    {
        return $this->type === 'one_click';
    }

    /**
     * Obtém métodos de pagamento salvos
     */
    public function getSavedPaymentMethods(): array
    {
        return $this->saved_payment_methods ?? [];
    }

    /**
     * Define métodos de pagamento salvos
     */
    public function setSavedPaymentMethods(array $methods): self
    {
        $this->saved_payment_methods = $methods;
        $this->data['saved_payment_methods'] = $methods;
        return $this;
    }

    // ===========================================
    // MÉTODOS DE ANALYTICS E TRACKING
    // ===========================================

    /**
     * Obtém dados de analytics
     */
    public function getAnalyticsData(): array
    {
        return $this->analytics_data ?? [];
    }

    /**
     * Define dados de analytics
     */
    public function setAnalyticsData(array $analyticsData): self
    {
        $this->analytics_data = $analyticsData;
        $this->data['analytics_data'] = $analyticsData;
        return $this;
    }

    /**
     * Adiciona evento de analytics
     */
    public function addAnalyticsEvent(string $event, array $data): self
    {
        if (!is_array($this->analytics_data)) {
            $this->analytics_data = ['events' => []];
        }

        if (!isset($this->analytics_data['events'])) {
            $this->analytics_data['events'] = [];
        }

        $this->analytics_data['events'][] = [
            'event' => $event,
            'data' => $data,
            'timestamp' => time()
        ];

        $this->data['analytics_data'] = $this->analytics_data;

        return $this;
    }

    /**
     * Obtém dados UTM completos
     */
    public function getUtmData(): array
    {
        return [
            'utm_source' => $this->utm_source,
            'utm_campaign' => $this->utm_campaign,
            'utm_medium' => $this->utm_medium,
            'referrer' => $this->referrer
        ];
    }

    /**
     * Define dados UTM
     */
    public function setUtmData(array $utmData): self
    {
        $this->utm_source = $utmData['utm_source'] ?? null;
        $this->utm_campaign = $utmData['utm_campaign'] ?? null;
        $this->utm_medium = $utmData['utm_medium'] ?? null;
        $this->referrer = $utmData['referrer'] ?? null;

        $this->data = array_merge($this->data, array_filter($utmData));

        return $this;
    }

    // ===========================================
    // MÉTODOS DE CONVERSÃO E TRANSAÇÃO
    // ===========================================

    /**
     * Verifica se carrinho foi convertido em transação
     */
    public function hasTransaction(): bool
    {
        return !empty($this->transaction_id);
    }

    /**
     * Define ID da transação
     */
    public function setTransactionId(string $transactionId): self
    {
        $this->transaction_id = $transactionId;
        $this->data['transaction_id'] = $transactionId;
        return $this;
    }

    /**
     * Verifica se está em processo de conversão
     */
    public function isConverting(): bool
    {
        return $this->status === 'converting';
    }

    /**
     * Marca como convertendo
     */
    public function markAsConverting(): self
    {
        $this->status = 'converting';
        $this->data['status'] = 'converting';
        return $this;
    }

    // ===========================================
    // MÉTODOS DE TEMPO E EXPIRAÇÃO
    // ===========================================

    /**
     * Verifica se carrinho expirou (versão aprimorada)
     */
    public function isExpired(): bool
    {
        if ($this->status === 'expired') {
            return true;
        }

        if ($this->expires_at && strtotime($this->expires_at) < time()) {
            return true;
        }

        // Verifica expiração de navegação se em fluxo
        if ($this->isInFlow()) {
            $flowData = $this->getFlowData();
            $flowExpiresAt = $flowData['expires_at'] ?? null;
            if ($flowExpiresAt && strtotime($flowExpiresAt) < time()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Define tempo de expiração
     */
    public function setExpiresAt(string $expiresAt): self
    {
        $this->expires_at = $expiresAt;
        $this->data['expires_at'] = $expiresAt;
        return $this;
    }

    /**
     * Define expiração em segundos a partir de agora
     */
    public function setExpiresIn(int $seconds): self
    {
        $expiresAt = date('Y-m-d H:i:s', time() + $seconds);
        return $this->setExpiresAt($expiresAt);
    }

    // ===========================================
    // MÉTODOS DE RESUMO E DISPLAY APRIMORADOS
    // ===========================================

    /**
     * Obtém resumo completo do carrinho
     */
    public function getCompleteSummary(): array
    {
        return [
            // Dados básicos
            'id' => $this->id,
            'session_id' => $this->session_id,
            'customer_id' => $this->customer_id,
            'type' => $this->type,
            'status' => $this->status,

            // Contadores
            'item_count' => $this->getItemCount(),
            'total_quantity' => $this->getTotalQuantity(),

            // Valores
            'subtotal' => $this->getSubtotal(),
            'discount' => $this->getDiscount(),
            'total' => $this->getTotal(),
            'formatted_total' => $this->getFormattedTotal(),
            'currency' => $this->getCurrency(),

            // Promoções
            'has_promotions' => count($this->getAllPromotions()) > 0,
            'promotion_count' => count($this->getAllPromotions()),
            'total_promotion_discount' => $this->getTotalPromotionDiscount(),

            // Fluxo e navegação
            'is_in_flow' => $this->isInFlow(),
            'navigation_id' => $this->navigation_id,
            'offer_id' => $this->offer_id,

            // One-click
            'is_one_click_eligible' => $this->isOneClickEligible(),
            'is_one_click_cart' => $this->isOneClickCart(),

            // Estado
            'requires_shipping' => $this->requiresShipping(),
            'is_empty' => $this->isEmpty(),
            'is_active' => $this->isActive(),
            'is_converted' => $this->isConverted(),
            'is_expired' => $this->isExpired(),
            'has_transaction' => $this->hasTransaction(),

            // Analytics
            'utm_data' => $this->getUtmData(),
            'analytics_events_count' => count($this->getAnalyticsData()['events'] ?? []),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'expires_at' => $this->expires_at
        ];
    }

    /**
     * Converte para array para API
     */
    public function toApiArray(): array
    {
        $apiData = $this->toArray();

        // Remove campos internos que não devem ir para API
        unset($apiData['analytics_data']);

        return $apiData;
    }

    /**
     * Cria instância para criação (versão aprimorada)
     */
    public static function forCreation(string $sessionId, array $data = []): self
    {
        $defaultData = [
            'session_id' => $sessionId,
            'type' => 'standard',
            'status' => 'active',
            'items' => [],
            'totals' => [
                'subtotal' => 0.0,
                'discount' => 0.0,
                'taxes' => 0.0,
                'shipping' => 0.0,
                'fees' => 0.0,
                'total' => 0.0
            ],
            'currency' => 'BRL',
            'shipping_data' => [],
            'billing_data' => [],
            'taxes' => [],
            'fees' => [],
            'promotions' => [],
            'discounts' => [],
            'metadata' => [],
            'one_click_eligible' => false,
            'analytics_data' => ['events' => []],
            'created_at' => date('Y-m-d H:i:s')
        ];

        return new self(array_merge($defaultData, $data));
    }

    /**
     * Cria instância para fluxo de navegação
     */
    public static function forFlow(string $sessionId, string $offerId, array $data = []): self
    {
        $flowData = array_merge($data, [
            'type' => 'flow',
            'offer_id' => $offerId,
            'flow_data' => $data['flow_data'] ?? []
        ]);

        return self::forCreation($sessionId, $flowData);
    }

    /**
     * Cria instância para one-click
     */
    public static function forOneClick(string $sessionId, string $customerId, array $data = []): self
    {
        $oneClickData = array_merge($data, [
            'type' => 'one_click',
            'customer_id' => $customerId,
            'one_click_eligible' => true
        ]);

        return self::forCreation($sessionId, $oneClickData);
    }
}