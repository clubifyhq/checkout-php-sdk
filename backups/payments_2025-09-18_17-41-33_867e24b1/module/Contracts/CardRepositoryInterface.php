<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Interface para Repository de Cartões
 *
 * Especializa o RepositoryInterface para operações
 * específicas de cartões tokenizados e salvos.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de cartão
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Interface específica para cartões
 * - D: Dependency Inversion - Abstração para implementações
 */
interface CardRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca cartões por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array;

    /**
     * Busca cartões por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca cartão por token
     */
    public function findByToken(string $token): ?array;

    /**
     * Busca cartões por últimos 4 dígitos
     */
    public function findByLastFour(string $lastFour, string $customerId = null): array;

    /**
     * Busca cartões por bandeira
     */
    public function findByBrand(string $brand, array $filters = []): array;

    /**
     * Busca cartões por gateway
     */
    public function findByGateway(string $gateway, array $filters = []): array;

    /**
     * Busca cartões ativos
     */
    public function findActive(array $filters = []): array;

    /**
     * Busca cartões expirados
     */
    public function findExpired(array $filters = []): array;

    /**
     * Busca cartões próximos ao vencimento
     */
    public function findExpiringNext(int $months = 2): array;

    /**
     * Busca cartões bloqueados
     */
    public function findBlocked(array $filters = []): array;

    /**
     * Busca cartões suspeitos
     */
    public function findSuspicious(array $filters = []): array;

    /**
     * Verifica se cartão já existe
     */
    public function cardExists(string $fingerprint, string $customerId): bool;

    /**
     * Obtém cartão por fingerprint
     */
    public function findByFingerprint(string $fingerprint): ?array;

    /**
     * Atualiza token do cartão
     */
    public function updateToken(string $id, string $newToken, array $tokenData = []): array;

    /**
     * Atualiza dados do gateway
     */
    public function updateGatewayData(string $id, array $gatewayData): array;

    /**
     * Marca cartão como principal
     */
    public function markAsPrimary(string $id, string $customerId): array;

    /**
     * Remove marcação de cartão principal
     */
    public function unmarkPrimary(string $customerId): bool;

    /**
     * Obtém cartão principal do cliente
     */
    public function getPrimaryCard(string $customerId): ?array;

    /**
     * Ativa cartão
     */
    public function activate(string $id): array;

    /**
     * Desativa cartão
     */
    public function deactivate(string $id, string $reason = ''): array;

    /**
     * Bloqueia cartão
     */
    public function block(string $id, string $reason): array;

    /**
     * Desbloqueia cartão
     */
    public function unblock(string $id): array;

    /**
     * Marca cartão como suspeito
     */
    public function markAsSuspicious(string $id, string $reason): array;

    /**
     * Remove marcação de suspeito
     */
    public function clearSuspicious(string $id): array;

    /**
     * Atualiza data de última utilização
     */
    public function updateLastUsed(string $id): array;

    /**
     * Adiciona uso ao cartão
     */
    public function addUsage(string $id, array $usageData): array;

    /**
     * Obtém histórico de uso
     */
    public function getUsageHistory(string $id): array;

    /**
     * Conta usos do cartão
     */
    public function getUsageCount(string $id): int;

    /**
     * Atualiza contadores de fraude
     */
    public function updateFraudCounters(string $id, array $fraudData): array;

    /**
     * Obtém dados de fraude
     */
    public function getFraudData(string $id): array;

    /**
     * Incrementa contador de falhas
     */
    public function incrementFailureCount(string $id): array;

    /**
     * Reseta contador de falhas
     */
    public function resetFailureCount(string $id): array;

    /**
     * Verifica se cartão está bloqueado por falhas
     */
    public function isBlockedByFailures(string $id): bool;

    /**
     * Atualiza dados BIN do cartão
     */
    public function updateBinData(string $id, array $binData): array;

    /**
     * Obtém dados BIN do cartão
     */
    public function getBinData(string $id): array;

    /**
     * Busca cartões por BIN
     */
    public function findByBin(string $bin, array $filters = []): array;

    /**
     * Atualiza dados de validação
     */
    public function updateValidationData(string $id, array $validationData): array;

    /**
     * Obtém dados de validação
     */
    public function getValidationData(string $id): array;

    /**
     * Registra tentativa de uso
     */
    public function registerUsageAttempt(string $id, array $attemptData): array;

    /**
     * Obtém tentativas de uso
     */
    public function getUsageAttempts(string $id): array;

    /**
     * Limpa tentativas antigas
     */
    public function cleanupOldAttempts(int $daysAgo = 30): int;

    /**
     * Atualiza metadados do cartão
     */
    public function updateMetadata(string $id, array $metadata): array;

    /**
     * Obtém metadados do cartão
     */
    public function getMetadata(string $id): array;

    /**
     * Adiciona tag ao cartão
     */
    public function addTag(string $id, string $tag): array;

    /**
     * Remove tag do cartão
     */
    public function removeTag(string $id, string $tag): array;

    /**
     * Obtém tags do cartão
     */
    public function getTags(string $id): array;

    /**
     * Busca cartões por tag
     */
    public function findByTag(string $tag, array $filters = []): array;

    /**
     * Verifica se cartão tem tag
     */
    public function hasTag(string $id, string $tag): bool;

    /**
     * Obtém estatísticas de cartões
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Conta cartões por bandeira
     */
    public function countByBrand(array $filters = []): array;

    /**
     * Conta cartões por status
     */
    public function countByStatus(array $filters = []): array;

    /**
     * Conta cartões por cliente
     */
    public function countByCustomer(array $filters = []): array;

    /**
     * Obtém cartões mais utilizados
     */
    public function getMostUsed(int $limit = 10): array;

    /**
     * Obtém relatório de cartões
     */
    public function getCardReport(array $filters = []): array;

    /**
     * Obtém relatório de expiração
     */
    public function getExpirationReport(): array;

    /**
     * Obtém relatório de fraude
     */
    public function getFraudReport(array $filters = []): array;

    /**
     * Exporta dados de cartões (para compliance)
     */
    public function exportCardData(string $customerId): array;

    /**
     * Remove todos os dados do cliente (LGPD)
     */
    public function removeCustomerData(string $customerId): int;

    /**
     * Anonimiza dados do cartão
     */
    public function anonymizeCard(string $id): array;

    /**
     * Obtém cartões para renovação automática
     */
    public function findForAutoRenewal(array $filters = []): array;

    /**
     * Marca cartão para renovação
     */
    public function markForRenewal(string $id, array $renewalData): array;

    /**
     * Processa renovação de cartão
     */
    public function processRenewal(string $id, array $newCardData): array;

    /**
     * Limpa cartões antigos e inativos
     */
    public function cleanupInactiveCards(int $daysAgo = 365): int;

    /**
     * Arquiva cartões antigos
     */
    public function archiveOldCards(int $daysAgo = 730): int;

    /**
     * Valida integridade dos tokens
     */
    public function validateTokenIntegrity(): array;

    /**
     * Rotaciona tokens por segurança
     */
    public function rotateTokens(array $filters = []): int;

    /**
     * Obtém cartões com tokens inválidos
     */
    public function findInvalidTokens(): array;

    /**
     * Repara tokens corrompidos
     */
    public function repairCorruptedTokens(): int;

    /**
     * Sincroniza dados com gateway
     */
    public function syncWithGateway(string $id): array;

    /**
     * Verifica consistência com gateway
     */
    public function verifyGatewayConsistency(string $id): bool;

    /**
     * Obtém diferenças com gateway
     */
    public function getGatewayDifferences(string $id): array;
}
