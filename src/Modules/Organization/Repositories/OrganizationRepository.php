<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Repositories;

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
     * Obtém o endpoint específico do repository
     */
    protected function getEndpoint(): string
    {
        return '/organizations';
    }

    /**
     * Busca organização por slug/subdomain
     */
    public function findBySlug(string $slug): ?array
    {
        try {
            $url = $this->buildUrl('/slug/' . urlencode($slug));
            $response = $this->httpClient->get($url);

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
     * Busca organização por domínio customizado
     */
    public function findByDomain(string $domain): ?array
    {
        try {
            $url = $this->buildUrl('/domain/' . urlencode($domain));
            $response = $this->httpClient->get($url);

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
     * Busca organizações por status
     */
    public function findByStatus(string $status): array
    {
        $url = $this->buildUrl('', ['status' => $status]);
        $response = $this->httpClient->get($url);

        return $response->getData() ?? [];
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
        $response = $this->httpClient->get($url);

        return $response->getData() ?? [];
    }

    /**
     * Verifica se um slug está disponível
     */
    public function isSlugAvailable(string $slug): bool
    {
        try {
            $url = $this->buildUrl('/slug/' . urlencode($slug) . '/availability');
            $response = $this->httpClient->get($url);

            $data = $response->getData();
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
            $response = $this->httpClient->get($url);

            $data = $response->getData();
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
            $response = $this->httpClient->post($url, []);

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
            $response = $this->httpClient->post($url, []);

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
            $response = $this->httpClient->post($url, []);

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
            $response = $this->httpClient->get($url);

            return $response->getData() ?? [];
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
            $response = $this->httpClient->post($url, $params);

            return $response->getData() ?? [
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
}
