<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para repositório de domínios
 *
 * Estende a RepositoryInterface base adicionando métodos específicos
 * para operações com domínios no sistema multi-tenant.
 * Segue os princípios SOLID e Repository Pattern.
 */
interface DomainRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca domínios por tenant
     */
    public function findByTenantId(string $tenantId): array;

    /**
     * Busca domínio por nome
     */
    public function findByDomain(string $domain): ?array;

    /**
     * Busca domínio por token de verificação
     */
    public function findByVerificationToken(string $token): ?array;

    /**
     * Atualiza status de verificação
     */
    public function updateVerificationStatus(string $domainId, string $status, ?array $metadata = null): bool;

    /**
     * Lista domínios verificados de um tenant
     */
    public function getVerifiedDomains(string $tenantId): array;

    /**
     * Lista domínios pendentes de verificação
     */
    public function getPendingVerification(): array;

    /**
     * Verifica se um domínio já existe
     */
    public function domainExists(string $domain): bool;

    /**
     * Atualiza configurações DNS
     */
    public function updateDnsRecords(string $domainId, array $records): bool;

    /**
     * Atualiza configurações SSL
     */
    public function updateSslConfig(string $domainId, array $config): bool;

    /**
     * Remove domínios expirados/inativos
     */
    public function cleanupInactiveDomains(int $daysInactive = 30): int;

    /**
     * Obtém estatísticas de domínios
     */
    public function getStats(string $tenantId): array;
}