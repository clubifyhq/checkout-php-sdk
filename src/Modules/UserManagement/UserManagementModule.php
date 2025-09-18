<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\UserManagement\Factories\UserServiceFactory;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;

/**
 * Módulo de gestão de usuários refatorado
 *
 * Implementa a nova arquitetura usando Factory Pattern e Repository Pattern.
 * Responsável pela gestão completa de usuários:
 * - CRUD de usuários via Repository Pattern
 * - Business logic via Services
 * - Dependency injection via Factory Pattern
 * - Lazy loading de services
 * - Health checks e métricas
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de usuário
 * - O: Open/Closed - Extensível via Factory Pattern
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de user management
 * - D: Dependency Inversion - Depende de abstrações
 */
class UserManagementModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?UserServiceFactory $factory = null;

    // Services (lazy loading)
    private ?UserService $userService = null;

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


        $this->logger->info('User Management module initialized', [
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
        return 'user_management';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Obtém dependências do módulo
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
     * Obtém status do módulo
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                'user' => $this->userService !== null,
            ],
            'factory_loaded' => $this->factory !== null,
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->userService = null;
        $this->factory = null;
        $this->initialized = false;
        $this->logger?->info('User Management module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized &&
                   ($this->userService === null || $this->userService->isHealthy());
        } catch (\Exception $e) {
            $this->logger?->error('UserManagementModule health check failed', [
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
            'services' => [
                'user' => $this->userService?->getMetrics(),
            ],
            'factory_stats' => $this->factory?->getStats(),
            'timestamp' => time()
        ];
    }

    // === USER MANAGEMENT METHODS ===

    /**
     * Cria um novo usuário
     */
    public function createUser(array $userData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->createUser($userData);
    }

    /**
     * Obtém um usuário por ID
     */
    public function getUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->getUser($userId);
    }

    /**
     * Atualiza um usuário
     */
    public function updateUser(string $userId, array $userData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->updateUser($userId, $userData);
    }

    /**
     * Exclui um usuário
     */
    public function deleteUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->deleteUser($userId);
    }

    /**
     * Lista usuários com filtros
     */
    public function listUsers(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getUserService()->listUsers($filters);
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateUserProfile(string $userId, array $profileData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->updateUserProfile($userId, $profileData);
    }

    /**
     * Obtém roles do usuário
     */
    public function getUserRoles(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->getUserRoles($userId);
    }

    /**
     * Busca usuário por email
     */
    public function findUserByEmail(string $email): array
    {
        $this->requireInitialized();
        return $this->getUserService()->findUserByEmail($email);
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(string $userId, string $newPassword): array
    {
        $this->requireInitialized();
        return $this->getUserService()->changePassword($userId, $newPassword);
    }

    /**
     * Ativa usuário
     */
    public function activateUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->activateUser($userId);
    }

    /**
     * Desativa usuário
     */
    public function deactivateUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->deactivateUser($userId);
    }

    // === FACTORY AND SERVICE CREATION ===

    /**
     * Obtém a factory de services
     */
    private function getFactory(): UserServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->createUserServiceFactory();
        }
        return $this->factory;
    }

    /**
     * Obtém o UserService (lazy loading)
     */
    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = $this->getFactory()->create('user');
        }
        return $this->userService;
    }

    /**
     * Verifica se o módulo está inicializado antes de executar operações
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('User Management module is not initialized');
        }
    }
}