<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Repositories;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface específica para Organization Repository
 *
 * Estende o RepositoryInterface base com operações específicas de organização.
 * Segue o princípio da Interface Segregation (I) do SOLID.
 */
interface OrganizationRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca organização por slug/subdomain
     */
    public function findBySlug(string $slug): ?array;

    /**
     * Busca organização por domínio customizado
     */
    public function findByDomain(string $domain): ?array;

    /**
     * Busca organizações por status
     */
    public function findByStatus(string $status): array;

    /**
     * Busca organizações criadas em um período
     */
    public function findByDateRange(\DateTime $startDate, \DateTime $endDate): array;

    /**
     * Verifica se um slug está disponível
     */
    public function isSlugAvailable(string $slug): bool;

    /**
     * Verifica se um domínio está disponível
     */
    public function isDomainAvailable(string $domain): bool;

    /**
     * Ativa uma organização
     */
    public function activate(string $id): bool;

    /**
     * Desativa uma organização
     */
    public function deactivate(string $id): bool;

    /**
     * Suspende uma organização
     */
    public function suspend(string $id): bool;

    /**
     * Obtém estatísticas da organização
     */
    public function getStats(string $id): array;

    /**
     * Busca organizações com paginação e filtros avançados
     */
    public function findWithFilters(array $filters = [], array $sort = [], int $page = 1, int $perPage = 50): array;
}