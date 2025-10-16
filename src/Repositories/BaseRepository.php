<?php

declare(strict_types=1);

namespace Clubify\Checkout\Repositories;

use Clubify\Checkout\Contracts\RepositoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Core\Http\ResponseHelper;

/**
 * Implementação base para Repository Pattern
 *
 * Fornece funcionalidades básicas de persistência via HTTP API.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de dados
 * - O: Open/Closed - Extensível sem modificação
 * - L: Liskov Substitution - Pode ser substituída por subclasses
 * - D: Dependency Inversion - Depende de abstrações
 */
abstract class BaseRepository implements RepositoryInterface
{
    protected Configuration $config;
    protected Logger $logger;
    protected Client $httpClient;

    public function __construct(
        Configuration $config,
        Logger $logger,
        Client $httpClient
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * Busca um registro por ID
     */
    public function findById(string $id): ?array
    {
        try {
            $url = $this->buildUrl("/{$id}");
            $response = $this->makeHttpRequest('GET', $url);

            if ($response->getStatusCode() === 404) {
                return null;
            }

            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca vários registros por um array de IDs
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $url = $this->buildUrl('', ['ids' => implode(',', $ids)]);
        $response = $this->makeHttpRequest('GET', $url);

        return $response->getData() ?? [];
    }

    /**
     * Busca todos os registros com paginação opcional
     */
    public function findAll(int $limit = 100, int $offset = 0): array
    {
        $url = $this->buildUrl('', [
            'limit' => $limit,
            'offset' => $offset
        ]);

        $response = $this->makeHttpRequest('GET', $url);
        return $response->getData() ?? [];
    }

    /**
     * Busca registros por critérios específicos
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array
    {
        $params = array_merge($criteria, [
            'limit' => $limit,
            'offset' => $offset
        ]);

        $url = $this->buildUrl('', $params);
        $response = $this->makeHttpRequest('GET', $url);

        return $response->getData() ?? [];
    }

    /**
     * Busca um único registro por critérios
     */
    public function findOneBy(array $criteria): ?array
    {
        $results = $this->findBy($criteria, 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Cria um novo registro
     */
    public function create(array $data): array
    {
        $url = $this->buildUrl();
        // FIX: Wrap data in 'json' option for POST request body
        $response = $this->makeHttpRequest('POST', $url, ['json' => $data]);

        return $response->getData();
    }

    /**
     * Atualiza um registro existente
     */
    public function update(string $id, array $data): array
    {
        $url = $this->buildUrl("/{$id}");
        // FIX: Wrap data in 'json' option for PUT request body
        $response = $this->makeHttpRequest('PUT', $url, ['json' => $data]);

        return $response->getData();
    }

    /**
     * Remove um registro por ID
     */
    public function delete(string $id): bool
    {
        try {
            $url = $this->buildUrl("/{$id}");
            $response = $this->makeHttpRequest('DELETE', $url);

            return $response->getStatusCode() === 204 || $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Verifica se um registro existe
     */
    public function exists(string $id): bool
    {
        try {
            $url = $this->buildUrl("/{$id}");
            $response = $this->httpClient->request('HEAD', $url);

            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Conta o total de registros
     */
    public function count(array $criteria = []): int
    {
        $params = array_merge($criteria, ['count' => true]);
        $url = $this->buildUrl('/count', $params);
        $response = $this->makeHttpRequest('GET', $url);

        $data = $response->getData();
        return $data['count'] ?? 0;
    }

    /**
     * Busca com filtros avançados
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $params = [
            'filters' => $filters,
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset
        ];

        $url = $this->buildUrl('/search');
        $response = $this->makeHttpRequest('POST', $url, $params);

        return $response->getData() ?? [];
    }

    /**
     * Constrói a URL do endpoint
     */
    protected function buildUrl(string $path = '', array $params = []): string
    {
        $baseUrl = $this->getBaseUrl();
        $endpoint = $this->getEndpoint();

        $url = rtrim($baseUrl, '/') . '/' . trim($endpoint, '/') . $path;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Obtém a URL base da API
     */
    protected function getBaseUrl(): string
    {
        return $this->config->get('api.base_url', 'https://api.clubify.com');
    }

    /**
     * Obtém o endpoint específico do repository
     */
    abstract protected function getEndpoint(): string;

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
