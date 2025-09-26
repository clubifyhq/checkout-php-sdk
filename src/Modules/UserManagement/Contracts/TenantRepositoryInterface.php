<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para repository de tenants/organizações
 *
 * Define o contrato para operações de persistência de tenants,
 * seguindo os princípios SOLID e padrões de repository.
 */
interface TenantRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca tenant por slug
     */
    public function findBySlug(string $slug): ?array;

    /**
     * Busca tenant por domínio
     */
    public function findByDomain(string $domain): ?array;

    /**
     * Busca tenants por status
     */
    public function findByStatus(string $status): array;

    /**
     * Busca tenants por plano
     */
    public function findByPlan(string $plan): array;

    /**
     * Atualiza configurações do tenant
     */
    public function updateSettings(string $tenantId, array $settings): array;

    /**
     * Adiciona domínio ao tenant
     */
    public function addDomain(string $tenantId, array $domainData): array;

    /**
     * Remove domínio do tenant
     */
    public function removeDomain(string $tenantId, string $domain): bool;

    /**
     * Suspende tenant
     */
    public function suspend(string $tenantId, string $reason = ''): bool;

    /**
     * Reativa tenant
     */
    public function reactivate(string $tenantId): bool;

    /**
     * Verifica se slug está disponível
     */
    public function isSlugAvailable(string $slug, ?string $excludeTenantId = null): bool;

    /**
     * Verifica se domínio está disponível
     */
    public function isDomainAvailable(string $domain, ?string $excludeTenantId = null): bool;

    /**
     * Obtém estatísticas dos tenants
     */
    public function getTenantStats(): array;

    /**
     * Busca tenants próximos ao vencimento do plano
     */
    public function findExpiringPlans(int $daysThreshold = 30): array;
}