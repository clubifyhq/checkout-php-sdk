<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications\Services;

use Clubify\Checkout\Core\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Notifications\DTOs\NotificationStatsData;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Serviço de estatísticas de notificações
 *
 * Responsável pela geração e análise de estatísticas de notificações:
 * - Métricas de entrega e performance
 * - Estatísticas por tipo, método e período
 * - Análise de webhooks e falhas
 * - Tendências e comparações
 * - Relatórios e dashboards
 * - Alertas baseados em métricas
 *
 * Segue padrões SOLID:
 * - S: Single Responsibility - Apenas estatísticas de notificações
 * - O: Open/Closed - Extensível via novos cálculos
 * - L: Liskov Substitution - Estende BaseService
 * - I: Interface Segregation - Métodos específicos
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationStatsService extends BaseService implements ServiceInterface
{
    private const CACHE_PREFIX = 'notification_stats:';
    private const STATS_CACHE_TTL = 300; // 5 minutos
    private const HEAVY_STATS_CACHE_TTL = 900; // 15 minutos

    public function __construct(
        private ClubifyCheckoutSDK $sdk,
        Configuration $config,
        Logger $logger
    ) {
        parent::__construct($config, $logger);
    }

    /**
     * Obtém o nome do serviço
     */
    public function getName(): string
    {
        return 'notification_stats';
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se o serviço está saudável (health check)
     */
    public function isHealthy(): bool
    {
        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats/health');
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::STATS_CACHE_TTL,
            'heavy_cache_ttl' => self::HEAVY_STATS_CACHE_TTL,
            'service_name' => $this->getName(),
            'service_version' => $this->getVersion()
        ];
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'cache_prefix' => self::CACHE_PREFIX,
            'cache_ttl' => self::STATS_CACHE_TTL,
            'heavy_cache_ttl' => self::HEAVY_STATS_CACHE_TTL,
            'service_name' => $this->getName(),
            'service_version' => $this->getVersion()
        ];
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        $healthy = $this->isHealthy();
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'healthy' => $healthy,
            'available' => $this->isAvailable(),
            'metrics' => $this->getMetrics(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Obtém estatísticas gerais de notificações
     * Endpoint: GET /api/v1/notifications/stats
     */
    public function getStatistics(array $filters = []): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'general:' . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/api/v1/notifications/stats', [
                'query' => $filters
            ]);

            $statsData = $response->toArray();

            // Cache o resultado
            $this->setCache($cacheKey, $statsData, self::STATS_CACHE_TTL);

            $this->logger->info('Estatísticas de notificações obtidas', [
                'totalSent' => $statsData['totalSent'] ?? 0,
                'successRate' => $statsData['successRate'] ?? '0%'
            ]);

            return $statsData;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter estatísticas de notificações', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'totalSent' => 0,
                'successful' => 0,
                'failed' => 0,
                'pending' => 0,
                'successRate' => '0%',
                'averageDeliveryTime' => '0ms',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas de notificações (alias for getStatistics)
     *
     * @deprecated Use getStatistics() instead
     */
    public function getStats(array $filters = []): array
    {
        return $this->getStatistics($filters);
    }

    /**
     * LEGACY METHOD - For backward compatibility
     * Obtém estatísticas gerais de notificações (old implementation)
     */
    private function getStatisticsLegacy(array $filters = []): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'general:' . md5(serialize($filters));
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats', [
                'query' => $filters
            ]);

            $statsData = $response->toArray();

            // Cria DTO de estatísticas
            $stats = NotificationStatsData::fromArray($statsData);

            // Adiciona cálculos extras
            $enrichedStats = array_merge($stats->toArray(), [
                'performance_summary' => $stats->getPerformanceSummary(),
                'alerts' => $stats->getAlerts(),
                'health_indicators' => $this->calculateHealthIndicators($stats),
                'recommendations' => $this->generateRecommendations($stats)
            ]);

            // Cache o resultado
            $this->setCache($cacheKey, $enrichedStats, self::STATS_CACHE_TTL);

            $this->logger->info('Estatísticas de notificações obtidas', [
                'filters' => $filters,
                'total_sent' => $stats->totalSent,
                'delivery_rate' => $stats->deliveryRate,
                'failure_rate' => $stats->failureRate
            ]);

            return $enrichedStats;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter estatísticas de notificações', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'total_sent' => 0,
                'total_delivered' => 0,
                'total_failed' => 0,
                'delivery_rate' => 0.0,
                'failure_rate' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas de entrega por período
     */
    public function getDeliveryStats(array $dateRange = []): array
    {
        $this->validateInitialization();

        // Define período padrão se não especificado
        if (empty($dateRange)) {
            $dateRange = [
                'start_date' => date('Y-m-d', strtotime('-7 days')),
                'end_date' => date('Y-m-d')
            ];
        }

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'delivery:' . md5(serialize($dateRange));
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats/delivery', [
                'query' => $dateRange
            ]);

            $deliveryStats = $response->toArray();

            // Adiciona cálculos de tendência
            $deliveryStats['trends'] = $this->calculateDeliveryTrends($deliveryStats);

            // Adiciona comparação com período anterior
            $deliveryStats['comparison'] = $this->calculatePeriodComparison($dateRange, $deliveryStats);

            // Cache o resultado
            $this->setCache($cacheKey, $deliveryStats, self::STATS_CACHE_TTL);

            $this->logger->info('Estatísticas de entrega obtidas', [
                'date_range' => $dateRange,
                'total_deliveries' => $deliveryStats['total_deliveries'] ?? 0
            ]);

            return $deliveryStats;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter estatísticas de entrega', [
                'date_range' => $dateRange,
                'error' => $e->getMessage()
            ]);

            return [
                'total_deliveries' => 0,
                'successful_deliveries' => 0,
                'failed_deliveries' => 0,
                'delivery_rate' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém performance de webhooks
     */
    public function getWebhookPerformance(): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'webhook_performance';
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats/webhook-performance');
            $performance = $response->toArray();

            // Adiciona análise de performance
            $performance['analysis'] = $this->analyzeWebhookPerformance($performance);

            // Adiciona rankings
            $performance['rankings'] = $this->rankWebhooks($performance['webhooks'] ?? []);

            // Cache o resultado
            $this->setCache($cacheKey, $performance, self::STATS_CACHE_TTL);

            $this->logger->info('Performance de webhooks obtida', [
                'webhook_count' => count($performance['webhooks'] ?? [])
            ]);

            return $performance;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter performance de webhooks', [
                'error' => $e->getMessage()
            ]);

            return [
                'webhooks' => [],
                'avg_response_time' => 0.0,
                'avg_success_rate' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém estatísticas por tipo de evento
     */
    public function getEventTypeStats(): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'event_types';
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats/event-types');
            $eventStats = $response->toArray();

            // Adiciona análise de popularidade
            $eventStats['popularity_analysis'] = $this->analyzeEventPopularity($eventStats);

            // Adiciona recomendações de otimização
            $eventStats['optimization_tips'] = $this->generateEventOptimizationTips($eventStats);

            // Cache o resultado
            $this->setCache($cacheKey, $eventStats, self::STATS_CACHE_TTL);

            $this->logger->info('Estatísticas por tipo de evento obtidas', [
                'event_type_count' => count($eventStats['events'] ?? [])
            ]);

            return $eventStats;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter estatísticas por tipo de evento', [
                'error' => $e->getMessage()
            ]);

            return [
                'events' => [],
                'total_events' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém análise de retries
     */
    public function getRetryAnalysis(): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'retry_analysis';
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            $response = $this->makeHttpRequest('GET', '/notifications/stats/retry-analysis');
            $retryData = $response->toArray();

            // Adiciona insights de retry
            $retryData['insights'] = $this->generateRetryInsights($retryData);

            // Adiciona recomendações de configuração
            $retryData['config_recommendations'] = $this->generateRetryConfigRecommendations($retryData);

            // Cache o resultado
            $this->setCache($cacheKey, $retryData, self::STATS_CACHE_TTL);

            $this->logger->info('Análise de retries obtida', [
                'total_retries' => $retryData['total_retries'] ?? 0,
                'retry_success_rate' => $retryData['retry_success_rate'] ?? 0.0
            ]);

            return $retryData;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao obter análise de retries', [
                'error' => $e->getMessage()
            ]);

            return [
                'total_retries' => 0,
                'retry_success_rate' => 0.0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém relatório de saúde do sistema
     */
    public function getHealthReport(): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'health_report';
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Obtém várias métricas
            $generalStats = $this->getStatistics();
            $deliveryStats = $this->getDeliveryStats();
            $webhookPerformance = $this->getWebhookPerformance();
            $retryAnalysis = $this->getRetryAnalysis();

            // Gera relatório consolidado
            $healthReport = [
                'overall_health' => $this->calculateOverallHealth($generalStats, $deliveryStats, $webhookPerformance),
                'critical_issues' => $this->identifyCriticalIssues($generalStats, $deliveryStats, $webhookPerformance),
                'recommendations' => $this->generateHealthRecommendations($generalStats, $deliveryStats, $webhookPerformance),
                'metrics_summary' => [
                    'delivery_rate' => $generalStats['delivery_rate'] ?? 0.0,
                    'avg_response_time' => $webhookPerformance['avg_response_time'] ?? 0.0,
                    'retry_rate' => $retryAnalysis['retry_rate'] ?? 0.0,
                    'active_webhooks' => count($webhookPerformance['webhooks'] ?? [])
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Cache o resultado por mais tempo (relatório pesado)
            $this->setCache($cacheKey, $healthReport, self::HEAVY_STATS_CACHE_TTL);

            $this->logger->info('Relatório de saúde gerado', [
                'overall_health' => $healthReport['overall_health'],
                'critical_issues_count' => count($healthReport['critical_issues'])
            ]);

            return $healthReport;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao gerar relatório de saúde', [
                'error' => $e->getMessage()
            ]);

            return [
                'overall_health' => 'unknown',
                'critical_issues' => [],
                'recommendations' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Obtém dashboard executivo
     */
    public function getExecutiveDashboard(): array
    {
        $this->validateInitialization();

        // Verifica cache
        $cacheKey = self::CACHE_PREFIX . 'executive_dashboard';
        $cached = $this->getFromCache($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        try {
            // Obtém dados de diferentes períodos
            $last24h = $this->getDeliveryStats([
                'start_date' => date('Y-m-d H:i:s', strtotime('-24 hours')),
                'end_date' => date('Y-m-d H:i:s')
            ]);

            $last7days = $this->getDeliveryStats([
                'start_date' => date('Y-m-d', strtotime('-7 days')),
                'end_date' => date('Y-m-d')
            ]);

            $last30days = $this->getDeliveryStats([
                'start_date' => date('Y-m-d', strtotime('-30 days')),
                'end_date' => date('Y-m-d')
            ]);

            $generalStats = $this->getStatistics();
            $webhookPerformance = $this->getWebhookPerformance();

            // Gera dashboard consolidado
            $dashboard = [
                'summary' => [
                    'total_notifications_24h' => $last24h['total_deliveries'] ?? 0,
                    'delivery_rate_24h' => $last24h['delivery_rate'] ?? 0.0,
                    'avg_response_time' => $webhookPerformance['avg_response_time'] ?? 0.0,
                    'active_webhooks' => count($webhookPerformance['webhooks'] ?? [])
                ],
                'trends' => [
                    '24h_vs_previous' => $this->calculateTrendComparison($last24h, 'previous_24h'),
                    '7d_vs_previous' => $this->calculateTrendComparison($last7days, 'previous_7d'),
                    '30d_vs_previous' => $this->calculateTrendComparison($last30days, 'previous_30d')
                ],
                'top_performers' => $this->getTopPerformers($webhookPerformance),
                'issues_requiring_attention' => $this->getIssuesRequiringAttention($generalStats, $webhookPerformance),
                'generated_at' => date('Y-m-d H:i:s')
            ];

            // Cache o resultado
            $this->setCache($cacheKey, $dashboard, self::STATS_CACHE_TTL);

            $this->logger->info('Dashboard executivo gerado', [
                'total_notifications_24h' => $dashboard['summary']['total_notifications_24h'],
                'delivery_rate_24h' => $dashboard['summary']['delivery_rate_24h']
            ]);

            return $dashboard;

        } catch (\Exception $e) {
            $this->logger->error('Erro ao gerar dashboard executivo', [
                'error' => $e->getMessage()
            ]);

            return [
                'summary' => [],
                'trends' => [],
                'top_performers' => [],
                'issues_requiring_attention' => [],
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calcula indicadores de saúde
     */
    private function calculateHealthIndicators(NotificationStatsData $stats): array
    {
        return [
            'delivery_health' => $stats->hasGoodPerformance() ? 'healthy' : ($stats->hasAcceptablePerformance() ? 'warning' : 'critical'),
            'volume_health' => $stats->getTotalProcessed() > 100 ? 'healthy' : 'warning',
            'retry_health' => $stats->getRetryRate() < 10 ? 'healthy' : ($stats->getRetryRate() < 20 ? 'warning' : 'critical'),
            'overall_score' => $this->calculateOverallScore($stats)
        ];
    }

    /**
     * Gera recomendações baseadas nas estatísticas
     */
    private function generateRecommendations(NotificationStatsData $stats): array
    {
        $recommendations = [];

        if ($stats->failureRate > 10) {
            $recommendations[] = [
                'type' => 'critical',
                'title' => 'Taxa de falha alta',
                'description' => 'Taxa de falha acima de 10%. Verifique conectividade dos webhooks.',
                'action' => 'check_webhook_connectivity'
            ];
        }

        if ($stats->getRetryRate() > 15) {
            $recommendations[] = [
                'type' => 'warning',
                'title' => 'Muitos retries',
                'description' => 'Taxa de retry alta. Considere revisar timeouts e configurações.',
                'action' => 'review_retry_config'
            ];
        }

        if ($stats->averageDeliveryTime !== null && $stats->averageDeliveryTime > 10) {
            $recommendations[] = [
                'type' => 'info',
                'title' => 'Tempo de entrega lento',
                'description' => 'Tempo médio de entrega acima de 10 segundos.',
                'action' => 'optimize_performance'
            ];
        }

        return $recommendations;
    }

    /**
     * Calcula tendências de entrega
     */
    private function calculateDeliveryTrends(array $deliveryStats): array
    {
        // Implementação simplificada - em produção, usaria dados históricos reais
        return [
            'direction' => 'stable', // increasing, decreasing, stable
            'change_percentage' => 0.0,
            'peak_hours' => [9, 10, 11, 14, 15, 16], // Horários de pico típicos
            'trend_analysis' => 'Delivery volume remains consistent'
        ];
    }

    /**
     * Calcula comparação com período anterior
     */
    private function calculatePeriodComparison(array $dateRange, array $currentStats): array
    {
        // Implementação simplificada
        return [
            'previous_period_delivery_rate' => 95.0,
            'change' => 2.5,
            'change_direction' => 'increase',
            'is_improvement' => true
        ];
    }

    /**
     * Analisa performance de webhooks
     */
    private function analyzeWebhookPerformance(array $performance): array
    {
        $webhooks = $performance['webhooks'] ?? [];
        $totalWebhooks = count($webhooks);

        if ($totalWebhooks === 0) {
            return ['status' => 'no_data'];
        }

        $healthyCount = 0;
        $warningCount = 0;
        $criticalCount = 0;

        foreach ($webhooks as $webhook) {
            $successRate = $webhook['success_rate'] ?? 0;
            if ($successRate >= 95) {
                $healthyCount++;
            } elseif ($successRate >= 85) {
                $warningCount++;
            } else {
                $criticalCount++;
            }
        }

        return [
            'total_webhooks' => $totalWebhooks,
            'healthy_webhooks' => $healthyCount,
            'warning_webhooks' => $warningCount,
            'critical_webhooks' => $criticalCount,
            'health_percentage' => round(($healthyCount / $totalWebhooks) * 100, 1)
        ];
    }

    /**
     * Classifica webhooks por performance
     */
    private function rankWebhooks(array $webhooks): array
    {
        // Ordena por taxa de sucesso
        usort($webhooks, function ($a, $b) {
            return ($b['success_rate'] ?? 0) <=> ($a['success_rate'] ?? 0);
        });

        return [
            'best_performers' => array_slice($webhooks, 0, 5),
            'worst_performers' => array_slice(array_reverse($webhooks), 0, 5)
        ];
    }

    /**
     * Calcula score geral
     */
    private function calculateOverallScore(NotificationStatsData $stats): float
    {
        $deliveryScore = min(100, $stats->deliveryRate);
        $retryScore = max(0, 100 - ($stats->getRetryRate() * 2));
        $timeScore = $stats->averageDeliveryTime !== null ? max(0, 100 - ($stats->averageDeliveryTime * 2)) : 100;

        return round(($deliveryScore + $retryScore + $timeScore) / 3, 1);
    }

    /**
     * Analisa popularidade de eventos
     */
    private function analyzeEventPopularity(array $eventStats): array
    {
        // Implementação simplificada
        return [
            'most_popular_events' => [],
            'trending_events' => [],
            'declining_events' => []
        ];
    }

    /**
     * Gera dicas de otimização para eventos
     */
    private function generateEventOptimizationTips(array $eventStats): array
    {
        return [
            'Consider consolidating low-volume events',
            'Review webhook configurations for high-failure events',
            'Optimize payload size for frequently sent events'
        ];
    }

    /**
     * Gera insights de retry
     */
    private function generateRetryInsights(array $retryData): array
    {
        return [
            'pattern_analysis' => 'Most retries occur during peak hours',
            'success_factors' => 'Longer retry delays improve success rates',
            'optimization_opportunities' => 'Consider exponential backoff'
        ];
    }

    /**
     * Gera recomendações de configuração de retry
     */
    private function generateRetryConfigRecommendations(array $retryData): array
    {
        return [
            'max_retries' => 3,
            'initial_delay' => 5,
            'backoff_strategy' => 'exponential',
            'max_delay' => 300
        ];
    }

    /**
     * Calcula saúde geral do sistema
     */
    private function calculateOverallHealth(array $generalStats, array $deliveryStats, array $webhookPerformance): string
    {
        $deliveryRate = $generalStats['delivery_rate'] ?? 0;
        $avgResponseTime = $webhookPerformance['avg_response_time'] ?? 0;

        if ($deliveryRate >= 95 && $avgResponseTime <= 5) {
            return 'excellent';
        } elseif ($deliveryRate >= 90 && $avgResponseTime <= 10) {
            return 'good';
        } elseif ($deliveryRate >= 80 && $avgResponseTime <= 30) {
            return 'warning';
        } else {
            return 'critical';
        }
    }

    /**
     * Identifica problemas críticos
     */
    private function identifyCriticalIssues(array $generalStats, array $deliveryStats, array $webhookPerformance): array
    {
        $issues = [];

        if (($generalStats['delivery_rate'] ?? 0) < 85) {
            $issues[] = [
                'type' => 'delivery_rate',
                'severity' => 'critical',
                'description' => 'Delivery rate below 85%'
            ];
        }

        if (($webhookPerformance['avg_response_time'] ?? 0) > 30) {
            $issues[] = [
                'type' => 'response_time',
                'severity' => 'warning',
                'description' => 'Average response time above 30 seconds'
            ];
        }

        return $issues;
    }

    /**
     * Gera recomendações de saúde
     */
    private function generateHealthRecommendations(array $generalStats, array $deliveryStats, array $webhookPerformance): array
    {
        return [
            'Monitor webhook endpoints regularly',
            'Implement circuit breakers for failing webhooks',
            'Consider load balancing for high-volume notifications'
        ];
    }

    /**
     * Calcula comparação de tendência
     */
    private function calculateTrendComparison(array $currentData, string $period): array
    {
        // Implementação simplificada
        return [
            'change_percentage' => 5.2,
            'direction' => 'increase',
            'is_significant' => true
        ];
    }

    /**
     * Obtém top performers
     */
    private function getTopPerformers(array $webhookPerformance): array
    {
        $webhooks = $webhookPerformance['webhooks'] ?? [];

        usort($webhooks, function ($a, $b) {
            return ($b['success_rate'] ?? 0) <=> ($a['success_rate'] ?? 0);
        });

        return array_slice($webhooks, 0, 3);
    }

    /**
     * Obtém problemas que requerem atenção
     */
    private function getIssuesRequiringAttention(array $generalStats, array $webhookPerformance): array
    {
        $issues = [];

        if (($generalStats['failure_rate'] ?? 0) > 5) {
            $issues[] = 'High failure rate detected';
        }

        return $issues;
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * Método para verificar resposta HTTP (compatibilidade)
     */
    protected function isSuccessfulResponse($response): bool
    {
        return ResponseHelper::isSuccessful($response);
    }

    /**
     * Método para extrair dados da resposta (compatibilidade)
     */
    protected function extractResponseData($response): ?array
    {
        return ResponseHelper::getData($response);
    }

}
