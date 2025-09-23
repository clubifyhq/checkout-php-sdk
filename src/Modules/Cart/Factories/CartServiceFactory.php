<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart\Factories;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\Cart\Services\CartService;
use Clubify\Checkout\Modules\Cart\Services\ItemService;
use Clubify\Checkout\Modules\Cart\Services\NavigationService;
use Clubify\Checkout\Modules\Cart\Services\PromotionService;
use Clubify\Checkout\Modules\Cart\Services\OneClickService;
use Clubify\Checkout\Modules\Cart\Repositories\ApiCartRepository;

/**
 * Factory para Serviços de Carrinho
 *
 * Centraliza a criação e configuração de todos os serviços
 * relacionados ao carrinho, garantindo consistência na
 * injeção de dependências.
 *
 * Responsabilidades:
 * - Criação de services com dependências corretas
 * - Configuração centralizada de services
 * - Lazy loading de dependências
 * - Reutilização de instâncias
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Criação de services de carrinho
 * - O: Open/Closed - Extensível para novos services
 * - L: Liskov Substitution - Substituível
 * - I: Interface Segregation - Factory específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class CartServiceFactory
{
    private ?ApiCartRepository $repository = null;
    private ?CartService $cartService = null;
    private ?ItemService $itemService = null;
    private ?NavigationService $navigationService = null;
    private ?PromotionService $promotionService = null;
    private ?OneClickService $oneClickService = null;

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    // ===========================================
    // CRIAÇÃO DO REPOSITORY
    // ===========================================

    /**
     * Cria ou retorna instância do repository
     */
    public function createRepository(): ApiCartRepository
    {
        if ($this->repository === null) {
            $this->repository = new ApiCartRepository(
                $this->config,
                $this->logger,
                $this->httpClient
            );

            $this->logger->debug('ApiCartRepository created', [
                'factory' => self::class
            ]);
        }

        return $this->repository;
    }

    // ===========================================
    // CRIAÇÃO DOS SERVICES
    // ===========================================

    /**
     * Cria ou retorna instância do CartService
     */
    public function createCartService(): CartService
    {
        if ($this->cartService === null) {
            $this->cartService = new CartService(
                $this->createRepository(),
                $this->logger,
                $this->cache,
                $this->config->toArray()
            );

            $this->logger->debug('CartService created', [
                'factory' => self::class
            ]);
        }

        return $this->cartService;
    }

    /**
     * Cria ou retorna instância do ItemService
     */
    public function createItemService(): ItemService
    {
        if ($this->itemService === null) {
            $this->itemService = new ItemService(
                $this->createRepository(),
                $this->logger,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('ItemService created', [
                'factory' => self::class
            ]);
        }

        return $this->itemService;
    }

    /**
     * Cria ou retorna instância do NavigationService
     */
    public function createNavigationService(): NavigationService
    {
        if ($this->navigationService === null) {
            $this->navigationService = new NavigationService(
                $this->createRepository(),
                $this->logger,
                $this->cache,
                $this->config
            );

            $this->logger->debug('NavigationService created', [
                'factory' => self::class
            ]);
        }

        return $this->navigationService;
    }

    /**
     * Cria ou retorna instância do PromotionService
     */
    public function createPromotionService(): PromotionService
    {
        if ($this->promotionService === null) {
            $this->promotionService = new PromotionService(
                $this->createRepository(),
                $this->logger,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('PromotionService created', [
                'factory' => self::class
            ]);
        }

        return $this->promotionService;
    }

    /**
     * Cria ou retorna instância do OneClickService
     */
    public function createOneClickService(): OneClickService
    {
        if ($this->oneClickService === null) {
            $this->oneClickService = new OneClickService(
                $this->createRepository(),
                $this->logger,
                $this->cache,
                $this->config,
                $this->eventDispatcher
            );

            $this->logger->debug('OneClickService created', [
                'factory' => self::class
            ]);
        }

        return $this->oneClickService;
    }

    // ===========================================
    // MÉTODOS DE CONVENIÊNCIA
    // ===========================================

    /**
     * Cria todos os services de uma vez
     */
    public function createAllServices(): array
    {
        return [
            'repository' => $this->createRepository(),
            'cart' => $this->createCartService(),
            'item' => $this->createItemService(),
            'navigation' => $this->createNavigationService(),
            'promotion' => $this->createPromotionService(),
            'oneClick' => $this->createOneClickService()
        ];
    }

    /**
     * Cria services essenciais para operações básicas
     */
    public function createEssentialServices(): array
    {
        return [
            'repository' => $this->createRepository(),
            'cart' => $this->createCartService(),
            'item' => $this->createItemService()
        ];
    }

    /**
     * Cria services avançados para funcionalidades completas
     */
    public function createAdvancedServices(): array
    {
        return [
            'navigation' => $this->createNavigationService(),
            'promotion' => $this->createPromotionService(),
            'oneClick' => $this->createOneClickService()
        ];
    }

    // ===========================================
    // CONFIGURAÇÃO E PERSONALIZAÇÃO
    // ===========================================

    /**
     * Configura services com configurações específicas
     */
    public function configureServices(array $serviceConfigs): void
    {
        foreach ($serviceConfigs as $serviceName => $config) {
            switch ($serviceName) {
                case 'cart':
                    $this->configureCartService($config);
                    break;
                case 'item':
                    $this->configureItemService($config);
                    break;
                case 'navigation':
                    $this->configureNavigationService($config);
                    break;
                case 'promotion':
                    $this->configurePromotionService($config);
                    break;
                case 'oneClick':
                    $this->configureOneClickService($config);
                    break;
            }
        }

        $this->logger->debug('Services configured', [
            'configured_services' => array_keys($serviceConfigs),
            'factory' => self::class
        ]);
    }

    /**
     * Obtém configurações padrão para services
     */
    public function getDefaultServiceConfigs(): array
    {
        return [
            'cart' => [
                'cache_ttl' => 1800,
                'max_items' => 50,
                'abandoned_hours' => 24
            ],
            'item' => [
                'cache_ttl' => 600,
                'max_quantity_per_item' => 999,
                'min_quantity_per_item' => 1
            ],
            'navigation' => [
                'cache_ttl' => 900,
                'max_steps' => 10,
                'session_timeout' => 3600
            ],
            'promotion' => [
                'cache_ttl' => 300,
                'max_concurrent_promotions' => 3
            ],
            'oneClick' => [
                'cache_ttl' => 300,
                'validation_timeout' => 30
            ]
        ];
    }

    // ===========================================
    // MÉTODOS DE CONFIGURAÇÃO ESPECÍFICOS
    // ===========================================

    /**
     * Configura CartService específico
     */
    private function configureCartService(array $config): void
    {
        // Configurações específicas do CartService podem ser aplicadas aqui
        // Por ora, apenas log da configuração
        $this->logger->debug('CartService configured', $config);
    }

    /**
     * Configura ItemService específico
     */
    private function configureItemService(array $config): void
    {
        // Configurações específicas do ItemService podem ser aplicadas aqui
        $this->logger->debug('ItemService configured', $config);
    }

    /**
     * Configura NavigationService específico
     */
    private function configureNavigationService(array $config): void
    {
        // Configurações específicas do NavigationService podem ser aplicadas aqui
        $this->logger->debug('NavigationService configured', $config);
    }

    /**
     * Configura PromotionService específico
     */
    private function configurePromotionService(array $config): void
    {
        // Configurações específicas do PromotionService podem ser aplicadas aqui
        $this->logger->debug('PromotionService configured', $config);
    }

    /**
     * Configura OneClickService específico
     */
    private function configureOneClickService(array $config): void
    {
        // Configurações específicas do OneClickService podem ser aplicadas aqui
        $this->logger->debug('OneClickService configured', $config);
    }

    // ===========================================
    // MÉTODOS DE ANÁLISE E DEBUG
    // ===========================================

    /**
     * Verifica quais services já foram criados
     */
    public function getCreatedServices(): array
    {
        return [
            'repository' => $this->repository !== null,
            'cart' => $this->cartService !== null,
            'item' => $this->itemService !== null,
            'navigation' => $this->navigationService !== null,
            'promotion' => $this->promotionService !== null,
            'oneClick' => $this->oneClickService !== null
        ];
    }

    /**
     * Obtém estatísticas da factory
     */
    public function getFactoryStats(): array
    {
        $createdServices = $this->getCreatedServices();
        $totalServices = count($createdServices);
        $createdCount = count(array_filter($createdServices));

        return [
            'factory' => self::class,
            'total_services' => $totalServices,
            'created_services' => $createdCount,
            'creation_percentage' => $totalServices > 0 ? round(($createdCount / $totalServices) * 100, 2) : 0,
            'services_status' => $createdServices,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => time()
        ];
    }

    /**
     * Força recriação de um service específico
     */
    public function recreateService(string $serviceName): object
    {
        $this->logger->info('Recreating service', [
            'service' => $serviceName,
            'factory' => self::class
        ]);

        switch ($serviceName) {
            case 'repository':
                $this->repository = null;
                return $this->createRepository();

            case 'cart':
                $this->cartService = null;
                return $this->createCartService();

            case 'item':
                $this->itemService = null;
                return $this->createItemService();

            case 'navigation':
                $this->navigationService = null;
                return $this->createNavigationService();

            case 'promotion':
                $this->promotionService = null;
                return $this->createPromotionService();

            case 'oneClick':
                $this->oneClickService = null;
                return $this->createOneClickService();

            default:
                throw new \InvalidArgumentException("Service '{$serviceName}' not found");
        }
    }

    /**
     * Limpa todas as instâncias criadas
     */
    public function clearAllServices(): void
    {
        $this->logger->info('Clearing all services', [
            'factory' => self::class
        ]);

        $this->repository = null;
        $this->cartService = null;
        $this->itemService = null;
        $this->navigationService = null;
        $this->promotionService = null;
        $this->oneClickService = null;
    }

    /**
     * Verifica integridade das dependências
     */
    public function validateDependencies(): array
    {
        $issues = [];

        try {
            // Testa criação do repository
            $repository = $this->createRepository();
            if (!$repository instanceof ApiCartRepository) {
                $issues[] = 'Repository creation failed';
            }
        } catch (\Exception $e) {
            $issues[] = 'Repository validation failed: ' . $e->getMessage();
        }

        try {
            // Testa criação de service básico
            $cartService = $this->createCartService();
            if (!$cartService instanceof CartService) {
                $issues[] = 'CartService creation failed';
            }
        } catch (\Exception $e) {
            $issues[] = 'CartService validation failed: ' . $e->getMessage();
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'timestamp' => time()
        ];
    }
}