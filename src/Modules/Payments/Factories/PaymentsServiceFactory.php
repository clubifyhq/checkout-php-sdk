<?php

namespace Clubify\Checkout\Modules\Payments\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
// Services
use Clubify\Checkout\Modules\Payments\Services\PaymentService;
use Clubify\Checkout\Modules\Payments\Services\CardService;
use Clubify\Checkout\Modules\Payments\Services\GatewayService;
use Clubify\Checkout\Modules\Payments\Services\TokenizationService;
// Repositories
use Clubify\Checkout\Modules\Payments\Repositories\ApiPaymentRepository;
use Clubify\Checkout\Modules\Payments\Repositories\ApiCardRepository;
// Utils
use Clubify\Checkout\Utils\Validators\CreditCardValidator;
use Clubify\Checkout\Utils\Formatters\CurrencyFormatter;
use Clubify\Checkout\Utils\Crypto\AESEncryption;
use Clubify\Checkout\Utils\Crypto\HMACSignature;

/**
 * Payments Service Factory
 *
 * Factory responsible for creating and managing all services and repositories
 * in the Payments module using dependency injection and singleton patterns:
 *
 * - Creates services with proper dependency injection
 * - Manages repository instances as singletons
 * - Handles service lifecycle and cleanup
 * - Provides statistics and monitoring
 * - Supports multiple service types
 *
 * Supported service types:
 * - 'payment': Main payment processing service
 * - 'card': Card management and tokenization service
 * - 'gateway': Gateway management and load balancing service
 * - 'tokenization': Advanced tokenization and security service
 *
 * Repository types automatically created:
 * - 'payment': ApiPaymentRepository
 * - 'card': ApiCardRepository
 *
 * @package Clubify\Checkout\Modules\Payments\Factories
 * @version 1.0.0
 * @author Clubify Checkout Team
 */
class PaymentsServiceFactory implements FactoryInterface
{
    /**
     * Cache of created services (singleton pattern)
     */
    private array $services = [];

    /**
     * Cache of created repositories (singleton pattern)
     */
    private array $repositories = [];

    /**
     * Cache of created utility instances
     */
    private array $utilities = [];

    /**
     * Statistics tracking
     */
    private array $stats = [
        'services_created' => 0,
        'repositories_created' => 0,
        'utilities_created' => 0,
        'created_service_types' => [],
        'created_repository_types' => [],
        'created_utility_types' => []
    ];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * Create service by type with dependency injection
     *
     * @param string $type Service type
     * @param array $config Optional service configuration
     * @return object Service instance
     * @throws \InvalidArgumentException When service type not supported
     */
    public function create(string $type, array $config = []): object
    {
        // Return existing service if already created (singleton)
        if (isset($this->services[$type])) {
            $this->logger->debug('Payments service reused from cache', [
                'type' => $type,
                'class' => get_class($this->services[$type])
            ]);
            return $this->services[$type];
        }

        // Validate service type
        if (!$this->isTypeSupported($type)) {
            throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        $this->logger->debug('Creating Payments service', [
            'type' => $type,
            'config' => $config
        ]);

        try {
            // Create service based on type
            $service = $this->createServiceByType($type, $config);

            // Cache service for reuse
            $this->services[$type] = $service;

            // Update statistics
            $this->updateStats('service', $type);

            $this->logger->info('Payments service created successfully', [
                'type' => $type,
                'class' => get_class($service),
                'config' => $config
            ]);

            return $service;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Payments service', [
                'type' => $type,
                'error' => $e->getMessage(),
                'config' => $config
            ]);
            throw $e;
        }
    }

    /**
     * Get supported service types
     *
     * @return array Array of supported service types
     */
    public function getSupportedTypes(): array
    {
        return [
            'payment',
            'card',
            'gateway',
            'tokenization'
        ];
    }

    /**
     * Check if service type is supported
     *
     * @param string $type Service type to check
     * @return bool True if supported
     */
    public function isTypeSupported(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes());
    }

    /**
     * Clear service cache (useful for testing and cleanup)
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->services = [];
        $this->repositories = [];
        $this->utilities = [];

        $this->logger->info('PaymentsServiceFactory cache cleared');
    }

    /**
     * Get factory statistics
     *
     * @return array Factory statistics and metrics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'supported_types' => count($this->getSupportedTypes()),
            'cached_services' => count($this->services),
            'cached_repositories' => count($this->repositories),
            'cached_utilities' => count($this->utilities),
            'types' => $this->getSupportedTypes(),
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time()
        ]);
    }

    /**
     * Get service instance without creating if not exists
     *
     * @param string $type Service type
     * @return object|null Service instance or null if not created
     */
    public function getService(string $type): ?object
    {
        return $this->services[$type] ?? null;
    }

    /**
     * Check if service is already created
     *
     * @param string $type Service type
     * @return bool True if service exists in cache
     */
    public function hasService(string $type): bool
    {
        return isset($this->services[$type]);
    }

    // ==============================================
    // PRIVATE METHODS - Service Creation Logic
    // ==============================================

    /**
     * Create service instance by type
     *
     * @param string $type Service type
     * @param array $config Service configuration
     * @return object Created service instance
     * @throws \InvalidArgumentException When type not supported
     */
    private function createServiceByType(string $type, array $config): object
    {
        switch ($type) {
            case 'payment':
                return $this->createPaymentService($config);

            case 'card':
                return $this->createCardService($config);

            case 'gateway':
                return $this->createGatewayService($config);

            case 'tokenization':
                return $this->createTokenizationService($config);

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }
    }

    /**
     * Create Payment service with dependencies
     *
     * @param array $config Service configuration
     * @return PaymentService Configured service instance
     */
    private function createPaymentService(array $config): PaymentService
    {
        $repository = $this->createRepository('payment');
        $cardValidator = $this->createUtility('card_validator');
        $currencyFormatter = $this->createUtility('currency_formatter');

        return new PaymentService(
            $repository,
            $this->logger,
            $cardValidator,
            $currencyFormatter,
            null // cache será null para evitar problemas de compatibilidade
        );
    }

    /**
     * Create Card service with dependencies
     *
     * @param array $config Service configuration
     * @return CardService Configured service instance
     */
    private function createCardService(array $config): CardService
    {
        $repository = $this->createRepository('card');
        $cardValidator = $this->createUtility('card_validator');
        $encryption = $this->createUtility('aes_encryption');

        return new CardService(
            $repository,
            $this->logger,
            $this->cache,
            $cardValidator,
            $encryption
        );
    }

    /**
     * Create Gateway service with dependencies
     *
     * @param array $config Service configuration
     * @return GatewayService Configured service instance
     */
    private function createGatewayService(array $config): GatewayService
    {
        return new GatewayService(
            $this->logger,
            $this->cache
        );
    }

    /**
     * Create Tokenization service with dependencies
     *
     * @param array $config Service configuration
     * @return TokenizationService Configured service instance
     */
    private function createTokenizationService(array $config): TokenizationService
    {
        $cardRepository = $this->createRepository('card');
        $cardValidator = $this->createUtility('card_validator');
        $encryption = $this->createUtility('aes_encryption');
        $hmacSignature = $this->createUtility('hmac_signature');

        return new TokenizationService(
            $cardRepository,
            $this->logger,
            $this->cache,
            $cardValidator,
            $encryption,
            $hmacSignature
        );
    }

    /**
     * Create repository by type with dependency injection
     *
     * @param string $type Repository type
     * @return object Repository instance
     * @throws \InvalidArgumentException When repository type not supported
     */
    private function createRepository(string $type): object
    {
        // Return existing repository if already created (singleton)
        if (isset($this->repositories[$type])) {
            return $this->repositories[$type];
        }

        $this->logger->debug('Creating repository', [
            'type' => $type,
            'factory' => get_class($this)
        ]);

        try {
            // Get repository class name
            $repositoryClass = $this->resolveRepositoryClass($type);

            // Create repository with dependency injection
            $repository = new $repositoryClass(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            // Cache repository for reuse
            $this->repositories[$type] = $repository;

            // Update statistics
            $this->updateStats('repository', $type);

            $this->logger->debug('Repository created', [
                'type' => $type,
                'class' => $repositoryClass
            ]);

            return $repository;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create repository', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create utility by type with dependency injection
     *
     * @param string $type Utility type
     * @return object Utility instance
     * @throws \InvalidArgumentException When utility type not supported
     */
    private function createUtility(string $type): object
    {
        // Return existing utility if already created (singleton)
        if (isset($this->utilities[$type])) {
            return $this->utilities[$type];
        }

        $this->logger->debug('Creating utility', [
            'type' => $type,
            'factory' => get_class($this)
        ]);

        try {
            // Get utility class name and create instance
            $utilityClass = $this->resolveUtilityClass($type);
            $utility = new $utilityClass();

            // Cache utility for reuse
            $this->utilities[$type] = $utility;

            // Update statistics
            $this->updateStats('utility', $type);

            $this->logger->debug('Utility created', [
                'type' => $type,
                'class' => $utilityClass
            ]);

            return $utility;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create utility', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Resolve repository class name from type
     *
     * @param string $type Repository type
     * @return string Repository class name
     * @throws \InvalidArgumentException When type not supported
     */
    private function resolveRepositoryClass(string $type): string
    {
        $mapping = [
            'payment' => ApiPaymentRepository::class,
            'card' => ApiCardRepository::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Available types: " . implode(', ', array_keys($mapping))
            );
        }

        return $mapping[$type];
    }

    /**
     * Resolve utility class name from type
     *
     * @param string $type Utility type
     * @return string Utility class name
     * @throws \InvalidArgumentException When type not supported
     */
    private function resolveUtilityClass(string $type): string
    {
        $mapping = [
            'card_validator' => CreditCardValidator::class,
            'currency_formatter' => CurrencyFormatter::class,
            'aes_encryption' => AESEncryption::class,
            'hmac_signature' => HMACSignature::class,
        ];

        if (!isset($mapping[$type])) {
            throw new \InvalidArgumentException(
                "Utility type '{$type}' is not yet implemented. Available types: " . implode(', ', array_keys($mapping))
            );
        }

        return $mapping[$type];
    }

    /**
     * Update internal statistics
     *
     * @param string $itemType Type of item ('service', 'repository', or 'utility')
     * @param string $type Specific type created
     */
    private function updateStats(string $itemType, string $type): void
    {
        if ($itemType === 'service') {
            $this->stats['services_created']++;
            if (!in_array($type, $this->stats['created_service_types'])) {
                $this->stats['created_service_types'][] = $type;
            }
        } elseif ($itemType === 'repository') {
            $this->stats['repositories_created']++;
            if (!in_array($type, $this->stats['created_repository_types'])) {
                $this->stats['created_repository_types'][] = $type;
            }
        } elseif ($itemType === 'utility') {
            $this->stats['utilities_created']++;
            if (!in_array($type, $this->stats['created_utility_types'])) {
                $this->stats['created_utility_types'][] = $type;
            }
        }
    }
}
