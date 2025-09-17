<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers\Repositories;

use ClubifyCheckout\Core\BaseRepository;
use ClubifyCheckout\Modules\Customers\Contracts\CustomerRepositoryInterface;
use ClubifyCheckout\Modules\Customers\Exceptions\CustomerNotFoundException;
use ClubifyCheckout\Modules\Customers\Exceptions\DuplicateCustomerException;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Implementação do Repository de Clientes
 *
 * Implementa todas as operações específicas de clientes,
 * incluindo CRUD, matching, histórico, perfis e compliance.
 *
 * Funcionalidades principais:
 * - CRUD completo de clientes
 * - Busca e matching inteligente
 * - Gestão de histórico e perfis
 * - Compliance LGPD/GDPR
 * - Análise comportamental
 * - Segmentação de clientes
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas persistência de clientes
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Substituível por outras implementações
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Implementa abstração
 */
class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    protected string $table = 'customers';

    public function __construct(
        array $config,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache = null
    ) {
        parent::__construct($config, $logger, $cache);
    }

    /**
     * Busca clientes por organização
     */
    public function findByOrganization(string $organizationId, array $filters = []): array
    {
        $query = $this->buildQuery(array_merge($filters, [
            'organization_id' => $organizationId
        ]));

        return $this->executeQuery($query);
    }

    /**
     * Busca cliente por email
     */
    public function findByEmail(string $email, string $organizationId = null): ?array
    {
        $conditions = ['email' => $email];

        if ($organizationId) {
            $conditions['organization_id'] = $organizationId;
        }

        $query = $this->buildQuery($conditions);
        $results = $this->executeQuery($query);

        return $results[0] ?? null;
    }

    /**
     * Busca cliente por documento
     */
    public function findByDocument(string $document, string $organizationId = null): ?array
    {
        $conditions = ['document' => $document];

        if ($organizationId) {
            $conditions['organization_id'] = $organizationId;
        }

        $query = $this->buildQuery($conditions);
        $results = $this->executeQuery($query);

        return $results[0] ?? null;
    }

    /**
     * Busca cliente por telefone
     */
    public function findByPhone(string $phone, string $organizationId = null): ?array
    {
        $conditions = ['phone' => $phone];

        if ($organizationId) {
            $conditions['organization_id'] = $organizationId;
        }

        $query = $this->buildQuery($conditions);
        $results = $this->executeQuery($query);

        return $results[0] ?? null;
    }

    /**
     * Busca clientes por nome
     */
    public function findByName(string $name, array $filters = []): array
    {
        $conditions = array_merge($filters, [
            'name' => ['$regex' => $name, '$options' => 'i']
        ]);

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por múltiplos critérios (matching)
     */
    public function findByCriteria(array $criteria): array
    {
        $query = $this->buildQuery($criteria);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes similares
     */
    public function findSimilar(array $customerData, float $threshold = 0.8): array
    {
        // Implementação simplificada - em produção usaria algoritmos de similarity
        $conditions = [];

        if (isset($customerData['email'])) {
            $conditions['email'] = $customerData['email'];
        }

        if (isset($customerData['document'])) {
            $conditions['document'] = $customerData['document'];
        }

        if (isset($customerData['phone'])) {
            $conditions['phone'] = $customerData['phone'];
        }

        if (empty($conditions)) {
            return [];
        }

        $query = $this->buildQuery(['$or' => array_map(fn($k, $v) => [$k => $v], array_keys($conditions), $conditions)]);
        return $this->executeQuery($query);
    }

    /**
     * Busca possíveis duplicatas
     */
    public function findPotentialDuplicates(string $customerId): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            return [];
        }

        return $this->findSimilar($customer);
    }

    /**
     * Busca clientes por localização
     */
    public function findByLocation(string $city, string $state = null, string $country = null): array
    {
        $conditions = ['address.city' => $city];

        if ($state) {
            $conditions['address.state'] = $state;
        }

        if ($country) {
            $conditions['address.country'] = $country;
        }

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por faixa etária
     */
    public function findByAgeRange(int $minAge, int $maxAge): array
    {
        $currentDate = new \DateTime();
        $maxBirthDate = clone $currentDate;
        $minBirthDate = clone $currentDate;

        $maxBirthDate->modify("-{$minAge} years");
        $minBirthDate->modify("-{$maxAge} years");

        $conditions = [
            'birth_date' => [
                '$gte' => $minBirthDate->format('Y-m-d'),
                '$lte' => $maxBirthDate->format('Y-m-d')
            ]
        ];

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por segmento
     */
    public function findBySegment(string $segment, array $filters = []): array
    {
        $conditions = array_merge($filters, [
            'segment' => $segment
        ]);

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por tag
     */
    public function findByTag(string $tag, array $filters = []): array
    {
        $conditions = array_merge($filters, [
            'tags' => ['$in' => [$tag]]
        ]);

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por valor de compra
     */
    public function findByPurchaseValue(float $minValue, float $maxValue = null): array
    {
        $conditions = [
            'total_spent' => ['$gte' => $minValue]
        ];

        if ($maxValue !== null) {
            $conditions['total_spent']['$lte'] = $maxValue;
        }

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por frequência de compra
     */
    public function findByPurchaseFrequency(int $minPurchases, int $maxPurchases = null): array
    {
        $conditions = [
            'total_orders' => ['$gte' => $minPurchases]
        ];

        if ($maxPurchases !== null) {
            $conditions['total_orders']['$lte'] = $maxPurchases;
        }

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes por última atividade
     */
    public function findByLastActivity(string $startDate, string $endDate = null): array
    {
        $conditions = [
            'last_activity_at' => ['$gte' => $startDate]
        ];

        if ($endDate) {
            $conditions['last_activity_at']['$lte'] = $endDate;
        }

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes inativos
     */
    public function findInactive(int $daysAgo = 90): array
    {
        $cutoffDate = new \DateTime();
        $cutoffDate->modify("-{$daysAgo} days");

        $conditions = [
            'last_activity_at' => ['$lt' => $cutoffDate->format('Y-m-d H:i:s')]
        ];

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes com risco de churn
     */
    public function findChurnRisk(array $criteria = []): array
    {
        $conditions = array_merge($criteria, [
            'churn_score' => ['$gte' => 0.7]
        ]);

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Busca clientes VIP/premium
     */
    public function findVipCustomers(array $criteria = []): array
    {
        $conditions = array_merge($criteria, [
            '$or' => [
                ['segment' => 'vip'],
                ['segment' => 'premium'],
                ['lifetime_value' => ['$gte' => 10000]]
            ]
        ]);

        $query = $this->buildQuery($conditions);
        return $this->executeQuery($query);
    }

    /**
     * Atualiza dados do cliente se houve mudanças
     */
    public function updateIfChanged(string $id, array $data): ?array
    {
        $currentCustomer = $this->findById($id);
        if (!$currentCustomer) {
            return null;
        }

        // Verifica se há mudanças significativas
        $fieldsToCheck = ['name', 'email', 'phone', 'address'];
        $hasChanges = false;

        foreach ($fieldsToCheck as $field) {
            if (isset($data[$field]) && $data[$field] !== ($currentCustomer[$field] ?? null)) {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return null;
        }

        // Atualiza apenas campos que mudaram
        $updateData = array_intersect_key($data, array_flip($fieldsToCheck));
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return $this->update($id, $updateData);
    }

    /**
     * Mescla dados de múltiplos clientes
     */
    public function mergeCustomers(string $primaryId, array $duplicateIds): array
    {
        $primaryCustomer = $this->findById($primaryId);
        if (!$primaryCustomer) {
            throw new CustomerNotFoundException("Cliente principal não encontrado: {$primaryId}");
        }

        $mergedData = $primaryCustomer;

        foreach ($duplicateIds as $duplicateId) {
            $duplicate = $this->findById($duplicateId);
            if (!$duplicate) {
                continue;
            }

            // Mescla dados importantes
            $mergedData['total_spent'] = ($mergedData['total_spent'] ?? 0) + ($duplicate['total_spent'] ?? 0);
            $mergedData['total_orders'] = ($mergedData['total_orders'] ?? 0) + ($duplicate['total_orders'] ?? 0);

            // Mescla tags
            $primaryTags = $mergedData['tags'] ?? [];
            $duplicateTags = $duplicate['tags'] ?? [];
            $mergedData['tags'] = array_unique(array_merge($primaryTags, $duplicateTags));

            // Mescla transações (implementação simplificada)
            $primaryTransactions = $mergedData['transactions'] ?? [];
            $duplicateTransactions = $duplicate['transactions'] ?? [];
            $mergedData['transactions'] = array_merge($primaryTransactions, $duplicateTransactions);

            // Remove cliente duplicado
            $this->delete($duplicateId);
        }

        // Atualiza cliente principal
        return $this->update($primaryId, $mergedData);
    }

    /**
     * Adiciona tag ao cliente
     */
    public function addTag(string $id, string $tag): array
    {
        $customer = $this->findById($id);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$id}");
        }

        $tags = $customer['tags'] ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
        }

        return $this->update($id, ['tags' => $tags]);
    }

    /**
     * Remove tag do cliente
     */
    public function removeTag(string $id, string $tag): array
    {
        $customer = $this->findById($id);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$id}");
        }

        $tags = $customer['tags'] ?? [];
        $tags = array_filter($tags, fn($t) => $t !== $tag);

        return $this->update($id, ['tags' => array_values($tags)]);
    }

    /**
     * Obtém tags do cliente
     */
    public function getTags(string $id): array
    {
        $customer = $this->findById($id);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$id}");
        }

        return $customer['tags'] ?? [];
    }

    /**
     * Verifica se cliente tem tag
     */
    public function hasTag(string $id, string $tag): bool
    {
        $tags = $this->getTags($id);
        return in_array($tag, $tags);
    }

    /**
     * Adiciona transação ao histórico
     */
    public function addTransaction(string $customerId, array $transactionData): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
        }

        $transactions = $customer['transactions'] ?? [];
        $transactions[] = array_merge($transactionData, [
            'id' => uniqid('txn_'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Atualiza totais
        $totalSpent = ($customer['total_spent'] ?? 0) + ($transactionData['amount'] ?? 0);
        $totalOrders = ($customer['total_orders'] ?? 0) + 1;

        return $this->update($customerId, [
            'transactions' => $transactions,
            'total_spent' => $totalSpent,
            'total_orders' => $totalOrders,
            'last_activity_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Obtém histórico de transações
     */
    public function getTransactionHistory(string $customerId, array $filters = []): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
        }

        $transactions = $customer['transactions'] ?? [];

        // Aplica filtros se necessário
        if (!empty($filters)) {
            $transactions = array_filter($transactions, function($transaction) use ($filters) {
                foreach ($filters as $key => $value) {
                    if (isset($transaction[$key]) && $transaction[$key] !== $value) {
                        return false;
                    }
                }
                return true;
            });
        }

        return array_values($transactions);
    }

    /**
     * Implementação simplificada dos métodos restantes
     * Em produção, estes métodos teriam implementações completas
     */

    public function updateBehaviorProfile(string $id, array $profileData): array
    {
        return $this->update($id, ['behavior_profile' => $profileData]);
    }

    public function getBehaviorProfile(string $id): ?array
    {
        $customer = $this->findById($id);
        return $customer['behavior_profile'] ?? null;
    }

    public function calculateCustomerMetrics(string $id): array
    {
        $customer = $this->findById($id);
        if (!$customer) {
            return [];
        }

        return [
            'total_spent' => $customer['total_spent'] ?? 0,
            'total_orders' => $customer['total_orders'] ?? 0,
            'average_order_value' => $customer['total_orders'] > 0 ?
                ($customer['total_spent'] / $customer['total_orders']) : 0,
            'lifetime_value' => $customer['lifetime_value'] ?? 0,
            'churn_score' => $customer['churn_score'] ?? 0
        ];
    }

    public function updateCustomerScore(string $id, float $score, array $factors = []): array
    {
        return $this->update($id, [
            'customer_score' => $score,
            'score_factors' => $factors,
            'score_updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function findByScore(float $minScore, float $maxScore = null): array
    {
        $conditions = ['customer_score' => ['$gte' => $minScore]];

        if ($maxScore !== null) {
            $conditions['customer_score']['$lte'] = $maxScore;
        }

        return $this->findByFilters($conditions);
    }

    public function recordEvent(string $customerId, array $eventData): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
        }

        $events = $customer['events'] ?? [];
        $events[] = array_merge($eventData, [
            'id' => uniqid('evt_'),
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        return $this->update($customerId, ['events' => $events]);
    }

    public function getEvents(string $customerId, array $filters = []): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            return [];
        }

        return $customer['events'] ?? [];
    }

    public function addNote(string $customerId, string $note, string $author = null): array
    {
        $customer = $this->findById($customerId);
        if (!$customer) {
            throw new CustomerNotFoundException("Cliente não encontrado: {$customerId}");
        }

        $notes = $customer['notes'] ?? [];
        $notes[] = [
            'id' => uniqid('note_'),
            'content' => $note,
            'author' => $author,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->update($customerId, ['notes' => $notes]);
    }

    public function getNotes(string $customerId): array
    {
        $customer = $this->findById($customerId);
        return $customer['notes'] ?? [];
    }

    public function updatePreferences(string $id, array $preferences): array
    {
        return $this->update($id, ['preferences' => $preferences]);
    }

    public function getPreferences(string $id): array
    {
        $customer = $this->findById($id);
        return $customer['preferences'] ?? [];
    }

    public function updateConsents(string $id, array $consents): array
    {
        return $this->update($id, [
            'consents' => $consents,
            'consents_updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    public function getConsents(string $id): array
    {
        $customer = $this->findById($id);
        return $customer['consents'] ?? [];
    }

    public function anonymize(string $id): bool
    {
        $anonymizedData = [
            'name' => 'ANONIMIZADO',
            'email' => 'anonimizado@example.com',
            'phone' => null,
            'document' => null,
            'address' => null,
            'anonymized' => true,
            'anonymized_at' => date('Y-m-d H:i:s')
        ];

        $result = $this->update($id, $anonymizedData);
        return !empty($result);
    }

    public function exportData(string $id): array
    {
        return $this->findById($id) ?? [];
    }

    public function removePersonalData(string $id): bool
    {
        return $this->delete($id);
    }

    public function getStatistics(array $filters = []): array
    {
        return [
            'total_customers' => $this->count($filters),
            'active_customers' => $this->count(array_merge($filters, ['status' => 'active'])),
            'inactive_customers' => $this->count(array_merge($filters, ['status' => 'inactive']))
        ];
    }

    // Implementações simplificadas dos métodos restantes...
    // Em uma implementação completa, cada método teria sua lógica específica

    public function countBySegment(array $filters = []): array { return []; }
    public function countByLocation(array $filters = []): array { return []; }
    public function countByRegistrationPeriod(string $period = 'month'): array { return []; }
    public function getAgeDistribution(): array { return []; }
    public function getAverageCustomerValue(array $filters = []): float { return 0.0; }
    public function getAveragePurchaseFrequency(array $filters = []): float { return 0.0; }
    public function getRetentionRate(string $period = 'month'): float { return 0.0; }
    public function getChurnRate(string $period = 'month'): float { return 0.0; }
    public function getGrowthReport(string $period = 'month'): array { return []; }
    public function getTopCustomers(int $limit = 10, string $metric = 'value'): array { return []; }
    public function findForRemarketing(array $criteria = []): array { return []; }
    public function findForCrossSell(array $criteria = []): array { return []; }
    public function findForUpSell(array $criteria = []): array { return []; }
    public function getRecommendations(string $customerId, string $type = 'products'): array { return []; }
    public function calculateLifetimeValue(string $customerId): array { return []; }
    public function predictNextPurchase(string $customerId): array { return []; }
    public function calculateChurnProbability(string $customerId): float { return 0.0; }
    public function updateLastAccess(string $id): array { return $this->update($id, ['last_access_at' => date('Y-m-d H:i:s')]); }
    public function recordLogin(string $customerId, array $loginData): array { return []; }
    public function getLoginHistory(string $customerId): array { return []; }
    public function cleanupOldData(int $daysAgo = 365): int { return 0; }
    public function archiveInactive(int $daysAgo = 730): int { return 0; }
    public function detectDuplicates(array $criteria = []): array { return []; }
    public function consolidateCustomerData(string $customerId): array { return []; }
    public function syncWithExternal(string $customerId, string $system, array $data): array { return []; }
    public function validateDataIntegrity(string $customerId): array { return []; }
    public function repairInconsistentData(): int { return 0; }
}