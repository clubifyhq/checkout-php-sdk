<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de analytics de eventos
 *
 * Representa resultados de análise de eventos com métricas,
 * insights e dados agregados para tomada de decisão.
 *
 * Funcionalidades principais:
 * - Métricas agregadas de eventos
 * - Análise de funil de conversão
 * - Segmentação de usuários
 * - Insights comportamentais
 * - Comparações temporais
 *
 * Campos principais:
 * - metrics: Métricas principais
 * - period: Período de análise
 * - segments: Segmentação de dados
 * - funnel: Dados de funil
 * - trends: Tendências temporais
 */
class EventAnalyticsData extends BaseData
{
    public array $metrics = [];
    public array $period = [];
    public array $segments = [];
    public array $funnel = [];
    public array $trends = [];
    public array $filters = [];
    public DateTime $generated_at;
    public string $version = '1.0';
    public ?string $organization_id = null;
    public array $comparisons = [];
    public array $insights = [];

    /**
     * Construtor com validação automática
     */
    public function __construct(array $data = [])
    {
        // Sanitizar dados antes de processar
        $data = $this->sanitizeData($data);
        
        parent::__construct($data);
        
        // Validar dados após construir
        $this->validate();
    }

    /**
     * Regras de validação
     */
    public function getRules(): array
    {
        return [
            'metrics' => ['array'],
            'period' => ['array'],
            'segments' => ['array'],
            'funnel' => ['array'],
            'trends' => ['array'],
            'filters' => ['array'],
            'generated_at' => ['required', 'date'],
            'version' => ['string'],
            'organization_id' => ['nullable', 'string'],
            'comparisons' => ['array'],
            'insights' => ['array'],
        ];
    }

    /**
     * Sanitiza dados antes da validação
     */
    protected function sanitizeData(array $data): array
    {
        // Garantir timestamp
        if (!isset($data['generated_at'])) {
            $data['generated_at'] = new DateTime();
        } elseif (is_string($data['generated_at'])) {
            $data['generated_at'] = new DateTime($data['generated_at']);
        }

        // Garantir arrays
        $arrayFields = ['metrics', 'period', 'segments', 'funnel', 'trends', 'filters', 'comparisons', 'insights'];
        foreach ($arrayFields as $field) {
            $data[$field] = $data[$field] ?? [];
        }

        return $data;
    }

    /**
     * Define métricas principais
     */
    public function setMetrics(array $metrics): void
    {
        $this->metrics = array_merge($this->metrics, $metrics);
    }

    /**
     * Adiciona métrica
     */
    public function addMetric(string $name, mixed $value, ?array $metadata = null): void
    {
        $this->metrics[$name] = [
            'value' => $value,
            'metadata' => $metadata ?? [],
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Define período de análise
     */
    public function setPeriod(DateTime $start, DateTime $end): void
    {
        $this->period = [
            'start' => $start->format('c'),
            'end' => $end->format('c'),
            'duration_days' => $end->diff($start)->days,
            'duration_hours' => round($end->diff($start)->totalHours, 2),
        ];
    }

    /**
     * Adiciona segmento
     */
    public function addSegment(string $name, array $data): void
    {
        $this->segments[$name] = $data;
    }

    /**
     * Define dados de funil
     */
    public function setFunnelData(array $steps, array $conversions): void
    {
        $this->funnel = [
            'steps' => $steps,
            'conversions' => $conversions,
            'total_entries' => $conversions[0] ?? 0,
            'final_conversions' => end($conversions) ?: 0,
            'overall_conversion_rate' => $this->calculateOverallConversionRate($conversions),
            'step_conversion_rates' => $this->calculateStepConversionRates($conversions),
            'drop_off_points' => $this->identifyDropOffPoints($conversions),
        ];
    }

    /**
     * Adiciona tendência temporal
     */
    public function addTrend(string $metric, array $timeSeriesData): void
    {
        $this->trends[$metric] = [
            'data' => $timeSeriesData,
            'trend_direction' => $this->calculateTrendDirection($timeSeriesData),
            'growth_rate' => $this->calculateGrowthRate($timeSeriesData),
            'volatility' => $this->calculateVolatility($timeSeriesData),
        ];
    }

    /**
     * Adiciona comparação temporal
     */
    public function addComparison(string $metric, array $current, array $previous): void
    {
        $this->comparisons[$metric] = [
            'current_period' => $current,
            'previous_period' => $previous,
            'change_absolute' => $this->calculateAbsoluteChange($current, $previous),
            'change_percentage' => $this->calculatePercentageChange($current, $previous),
            'significance' => $this->calculateSignificance($current, $previous),
        ];
    }

    /**
     * Adiciona insight
     */
    public function addInsight(string $type, string $message, array $data = [], string $priority = 'medium'): void
    {
        $this->insights[] = [
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'timestamp' => (new DateTime())->format('c'),
        ];
    }

    /**
     * Obtém métrica por nome
     */
    public function getMetric(string $name): ?array
    {
        return $this->metrics[$name] ?? null;
    }

    /**
     * Obtém valor de métrica
     */
    public function getMetricValue(string $name): mixed
    {
        return $this->metrics[$name]['value'] ?? null;
    }

    /**
     * Obtém segmento por nome
     */
    public function getSegment(string $name): ?array
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Obtém tendência por métrica
     */
    public function getTrend(string $metric): ?array
    {
        return $this->trends[$metric] ?? null;
    }

    /**
     * Obtém comparação por métrica
     */
    public function getComparison(string $metric): ?array
    {
        return $this->comparisons[$metric] ?? null;
    }

    /**
     * Obtém insights por tipo
     */
    public function getInsightsByType(string $type): array
    {
        return array_filter($this->insights, function($insight) use ($type) {
            return $insight['type'] === $type;
        });
    }

    /**
     * Obtém insights por prioridade
     */
    public function getInsightsByPriority(string $priority): array
    {
        return array_filter($this->insights, function($insight) use ($priority) {
            return $insight['priority'] === $priority;
        });
    }

    /**
     * Verifica se há insights de alta prioridade
     */
    public function hasHighPriorityInsights(): bool
    {
        return !empty($this->getInsightsByPriority('high'));
    }

    /**
     * Obtém resumo executivo
     */
    public function getExecutiveSummary(): array
    {
        return [
            'period' => $this->period,
            'key_metrics' => $this->getKeyMetrics(),
            'top_insights' => $this->getTopInsights(),
            'performance_indicators' => $this->getPerformanceIndicators(),
            'recommendations' => $this->getRecommendations(),
        ];
    }

    /**
     * Exporta para dashboard
     */
    public function toDashboardFormat(): array
    {
        return [
            'summary' => $this->getExecutiveSummary(),
            'metrics' => $this->metrics,
            'funnel' => $this->funnel,
            'trends' => $this->trends,
            'segments' => $this->segments,
            'insights' => $this->insights,
            'generated_at' => $this->generated_at->format('c'),
        ];
    }

    /**
     * Calcula taxa de conversão geral do funil
     */
    private function calculateOverallConversionRate(array $conversions): float
    {
        if (empty($conversions) || $conversions[0] == 0) {
            return 0.0;
        }
        
        $finalConversions = end($conversions) ?: 0;
        return round(($finalConversions / $conversions[0]) * 100, 2);
    }

    /**
     * Calcula taxas de conversão por etapa
     */
    private function calculateStepConversionRates(array $conversions): array
    {
        $rates = [];
        
        for ($i = 1; $i < count($conversions); $i++) {
            $previous = $conversions[$i - 1];
            $current = $conversions[$i];
            
            if ($previous > 0) {
                $rates[] = round(($current / $previous) * 100, 2);
            } else {
                $rates[] = 0.0;
            }
        }
        
        return $rates;
    }

    /**
     * Identifica pontos de abandono no funil
     */
    private function identifyDropOffPoints(array $conversions): array
    {
        $stepRates = $this->calculateStepConversionRates($conversions);
        $dropOffs = [];
        
        foreach ($stepRates as $index => $rate) {
            if ($rate < 50) { // Considera drop-off significativo abaixo de 50%
                $dropOffs[] = [
                    'step' => $index + 1,
                    'conversion_rate' => $rate,
                    'severity' => $rate < 25 ? 'high' : 'medium',
                ];
            }
        }
        
        return $dropOffs;
    }

    /**
     * Calcula direção da tendência
     */
    private function calculateTrendDirection(array $data): string
    {
        if (count($data) < 2) {
            return 'insufficient_data';
        }
        
        $first = reset($data);
        $last = end($data);
        
        if ($last > $first) {
            return 'upward';
        } elseif ($last < $first) {
            return 'downward';
        } else {
            return 'stable';
        }
    }

    /**
     * Calcula taxa de crescimento
     */
    private function calculateGrowthRate(array $data): float
    {
        if (count($data) < 2) {
            return 0.0;
        }
        
        $first = reset($data);
        $last = end($data);
        
        if ($first == 0) {
            return 0.0;
        }
        
        return round((($last - $first) / $first) * 100, 2);
    }

    /**
     * Calcula volatilidade dos dados
     */
    private function calculateVolatility(array $data): float
    {
        if (count($data) < 2) {
            return 0.0;
        }
        
        $mean = array_sum($data) / count($data);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $data)) / count($data);
        
        return round(sqrt($variance), 2);
    }

    /**
     * Calcula mudança absoluta
     */
    private function calculateAbsoluteChange(array $current, array $previous): float
    {
        $currentSum = array_sum($current);
        $previousSum = array_sum($previous);
        
        return round($currentSum - $previousSum, 2);
    }

    /**
     * Calcula mudança percentual
     */
    private function calculatePercentageChange(array $current, array $previous): float
    {
        $currentSum = array_sum($current);
        $previousSum = array_sum($previous);
        
        if ($previousSum == 0) {
            return 0.0;
        }
        
        return round((($currentSum - $previousSum) / $previousSum) * 100, 2);
    }

    /**
     * Calcula significância estatística (simplificada)
     */
    private function calculateSignificance(array $current, array $previous): string
    {
        $change = abs($this->calculatePercentageChange($current, $previous));
        
        if ($change > 20) {
            return 'high';
        } elseif ($change > 10) {
            return 'medium';
        } elseif ($change > 5) {
            return 'low';
        } else {
            return 'negligible';
        }
    }

    /**
     * Obtém métricas principais
     */
    private function getKeyMetrics(): array
    {
        $keyMetrics = ['total_events', 'unique_users', 'conversion_rate', 'revenue'];
        $result = [];
        
        foreach ($keyMetrics as $metric) {
            if (isset($this->metrics[$metric])) {
                $result[$metric] = $this->metrics[$metric];
            }
        }
        
        return $result;
    }

    /**
     * Obtém principais insights
     */
    private function getTopInsights(): array
    {
        $topInsights = array_filter($this->insights, function($insight) {
            return in_array($insight['priority'], ['high', 'medium']);
        });
        
        usort($topInsights, function($a, $b) {
            $priorities = ['high' => 3, 'medium' => 2, 'low' => 1];
            return $priorities[$b['priority']] - $priorities[$a['priority']];
        });
        
        return array_slice($topInsights, 0, 5);
    }

    /**
     * Obtém indicadores de performance
     */
    private function getPerformanceIndicators(): array
    {
        return [
            'funnel_health' => $this->getFunnelHealth(),
            'growth_indicators' => $this->getGrowthIndicators(),
            'user_engagement' => $this->getUserEngagementIndicators(),
        ];
    }

    /**
     * Avalia saúde do funil
     */
    private function getFunnelHealth(): string
    {
        if (empty($this->funnel)) {
            return 'unknown';
        }
        
        $overallRate = $this->funnel['overall_conversion_rate'] ?? 0;
        
        if ($overallRate > 10) {
            return 'excellent';
        } elseif ($overallRate > 5) {
            return 'good';
        } elseif ($overallRate > 2) {
            return 'average';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * Obtém indicadores de crescimento
     */
    private function getGrowthIndicators(): array
    {
        $indicators = [];
        
        foreach ($this->trends as $metric => $trend) {
            $indicators[$metric] = $trend['trend_direction'];
        }
        
        return $indicators;
    }

    /**
     * Obtém indicadores de engajamento
     */
    private function getUserEngagementIndicators(): array
    {
        return [
            'session_depth' => $this->getMetricValue('avg_session_depth') ?? 'unknown',
            'time_on_site' => $this->getMetricValue('avg_time_on_site') ?? 'unknown',
            'bounce_rate' => $this->getMetricValue('bounce_rate') ?? 'unknown',
        ];
    }

    /**
     * Obtém recomendações
     */
    private function getRecommendations(): array
    {
        return array_map(function($insight) {
            return $insight['message'];
        }, $this->getInsightsByType('recommendation'));
    }
}
