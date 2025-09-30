<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Checkout\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de Flow de Checkout
 *
 * Gerencia criação e navegação de flows de checkout.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gestão de flows
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseService
 * - I: Interface Segregation - Usa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class FlowService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'flow';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Cria novo flow de checkout
     * Endpoint: POST /navigation/flow/:offerId
     */
    public function create(string $offerId, array $flowData): array
    {
        return $this->executeWithMetrics('create_flow', function () use ($offerId, $flowData) {
            $this->validateFlowData($flowData);

            // Log do payload para debug
            $this->logger->debug('Creating flow with payload', [
                'offer_id' => $offerId,
                'payload' => $flowData
            ]);

            // Criar flow via API do cart-service
            $result = $this->makeHttpRequest('POST', "/navigation/flow/{$offerId}", [
                'json' => $flowData
            ]);

            // A API retorna { success, flowId, message }
            $flow = array_merge($flowData, [
                '_id' => $result['flowId'] ?? null,
                'id' => $result['flowId'] ?? null,
                'offerId' => $offerId,
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? ''
            ]);

            // Normalizar ID (API retorna _id, mas SDK usa id)
            $flowId = $flow['_id'] ?? $flow['id'] ?? null;
            if ($flowId && !isset($flow['id'])) {
                $flow['id'] = $flowId;
            }

            // Cache do flow
            if ($flowId) {
                $this->cache->set($this->getCacheKey("flow:{$flowId}"), $flow, 3600);
            }

            // Dispatch evento
            $this->dispatch('flow.created', [
                'flow_id' => $flowId,
                'name' => $flow['name'] ?? 'unknown',
                'offer_id' => $offerId
            ]);

            $this->logger->info('Flow created successfully', [
                'flow_id' => $flowId,
                'name' => $flow['name'] ?? 'unknown',
                'offer_id' => $offerId
            ]);

            return $flow;
        });
    }

    /**
     * Obtém flow de uma oferta
     * Endpoint: GET /navigation/flow/:offerId
     */
    public function get(string $offerId, array $query = []): ?array
    {
        return $this->executeWithMetrics('get_flow', function () use ($offerId, $query) {
            try {
                return $this->makeHttpRequest('GET', "/navigation/flow/{$offerId}", [
                    'query' => $query
                ]);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return null;
                }
                throw $e;
            }
        });
    }

    /**
     * Lista flows com filtros
     * Endpoint: GET /navigation/flows
     */
    public function list(array $filters = []): array
    {
        return $this->executeWithMetrics('list_flows', function () use ($filters) {
            return $this->makeHttpRequest('GET', '/navigation/flows', [
                'query' => $filters
            ]) ?? [];
        });
    }

    /**
     * Obtém flows ativos
     * Endpoint: GET /navigation/flows/active
     */
    public function listActive(): array
    {
        return $this->executeWithMetrics('list_active_flows', function () {
            return $this->makeHttpRequest('GET', '/navigation/flows/active') ?? [];
        });
    }

    /**
     * Atualiza flow
     * Endpoint: PUT /navigation/flow/:flowId
     */
    public function update(string $flowId, array $data): array
    {
        return $this->executeWithMetrics('update_flow', function () use ($flowId, $data) {
            $result = $this->makeHttpRequest('PUT', "/navigation/flow/{$flowId}", ['json' => $data]);

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("flow:{$flowId}"));

            // Dispatch evento
            $this->dispatch('flow.updated', [
                'flow_id' => $flowId,
                'updated_fields' => array_keys($data)
            ]);

            return $result;
        });
    }

    /**
     * Exclui flow
     * Endpoint: DELETE /navigation/flow/:flowId
     */
    public function delete(string $flowId): bool
    {
        return $this->executeWithMetrics('delete_flow', function () use ($flowId) {
            try {
                $response = $this->httpClient->request('DELETE', "/navigation/flow/{$flowId}");

                // Invalidar cache
                $this->cache->delete($this->getCacheKey("flow:{$flowId}"));

                // Dispatch evento
                $this->dispatch('flow.deleted', [
                    'flow_id' => $flowId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete flow', [
                    'flow_id' => $flowId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Publica flow (ativa)
     * Endpoint: POST /navigation/flow/:flowId/publish
     */
    public function publish(string $flowId): array
    {
        return $this->executeWithMetrics('publish_flow', function () use ($flowId) {
            $result = $this->makeHttpRequest('POST', "/navigation/flow/{$flowId}/publish");

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("flow:{$flowId}"));

            // Dispatch evento
            $this->dispatch('flow.published', [
                'flow_id' => $flowId,
                'published_at' => $result['publishedAt'] ?? null
            ]);

            $this->logger->info('Flow published successfully', [
                'flow_id' => $flowId,
                'published_at' => $result['publishedAt'] ?? null
            ]);

            return $result;
        });
    }

    /**
     * Despublica flow (desativa)
     * Endpoint: POST /navigation/flow/:flowId/unpublish
     */
    public function unpublish(string $flowId): array
    {
        return $this->executeWithMetrics('unpublish_flow', function () use ($flowId) {
            $result = $this->makeHttpRequest('POST', "/navigation/flow/{$flowId}/unpublish");

            // Invalidar cache
            $this->cache->delete($this->getCacheKey("flow:{$flowId}"));

            // Dispatch evento
            $this->dispatch('flow.unpublished', [
                'flow_id' => $flowId
            ]);

            $this->logger->info('Flow unpublished successfully', [
                'flow_id' => $flowId
            ]);

            return $result;
        });
    }

    /**
     * Ativa flow (alias para publish)
     */
    public function activate(string $flowId): array
    {
        return $this->publish($flowId);
    }

    /**
     * Desativa flow (alias para unpublish)
     */
    public function deactivate(string $flowId): array
    {
        return $this->unpublish($flowId);
    }

    /**
     * Clona flow
     * Endpoint: POST /navigation/flow/:flowId/clone
     */
    public function clone(string $flowId, array $cloneData): array
    {
        return $this->executeWithMetrics('clone_flow', function () use ($flowId, $cloneData) {
            return $this->makeHttpRequest('POST', "/navigation/flow/{$flowId}/clone", [
                'json' => $cloneData
            ]);
        });
    }

    /**
     * Obtém detalhes do flow
     * Endpoint: GET /navigation/flow/:flowId/details
     */
    public function getDetails(string $flowId): ?array
    {
        return $this->executeWithMetrics('get_flow_details', function () use ($flowId) {
            try {
                return $this->makeHttpRequest('GET', "/navigation/flow/{$flowId}/details");
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return null;
                }
                throw $e;
            }
        });
    }

    /**
     * Obtém analytics do flow
     * Endpoint: GET /navigation/analytics/:flowId
     */
    public function getAnalytics(string $flowId): array
    {
        return $this->executeWithMetrics('get_flow_analytics', function () use ($flowId) {
            return $this->makeHttpRequest('GET', "/navigation/analytics/{$flowId}") ?? [];
        });
    }

    /**
     * Valida dados do flow
     */
    private function validateFlowData(array $data): void
    {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for flow creation");
            }
        }

        if (isset($data['type'])) {
            $allowedTypes = ['standard', 'express', 'custom'];
            if (!in_array($data['type'], $allowedTypes)) {
                throw new ValidationException("Invalid flow type: {$data['type']}. Allowed types: " . implode(', ', $allowedTypes));
            }
        }
    }

    /**
     * Método centralizado para fazer chamadas HTTP
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);
            $data = json_decode($response->getBody()->getContents(), true);

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
}
