<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de estratégias de preços
 *
 * Responsável pela gestão de preços dinâmicos e estratégias:
 * - Pricing dinâmico baseado em regras
 * - Descontos e promoções automáticas
 * - Preços por localização e segmento
 * - A/B testing de preços
 * - Análise de elasticidade de preços
 * - Pricing inteligente com ML
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas estratégias de preço
 * - O: Open/Closed - Extensível via estratégias
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de pricing
 * - D: Dependency Inversion - Depende de abstrações
 */
class PricingService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'pricing';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria uma nova estratégia de pricing
     */
    public function createStrategy(string $productId, array $strategyData): array
    {
        return $this->executeWithMetrics('create_pricing_strategy', function () use ($productId, $strategyData) {
            $this->validateStrategyData($strategyData);

            // Preparar dados da estratégia
            $data = array_merge($strategyData, [
                'product_id' => $productId,
                'status' => $strategyData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildStrategyConfiguration($strategyData),
                'analytics' => $this->initializeStrategyAnalytics()
            ]);

            // Criar estratégia via API
            $response = $this->makeHttpRequest('POST', '/pricing-strategies', $data);
            $strategy = ResponseHelper::getData($response);

            // Cache da estratégia
            $this->cache->set($this->getCacheKey("pricing_strategy:{$strategy['id']}"), $strategy, 3600);

            // Dispatch evento
            $this->dispatch('pricing_strategy.created', [
                'strategy_id' => $strategy['id'],
                'product_id' => $productId,
                'type' => $strategy['type'],
                'base_price' => $strategy['base_price']
            ]);

            $this->logger->info('Pricing strategy created successfully', [
                'strategy_id' => $strategy['id'],
                'product_id' => $productId,
                'type' => $strategy['type']
            ]);

            return $strategy;
        });
    }

    /**
     * Obtém uma estratégia por ID
     */
    public function getStrategy(string $strategyId): ?array
    {
        return $this->getCachedOrExecute(
            "pricing_strategy:{$strategyId}",
            fn () => $this->fetchStrategyById($strategyId),
            3600
        );
    }

    /**
     * Obtém estratégias por produto
     */
    public function getStrategiesByProduct(string $productId): array
    {
        return $this->executeWithMetrics('get_strategies_by_product', function () use ($productId) {
            $response = $this->makeHttpRequest('GET', '/pricing-strategies', [
                'query' => ['product_id' => $productId]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Calcula preço dinâmico
     */
    public function calculateDynamicPrice(string $productId, array $context = []): array
    {
        return $this->executeWithMetrics('calculate_dynamic_price', function () use ($productId, $context) {
            $response = $this->makeHttpRequest('POST', '/pricing/calculate', [
                'product_id' => $productId,
                'context' => $context
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento para tracking
            $this->dispatch('pricing.calculated', [
                'product_id' => $productId,
                'original_price' => $result['original_price'] ?? 0,
                'calculated_price' => $result['final_price'] ?? 0,
                'discount_applied' => $result['discount_applied'] ?? 0,
                'strategy_used' => $result['strategy_id'] ?? null
            ]);

            return $result;
        });
    }

    /**
     * Atualiza estratégia
     */
    public function updateStrategy(string $strategyId, array $data): array
    {
        return $this->executeWithMetrics('update_pricing_strategy', function () use ($strategyId, $data) {
            $this->validateStrategyUpdateData($data);

            // Verificar se estratégia existe
            $currentStrategy = $this->getStrategy($strategyId);
            if (!$currentStrategy) {
                throw new ValidationException("Pricing strategy not found: {$strategyId}");
            }

            // CORREÇÃO: Não adicionar updated_at - a API gerencia timestamps automaticamente
            // $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}", $data);
            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.updated', [
                'strategy_id' => $strategyId,
                'updated_fields' => array_keys($data)
            ]);

            return $strategy;
        });
    }

    /**
     * Configura regras de desconto
     */
    public function configureDiscountRules(string $strategyId, array $rules): array
    {
        return $this->executeWithMetrics('configure_discount_rules', function () use ($strategyId, $rules) {
            $this->validateDiscountRules($rules);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/discount-rules", [
                'rules' => $rules
            ]);

            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.discount_rules_updated', [
                'strategy_id' => $strategyId,
                'rules_count' => count($rules)
            ]);

            return $strategy;
        });
    }

    /**
     * Configura pricing por localização
     */
    public function configureLocationPricing(string $strategyId, array $locationRules): array
    {
        return $this->executeWithMetrics('configure_location_pricing', function () use ($strategyId, $locationRules) {
            $this->validateLocationRules($locationRules);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/location-pricing", [
                'location_rules' => $locationRules
            ]);

            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.location_pricing_updated', [
                'strategy_id' => $strategyId,
                'locations_count' => count($locationRules)
            ]);

            return $strategy;
        });
    }

    /**
     * Configura pricing por segmento
     */
    public function configureSegmentPricing(string $strategyId, array $segmentRules): array
    {
        return $this->executeWithMetrics('configure_segment_pricing', function () use ($strategyId, $segmentRules) {
            $this->validateSegmentRules($segmentRules);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/segment-pricing", [
                'segment_rules' => $segmentRules
            ]);

            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.segment_pricing_updated', [
                'strategy_id' => $strategyId,
                'segments_count' => count($segmentRules)
            ]);

            return $strategy;
        });
    }

    /**
     * Configura A/B testing de preços
     */
    public function configureAbTesting(string $strategyId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_pricing_ab_testing', function () use ($strategyId, $testConfig) {
            $this->validateAbTestConfig($testConfig);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/ab-testing", $testConfig);
            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.ab_testing_configured', [
                'strategy_id' => $strategyId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $strategy;
        });
    }

    /**
     * Obtém resultados de A/B testing
     */
    public function getAbTestResults(string $strategyId): array
    {
        return $this->executeWithMetrics('get_pricing_ab_test_results', function () use ($strategyId) {
            $response = $this->makeHttpRequest('GET', "/pricing-strategies/{$strategyId}/ab-testing/results");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Configura pricing inteligente (ML-based)
     */
    public function configureSmartPricing(string $strategyId, array $mlConfig): array
    {
        return $this->executeWithMetrics('configure_smart_pricing', function () use ($strategyId, $mlConfig) {
            $this->validateSmartPricingConfig($mlConfig);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/smart-pricing", $mlConfig);
            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.smart_pricing_configured', [
                'strategy_id' => $strategyId,
                'model_type' => $mlConfig['model_type'] ?? 'default'
            ]);

            return $strategy;
        });
    }

    /**
     * Analisa elasticidade de preços
     */
    public function analyzeElasticity(string $productId, array $priceRange = []): array
    {
        return $this->executeWithMetrics('analyze_price_elasticity', function () use ($productId, $priceRange) {
            $response = $this->makeHttpRequest('POST', '/pricing/elasticity-analysis', [
                'product_id' => $productId,
                'price_range' => $priceRange
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém sugestões de preço otimizado
     */
    public function getOptimizedPriceSuggestions(string $productId, array $criteria = []): array
    {
        return $this->executeWithMetrics('get_optimized_price_suggestions', function () use ($productId, $criteria) {
            $response = $this->makeHttpRequest('POST', '/pricing/optimize', [
                'product_id' => $productId,
                'criteria' => $criteria
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Configura promoções automáticas
     */
    public function configureAutomaticPromotions(string $strategyId, array $promotionRules): array
    {
        return $this->executeWithMetrics('configure_automatic_promotions', function () use ($strategyId, $promotionRules) {
            $this->validatePromotionRules($promotionRules);

            $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/automatic-promotions", [
                'promotion_rules' => $promotionRules
            ]);

            $strategy = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateStrategyCache($strategyId);

            // Dispatch evento
            $this->dispatch('pricing_strategy.automatic_promotions_configured', [
                'strategy_id' => $strategyId,
                'promotions_count' => count($promotionRules)
            ]);

            return $strategy;
        });
    }

    /**
     * Ativa estratégia
     */
    public function activateStrategy(string $strategyId): bool
    {
        return $this->updateStrategyStatus($strategyId, 'active');
    }

    /**
     * Desativa estratégia
     */
    public function deactivateStrategy(string $strategyId): bool
    {
        return $this->updateStrategyStatus($strategyId, 'inactive');
    }

    /**
     * Pausa estratégia
     */
    public function pauseStrategy(string $strategyId): bool
    {
        return $this->updateStrategyStatus($strategyId, 'paused');
    }

    /**
     * Duplica estratégia
     */
    public function duplicateStrategy(string $strategyId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_pricing_strategy', function () use ($strategyId, $overrideData) {
            $response = $this->makeHttpRequest('POST', "/pricing-strategies/{$strategyId}/duplicate", $overrideData);
            $strategy = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('pricing_strategy.duplicated', [
                'original_id' => $strategyId,
                'new_id' => $strategy['id']
            ]);

            return $strategy;
        });
    }

    /**
     * Obtém analytics da estratégia
     */
    public function getStrategyAnalytics(string $strategyId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_strategy_analytics', function () use ($strategyId, $filters) {
            $response = $this->makeHttpRequest('GET', "/pricing-strategies/{$strategyId}/analytics", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém histórico de preços
     */
    public function getPriceHistory(string $productId, array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_price_history', function () use ($productId, $dateRange) {
            $response = $this->makeHttpRequest('GET', "/products/{$productId}/price-history", [
                'query' => $dateRange
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém relatório de performance de pricing
     */
    public function getPerformanceReport(string $strategyId, array $metrics = []): array
    {
        return $this->executeWithMetrics('get_pricing_performance_report', function () use ($strategyId, $metrics) {
            $response = $this->makeHttpRequest('GET', "/pricing-strategies/{$strategyId}/performance", [
                'query' => ['metrics' => $metrics]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista estratégias com filtros
     */
    public function listStrategies(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_pricing_strategies', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->makeHttpRequest('GET', '/pricing-strategies', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Remove estratégia
     */
    public function deleteStrategy(string $strategyId): bool
    {
        return $this->executeWithMetrics('delete_pricing_strategy', function () use ($strategyId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "/pricing-strategies/{$strategyId}");

                // Invalidar cache
                $this->invalidateStrategyCache($strategyId);

                // Dispatch evento
                $this->dispatch('pricing_strategy.deleted', [
                    'strategy_id' => $strategyId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete pricing strategy', [
                    'strategy_id' => $strategyId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta estratégias ativas
     */
    public function countStrategies(): int
    {
        try {
            $response = $this->makeHttpRequest('GET', '/pricing-strategies/count');
            $data = ResponseHelper::getData($response);
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count pricing strategies', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Busca estratégia por ID via API
     */
    private function fetchStrategyById(string $strategyId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/pricing-strategies/{$strategyId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status da estratégia
     */
    private function updateStrategyStatus(string $strategyId, string $status): bool
    {
        return $this->executeWithMetrics("update_strategy_status_{$status}", function () use ($strategyId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "/pricing-strategies/{$strategyId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateStrategyCache($strategyId);

                // Dispatch evento
                $this->dispatch('pricing_strategy.status_changed', [
                    'strategy_id' => $strategyId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update strategy status to {$status}", [
                    'strategy_id' => $strategyId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache da estratégia
     */
    private function invalidateStrategyCache(string $strategyId): void
    {
        $this->cache->delete($this->getCacheKey("pricing_strategy:{$strategyId}"));
    }

    /**
     * Valida dados da estratégia
     */
    private function validateStrategyData(array $data): void
    {
        $required = ['type', 'base_price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for pricing strategy creation");
            }
        }

        $allowedTypes = ['fixed', 'dynamic', 'tiered', 'segment_based', 'location_based', 'smart_pricing'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid pricing strategy type: {$data['type']}");
        }

        if (!is_numeric($data['base_price']) || $data['base_price'] < 0) {
            throw new ValidationException('Base price must be a positive number');
        }
    }

    /**
     * Valida dados de atualização da estratégia
     */
    private function validateStrategyUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['fixed', 'dynamic', 'tiered', 'segment_based', 'location_based', 'smart_pricing'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid pricing strategy type: {$data['type']}");
            }
        }

        if (isset($data['base_price']) && (!is_numeric($data['base_price']) || $data['base_price'] < 0)) {
            throw new ValidationException('Base price must be a positive number');
        }
    }

    /**
     * Valida regras de desconto
     */
    private function validateDiscountRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['condition']) || !isset($rule['discount_type'])) {
                throw new ValidationException('Invalid discount rule format');
            }

            $allowedConditions = ['quantity', 'total_amount', 'customer_type', 'date_range', 'first_purchase'];
            if (!in_array($rule['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid discount condition: {$rule['condition']}");
            }

            $allowedTypes = ['percentage', 'fixed_amount', 'buy_x_get_y'];
            if (!in_array($rule['discount_type'], $allowedTypes)) {
                throw new ValidationException("Invalid discount type: {$rule['discount_type']}");
            }
        }
    }

    /**
     * Valida regras de localização
     */
    private function validateLocationRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['location']) || !isset($rule['price_modifier'])) {
                throw new ValidationException('Invalid location rule format');
            }

            if (!is_numeric($rule['price_modifier'])) {
                throw new ValidationException('Price modifier must be numeric');
            }
        }
    }

    /**
     * Valida regras de segmento
     */
    private function validateSegmentRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['segment']) || !isset($rule['price_modifier'])) {
                throw new ValidationException('Invalid segment rule format');
            }

            if (!is_numeric($rule['price_modifier'])) {
                throw new ValidationException('Price modifier must be numeric');
            }
        }
    }

    /**
     * Valida configuração de A/B testing
     */
    private function validateAbTestConfig(array $config): void
    {
        $required = ['name', 'variants'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new ValidationException("Field '{$field}' is required for A/B test configuration");
            }
        }

        if (!is_array($config['variants']) || count($config['variants']) < 2) {
            throw new ValidationException('A/B test must have at least 2 variants');
        }
    }

    /**
     * Valida configuração de smart pricing
     */
    private function validateSmartPricingConfig(array $config): void
    {
        $allowedModels = ['linear_regression', 'random_forest', 'neural_network', 'decision_tree'];
        if (isset($config['model_type']) && !in_array($config['model_type'], $allowedModels)) {
            throw new ValidationException("Invalid ML model type: {$config['model_type']}");
        }
    }

    /**
     * Valida regras de promoção
     */
    private function validatePromotionRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['trigger']) || !isset($rule['action'])) {
                throw new ValidationException('Invalid promotion rule format');
            }

            $allowedTriggers = ['low_conversion', 'high_inventory', 'competitor_price', 'seasonal'];
            if (!in_array($rule['trigger'], $allowedTriggers)) {
                throw new ValidationException("Invalid promotion trigger: {$rule['trigger']}");
            }

            $allowedActions = ['discount_percentage', 'discount_fixed', 'bundle_offer', 'free_shipping'];
            if (!in_array($rule['action'], $allowedActions)) {
                throw new ValidationException("Invalid promotion action: {$rule['action']}");
            }
        }
    }

    /**
     * Constrói configuração da estratégia
     */
    private function buildStrategyConfiguration(array $data): array
    {
        return [
            'calculation_method' => $data['calculation_method'] ?? 'standard',
            'update_frequency' => $data['update_frequency'] ?? 'real_time',
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'profit_margin_target' => $data['profit_margin_target'] ?? null,
            'competitor_tracking' => $data['competitor_tracking'] ?? false,
            'seasonal_adjustments' => $data['seasonal_adjustments'] ?? false
        ];
    }

    /**
     * Inicializa analytics da estratégia
     */
    private function initializeStrategyAnalytics(): array
    {
        return [
            'price_changes' => 0,
            'revenue_impact' => 0,
            'conversion_rate_change' => 0,
            'profit_margin_change' => 0,
            'customer_satisfaction_score' => 0,
            'a_b_tests_run' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
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
