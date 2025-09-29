<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Repositories\BaseRepository;
use Clubify\Checkout\Modules\Organization\Repositories\OrganizationRepositoryInterface;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Repository para gestão de organizações
 *
 * Implementa operações específicas de persistência para organizações.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de organizações
 * - O: Open/Closed - Extensível sem modificação via interface
 * - L: Liskov Substitution - Pode substituir BaseRepository
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrganizationRepository extends BaseRepository implements OrganizationRepositoryInterface
{
    /**
     * Obtém o endpoint específico do repository (integração com user-management-service)
     */
    protected function getEndpoint(): string
    {
        return '/api/v1/organizations';
    }

    /**
     * Busca organização por slug/subdomain
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            $url = $this->buildUrl('/slug/' . urlencode($slug));
            $response = $this->makeHttpRequest('GET', $url);

            if ($response->getStatusCode() === 404) {
                return null;
            }

            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca organização por domínio customizado
     */
    public function findByDomain(string $domain): ?array
    {
        try {
            $url = $this->buildUrl('/domain/' . urlencode($domain));
            $response = $this->makeHttpRequest('GET', $url);

            if ($response->getStatusCode() === 404) {
                return null;
            }

            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca organizações por status
     */
    public function findByStatus(string $status): array
    {
        $url = $this->buildUrl('', ['status' => $status]);
        $response = $this->makeHttpRequest('GET', $url);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Busca organizações criadas em um período
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array
    {
        $params = [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ];

        $url = $this->buildUrl('/date-range', $params);
        $response = $this->makeHttpRequest('GET', $url);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Verifica se um slug está disponível
     */
    public function isSlugAvailable(string $slug): bool
    {
        try {
            $url = $this->buildUrl('/slug/' . urlencode($slug) . '/availability');
            $response = $this->makeHttpRequest('GET', $url);

            $data = ResponseHelper::getData($response);
            return $data['available'] ?? false;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return true; // Se não existe, está disponível
            }
            throw $e;
        }
    }

    /**
     * Verifica se um domínio está disponível
     */
    public function isDomainAvailable(string $domain): bool
    {
        try {
            $url = $this->buildUrl('/domain/' . urlencode($domain) . '/availability');
            $response = $this->makeHttpRequest('GET', $url);

            $data = ResponseHelper::getData($response);
            return $data['available'] ?? false;
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return true; // Se não existe, está disponível
            }
            throw $e;
        }
    }

    /**
     * Ativa uma organização
     */
    public function activate(string $id): bool
    {
        try {
            $url = $this->buildUrl("/{$id}/activate");
            $response = $this->makeHttpRequest('POST', $url, []);

            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            $this->logger->error('Failed to activate organization', [
                'organization_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Desativa uma organização
     */
    public function deactivate(string $id): bool
    {
        try {
            $url = $this->buildUrl("/{$id}/deactivate");
            $response = $this->makeHttpRequest('POST', $url, []);

            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            $this->logger->error('Failed to deactivate organization', [
                'organization_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Suspende uma organização
     */
    public function suspend(string $id): bool
    {
        try {
            $url = $this->buildUrl("/{$id}/suspend");
            $response = $this->makeHttpRequest('POST', $url, []);

            return $response->getStatusCode() === 200;
        } catch (HttpException $e) {
            $this->logger->error('Failed to suspend organization', [
                'organization_id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém estatísticas da organização
     */
    public function getStats(string $id): array
    {
        try {
            $url = $this->buildUrl("/{$id}/stats");
            $response = $this->makeHttpRequest('GET', $url);

            return ResponseHelper::getData($response) ?? [];
        } catch (HttpException $e) {
            $this->logger->error('Failed to get organization stats', [
                'organization_id' => $id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Busca organizações com paginação e filtros avançados
     */
    public function findWithFilters(array $filters = [], array $sort = [], int $page = 1, int $perPage = 50): array
    {
        $params = [
            'page' => $page,
            'per_page' => $perPage
        ];

        if (!empty($filters)) {
            $params['filters'] = $filters;
        }

        if (!empty($sort)) {
            $params['sort'] = $sort;
        }

        try {
            $url = $this->buildUrl('/search');
            $response = $this->makeHttpRequest('POST', $url, $params);

            return ResponseHelper::getData($response) ?? [
                'data' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
        } catch (HttpException $e) {
            $this->logger->error('Failed to search organizations', [
                'filters' => $filters,
                'sort' => $sort,
                'page' => $page,
                'per_page' => $perPage,
                'error' => $e->getMessage()
            ]);

            return [
                'data' => [],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => 0,
                    'total_pages' => 0
                ]
            ];
        }
    }

    /**
     * Cria uma nova organização com validações específicas
     */
    public function create(array $data): array
    {
        // Validações específicas antes de criar
        if (isset($data['slug']) && !$this->isSlugAvailable($data['slug'])) {
            throw new \InvalidArgumentException("Slug '{$data['slug']}' is not available");
        }

        if (isset($data['domain']) && !$this->isDomainAvailable($data['domain'])) {
            throw new \InvalidArgumentException("Domain '{$data['domain']}' is not available");
        }

        return parent::create($data);
    }

    /**
     * Atualiza uma organização com validações específicas
     */
    public function update(string $id, array $data): array
    {
        // Se está alterando slug, verifica disponibilidade
        if (isset($data['slug'])) {
            $current = $this->findById($id);
            if ($current && $current['slug'] !== $data['slug'] && !$this->isSlugAvailable($data['slug'])) {
                throw new \InvalidArgumentException("Slug '{$data['slug']}' is not available");
            }
        }

        // Se está alterando domínio, verifica disponibilidade
        if (isset($data['domain'])) {
            $current = $this->findById($id);
            if ($current && $current['domain'] !== $data['domain'] && !$this->isDomainAvailable($data['domain'])) {
                throw new \InvalidArgumentException("Domain '{$data['domain']}' is not available");
            }
        }

        return parent::update($id, $data);
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper e adiciona headers organizacionais
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            // Adicionar headers organizacionais se disponíveis
            if (!isset($options['headers'])) {
                $options['headers'] = [];
            }

            // Header organizationId para multi-tenancy
            $organizationId = $this->config->get('organization_id');
            if ($organizationId) {
                $options['headers']['X-Organization-Id'] = $organizationId;
            }

            // Headers de autenticação e tenant
            $tenantId = $this->config->get('tenant_id');
            if ($tenantId) {
                $options['headers']['X-Tenant-Id'] = $tenantId;
            }

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
