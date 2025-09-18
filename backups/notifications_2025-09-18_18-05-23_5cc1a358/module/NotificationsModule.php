<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Notifications;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Notifications\Services\NotificationService;
use Clubify\Checkout\Modules\Notifications\Services\WebhookConfigService;
use Clubify\Checkout\Modules\Notifications\Services\NotificationLogService;
use Clubify\Checkout\Modules\Notifications\Services\NotificationStatsService;
use Clubify\Checkout\Modules\Notifications\Factories\NotificationsServiceFactory;

/**
 * Módulo de gestão de notificações
 *
 * Responsável pela gestão completa de notificações:
 * - Gestão de notificações
 * - Configuração avançada de webhooks
 * - Logs detalhados de notificações
 * - Estatísticas e métricas
 * - Teste de entrega de webhooks
 * - Tipos de eventos configuráveis
 * - Retry automático e manual
 * - Validação de entrega
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de notificação
 * - O: Open/Closed - Extensível via novos tipos de notificação
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de notificações
 * - D: Dependency Inversion - Depende de abstrações
 */
class NotificationsModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Factory for service creation (lazy loading)
    private ?NotificationsServiceFactory $serviceFactory = null;

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

        $this->logger->info('Notifications module initialized', [
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
        return 'notifications';
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
        return ['webhooks'];
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
        $factory = $this->getServiceFactory();
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                'notification' => $factory->hasService('notification'),
                'webhook_config' => $factory->hasService('webhook_config'),
                'notification_log' => $factory->hasService('notification_log'),
                'notification_stats' => $factory->hasService('notification_stats'),
            ],
            'factory_stats' => $factory->getStats(),
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        if ($this->serviceFactory !== null) {
            $this->serviceFactory->clearCache();
            $this->serviceFactory = null;
        }
        $this->initialized = false;
        $this->logger?->info('Notifications module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('NotificationsModule health check failed', [
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
     * Gestão de notificações
     */
    public function sendNotification(array $notificationData): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->send($notificationData);
    }

    public function getNotification(string $notificationId): ?array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->get($notificationId);
    }

    public function listNotifications(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->list($filters, $page, $limit);
    }

    public function retryNotification(string $notificationId): bool
    {
        $this->requireInitialized();
        return $this->getNotificationService()->retry($notificationId);
    }

    public function cancelNotification(string $notificationId): bool
    {
        $this->requireInitialized();
        return $this->getNotificationService()->cancel($notificationId);
    }

    /**
     * Configuração de webhooks
     */
    public function createWebhookConfig(array $configData): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->create($configData);
    }

    public function getWebhookConfig(string $configId): ?array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->get($configId);
    }

    public function updateWebhookConfig(string $configId, array $configData): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->update($configId, $configData);
    }

    public function deleteWebhookConfig(string $configId): bool
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->delete($configId);
    }

    public function listWebhookConfigs(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->list($filters);
    }

    public function testWebhook(string $configId, array $testData = []): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->test($configId, $testData);
    }

    public function validateWebhookConfig(array $configData): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->validate($configData);
    }

    /**
     * Logs de notificações
     */
    public function getNotificationLogs(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $this->requireInitialized();
        return $this->getNotificationLogService()->getLogs($filters, $page, $limit);
    }

    public function getNotificationLog(string $logId): ?array
    {
        $this->requireInitialized();
        return $this->getNotificationLogService()->getLog($logId);
    }

    public function getLogsByNotification(string $notificationId): array
    {
        $this->requireInitialized();
        return $this->getNotificationLogService()->getLogsByNotification($notificationId);
    }

    public function getLogsByWebhook(string $webhookId): array
    {
        $this->requireInitialized();
        return $this->getNotificationLogService()->getLogsByWebhook($webhookId);
    }

    public function getFailedNotifications(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getNotificationLogService()->getFailedNotifications($filters);
    }

    /**
     * Estatísticas e métricas
     */
    public function getNotificationStatistics(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getNotificationStatsService()->getStatistics($filters);
    }

    public function getDeliveryStats(array $dateRange = []): array
    {
        $this->requireInitialized();
        return $this->getNotificationStatsService()->getDeliveryStats($dateRange);
    }

    public function getWebhookPerformance(): array
    {
        $this->requireInitialized();
        return $this->getNotificationStatsService()->getWebhookPerformance();
    }

    public function getEventTypeStats(): array
    {
        $this->requireInitialized();
        return $this->getNotificationStatsService()->getEventTypeStats();
    }

    public function getRetryAnalysis(): array
    {
        $this->requireInitialized();
        return $this->getNotificationStatsService()->getRetryAnalysis();
    }

    /**
     * Operações em lote
     */
    public function bulkSendNotifications(array $notifications): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->bulkSend($notifications);
    }

    public function bulkRetryNotifications(array $notificationIds): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->bulkRetry($notificationIds);
    }

    /**
     * Testes e validação
     */
    public function testNotificationDelivery(array $testData): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->testDelivery($testData);
    }

    public function validateNotificationData(array $notificationData): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->validate($notificationData);
    }

    /**
     * Configuração de eventos
     */
    public function getAvailableEventTypes(): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->getAvailableEventTypes();
    }

    public function configureEventSubscriptions(string $configId, array $eventTypes): bool
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->configureEvents($configId, $eventTypes);
    }

    /**
     * Health checks
     */
    public function performHealthCheck(): array
    {
        $this->requireInitialized();
        return $this->getNotificationService()->healthCheck();
    }

    public function checkWebhookConnectivity(string $configId): array
    {
        $this->requireInitialized();
        return $this->getWebhookConfigService()->checkConnectivity($configId);
    }

    /**
     * Factory-based service creation
     */
    private function getServiceFactory(): NotificationsServiceFactory
    {
        if ($this->serviceFactory === null) {
            $this->serviceFactory = $this->sdk->createNotificationsServiceFactory();
        }
        return $this->serviceFactory;
    }

    private function getNotificationService(): NotificationService
    {
        return $this->getServiceFactory()->create('notification');
    }

    private function getWebhookConfigService(): WebhookConfigService
    {
        return $this->getServiceFactory()->create('webhook_config');
    }

    private function getNotificationLogService(): NotificationLogService
    {
        return $this->getServiceFactory()->create('notification_log');
    }

    private function getNotificationStatsService(): NotificationStatsService
    {
        return $this->getServiceFactory()->create('notification_stats');
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Notifications module is not initialized');
        }
    }
}
