<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de order bumps
 *
 * Responsável pela gestão de order bumps para aumentar AOV:
 * - Criação e configuração de order bumps
 * - Posicionamento estratégico no checkout
 * - Configuração de descontos e promoções
 * - A/B testing de order bumps
 * - Análise de performance e conversão
 * - Templates e personalizações visuais
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas order bumps
 * - O: Open/Closed - Extensível via tipos de bump
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de order bump
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderBumpService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'order_bump';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo order bump
     */
    public function create(array $bumpData): array
    {
        return $this->executeWithMetrics('create_order_bump', function () use ($bumpData) {
            $this->validateOrderBumpData($bumpData);

            // Preparar dados do order bump
            $data = array_merge($bumpData, [
                'status' => $bumpData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildBumpConfiguration($bumpData),
                'analytics' => $this->initializeBumpAnalytics()
            ]);

            // Criar order bump via API
            $response = $this->httpClient->post('/order-bumps', $data);
            $bump = ResponseHelper::getData($response);

            // Cache do order bump
            $this->cache->set($this->getCacheKey("order_bump:{$bump['id']}"), $bump, 3600);

            // Dispatch evento
            $this->dispatch('order_bump.created', [
                'bump_id' => $bump['id'],
                'offer_id' => $bump['offer_id'] ?? null,
                'product_id' => $bump['product_id'],
                'position' => $bump['position'],
                'discount_percentage' => $bump['discount_percentage'] ?? 0
            ]);

            $this->logger->info('Order bump created successfully', [
                'bump_id' => $bump['id'],
                'product_id' => $bump['product_id'],
                'position' => $bump['position']
            ]);

            return $bump;
        });
    }

    /**
     * Obtém um order bump por ID
     */
    public function get(string $bumpId): ?array
    {
        return $this->getCachedOrExecute(
            "order_bump:{$bumpId}",
            fn () => $this->fetchOrderBumpById($bumpId),
            3600
        );
    }

    /**
     * Obtém order bumps por oferta
     */
    public function getByOffer(string $offerId): array
    {
        return $this->executeWithMetrics('get_order_bumps_by_offer', function () use ($offerId) {
            $response = $this->httpClient->get('/order-bumps', [
                'query' => ['offer_id' => $offerId]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém order bumps por posição
     */
    public function getByPosition(string $position): array
    {
        return $this->executeWithMetrics('get_order_bumps_by_position', function () use ($position) {
            $response = $this->httpClient->get('/order-bumps', [
                'query' => ['position' => $position]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Atualiza order bump
     */
    public function update(string $bumpId, array $data): array
    {
        return $this->executeWithMetrics('update_order_bump', function () use ($bumpId, $data) {
            $this->validateOrderBumpUpdateData($data);

            // Verificar se order bump existe
            $currentBump = $this->get($bumpId);
            if (!$currentBump) {
                throw new ValidationException("Order bump not found: {$bumpId}");
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/order-bumps/{$bumpId}", $data);
            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.updated', [
                'bump_id' => $bumpId,
                'updated_fields' => array_keys($data)
            ]);

            return $bump;
        });
    }

    /**
     * Configura posicionamento do order bump
     */
    public function updatePosition(string $bumpId, string $position, array $config = []): array
    {
        return $this->executeWithMetrics('update_order_bump_position', function () use ($bumpId, $position, $config) {
            $this->validatePosition($position);

            $data = array_merge($config, [
                'position' => $position,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/position", $data);
            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.position_updated', [
                'bump_id' => $bumpId,
                'new_position' => $position,
                'config' => $config
            ]);

            return $bump;
        });
    }

    /**
     * Configura desconto do order bump
     */
    public function updateDiscount(string $bumpId, array $discountConfig): array
    {
        return $this->executeWithMetrics('update_order_bump_discount', function () use ($bumpId, $discountConfig) {
            $this->validateDiscountConfig($discountConfig);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/discount", $discountConfig);
            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.discount_updated', [
                'bump_id' => $bumpId,
                'discount_type' => $discountConfig['type'] ?? 'percentage',
                'discount_value' => $discountConfig['value'] ?? 0
            ]);

            return $bump;
        });
    }

    /**
     * Configura aparência visual do order bump
     */
    public function updateAppearance(string $bumpId, array $appearanceConfig): array
    {
        return $this->executeWithMetrics('update_order_bump_appearance', function () use ($bumpId, $appearanceConfig) {
            $this->validateAppearanceConfig($appearanceConfig);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/appearance", $appearanceConfig);
            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.appearance_updated', [
                'bump_id' => $bumpId,
                'theme' => $appearanceConfig['theme'] ?? 'default',
                'style' => $appearanceConfig['style'] ?? 'card'
            ]);

            return $bump;
        });
    }

    /**
     * Configura targeting do order bump
     */
    public function updateTargeting(string $bumpId, array $targetingRules): array
    {
        return $this->executeWithMetrics('update_order_bump_targeting', function () use ($bumpId, $targetingRules) {
            $this->validateTargetingRules($targetingRules);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/targeting", [
                'targeting_rules' => $targetingRules
            ]);

            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.targeting_updated', [
                'bump_id' => $bumpId,
                'rules_count' => count($targetingRules)
            ]);

            return $bump;
        });
    }

    /**
     * Configura A/B testing para order bump
     */
    public function configureAbTesting(string $bumpId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_order_bump_ab_testing', function () use ($bumpId, $testConfig) {
            $this->validateAbTestConfig($testConfig);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/ab-testing", $testConfig);
            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.ab_testing_configured', [
                'bump_id' => $bumpId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $bump;
        });
    }

    /**
     * Obtém resultados de A/B testing
     */
    public function getAbTestResults(string $bumpId): array
    {
        return $this->executeWithMetrics('get_order_bump_ab_test_results', function () use ($bumpId) {
            $response = $this->httpClient->get("/order-bumps/{$bumpId}/ab-testing/results");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Ativa order bump
     */
    public function activate(string $bumpId): bool
    {
        return $this->updateStatus($bumpId, 'active');
    }

    /**
     * Desativa order bump
     */
    public function deactivate(string $bumpId): bool
    {
        return $this->updateStatus($bumpId, 'inactive');
    }

    /**
     * Pausa order bump
     */
    public function pause(string $bumpId): bool
    {
        return $this->updateStatus($bumpId, 'paused');
    }

    /**
     * Duplica order bump
     */
    public function duplicate(string $bumpId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_order_bump', function () use ($bumpId, $overrideData) {
            $response = $this->httpClient->post("/order-bumps/{$bumpId}/duplicate", $overrideData);
            $bump = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('order_bump.duplicated', [
                'original_id' => $bumpId,
                'new_id' => $bump['id']
            ]);

            return $bump;
        });
    }

    /**
     * Obtém analytics do order bump
     */
    public function getAnalytics(string $bumpId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_order_bump_analytics', function () use ($bumpId, $filters) {
            $response = $this->httpClient->get("/order-bumps/{$bumpId}/analytics", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém relatório de conversão
     */
    public function getConversionReport(string $bumpId, array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_order_bump_conversion_report', function () use ($bumpId, $dateRange) {
            $response = $this->httpClient->get("/order-bumps/{$bumpId}/conversion-report", [
                'query' => $dateRange
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém order bumps com melhor performance
     */
    public function getTopPerforming(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_performing_order_bumps', function () use ($limit) {
            $response = $this->httpClient->get('/order-bumps/top-performing', [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista order bumps com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_order_bumps', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/order-bumps', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Remove order bump
     */
    public function delete(string $bumpId): bool
    {
        return $this->executeWithMetrics('delete_order_bump', function () use ($bumpId) {
            try {
                $response = $this->httpClient->delete("/order-bumps/{$bumpId}");

                // Invalidar cache
                $this->invalidateOrderBumpCache($bumpId);

                // Dispatch evento
                $this->dispatch('order_bump.deleted', [
                    'bump_id' => $bumpId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete order bump', [
                    'bump_id' => $bumpId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta order bumps ativos
     */
    public function countActive(): int
    {
        try {
            $response = $this->httpClient->get('/order-bumps/count', [
                'query' => ['status' => 'active']
            ]);
            $data = ResponseHelper::getData($response);
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count active order bumps', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Otimiza automaticamente para melhor conversão
     */
    public function autoOptimize(string $bumpId, array $optimizationCriteria = []): array
    {
        return $this->executeWithMetrics('auto_optimize_order_bump', function () use ($bumpId, $optimizationCriteria) {
            $response = $this->httpClient->post("/order-bumps/{$bumpId}/auto-optimize", [
                'optimization_criteria' => $optimizationCriteria
            ]);

            $result = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.auto_optimized', [
                'bump_id' => $bumpId,
                'optimizations_applied' => count($result['optimizations'] ?? [])
            ]);

            return $result;
        });
    }

    /**
     * Configura gatilhos condicionais
     */
    public function configureConditionalTriggers(string $bumpId, array $triggers): array
    {
        return $this->executeWithMetrics('configure_order_bump_triggers', function () use ($bumpId, $triggers) {
            $this->validateConditionalTriggers($triggers);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/conditional-triggers", [
                'triggers' => $triggers
            ]);

            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.conditional_triggers_configured', [
                'bump_id' => $bumpId,
                'triggers_count' => count($triggers)
            ]);

            return $bump;
        });
    }

    /**
     * Configura limitações de exibição
     */
    public function configureDisplayLimits(string $bumpId, array $limits): array
    {
        return $this->executeWithMetrics('configure_order_bump_display_limits', function () use ($bumpId, $limits) {
            $this->validateDisplayLimits($limits);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/display-limits", [
                'limits' => $limits
            ]);

            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.display_limits_configured', [
                'bump_id' => $bumpId,
                'limits' => array_keys($limits)
            ]);

            return $bump;
        });
    }

    /**
     * Configura animações de entrada/saída
     */
    public function configureAnimations(string $bumpId, array $animationConfig): array
    {
        return $this->executeWithMetrics('configure_order_bump_animations', function () use ($bumpId, $animationConfig) {
            $this->validateAnimationConfig($animationConfig);

            $response = $this->httpClient->put("/order-bumps/{$bumpId}/animations", [
                'animation_config' => $animationConfig
            ]);

            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.animations_configured', [
                'bump_id' => $bumpId,
                'animations' => array_keys($animationConfig)
            ]);

            return $bump;
        });
    }

    /**
     * Agenda order bump para ativação futura
     */
    public function scheduleActivation(string $bumpId, string $scheduledDate, array $options = []): array
    {
        return $this->executeWithMetrics('schedule_order_bump_activation', function () use ($bumpId, $scheduledDate, $options) {
            if (!strtotime($scheduledDate)) {
                throw new ValidationException('Invalid scheduled date format');
            }

            $response = $this->httpClient->post("/order-bumps/{$bumpId}/schedule-activation", [
                'scheduled_date' => $scheduledDate,
                'options' => $options
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('order_bump.activation_scheduled', [
                'bump_id' => $bumpId,
                'scheduled_date' => $scheduledDate,
                'options' => $options
            ]);

            return $result;
        });
    }

    /**
     * Obtém sugestões de produtos para order bump
     */
    public function getProductSuggestions(string $offerId, array $criteria = []): array
    {
        return $this->executeWithMetrics('get_order_bump_product_suggestions', function () use ($offerId, $criteria) {
            $response = $this->httpClient->get("/offers/{$offerId}/order-bump-suggestions", [
                'query' => $criteria
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Executa teste de posicionamento
     */
    public function runPositionTest(string $bumpId, array $positions): array
    {
        return $this->executeWithMetrics('run_order_bump_position_test', function () use ($bumpId, $positions) {
            $this->validatePositionTest($positions);

            $response = $this->httpClient->post("/order-bumps/{$bumpId}/position-test", [
                'positions' => $positions
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('order_bump.position_test_executed', [
                'bump_id' => $bumpId,
                'positions_tested' => count($positions)
            ]);

            return $result;
        });
    }

    /**
     * Exporta configuração do order bump
     */
    public function exportConfiguration(string $bumpId): array
    {
        return $this->executeWithMetrics('export_order_bump_configuration', function () use ($bumpId) {
            $response = $this->httpClient->get("/order-bumps/{$bumpId}/export-configuration");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Importa configuração para um order bump
     */
    public function importConfiguration(string $bumpId, array $configuration): array
    {
        return $this->executeWithMetrics('import_order_bump_configuration', function () use ($bumpId, $configuration) {
            $this->validateImportConfiguration($configuration);

            $response = $this->httpClient->post("/order-bumps/{$bumpId}/import-configuration", [
                'configuration' => $configuration
            ]);

            $bump = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateOrderBumpCache($bumpId);

            // Dispatch evento
            $this->dispatch('order_bump.configuration_imported', [
                'bump_id' => $bumpId,
                'configuration_keys' => array_keys($configuration)
            ]);

            return $bump;
        });
    }

    /**
     * Busca order bump por ID via API
     */
    private function fetchOrderBumpById(string $bumpId): ?array
    {
        try {
            $response = $this->httpClient->get("/order-bumps/{$bumpId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza status do order bump
     */
    private function updateStatus(string $bumpId, string $status): bool
    {
        return $this->executeWithMetrics("update_order_bump_status_{$status}", function () use ($bumpId, $status) {
            try {
                $response = $this->httpClient->put("/order-bumps/{$bumpId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateOrderBumpCache($bumpId);

                // Dispatch evento
                $this->dispatch('order_bump.status_changed', [
                    'bump_id' => $bumpId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update order bump status to {$status}", [
                    'bump_id' => $bumpId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do order bump
     */
    private function invalidateOrderBumpCache(string $bumpId): void
    {
        $this->cache->delete($this->getCacheKey("order_bump:{$bumpId}"));
    }

    /**
     * Valida dados do order bump
     */
    private function validateOrderBumpData(array $data): void
    {
        $required = ['product_id', 'position'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for order bump creation");
            }
        }

        $this->validatePosition($data['position']);

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida dados de atualização do order bump
     */
    private function validateOrderBumpUpdateData(array $data): void
    {
        if (isset($data['position'])) {
            $this->validatePosition($data['position']);
        }

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida posição do order bump
     */
    private function validatePosition(string $position): void
    {
        $allowedPositions = ['after_products', 'before_payment', 'in_payment_form', 'after_customer_info'];
        if (!in_array($position, $allowedPositions)) {
            throw new ValidationException("Invalid order bump position: {$position}");
        }
    }

    /**
     * Valida configuração de desconto
     */
    private function validateDiscountConfig(array $config): void
    {
        $allowedTypes = ['percentage', 'fixed_amount'];
        if (isset($config['type']) && !in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid discount type: {$config['type']}");
        }

        if (isset($config['value']) && (!is_numeric($config['value']) || $config['value'] < 0)) {
            throw new ValidationException('Discount value must be a positive number');
        }
    }

    /**
     * Valida configuração de aparência
     */
    private function validateAppearanceConfig(array $config): void
    {
        $allowedThemes = ['default', 'minimal', 'bold', 'elegant'];
        if (isset($config['theme']) && !in_array($config['theme'], $allowedThemes)) {
            throw new ValidationException("Invalid appearance theme: {$config['theme']}");
        }

        $allowedStyles = ['card', 'inline', 'popup', 'sidebar'];
        if (isset($config['style']) && !in_array($config['style'], $allowedStyles)) {
            throw new ValidationException("Invalid appearance style: {$config['style']}");
        }
    }

    /**
     * Valida regras de targeting
     */
    private function validateTargetingRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['condition']) || !isset($rule['value'])) {
                throw new ValidationException('Invalid targeting rule format');
            }

            $allowedConditions = ['cart_value_min', 'cart_value_max', 'product_in_cart', 'customer_type', 'device_type'];
            if (!in_array($rule['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid targeting condition: {$rule['condition']}");
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
     * Constrói configuração do order bump
     */
    private function buildBumpConfiguration(array $data): array
    {
        return [
            'display_rules' => $data['display_rules'] ?? [],
            'animation_settings' => $data['animation_settings'] ?? ['enabled' => true, 'type' => 'fade'],
            'interaction_tracking' => $data['interaction_tracking'] ?? true,
            'conversion_tracking' => $data['conversion_tracking'] ?? true,
            'mobile_optimized' => $data['mobile_optimized'] ?? true
        ];
    }

    /**
     * Inicializa analytics do order bump
     */
    private function initializeBumpAnalytics(): array
    {
        return [
            'impressions' => 0,
            'interactions' => 0,
            'conversions' => 0,
            'revenue_generated' => 0,
            'conversion_rate' => 0,
            'average_order_value_increase' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Valida gatilhos condicionais
     */
    private function validateConditionalTriggers(array $triggers): void
    {
        foreach ($triggers as $trigger) {
            if (!is_array($trigger) || !isset($trigger['condition']) || !isset($trigger['action'])) {
                throw new ValidationException('Invalid conditional trigger format');
            }

            $allowedConditions = [
                'cart_value', 'product_category', 'user_segment', 'time_on_page',
                'previous_purchases', 'device_type', 'geographic_location'
            ];

            if (!in_array($trigger['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid trigger condition: {$trigger['condition']}");
            }

            $allowedActions = ['show', 'hide', 'modify_discount', 'change_position'];
            if (!in_array($trigger['action'], $allowedActions)) {
                throw new ValidationException("Invalid trigger action: {$trigger['action']}");
            }
        }
    }

    /**
     * Valida limitações de exibição
     */
    private function validateDisplayLimits(array $limits): void
    {
        $allowedLimits = [
            'max_displays_per_session', 'max_displays_per_user', 'time_between_displays',
            'max_daily_displays', 'session_timeout'
        ];

        foreach ($limits as $limit => $value) {
            if (!in_array($limit, $allowedLimits)) {
                throw new ValidationException("Invalid display limit: {$limit}");
            }

            if (!is_numeric($value) || $value < 0) {
                throw new ValidationException("Display limit '{$limit}' must be a non-negative number");
            }
        }
    }

    /**
     * Valida configuração de animações
     */
    private function validateAnimationConfig(array $config): void
    {
        $allowedAnimations = ['fadeIn', 'slideIn', 'bounceIn', 'zoomIn', 'none'];

        foreach ($config as $animationType => $settings) {
            if (!in_array($animationType, $allowedAnimations)) {
                throw new ValidationException("Invalid animation type: {$animationType}");
            }

            if (is_array($settings)) {
                if (isset($settings['duration']) && (!is_numeric($settings['duration']) || $settings['duration'] < 0)) {
                    throw new ValidationException("Animation duration must be a non-negative number for: {$animationType}");
                }
            }
        }
    }

    /**
     * Valida teste de posicionamento
     */
    private function validatePositionTest(array $positions): void
    {
        if (count($positions) < 2) {
            throw new ValidationException('Position test must include at least 2 positions');
        }

        $allowedPositions = ['after_products', 'before_payment', 'in_payment_form', 'after_customer_info'];

        foreach ($positions as $position) {
            if (!in_array($position, $allowedPositions)) {
                throw new ValidationException("Invalid position for test: {$position}");
            }
        }
    }

    /**
     * Valida configuração de importação
     */
    private function validateImportConfiguration(array $configuration): void
    {
        $allowedSections = [
            'position', 'discount', 'appearance', 'targeting',
            'ab_testing', 'conditional_triggers', 'display_limits', 'animations'
        ];

        foreach ($configuration as $section => $config) {
            if (!in_array($section, $allowedSections)) {
                throw new ValidationException("Invalid configuration section: {$section}");
            }

            if (!is_array($config)) {
                throw new ValidationException("Configuration section '{$section}' must be an array");
            }
        }
    }
}
