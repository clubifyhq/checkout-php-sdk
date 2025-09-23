<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\Offer\Repositories\OfferRepositoryInterface;
use Clubify\Checkout\Modules\Offer\Repositories\ApiOfferRepository;
use Clubify\Checkout\Modules\Offer\Services\OfferService;
use Clubify\Checkout\Modules\Offer\Services\UpsellService;
use Clubify\Checkout\Modules\Offer\Services\ThemeService;
use Clubify\Checkout\Modules\Offer\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Offer\Services\PublicOfferService;

/**
 * Módulo de Ofertas
 *
 * Responsável pela gestão completa de ofertas, incluindo:
 * - CRUD de ofertas (create, read, update, delete)
 * - Configuração de temas e layouts
 * - Gestão de upsells e order bumps
 * - Planos de assinatura
 * - Ofertas públicas (acesso por slug)
 * - Sistema avançado de temas e layouts
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas ofertas
 * - O: Open/Closed - Extensível via services
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class OfferModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    private ?OfferRepositoryInterface $repository = null;
    private ?OfferService $offerService = null;
    private ?UpsellService $upsellService = null;
    private ?ThemeService $themeService = null;
    private ?SubscriptionPlanService $subscriptionPlanService = null;
    private ?PublicOfferService $publicOfferService = null;

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

        $this->logger->info('Offer module initialized', [
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
        return 'offer';
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
                'offer' => $this->offerService !== null,
                'upsell' => $this->upsellService !== null,
                'theme' => $this->themeService !== null,
                'subscription_plan' => $this->subscriptionPlanService !== null,
                'public_offer' => $this->publicOfferService !== null
            ],
            'repository' => $this->repository !== null,
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->repository = null;
        $this->offerService = null;
        $this->upsellService = null;
        $this->themeService = null;
        $this->subscriptionPlanService = null;
        $this->publicOfferService = null;
        $this->initialized = false;

        $this->logger->info('Offer module cleaned up');
    }

    /**
     * Obtém o repository de ofertas (lazy loading)
     */
    public function getRepository(): OfferRepositoryInterface
    {
        if ($this->repository === null) {
            $this->ensureDependenciesInitialized();
            $this->repository = new ApiOfferRepository(
                $this->config,
                $this->logger,
                $this->httpClient
            );
        }

        return $this->repository;
    }

    /**
     * Obtém o serviço principal de ofertas (lazy loading)
     */
    public function offers(): OfferService
    {
        if ($this->offerService === null) {
            $this->ensureDependenciesInitialized();
            $this->offerService = new OfferService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->offerService;
    }

    /**
     * Obtém o serviço de upsells (lazy loading)
     */
    public function upsells(): UpsellService
    {
        if ($this->upsellService === null) {
            $this->ensureDependenciesInitialized();
            $this->upsellService = new UpsellService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->upsellService;
    }

    /**
     * Obtém o serviço de temas (lazy loading)
     */
    public function themes(): ThemeService
    {
        if ($this->themeService === null) {
            $this->ensureDependenciesInitialized();
            $this->themeService = new ThemeService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->themeService;
    }

    /**
     * Obtém o serviço de planos de assinatura (lazy loading)
     */
    public function subscriptionPlans(): SubscriptionPlanService
    {
        if ($this->subscriptionPlanService === null) {
            $this->ensureDependenciesInitialized();
            $this->subscriptionPlanService = new SubscriptionPlanService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->subscriptionPlanService;
    }

    /**
     * Obtém o serviço de ofertas públicas (lazy loading)
     */
    public function publicOffers(): PublicOfferService
    {
        if ($this->publicOfferService === null) {
            $this->ensureDependenciesInitialized();
            $this->publicOfferService = new PublicOfferService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );
        }

        return $this->publicOfferService;
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
     * Criar oferta completa (método de conveniência)
     *
     * @param array $offerData Dados da oferta
     * @return array Oferta criada
     */
    public function createOffer(array $offerData): array
    {
        return $this->offers()->create($offerData);
    }

    /**
     * Obter oferta por slug público (método de conveniência)
     *
     * @param string $slug Slug da oferta
     * @return array|null Dados da oferta pública
     */
    public function getPublicOffer(string $slug): ?array
    {
        return $this->publicOffers()->getBySlug($slug);
    }

    /**
     * Configurar tema de oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $themeData Dados do tema
     * @return array Resultado da configuração
     */
    public function configureTheme(string $offerId, array $themeData): array
    {
        return $this->themes()->applyToOffer($offerId, $themeData);
    }

    /**
     * Configurar layout de oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $layoutData Dados do layout
     * @return array Resultado da configuração
     */
    public function configureLayout(string $offerId, array $layoutData): array
    {
        return $this->offers()->updateLayout($offerId, $layoutData);
    }

    /**
     * Adicionar upsell à oferta (método de conveniência)
     *
     * @param string $offerId ID da oferta
     * @param array $upsellData Dados do upsell
     * @return array Upsell criado
     */
    public function addUpsell(string $offerId, array $upsellData): array
    {
        $upsellData['offer_id'] = $offerId;
        return $this->upsells()->create($upsellData);
    }
}