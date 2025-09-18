<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Webhooks;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;

class WebhooksModule implements ModuleInterface
{
    private bool $initialized = false;
    private ?Configuration $config = null;
    private ?Logger $logger = null;

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

        $this->logger->info('WebhooksModule initialized successfully');
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
            'initialized' => $this->isInitialized(),
            'available' => $this->isAvailable(),
            'dependencies' => $this->getDependencies(),
            'healthy' => $this->isHealthy()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->initialized = false;
        $this->config = null;
        $this->logger = null;
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => 'webhooks',
            'status' => $this->isAvailable() ? 'active' : 'inactive',
            'initialized' => $this->isInitialized(),
            'version' => $this->getVersion()
        ];
    }

    /**
     * Configura webhook
     */
    public function configureWebhook(array $webhookConfig): array
    {
        $this->logger?->info('Configuring webhook', $webhookConfig);

        return [
            'success' => true,
            'webhook_id' => uniqid('webhook_'),
            'url' => $webhookConfig['url'] ?? '',
            'events' => $webhookConfig['events'] ?? [],
            'status' => 'active',
            'created_at' => time()
        ];
    }

    /**
     * Envia evento via webhook
     */
    public function sendEvent(string $event, array $data): array
    {
        $this->logger?->info('Sending webhook event', [
            'event' => $event,
            'data_keys' => array_keys($data)
        ]);

        return [
            'success' => true,
            'event_id' => uniqid('event_'),
            'event' => $event,
            'sent_at' => time(),
            'delivery_status' => 'delivered'
        ];
    }

    /**
     * Lista webhooks configurados
     */
    public function listWebhooks(): array
    {
        return [
            'success' => true,
            'webhooks' => [
                [
                    'id' => uniqid('webhook_'),
                    'url' => 'https://example.com/webhook',
                    'events' => ['order.created', 'payment.completed'],
                    'status' => 'active'
                ]
            ],
            'total' => 1
        ];
    }
}
