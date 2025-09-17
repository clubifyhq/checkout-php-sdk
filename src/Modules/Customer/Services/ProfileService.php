<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customer\Services;

use ClubifyCheckout\Services\BaseService;
use Clubify\Checkout\Modules\Customer\Repositories\CustomerRepositoryInterface;
use Clubify\Checkout\Modules\Customer\DTOs\ProfileData;
use ClubifyCheckout\Utils\Formatters\DocumentFormatter;
use ClubifyCheckout\Utils\Formatters\PhoneFormatter;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de perfil de clientes
 *
 * Gerencia perfis comportamentais avançados incluindo
 * análise psicográfica, segmentação baseada em ML,
 * scores de propensão e insights de personalização.
 */
class ProfileService extends BaseService
{
    private const CACHE_PREFIX = 'customer_profile:';
    private const CACHE_TTL = 3600; // 1 hora

    private array $metrics = [
        'profiles_generated' => 0,
        'segmentations_performed' => 0,
        'avg_processing_time' => 0.0,
        'ml_predictions_made' => 0,
    ];

    private array $behavioralSegments = [
        'innovators' => [
            'early_adopters',
            'tech_enthusiasts',
            'premium_seekers',
        ],
        'mainstream' => [
            'value_conscious',
            'convenience_seekers',
            'brand_loyal',
        ],
        'conservatives' => [
            'price_sensitive',
            'traditional_shoppers',
            'relationship_focused',
        ],
    ];

    private array $psychographicTraits = [
        'openness' => 'Abertura para novas experiências',
        'conscientiousness' => 'Conscienciosidade e organização',
        'extraversion' => 'Extroversão e sociabilidade',
        'agreeableness' => 'Amabilidade e cooperação',
        'neuroticism' => 'Estabilidade emocional',
    ];

    public function __construct(
        private CustomerRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        private DocumentFormatter $documentFormatter,
        private PhoneFormatter $phoneFormatter
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Gera perfil completo do cliente
     */
    public function generateProfile(string $customerId, array $options = []): ProfileData
    {
        return $this->withCache(
            self::CACHE_PREFIX . $customerId,
            function () use ($customerId, $options) {
                $startTime = microtime(true);

                try {
                    // Busca dados base do cliente
                    $customer = $this->repository->findById($customerId);
                    if (!$customer) {
                        throw new \InvalidArgumentException("Cliente não encontrado: {$customerId}");
                    }

                    // Coleta dados para análise
                    $behaviorData = $this->collectBehaviorData($customerId);
                    $transactionData = $this->collectTransactionData($customerId);
                    $interactionData = $this->collectInteractionData($customerId);

                    // Gera análises psicográficas
                    $psychographics = $this->analyzePsychographics($behaviorData, $transactionData);

                    // Calcula scores de propensão
                    $propensityScores = $this->calculatePropensityScores($customerId, $behaviorData, $transactionData);

                    // Realiza segmentação
                    $segmentation = $this->performSegmentation($customerId, $psychographics, $propensityScores);

                    // Gera insights de personalização
                    $personalization = $this->generatePersonalizationInsights($customerId, $segmentation, $psychographics);

                    // Calcula scores de similaridade
                    $similarityScores = $this->calculateSimilarityScores($customerId, $segmentation);

                    // Cria perfil completo
                    $profile = ProfileData::fromArray([
                        'customer_id' => $customerId,
                        'psychographics' => $psychographics,
                        'behavioral_segments' => $segmentation['segments'] ?? [],
                        'propensity_scores' => $propensityScores,
                        'personality_traits' => $this->extractPersonalityTraits($psychographics),
                        'lifestyle_indicators' => $this->extractLifestyleIndicators($behaviorData),
                        'purchase_motivations' => $this->analyzePurchaseMotivations($transactionData),
                        'communication_preferences' => $this->analyzeCommPreferences($interactionData),
                        'personalization_insights' => $personalization,
                        'similarity_scores' => $similarityScores,
                        'confidence_level' => $this->calculateConfidenceLevel($behaviorData, $transactionData),
                        'data_quality_score' => $this->calculateDataQuality($behaviorData, $transactionData, $interactionData),
                        'profile_completeness' => $this->calculateProfileCompleteness($customer),
                        'last_updated' => date('Y-m-d H:i:s'),
                        'generated_at' => date('Y-m-d H:i:s'),
                    ]);

                    $this->updateMetrics('generateProfile', microtime(true) - $startTime, true);

                    $this->logger->info('Perfil de cliente gerado com sucesso', [
                        'customer_id' => $customerId,
                        'processing_time' => microtime(true) - $startTime,
                        'primary_segment' => $segmentation['primary_segment'] ?? 'unknown',
                        'confidence_level' => $profile->confidenceLevel,
                    ]);

                    return $profile;

                } catch (\Exception $e) {
                    $this->updateMetrics('generateProfile', microtime(true) - $startTime, false);

                    $this->logger->error('Erro ao gerar perfil de cliente', [
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);

                    throw $e;
                }
            },
            self::CACHE_TTL
        );
    }

    /**
     * Realiza análise psicográfica avançada
     */
    public function analyzePsychographics(array $behaviorData, array $transactionData): array
    {
        return $this->executeWithMetrics('analyzePsychographics', function () use ($behaviorData, $transactionData) {
            $scores = [];

            // Análise de abertura para experiências
            $scores['openness'] = $this->calculateOpennessScore($behaviorData, $transactionData);

            // Análise de conscienciosidade
            $scores['conscientiousness'] = $this->calculateConscientiousnessScore($behaviorData);

            // Análise de extroversão
            $scores['extraversion'] = $this->calculateExtraversionScore($behaviorData);

            // Análise de amabilidade
            $scores['agreeableness'] = $this->calculateAgreeablenessScore($behaviorData);

            // Análise de estabilidade emocional
            $scores['neuroticism'] = $this->calculateNeuroticismScore($behaviorData);

            return [
                'big_five_scores' => $scores,
                'dominant_traits' => $this->identifyDominantTraits($scores),
                'trait_combinations' => $this->analyzeTraitCombinations($scores),
                'behavioral_indicators' => $this->extractBehavioralIndicators($behaviorData),
                'confidence_intervals' => $this->calculateTraitConfidenceIntervals($scores),
            ];
        });
    }

    /**
     * Calcula scores de propensão
     */
    public function calculatePropensityScores(string $customerId, array $behaviorData, array $transactionData): array
    {
        return $this->executeWithMetrics('calculatePropensityScores', function () use ($customerId, $behaviorData, $transactionData) {
            $this->metrics['ml_predictions_made']++;

            return [
                'purchase_propensity' => $this->calculatePurchasePropensity($transactionData),
                'churn_propensity' => $this->calculateChurnPropensity($behaviorData, $transactionData),
                'upsell_propensity' => $this->calculateUpsellPropensity($transactionData),
                'cross_sell_propensity' => $this->calculateCrossSellPropensity($transactionData),
                'loyalty_propensity' => $this->calculateLoyaltyPropensity($behaviorData, $transactionData),
                'advocacy_propensity' => $this->calculateAdvocacyPropensity($behaviorData),
                'price_sensitivity' => $this->calculatePriceSensitivity($transactionData),
                'brand_affinity' => $this->calculateBrandAffinity($transactionData),
                'channel_preference' => $this->calculateChannelPreference($behaviorData),
            ];
        });
    }

    /**
     * Realiza segmentação comportamental
     */
    public function performSegmentation(string $customerId, array $psychographics, array $propensityScores): array
    {
        return $this->executeWithMetrics('performSegmentation', function () use ($customerId, $psychographics, $propensityScores) {
            $this->metrics['segmentations_performed']++;

            $segments = [];

            // Segmentação baseada em propensão
            $segments['propensity'] = $this->segmentByPropensity($propensityScores);

            // Segmentação psicográfica
            $segments['psychographic'] = $this->segmentByPsychographics($psychographics);

            // Segmentação comportamental
            $segments['behavioral'] = $this->segmentByBehavior($psychographics, $propensityScores);

            // Segmentação de valor
            $segments['value'] = $this->segmentByValue($propensityScores);

            // Determina segmento primário
            $primarySegment = $this->determinePrimarySegment($segments);

            return [
                'segments' => $segments,
                'primary_segment' => $primarySegment,
                'segment_confidence' => $this->calculateSegmentConfidence($segments),
                'segment_stability' => $this->calculateSegmentStability($customerId),
                'migration_probability' => $this->calculateMigrationProbability($segments),
            ];
        });
    }

    /**
     * Gera insights de personalização
     */
    public function generatePersonalizationInsights(string $customerId, array $segmentation, array $psychographics): array
    {
        return $this->executeWithMetrics('generatePersonalizationInsights', function () use ($customerId, $segmentation, $psychographics) {
            return [
                'content_preferences' => $this->analyzeContentPreferences($psychographics),
                'communication_style' => $this->determineCommunicationStyle($psychographics),
                'offer_optimization' => $this->optimizeOffers($segmentation),
                'timing_preferences' => $this->analyzeTimingPreferences($psychographics),
                'channel_optimization' => $this->optimizeChannels($segmentation),
                'product_recommendations' => $this->generatePersonalizedRecommendations($segmentation, $psychographics),
                'experience_customization' => $this->customizeExperience($psychographics),
                'message_personalization' => $this->personalizeMessages($psychographics),
            ];
        });
    }

    /**
     * Compara perfis de clientes
     */
    public function compareProfiles(array $customerIds, array $options = []): array
    {
        return $this->executeWithMetrics('compareProfiles', function () use ($customerIds, $options) {
            $profiles = [];
            $comparisons = [];

            // Gera perfis para todos os clientes
            foreach ($customerIds as $customerId) {
                $profiles[$customerId] = $this->generateProfile($customerId, $options);
            }

            // Realiza comparações
            for ($i = 0; $i < count($customerIds); $i++) {
                for ($j = $i + 1; $j < count($customerIds); $j++) {
                    $id1 = $customerIds[$i];
                    $id2 = $customerIds[$j];

                    $comparisons["{$id1}_{$id2}"] = [
                        'similarity_score' => $this->calculateProfileSimilarity($profiles[$id1], $profiles[$id2]),
                        'trait_differences' => $this->compareTraits($profiles[$id1], $profiles[$id2]),
                        'segment_alignment' => $this->compareSegments($profiles[$id1], $profiles[$id2]),
                        'propensity_differences' => $this->comparePropensities($profiles[$id1], $profiles[$id2]),
                    ];
                }
            }

            return [
                'profiles' => $profiles,
                'comparisons' => $comparisons,
                'cluster_analysis' => $this->performClusterAnalysis($profiles),
                'similarity_matrix' => $this->generateSimilarityMatrix($profiles),
            ];
        });
    }

    /**
     * Métodos auxiliares para análise psicográfica
     */

    private function collectBehaviorData(string $customerId): array
    {
        // Simula coleta de dados comportamentais
        return [
            'page_views' => 150,
            'session_duration' => 1200,
            'bounce_rate' => 0.25,
            'social_shares' => 5,
            'review_frequency' => 0.3,
            'search_patterns' => ['specific', 'research-heavy'],
            'navigation_style' => 'explorer',
        ];
    }

    private function collectTransactionData(string $customerId): array
    {
        // Simula coleta de dados transacionais
        return [
            'total_orders' => 12,
            'total_value' => 2400.00,
            'avg_order_value' => 200.00,
            'purchase_frequency' => 0.4,
            'price_sensitivity' => 0.6,
            'brand_diversity' => 0.8,
            'category_spread' => ['electronics', 'clothing', 'books'],
        ];
    }

    private function collectInteractionData(string $customerId): array
    {
        // Simula coleta de dados de interação
        return [
            'email_engagement' => 0.7,
            'support_interactions' => 2,
            'social_media_activity' => 0.4,
            'referral_activity' => 1,
            'feedback_frequency' => 0.2,
            'preferred_channels' => ['email', 'website'],
        ];
    }

    /**
     * Cálculo de scores psicográficos
     */

    private function calculateOpennessScore(array $behaviorData, array $transactionData): float
    {
        $score = 0.0;

        // Diversidade de categorias
        $score += count($transactionData['category_spread'] ?? []) * 0.2;

        // Frequência de novos produtos
        $score += ($transactionData['brand_diversity'] ?? 0) * 0.3;

        // Padrões de exploração
        if (($behaviorData['navigation_style'] ?? '') === 'explorer') {
            $score += 0.3;
        }

        return min(1.0, $score);
    }

    private function calculateConscientiousnessScore(array $behaviorData): float
    {
        $score = 0.0;

        // Baixa taxa de bounce
        $score += (1 - ($behaviorData['bounce_rate'] ?? 0.5)) * 0.4;

        // Duração de sessão alta
        $sessionDuration = $behaviorData['session_duration'] ?? 0;
        $score += min(0.4, $sessionDuration / 3000); // Normaliza por 50 min

        // Padrões de pesquisa estruturados
        if (in_array('research-heavy', $behaviorData['search_patterns'] ?? [])) {
            $score += 0.2;
        }

        return min(1.0, $score);
    }

    private function calculateExtraversionScore(array $behaviorData): float
    {
        $score = 0.0;

        // Atividade em redes sociais
        $score += ($behaviorData['social_shares'] ?? 0) * 0.1;

        // Frequência de reviews
        $score += ($behaviorData['review_frequency'] ?? 0) * 0.5;

        // Atividade de referência
        $score += min(0.4, ($behaviorData['referral_activity'] ?? 0) * 0.2);

        return min(1.0, $score);
    }

    private function calculateAgreeablenessScore(array $behaviorData): float
    {
        $score = 0.5; // Score base neutro

        // Frequência de feedback positivo
        $score += ($behaviorData['feedback_frequency'] ?? 0) * 0.3;

        // Baixa frequência de reclamações (simulado)
        $score += 0.2; // Assumindo baixa reclamação

        return min(1.0, $score);
    }

    private function calculateNeuroticismScore(array $behaviorData): float
    {
        $score = 0.0;

        // Alta taxa de bounce pode indicar ansiedade
        $score += ($behaviorData['bounce_rate'] ?? 0) * 0.3;

        // Muitas interações de suporte
        $supportInteractions = $behaviorData['support_interactions'] ?? 0;
        $score += min(0.4, $supportInteractions * 0.1);

        // Inverte o score (menos neuroticism = mais estabilidade)
        return 1.0 - min(1.0, $score);
    }

    /**
     * Cálculo de scores de propensão
     */

    private function calculatePurchasePropensity(array $transactionData): float
    {
        $frequency = $transactionData['purchase_frequency'] ?? 0;
        return min(1.0, $frequency * 2);
    }

    private function calculateChurnPropensity(array $behaviorData, array $transactionData): float
    {
        $engagementScore = 1 - ($behaviorData['bounce_rate'] ?? 0.5);
        $loyaltyScore = min(1.0, ($transactionData['total_orders'] ?? 0) / 20);

        return 1.0 - (($engagementScore + $loyaltyScore) / 2);
    }

    private function calculateUpsellPropensity(array $transactionData): float
    {
        $avgOrderValue = $transactionData['avg_order_value'] ?? 0;
        $priceSensitivity = 1 - ($transactionData['price_sensitivity'] ?? 0.5);

        return min(1.0, ($avgOrderValue / 500) * $priceSensitivity);
    }

    private function calculateCrossSellPropensity(array $transactionData): float
    {
        $categorySpread = count($transactionData['category_spread'] ?? []);
        return min(1.0, $categorySpread / 5);
    }

    private function calculateLoyaltyPropensity(array $behaviorData, array $transactionData): float
    {
        $orderCount = $transactionData['total_orders'] ?? 0;
        $engagementLevel = $behaviorData['email_engagement'] ?? 0;

        return min(1.0, (($orderCount / 15) + $engagementLevel) / 2);
    }

    private function calculateAdvocacyPropensity(array $behaviorData): float
    {
        $socialShares = $behaviorData['social_shares'] ?? 0;
        $referralActivity = $behaviorData['referral_activity'] ?? 0;

        return min(1.0, (($socialShares * 0.1) + ($referralActivity * 0.5)) / 2);
    }

    private function calculatePriceSensitivity(array $transactionData): float
    {
        return $transactionData['price_sensitivity'] ?? 0.5;
    }

    private function calculateBrandAffinity(array $transactionData): float
    {
        $brandDiversity = $transactionData['brand_diversity'] ?? 0.5;
        return 1.0 - $brandDiversity; // Menor diversidade = maior afinidade
    }

    private function calculateChannelPreference(array $behaviorData): array
    {
        return $behaviorData['preferred_channels'] ?? ['website'];
    }

    /**
     * Métodos auxiliares diversos
     */

    private function identifyDominantTraits(array $scores): array
    {
        arsort($scores);
        return array_slice(array_keys($scores), 0, 2, true);
    }

    private function analyzeTraitCombinations(array $scores): array
    {
        // Analisa combinações específicas de traits
        $combinations = [];

        if ($scores['openness'] > 0.7 && $scores['conscientiousness'] > 0.7) {
            $combinations[] = 'innovative_planner';
        }

        if ($scores['extraversion'] > 0.6 && $scores['agreeableness'] > 0.6) {
            $combinations[] = 'social_influencer';
        }

        return $combinations;
    }

    private function extractBehavioralIndicators(array $behaviorData): array
    {
        return [
            'research_oriented' => in_array('research-heavy', $behaviorData['search_patterns'] ?? []),
            'socially_active' => ($behaviorData['social_shares'] ?? 0) > 3,
            'engaged_user' => ($behaviorData['session_duration'] ?? 0) > 900,
        ];
    }

    private function calculateTraitConfidenceIntervals(array $scores): array
    {
        $intervals = [];
        foreach ($scores as $trait => $score) {
            $intervals[$trait] = [
                'lower' => max(0, $score - 0.1),
                'upper' => min(1, $score + 0.1),
            ];
        }
        return $intervals;
    }

    private function extractPersonalityTraits(array $psychographics): array
    {
        $traits = [];
        $scores = $psychographics['big_five_scores'] ?? [];

        foreach ($scores as $trait => $score) {
            $level = match (true) {
                $score > 0.7 => 'high',
                $score > 0.4 => 'medium',
                default => 'low',
            };

            $traits[$trait] = [
                'score' => $score,
                'level' => $level,
                'description' => $this->psychographicTraits[$trait] ?? '',
            ];
        }

        return $traits;
    }

    private function extractLifestyleIndicators(array $behaviorData): array
    {
        return [
            'tech_savvy' => ($behaviorData['page_views'] ?? 0) > 100,
            'research_oriented' => in_array('research-heavy', $behaviorData['search_patterns'] ?? []),
            'socially_connected' => ($behaviorData['social_shares'] ?? 0) > 0,
            'detail_oriented' => ($behaviorData['session_duration'] ?? 0) > 1200,
        ];
    }

    private function analyzePurchaseMotivations(array $transactionData): array
    {
        $motivations = [];

        if (($transactionData['price_sensitivity'] ?? 0) > 0.6) {
            $motivations[] = 'value_seeking';
        }

        if (($transactionData['brand_diversity'] ?? 0) < 0.3) {
            $motivations[] = 'brand_loyalty';
        }

        if (($transactionData['avg_order_value'] ?? 0) > 300) {
            $motivations[] = 'quality_focus';
        }

        return $motivations;
    }

    private function analyzeCommPreferences(array $interactionData): array
    {
        return [
            'preferred_channels' => $interactionData['preferred_channels'] ?? ['email'],
            'engagement_level' => $interactionData['email_engagement'] ?? 0.5,
            'response_frequency' => 'weekly',
            'content_preference' => 'informative',
        ];
    }

    private function calculateSimilarityScores(string $customerId, array $segmentation): array
    {
        // Simula cálculo de similaridade com outros clientes
        return [
            'customer_001' => 0.85,
            'customer_002' => 0.72,
            'customer_003' => 0.69,
        ];
    }

    private function calculateConfidenceLevel(array $behaviorData, array $transactionData): float
    {
        $dataPoints = count($behaviorData) + count($transactionData);
        return min(1.0, $dataPoints / 20); // Normaliza por 20 pontos de dados
    }

    private function calculateDataQuality(array $behaviorData, array $transactionData, array $interactionData): float
    {
        $totalFields = 20; // Total de campos esperados
        $filledFields = 0;

        foreach ([$behaviorData, $transactionData, $interactionData] as $dataset) {
            foreach ($dataset as $value) {
                if (!empty($value)) {
                    $filledFields++;
                }
            }
        }

        return min(1.0, $filledFields / $totalFields);
    }

    private function calculateProfileCompleteness(array $customer): float
    {
        $requiredFields = ['name', 'email', 'phone', 'document'];
        $completedFields = 0;

        foreach ($requiredFields as $field) {
            if (!empty($customer[$field])) {
                $completedFields++;
            }
        }

        return $completedFields / count($requiredFields);
    }

    /**
     * Métodos de segmentação, personalização e comparação (simplificados)
     */

    private function segmentByPropensity(array $propensityScores): string
    {
        if ($propensityScores['loyalty_propensity'] > 0.7) {
            return 'high_loyalty';
        }
        if ($propensityScores['churn_propensity'] > 0.6) {
            return 'at_risk';
        }
        return 'standard';
    }

    private function segmentByPsychographics(array $psychographics): string
    {
        $dominantTraits = $psychographics['dominant_traits'] ?? [];

        if (in_array('openness', $dominantTraits)) {
            return 'innovator';
        }
        if (in_array('conscientiousness', $dominantTraits)) {
            return 'planner';
        }
        return 'mainstream';
    }

    private function segmentByBehavior(array $psychographics, array $propensityScores): string
    {
        if ($propensityScores['advocacy_propensity'] > 0.6) {
            return 'advocate';
        }
        if ($propensityScores['upsell_propensity'] > 0.7) {
            return 'growth_potential';
        }
        return 'stable';
    }

    private function segmentByValue(array $propensityScores): string
    {
        if ($propensityScores['purchase_propensity'] > 0.8) {
            return 'high_value';
        }
        if ($propensityScores['purchase_propensity'] > 0.5) {
            return 'medium_value';
        }
        return 'low_value';
    }

    private function determinePrimarySegment(array $segments): string
    {
        // Lógica para determinar segmento primário
        if ($segments['value'] === 'high_value') {
            return 'high_value_customer';
        }
        if ($segments['behavioral'] === 'advocate') {
            return 'brand_advocate';
        }
        return $segments['psychographic'] ?? 'mainstream';
    }

    private function calculateSegmentConfidence(array $segments): float
    {
        // Simula cálculo de confiança na segmentação
        return 0.82;
    }

    private function calculateSegmentStability(string $customerId): float
    {
        // Simula cálculo de estabilidade do segmento
        return 0.75;
    }

    private function calculateMigrationProbability(array $segments): array
    {
        return [
            'to_high_value' => 0.25,
            'to_advocate' => 0.15,
            'to_at_risk' => 0.05,
        ];
    }

    private function analyzeContentPreferences(array $psychographics): array
    {
        return [
            'content_type' => 'educational',
            'complexity' => 'medium',
            'format' => 'visual',
        ];
    }

    private function determineCommunicationStyle(array $psychographics): array
    {
        return [
            'tone' => 'friendly',
            'formality' => 'casual',
            'frequency' => 'weekly',
        ];
    }

    private function optimizeOffers(array $segmentation): array
    {
        return [
            'discount_type' => 'percentage',
            'timing' => 'end_of_month',
            'urgency_level' => 'medium',
        ];
    }

    private function analyzeTimingPreferences(array $psychographics): array
    {
        return [
            'best_day' => 'tuesday',
            'best_time' => '10:00-12:00',
            'frequency' => 'weekly',
        ];
    }

    private function optimizeChannels(array $segmentation): array
    {
        return [
            'primary' => 'email',
            'secondary' => 'website',
            'avoid' => ['phone'],
        ];
    }

    private function generatePersonalizedRecommendations(array $segmentation, array $psychographics): array
    {
        return [
            ['product_id' => 'prod_001', 'score' => 0.9, 'reason' => 'personality_match'],
            ['product_id' => 'prod_002', 'score' => 0.8, 'reason' => 'behavioral_pattern'],
        ];
    }

    private function customizeExperience(array $psychographics): array
    {
        return [
            'layout' => 'detailed',
            'navigation' => 'guided',
            'information_density' => 'high',
        ];
    }

    private function personalizeMessages(array $psychographics): array
    {
        return [
            'greeting_style' => 'warm',
            'call_to_action' => 'soft',
            'social_proof' => 'testimonials',
        ];
    }

    // Métodos de comparação simplificados
    private function calculateProfileSimilarity(ProfileData $profile1, ProfileData $profile2): float
    {
        return 0.75; // Simulado
    }

    private function compareTraits(ProfileData $profile1, ProfileData $profile2): array
    {
        return ['openness' => 0.1, 'conscientiousness' => -0.2];
    }

    private function compareSegments(ProfileData $profile1, ProfileData $profile2): array
    {
        return ['alignment' => 'high', 'differences' => []];
    }

    private function comparePropensities(ProfileData $profile1, ProfileData $profile2): array
    {
        return ['purchase' => 0.15, 'churn' => -0.1];
    }

    private function performClusterAnalysis(array $profiles): array
    {
        return ['clusters' => 3, 'primary_cluster' => 'innovators'];
    }

    private function generateSimilarityMatrix(array $profiles): array
    {
        return []; // Matriz simplificada
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }
}
