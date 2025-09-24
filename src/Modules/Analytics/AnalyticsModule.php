<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Analytics;

use Clubify\Checkout\Core\BaseModule;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Módulo Analytics
 *
 * Fornece funcionalidades avançadas de analytics e relatórios,
 * incluindo métricas de vendas, análise de funil, segmentação
 * de clientes e business intelligence.
 */
class AnalyticsModule extends BaseModule
{
    /**
     * Obtém métricas de vendas
     */
    public function getSalesMetrics(array $filters = []): array
    {
        $endpoint = '/analytics/sales/metrics';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise do funil de conversão
     */
    public function getFunnelAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/funnel';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém produtos mais vendidos
     */
    public function getTopProducts(array $filters = []): array
    {
        $endpoint = '/analytics/products/top';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise de segmentação de clientes
     */
    public function getCustomerSegmentAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/customers/segments';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém Customer Lifetime Value (CLV)
     */
    public function getLTVAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/customers/ltv';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém relatório de retenção de clientes
     */
    public function getRetentionReport(array $filters = []): array
    {
        $endpoint = '/analytics/customers/retention';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise de coorte
     */
    public function getCohortAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/cohort';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém métricas de performance do site
     */
    public function getPerformanceMetrics(array $filters = []): array
    {
        $endpoint = '/analytics/performance';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém relatório de campanhas de marketing
     */
    public function getMarketingCampaignReport(array $filters = []): array
    {
        $endpoint = '/analytics/marketing/campaigns';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise de atribuição
     */
    public function getAttributionAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/attribution';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém relatório de abandono de carrinho
     */
    public function getCartAbandonmentReport(array $filters = []): array
    {
        $endpoint = '/analytics/cart/abandonment';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise de A/B testing
     */
    public function getABTestingResults(string $testId): array
    {
        $endpoint = "/analytics/ab-testing/{$testId}";
        return $this->makeHttpRequest('GET', $endpoint);
    }

    /**
     * Registra um evento customizado para analytics
     */
    public function recordEvent(string $entityId, array $eventData): bool
    {
        $endpoint = '/analytics/events';

        $data = [
            'entity_id' => $entityId,
            'event_type' => $eventData['event_type'],
            'event_name' => $eventData['event_name'],
            'properties' => $eventData['properties'] ?? [],
            'timestamp' => $eventData['timestamp'] ?? time()
        ];

        $response = $this->makeHttpRequest('POST', $endpoint, $data);
        return $response['success'] ?? false;
    }

    /**
     * Registra uso/métrica para analytics
     */
    public function recordUsage(string $entityId, array $usageData): bool
    {
        $endpoint = '/analytics/usage';

        $data = [
            'entity_id' => $entityId,
            'metric' => $usageData['metric'],
            'value' => $usageData['value'],
            'timestamp' => $usageData['timestamp'] ?? time()
        ];

        $response = $this->makeHttpRequest('POST', $endpoint, $data);
        return $response['success'] ?? false;
    }

    /**
     * Obtém health score de uma assinatura
     */
    public function getSubscriptionHealthScore(string $subscriptionId): array
    {
        $endpoint = "/analytics/subscriptions/{$subscriptionId}/health";
        return $this->makeHttpRequest('GET', $endpoint);
    }

    /**
     * Obtém análise de churn
     */
    public function getChurnAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/churn';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém previsões baseadas em ML
     */
    public function getPredictions(string $type, array $parameters = []): array
    {
        $endpoint = "/analytics/predictions/{$type}";
        return $this->makeHttpRequest('GET', $endpoint, $parameters);
    }

    /**
     * Obtém dashboard executivo
     */
    public function getExecutiveDashboard(array $filters = []): array
    {
        $endpoint = '/analytics/dashboard/executive';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém comparação com período anterior
     */
    public function getPeriodicComparison(array $filters = []): array
    {
        $endpoint = '/analytics/comparison';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise de produtos (cross-sell/upsell)
     */
    public function getProductAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/products/analysis';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém relatório de receita
     */
    public function getRevenueReport(array $filters = []): array
    {
        $endpoint = '/analytics/revenue';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém métricas de pagamento
     */
    public function getPaymentMetrics(array $filters = []): array
    {
        $endpoint = '/analytics/payments/metrics';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise geográfica
     */
    public function getGeographicAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/geographic';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Obtém análise temporal (sazonalidade)
     */
    public function getTemporalAnalysis(array $filters = []): array
    {
        $endpoint = '/analytics/temporal';
        return $this->makeHttpRequest('GET', $endpoint, $filters);
    }

    /**
     * Cria um relatório customizado
     */
    public function createCustomReport(array $reportConfig): array
    {
        $endpoint = '/analytics/reports/custom';
        return $this->makeHttpRequest('POST', $endpoint, $reportConfig);
    }

    /**
     * Obtém relatório customizado
     */
    public function getCustomReport(string $reportId): array
    {
        $endpoint = "/analytics/reports/custom/{$reportId}";
        return $this->makeHttpRequest('GET', $endpoint);
    }

    /**
     * Exporta dados para análise externa
     */
    public function exportData(array $exportConfig): array
    {
        $endpoint = '/analytics/export';
        return $this->makeHttpRequest('POST', $endpoint, $exportConfig);
    }

    /**
     * Obtém status de uma exportação
     */
    public function getExportStatus(string $exportId): array
    {
        $endpoint = "/analytics/export/{$exportId}/status";
        return $this->makeHttpRequest('GET', $endpoint);
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->httpClient !== null && $this->config !== null;
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
