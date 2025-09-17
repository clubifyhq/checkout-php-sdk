<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Customers;

use ClubifyCheckout\Core\BaseModule;
use ClubifyCheckout\Modules\Customers\Services\CustomerService;
use ClubifyCheckout\Modules\Customers\Services\MatchingService;
use ClubifyCheckout\Modules\Customers\Services\HistoryService;
use ClubifyCheckout\Modules\Customers\Services\ProfileService;
use ClubifyCheckout\Modules\Customers\Contracts\CustomerRepositoryInterface;
use ClubifyCheckout\Modules\Customers\DTOs\CustomerData;
use ClubifyCheckout\Modules\Customers\DTOs\HistoryData;
use ClubifyCheckout\Modules\Customers\DTOs\ProfileData;
use ClubifyCheckout\Modules\Customers\Exceptions\CustomerException;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Módulo de gestão de clientes
 *
 * Centraliza todas as operações relacionadas a clientes,
 * incluindo CRUD, matching, histórico e perfis de comportamento.
 *
 * Funcionalidades principais:
 * - Gestão completa de clientes (CRUD)
 * - Matching inteligente de clientes
 * - Histórico de compras e transações
 * - Perfis de comportamento e segmentação
 * - Análise de valor do cliente (CLV)
 * - Gestão de tags e metadados
 * - Compliance LGPD/GDPR
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Apenas gestão de clientes
 * - O: Open/Closed - Extensível via implementações
 * - L: Liskov Substitution - Módulos intercambiáveis
 * - I: Interface Segregation - Separação de responsabilidades
 * - D: Dependency Inversion - Depende de abstrações
 */
class CustomersModule extends BaseModule
{
    private ?CustomerService $customerService = null;
    private ?MatchingService $matchingService = null;
    private ?HistoryService $historyService = null;
    private ?ProfileService $profileService = null;

    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger,
        CacheItemPoolInterface $cache,
        array $config = []
    ) {
        parent::__construct($logger, $cache, $config);
    }

    /**
     * Obtém nome do módulo
     */
    public function getName(): string
    {
        return 'customers';
    }

    /**
     * Obtém versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Inicializa o módulo
     */
    public function initialize(): void
    {
        $this->logger->info('Inicializando módulo de clientes', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
        ]);

        // Validar configurações necessárias
        $this->validateConfiguration();

        // Inicializar serviços conforme necessário
        $this->initializeServices();

        $this->initialized = true;
    }

    /**
     * Obtém serviço de clientes (lazy loading)
     */
    public function customers(): CustomerService
    {
        if (!$this->customerService) {
            $this->customerService = new CustomerService(
                $this->customerRepository,
                $this->logger,
                $this->cache
            );
        }

        return $this->customerService;
    }

    /**
     * Obtém serviço de matching (lazy loading)
     */
    public function matching(): MatchingService
    {
        if (!$this->matchingService) {
            $this->matchingService = new MatchingService(
                $this->customerRepository,
                $this->logger,
                $this->cache
            );
        }

        return $this->matchingService;
    }

    /**
     * Obtém serviço de histórico (lazy loading)
     */
    public function history(): HistoryService
    {
        if (!$this->historyService) {
            $this->historyService = new HistoryService(
                $this->customerRepository,
                $this->logger,
                $this->cache
            );
        }

        return $this->historyService;
    }

    /**
     * Obtém serviço de perfis (lazy loading)
     */
    public function profiles(): ProfileService
    {
        if (!$this->profileService) {
            $this->profileService = new ProfileService(
                $this->customerRepository,
                $this->logger,
                $this->cache
            );
        }

        return $this->profileService;
    }

    /**
     * Cria novo cliente
     */
    public function createCustomer(array $customerData): array
    {
        $this->ensureInitialized();

        $customerDto = new CustomerData($customerData);
        return $this->customers()->create($customerDto->toArray());
    }

    /**
     * Obtém cliente por ID
     */
    public function getCustomer(string $customerId): ?array
    {
        $this->ensureInitialized();

        return $this->customers()->findById($customerId);
    }

    /**
     * Atualiza dados do cliente
     */
    public function updateCustomer(string $customerId, array $updateData): array
    {
        $this->ensureInitialized();

        return $this->customers()->update($customerId, $updateData);
    }

    /**
     * Remove cliente
     */
    public function deleteCustomer(string $customerId): bool
    {
        $this->ensureInitialized();

        return $this->customers()->delete($customerId);
    }

    /**
     * Lista clientes com filtros
     */
    public function listCustomers(array $filters = []): array
    {
        $this->ensureInitialized();

        return $this->customers()->findByFilters($filters);
    }

    /**
     * Busca ou cria cliente baseado em dados
     */
    public function findOrCreateCustomer(array $customerData): array
    {
        $this->ensureInitialized();

        // Tenta fazer matching primeiro
        $existingCustomer = $this->matching()->findBestMatch($customerData);

        if ($existingCustomer) {
            // Atualiza dados se necessário
            $updatedCustomer = $this->customers()->updateIfChanged(
                $existingCustomer['id'],
                $customerData
            );

            return $updatedCustomer ?: $existingCustomer;
        }

        // Cria novo cliente
        return $this->createCustomer($customerData);
    }

    /**
     * Obtém histórico de compras do cliente
     */
    public function getCustomerHistory(string $customerId, array $filters = []): array
    {
        $this->ensureInitialized();

        return $this->history()->getCustomerHistory($customerId, $filters);
    }

    /**
     * Adiciona transação ao histórico
     */
    public function addTransactionToHistory(string $customerId, array $transactionData): array
    {
        $this->ensureInitialized();

        $historyDto = new HistoryData($transactionData);
        return $this->history()->addTransaction($customerId, $historyDto->toArray());
    }

    /**
     * Obtém perfil do cliente
     */
    public function getCustomerProfile(string $customerId): ?array
    {
        $this->ensureInitialized();

        return $this->profiles()->getProfile($customerId);
    }

    /**
     * Atualiza perfil do cliente
     */
    public function updateCustomerProfile(string $customerId, array $profileData): array
    {
        $this->ensureInitialized();

        $profileDto = new ProfileData($profileData);
        return $this->profiles()->updateProfile($customerId, $profileDto->toArray());
    }

    /**
     * Calcula valor do tempo de vida do cliente (CLV)
     */
    public function calculateCustomerLifetimeValue(string $customerId): array
    {
        $this->ensureInitialized();

        return $this->profiles()->calculateLifetimeValue($customerId);
    }

    /**
     * Segmenta clientes baseado em critérios
     */
    public function segmentCustomers(array $criteria): array
    {
        $this->ensureInitialized();

        return $this->profiles()->segmentCustomers($criteria);
    }

    /**
     * Adiciona tag ao cliente
     */
    public function addCustomerTag(string $customerId, string $tag): array
    {
        $this->ensureInitialized();

        return $this->customers()->addTag($customerId, $tag);
    }

    /**
     * Remove tag do cliente
     */
    public function removeCustomerTag(string $customerId, string $tag): array
    {
        $this->ensureInitialized();

        return $this->customers()->removeTag($customerId, $tag);
    }

    /**
     * Busca clientes por tag
     */
    public function findCustomersByTag(string $tag): array
    {
        $this->ensureInitialized();

        return $this->customers()->findByTag($tag);
    }

    /**
     * Obtém estatísticas de clientes
     */
    public function getCustomerStatistics(array $filters = []): array
    {
        $this->ensureInitialized();

        return $this->customers()->getStatistics($filters);
    }

    /**
     * Exporta dados do cliente (compliance LGPD)
     */
    public function exportCustomerData(string $customerId): array
    {
        $this->ensureInitialized();

        return $this->customers()->exportData($customerId);
    }

    /**
     * Anonimiza dados do cliente (compliance LGPD)
     */
    public function anonymizeCustomerData(string $customerId): bool
    {
        $this->ensureInitialized();

        return $this->customers()->anonymizeData($customerId);
    }

    /**
     * Busca clientes duplicados
     */
    public function findDuplicateCustomers(array $criteria = []): array
    {
        $this->ensureInitialized();

        return $this->matching()->findDuplicates($criteria);
    }

    /**
     * Mescla clientes duplicados
     */
    public function mergeCustomers(string $primaryCustomerId, array $duplicateCustomerIds): array
    {
        $this->ensureInitialized();

        return $this->matching()->mergeCustomers($primaryCustomerId, $duplicateCustomerIds);
    }

    /**
     * Obtém análise de comportamento do cliente
     */
    public function getCustomerBehaviorAnalysis(string $customerId): array
    {
        $this->ensureInitialized();

        return $this->profiles()->getBehaviorAnalysis($customerId);
    }

    /**
     * Prediz próxima compra do cliente
     */
    public function predictNextPurchase(string $customerId): array
    {
        $this->ensureInitialized();

        return $this->profiles()->predictNextPurchase($customerId);
    }

    /**
     * Obtém recomendações para o cliente
     */
    public function getCustomerRecommendations(string $customerId, array $options = []): array
    {
        $this->ensureInitialized();

        return $this->profiles()->getRecommendations($customerId, $options);
    }

    /**
     * Valida configurações do módulo
     */
    private function validateConfiguration(): void
    {
        $required = ['organization_id'];

        foreach ($required as $field) {
            if (!isset($this->config[$field])) {
                throw new CustomerException("Configuração obrigatória ausente: {$field}");
            }
        }
    }

    /**
     * Inicializa serviços necessários
     */
    private function initializeServices(): void
    {
        // Configura opções específicas dos serviços se necessário
        if (isset($this->config['matching'])) {
            // Configurações específicas do matching podem ser aplicadas aqui
        }

        if (isset($this->config['history'])) {
            // Configurações específicas do histórico podem ser aplicadas aqui
        }

        if (isset($this->config['profiles'])) {
            // Configurações específicas dos perfis podem ser aplicadas aqui
        }
    }

    /**
     * Obtém configuração específica
     */
    public function getModuleConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * Obtém serviços disponíveis
     */
    public function getAvailableServices(): array
    {
        return [
            'customers' => 'Gestão CRUD de clientes',
            'matching' => 'Matching e deduplicação de clientes',
            'history' => 'Histórico de transações e compras',
            'profiles' => 'Perfis de comportamento e análise',
        ];
    }

    /**
     * Verifica se módulo está funcionando corretamente
     */
    public function healthCheck(): array
    {
        $this->ensureInitialized();

        try {
            // Testa conexão com repositório
            $this->customerRepository->count();

            // Testa cache se configurado
            $cacheTest = $this->cache ? $this->testCache() : true;

            return [
                'module' => $this->getName(),
                'status' => 'healthy',
                'repository' => 'connected',
                'cache' => $cacheTest ? 'working' : 'failed',
                'services' => [
                    'customers' => $this->customerService ? 'loaded' : 'lazy',
                    'matching' => $this->matchingService ? 'loaded' : 'lazy',
                    'history' => $this->historyService ? 'loaded' : 'lazy',
                    'profiles' => $this->profileService ? 'loaded' : 'lazy',
                ],
                'last_check' => date('Y-m-d H:i:s'),
            ];

        } catch (\Throwable $e) {
            return [
                'module' => $this->getName(),
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Testa funcionamento do cache
     */
    private function testCache(): bool
    {
        try {
            $testKey = 'customers_health_check_' . time();
            $testValue = 'test';

            $this->setCache($testKey, $testValue, 10);
            $retrieved = $this->getFromCache($testKey);

            return $retrieved === $testValue;

        } catch (\Throwable $e) {
            $this->logger->warning('Falha no teste de cache do módulo customers', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}