<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers\Contracts;

use ClubifyCheckout\Contracts\RepositoryInterface;

/**
 * Interface para Repository de Clientes
 *
 * Especializa o RepositoryInterface para operações
 * específicas de clientes, incluindo matching,
 * histórico, perfis e compliance.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de cliente
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Pode substituir RepositoryInterface
 * - I: Interface Segregation - Interface específica para clientes
 * - D: Dependency Inversion - Abstração para implementações
 */
interface CustomerRepositoryInterface extends RepositoryInterface
{
    /**
     * Busca clientes por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array;

    /**
     * Busca cliente por email
     */
    public function findByEmail(string $email, string $organizationId = null): ?array;

    /**
     * Busca cliente por documento
     */
    public function findByDocument(string $document, string $organizationId = null): ?array;

    /**
     * Busca cliente por telefone
     */
    public function findByPhone(string $phone, string $organizationId = null): ?array;

    /**
     * Busca clientes por nome
     */
    public function findByName(string $name, array $filters = []): array;

    /**
     * Busca clientes por múltiplos critérios (matching)
     */
    public function findByCriteria(array $criteria): array;

    /**
     * Busca clientes similares
     */
    public function findSimilar(array $customerData, float $threshold = 0.8): array;

    /**
     * Busca possíveis duplicatas
     */
    public function findPotentialDuplicates(string $customerId): array;

    /**
     * Busca clientes por localização
     */
    public function findByLocation(string $city, string $state = null, string $country = null): array;

    /**
     * Busca clientes por faixa etária
     */
    public function findByAgeRange(int $minAge, int $maxAge): array;

    /**
     * Busca clientes por segmento
     */
    public function findBySegment(string $segment, array $filters = []): array;

    /**
     * Busca clientes por tag
     */
    public function findByTag(string $tag, array $filters = []): array;

    /**
     * Busca clientes por valor de compra
     */
    public function findByPurchaseValue(float $minValue, float $maxValue = null): array;

    /**
     * Busca clientes por frequência de compra
     */
    public function findByPurchaseFrequency(int $minPurchases, int $maxPurchases = null): array;

    /**
     * Busca clientes por última atividade
     */
    public function findByLastActivity(string $startDate, string $endDate = null): array;

    /**
     * Busca clientes inativos
     */
    public function findInactive(int $daysAgo = 90): array;

    /**
     * Busca clientes com risco de churn
     */
    public function findChurnRisk(array $criteria = []): array;

    /**
     * Busca clientes VIP/premium
     */
    public function findVipCustomers(array $criteria = []): array;

    /**
     * Atualiza dados do cliente se houve mudanças
     */
    public function updateIfChanged(string $id, array $data): ?array;

    /**
     * Mescla dados de múltiplos clientes
     */
    public function mergeCustomers(string $primaryId, array $duplicateIds): array;

    /**
     * Adiciona tag ao cliente
     */
    public function addTag(string $id, string $tag): array;

    /**
     * Remove tag do cliente
     */
    public function removeTag(string $id, string $tag): array;

    /**
     * Obtém tags do cliente
     */
    public function getTags(string $id): array;

    /**
     * Verifica se cliente tem tag
     */
    public function hasTag(string $id, string $tag): bool;

    /**
     * Adiciona transação ao histórico
     */
    public function addTransaction(string $customerId, array $transactionData): array;

    /**
     * Obtém histórico de transações
     */
    public function getTransactionHistory(string $customerId, array $filters = []): array;

    /**
     * Atualiza perfil comportamental
     */
    public function updateBehaviorProfile(string $id, array $profileData): array;

    /**
     * Obtém perfil comportamental
     */
    public function getBehaviorProfile(string $id): ?array;

    /**
     * Calcula métricas do cliente
     */
    public function calculateCustomerMetrics(string $id): array;

    /**
     * Atualiza score do cliente
     */
    public function updateCustomerScore(string $id, float $score, array $factors = []): array;

    /**
     * Obtém clientes por score
     */
    public function findByScore(float $minScore, float $maxScore = null): array;

    /**
     * Registra evento do cliente
     */
    public function recordEvent(string $customerId, array $eventData): array;

    /**
     * Obtém eventos do cliente
     */
    public function getEvents(string $customerId, array $filters = []): array;

    /**
     * Adiciona nota ao cliente
     */
    public function addNote(string $customerId, string $note, string $author = null): array;

    /**
     * Obtém notas do cliente
     */
    public function getNotes(string $customerId): array;

    /**
     * Atualiza preferências do cliente
     */
    public function updatePreferences(string $id, array $preferences): array;

    /**
     * Obtém preferências do cliente
     */
    public function getPreferences(string $id): array;

    /**
     * Atualiza consentimentos LGPD
     */
    public function updateConsents(string $id, array $consents): array;

    /**
     * Obtém consentimentos LGPD
     */
    public function getConsents(string $id): array;

    /**
     * Marca cliente como anonimizado
     */
    public function anonymize(string $id): bool;

    /**
     * Exporta dados do cliente para compliance
     */
    public function exportData(string $id): array;

    /**
     * Remove dados pessoais (right to be forgotten)
     */
    public function removePersonalData(string $id): bool;

    /**
     * Obtém estatísticas de clientes
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Conta clientes por segmento
     */
    public function countBySegment(array $filters = []): array;

    /**
     * Conta clientes por localização
     */
    public function countByLocation(array $filters = []): array;

    /**
     * Conta clientes por período de cadastro
     */
    public function countByRegistrationPeriod(string $period = 'month'): array;

    /**
     * Obtém distribuição etária
     */
    public function getAgeDistribution(): array;

    /**
     * Obtém valor médio por cliente
     */
    public function getAverageCustomerValue(array $filters = []): float;

    /**
     * Obtém frequência média de compra
     */
    public function getAveragePurchaseFrequency(array $filters = []): float;

    /**
     * Obtém taxa de retenção
     */
    public function getRetentionRate(string $period = 'month'): float;

    /**
     * Obtém taxa de churn
     */
    public function getChurnRate(string $period = 'month'): float;

    /**
     * Obtém relatório de crescimento
     */
    public function getGrowthReport(string $period = 'month'): array;

    /**
     * Obtém clientes mais valiosos
     */
    public function getTopCustomers(int $limit = 10, string $metric = 'value'): array;

    /**
     * Busca clientes para remarketing
     */
    public function findForRemarketing(array $criteria = []): array;

    /**
     * Busca clientes para cross-sell
     */
    public function findForCrossSell(array $criteria = []): array;

    /**
     * Busca clientes para up-sell
     */
    public function findForUpSell(array $criteria = []): array;

    /**
     * Obtém recomendações para cliente
     */
    public function getRecommendations(string $customerId, string $type = 'products'): array;

    /**
     * Calcula lifetime value
     */
    public function calculateLifetimeValue(string $customerId): array;

    /**
     * Prediz próxima compra
     */
    public function predictNextPurchase(string $customerId): array;

    /**
     * Calcula probabilidade de churn
     */
    public function calculateChurnProbability(string $customerId): float;

    /**
     * Atualiza último acesso
     */
    public function updateLastAccess(string $id): array;

    /**
     * Registra login do cliente
     */
    public function recordLogin(string $customerId, array $loginData): array;

    /**
     * Obtém histórico de logins
     */
    public function getLoginHistory(string $customerId): array;

    /**
     * Limpa dados antigos
     */
    public function cleanupOldData(int $daysAgo = 365): int;

    /**
     * Arquiva clientes inativos
     */
    public function archiveInactive(int $daysAgo = 730): int;

    /**
     * Verifica duplicatas no sistema
     */
    public function detectDuplicates(array $criteria = []): array;

    /**
     * Consolida dados de cliente
     */
    public function consolidateCustomerData(string $customerId): array;

    /**
     * Sincroniza com sistemas externos
     */
    public function syncWithExternal(string $customerId, string $system, array $data): array;

    /**
     * Valida integridade dos dados
     */
    public function validateDataIntegrity(string $customerId): array;

    /**
     * Repara dados inconsistentes
     */
    public function repairInconsistentData(): int;
}