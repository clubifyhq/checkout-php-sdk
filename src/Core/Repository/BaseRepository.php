<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Repository;

use Clubify\Checkout\Contracts\RepositoryInterface;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Classe base para Repository Pattern
 *
 * Fornece implementação padrão para operações CRUD comuns.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de persistência
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por subclasses
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
abstract class BaseRepository extends BaseService implements RepositoryInterface
{
    protected string $endpoint;
    protected string $resourceName;

    /**
     * Obtém o endpoint base para o recurso
     */
    abstract protected function getEndpoint(): string;

    /**
     * Obtém o nome do recurso
     */
    abstract protected function getResourceName(): string;

    /**
     * Busca um registro por ID
     */
    public function findById(string $id): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("{$this->getResourceName()}:{$id}"),
            function () use ($id) {
                $response = $this->httpClient->get("{$this->getEndpoint()}/{$id}");
                return $this->isSuccessfulResponse($response) ? $this->extractResponseData($response) : null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Busca vários registros por um array de IDs
     */
    public function findByIds(array $ids): array
    {
        $cacheKey = $this->getCacheKey("{$this->getResourceName()}:ids:" . md5(implode(',', $ids)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($ids) {
                $queryParams = ['ids' => implode(',', $ids)];
                $endpoint = $this->getEndpoint() . '?' . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);
                return $this->isSuccessfulResponse($response) ? $this->extractResponseData($response) ?? [] : [];
            },
            180 // 3 minutes cache
        );
    }

    /**
     * Busca todos os registros com paginação opcional
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("{$this->getResourceName()}:all:{$limit}:{$offset}");

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($limit, $offset) {
                $queryParams = ['limit' => $limit, 'offset' => $offset];
                $endpoint = $this->getEndpoint() . '?' . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);
                return $this->isSuccessfulResponse($response) ? $this->extractResponseData($response) ?? [] : [];
            },
            180 // 3 minutes cache
        );
    }

    /**
     * Busca registros por critérios específicos
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("{$this->getResourceName()}:by:" . md5(serialize($criteria)) . ":{$limit}:{$offset}");

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $limit, $offset) {
                $queryParams = array_merge($criteria, ['limit' => $limit, 'offset' => $offset]);
                $endpoint = $this->getEndpoint() . '?' . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);
                return $this->isSuccessfulResponse($response) ? $this->extractResponseData($response) ?? [] : [];
            },
            180 // 3 minutes cache
        );
    }

    /**
     * Busca um único registro por critérios
     */
    public function findOneBy(array $criteria): ?array
    {
        $results = $this->findBy($criteria, 1, 0);
        return $results['data'][0] ?? $results[0] ?? null;
    }

    /**
     * Cria um novo registro
     */
    public function create(array $data): array
    {
        return $this->executeWithMetrics("create_{$this->getResourceName()}", function () use ($data) {
            $response = $this->httpClient->post($this->getEndpoint(), $data);

            if (!$this->isSuccessfulResponse($response)) {
                throw new HttpException(
                    "Failed to create {$this->getResourceName()}: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $createdData = $this->extractResponseData($response);

            // Dispatch creation event
            $this->dispatch("{$this->getResourceName()}.created", [
                'resource_id' => $createdData['id'] ?? null,
                'data' => $createdData
            ]);

            return $createdData;
        });
    }

    /**
     * Atualiza um registro existente
     */
    public function update(string $id, array $data): array
    {
        return $this->executeWithMetrics("update_{$this->getResourceName()}", function () use ($id, $data) {
            $response = $this->httpClient->put("{$this->getEndpoint()}/{$id}", $data);

            if (!$this->isSuccessfulResponse($response)) {
                throw new HttpException(
                    "Failed to update {$this->getResourceName()}: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $updatedData = $this->extractResponseData($response);

            // Invalidate cache
            $this->cache->delete($this->getCacheKey("{$this->getResourceName()}:{$id}"));

            // Dispatch update event
            $this->dispatch("{$this->getResourceName()}.updated", [
                'resource_id' => $id,
                'data' => $updatedData
            ]);

            return $updatedData;
        });
    }

    /**
     * Remove um registro por ID
     */
    public function delete(string $id): bool
    {
        return $this->executeWithMetrics("delete_{$this->getResourceName()}", function () use ($id) {
            $response = $this->httpClient->delete("{$this->getEndpoint()}/{$id}");

            if ($this->isSuccessfulResponse($response)) {
                // Invalidate cache
                $this->cache->delete($this->getCacheKey("{$this->getResourceName()}:{$id}"));

                // Dispatch deletion event
                $this->dispatch("{$this->getResourceName()}.deleted", [
                    'resource_id' => $id
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Verifica se um registro existe
     */
    public function exists(string $id): bool
    {
        return $this->findById($id) !== null;
    }

    /**
     * Conta o total de registros
     */
    public function count(array $criteria = []): int
    {
        $cacheKey = $this->getCacheKey("{$this->getResourceName()}:count:" . md5(serialize($criteria)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria) {
                $queryParams = array_merge($criteria, ['count_only' => true]);
                $endpoint = $this->getEndpoint() . '?' . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);

                if ($this->isSuccessfulResponse($response)) {
                    $data = $this->extractResponseData($response);
                    return $data['total'] ?? count($data['data'] ?? []);
                }

                return 0;
            },
            300 // 5 minutes cache for counts
        );
    }

    /**
     * Busca com filtros avançados
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("{$this->getResourceName()}:search:" . md5(serialize($filters + $sort)) . ":{$limit}:{$offset}");

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters, $sort, $limit, $offset) {
                $queryParams = array_merge(
                    $filters,
                    ['sort' => $sort, 'limit' => $limit, 'offset' => $offset]
                );
                $endpoint = $this->getEndpoint() . '/search?' . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);
                return $this->isSuccessfulResponse($response) ? $this->extractResponseData($response) ?? [] : [];
            },
            180 // 3 minutes cache
        );
    }

    /**
     * Realiza health check específico do repository
     */
    protected function performHealthCheck(): bool
    {
        try {
            // Test basic connectivity with a simple count operation
            $this->count();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Repository health check failed', [
                'repository' => static::class,
                'resource' => $this->getResourceName(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém o nome do serviço (implementação para BaseService)
     */
    protected function getServiceName(): string
    {
        return $this->getResourceName() . '_repository';
    }

    /**
     * Obtém a versão do serviço (implementação para BaseService)
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Verifica se a resposta HTTP é bem-sucedida
     */
    protected function isSuccessfulResponse(\Psr\Http\Message\ResponseInterface $response): bool
    {
        $statusCode = $response->getStatusCode();
        return $statusCode >= 200 && $statusCode < 300;
    }

    /**
     * Extrai dados JSON da resposta HTTP
     */
    protected function extractResponseData(\Psr\Http\Message\ResponseInterface $response): ?array
    {
        $content = $response->getBody()->getContents();

        if (empty($content)) {
            return null;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $data;
    }
}
