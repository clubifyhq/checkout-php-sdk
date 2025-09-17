<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\DTOs;

use ClubifyCheckout\Data\BaseData;
use DateTime;

/**
 * DTO para perfil comportamental do cliente
 *
 * Representa o perfil psicográfico e comportamental do cliente,
 * incluindo preferências, interesses, comportamentos de compra
 * e insights de machine learning para personalização.
 *
 * Funcionalidades principais:
 * - Perfil psicográfico e demográfico
 * - Preferências de produto e marca
 * - Comportamentos de navegação e compra
 * - Propensões e scores preditivos
 * - Segmentação comportamental avançada
 * - Personalização de experiência
 *
 * Componentes do perfil:
 * - Demographics: Dados demográficos
 * - Psychographics: Perfil psicográfico
 * - Preferences: Preferências de produto
 * - Behaviors: Padrões comportamentais
 * - Propensities: Propensões preditivas
 * - Segments: Segmentos comportamentais
 */
class ProfileData extends BaseData
{
    public string $customer_id;
    public array $demographics = [];
    public array $psychographics = [];
    public array $preferences = [];
    public array $behaviors = [];
    public array $propensities = [];
    public array $segments = [];
    public array $interests = [];
    public array $communication_preferences = [];
    public float $engagement_score = 0.0;
    public float $satisfaction_score = 0.0;
    public float $churn_probability = 0.0;
    public float $upsell_propensity = 0.0;
    public float $price_sensitivity = 0.0;
    public string $customer_journey_stage = 'awareness';
    public ?string $primary_persona = null;
    public array $touchpoint_preferences = [];
    public array $ml_insights = [];
    public ?DateTime $profile_updated_at = null;
    public ?DateTime $created_at = null;
    public ?DateTime $updated_at = null;

    /**
     * Regras de validação
     */
    protected function getValidationRules(): array
    {
        return [
            'customer_id' => ['required', 'string'],
            'demographics' => ['array'],
            'psychographics' => ['array'],
            'preferences' => ['array'],
            'behaviors' => ['array'],
            'propensities' => ['array'],
            'segments' => ['array'],
            'interests' => ['array'],
            'communication_preferences' => ['array'],
            'engagement_score' => ['numeric', 'min:0', 'max:1'],
            'satisfaction_score' => ['numeric', 'min:0', 'max:1'],
            'churn_probability' => ['numeric', 'min:0', 'max:1'],
            'upsell_propensity' => ['numeric', 'min:0', 'max:1'],
            'price_sensitivity' => ['numeric', 'min:0', 'max:1'],
            'customer_journey_stage' => ['in:awareness,consideration,purchase,retention,advocacy'],
            'primary_persona' => ['nullable', 'string'],
            'touchpoint_preferences' => ['array'],
            'ml_insights' => ['array'],
            'profile_updated_at' => ['nullable', 'date'],
            'created_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Converte datas
        $dateFields = ['profile_updated_at', 'created_at', 'updated_at'];
        foreach ($dateFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = new DateTime($data[$field]);
            }
        }

        // Normaliza scores entre 0 e 1
        $scoreFields = ['engagement_score', 'satisfaction_score', 'churn_probability', 'upsell_propensity', 'price_sensitivity'];
        foreach ($scoreFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = max(0.0, min(1.0, (float) $data[$field]));
            }
        }

        return $data;
    }

    /**
     * Atualiza dados demográficos
     */
    public function updateDemographics(array $demographics): void
    {
        $this->demographics = array_merge($this->demographics, [
            'age_range' => $demographics['age_range'] ?? null,
            'gender' => $demographics['gender'] ?? null,
            'location' => $demographics['location'] ?? null,
            'income_range' => $demographics['income_range'] ?? null,
            'education_level' => $demographics['education_level'] ?? null,
            'marital_status' => $demographics['marital_status'] ?? null,
            'occupation' => $demographics['occupation'] ?? null,
            'family_size' => $demographics['family_size'] ?? null,
        ]);

        $this->profile_updated_at = new DateTime();
    }

    /**
     * Atualiza perfil psicográfico
     */
    public function updatePsychographics(array $psychographics): void
    {
        $this->psychographics = array_merge($this->psychographics, [
            'lifestyle' => $psychographics['lifestyle'] ?? null,
            'values' => $psychographics['values'] ?? [],
            'personality_traits' => $psychographics['personality_traits'] ?? [],
            'attitudes' => $psychographics['attitudes'] ?? [],
            'motivations' => $psychographics['motivations'] ?? [],
            'pain_points' => $psychographics['pain_points'] ?? [],
            'decision_making_style' => $psychographics['decision_making_style'] ?? null,
        ]);

        $this->profile_updated_at = new DateTime();
    }

    /**
     * Atualiza preferências de produto
     */
    public function updatePreferences(array $preferences): void
    {
        $this->preferences = array_merge($this->preferences, [
            'product_categories' => $preferences['product_categories'] ?? [],
            'brands' => $preferences['brands'] ?? [],
            'price_ranges' => $preferences['price_ranges'] ?? [],
            'features' => $preferences['features'] ?? [],
            'quality_vs_price' => $preferences['quality_vs_price'] ?? 'balanced',
            'shopping_frequency' => $preferences['shopping_frequency'] ?? null,
            'purchase_timing' => $preferences['purchase_timing'] ?? [],
        ]);

        $this->profile_updated_at = new DateTime();
    }

    /**
     * Atualiza comportamentos observados
     */
    public function updateBehaviors(array $behaviors): void
    {
        $this->behaviors = array_merge($this->behaviors, [
            'browsing_patterns' => $behaviors['browsing_patterns'] ?? [],
            'purchase_patterns' => $behaviors['purchase_patterns'] ?? [],
            'engagement_patterns' => $behaviors['engagement_patterns'] ?? [],
            'channel_usage' => $behaviors['channel_usage'] ?? [],
            'device_preferences' => $behaviors['device_preferences'] ?? [],
            'time_preferences' => $behaviors['time_preferences'] ?? [],
            'social_influence' => $behaviors['social_influence'] ?? 0.0,
        ]);

        $this->profile_updated_at = new DateTime();
    }

    /**
     * Calcula score de engajamento
     */
    public function calculateEngagementScore(): float
    {
        $score = 0.0;
        $factors = [];

        // Frequência de visitas
        if (isset($this->behaviors['browsing_patterns']['visit_frequency'])) {
            $factors[] = min(1.0, $this->behaviors['browsing_patterns']['visit_frequency'] / 10);
        }

        // Tempo no site
        if (isset($this->behaviors['browsing_patterns']['avg_session_duration'])) {
            $factors[] = min(1.0, $this->behaviors['browsing_patterns']['avg_session_duration'] / 600); // 10 min max
        }

        // Páginas por visita
        if (isset($this->behaviors['browsing_patterns']['pages_per_session'])) {
            $factors[] = min(1.0, $this->behaviors['browsing_patterns']['pages_per_session'] / 10);
        }

        // Interações sociais
        if (isset($this->behaviors['social_influence'])) {
            $factors[] = $this->behaviors['social_influence'];
        }

        $this->engagement_score = empty($factors) ? 0.0 : array_sum($factors) / count($factors);

        return $this->engagement_score;
    }

    /**
     * Calcula propensão de churn
     */
    public function calculateChurnProbability(): float
    {
        $riskFactors = 0.0;
        $totalFactors = 0;

        // Última compra
        if (isset($this->behaviors['purchase_patterns']['days_since_last_purchase'])) {
            $daysSince = $this->behaviors['purchase_patterns']['days_since_last_purchase'];
            $riskFactors += min(1.0, $daysSince / 180); // Risco aumenta após 6 meses
            $totalFactors++;
        }

        // Frequência de compra decrescente
        if (isset($this->behaviors['purchase_patterns']['frequency_trend'])) {
            $trend = $this->behaviors['purchase_patterns']['frequency_trend'];
            if ($trend < 0) {
                $riskFactors += abs($trend);
                $totalFactors++;
            }
        }

        // Engajamento baixo
        if ($this->engagement_score < 0.3) {
            $riskFactors += 1.0 - $this->engagement_score;
            $totalFactors++;
        }

        // Satisfação baixa
        if ($this->satisfaction_score < 0.5) {
            $riskFactors += 1.0 - $this->satisfaction_score;
            $totalFactors++;
        }

        $this->churn_probability = $totalFactors > 0 ? min(1.0, $riskFactors / $totalFactors) : 0.0;

        return $this->churn_probability;
    }

    /**
     * Calcula propensão de upsell
     */
    public function calculateUpsellPropensity(): float
    {
        $propensityFactors = 0.0;
        $totalFactors = 0;

        // Histórico de compras crescente
        if (isset($this->behaviors['purchase_patterns']['value_trend'])) {
            $trend = $this->behaviors['purchase_patterns']['value_trend'];
            if ($trend > 0) {
                $propensityFactors += $trend;
                $totalFactors++;
            }
        }

        // Alto engajamento
        if ($this->engagement_score > 0.7) {
            $propensityFactors += $this->engagement_score;
            $totalFactors++;
        }

        // Satisfação alta
        if ($this->satisfaction_score > 0.8) {
            $propensityFactors += $this->satisfaction_score;
            $totalFactors++;
        }

        // Baixa sensibilidade a preço
        if ($this->price_sensitivity < 0.3) {
            $propensityFactors += 1.0 - $this->price_sensitivity;
            $totalFactors++;
        }

        $this->upsell_propensity = $totalFactors > 0 ? $propensityFactors / $totalFactors : 0.0;

        return $this->upsell_propensity;
    }

    /**
     * Determina persona primária
     */
    public function determinePrimaryPersona(): string
    {
        // Lógica simples de determinação de persona baseada em comportamentos
        $personas = [
            'bargain_hunter' => 0.0,
            'quality_seeker' => 0.0,
            'convenience_lover' => 0.0,
            'trend_follower' => 0.0,
            'loyal_customer' => 0.0,
        ];

        // Bargain Hunter - Sensível a preço
        if ($this->price_sensitivity > 0.7) {
            $personas['bargain_hunter'] += 0.8;
        }

        // Quality Seeker - Prefere qualidade
        if (isset($this->preferences['quality_vs_price']) && $this->preferences['quality_vs_price'] === 'quality') {
            $personas['quality_seeker'] += 0.8;
        }

        // Convenience Lover - Compras frequentes e rápidas
        if (isset($this->behaviors['purchase_patterns']['quick_decisions']) && $this->behaviors['purchase_patterns']['quick_decisions']) {
            $personas['convenience_lover'] += 0.7;
        }

        // Trend Follower - Influenciado socialmente
        if (isset($this->behaviors['social_influence']) && $this->behaviors['social_influence'] > 0.6) {
            $personas['trend_follower'] += 0.7;
        }

        // Loyal Customer - Alto LTV e baixo churn
        if ($this->churn_probability < 0.2 && $this->engagement_score > 0.8) {
            $personas['loyal_customer'] += 0.9;
        }

        $this->primary_persona = array_key_first(array_filter($personas, fn($score) => $score > 0.5)) ?? 'undefined';

        return $this->primary_persona;
    }

    /**
     * Obtém recomendações personalizadas
     */
    public function getPersonalizedRecommendations(): array
    {
        $recommendations = [];

        switch ($this->primary_persona) {
            case 'bargain_hunter':
                $recommendations[] = [
                    'type' => 'discount_offer',
                    'message' => 'Ofertas especiais com até 50% de desconto!',
                    'priority' => 'high',
                ];
                break;

            case 'quality_seeker':
                $recommendations[] = [
                    'type' => 'premium_product',
                    'message' => 'Produtos premium selecionados para você',
                    'priority' => 'high',
                ];
                break;

            case 'convenience_lover':
                $recommendations[] = [
                    'type' => 'express_checkout',
                    'message' => 'Compra rápida em 1 clique disponível',
                    'priority' => 'medium',
                ];
                break;

            case 'trend_follower':
                $recommendations[] = [
                    'type' => 'trending_product',
                    'message' => 'Produtos em alta que estão bombando',
                    'priority' => 'high',
                ];
                break;

            case 'loyal_customer':
                $recommendations[] = [
                    'type' => 'exclusive_access',
                    'message' => 'Acesso exclusivo a lançamentos',
                    'priority' => 'high',
                ];
                break;
        }

        return $recommendations;
    }

    /**
     * Obtém insights do perfil
     */
    public function getProfileInsights(): array
    {
        return [
            'primary_persona' => $this->determinePrimaryPersona(),
            'scores' => [
                'engagement' => $this->calculateEngagementScore(),
                'churn_probability' => $this->calculateChurnProbability(),
                'upsell_propensity' => $this->calculateUpsellPropensity(),
                'satisfaction' => $this->satisfaction_score,
                'price_sensitivity' => $this->price_sensitivity,
            ],
            'journey_stage' => $this->customer_journey_stage,
            'key_preferences' => $this->getKeyPreferences(),
            'behavioral_patterns' => $this->getBehavioralPatterns(),
            'recommendations' => $this->getPersonalizedRecommendations(),
        ];
    }

    /**
     * Atualiza estágio na jornada do cliente
     */
    public function updateJourneyStage(string $stage): void
    {
        $validStages = ['awareness', 'consideration', 'purchase', 'retention', 'advocacy'];

        if (in_array($stage, $validStages)) {
            $this->customer_journey_stage = $stage;
            $this->profile_updated_at = new DateTime();
        }
    }

    /**
     * Obtém preferências principais
     */
    private function getKeyPreferences(): array
    {
        return [
            'favorite_categories' => $this->preferences['product_categories'] ?? [],
            'preferred_brands' => $this->preferences['brands'] ?? [],
            'price_range' => $this->preferences['price_ranges'] ?? [],
            'shopping_style' => $this->preferences['quality_vs_price'] ?? 'balanced',
        ];
    }

    /**
     * Obtém padrões comportamentais principais
     */
    private function getBehavioralPatterns(): array
    {
        return [
            'browsing_style' => $this->behaviors['browsing_patterns'] ?? [],
            'purchase_style' => $this->behaviors['purchase_patterns'] ?? [],
            'preferred_channels' => $this->behaviors['channel_usage'] ?? [],
            'device_usage' => $this->behaviors['device_preferences'] ?? [],
        ];
    }
}