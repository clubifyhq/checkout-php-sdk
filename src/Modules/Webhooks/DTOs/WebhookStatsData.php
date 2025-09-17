<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks\DTOs;

use Clubify\Checkout\Core\DTOs\BaseDTO;

/**
 * DTO para estatísticas de webhook
 *
 * Representa métricas de performance e estatísticas
 * detalhadas de um webhook incluindo entregas,
 * falhas, tempos de resposta e tendências.
 */
class WebhookStatsData extends BaseDTO
{
    public function __construct(
        public readonly string $webhookId,
        public readonly string $period,
        public readonly int $totalDeliveries = 0,
        public readonly int $successfulDeliveries = 0,
        public readonly int $failedDeliveries = 0,
        public readonly int $pendingRetries = 0,
        public readonly int $abandonedRetries = 0,
        public readonly float $successRate = 0.0,
        public readonly float $failureRate = 0.0,
        public readonly float $avgResponseTime = 0.0,
        public readonly float $minResponseTime = 0.0,
        public readonly float $maxResponseTime = 0.0,
        public readonly float $p50ResponseTime = 0.0,
        public readonly float $p95ResponseTime = 0.0,
        public readonly float $p99ResponseTime = 0.0,
        public readonly int $circuitBreakerTrips = 0,
        public readonly int $rateLimitHits = 0,
        public readonly int $timeouts = 0,
        public readonly int $connectionErrors = 0,
        public readonly int $sslErrors = 0,
        public readonly int $httpErrors = 0,
        public readonly array $statusCodeDistribution = [],
        public readonly array $errorDistribution = [],
        public readonly array $hourlyStats = [],
        public readonly array $dailyStats = [],
        public readonly array $eventTypeStats = [],
        public readonly ?string $firstDeliveryAt = null,
        public readonly ?string $lastDeliveryAt = null,
        public readonly ?string $lastSuccessAt = null,
        public readonly ?string $lastFailureAt = null,
        public readonly string $calculatedAt = '',
        public readonly array $trends = [],
        public readonly array $alerts = []
    ) {
        $this->validate();
    }

    /**
     * Cria instância a partir de array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            webhookId: $data['webhook_id'] ?? '',
            period: $data['period'] ?? '',
            totalDeliveries: $data['total_deliveries'] ?? 0,
            successfulDeliveries: $data['successful_deliveries'] ?? 0,
            failedDeliveries: $data['failed_deliveries'] ?? 0,
            pendingRetries: $data['pending_retries'] ?? 0,
            abandonedRetries: $data['abandoned_retries'] ?? 0,
            successRate: $data['success_rate'] ?? 0.0,
            failureRate: $data['failure_rate'] ?? 0.0,
            avgResponseTime: $data['avg_response_time'] ?? 0.0,
            minResponseTime: $data['min_response_time'] ?? 0.0,
            maxResponseTime: $data['max_response_time'] ?? 0.0,
            p50ResponseTime: $data['p50_response_time'] ?? 0.0,
            p95ResponseTime: $data['p95_response_time'] ?? 0.0,
            p99ResponseTime: $data['p99_response_time'] ?? 0.0,
            circuitBreakerTrips: $data['circuit_breaker_trips'] ?? 0,
            rateLimitHits: $data['rate_limit_hits'] ?? 0,
            timeouts: $data['timeouts'] ?? 0,
            connectionErrors: $data['connection_errors'] ?? 0,
            sslErrors: $data['ssl_errors'] ?? 0,
            httpErrors: $data['http_errors'] ?? 0,
            statusCodeDistribution: $data['status_code_distribution'] ?? [],
            errorDistribution: $data['error_distribution'] ?? [],
            hourlyStats: $data['hourly_stats'] ?? [],
            dailyStats: $data['daily_stats'] ?? [],
            eventTypeStats: $data['event_type_stats'] ?? [],
            firstDeliveryAt: $data['first_delivery_at'] ?? null,
            lastDeliveryAt: $data['last_delivery_at'] ?? null,
            lastSuccessAt: $data['last_success_at'] ?? null,
            lastFailureAt: $data['last_failure_at'] ?? null,
            calculatedAt: $data['calculated_at'] ?? date('Y-m-d H:i:s'),
            trends: $data['trends'] ?? [],
            alerts: $data['alerts'] ?? []
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'webhook_id' => $this->webhookId,
            'period' => $this->period,
            'total_deliveries' => $this->totalDeliveries,
            'successful_deliveries' => $this->successfulDeliveries,
            'failed_deliveries' => $this->failedDeliveries,
            'pending_retries' => $this->pendingRetries,
            'abandoned_retries' => $this->abandonedRetries,
            'success_rate' => $this->successRate,
            'failure_rate' => $this->failureRate,
            'avg_response_time' => $this->avgResponseTime,
            'min_response_time' => $this->minResponseTime,
            'max_response_time' => $this->maxResponseTime,
            'p50_response_time' => $this->p50ResponseTime,
            'p95_response_time' => $this->p95ResponseTime,
            'p99_response_time' => $this->p99ResponseTime,
            'circuit_breaker_trips' => $this->circuitBreakerTrips,
            'rate_limit_hits' => $this->rateLimitHits,
            'timeouts' => $this->timeouts,
            'connection_errors' => $this->connectionErrors,
            'ssl_errors' => $this->sslErrors,
            'http_errors' => $this->httpErrors,
            'status_code_distribution' => $this->statusCodeDistribution,
            'error_distribution' => $this->errorDistribution,
            'hourly_stats' => $this->hourlyStats,
            'daily_stats' => $this->dailyStats,
            'event_type_stats' => $this->eventTypeStats,
            'first_delivery_at' => $this->firstDeliveryAt,
            'last_delivery_at' => $this->lastDeliveryAt,
            'last_success_at' => $this->lastSuccessAt,
            'last_failure_at' => $this->lastFailureAt,
            'calculated_at' => $this->calculatedAt,
            'trends' => $this->trends,
            'alerts' => $this->alerts,
        ];
    }

    /**
     * Verifica se webhook está saudável
     */
    public function isHealthy(): bool
    {
        return $this->successRate >= 0.95 && // 95% de sucesso
               $this->avgResponseTime <= 5.0 && // Menos de 5s
               $this->circuitBreakerTrips === 0;
    }

    /**
     * Obtém nível de saúde
     */
    public function getHealthLevel(): string
    {
        if ($this->successRate >= 0.99 && $this->avgResponseTime <= 2.0) {
            return 'excellent';
        }

        if ($this->successRate >= 0.95 && $this->avgResponseTime <= 5.0) {
            return 'good';
        }

        if ($this->successRate >= 0.90 && $this->avgResponseTime <= 10.0) {
            return 'fair';
        }

        if ($this->successRate >= 0.80) {
            return 'poor';
        }

        return 'critical';
    }

    /**
     * Obtém cor do status
     */
    public function getHealthColor(): string
    {
        return match ($this->getHealthLevel()) {
            'excellent' => 'green',
            'good' => 'blue',
            'fair' => 'yellow',
            'poor' => 'orange',
            'critical' => 'red',
        };
    }

    /**
     * Obtém tendência de performance
     */
    public function getPerformanceTrend(): string
    {
        if (empty($this->trends)) {
            return 'stable';
        }

        $successTrend = $this->trends['success_rate'] ?? 'stable';
        $responseTrend = $this->trends['response_time'] ?? 'stable';

        if ($successTrend === 'improving' && $responseTrend === 'improving') {
            return 'improving';
        }

        if ($successTrend === 'degrading' || $responseTrend === 'degrading') {
            return 'degrading';
        }

        return 'stable';
    }

    /**
     * Obtém principais problemas
     */
    public function getTopIssues(): array
    {
        $issues = [];

        if ($this->successRate < 0.95) {
            $issues[] = [
                'type' => 'low_success_rate',
                'message' => "Taxa de sucesso baixa: {$this->successRate}%",
                'severity' => $this->successRate < 0.80 ? 'critical' : 'warning',
            ];
        }

        if ($this->avgResponseTime > 10.0) {
            $issues[] = [
                'type' => 'slow_response',
                'message' => "Tempo de resposta alto: {$this->avgResponseTime}s",
                'severity' => $this->avgResponseTime > 30.0 ? 'critical' : 'warning',
            ];
        }

        if ($this->circuitBreakerTrips > 0) {
            $issues[] = [
                'type' => 'circuit_breaker',
                'message' => "Circuit breaker ativado {$this->circuitBreakerTrips} vezes",
                'severity' => 'warning',
            ];
        }

        if ($this->pendingRetries > 10) {
            $issues[] = [
                'type' => 'high_retry_queue',
                'message' => "{$this->pendingRetries} retries pendentes",
                'severity' => $this->pendingRetries > 100 ? 'critical' : 'warning',
            ];
        }

        return $issues;
    }

    /**
     * Obtém recomendações
     */
    public function getRecommendations(): array
    {
        $recommendations = [];

        if ($this->successRate < 0.95) {
            $recommendations[] = 'Investigar causas das falhas e otimizar endpoint de destino';
        }

        if ($this->avgResponseTime > 5.0) {
            $recommendations[] = 'Otimizar performance do endpoint ou reduzir timeout';
        }

        if ($this->timeouts > 0) {
            $recommendations[] = 'Considerar aumentar timeout ou otimizar processamento';
        }

        if ($this->rateLimitHits > 0) {
            $recommendations[] = 'Implementar backoff ou reduzir frequência de envios';
        }

        if ($this->pendingRetries > 10) {
            $recommendations[] = 'Processar fila de retries ou revisar configuração';
        }

        return $recommendations;
    }

    /**
     * Calcula score de qualidade (0-100)
     */
    public function getQualityScore(): int
    {
        $score = 0;

        // Taxa de sucesso (40% do score)
        $score += $this->successRate * 40;

        // Tempo de resposta (30% do score)
        $responseScore = max(0, 30 - ($this->avgResponseTime * 3));
        $score += min(30, $responseScore);

        // Estabilidade (20% do score)
        $stabilityScore = 20;
        if ($this->circuitBreakerTrips > 0) $stabilityScore -= 5;
        if ($this->rateLimitHits > 0) $stabilityScore -= 3;
        if ($this->pendingRetries > 10) $stabilityScore -= 5;
        $score += max(0, $stabilityScore);

        // Consistência (10% do score)
        $consistencyScore = 10;
        if ($this->p99ResponseTime > ($this->avgResponseTime * 3)) {
            $consistencyScore -= 5; // Alta variabilidade
        }
        $score += max(0, $consistencyScore);

        return (int) min(100, max(0, $score));
    }

    /**
     * Obtém distribuição de status em formato percentual
     */
    public function getStatusCodePercentages(): array
    {
        if ($this->totalDeliveries === 0) {
            return [];
        }

        $percentages = [];
        foreach ($this->statusCodeDistribution as $code => $count) {
            $percentages[$code] = round(($count / $this->totalDeliveries) * 100, 2);
        }

        return $percentages;
    }

    /**
     * Obtém distribuição de erros em formato percentual
     */
    public function getErrorPercentages(): array
    {
        if ($this->failedDeliveries === 0) {
            return [];
        }

        $percentages = [];
        foreach ($this->errorDistribution as $error => $count) {
            $percentages[$error] = round(($count / $this->failedDeliveries) * 100, 2);
        }

        return $percentages;
    }

    /**
     * Obtém resumo executivo
     */
    public function getExecutiveSummary(): array
    {
        return [
            'webhook_id' => $this->webhookId,
            'period' => $this->period,
            'health_level' => $this->getHealthLevel(),
            'quality_score' => $this->getQualityScore(),
            'total_deliveries' => $this->totalDeliveries,
            'success_rate' => round($this->successRate * 100, 2) . '%',
            'avg_response_time' => round($this->avgResponseTime, 2) . 's',
            'trend' => $this->getPerformanceTrend(),
            'top_issues' => array_slice($this->getTopIssues(), 0, 3),
            'calculated_at' => $this->calculatedAt,
        ];
    }

    /**
     * Compara com estatísticas anteriores
     */
    public function compareWith(WebhookStatsData $previous): array
    {
        return [
            'deliveries_change' => $this->totalDeliveries - $previous->totalDeliveries,
            'success_rate_change' => $this->successRate - $previous->successRate,
            'response_time_change' => $this->avgResponseTime - $previous->avgResponseTime,
            'quality_score_change' => $this->getQualityScore() - $previous->getQualityScore(),
            'new_issues' => array_diff_key($this->getTopIssues(), $previous->getTopIssues()),
        ];
    }

    /**
     * Valida dados das estatísticas
     */
    protected function validate(): void
    {
        if (empty($this->webhookId)) {
            throw new \InvalidArgumentException('Webhook ID é obrigatório');
        }

        if (empty($this->period)) {
            throw new \InvalidArgumentException('Período é obrigatório');
        }

        if ($this->totalDeliveries < 0) {
            throw new \InvalidArgumentException('Total de entregas não pode ser negativo');
        }

        if ($this->successfulDeliveries > $this->totalDeliveries) {
            throw new \InvalidArgumentException('Entregas bem-sucedidas não pode ser maior que total');
        }

        if ($this->failedDeliveries > $this->totalDeliveries) {
            throw new \InvalidArgumentException('Entregas falhadas não pode ser maior que total');
        }

        if ($this->successRate < 0.0 || $this->successRate > 1.0) {
            throw new \InvalidArgumentException('Taxa de sucesso deve estar entre 0.0 e 1.0');
        }

        if ($this->failureRate < 0.0 || $this->failureRate > 1.0) {
            throw new \InvalidArgumentException('Taxa de falha deve estar entre 0.0 e 1.0');
        }

        if ($this->avgResponseTime < 0.0) {
            throw new \InvalidArgumentException('Tempo médio de resposta não pode ser negativo');
        }
    }
}