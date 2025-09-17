<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Tracking;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Tracking\Services\EventTrackingService;
use Clubify\Checkout\Modules\Tracking\Services\BatchEventService;
use Clubify\Checkout\Modules\Tracking\Services\BeaconService;
use Clubify\Checkout\Modules\Tracking\Services\EventAnalyticsService;

/**
 * Módulo de rastreamento e analytics
 *
 * Responsável pela gestão completa de eventos e analytics:
 * - Rastreamento de eventos únicos
 * - Rastreamento em lote (otimizado)
 * - Eventos beacon (page unload)
 * - Analytics de eventos
 * - Segmentação de usuários
 * - Funil de conversão
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de tracking
 * - O: Open/Closed - Extensível via novos tipos de evento
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de tracking
 * - D: Dependency Inversion - Depende de abstrações
 */
class TrackingModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Services (lazy loading)
    private ?EventTrackingService $eventTrackingService = null;
    private ?BatchEventService $batchEventService = null;
    private ?BeaconService $beaconService = null;
    private ?EventAnalyticsService $eventAnalyticsService = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {}

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Tracking module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'tenant_id' => $this->config->getTenantId()
        ]);
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
        return 'tracking';
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
        return [];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
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
            'services_loaded' => [
                'event_tracking' => $this->eventTrackingService !== null,
                'batch_event' => $this->batchEventService !== null,
                'beacon' => $this->beaconService !== null,
                'analytics' => $this->eventAnalyticsService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->eventTrackingService = null;
        $this->batchEventService = null;
        $this->beaconService = null;
        $this->eventAnalyticsService = null;
        $this->initialized = false;
        $this->logger?->info('Tracking module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('TrackingModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'timestamp' => time()
        ];
    }

    /**
     * Rastrear evento único
     */
    public function trackEvent(array $eventData): array
    {
        $this->requireInitialized();
        return $this->getEventTrackingService()->trackEvent($eventData);
    }

    /**
     * Rastrear lote de eventos
     */
    public function trackBatch(array $eventsData): array
    {
        $this->requireInitialized();
        return $this->getBatchEventService()->trackBatch($eventsData);
    }

    /**
     * Rastrear evento beacon
     */
    public function trackBeacon(array $eventData): array
    {
        $this->requireInitialized();
        return $this->getBeaconService()->trackBeacon($eventData);
    }

    /**
     * Obter analytics de eventos
     */
    public function getAnalytics(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getEventAnalyticsService()->getAnalytics($filters);
    }

    /**
     * Obter segmentação de usuários
     */
    public function getUserSegmentation(array $criteria = []): array
    {
        $this->requireInitialized();
        return $this->getEventAnalyticsService()->getUserSegmentation($criteria);
    }

    /**
     * Obter funil de conversão
     */
    public function getConversionFunnel(array $steps): array
    {
        $this->requireInitialized();
        return $this->getEventAnalyticsService()->getConversionFunnel($steps);
    }

    /**
     * Obter Event Tracking Service (lazy loading)
     */
    private function getEventTrackingService(): EventTrackingService
    {
        if ($this->eventTrackingService === null) {
            $this->eventTrackingService = new EventTrackingService(
                $this->sdk,
                $this->config,
                $this->logger
            );
        }
        return $this->eventTrackingService;
    }

    /**
     * Obter Batch Event Service (lazy loading)
     */
    private function getBatchEventService(): BatchEventService
    {
        if ($this->batchEventService === null) {
            $this->batchEventService = new BatchEventService(
                $this->sdk,
                $this->config,
                $this->logger
            );
        }
        return $this->batchEventService;
    }

    /**
     * Obter Beacon Service (lazy loading)
     */
    private function getBeaconService(): BeaconService
    {
        if ($this->beaconService === null) {
            $this->beaconService = new BeaconService(
                $this->sdk,
                $this->config,
                $this->logger
            );
        }
        return $this->beaconService;
    }

    /**
     * Obter Event Analytics Service (lazy loading)
     */
    private function getEventAnalyticsService(): EventAnalyticsService
    {
        if ($this->eventAnalyticsService === null) {
            $this->eventAnalyticsService = new EventAnalyticsService(
                $this->sdk,
                $this->config,
                $this->logger
            );
        }
        return $this->eventAnalyticsService;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Tracking module is not initialized');
        }
    }
}
