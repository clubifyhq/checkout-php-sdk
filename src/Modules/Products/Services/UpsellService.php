<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de upsells
 *
 * Responsável pela gestão de upsells pós-compra:
 * - Criação e configuração de upsells
 * - Sequências de upsell em cascata
 * - Targeting baseado em comportamento
 * - Templates personalizáveis
 * - Análise de performance
 * - Automação de workflows
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas upsells
 * - O: Open/Closed - Extensível via tipos de upsell
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de upsell
 * - D: Dependency Inversion - Depende de abstrações
 */
class UpsellService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'upsell';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria um novo upsell
     */
    public function create(array $upsellData): array
    {
        return $this->executeWithMetrics('create_upsell', function () use ($upsellData) {
            $this->validateUpsellData($upsellData);

            // Preparar dados do upsell
            $data = array_merge($upsellData, [
                'status' => $upsellData['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildUpsellConfiguration($upsellData),
                'analytics' => $this->initializeUpsellAnalytics(),
                'sequence_order' => $this->getNextSequenceOrder($upsellData['flow_id'] ?? null)
            ]);

            // Criar upsell via API
            $response = $this->makeHttpRequest('POST', '/upsells', $data);
            $upsell = ResponseHelper::getData($response);

            // Cache do upsell
            $this->cache->set($this->getCacheKey("upsell:{$upsell['id']}"), $upsell, 3600);

            // Dispatch evento
            $this->dispatch('upsell.created', [
                'upsell_id' => $upsell['id'],
                'flow_id' => $upsell['flow_id'] ?? null,
                'product_id' => $upsell['product_id'],
                'type' => $upsell['type'],
                'sequence_order' => $upsell['sequence_order']
            ]);

            $this->logger->info('Upsell created successfully', [
                'upsell_id' => $upsell['id'],
                'product_id' => $upsell['product_id'],
                'type' => $upsell['type']
            ]);

            return $upsell;
        });
    }

    /**
     * Obtém um upsell por ID
     */
    public function get(string $upsellId): ?array
    {
        return $this->getCachedOrExecute(
            "upsell:{$upsellId}",
            fn () => $this->fetchUpsellById($upsellId),
            3600
        );
    }

    /**
     * Obtém upsells por flow
     */
    public function getByFlow(string $flowId): array
    {
        return $this->executeWithMetrics('get_upsells_by_flow', function () use ($flowId) {
            $response = $this->makeHttpRequest('GET', '/upsells', [
                'query' => ['flow_id' => $flowId, 'order_by' => 'sequence_order']
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém upsells por tipo
     */
    public function getByType(string $type): array
    {
        return $this->executeWithMetrics('get_upsells_by_type', function () use ($type) {
            $response = $this->makeHttpRequest('GET', '/upsells', [
                'query' => ['type' => $type]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Atualiza upsell
     */
    public function update(string $upsellId, array $data): array
    {
        return $this->executeWithMetrics('update_upsell', function () use ($upsellId, $data) {
            $this->validateUpsellUpdateData($data);

            // Verificar se upsell existe
            $currentUpsell = $this->get($upsellId);
            if (!$currentUpsell) {
                throw new ValidationException("Upsell not found: {$upsellId}");
            }

            // CORREÇÃO: Não adicionar updated_at - a API gerencia timestamps automaticamente
            // $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}", $data);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.updated', [
                'upsell_id' => $upsellId,
                'updated_fields' => array_keys($data)
            ]);

            return $upsell;
        });
    }

    /**
     * Configura sequência de upsells
     */
    public function configureSequence(string $flowId, array $upsellSequence): array
    {
        return $this->executeWithMetrics('configure_upsell_sequence', function () use ($flowId, $upsellSequence) {
            $this->validateUpsellSequence($upsellSequence);

            $response = $this->makeHttpRequest('PUT', "/flows/{$flowId}/upsell-sequence", [
                'sequence' => $upsellSequence
            ]);

            $result = ResponseHelper::getData($response);

            // Invalidar cache dos upsells afetados
            foreach ($upsellSequence as $upsellConfig) {
                if (isset($upsellConfig['upsell_id'])) {
                    $this->invalidateUpsellCache($upsellConfig['upsell_id']);
                }
            }

            // Dispatch evento
            $this->dispatch('upsell.sequence_configured', [
                'flow_id' => $flowId,
                'sequence_length' => count($upsellSequence)
            ]);

            return $result;
        });
    }

    /**
     * Configura targeting do upsell
     */
    public function updateTargeting(string $upsellId, array $targetingRules): array
    {
        return $this->executeWithMetrics('update_upsell_targeting', function () use ($upsellId, $targetingRules) {
            $this->validateTargetingRules($targetingRules);

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/targeting", [
                'targeting_rules' => $targetingRules
            ]);

            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.targeting_updated', [
                'upsell_id' => $upsellId,
                'rules_count' => count($targetingRules)
            ]);

            return $upsell;
        });
    }

    /**
     * Configura template do upsell
     */
    public function updateTemplate(string $upsellId, array $templateConfig): array
    {
        return $this->executeWithMetrics('update_upsell_template', function () use ($upsellId, $templateConfig) {
            $this->validateTemplateConfig($templateConfig);

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/template", $templateConfig);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.template_updated', [
                'upsell_id' => $upsellId,
                'template_type' => $templateConfig['type'] ?? 'default'
            ]);

            return $upsell;
        });
    }

    /**
     * Configura A/B testing para upsell
     */
    public function configureAbTesting(string $upsellId, array $testConfig): array
    {
        return $this->executeWithMetrics('configure_upsell_ab_testing', function () use ($upsellId, $testConfig) {
            $this->validateAbTestConfig($testConfig);

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/ab-testing", $testConfig);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.ab_testing_configured', [
                'upsell_id' => $upsellId,
                'test_name' => $testConfig['name'],
                'variants_count' => count($testConfig['variants'] ?? [])
            ]);

            return $upsell;
        });
    }

    /**
     * Obtém resultados de A/B testing
     */
    public function getAbTestResults(string $upsellId): array
    {
        return $this->executeWithMetrics('get_upsell_ab_test_results', function () use ($upsellId) {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}/ab-testing/results");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Configura automação do upsell
     */
    public function configureAutomation(string $upsellId, array $automationRules): array
    {
        return $this->executeWithMetrics('configure_upsell_automation', function () use ($upsellId, $automationRules) {
            $this->validateAutomationRules($automationRules);

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/automation", [
                'automation_rules' => $automationRules
            ]);

            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.automation_configured', [
                'upsell_id' => $upsellId,
                'rules_count' => count($automationRules)
            ]);

            return $upsell;
        });
    }

    /**
     * Reordena sequência de upsells
     */
    public function reorderSequence(string $flowId, array $orderMapping): array
    {
        return $this->executeWithMetrics('reorder_upsell_sequence', function () use ($flowId, $orderMapping) {
            $response = $this->makeHttpRequest('PUT', "/flows/{$flowId}/upsell-sequence/reorder", [
                'order_mapping' => $orderMapping
            ]);

            $result = ResponseHelper::getData($response);

            // Invalidar cache dos upsells afetados
            foreach ($orderMapping as $upsellId => $newOrder) {
                $this->invalidateUpsellCache($upsellId);
            }

            // Dispatch evento
            $this->dispatch('upsell.sequence_reordered', [
                'flow_id' => $flowId,
                'affected_upsells' => count($orderMapping)
            ]);

            return $result;
        });
    }

    /**
     * Ativa upsell
     */
    public function activate(string $upsellId): bool
    {
        return $this->updateStatus($upsellId, 'active');
    }

    /**
     * Desativa upsell
     */
    public function deactivate(string $upsellId): bool
    {
        return $this->updateStatus($upsellId, 'inactive');
    }

    /**
     * Pausa upsell
     */
    public function pause(string $upsellId): bool
    {
        return $this->updateStatus($upsellId, 'paused');
    }

    /**
     * Duplica upsell
     */
    public function duplicate(string $upsellId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_upsell', function () use ($upsellId, $overrideData) {
            $response = $this->makeHttpRequest('POST', "/upsells/{$upsellId}/duplicate", $overrideData);
            $upsell = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('upsell.duplicated', [
                'original_id' => $upsellId,
                'new_id' => $upsell['id']
            ]);

            return $upsell;
        });
    }

    /**
     * Obtém analytics do upsell
     */
    public function getAnalytics(string $upsellId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_upsell_analytics', function () use ($upsellId, $filters) {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}/analytics", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém análise de performance da sequência
     */
    public function getSequencePerformance(string $flowId): array
    {
        return $this->executeWithMetrics('get_upsell_sequence_performance', function () use ($flowId) {
            $response = $this->makeHttpRequest('GET', "/flows/{$flowId}/upsell-sequence/performance");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém relatório de conversão
     */
    public function getConversionReport(string $upsellId, array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_upsell_conversion_report', function () use ($upsellId, $dateRange) {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}/conversion-report", [
                'query' => $dateRange
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém upsells com melhor performance
     */
    public function getTopPerforming(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_performing_upsells', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/upsells/top-performing', [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Lista upsells com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_upsells', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->makeHttpRequest('GET', '/upsells', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Remove upsell
     */
    public function delete(string $upsellId): bool
    {
        return $this->executeWithMetrics('delete_upsell', function () use ($upsellId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "/upsells/{$upsellId}");

                // Invalidar cache
                $this->invalidateUpsellCache($upsellId);

                // Dispatch evento
                $this->dispatch('upsell.deleted', [
                    'upsell_id' => $upsellId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete upsell', [
                    'upsell_id' => $upsellId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Conta upsells ativos
     */
    public function countActive(): int
    {
        try {
            $response = $this->makeHttpRequest('GET', '/upsells/count', [
                'query' => ['status' => 'active']
            ]);
            $data = ResponseHelper::getData($response);
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count active upsells', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Executa teste de conversão do upsell
     */
    public function runConversionTest(string $upsellId, array $testConfig): array
    {
        return $this->executeWithMetrics('run_upsell_conversion_test', function () use ($upsellId, $testConfig) {
            $this->validateConversionTestConfig($testConfig);

            $response = $this->makeHttpRequest('POST', "/upsells/{$upsellId}/conversion-test", $testConfig);
            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('upsell.conversion_test_executed', [
                'upsell_id' => $upsellId,
                'test_type' => $testConfig['type'] ?? 'standard',
                'duration' => $testConfig['duration'] ?? 'default'
            ]);

            return $result;
        });
    }

    /**
     * Obtém recomendações de otimização
     */
    public function getOptimizationRecommendations(string $upsellId): array
    {
        return $this->executeWithMetrics('get_upsell_optimization_recommendations', function () use ($upsellId) {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}/optimization-recommendations");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Aplica otimizações automáticas
     */
    public function applyAutoOptimizations(string $upsellId, array $optimizationRules = []): array
    {
        return $this->executeWithMetrics('apply_upsell_auto_optimizations', function () use ($upsellId, $optimizationRules) {
            $response = $this->makeHttpRequest('POST', "/upsells/{$upsellId}/auto-optimize", [
                'optimization_rules' => $optimizationRules
            ]);

            $result = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.auto_optimizations_applied', [
                'upsell_id' => $upsellId,
                'optimizations_count' => count($result['applied_optimizations'] ?? [])
            ]);

            return $result;
        });
    }

    /**
     * Configura limitações e restrições
     */
    public function configureRestrictions(string $upsellId, array $restrictions): array
    {
        return $this->executeWithMetrics('configure_upsell_restrictions', function () use ($upsellId, $restrictions) {
            $this->validateRestrictions($restrictions);

            $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/restrictions", [
                'restrictions' => $restrictions
            ]);

            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.restrictions_configured', [
                'upsell_id' => $upsellId,
                'restrictions_count' => count($restrictions)
            ]);

            return $upsell;
        });
    }

    /**
     * Agenda upsell para ativação futura
     */
    public function scheduleActivation(string $upsellId, string $scheduledDate, array $options = []): array
    {
        return $this->executeWithMetrics('schedule_upsell_activation', function () use ($upsellId, $scheduledDate, $options) {
            if (!strtotime($scheduledDate)) {
                throw new ValidationException('Invalid scheduled date format');
            }

            $response = $this->makeHttpRequest('POST', "/upsells/{$upsellId}/schedule-activation", [
                'scheduled_date' => $scheduledDate,
                'options' => $options
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('upsell.activation_scheduled', [
                'upsell_id' => $upsellId,
                'scheduled_date' => $scheduledDate,
                'options' => $options
            ]);

            return $result;
        });
    }

    /**
     * Exporta configuração do upsell
     */
    public function exportConfiguration(string $upsellId): array
    {
        return $this->executeWithMetrics('export_upsell_configuration', function () use ($upsellId) {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}/export-configuration");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Importa configuração para um upsell
     */
    public function importConfiguration(string $upsellId, array $configuration): array
    {
        return $this->executeWithMetrics('import_upsell_configuration', function () use ($upsellId, $configuration) {
            $this->validateImportConfiguration($configuration);

            $response = $this->makeHttpRequest('POST', "/upsells/{$upsellId}/import-configuration", [
                'configuration' => $configuration
            ]);

            $upsell = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateUpsellCache($upsellId);

            // Dispatch evento
            $this->dispatch('upsell.configuration_imported', [
                'upsell_id' => $upsellId,
                'configuration_keys' => array_keys($configuration)
            ]);

            return $upsell;
        });
    }

    /**
     * Busca upsell por ID via API
     */
    private function fetchUpsellById(string $upsellId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/upsells/{$upsellId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Obtém próxima ordem na sequência
     */
    private function getNextSequenceOrder(?string $flowId): int
    {
        if (!$flowId) {
            return 1;
        }

        try {
            $upsells = $this->getByFlow($flowId);
            $maxOrder = 0;

            foreach ($upsells as $upsell) {
                if (isset($upsell['sequence_order']) && $upsell['sequence_order'] > $maxOrder) {
                    $maxOrder = $upsell['sequence_order'];
                }
            }

            return $maxOrder + 1;
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * Atualiza status do upsell
     */
    private function updateStatus(string $upsellId, string $status): bool
    {
        return $this->executeWithMetrics("update_upsell_status_{$status}", function () use ($upsellId, $status) {
            try {
                $response = $this->makeHttpRequest('PUT', "/upsells/{$upsellId}/status", [
                    'status' => $status
                ]);

                // Invalidar cache
                $this->invalidateUpsellCache($upsellId);

                // Dispatch evento
                $this->dispatch('upsell.status_changed', [
                    'upsell_id' => $upsellId,
                    'new_status' => $status
                ]);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                $this->logger->error("Failed to update upsell status to {$status}", [
                    'upsell_id' => $upsellId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Invalida cache do upsell
     */
    private function invalidateUpsellCache(string $upsellId): void
    {
        $this->cache->delete($this->getCacheKey("upsell:{$upsellId}"));
    }

    /**
     * Valida dados do upsell
     */
    private function validateUpsellData(array $data): void
    {
        $required = ['product_id', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for upsell creation");
            }
        }

        $allowedTypes = ['one_time_offer', 'downsell', 'cross_sell', 'subscription_upgrade', 'addon'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid upsell type: {$data['type']}");
        }

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida dados de atualização do upsell
     */
    private function validateUpsellUpdateData(array $data): void
    {
        if (isset($data['type'])) {
            $allowedTypes = ['one_time_offer', 'downsell', 'cross_sell', 'subscription_upgrade', 'addon'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid upsell type: {$data['type']}");
            }
        }

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida sequência de upsells
     */
    private function validateUpsellSequence(array $sequence): void
    {
        if (empty($sequence)) {
            throw new ValidationException('Upsell sequence cannot be empty');
        }

        foreach ($sequence as $index => $upsellConfig) {
            if (!is_array($upsellConfig) || !isset($upsellConfig['upsell_id'])) {
                throw new ValidationException("Invalid upsell configuration at index {$index}");
            }

            if (!isset($upsellConfig['order']) || !is_numeric($upsellConfig['order'])) {
                throw new ValidationException("Missing or invalid order for upsell at index {$index}");
            }
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

            $allowedConditions = ['purchase_amount', 'product_purchased', 'customer_segment', 'time_on_page', 'previous_upsell_declined'];
            if (!in_array($rule['condition'], $allowedConditions)) {
                throw new ValidationException("Invalid targeting condition: {$rule['condition']}");
            }
        }
    }

    /**
     * Valida configuração de template
     */
    private function validateTemplateConfig(array $config): void
    {
        $allowedTypes = ['standard', 'minimal', 'video', 'countdown', 'social_proof'];
        if (isset($config['type']) && !in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid template type: {$config['type']}");
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
     * Valida regras de automação
     */
    private function validateAutomationRules(array $rules): void
    {
        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['trigger']) || !isset($rule['action'])) {
                throw new ValidationException('Invalid automation rule format');
            }

            $allowedTriggers = ['time_based', 'behavior_based', 'conversion_based', 'abandonment_based'];
            if (!in_array($rule['trigger'], $allowedTriggers)) {
                throw new ValidationException("Invalid automation trigger: {$rule['trigger']}");
            }

            $allowedActions = ['show_upsell', 'send_email', 'apply_discount', 'skip_to_next'];
            if (!in_array($rule['action'], $allowedActions)) {
                throw new ValidationException("Invalid automation action: {$rule['action']}");
            }
        }
    }

    /**
     * Constrói configuração do upsell
     */
    private function buildUpsellConfiguration(array $data): array
    {
        return [
            'display_settings' => $data['display_settings'] ?? [],
            'timing_rules' => $data['timing_rules'] ?? ['show_after' => 2, 'hide_after' => 30],
            'interaction_tracking' => $data['interaction_tracking'] ?? true,
            'conversion_tracking' => $data['conversion_tracking'] ?? true,
            'fallback_settings' => $data['fallback_settings'] ?? []
        ];
    }

    /**
     * Inicializa analytics do upsell
     */
    private function initializeUpsellAnalytics(): array
    {
        return [
            'impressions' => 0,
            'interactions' => 0,
            'conversions' => 0,
            'revenue_generated' => 0,
            'conversion_rate' => 0,
            'average_revenue_per_user' => 0,
            'sequence_completion_rate' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Valida configuração de teste de conversão
     */
    private function validateConversionTestConfig(array $config): void
    {
        $allowedTypes = ['performance', 'split', 'multivariate', 'funnel'];
        if (isset($config['type']) && !in_array($config['type'], $allowedTypes)) {
            throw new ValidationException("Invalid test type: {$config['type']}");
        }

        if (isset($config['duration']) && (!is_numeric($config['duration']) || $config['duration'] <= 0)) {
            throw new ValidationException('Test duration must be a positive number');
        }
    }

    /**
     * Valida restrições do upsell
     */
    private function validateRestrictions(array $restrictions): void
    {
        $allowedRestrictions = [
            'max_impressions_per_user', 'max_conversions_per_user', 'time_window',
            'geographic_restrictions', 'device_restrictions', 'user_segment_restrictions'
        ];

        foreach ($restrictions as $restriction => $value) {
            if (!in_array($restriction, $allowedRestrictions)) {
                throw new ValidationException("Invalid restriction type: {$restriction}");
            }

            if (in_array($restriction, ['max_impressions_per_user', 'max_conversions_per_user']) &&
                (!is_numeric($value) || $value < 0)) {
                throw new ValidationException("Restriction '{$restriction}' must be a non-negative number");
            }
        }
    }

    /**
     * Valida configuração de importação
     */
    private function validateImportConfiguration(array $configuration): void
    {
        $allowedSections = [
            'targeting', 'template', 'automation', 'restrictions',
            'ab_testing', 'display_settings', 'timing_rules'
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
