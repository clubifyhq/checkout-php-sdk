<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Exceptions\ValidationException;
use ClubifyCheckout\Exceptions\HttpException;

/**
 * Serviço de flows de vendas
 *
 * Responsável pela criação e gestão de flows de vendas:
 * - Criação de flows multi-step
 * - Configuração de navegação entre passos
 * - Integração com upsells e order bumps
 * - Personalização de experiência
 * - Analytics de conversão por step
 * - Otimização automática de flows
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas flows de vendas
 * - O: Open/Closed - Extensível via tipos de flow
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de flow
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowService extends BaseService
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'flow';
    }

    /**
     * Cria um novo flow de vendas
     */
    public function create(array $flowData): array
    {
        return $this->executeWithMetrics('create_sales_flow', function () use ($flowData) {
            $this->validateFlowData($flowData);

            // Gerar slug se não fornecido
            if (empty($flowData['slug'])) {
                $flowData['slug'] = $this->generateSlug($flowData['name']);
            }

            // Verificar unicidade do slug
            if ($this->slugExists($flowData['slug'])) {
                $flowData['slug'] = $this->generateUniqueSlug($flowData['slug']);
            }

            // Preparar dados do flow
            $data = array_merge($flowData, [
                'status' => $flowData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildFlowConfiguration($flowData),
                'analytics' => $this->initializeFlowAnalytics(),
                'steps' => $this->processFlowSteps($flowData['steps'] ?? [])
            ]);

            // Criar flow via API
            $response = $this->httpClient->post('/sales-flows', $data);
            $flow = $response->getData();

            // Cache do flow
            $this->cache->set($this->getCacheKey("sales_flow:{$flow['id']}"), $flow, 3600);
            $this->cache->set($this->getCacheKey("flow_slug:{$flow['slug']}"), $flow, 3600);

            // Dispatch evento
            $this->dispatch('sales_flow.created', [
                'flow_id' => $flow['id'],
                'name' => $flow['name'],
                'type' => $flow['type'],
                'steps_count' => count($flow['steps'] ?? [])
            ]);

            $this->logger->info('Sales flow created successfully', [
                'flow_id' => $flow['id'],
                'name' => $flow['name'],
                'slug' => $flow['slug']
            ]);

            return $flow;
        });
    }

    /**
     * Obtém um flow por ID
     */
    public function get(string $flowId): ?array
    {
        return $this->getCachedOrExecute(
            "sales_flow:{$flowId}",
            fn () => $this->fetchFlowById($flowId),
            3600
        );
    }

    /**
     * Obtém flow por slug
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->getCachedOrExecute(
            "flow_slug:{$slug}",
            fn () => $this->fetchFlowBySlug($slug),
            3600
        );
    }

    /**
     * Atualiza flow
     */
    public function update(string $flowId, array $data): array
    {
        return $this->executeWithMetrics('update_sales_flow', function () use ($flowId, $data) {
            $this->validateFlowUpdateData($data);

            // Verificar se flow existe
            $currentFlow = $this->get($flowId);
            if (!$currentFlow) {
                throw new ValidationException("Sales flow not found: {$flowId}");
            }

            // Verificar unicidade do slug se alterado
            if (isset($data['slug']) && $data['slug'] !== $currentFlow['slug']) {
                if ($this->slugExists($data['slug'])) {
                    throw new ValidationException("Slug '{$data['slug']}' already exists");
                }
            }

            // Processar steps se fornecidos
            if (isset($data['steps'])) {
                $data['steps'] = $this->processFlowSteps($data['steps']);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/sales-flows/{$flowId}", $data);
            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.updated', [
                'flow_id' => $flowId,
                'updated_fields' => array_keys($data)
            ]);

            return $flow;
        });
    }

    /**
     * Adiciona step ao flow
     */
    public function addStep(string $flowId, array $stepData): array
    {
        return $this->executeWithMetrics('add_flow_step', function () use ($flowId, $stepData) {
            $this->validateStepData($stepData);

            // Determinar ordem do step
            if (!isset($stepData['order'])) {
                $stepData['order'] = $this->getNextStepOrder($flowId);
            }

            $response = $this->httpClient->post("/sales-flows/{$flowId}/steps", $stepData);
            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.step_added', [
                'flow_id' => $flowId,
                'step_type' => $stepData['type'],
                'step_order' => $stepData['order']
            ]);

            return $flow;
        });
    }

    /**
     * Atualiza step do flow
     */
    public function updateStep(string $flowId, string $stepId, array $stepData): array
    {
        return $this->executeWithMetrics('update_flow_step', function () use ($flowId, $stepId, $stepData) {
            $this->validateStepUpdateData($stepData);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/steps/{$stepId}", $stepData);
            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.step_updated', [
                'flow_id' => $flowId,
                'step_id' => $stepId,
                'updated_fields' => array_keys($stepData)
            ]);

            return $flow;
        });
    }

    /**
     * Remove step do flow
     */
    public function removeStep(string $flowId, string $stepId): array
    {
        return $this->executeWithMetrics('remove_flow_step', function () use ($flowId, $stepId) {
            $response = $this->httpClient->delete("/sales-flows/{$flowId}/steps/{$stepId}");
            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.step_removed', [
                'flow_id' => $flowId,
                'step_id' => $stepId
            ]);

            return $flow;
        });
    }

    /**
     * Reordena steps do flow
     */
    public function reorderSteps(string $flowId, array $stepOrder): array
    {
        return $this->executeWithMetrics('reorder_flow_steps', function () use ($flowId, $stepOrder) {
            $response = $this->httpClient->put("/sales-flows/{$flowId}/steps/reorder", [
                'step_order' => $stepOrder
            ]);

            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.steps_reordered', [
                'flow_id' => $flowId,
                'steps_count' => count($stepOrder)
            ]);

            return $flow;
        });
    }

    /**
     * Configura navegação do flow
     */
    public function configureNavigation(string $flowId, array $navigationRules): array
    {
        return $this->executeWithMetrics('configure_flow_navigation', function () use ($flowId, $navigationRules) {
            $this->validateNavigationRules($navigationRules);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/navigation", [
                'navigation_rules' => $navigationRules
            ]);

            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.navigation_configured', [
                'flow_id' => $flowId,
                'rules_count' => count($navigationRules)
            ]);

            return $flow;
        });
    }

    /**
     * Configura A/B testing do flow
     */
    public function configureAbTesting(string $flowId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_flow_ab_testing', function () use ($flowId, $testConfig) {
            $this->validateAbTestConfig($testConfig);

            $response = $this->httpClient->put("/sales-flows/{$flowId}/ab-testing", $testConfig);
            $flow = $response->getData();

            // Invalidar cache
            $this->invalidateFlowCache($flowId);

            // Dispatch evento
            $this->dispatch('sales_flow.ab_testing_configured', [
                'flow_id' => $flowId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $flow;
        });
    }

    /**
     * Obtém resultados de A/B testing
     */
    public function getAbTestResults(string $flowId): array
    {
        return $this->executeWithMetrics('get_flow_ab_test_results', function () use ($flowId) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/ab-testing/results");
            return $response->getData() ?? [];
        });
    }

    /**
     * Otimiza flow automaticamente
     */
    public function optimizeFlow(string $flowId, array $optimizationCriteria = []): array
    {
        return $this->executeWithMetrics('optimize_sales_flow', function () use ($flowId, $optimizationCriteria) {
            $response = $this->httpClient->post("/sales-flows/{$flowId}/optimize", [
                'criteria' => $optimizationCriteria
            ]);

            $result = $response->getData();

            // Dispatch evento
            $this->dispatch('sales_flow.optimized', [
                'flow_id' => $flowId,
                'optimization_type' => $optimizationCriteria['type'] ?? 'auto',
                'improvements_count' => count($result['improvements'] ?? [])
            ]);

            return $result;
        });
    }

    /**
     * Ativa flow
     */
    public function activate(string $flowId): bool
    {
        return $this->updateStatus($flowId, 'active');
    }

    /**
     * Desativa flow
     */
    public function deactivate(string $flowId): bool
    {
        return $this->updateStatus($flowId, 'inactive');
    }

    /**
     * Pausa flow
     */
    public function pause(string $flowId): bool
    {
        return $this->updateStatus($flowId, 'paused');
    }

    /**
     * Duplica flow
     */
    public function duplicate(string $flowId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_sales_flow', function () use ($flowId, $overrideData) {
            $response = $this->httpClient->post("/sales-flows/{$flowId}/duplicate", $overrideData);
            $flow = $response->getData();

            // Dispatch evento
            $this->dispatch('sales_flow.duplicated', [
                'original_id' => $flowId,
                'new_id' => $flow['id']
            ]);

            return $flow;
        });
    }

    /**
     * Obtém analytics do flow
     */
    public function getAnalytics(string $flowId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_flow_analytics', function () use ($flowId, $filters) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/analytics", [
                'query' => $filters
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém analytics por step
     */
    public function getStepAnalytics(string $flowId, string $stepId): array
    {
        return $this->executeWithMetrics('get_step_analytics', function () use ($flowId, $stepId) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/steps/{$stepId}/analytics");
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém relatório de conversão do flow
     */
    public function getConversionReport(string $flowId, array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_flow_conversion_report', function () use ($flowId, $dateRange) {
            $response = $this->httpClient->get("/sales-flows/{$flowId}/conversion-report", [
                'query' => $dateRange
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém flows com melhor performance
     */
    public function getTopPerforming(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_performing_flows', function () use ($limit) {
            $response = $this->httpClient->get('/sales-flows/top-performing', [
                'query' => ['limit' => $limit]
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Lista flows com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_sales_flows', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/sales-flows', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Remove flow
     */
    public function delete(string $flowId): bool
    {
        return $this->executeWithMetrics('delete_sales_flow', function () use ($flowId) {
            try {
                $response = $this->httpClient->delete("/sales-flows/{$flowId}");

                // Invalidar cache
                $this->invalidateFlowCache($flowId);

                // Dispatch evento
                $this->dispatch('sales_flow.deleted', [
                    'flow_id' => $flowId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete sales flow', [
                    'flow_id' => $flowId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta total de flows
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->httpClient->get('/sales-flows/count', [
                'query' => $filters
            ]);
            $data = $response->getData();
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count sales flows', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Busca flow por ID via API
     */
    private function fetchFlowById(string $flowId): ?array
    {
        try {
            $response = $this->httpClient->get("/sales-flows/{$flowId}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca flow por slug via API
     */
    private function fetchFlowBySlug(string $slug): ?array
    {
        try {
            $response = $this->httpClient->get("/sales-flows/slug/{$slug}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Obtém próxima ordem de step
     */
    private function getNextStepOrder(string $flowId): int
    {
        try {
            $flow = $this->get($flowId);
            if (!$flow || empty($flow['steps'])) {
                return 1;
            }

            $maxOrder = 0;
            foreach ($flow['steps'] as $step) {
                if (isset($step['order']) && $step['order'] > $maxOrder) {
                    $maxOrder = $step['order'];
                }
            }

            return $maxOrder + 1;
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Atualiza status do flow
     */
    private function updateStatus(string $flowId, string $status): bool
    {
        return $this->executeWithMetrics("update_flow_status_{$status}", function () use ($flowId, $status) {
            try {
                $response = $this->httpClient->put("/sales-flows/{$flowId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateFlowCache($flowId);

                // Dispatch evento
                $this->dispatch('sales_flow.status_changed', [
                    'flow_id' => $flowId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update flow status to {$status}", [
                    'flow_id' => $flowId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do flow
     */
    private function invalidateFlowCache(string $flowId): void
    {
        $flow = $this->get($flowId);

        $this->cache->delete($this->getCacheKey("sales_flow:{$flowId}"));

        if ($flow && isset($flow['slug'])) {
            $this->cache->delete($this->getCacheKey("flow_slug:{$flow['slug']}"));
        }
    }

    /**
     * Valida dados do flow
     */
    private function validateFlowData(array $data): void
    {
        $required = ['name', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for flow creation");
            }
        }

        $allowedTypes = ['product_sales', 'lead_generation', 'subscription', 'event_registration', 'consultation'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid flow type: {$data['type']}");
        }
    }

    /**
     * Valida dados de atualização do flow
     */
    private function validateFlowUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['product_sales', 'lead_generation', 'subscription', 'event_registration', 'consultation'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid flow type: {$data['type']}");
            }
        }
    }

    /**
     * Valida dados do step
     */
    private function validateStepData(array $data): void
    {
        $required = ['type', 'name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for step creation");
            }
        }

        $allowedTypes = ['product_selection', 'customer_info', 'payment', 'upsell', 'order_bump', 'confirmation', 'thank_you'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid step type: {$data['type']}");
        }
    }

    /**
     * Valida dados de atualização do step
     */
    private function validateStepUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['product_selection', 'customer_info', 'payment', 'upsell', 'order_bump', 'confirmation', 'thank_you'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid step type: {$data['type']}");
            }
        }
    }

    /**
     * Valida regras de navegação
     */
    private function validateNavigationRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['from_step']) || !isset($rule['to_step'])) {
                throw new ValidationException('Invalid navigation rule format');
            }

            if (isset($rule['condition']) && !is_array($rule['condition'])) {
                throw new ValidationException('Navigation condition must be an array');
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
     * Verifica se slug já existe
     */
    private function slugExists(string $slug): bool
    {
        try {
            $flow = $this->fetchFlowBySlug($slug);
            return $flow !== null;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * Gera slug a partir do nome
     */
    private function generateSlug(string $name): string
    {
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
    }

    /**
     * Gera slug único
     */
    private function generateUniqueSlug(string $baseSlug): string
    {
        $counter = 1;
        $slug = $baseSlug;

        while ($this->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Processa steps do flow
     */
    private function processFlowSteps(array $steps): array
    {
        $processedSteps = [];

        foreach ($steps as $index => $step) {
            $this->validateStepData($step);

            // Garantir ordem sequencial se não especificada
            if (!isset($step['order'])) {
                $step['order'] = $index + 1;
            }

            // Adicionar configurações padrão
            $step['configuration'] = $step['configuration'] ?? [];
            $step['analytics'] = $this->initializeStepAnalytics();

            $processedSteps[] = $step;
        }

        // Ordenar por ordem
        usort($processedSteps, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $processedSteps;
    }

    /**
     * Constrói configuração do flow
     */
    private function buildFlowConfiguration(array $data): array
    {
        return [
            'allow_back_navigation' => $data['allow_back_navigation'] ?? true,
            'auto_save_progress' => $data['auto_save_progress'] ?? true,
            'session_timeout' => $data['session_timeout'] ?? 1800,
            'mobile_optimized' => $data['mobile_optimized'] ?? true,
            'analytics_tracking' => $data['analytics_tracking'] ?? true,
            'exit_intent_detection' => $data['exit_intent_detection'] ?? false
        ];
    }

    /**
     * Inicializa analytics do flow
     */
    private function initializeFlowAnalytics(): array
    {
        return [
            'total_visitors' => 0,
            'completed_flows' => 0,
            'completion_rate' => 0,
            'average_time_to_complete' => 0,
            'abandonment_rate' => 0,
            'revenue_generated' => 0,
            'conversion_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Inicializa analytics do step
     */
    private function initializeStepAnalytics(): array
    {
        return [
            'visitors' => 0,
            'completions' => 0,
            'completion_rate' => 0,
            'average_time_on_step' => 0,
            'exits' => 0,
            'exit_rate' => 0
        ];
    }
}
