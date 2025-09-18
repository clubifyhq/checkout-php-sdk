<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Factory;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;

/**
 * Factory para criação de Repositories
 *
 * Implementa o Factory Pattern para criar e gerenciar instâncias de repositories.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Cria apenas repositories
 * - O: Open/Closed - Extensível para novos tipos
 * - L: Liskov Substitution - Pode ser substituída
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class RepositoryFactory implements FactoryInterface
{
    /**
     * Cache de repositories já criados (singleton per type)
     */
    private array $repositories = [];

    /**
     * Mapeamento de tipos para classes de repository
     */
    private array $repositoryMapping = [];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {
        $this->initializeRepositoryMapping();
    }

    /**
     * Cria um repository do tipo especificado
     *
     * @param string $type Tipo do repository a ser criado
     * @param array $config Configurações opcionais para criação
     * @return object Repository criado
     * @throws \InvalidArgumentException Se o tipo não for suportado
     */
    public function create(string $type, array $config = []): object
    {
        if (!isset($this->repositories[$type])) {
            $className = $this->resolveRepositoryClass($type);

            $this->logger->debug('Creating repository', [
                'type' => $type,
                'class' => $className,
                'config' => $config
            ]);

            $this->repositories[$type] = new $className(
                $this->config,
                $this->logger,
                $this->httpClient,
                $this->cache,
                $this->eventDispatcher
            );

            $this->logger->info('Repository created successfully', [
                'type' => $type,
                'class' => $className
            ]);
        }

        return $this->repositories[$type];
    }

    /**
     * Obtém lista de tipos suportados pela factory
     *
     * @return array Lista de tipos suportados
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->repositoryMapping);
    }

    /**
     * Registra um novo tipo de repository
     *
     * @param string $type Tipo do repository
     * @param string $className Nome da classe do repository
     * @throws \InvalidArgumentException Se a classe não existir ou não implementar RepositoryInterface
     */
    public function registerRepository(string $type, string $className): void
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Repository class '{$className}' does not exist");
        }

        $reflection = new \ReflectionClass($className);
        if (!$reflection->implementsInterface(\Clubify\Checkout\Contracts\RepositoryInterface::class)) {
            throw new \InvalidArgumentException("Class '{$className}' must implement RepositoryInterface");
        }

        $this->repositoryMapping[$type] = $className;

        $this->logger->info('Repository type registered', [
            'type' => $type,
            'class' => $className
        ]);
    }

    /**
     * Verifica se um tipo é suportado
     *
     * @param string $type Tipo a verificar
     * @return bool True se o tipo for suportado
     */
    public function isTypeSupported(string $type): bool
    {
        return isset($this->repositoryMapping[$type]);
    }

    /**
     * Limpa o cache de repositories
     */
    public function clearCache(): void
    {
        $this->repositories = [];
        $this->logger->info('Repository cache cleared');
    }

    /**
     * Obtém estatísticas da factory
     *
     * @return array Estatísticas da factory
     */
    public function getStats(): array
    {
        return [
            'supported_types' => count($this->repositoryMapping),
            'created_repositories' => count($this->repositories),
            'types' => array_keys($this->repositoryMapping),
            'created_types' => array_keys($this->repositories)
        ];
    }

    /**
     * Resolve a classe do repository para um tipo
     *
     * @param string $type Tipo do repository
     * @return string Nome da classe
     * @throws \InvalidArgumentException Se o tipo não for suportado
     */
    private function resolveRepositoryClass(string $type): string
    {
        if (!isset($this->repositoryMapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not supported. Supported types: " .
                implode(', ', $this->getSupportedTypes())
            );
        }

        return $this->repositoryMapping[$type];
    }

    /**
     * Inicializa o mapeamento padrão de repositories
     */
    private function initializeRepositoryMapping(): void
    {
        // Core repositories
        $this->repositoryMapping = [
            // User Management repositories
            'user' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository::class,
            'auth' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiAuthRepository::class,
            'passkey' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiPasskeyRepository::class,
            'tenant' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiTenantRepository::class,
            'role' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiRoleRepository::class,
            'session' => \Clubify\Checkout\Modules\UserManagement\Repositories\ApiSessionRepository::class,

            // E-commerce repositories
            'product' => \Clubify\Checkout\Modules\Products\Repositories\ApiProductRepository::class,
            'offer' => \Clubify\Checkout\Modules\Offers\Repositories\ApiOfferRepository::class,
            'order' => \Clubify\Checkout\Modules\Orders\Repositories\ApiOrderRepository::class,
            'payment' => \Clubify\Checkout\Modules\Payments\Repositories\ApiPaymentRepository::class,
            'customer' => \Clubify\Checkout\Modules\Customers\Repositories\ApiCustomerRepository::class,

            // Infrastructure repositories
            'webhook' => \Clubify\Checkout\Modules\Webhooks\Repositories\ApiWebhookRepository::class,
            'notification' => \Clubify\Checkout\Modules\Notifications\Repositories\ApiNotificationRepository::class,
            'tracking' => \Clubify\Checkout\Modules\Tracking\Repositories\ApiTrackingRepository::class,

            // Future repositories
            'subscription' => \Clubify\Checkout\Modules\Subscriptions\Repositories\ApiSubscriptionRepository::class,
        ];

        $this->logger->info('Repository mapping initialized', [
            'total_types' => count($this->repositoryMapping),
            'types' => array_keys($this->repositoryMapping)
        ]);
    }
}