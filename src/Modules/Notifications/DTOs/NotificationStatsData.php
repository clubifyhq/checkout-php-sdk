<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\DTOs;

use Clubify\Checkout\Core\DTOs\BaseData;

/**
 * DTO para estatísticas de notificações
 *
 * Representa as estatísticas e métricas de notificações:
 * - Métricas de entrega e falhas
 * - Estatísticas por tipo e método
 * - Performance de webhooks
 * - Análise de retry e tempo de resposta
 * - Dados de períodos específicos
 * - Tendências e comparações
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas estatísticas de notificações
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substitui BaseData
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationStatsData extends BaseData
{
    public function __construct(
        public readonly int $totalSent,
        public readonly int $totalDelivered,
        public readonly int $totalFailed,
        public readonly float $deliveryRate,
        public readonly float $failureRate,
        public readonly array $byType = [],
        public readonly array $byMethod = [],
        public readonly array $byPeriod = [],
        public readonly array $webhookPerformance = [],
        public readonly array $retryAnalysis = [],
        public readonly ?float $averageDeliveryTime = null,
        public readonly ?int $totalRetries = null,
        public readonly ?array $failureReasons = null,
        public readonly ?array $topEvents = null,
        public readonly ?array $hourlyDistribution = null,
        public readonly ?array $weeklyTrends = null,
        public readonly ?array $monthlyTrends = null,
        public readonly ?\DateTime $periodStart = null,
        public readonly ?\DateTime $periodEnd = null,
        public readonly ?\DateTime $generatedAt = null
    ) {
        $this->validate();
    }

    /**
     * Regras de validação para estatísticas
     */
    protected function rules(): array
    {
        return [
            'totalSent' => 'integer|min:0',
            'totalDelivered' => 'integer|min:0',
            'totalFailed' => 'integer|min:0',
            'deliveryRate' => 'numeric|min:0|max:100',
            'failureRate' => 'numeric|min:0|max:100',
            'byType' => 'array',
            'byMethod' => 'array',
            'byPeriod' => 'array'
        ];
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'total_sent' => $this->totalSent,
            'total_delivered' => $this->totalDelivered,
            'total_failed' => $this->totalFailed,
            'delivery_rate' => $this->deliveryRate,
            'failure_rate' => $this->failureRate,
            'by_type' => $this->byType,
            'by_method' => $this->byMethod,
            'by_period' => $this->byPeriod,
            'webhook_performance' => $this->webhookPerformance,
            'retry_analysis' => $this->retryAnalysis,
            'average_delivery_time' => $this->averageDeliveryTime,
            'total_retries' => $this->totalRetries,
            'failure_reasons' => $this->failureReasons,
            'top_events' => $this->topEvents,
            'hourly_distribution' => $this->hourlyDistribution,
            'weekly_trends' => $this->weeklyTrends,
            'monthly_trends' => $this->monthlyTrends,
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
            totalSent: (int)($data['total_sent'] ?? 0),
            totalDelivered: (int)($data['total_delivered'] ?? 0),
            totalFailed: (int)($data['total_failed'] ?? 0),
            deliveryRate: (float)($data['delivery_rate'] ?? 0.0),
            failureRate: (float)($data['failure_rate'] ?? 0.0),
            byType: $data['by_type'] ?? [],
            byMethod: $data['by_method'] ?? [],
            byPeriod: $data['by_period'] ?? [],
            webhookPerformance: $data['webhook_performance'] ?? [],
            retryAnalysis: $data['retry_analysis'] ?? [],
            averageDeliveryTime: isset($data['average_delivery_time']) ? (float)$data['average_delivery_time'] : null,
            totalRetries: isset($data['total_retries']) ? (int)$data['total_retries'] : null,
            failureReasons: $data['failure_reasons'] ?? null,
            topEvents: $data['top_events'] ?? null,
            hourlyDistribution: $data['hourly_distribution'] ?? null,
            weeklyTrends: $data['weekly_trends'] ?? null,
            monthlyTrends: $data['monthly_trends'] ?? null,
            periodStart: isset($data['period_start']) ? new \DateTime($data['period_start']) : null,
            periodEnd: isset($data['period_end']) ? new \DateTime($data['period_end']) : null,
            generatedAt: isset($data['generated_at']) ? new \DateTime($data['generated_at']) : null
        );
    }

    /**
     * Obtém total de notificações processadas
     */
    public function getTotalProcessed(): int
    {
        return $this->totalSent + $this->totalFailed;
    }

    /**
     * Obtém total de notificações pendentes
     */
    public function getTotalPending(): int
    {
        $total = $this->getTotalProcessed();
        return max(0, $this->totalSent - $total);
    }

    /**
     * Calcula taxa de sucesso
     */
    public function getSuccessRate(): float
    {
        $total = $this->getTotalProcessed();
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->totalDelivered / $total) * 100, 2);
    }

    /**
     * Calcula taxa de retry
     */
    public function getRetryRate(): float
    {
        if ($this->totalRetries === null || $this->getTotalProcessed() === 0) {
            return 0.0;
        }

        return round(($this->totalRetries / $this->getTotalProcessed()) * 100, 2);
    }

    /**
     * Verifica se performance está boa
     */
    public function hasGoodPerformance(): bool
    {
        return $this->deliveryRate >= 95.0 && $this->failureRate <= 5.0;
    }

    /**
     * Verifica se performance está aceitável
     */
    public function hasAcceptablePerformance(): bool
    {
        return $this->deliveryRate >= 90.0 && $this->failureRate <= 10.0;
    }

    /**
     * Verifica se performance está ruim
     */
    public function hasPoorPerformance(): bool
    {
        return $this->deliveryRate < 90.0 || $this->failureRate > 10.0;
    }

    /**
     * Obtém o método de entrega mais usado
     */
    public function getMostUsedMethod(): ?string
    {
        if (empty($this->byMethod)) {
            return null;
        }

        $maxCount = 0;
        $mostUsed = null;

        foreach ($this->byMethod as $method => $stats) {
            $count = $stats['count'] ?? 0;
            if ($count > $maxCount) {
                $maxCount = $count;
                $mostUsed = $method;
            }
        }

        return $mostUsed;
    }

    /**
     * Obtém o tipo de evento mais usado
     */
    public function getMostUsedType(): ?string
    {
        if (empty($this->byType)) {
            return null;
        }

        $maxCount = 0;
        $mostUsed = null;

        foreach ($this->byType as $type => $stats) {
            $count = $stats['count'] ?? 0;
            if ($count > $maxCount) {
                $maxCount = $count;
                $mostUsed = $type;
            }
        }

        return $mostUsed;
    }

    /**
     * Obtém webhook com melhor performance
     */
    public function getBestPerformingWebhook(): ?array
    {
        if (empty($this->webhookPerformance)) {
            return null;
        }

        $bestRate = 0;
        $bestWebhook = null;

        foreach ($this->webhookPerformance as $webhook) {
            $rate = $webhook['success_rate'] ?? 0;
            if ($rate > $bestRate) {
                $bestRate = $rate;
                $bestWebhook = $webhook;
            }
        }

        return $bestWebhook;
    }

    /**
     * Obtém webhook com pior performance
     */
    public function getWorstPerformingWebhook(): ?array
    {
        if (empty($this->webhookPerformance)) {
            return null;
        }

        $worstRate = 100;
        $worstWebhook = null;

        foreach ($this->webhookPerformance as $webhook) {
            $rate = $webhook['success_rate'] ?? 100;
            if ($rate < $worstRate) {
                $worstRate = $rate;
                $worstWebhook = $webhook;
            }
        }

        return $worstWebhook;
    }

    /**
     * Obtém principais razões de falha
     */
    public function getTopFailureReasons(int $limit = 5): array
    {
        if (empty($this->failureReasons)) {
            return [];
        }

        // Ordena por quantidade de ocorrências
        $reasons = $this->failureReasons;
        arsort($reasons);

        return array_slice($reasons, 0, $limit, true);
    }

    /**
     * Obtém horário de pico
     */
    public function getPeakHour(): ?int
    {
        if (empty($this->hourlyDistribution)) {
            return null;
        }

        $maxCount = 0;
        $peakHour = null;

        foreach ($this->hourlyDistribution as $hour => $count) {
            if ($count > $maxCount) {
                $maxCount = $count;
                $peakHour = (int)$hour;
            }
        }

        return $peakHour;
    }

    /**
     * Obtém tendência geral
     */
    public function getOverallTrend(): string
    {
        if (empty($this->weeklyTrends) || count($this->weeklyTrends) < 2) {
            return 'stable';
        }

        $trends = array_values($this->weeklyTrends);
        $recent = array_slice($trends, -2);

        if ($recent[1] > $recent[0] * 1.1) {
            return 'increasing';
        } elseif ($recent[1] < $recent[0] * 0.9) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Calcula crescimento percentual
     */
    public function getGrowthPercentage(): ?float
    {
        if (empty($this->weeklyTrends) || count($this->weeklyTrends) < 2) {
            return null;
        }

        $trends = array_values($this->weeklyTrends);
        $previous = $trends[count($trends) - 2];
        $current = $trends[count($trends) - 1];

        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }

    /**
     * Obtém resumo da performance
     */
    public function getPerformanceSummary(): array
    {
        return [
            'status' => $this->getPerformanceStatus(),
            'delivery_rate' => $this->deliveryRate,
            'failure_rate' => $this->failureRate,
            'success_rate' => $this->getSuccessRate(),
            'retry_rate' => $this->getRetryRate(),
            'total_processed' => $this->getTotalProcessed(),
            'average_delivery_time' => $this->averageDeliveryTime,
            'most_used_method' => $this->getMostUsedMethod(),
            'most_used_type' => $this->getMostUsedType(),
            'peak_hour' => $this->getPeakHour(),
            'trend' => $this->getOverallTrend(),
            'growth_percentage' => $this->getGrowthPercentage()
        ];
    }

    /**
     * Obtém status da performance
     */
    public function getPerformanceStatus(): string
    {
        if ($this->hasGoodPerformance()) {
            return 'excellent';
        } elseif ($this->hasAcceptablePerformance()) {
            return 'good';
        } elseif ($this->hasPoorPerformance()) {
            return 'poor';
        }

        return 'critical';
    }

    /**
     * Obtém alertas baseados nas métricas
     */
    public function getAlerts(): array
    {
        $alerts = [];

        if ($this->failureRate > 15.0) {
            $alerts[] = [
                'type' => 'high_failure_rate',
                'message' => "Taxa de falha muito alta: {$this->failureRate}%",
                'severity' => 'critical'
            ];
        }

        if ($this->deliveryRate < 85.0) {
            $alerts[] = [
                'type' => 'low_delivery_rate',
                'message' => "Taxa de entrega baixa: {$this->deliveryRate}%",
                'severity' => 'warning'
            ];
        }

        if ($this->getRetryRate() > 20.0) {
            $alerts[] = [
                'type' => 'high_retry_rate',
                'message' => "Taxa de retry alta: {$this->getRetryRate()}%",
                'severity' => 'warning'
            ];
        }

        if ($this->averageDeliveryTime !== null && $this->averageDeliveryTime > 30.0) {
            $alerts[] = [
                'type' => 'slow_delivery',
                'message' => "Tempo médio de entrega lento: {$this->averageDeliveryTime}s",
                'severity' => 'info'
            ];
        }

        return $alerts;
    }

    /**
     * Verifica se há alertas críticos
     */
    public function hasCriticalAlerts(): bool
    {
        foreach ($this->getAlerts() as $alert) {
            if ($alert['severity'] === 'critical') {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtém duração do período analisado
     */
    public function getPeriodDuration(): ?int
    {
        if ($this->periodStart === null || $this->periodEnd === null) {
            return null;
        }

        return $this->periodEnd->getTimestamp() - $this->periodStart->getTimestamp();
    }

    /**
     * Verifica se os dados são recentes
     */
    public function isRecent(): bool
    {
        if ($this->generatedAt === null) {
            return false;
        }

        $oneHourAgo = new \DateTime('-1 hour');
        return $this->generatedAt > $oneHourAgo;
    }
}