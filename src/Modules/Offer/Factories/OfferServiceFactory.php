<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Factories;

use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\Offer\Services\OfferService;
use Clubify\Checkout\Modules\Offer\Services\UpsellService;
use Clubify\Checkout\Modules\Offer\Services\ThemeService;
use Clubify\Checkout\Modules\Offer\Services\SubscriptionPlanService;
use Clubify\Checkout\Modules\Offer\Services\PublicOfferService;
use Clubify\Checkout\Modules\Offer\Repositories\OfferRepositoryInterface;
use Clubify\Checkout\Modules\Offer\Repositories\ApiOfferRepository;

/**
 * Factory para serviços do módulo Offer
 *
 * Responsável pela criação e configuração de todos os serviços
 * relacionados a ofertas, garantindo injeção adequada de dependências
 * e configuração consistente.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Cria apenas services de Offer
 * - O: Open/Closed - Extensível para novos services
 * - L: Liskov Substitution - Services implementam interfaces
 * - I: Interface Segregation - Factory específica para Offer
 * - D: Dependency Inversion - Injeta dependências via abstrações
 */
class OfferServiceFactory
{
    private Configuration $config;
    private Logger $logger;
    private Client $httpClient;
    private CacheManagerInterface $cache;
    private EventDispatcherInterface $eventDispatcher;

    // Cache de instâncias criadas
    private ?OfferService $offerService = null;
    private ?UpsellService $upsellService = null;
    private ?ThemeService $themeService = null;
    private ?SubscriptionPlanService $subscriptionPlanService = null;
    private ?PublicOfferService $publicOfferService = null;
    private ?OfferRepositoryInterface $repository = null;

    /**
     * Construtor da factory
     */
    public function __construct(
        Configuration $config,
        Logger $logger,
        Client $httpClient,
        CacheManagerInterface $cache,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;

        $this->logger->info('OfferServiceFactory initialized');
    }

    /**
     * Cria o serviço principal de ofertas
     */
    public function createOfferService(): OfferService
    {
        if ($this->offerService === null) {
            $this->offerService = new OfferService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('OfferService created');
        }

        return $this->offerService;
    }

    /**
     * Cria o serviço de upsells
     */
    public function createUpsellService(): UpsellService
    {
        if ($this->upsellService === null) {
            $this->upsellService = new UpsellService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('UpsellService created');
        }

        return $this->upsellService;
    }

    /**
     * Cria o serviço de temas
     */
    public function createThemeService(): ThemeService
    {
        if ($this->themeService === null) {
            $this->themeService = new ThemeService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('ThemeService created');
        }

        return $this->themeService;
    }

    /**
     * Cria o serviço de planos de assinatura
     */
    public function createSubscriptionPlanService(): SubscriptionPlanService
    {
        if ($this->subscriptionPlanService === null) {
            $this->subscriptionPlanService = new SubscriptionPlanService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('SubscriptionPlanService created');
        }

        return $this->subscriptionPlanService;
    }

    /**
     * Cria o serviço de ofertas públicas
     */
    public function createPublicOfferService(): PublicOfferService
    {
        if ($this->publicOfferService === null) {
            $this->publicOfferService = new PublicOfferService(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->debug('PublicOfferService created');
        }

        return $this->publicOfferService;
    }

    /**
     * Cria o repositório de ofertas
     */
    public function createRepository(): OfferRepositoryInterface
    {
        if ($this->repository === null) {
            $this->repository = new ApiOfferRepository(
                $this->config,
                $this->logger,
                $this->httpClient
            );

            $this->logger->debug('ApiOfferRepository created');
        }

        return $this->repository;
    }

    /**
     * Cria todos os serviços de uma vez
     */
    public function createAllServices(): array
    {
        return [
            'offer' => $this->createOfferService(),
            'upsell' => $this->createUpsellService(),
            'theme' => $this->createThemeService(),
            'subscription_plan' => $this->createSubscriptionPlanService(),
            'public_offer' => $this->createPublicOfferService(),
            'repository' => $this->createRepository()
        ];
    }

    /**
     * Obtém estatísticas da factory
     */
    public function getStats(): array
    {
        return [
            'services_created' => [
                'offer_service' => $this->offerService !== null,
                'upsell_service' => $this->upsellService !== null,
                'theme_service' => $this->themeService !== null,
                'subscription_plan_service' => $this->subscriptionPlanService !== null,
                'public_offer_service' => $this->publicOfferService !== null,
                'repository' => $this->repository !== null
            ],
            'factory_initialized' => true,
            'timestamp' => time()
        ];
    }

    /**
     * Verifica se um serviço específico foi criado
     */
    public function hasService(string $serviceName): bool
    {
        return match ($serviceName) {
            'offer' => $this->offerService !== null,
            'upsell' => $this->upsellService !== null,
            'theme' => $this->themeService !== null,
            'subscription_plan' => $this->subscriptionPlanService !== null,
            'public_offer' => $this->publicOfferService !== null,
            'repository' => $this->repository !== null,
            default => false
        };
    }

    /**
     * Obtém um serviço específico
     */
    public function getService(string $serviceName): mixed
    {
        return match ($serviceName) {
            'offer' => $this->createOfferService(),
            'upsell' => $this->createUpsellService(),
            'theme' => $this->createThemeService(),
            'subscription_plan' => $this->createSubscriptionPlanService(),
            'public_offer' => $this->createPublicOfferService(),
            'repository' => $this->createRepository(),
            default => throw new \InvalidArgumentException("Unknown service: {$serviceName}")
        };
    }

    /**
     * Verifica saúde de todos os serviços
     */
    public function healthCheck(): array
    {
        $health = [];

        try {
            if ($this->offerService !== null) {
                $health['offer_service'] = $this->offerService->isHealthy();
            }

            if ($this->upsellService !== null) {
                $health['upsell_service'] = $this->upsellService->isHealthy();
            }

            if ($this->themeService !== null) {
                $health['theme_service'] = $this->themeService->isHealthy();
            }

            if ($this->subscriptionPlanService !== null) {
                $health['subscription_plan_service'] = $this->subscriptionPlanService->isHealthy();
            }

            if ($this->publicOfferService !== null) {
                $health['public_offer_service'] = $this->publicOfferService->isHealthy();
            }

            $health['overall'] = !in_array(false, $health, true);
        } catch (\Exception $e) {
            $this->logger->error('Health check failed', [
                'error' => $e->getMessage()
            ]);
            $health['overall'] = false;
            $health['error'] = $e->getMessage();
        }

        return $health;
    }

    /**
     * Limpa cache de todas as instâncias
     */
    public function reset(): void
    {
        $this->offerService = null;
        $this->upsellService = null;
        $this->themeService = null;
        $this->subscriptionPlanService = null;
        $this->publicOfferService = null;
        $this->repository = null;

        $this->logger->debug('OfferServiceFactory reset - all services cleared');
    }

    /**
     * Obtém configuração específica para offers
     */
    public function getOfferConfig(): array
    {
        return $this->config->get('offer', []);
    }

    /**
     * Verifica se o módulo está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->config->get('modules.offer.enabled', true);
    }

    /**
     * Obtém versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Configura evento listeners para todos os serviços
     */
    public function setupEventListeners(): void
    {
        // Configurar listeners para eventos de offer
        $this->eventDispatcher->addListener('offer.created', function ($event) {
            $this->logger->info('Offer created event received', $event);
        });

        $this->eventDispatcher->addListener('offer.updated', function ($event) {
            $this->logger->info('Offer updated event received', $event);
        });

        $this->eventDispatcher->addListener('offer.deleted', function ($event) {
            $this->logger->info('Offer deleted event received', $event);
        });

        // Configurar listeners para eventos de upsell
        $this->eventDispatcher->addListener('upsell.created', function ($event) {
            $this->logger->info('Upsell created event received', $event);
        });

        // Configurar listeners para eventos de theme
        $this->eventDispatcher->addListener('theme.applied_to_offer', function ($event) {
            $this->logger->info('Theme applied to offer event received', $event);
        });

        // Configurar listeners para eventos de subscription plan
        $this->eventDispatcher->addListener('subscription_plan.created', function ($event) {
            $this->logger->info('Subscription plan created event received', $event);
        });

        // Configurar listeners para eventos de public offer
        $this->eventDispatcher->addListener('public_offer.view_tracked', function ($event) {
            $this->logger->debug('Public offer view tracked', $event);
        });

        $this->logger->info('Event listeners configured for OfferServiceFactory');
    }
}