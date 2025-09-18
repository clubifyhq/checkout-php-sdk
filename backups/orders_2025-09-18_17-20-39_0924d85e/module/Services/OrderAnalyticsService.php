<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Exceptions\ValidationException;
use ClubifyCheckout\Exceptions\HttpException;

/**
 * Serviço de analytics de pedidos
 *
 * Responsável pela geração de relatórios e análises de pedidos:
 * - Estatísticas gerais de pedidos
 * - Análises de receita e faturamento
 * - Métricas de conversão
 * - Relatórios de performance
 * - Analytics de clientes e produtos
 * - Tendências e forecasting
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas analytics de pedidos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de analytics
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderAnalyticsService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'order_analytics';
    }

    /**
     * Obtém estatísticas gerais de pedidos
     */
    public function getStatistics(array $filters = []): array
    {
        return $this->executeWithMetrics('get_order_statistics', function () use ($filters) {
            $response = $this->httpClient->get('/orders/statistics', [
                'query' => $filters
            ]);

            $stats = $response->getData() ?? [];

            // Calcular métricas adicionais se necessário
            if (!empty($stats)) {
                $stats = $this->enhanceStatistics($stats);
            }

            return $stats;
        });
    }

    /**
     * Obtém estatísticas de receita
     */
    public function getRevenueStats(array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_revenue_statistics', function () use ($dateRange) {
            $this->validateDateRange($dateRange);

            $response = $this->httpClient->get('/orders/revenue-stats', [
                'query' => $dateRange
            ]);

            $revenueStats = $response->getData() ?? [];

            // Adicionar cálculos de crescimento e tendências
            if (!empty($revenueStats)) {
                $revenueStats = $this->enhanceRevenueStats($revenueStats);
            }

            return $revenueStats;
        });
    }

    /**
     * Obtém top clientes por valor de compras
     */
    public function getTopCustomers(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_customers', function () use ($limit) {
            if ($limit < 1 || $limit > 100) {
                throw new ValidationException('Limit must be between 1 and 100');
            }

            $response = $this->httpClient->get('/orders/top-customers', [
                'query' => ['limit' => $limit]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém top produtos mais vendidos
     */
    public function getTopProducts(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_products', function () use ($limit) {
            if ($limit < 1 || $limit > 100) {
                throw new ValidationException('Limit must be between 1 and 100');
            }

            $response = $this->httpClient->get('/orders/top-products', [
                'query' => ['limit' => $limit]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém métricas de conversão
     */
    public function getConversionMetrics(): array
    {
        return $this->executeWithMetrics('get_conversion_metrics', function () {
            $response = $this->httpClient->get('/orders/conversion-metrics');
            $metrics = $response->getData() ?? [];

            // Calcular métricas adicionais de conversão
            if (!empty($metrics)) {
                $metrics = $this->enhanceConversionMetrics($metrics);
            }

            return $metrics;
        });
    }

    /**
     * Obtém análise de cohort de clientes
     */
    public function getCohortAnalysis(string $period = 'monthly'): array
    {
        return $this->executeWithMetrics('get_cohort_analysis', function () use ($period) {
            $allowedPeriods = ['weekly', 'monthly', 'quarterly'];
            if (!in_array($period, $allowedPeriods)) {
                throw new ValidationException("Invalid period. Allowed: " . implode(', ', $allowedPeriods));
            }

            $response = $this->httpClient->get('/orders/cohort-analysis', [
                'query' => ['period' => $period]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém análise de valor do tempo de vida (LTV)
     */
    public function getLifetimeValueAnalysis(): array
    {
        return $this->executeWithMetrics('get_ltv_analysis', function () {
            $response = $this->httpClient->get('/orders/lifetime-value-analysis');
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém análise de sazonalidade
     */
    public function getSeasonalityAnalysis(array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_seasonality_analysis', function () use ($dateRange) {
            $this->validateDateRange($dateRange);

            $response = $this->httpClient->get('/orders/seasonality-analysis', [
                'query' => $dateRange
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém análise de performance por canal
     */
    public function getChannelPerformance(array $filters = []): array
    {
        return $this->executeWithMetrics('get_channel_performance', function () use ($filters) {
            $response = $this->httpClient->get('/orders/channel-performance', [
                'query' => $filters
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém relatório de abandono de carrinho
     */
    public function getAbandonmentReport(array $filters = []): array
    {
        return $this->executeWithMetrics('get_abandonment_report', function () use ($filters) {
            $response = $this->httpClient->get('/orders/abandonment-report', [
                'query' => $filters
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém análise de pricing e margem
     */
    public function getPricingAnalysis(array $filters = []): array
    {
        return $this->executeWithMetrics('get_pricing_analysis', function () use ($filters) {
            $response = $this->httpClient->get('/orders/pricing-analysis', [
                'query' => $filters
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém métricas de fulfillment
     */
    public function getFulfillmentMetrics(array $filters = []): array
    {
        return $this->executeWithMetrics('get_fulfillment_metrics', function () use ($filters) {
            $response = $this->httpClient->get('/orders/fulfillment-metrics', [
                'query' => $filters
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém análise geográfica de vendas
     */
    public function getGeographicAnalysis(array $filters = []): array
    {
        return $this->executeWithMetrics('get_geographic_analysis', function () use ($filters) {
            $response = $this->httpClient->get('/orders/geographic-analysis', [
                'query' => $filters
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém forecast de vendas
     */
    public function getSalesForecast(int $days = 30): array
    {
        return $this->executeWithMetrics('get_sales_forecast', function () use ($days) {
            if ($days < 1 || $days > 365) {
                throw new ValidationException('Forecast days must be between 1 and 365');
            }

            $response = $this->httpClient->get('/orders/sales-forecast', [
                'query' => ['days' => $days]
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém comparação de períodos
     */
    public function getPeriodComparison(array $currentPeriod, array $comparisonPeriod): array
    {
        return $this->executeWithMetrics('get_period_comparison', function () use ($currentPeriod, $comparisonPeriod) {
            $this->validateDateRange($currentPeriod);
            $this->validateDateRange($comparisonPeriod);

            $data = [
                'current_period' => $currentPeriod,
                'comparison_period' => $comparisonPeriod
            ];

            $response = $this->httpClient->post('/orders/period-comparison', $data);
            $comparison = $response->getData() ?? [];

            // Calcular variações percentuais
            if (!empty($comparison)) {
                $comparison = $this->calculatePeriodVariations($comparison);
            }

            return $comparison;
        });
    }

    /**
     * Obtém dashboard executivo
     */
    public function getExecutiveDashboard(array $filters = []): array
    {
        return $this->executeWithMetrics('get_executive_dashboard', function () use ($filters) {
            // Combinar múltiplas métricas em um dashboard
            $dashboard = [];

            try {
                // Estatísticas gerais
                $dashboard['general_stats'] = $this->getStatistics($filters);

                // Receita
                $dashboard['revenue_stats'] = $this->getRevenueStats($filters);

                // Top performers
                $dashboard['top_customers'] = $this->getTopCustomers(5);
                $dashboard['top_products'] = $this->getTopProducts(5);

                // Conversão
                $dashboard['conversion_metrics'] = $this->getConversionMetrics();

                // Performance por canal
                $dashboard['channel_performance'] = $this->getChannelPerformance($filters);

                // Forecast
                $dashboard['sales_forecast'] = $this->getSalesForecast(7);

                // Timestamp
                $dashboard['generated_at'] = date('Y-m-d H:i:s');

            } catch (\Exception $e) {
                $this->logger->error('Error generating executive dashboard', [
                    'error' => $e->getMessage(),
                    'filters' => $filters
                ]);

                throw $e;
            }

            return $dashboard;
        });
    }

    /**
     * Exporta relatório para CSV
     */
    public function exportReport(string $reportType, array $filters = [], string $format = 'csv'): array
    {
        return $this->executeWithMetrics('export_report', function () use ($reportType, $filters, $format) {
            $allowedFormats = ['csv', 'xlsx', 'pdf'];
            if (!in_array($format, $allowedFormats)) {
                throw new ValidationException("Invalid format. Allowed: " . implode(', ', $allowedFormats));
            }

            $allowedReports = [
                'orders', 'revenue', 'customers', 'products', 'conversion',
                'cohort', 'geographic', 'fulfillment'
            ];
            if (!in_array($reportType, $allowedReports)) {
                throw new ValidationException("Invalid report type. Allowed: " . implode(', ', $allowedReports));
            }

            $data = array_merge($filters, [
                'report_type' => $reportType,
                'format' => $format
            ]);

            $response = $this->httpClient->post('/orders/export-report', $data);
            return $response->getData() ?? [];
        });
    }

    /**
     * Valida intervalo de datas
     */
    private function validateDateRange(array $dateRange): void
    {
        if (empty($dateRange)) {
            return;
        }

        if (isset($dateRange['start_date'])) {
            if (!$this->isValidDate($dateRange['start_date'])) {
                throw new ValidationException('Invalid start_date format');
            }
        }

        if (isset($dateRange['end_date'])) {
            if (!$this->isValidDate($dateRange['end_date'])) {
                throw new ValidationException('Invalid end_date format');
            }
        }

        if (isset($dateRange['start_date']) && isset($dateRange['end_date'])) {
            if (strtotime($dateRange['start_date']) > strtotime($dateRange['end_date'])) {
                throw new ValidationException('start_date cannot be after end_date');
            }
        }
    }

    /**
     * Verifica se data é válida
     */
    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    /**
     * Melhora estatísticas com cálculos adicionais
     */
    private function enhanceStatistics(array $stats): array
    {
        // Calcular AOV (Average Order Value)
        if (isset($stats['total_revenue']) && isset($stats['total_orders']) && $stats['total_orders'] > 0) {
            $stats['average_order_value'] = round($stats['total_revenue'] / $stats['total_orders'], 2);
        }

        // Calcular taxa de retorno
        if (isset($stats['total_orders']) && isset($stats['returned_orders']) && $stats['total_orders'] > 0) {
            $stats['return_rate'] = round(($stats['returned_orders'] / $stats['total_orders']) * 100, 2);
        }

        // Calcular taxa de cancelamento
        if (isset($stats['total_orders']) && isset($stats['cancelled_orders']) && $stats['total_orders'] > 0) {
            $stats['cancellation_rate'] = round(($stats['cancelled_orders'] / $stats['total_orders']) * 100, 2);
        }

        return $stats;
    }

    /**
     * Melhora estatísticas de receita
     */
    private function enhanceRevenueStats(array $stats): array
    {
        // Calcular crescimento se houver dados históricos
        if (isset($stats['current_revenue']) && isset($stats['previous_revenue']) && $stats['previous_revenue'] > 0) {
            $growth = (($stats['current_revenue'] - $stats['previous_revenue']) / $stats['previous_revenue']) * 100;
            $stats['revenue_growth_percentage'] = round($growth, 2);
        }

        return $stats;
    }

    /**
     * Melhora métricas de conversão
     */
    private function enhanceConversionMetrics(array $metrics): array
    {
        // Calcular funil de conversão se os dados estiverem disponíveis
        if (isset($metrics['visits']) && isset($metrics['cart_additions']) && $metrics['visits'] > 0) {
            $metrics['visit_to_cart_rate'] = round(($metrics['cart_additions'] / $metrics['visits']) * 100, 2);
        }

        if (isset($metrics['cart_additions']) && isset($metrics['checkouts']) && $metrics['cart_additions'] > 0) {
            $metrics['cart_to_checkout_rate'] = round(($metrics['checkouts'] / $metrics['cart_additions']) * 100, 2);
        }

        if (isset($metrics['checkouts']) && isset($metrics['orders']) && $metrics['checkouts'] > 0) {
            $metrics['checkout_to_order_rate'] = round(($metrics['orders'] / $metrics['checkouts']) * 100, 2);
        }

        return $metrics;
    }

    /**
     * Calcula variações entre períodos
     */
    private function calculatePeriodVariations(array $comparison): array
    {
        $current = $comparison['current'] ?? [];
        $previous = $comparison['previous'] ?? [];

        foreach ($current as $key => $value) {
            if (is_numeric($value) && isset($previous[$key]) && is_numeric($previous[$key]) && $previous[$key] > 0) {
                $variation = (($value - $previous[$key]) / $previous[$key]) * 100;
                $comparison['variations'][$key] = round($variation, 2);
            }
        }

        return $comparison;
    }
}
