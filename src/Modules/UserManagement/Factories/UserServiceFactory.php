<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Http\Client;
use Clubify\Checkout\Core\Cache\CacheManagerInterface;
use Clubify\Checkout\Core\Events\EventDispatcherInterface;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Services\AuthService;
use Clubify\Checkout\Modules\UserManagement\Services\PasskeyService;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository;

/**
 * Factory para criação de Services do UserManagement
 *
 * Implementa o Factory Pattern para criar e gerenciar instâncias de services
 * específicos do módulo UserManagement com suas dependências.
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Cria apenas services do UserManagement
 * - O: Open/Closed - Extensível para novos tipos de service
 * - L: Liskov Substitution - Pode ser substituída
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class UserServiceFactory implements FactoryInterface
{
    /**
     * Cache de services já criados (singleton per type)
     */
    private array $services = [];

    /**
     * Cache de repositories já criados
     */
    private array $repositories = [];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * Cria um service do tipo especificado
     *
     * @param string $type Tipo do service a ser criado
     * @param array $config Configurações opcionais para criação
     * @return object Service criado
     * @throws \InvalidArgumentException Se o tipo não for suportado
     */
    public function create(string $type, array $config = []): object
    {
        if (isset($this->services[$type])) {
            return $this->services[$type];
        }

        $this->logger->debug('Creating UserManagement service', [
            'type' => $type,
            'config' => $config
        ]);

        switch ($type) {
            case 'user':
                $repository = $this->createRepository('user');
                $this->services[$type] = new UserService($repository, $this->logger);
                break;

            case 'auth':
                $repository = $this->createRepository('auth');
                $this->services[$type] = new AuthService($repository, $this->logger);
                break;

            case 'passkey':
                $repository = $this->createRepository('passkey');
                $this->services[$type] = new PasskeyService($repository, $this->logger);
                break;

            default:
                throw new \InvalidArgumentException(
                    "Service type '{$type}' is not supported. Supported types: " .
                    implode(', ', $this->getSupportedTypes())
                );
        }

        $this->logger->info('UserManagement service created successfully', [
            'type' => $type,
            'class' => get_class($this->services[$type])
        ]);

        return $this->services[$type];
    }

    /**
     * Obtém lista de tipos suportados pela factory
     *
     * @return array Lista de tipos suportados
     */
    public function getSupportedTypes(): array
    {
        return ['user', 'auth', 'passkey', 'tenant', 'role', 'session'];
    }

    /**
     * Verifica se um tipo é suportado
     *
     * @param string $type Tipo a verificar
     * @return bool True se o tipo for suportado
     */
    public function isTypeSupported(string $type): bool
    {
        return in_array($type, $this->getSupportedTypes());
    }

    /**
     * Limpa o cache de services
     */
    public function clearCache(): void
    {
        $this->services = [];
        $this->repositories = [];
        $this->logger->info('UserServiceFactory cache cleared');
    }

    /**
     * Obtém estatísticas da factory
     *
     * @return array Estatísticas da factory
     */
    public function getStats(): array
    {
        return [
            'supported_types' => count($this->getSupportedTypes()),
            'created_services' => count($this->services),
            'created_repositories' => count($this->repositories),
            'types' => $this->getSupportedTypes(),
            'created_service_types' => array_keys($this->services),
            'created_repository_types' => array_keys($this->repositories)
        ];
    }

    /**
     * Cria um repository específico
     *
     * @param string $type Tipo do repository
     * @return object Repository criado
     * @throws \InvalidArgumentException Se o tipo não for suportado
     */
    private function createRepository(string $type): object
    {
        if (isset($this->repositories[$type])) {
            return $this->repositories[$type];
        }

        $repositoryClass = $this->resolveRepositoryClass($type);

        $this->repositories[$type] = new $repositoryClass(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        $this->logger->debug('Repository created', [
            'type' => $type,
            'class' => $repositoryClass
        ]);

        return $this->repositories[$type];
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
        $repositoryMapping = [
            'user' => ApiUserRepository::class,
            // 'auth' => ApiAuthRepository::class,          // TODO: Implementar na próxima fase
            // 'passkey' => ApiPasskeyRepository::class,    // TODO: Implementar na próxima fase
            // 'tenant' => ApiTenantRepository::class,      // TODO: Implementar na próxima fase
            // 'role' => ApiRoleRepository::class,          // TODO: Implementar na próxima fase
            // 'session' => ApiSessionRepository::class,   // TODO: Implementar na próxima fase
        ];

        // Para esta fase, apenas user repository está implementado
        if ($type !== 'user') {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not yet implemented. Currently only 'user' is available."
            );
        }

        if (!isset($repositoryMapping[$type])) {
            throw new \InvalidArgumentException(
                "Repository type '{$type}' is not supported. Supported types: " .
                implode(', ', array_keys($repositoryMapping))
            );
        }

        $className = $repositoryMapping[$type];

        if (!class_exists($className)) {
            throw new \InvalidArgumentException("Repository class '{$className}' does not exist");
        }

        return $className;
    }
}