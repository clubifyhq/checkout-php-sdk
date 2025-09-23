<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Cart;

use Clubify\Checkout\Contracts\ModuleInterface;
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
use Clubify\Checkout\Modules\Cart\Factories\CartServiceFactory;

/**
 * Módulo de Carrinho
 *
 * Responsável pela gestão completa de carrinho de compras, incluindo:
 * - Operações básicas de carrinho (CRUD)
 * - Gerenciamento de itens
 * - Sistema de navegação de fluxos (Flow Navigation)
 * - Promoções e descontos
 * - Checkout one-click
 * - Integração com API endpoints
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas carrinho
 * - O: Open/Closed - Extensível via services
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class CartModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    private ?ApiCartRepository $repository = null;
    private ?CartService $cartService = null;
    private ?ItemService $itemService = null;
    private ?NavigationService $navigationService = null;
    private ?PromotionService $promotionService = null;
    private ?OneClickService $oneClickService = null;
    private ?CartServiceFactory $serviceFactory = null;

    private ?Client $httpClient = null;
    private ?CacheManagerInterface $cache = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Cart module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
        ]);
    }

    /**
     * Define as dependências necessárias
     */
    public function setDependencies(
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ): void {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'cart';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array
    {
        return [
            'http_client' => Client::class,
            'cache' => CacheManagerInterface::class,
            'event_dispatcher' => EventDispatcherInterface::class
        ];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        if (!$this->initialized) {
            return false;
        }

        // Garante que as dependências estão disponíveis
        $this->ensureDependenciesInitialized();

        return $this->initialized &&
               $this->httpClient !== null &&
               $this->cache !== null &&
               $this->eventDispatcher !== null;
    }

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services' => [
                'cart' => $this->cartService !== null,
                'item' => $this->itemService !== null,
                'navigation' => $this->navigationService !== null,
                'promotion' => $this->promotionService !== null,
                'one_click' => $this->oneClickService !== null
            ],
            'repository' => $this->repository !== null,
            'factory' => $this->serviceFactory !== null,
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->repository = null;
        $this->cartService = null;
        $this->itemService = null;
        $this->navigationService = null;
        $this->promotionService = null;
        $this->oneClickService = null;
        $this->serviceFactory = null;
        $this->initialized = false;

        $this->logger->info('Cart module cleaned up');
    }

    /**
     * Obtém o repository de carrinho (lazy loading)
     */
    public function getRepository(): ApiCartRepository
    {
        if ($this->repository === null) {
            $this->ensureDependenciesInitialized();
            $this->repository = new ApiCartRepository(
                $this->config,
                $this->logger,
                $this->httpClient
            );
        }

        return $this->repository;
    }

    /**
     * Obtém o serviço de carrinho (lazy loading)
     */
    public function cart(): CartService
    {
        if ($this->cartService === null) {
            $this->ensureDependenciesInitialized();
            $this->cartService = new CartService(
                $this->getRepository(),
                $this->logger,
                $this->cache,
                $this->config->toArray()
            );
        }

        return $this->cartService;
    }

    /**
     * Obtém o serviço de itens (lazy loading)
     */
    public function item(): ItemService
    {
        if ($this->itemService === null) {
            $this->ensureDependenciesInitialized();
            $this->itemService = new ItemService(
                $this->getRepository(),
                $this->logger,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->itemService;
    }

    /**
     * Obtém o serviço de navegação (lazy loading)
     */
    public function navigation(): NavigationService
    {
        if ($this->navigationService === null) {
            $this->ensureDependenciesInitialized();
            $this->navigationService = new NavigationService(
                $this->getRepository(),
                $this->logger,
                $this->cache,
                $this->config
            );
        }

        return $this->navigationService;
    }

    /**
     * Obtém o serviço de promoções (lazy loading)
     */
    public function promotion(): PromotionService
    {
        if ($this->promotionService === null) {
            $this->ensureDependenciesInitialized();
            $this->promotionService = new PromotionService(
                $this->getRepository(),
                $this->logger,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->promotionService;
    }

    /**
     * Obtém o serviço de one-click (lazy loading)
     */
    public function oneClick(): OneClickService
    {
        if ($this->oneClickService === null) {
            $this->ensureDependenciesInitialized();
            $this->oneClickService = new OneClickService(
                $this->getRepository(),
                $this->logger,
                $this->cache,
                $this->config,
                $this->eventDispatcher
            );
        }

        return $this->oneClickService;
    }

    /**
     * Obtém a factory de serviços (lazy loading)
     */
    public function getServiceFactory(): CartServiceFactory
    {
        if ($this->serviceFactory === null) {
            $this->ensureDependenciesInitialized();
            $this->serviceFactory = new CartServiceFactory(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->serviceFactory;
    }

    /**
     * Garante que as dependências estão inicializadas usando classes reais
     */
    private function ensureDependenciesInitialized(): void
    {
        if (!isset($this->httpClient)) {
            $this->httpClient = new \Clubify\Checkout\Core\Http\Client($this->config, $this->logger);
        }

        if (!isset($this->cache)) {
            $this->cache = new \Clubify\Checkout\Core\Cache\CacheManager($this->config);
        }

        if (!isset($this->eventDispatcher)) {
            $this->eventDispatcher = new \Clubify\Checkout\Core\Events\EventDispatcher();
        }
    }

    /**
     * Cria novo carrinho (método de conveniência)
     */
    public function create(string $sessionId, array $data = []): array
    {
        return $this->cart()->create($sessionId, $data);
    }

    /**
     * Busca carrinho por ID (método de conveniência)
     */
    public function find(string $id): ?array
    {
        return $this->cart()->find($id);
    }

    /**
     * Busca carrinho por sessão (método de conveniência)
     */
    public function findBySession(string $sessionId): ?array
    {
        return $this->cart()->findBySession($sessionId);
    }

    /**
     * Adiciona item ao carrinho (método de conveniência)
     */
    public function addItem(string $cartId, array $itemData): array
    {
        return $this->item()->addToCart($cartId, $itemData);
    }

    /**
     * Remove item do carrinho (método de conveniência)
     */
    public function removeItem(string $cartId, string $itemId): array
    {
        return $this->item()->removeFromCart($cartId, $itemId);
    }

    /**
     * Atualiza item do carrinho (método de conveniência)
     */
    public function updateItem(string $cartId, string $itemId, array $updates): array
    {
        return $this->item()->updateInCart($cartId, $itemId, $updates);
    }

    /**
     * Aplica promoção ao carrinho (método de conveniência)
     */
    public function applyPromotion(string $cartId, string $promotionCode): array
    {
        return $this->promotion()->apply($cartId, $promotionCode);
    }

    /**
     * Remove promoção do carrinho (método de conveniência)
     */
    public function removePromotion(string $cartId): array
    {
        return $this->promotion()->remove($cartId);
    }

    /**
     * Processa checkout one-click (método de conveniência)
     */
    public function processOneClick(string $cartId, array $paymentData): array
    {
        return $this->oneClick()->process($cartId, $paymentData);
    }

    /**
     * Inicia navegação de fluxo (método de conveniência)
     */
    public function startFlowNavigation(string $offerId, array $context = []): array
    {
        return $this->navigation()->startFlow($offerId, $context);
    }

    /**
     * Continua navegação de fluxo (método de conveniência)
     */
    public function continueFlowNavigation(string $navigationId, array $stepData): array
    {
        return $this->navigation()->continueFlow($navigationId, $stepData);
    }

    /**
     * Setup completo do carrinho com itens e configurações (método de conveniência)
     */
    public function setupComplete(array $cartData): array
    {
        $this->logger->info('Starting cart setup', $cartData);

        try {
            // Cria o carrinho
            $cart = $this->create($cartData['session_id'] ?? uniqid('session_'), $cartData);

            // Adiciona itens se fornecidos
            if (!empty($cartData['items'])) {
                foreach ($cartData['items'] as $itemData) {
                    $this->addItem($cart['id'], $itemData);
                }
            }

            // Aplica promoção se fornecida
            if (!empty($cartData['promotion_code'])) {
                $this->applyPromotion($cart['id'], $cartData['promotion_code']);
            }

            // Recalcula totais
            $cart = $this->cart()->calculateTotals($cart['id']);

            $this->logger->info('Cart setup completed', [
                'cart_id' => $cart['id']
            ]);

            return $cart;

        } catch (\Exception $e) {
            $this->logger->error('Cart setup failed', [
                'error' => $e->getMessage(),
                'data' => $cartData
            ]);

            throw $e;
        }
    }
}