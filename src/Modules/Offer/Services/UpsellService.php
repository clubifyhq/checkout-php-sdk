<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de upsells
 *
 * Responsável pela gestão completa de upsells nas ofertas:
 * - CRUD de upsells
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
                'sequence_order' => $this->getNextSequenceOrder($upsellData['offer_id'] ?? null)
            ]);

            // Criar upsell via API
            $response = $this->httpClient->post('/upsells', $data);
            $upsell = ResponseHelper::getData($response);

            // Cache do upsell
            $this->cache->set($this->getCacheKey("upsell:{$upsell['id']}"), $upsell, 3600);

            // Dispatch evento
            $this->dispatch('upsell.created', [
                'upsell_id' => $upsell['id'],
                'offer_id' => $upsell['offer_id'] ?? null,
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
     * Obtém upsells por oferta
     */
    public function getByOffer(string $offerId): array
    {
        return $this->executeWithMetrics('get_upsells_by_offer', function () use ($offerId) {
            $response = $this->httpClient->get("/offers/{$offerId}/upsells");
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

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/upsells/{$upsellId}", $data);
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
     * Exclui upsell
     */
    public function delete(string $upsellId): bool
    {
        return $this->executeWithMetrics('delete_upsell', function () use ($upsellId) {
            try {
                $response = $this->httpClient->delete("/upsells/{$upsellId}");

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
     * Configura sequência de upsells
     */
    public function configureSequence(string $offerId, array $upsellSequence): array
    {
        return $this->executeWithMetrics('configure_upsell_sequence', function () use ($offerId, $upsellSequence) {
            $this->validateUpsellSequence($upsellSequence);

            $response = $this->httpClient->put("/offers/{$offerId}/upsell-sequence", [
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
                'offer_id' => $offerId,
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

            $response = $this->httpClient->put("/upsells/{$upsellId}/targeting", [
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

            $response = $this->httpClient->put("/upsells/{$upsellId}/template", $templateConfig);
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
     * Obtém analytics do upsell
     */
    public function getAnalytics(string $upsellId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_upsell_analytics', function () use ($upsellId, $filters) {
            $response = $this->httpClient->get("/upsells/{$upsellId}/analytics", [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém análise de performance da sequência
     */
    public function getSequencePerformance(string $offerId): array
    {
        return $this->executeWithMetrics('get_upsell_sequence_performance', function () use ($offerId) {
            $response = $this->httpClient->get("/offers/{$offerId}/upsell-sequence/performance");
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

            $response = $this->httpClient->get('/upsells', [
                'query' => $queryParams
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Duplica upsell
     */
    public function duplicate(string $upsellId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_upsell', function () use ($upsellId, $overrideData) {
            $response = $this->httpClient->post("/upsells/{$upsellId}/duplicate", $overrideData);
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
     * Busca upsell por ID via API
     */
    private function fetchUpsellById(string $upsellId): ?array
    {
        try {
            $response = $this->httpClient->get("/upsells/{$upsellId}");
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
    private function getNextSequenceOrder(?string $offerId): int
    {
        if (!$offerId) {
            return 1;
        }

        try {
            $upsells = $this->getByOffer($offerId);
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
                $response = $this->httpClient->put("/upsells/{$upsellId}/status", [
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
}