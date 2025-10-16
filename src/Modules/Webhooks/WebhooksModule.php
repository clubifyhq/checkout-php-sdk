<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Webhooks\Factories\WebhooksServiceFactory;
use Clubify\Checkout\Modules\Webhooks\Services\WebhookService;
use Clubify\Checkout\Modules\Webhooks\Services\ConfigService;
use Clubify\Checkout\Modules\Webhooks\Services\DeliveryService;
use Clubify\Checkout\Modules\Webhooks\Services\RetryService;
use Clubify\Checkout\Modules\Webhooks\Services\TestingService;

/**
 * Módulo de gestão de webhooks
 *
 * Responsável pela gestão completa de webhooks usando Factory Pattern:
 * - CRUD de webhooks via WebhookService
 * - Gestão de configurações via ConfigService
 * - Entrega confiável via DeliveryService
 * - Sistema de retry via RetryService
 * - Testes e validação via TestingService
 *
 * Arquitetura:
 * - Usa WebhooksServiceFactory para criar services sob demanda
 * - Implementa lazy loading para otimização de performance
 * - Segue padrão singleton para reutilização de instâncias
 * - Suporta 5 tipos de services diferentes
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de webhooks
 * - O: Open/Closed - Extensível via Factory pattern
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de webhooks
 * - D: Dependency Inversion - Depende de abstrações via Factory
 */
class WebhooksModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?WebhooksServiceFactory $factory = null;

    // Services (lazy loading via Factory)
    private ?WebhookService $webhookService = null;
    private ?ConfigService $configService = null;
    private ?DeliveryService $deliveryService = null;
    private ?RetryService $retryService = null;
    private ?TestingService $testingService = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
    }

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Webhooks module initialized', [
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
        // Atualizar a factory com as dependências corretas se já existe
        if ($this->factory !== null) {
            $this->factory = null; // Force recreation with new dependencies
        }

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
        return 'webhooks';
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
        return ['core', 'http-client'];
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
            'factory_loaded' => $this->factory !== null,
            'services_loaded' => [
                'webhook' => $this->webhookService !== null,
                'config' => $this->configService !== null,
                'delivery' => $this->deliveryService !== null,
                'retry' => $this->retryService !== null,
                'testing' => $this->testingService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Enhanced cleanup with factory
     */
    public function cleanup(): void
    {
        $this->webhookService = null;
        $this->configService = null;
        $this->deliveryService = null;
        $this->retryService = null;
        $this->testingService = null;
        $this->factory = null;
        $this->initialized = false;
        $this->logger?->info('Webhooks module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('WebhooksModule health check failed', [
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
     * CRUD de webhooks
     */
    public function createWebhook(array $webhookData): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->create($webhookData);
    }

    public function getWebhook(string $webhookId): ?array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->findById($webhookId);
    }

    public function updateWebhook(string $webhookId, array $updateData): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->update($webhookId, $updateData);
    }

    public function deleteWebhook(string $webhookId): bool
    {
        $this->requireInitialized();
        return $this->getWebhookService()->delete($webhookId);
    }

    public function listWebhooks(array $filters = []): array
    {
        $this->requireInitialized();
        if (isset($filters['organization_id']) && is_string($filters['organization_id']) && !empty($filters['organization_id'])) {
            return $this->getWebhookService()->findByOrganization($filters['organization_id']);
        }
        if (isset($filters['event']) && is_string($filters['event']) && !empty($filters['event'])) {
            return $this->getWebhookService()->findByEvent($filters['event']);
        }
        return ['webhooks' => [], 'total' => 0];
    }

    /**
     * Ativação/desativação de webhooks
     */
    public function activateWebhook(string $webhookId): bool
    {
        $this->requireInitialized();
        return $this->getWebhookService()->activate($webhookId);
    }

    public function deactivateWebhook(string $webhookId, string $reason = null): bool
    {
        $this->requireInitialized();
        return $this->getWebhookService()->deactivate($webhookId, $reason);
    }

    /**
     * Entrega de eventos
     */
    public function sendEvent(string $event, array $data, array $options = []): array
    {
        $this->requireInitialized();
        $webhooks = $this->getWebhookService()->findByEvent($event);

        $results = [];
        foreach ($webhooks as $webhook) {
            $result = $this->getDeliveryService()->deliver($webhook, $event, $data, $options);
            $results[] = $result;
        }

        return [
            'event' => $event,
            'webhooks_count' => count($webhooks),
            'deliveries' => $results,
            'timestamp' => time()
        ];
    }

    /**
     * Configurações de webhooks
     */
    public function getConfig(string $key = null): array
    {
        $this->requireInitialized();
        return $this->getConfigService()->getConfig($key);
    }

    public function updateConfig(string $key, mixed $value): bool
    {
        $this->requireInitialized();
        return $this->getConfigService()->updateConfig($key, $value);
    }

    /**
     * Testes de webhooks
     */
    public function testWebhook(string $webhookId, array $options = []): array
    {
        $this->requireInitialized();
        return $this->getTestingService()->testWebhook($webhookId, $options);
    }

    public function testUrl(string $url, array $options = []): array
    {
        $this->requireInitialized();
        return $this->getTestingService()->testUrl($url, $options);
    }

    /**
     * Validate webhook URL accessibility and configuration
     */
    public function validateUrl(string $url): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->validateUrl($url);
    }

    /**
     * Retry de webhooks
     */
    public function retryWebhook(string $webhookId, string $deliveryId): ?string
    {
        $this->requireInitialized();
        return $this->getRetryService()->scheduleRetry($webhookId, $deliveryId);
    }

    public function getRetryStatus(string $retryId): array
    {
        $this->requireInitialized();
        return $this->getRetryService()->getRetryStatus($retryId);
    }

    /**
     * Estatísticas e métricas
     */
    public function getWebhookStats(string $webhookId): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->getStats($webhookId);
    }

    public function getSupportedEvents(): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->getSupportedEvents();
    }

    /**
     * Create or update webhook configuration
     * Automatically creates new config or adds events to existing config
     */
    public function createOrUpdateWebhook(array $webhookData): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->createOrUpdateWebhook($webhookData);
    }

    /**
     * Add endpoint to webhook configuration
     */
    public function addEndpoint(string $organizationId, string $configName, string $eventType, string $url, array $options = []): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->addEndpoint($organizationId, $configName, $eventType, $url, $options);
    }

    /**
     * Remove endpoint from webhook configuration
     */
    public function removeEndpoint(string $organizationId, string $configName, string $eventType): bool
    {
        $this->requireInitialized();
        return $this->getWebhookService()->removeEndpoint($organizationId, $configName, $eventType);
    }

    /**
     * List all endpoints for a webhook configuration
     */
    public function listEndpoints(string $organizationId, string $configName = null): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->listEndpoints($organizationId, $configName);
    }

    /**
     * Update an existing endpoint
     */
    public function updateEndpoint(string $organizationId, string $configName, string $eventType, array $updates): array
    {
        $this->requireInitialized();
        return $this->getWebhookService()->updateEndpoint($organizationId, $configName, $eventType, $updates);
    }

    // ==============================================
    // FACTORY PATTERN - SERVICE CREATION
    // ==============================================

    /**
     * Get WebhooksServiceFactory instance (lazy loading)
     */
    private function getFactory(): WebhooksServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->createWebhooksServiceFactory();
        }
        return $this->factory;
    }

    /**
     * Get WebhookService instance (lazy loading)
     */
    private function getWebhookService(): WebhookService
    {
        if ($this->webhookService === null) {
            $this->webhookService = $this->getFactory()->create('webhook');
        }
        return $this->webhookService;
    }

    /**
     * Get ConfigService instance (lazy loading)
     */
    private function getConfigService(): ConfigService
    {
        if ($this->configService === null) {
            $this->configService = $this->getFactory()->create('config');
        }
        return $this->configService;
    }

    /**
     * Get DeliveryService instance (lazy loading)
     */
    private function getDeliveryService(): DeliveryService
    {
        if ($this->deliveryService === null) {
            $this->deliveryService = $this->getFactory()->create('delivery');
        }
        return $this->deliveryService;
    }

    /**
     * Get RetryService instance (lazy loading)
     */
    private function getRetryService(): RetryService
    {
        if ($this->retryService === null) {
            $this->retryService = $this->getFactory()->create('retry');
        }
        return $this->retryService;
    }

    /**
     * Get TestingService instance (lazy loading)
     */
    private function getTestingService(): TestingService
    {
        if ($this->testingService === null) {
            $this->testingService = $this->getFactory()->create('testing');
        }
        return $this->testingService;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Webhooks module is not initialized');
        }
    }
}
