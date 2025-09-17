<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Customers\Services;

use Clubify\Checkout\Core\BaseService;
use Clubify\Checkout\Modules\Customers\Contracts\CustomerRepositoryInterface;
use Clubify\Checkout\Modules\Customers\Exceptions\CustomerException;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Serviço de matching inteligente de clientes
 *
 * Implementa algoritmos avançados para identificar,
 * combinar e deduplificar clientes baseado em
 * múltiplos critérios e scoring de similaridade.
 *
 * Funcionalidades principais:
 * - Matching por múltiplos critérios
 * - Scoring de similaridade avançado
 * - Deduplicação automática
 * - Merge inteligente de dados
 * - Detecção de fraudes
 * - Analytics de matching
 *
 * Algoritmos implementados:
 * - Exact matching (email, documento, telefone)
 * - Fuzzy matching (nome, endereço)
 * - Levenshtein distance para strings
 * - Soundex para similaridade fonética
 * - Scoring ponderado multi-critério
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas matching de clientes
 * - O: Open/Closed - Extensível via algoritmos
 * - L: Liskov Substitution - Substituível por outras implementações
 * - I: Interface Segregation - Interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class MatchingService extends BaseService
{
    private array $matchingConfig = [
        'thresholds' => [
            'exact_match' => 1.0,
            'high_confidence' => 0.9,
            'medium_confidence' => 0.7,
            'low_confidence' => 0.5,
            'minimum_match' => 0.3,
        ],
        'weights' => [
            'email' => 0.4,
            'document' => 0.3,
            'phone' => 0.2,
            'name' => 0.1,
            'address' => 0.05,
        ],
        'fuzzy_matching' => [
            'enabled' => true,
            'name_threshold' => 0.8,
            'address_threshold' => 0.7,
        ],
    ];

    public function __construct(
        private CustomerRepositoryInterface $repository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache = null
    ) {
        parent::__construct($logger, $cache);
    }

    /**
     * Encontra melhor match para um cliente
     */
    public function findBestMatch(array $customerData): ?array
    {
        return $this->executeWithMetrics('findBestMatch', function () use ($customerData) {
            $candidates = $this->findCandidates($customerData);

            if (empty($candidates)) {
                return null;
            }

            $scoredCandidates = [];
            foreach ($candidates as $candidate) {
                $score = $this->calculateSimilarityScore($customerData, $candidate);
                if ($score >= $this->matchingConfig['thresholds']['minimum_match']) {
                    $scoredCandidates[] = [
                        'customer' => $candidate,
                        'score' => $score,
                        'confidence' => $this->getConfidenceLevel($score),
                    ];
                }
            }

            if (empty($scoredCandidates)) {
                return null;
            }

            // Ordena por score decrescente
            usort($scoredCandidates, fn($a, $b) => $b['score'] <=> $a['score']);

            $bestMatch = $scoredCandidates[0];

            $this->logger->info('Melhor match encontrado', [
                'customer_id' => $bestMatch['customer']['id'],
                'score' => $bestMatch['score'],
                'confidence' => $bestMatch['confidence'],
                'criteria_count' => count($customerData),
            ]);

            return $bestMatch['customer'];
        });
    }

    /**
     * Encontra todos os matches possíveis
     */
    public function findAllMatches(array $customerData, float $threshold = null): array
    {
        return $this->executeWithMetrics('findAllMatches', function () use ($customerData, $threshold) {
            $threshold = $threshold ?? $this->matchingConfig['thresholds']['minimum_match'];

            $candidates = $this->findCandidates($customerData);
            $matches = [];

            foreach ($candidates as $candidate) {
                $score = $this->calculateSimilarityScore($customerData, $candidate);

                if ($score >= $threshold) {
                    $matches[] = [
                        'customer' => $candidate,
                        'score' => $score,
                        'confidence' => $this->getConfidenceLevel($score),
                        'matching_criteria' => $this->getMatchingCriteria($customerData, $candidate),
                    ];
                }
            }

            // Ordena por score decrescente
            usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

            return $matches;
        });
    }

    /**
     * Detecta duplicatas no sistema
     */
    public function findDuplicates(array $criteria = []): array
    {
        return $this->executeWithMetrics('findDuplicates', function () use ($criteria) {
            $duplicateGroups = [];
            $processedIds = [];

            // Busca todos os clientes ou filtra por critérios
            $customers = empty($criteria)
                ? $this->repository->findAll()
                : $this->repository->findByFilters($criteria);

            foreach ($customers as $customer) {
                if (in_array($customer['id'], $processedIds)) {
                    continue;
                }

                $duplicates = $this->findDuplicatesForCustomer($customer['id']);

                if (!empty($duplicates)) {
                    $group = [$customer];
                    $groupIds = [$customer['id']];

                    foreach ($duplicates as $duplicate) {
                        if (!in_array($duplicate['customer']['id'], $processedIds)) {
                            $group[] = $duplicate['customer'];
                            $groupIds[] = $duplicate['customer']['id'];
                        }
                    }

                    if (count($group) > 1) {
                        $duplicateGroups[] = [
                            'primary' => $customer,
                            'duplicates' => array_slice($group, 1),
                            'count' => count($group),
                            'total_score' => array_sum(array_column($duplicates, 'score')),
                        ];

                        $processedIds = array_merge($processedIds, $groupIds);
                    }
                }

                $processedIds[] = $customer['id'];
            }

            $this->logger->info('Duplicatas detectadas', [
                'total_groups' => count($duplicateGroups),
                'total_duplicates' => array_sum(array_column($duplicateGroups, 'count')),
            ]);

            return $duplicateGroups;
        });
    }

    /**
     * Mescla clientes duplicados
     */
    public function mergeCustomers(string $primaryCustomerId, array $duplicateCustomerIds): array
    {
        return $this->executeWithMetrics('mergeCustomers', function () use ($primaryCustomerId, $duplicateCustomerIds) {
            $primaryCustomer = $this->repository->findById($primaryCustomerId);
            if (!$primaryCustomer) {
                throw new CustomerException("Cliente principal não encontrado: {$primaryCustomerId}");
            }

            $duplicateCustomers = [];
            foreach ($duplicateCustomerIds as $duplicateId) {
                $duplicate = $this->repository->findById($duplicateId);
                if ($duplicate) {
                    $duplicateCustomers[] = $duplicate;
                }
            }

            if (empty($duplicateCustomers)) {
                throw new CustomerException("Nenhum cliente duplicado válido encontrado");
            }

            // Calcula dados mesclados
            $mergedData = $this->calculateMergedData($primaryCustomer, $duplicateCustomers);

            // Executa merge no repositório
            $result = $this->repository->mergeCustomers($primaryCustomerId, $duplicateCustomerIds);

            // Atualiza com dados calculados
            if (!empty($mergedData)) {
                $result = $this->repository->update($primaryCustomerId, $mergedData);
            }

            // Dispara evento
            $this->dispatchEvent('customers.merged', [
                'primary_customer_id' => $primaryCustomerId,
                'duplicate_customer_ids' => $duplicateCustomerIds,
                'merged_data' => $mergedData,
            ]);

            $this->logger->info('Clientes mesclados com sucesso', [
                'primary_customer_id' => $primaryCustomerId,
                'duplicates_count' => count($duplicateCustomerIds),
                'merged_fields' => array_keys($mergedData),
            ]);

            return $result;
        });
    }

    /**
     * Calcula score de similaridade entre dois clientes
     */
    public function calculateSimilarityScore(array $customer1, array $customer2): float
    {
        $totalScore = 0.0;
        $totalWeight = 0.0;

        // Email (exact match)
        if (isset($customer1['email']) && isset($customer2['email'])) {
            $emailScore = $this->compareEmails($customer1['email'], $customer2['email']);
            $totalScore += $emailScore * $this->matchingConfig['weights']['email'];
            $totalWeight += $this->matchingConfig['weights']['email'];
        }

        // Documento (exact match)
        if (isset($customer1['document']) && isset($customer2['document'])) {
            $documentScore = $this->compareDocuments($customer1['document'], $customer2['document']);
            $totalScore += $documentScore * $this->matchingConfig['weights']['document'];
            $totalWeight += $this->matchingConfig['weights']['document'];
        }

        // Telefone (exact e fuzzy match)
        if (isset($customer1['phone']) && isset($customer2['phone'])) {
            $phoneScore = $this->comparePhones($customer1['phone'], $customer2['phone']);
            $totalScore += $phoneScore * $this->matchingConfig['weights']['phone'];
            $totalWeight += $this->matchingConfig['weights']['phone'];
        }

        // Nome (fuzzy match)
        if (isset($customer1['name']) && isset($customer2['name'])) {
            $nameScore = $this->compareNames($customer1['name'], $customer2['name']);
            $totalScore += $nameScore * $this->matchingConfig['weights']['name'];
            $totalWeight += $this->matchingConfig['weights']['name'];
        }

        // Endereço (fuzzy match)
        if (isset($customer1['address']) && isset($customer2['address'])) {
            $addressScore = $this->compareAddresses($customer1['address'], $customer2['address']);
            $totalScore += $addressScore * $this->matchingConfig['weights']['address'];
            $totalWeight += $this->matchingConfig['weights']['address'];
        }

        return $totalWeight > 0 ? $totalScore / $totalWeight : 0.0;
    }

    /**
     * Busca candidatos para matching
     */
    private function findCandidates(array $customerData): array
    {
        $candidates = [];

        // Busca por email exato
        if (isset($customerData['email'])) {
            $emailMatch = $this->repository->findByEmail($customerData['email']);
            if ($emailMatch) {
                $candidates[$emailMatch['id']] = $emailMatch;
            }
        }

        // Busca por documento exato
        if (isset($customerData['document'])) {
            $documentMatch = $this->repository->findByDocument($customerData['document']);
            if ($documentMatch) {
                $candidates[$documentMatch['id']] = $documentMatch;
            }
        }

        // Busca por telefone exato
        if (isset($customerData['phone'])) {
            $phoneMatch = $this->repository->findByPhone($customerData['phone']);
            if ($phoneMatch) {
                $candidates[$phoneMatch['id']] = $phoneMatch;
            }
        }

        // Busca fuzzy por nome (se habilitado)
        if ($this->matchingConfig['fuzzy_matching']['enabled'] && isset($customerData['name'])) {
            $nameMatches = $this->repository->findByName($customerData['name']);
            foreach ($nameMatches as $match) {
                $candidates[$match['id']] = $match;
            }
        }

        return array_values($candidates);
    }

    /**
     * Encontra duplicatas para um cliente específico
     */
    private function findDuplicatesForCustomer(string $customerId): array
    {
        $customer = $this->repository->findById($customerId);
        if (!$customer) {
            return [];
        }

        $potentialDuplicates = $this->repository->findPotentialDuplicates($customerId);
        $duplicates = [];

        foreach ($potentialDuplicates as $candidate) {
            $score = $this->calculateSimilarityScore($customer, $candidate);

            if ($score >= $this->matchingConfig['thresholds']['low_confidence']) {
                $duplicates[] = [
                    'customer' => $candidate,
                    'score' => $score,
                    'confidence' => $this->getConfidenceLevel($score),
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Compara emails
     */
    private function compareEmails(string $email1, string $email2): float
    {
        return strtolower(trim($email1)) === strtolower(trim($email2)) ? 1.0 : 0.0;
    }

    /**
     * Compara documentos
     */
    private function compareDocuments(string $doc1, string $doc2): float
    {
        $cleanDoc1 = preg_replace('/\D/', '', $doc1);
        $cleanDoc2 = preg_replace('/\D/', '', $doc2);

        return $cleanDoc1 === $cleanDoc2 ? 1.0 : 0.0;
    }

    /**
     * Compara telefones
     */
    private function comparePhones(string $phone1, string $phone2): float
    {
        $cleanPhone1 = preg_replace('/\D/', '', $phone1);
        $cleanPhone2 = preg_replace('/\D/', '', $phone2);

        // Match exato
        if ($cleanPhone1 === $cleanPhone2) {
            return 1.0;
        }

        // Match dos últimos 9 dígitos (para números com/sem DDD)
        if (strlen($cleanPhone1) >= 9 && strlen($cleanPhone2) >= 9) {
            $suffix1 = substr($cleanPhone1, -9);
            $suffix2 = substr($cleanPhone2, -9);

            if ($suffix1 === $suffix2) {
                return 0.9;
            }
        }

        // Match dos últimos 8 dígitos (para números fixos)
        if (strlen($cleanPhone1) >= 8 && strlen($cleanPhone2) >= 8) {
            $suffix1 = substr($cleanPhone1, -8);
            $suffix2 = substr($cleanPhone2, -8);

            if ($suffix1 === $suffix2) {
                return 0.8;
            }
        }

        return 0.0;
    }

    /**
     * Compara nomes usando fuzzy matching
     */
    private function compareNames(string $name1, string $name2): float
    {
        $name1 = $this->normalizeName($name1);
        $name2 = $this->normalizeName($name2);

        // Match exato
        if ($name1 === $name2) {
            return 1.0;
        }

        // Levenshtein distance
        $levenshteinScore = $this->calculateLevenshteinSimilarity($name1, $name2);

        // Soundex para similaridade fonética
        $soundexScore = soundex($name1) === soundex($name2) ? 0.8 : 0.0;

        // Comparação de palavras individuais
        $wordsScore = $this->compareNameWords($name1, $name2);

        // Retorna o maior score
        return max($levenshteinScore, $soundexScore, $wordsScore);
    }

    /**
     * Compara endereços
     */
    private function compareAddresses(array $address1, array $address2): float
    {
        $scores = [];

        // Compara CEP
        if (isset($address1['zip_code']) && isset($address2['zip_code'])) {
            $zipScore = $this->compareZipCodes($address1['zip_code'], $address2['zip_code']);
            $scores[] = $zipScore * 0.4;
        }

        // Compara rua
        if (isset($address1['street']) && isset($address2['street'])) {
            $streetScore = $this->calculateLevenshteinSimilarity(
                $this->normalizeText($address1['street']),
                $this->normalizeText($address2['street'])
            );
            $scores[] = $streetScore * 0.3;
        }

        // Compara cidade
        if (isset($address1['city']) && isset($address2['city'])) {
            $cityScore = $this->calculateLevenshteinSimilarity(
                $this->normalizeText($address1['city']),
                $this->normalizeText($address2['city'])
            );
            $scores[] = $cityScore * 0.2;
        }

        // Compara número
        if (isset($address1['number']) && isset($address2['number'])) {
            $numberScore = $address1['number'] === $address2['number'] ? 1.0 : 0.0;
            $scores[] = $numberScore * 0.1;
        }

        return empty($scores) ? 0.0 : array_sum($scores);
    }

    /**
     * Compara CEPs
     */
    private function compareZipCodes(string $zip1, string $zip2): float
    {
        $cleanZip1 = preg_replace('/\D/', '', $zip1);
        $cleanZip2 = preg_replace('/\D/', '', $zip2);

        if ($cleanZip1 === $cleanZip2) {
            return 1.0;
        }

        // Compara primeiros 5 dígitos (região)
        if (strlen($cleanZip1) >= 5 && strlen($cleanZip2) >= 5) {
            $prefix1 = substr($cleanZip1, 0, 5);
            $prefix2 = substr($cleanZip2, 0, 5);

            if ($prefix1 === $prefix2) {
                return 0.7;
            }
        }

        return 0.0;
    }

    /**
     * Normaliza nome para comparação
     */
    private function normalizeName(string $name): string
    {
        $name = $this->normalizeText($name);

        // Remove títulos comuns
        $titles = ['mr', 'mrs', 'ms', 'dr', 'prof', 'sr', 'sra', 'srta'];
        $words = explode(' ', $name);
        $words = array_filter($words, fn($word) => !in_array($word, $titles));

        return implode(' ', $words);
    }

    /**
     * Normaliza texto para comparação
     */
    private function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return $text;
    }

    /**
     * Calcula similaridade usando Levenshtein distance
     */
    private function calculateLevenshteinSimilarity(string $str1, string $str2): float
    {
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen === 0) {
            return 1.0;
        }

        $distance = levenshtein($str1, $str2);
        return 1.0 - ($distance / $maxLen);
    }

    /**
     * Compara palavras individuais dos nomes
     */
    private function compareNameWords(string $name1, string $name2): float
    {
        $words1 = explode(' ', $name1);
        $words2 = explode(' ', $name2);

        $matches = 0;
        $totalWords = max(count($words1), count($words2));

        foreach ($words1 as $word1) {
            foreach ($words2 as $word2) {
                if ($word1 === $word2 && strlen($word1) > 2) {
                    $matches++;
                    break;
                }
            }
        }

        return $totalWords > 0 ? $matches / $totalWords : 0.0;
    }

    /**
     * Obtém nível de confiança baseado no score
     */
    private function getConfidenceLevel(float $score): string
    {
        if ($score >= $this->matchingConfig['thresholds']['exact_match']) {
            return 'exact';
        } elseif ($score >= $this->matchingConfig['thresholds']['high_confidence']) {
            return 'high';
        } elseif ($score >= $this->matchingConfig['thresholds']['medium_confidence']) {
            return 'medium';
        } elseif ($score >= $this->matchingConfig['thresholds']['low_confidence']) {
            return 'low';
        } else {
            return 'very_low';
        }
    }

    /**
     * Obtém critérios que fizeram match
     */
    private function getMatchingCriteria(array $customer1, array $customer2): array
    {
        $criteria = [];

        if (isset($customer1['email']) && isset($customer2['email'])) {
            if ($this->compareEmails($customer1['email'], $customer2['email']) > 0) {
                $criteria[] = 'email';
            }
        }

        if (isset($customer1['document']) && isset($customer2['document'])) {
            if ($this->compareDocuments($customer1['document'], $customer2['document']) > 0) {
                $criteria[] = 'document';
            }
        }

        if (isset($customer1['phone']) && isset($customer2['phone'])) {
            if ($this->comparePhones($customer1['phone'], $customer2['phone']) > 0) {
                $criteria[] = 'phone';
            }
        }

        if (isset($customer1['name']) && isset($customer2['name'])) {
            if ($this->compareNames($customer1['name'], $customer2['name']) >= 0.7) {
                $criteria[] = 'name';
            }
        }

        return $criteria;
    }

    /**
     * Calcula dados mesclados de múltiplos clientes
     */
    private function calculateMergedData(array $primary, array $duplicates): array
    {
        $merged = [];

        // Soma valores financeiros
        $totalSpent = $primary['total_spent'] ?? 0.0;
        $totalOrders = $primary['total_orders'] ?? 0;

        foreach ($duplicates as $duplicate) {
            $totalSpent += $duplicate['total_spent'] ?? 0.0;
            $totalOrders += $duplicate['total_orders'] ?? 0;
        }

        $merged['total_spent'] = $totalSpent;
        $merged['total_orders'] = $totalOrders;

        // Mescla tags
        $allTags = $primary['tags'] ?? [];
        foreach ($duplicates as $duplicate) {
            $allTags = array_merge($allTags, $duplicate['tags'] ?? []);
        }
        $merged['tags'] = array_unique($allTags);

        // Usa data de criação mais antiga
        $oldestDate = $primary['created_at'] ?? date('Y-m-d H:i:s');
        foreach ($duplicates as $duplicate) {
            $duplicateDate = $duplicate['created_at'] ?? date('Y-m-d H:i:s');
            if (strtotime($duplicateDate) < strtotime($oldestDate)) {
                $oldestDate = $duplicateDate;
            }
        }
        $merged['created_at'] = $oldestDate;

        return $merged;
    }
}