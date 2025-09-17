<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para analytics de flow de checkout
 *
 * Representa dados analíticos completos de um flow:
 * - Métricas de conversão e abandono
 * - Performance por step
 * - Dados de timing e comportamento
 * - Segmentação de usuários
 * - A/B testing e otimizações
 * - Funil de conversão detalhado
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas analytics de flow
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowAnalyticsData extends BaseData
{
    public function __construct(
        public readonly string $flowId,
        public readonly string $period,
        public readonly int $totalSessions,
        public readonly int $completedSessions,
        public readonly int $abandonedSessions,
        public readonly float $conversionRate,
        public readonly float $abandomentRate,
        public readonly float $averageCompletionTime,
        public readonly array $stepMetrics = [],
        public readonly array $deviceBreakdown = [],
        public readonly array $trafficSources = [],
        public readonly array $userSegments = [],
        public readonly array $hourlyDistribution = [],
        public readonly array $weeklyTrends = [],
        public readonly array $abTestResults = [],
        public readonly array $errorAnalysis = [],
        public readonly array $conversionFunnel = [],
        public readonly ?float $revenueGenerated = null,
        public readonly ?float $averageOrderValue = null,
        public readonly ?array $topExitPoints = null,
        public readonly ?array $optimizationOpportunities = null,
        public readonly ?array $comparisonData = null,
        public readonly ?array $cohortAnalysis = null,
        public readonly ?array $heatmapData = null,
        public readonly ?array $customEvents = null,
        public readonly ?\DateTime $periodStart = null,
        public readonly ?\DateTime $periodEnd = null,
        public readonly ?\DateTime $generatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para analytics
     */
    protected function rules(): array
    {
        return [
            'flowId' => 'required|string|min:1|max:100',
            'period' => 'required|string|min:1|max:50',
            'totalSessions' => 'integer|min:0',
            'completedSessions' => 'integer|min:0',
            'abandonedSessions' => 'integer|min:0',
            'conversionRate' => 'numeric|min:0|max:100',
            'abandomentRate' => 'numeric|min:0|max:100',
            'averageCompletionTime' => 'numeric|min:0',
        ];
    }

    /**
     * Converte para array completo
     */
    public function toArray(): array
    {
        return [
            'flow_id' => $this->flowId,
            'period' => $this->period,
            'total_sessions' => $this->totalSessions,
            'completed_sessions' => $this->completedSessions,
            'abandoned_sessions' => $this->abandonedSessions,
            'conversion_rate' => $this->conversionRate,
            'abandonment_rate' => $this->abandomentRate,
            'average_completion_time' => $this->averageCompletionTime,
            'step_metrics' => $this->stepMetrics,
            'device_breakdown' => $this->deviceBreakdown,
            'traffic_sources' => $this->trafficSources,
            'user_segments' => $this->userSegments,
            'hourly_distribution' => $this->hourlyDistribution,
            'weekly_trends' => $this->weeklyTrends,
            'ab_test_results' => $this->abTestResults,
            'error_analysis' => $this->errorAnalysis,
            'conversion_funnel' => $this->conversionFunnel,
            'revenue_generated' => $this->revenueGenerated,
            'average_order_value' => $this->averageOrderValue,
            'top_exit_points' => $this->topExitPoints,
            'optimization_opportunities' => $this->optimizationOpportunities,
            'comparison_data' => $this->comparisonData,
            'cohort_analysis' => $this->cohortAnalysis,
            'heatmap_data' => $this->heatmapData,
            'custom_events' => $this->customEvents,
            'period_start' => $this->periodStart?->format('Y-m-d H:i:s'),
            'period_end' => $this->periodEnd?->format('Y-m-d H:i:s'),
            'generated_at' => $this->generatedAt?->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            flowId: $data['flow_id'] ?? '',
            period: $data['period'] ?? '',
            totalSessions: (int)($data['total_sessions'] ?? 0),
            completedSessions: (int)($data['completed_sessions'] ?? 0),
            abandonedSessions: (int)($data['abandoned_sessions'] ?? 0),
            conversionRate: (float)($data['conversion_rate'] ?? 0.0),
            abandomentRate: (float)($data['abandonment_rate'] ?? 0.0),
            averageCompletionTime: (float)($data['average_completion_time'] ?? 0.0),
            stepMetrics: $data['step_metrics'] ?? [],
            deviceBreakdown: $data['device_breakdown'] ?? [],
            trafficSources: $data['traffic_sources'] ?? [],
            userSegments: $data['user_segments'] ?? [],
            hourlyDistribution: $data['hourly_distribution'] ?? [],
            weeklyTrends: $data['weekly_trends'] ?? [],
            abTestResults: $data['ab_test_results'] ?? [],
            errorAnalysis: $data['error_analysis'] ?? [],
            conversionFunnel: $data['conversion_funnel'] ?? [],
            revenueGenerated: isset($data['revenue_generated']) ? (float)$data['revenue_generated'] : null,
            averageOrderValue: isset($data['average_order_value']) ? (float)$data['average_order_value'] : null,
            topExitPoints: $data['top_exit_points'] ?? null,
            optimizationOpportunities: $data['optimization_opportunities'] ?? null,
            comparisonData: $data['comparison_data'] ?? null,
            cohortAnalysis: $data['cohort_analysis'] ?? null,
            heatmapData: $data['heatmap_data'] ?? null,
            customEvents: $data['custom_events'] ?? null,
            periodStart: isset($data['period_start']) ? new \DateTime($data['period_start']) : null,
            periodEnd: isset($data['period_end']) ? new \DateTime($data['period_end']) : null,
            generatedAt: isset($data['generated_at']) ? new \DateTime($data['generated_at']) : null
        );
    }

    /**
     * Calcula taxa de sucesso
     */
    public function getSuccessRate(): float
    {
        return $this->conversionRate;
    }

    /**
     * Calcula taxa de abandono
     */
    public function getAbandonmentRate(): float
    {
        return $this->abandomentRate;
    }

    /**
     * Obtém step com maior abandono
     */
    public function getHighestAbandonmentStep(): ?array
    {
        if (empty($this->stepMetrics)) {
            return null;
        }

        $maxAbandonmentRate = 0;
        $highestStep = null;

        foreach ($this->stepMetrics as $step) {
            $abandonmentRate = $step['abandonment_rate'] ?? 0;
            if ($abandonmentRate > $maxAbandonmentRate) {
                $maxAbandonmentRate = $abandonmentRate;
                $highestStep = $step;
            }
        }

        return $highestStep;
    }

    /**
     * Obtém step com menor conversão
     */
    public function getLowestConversionStep(): ?array
    {
        if (empty($this->stepMetrics)) {
            return null;
        }

        $minConversionRate = 100;
        $lowestStep = null;

        foreach ($this->stepMetrics as $step) {
            $conversionRate = $step['conversion_rate'] ?? 100;
            if ($conversionRate < $minConversionRate) {
                $minConversionRate = $conversionRate;
                $lowestStep = $step;
            }
        }

        return $lowestStep;
    }

    /**
     * Obtém device mais usado
     */
    public function getMostUsedDevice(): ?string
    {
        if (empty($this->deviceBreakdown)) {
            return null;
        }

        $maxSessions = 0;
        $topDevice = null;

        foreach ($this->deviceBreakdown as $device => $data) {
            $sessions = $data['sessions'] ?? 0;
            if ($sessions > $maxSessions) {
                $maxSessions = $sessions;
                $topDevice = $device;
            }
        }

        return $topDevice;
    }

    /**
     * Obtém fonte de tráfego mais convertida
     */
    public function getBestConvertingTrafficSource(): ?array
    {
        if (empty($this->trafficSources)) {
            return null;
        }

        $maxConversionRate = 0;
        $bestSource = null;

        foreach ($this->trafficSources as $source) {
            $conversionRate = $source['conversion_rate'] ?? 0;
            if ($conversionRate > $maxConversionRate) {
                $maxConversionRate = $conversionRate;
                $bestSource = $source;
            }
        }

        return $bestSource;
    }

    /**
     * Obtém horário de pico
     */
    public function getPeakHour(): ?int
    {
        if (empty($this->hourlyDistribution)) {
            return null;
        }

        $maxSessions = 0;
        $peakHour = null;

        foreach ($this->hourlyDistribution as $hour => $sessions) {
            if ($sessions > $maxSessions) {
                $maxSessions = $sessions;
                $peakHour = (int)$hour;
            }
        }

        return $peakHour;
    }

    /**
     * Calcula tendência geral
     */
    public function getOverallTrend(): string
    {
        if (count($this->weeklyTrends) < 2) {
            return 'insufficient_data';
        }

        $recent = array_slice($this->weeklyTrends, -2);
        $current = end($recent);
        $previous = reset($recent);

        $currentRate = $current['conversion_rate'] ?? 0;
        $previousRate = $previous['conversion_rate'] ?? 0;

        if ($currentRate > $previousRate * 1.05) {
            return 'improving';
        } elseif ($currentRate < $previousRate * 0.95) {
            return 'declining';
        } else {
            return 'stable';
        }
    }

    /**
     * Calcula performance geral
     */
    public function getPerformanceGrade(): string
    {
        $score = 0;

        // Conversão (40% do peso)
        if ($this->conversionRate >= 80) {
            $score += 40;
        } elseif ($this->conversionRate >= 60) {
            $score += 30;
        } elseif ($this->conversionRate >= 40) {
            $score += 20;
        } elseif ($this->conversionRate >= 20) {
            $score += 10;
        }

        // Tempo de conclusão (30% do peso)
        if ($this->averageCompletionTime <= 120) { // 2 minutos
            $score += 30;
        } elseif ($this->averageCompletionTime <= 300) { // 5 minutos
            $score += 20;
        } elseif ($this->averageCompletionTime <= 600) { // 10 minutos
            $score += 10;
        }

        // Abandono (30% do peso)
        if ($this->abandomentRate <= 20) {
            $score += 30;
        } elseif ($this->abandomentRate <= 40) {
            $score += 20;
        } elseif ($this->abandomentRate <= 60) {
            $score += 10;
        }

        if ($score >= 85) {
            return 'A';
        } elseif ($score >= 70) {
            return 'B';
        } elseif ($score >= 55) {
            return 'C';
        } elseif ($score >= 40) {
            return 'D';
        } else {
            return 'F';
        }
    }

    /**
     * Identifica problemas críticos
     */
    public function getCriticalIssues(): array
    {
        $issues = [];

        if ($this->conversionRate < 30) {
            $issues[] = [
                'type' => 'low_conversion',
                'severity' => 'critical',
                'description' => 'Conversion rate is critically low',
                'value' => $this->conversionRate
            ];
        }

        if ($this->abandomentRate > 70) {
            $issues[] = [
                'type' => 'high_abandonment',
                'severity' => 'critical',
                'description' => 'Abandonment rate is too high',
                'value' => $this->abandomentRate
            ];
        }

        if ($this->averageCompletionTime > 900) { // 15 minutos
            $issues[] = [
                'type' => 'slow_completion',
                'severity' => 'warning',
                'description' => 'Average completion time is too long',
                'value' => $this->averageCompletionTime
            ];
        }

        // Identifica steps problemáticos
        $problematicStep = $this->getHighestAbandonmentStep();
        if ($problematicStep && ($problematicStep['abandonment_rate'] ?? 0) > 50) {
            $issues[] = [
                'type' => 'problematic_step',
                'severity' => 'warning',
                'description' => "Step '{$problematicStep['name']}' has high abandonment",
                'value' => $problematicStep['abandonment_rate']
            ];
        }

        return $issues;
    }

    /**
     * Gera recomendações de otimização
     */
    public function getOptimizationRecommendations(): array
    {
        $recommendations = [];

        $criticalIssues = $this->getCriticalIssues();
        foreach ($criticalIssues as $issue) {
            switch ($issue['type']) {
                case 'low_conversion':
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'conversion',
                        'action' => 'Simplify the flow and reduce friction points',
                        'impact' => 'Could improve conversion by 15-30%'
                    ];
                    break;

                case 'high_abandonment':
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'abandonment',
                        'action' => 'Add progress indicators and reduce required fields',
                        'impact' => 'Could reduce abandonment by 20-40%'
                    ];
                    break;

                case 'slow_completion':
                    $recommendations[] = [
                        'priority' => 'medium',
                        'category' => 'performance',
                        'action' => 'Optimize step transitions and form autofill',
                        'impact' => 'Could reduce completion time by 30-50%'
                    ];
                    break;

                case 'problematic_step':
                    $recommendations[] = [
                        'priority' => 'high',
                        'category' => 'step_optimization',
                        'action' => "Redesign the '{$issue['description']}' step",
                        'impact' => 'Could improve overall flow conversion'
                    ];
                    break;
            }
        }

        // Recomendações baseadas em device
        $topDevice = $this->getMostUsedDevice();
        if ($topDevice === 'mobile' && !empty($this->deviceBreakdown['mobile'])) {
            $mobileConversion = $this->deviceBreakdown['mobile']['conversion_rate'] ?? 0;
            if ($mobileConversion < $this->conversionRate * 0.8) {
                $recommendations[] = [
                    'priority' => 'medium',
                    'category' => 'mobile_optimization',
                    'action' => 'Optimize flow for mobile devices',
                    'impact' => 'Could improve mobile conversion significantly'
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Calcula ROI do flow
     */
    public function calculateROI(): ?array
    {
        if ($this->revenueGenerated === null || $this->averageOrderValue === null) {
            return null;
        }

        $completedOrders = $this->completedSessions;
        $estimatedRevenue = $completedOrders * $this->averageOrderValue;

        return [
            'revenue_generated' => $this->revenueGenerated,
            'estimated_revenue' => $estimatedRevenue,
            'average_order_value' => $this->averageOrderValue,
            'completed_orders' => $completedOrders,
            'revenue_per_session' => $this->totalSessions > 0 ? $this->revenueGenerated / $this->totalSessions : 0
        ];
    }

    /**
     * Obtém resumo executivo
     */
    public function getExecutiveSummary(): array
    {
        return [
            'period' => $this->period,
            'total_sessions' => $this->totalSessions,
            'conversion_rate' => $this->conversionRate,
            'revenue_generated' => $this->revenueGenerated,
            'performance_grade' => $this->getPerformanceGrade(),
            'trend' => $this->getOverallTrend(),
            'critical_issues_count' => count($this->getCriticalIssues()),
            'recommendations_count' => count($this->getOptimizationRecommendations()),
            'best_device' => $this->getMostUsedDevice(),
            'peak_hour' => $this->getPeakHour(),
            'problematic_step' => $this->getHighestAbandonmentStep()['name'] ?? null
        ];
    }

    /**
     * Compara com período anterior
     */
    public function compareWithPrevious(): ?array
    {
        if ($this->comparisonData === null) {
            return null;
        }

        $previous = $this->comparisonData['previous_period'] ?? [];

        return [
            'conversion_rate_change' => $this->calculatePercentageChange(
                $previous['conversion_rate'] ?? 0,
                $this->conversionRate
            ),
            'sessions_change' => $this->calculatePercentageChange(
                $previous['total_sessions'] ?? 0,
                $this->totalSessions
            ),
            'revenue_change' => $this->calculatePercentageChange(
                $previous['revenue_generated'] ?? 0,
                $this->revenueGenerated ?? 0
            ),
            'completion_time_change' => $this->calculatePercentageChange(
                $previous['average_completion_time'] ?? 0,
                $this->averageCompletionTime
            )
        ];
    }

    /**
     * Verifica se tem dados suficientes para análise
     */
    public function hasSufficientData(): bool
    {
        return $this->totalSessions >= 100; // Mínimo de 100 sessões para análise confiável
    }

    /**
     * Obtém nível de confiança dos dados
     */
    public function getDataConfidenceLevel(): string
    {
        if ($this->totalSessions >= 1000) {
            return 'high';
        } elseif ($this->totalSessions >= 500) {
            return 'medium';
        } elseif ($this->totalSessions >= 100) {
            return 'low';
        } else {
            return 'insufficient';
        }
    }

    /**
     * Calcula mudança percentual
     */
    private function calculatePercentageChange(float $oldValue, float $newValue): float
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 2);
    }

    /**
     * Exporta dados para dashboard
     */
    public function exportForDashboard(): array
    {
        return [
            'summary' => $this->getExecutiveSummary(),
            'performance' => [
                'grade' => $this->getPerformanceGrade(),
                'score_breakdown' => [
                    'conversion' => $this->conversionRate,
                    'speed' => $this->averageCompletionTime,
                    'abandonment' => $this->abandomentRate
                ]
            ],
            'trends' => [
                'overall' => $this->getOverallTrend(),
                'weekly' => $this->weeklyTrends,
                'hourly' => $this->hourlyDistribution
            ],
            'issues' => $this->getCriticalIssues(),
            'recommendations' => $this->getOptimizationRecommendations(),
            'roi' => $this->calculateROI(),
            'confidence' => $this->getDataConfidenceLevel(),
            'generated_at' => $this->generatedAt?->format('Y-m-d H:i:s')
        ];
    }
}