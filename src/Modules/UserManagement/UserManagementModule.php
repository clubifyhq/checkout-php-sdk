<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\UserManagement;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Services\TenantService;
use Clubify\Checkout\Modules\UserManagement\Services\PasskeyService;
use Clubify\Checkout\Modules\UserManagement\Services\AuthService;
use Clubify\Checkout\Modules\UserManagement\Services\ApiKeyService;
use Clubify\Checkout\Modules\UserManagement\Services\DomainService;
use Clubify\Checkout\Modules\UserManagement\Services\RoleService;
use Clubify\Checkout\Modules\UserManagement\Services\SessionService;

/**
 * Módulo de gestão de usuários enterprise
 *
 * Responsável pela gestão completa de usuários e autenticação:
 * - CRUD de usuários
 * - Sistema completo de Passkeys/WebAuthn
 * - Gestão de tenants
 * - Gestão de roles e permissões
 * - Gestão de chaves API
 * - Configuração de domínios customizados
 * - Re-autenticação para operações sensíveis
 * - Verificação de suporte de browser WebAuthn
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de usuário
 * - O: Open/Closed - Extensível via novos tipos de autenticação
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de user management
 * - D: Dependency Inversion - Depende de abstrações
 */
class UserManagementModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Services (lazy loading)
    private ?UserService $userService = null;
    private ?TenantService $tenantService = null;
    private ?PasskeyService $passkeyService = null;
    private ?AuthService $authService = null;
    private ?ApiKeyService $apiKeyService = null;
    private ?DomainService $domainService = null;
    private ?RoleService $roleService = null;
    private ?SessionService $sessionService = null;

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
                'user' => $this->userService !== null,
                'tenant' => $this->tenantService !== null,
                'passkey' => $this->passkeyService !== null,
                'auth' => $this->authService !== null,
                'api_key' => $this->apiKeyService !== null,
                'domain' => $this->domainService !== null,
                'role' => $this->roleService !== null,
                'session' => $this->sessionService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->userService = null;
        $this->tenantService = null;
        $this->passkeyService = null;
        $this->authService = null;
        $this->apiKeyService = null;
        $this->domainService = null;
        $this->roleService = null;
        $this->sessionService = null;
        $this->initialized = false;
        $this->logger?->info('User Management module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
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
            'timestamp' => time()
        ];
    }

    /**
     * CRUD de usuários
     */
    public function createUser(array $userData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->createUser($userData);
    }

    public function getUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->getUser($userId);
    }

    public function updateUser(string $userId, array $userData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->updateUser($userId, $userData);
    }

    public function deleteUser(string $userId): array
    {
        $this->requireInitialized();
        return $this->getUserService()->deleteUser($userId);
    }

    public function listUsers(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getUserService()->listUsers($filters);
    }

    /**
     * Autenticar usuário
     */
    public function authenticateUser(string $email, string $password): array
    {
        $this->requireInitialized();
        return $this->getAuthService()->authenticateUser($email, $password);
    }

    /**
     * Obter roles do usuário
     */
    public function getUserRoles(string $userId): array
    {
        $this->requireInitialized();
        return $this->getRoleService()->getUserRoles($userId);
    }

    /**
     * Atualizar perfil do usuário
     */
    public function updateUserProfile(string $userId, array $profileData): array
    {
        $this->requireInitialized();
        return $this->getUserService()->updateUserProfile($userId, $profileData);
    }

    /**
     * Passkeys/WebAuthn
     */
    public function registerPasskeyBegin(string $userId): array
    {
        $this->requireInitialized();
        return $this->getPasskeyService()->registerBegin($userId);
    }

    public function registerPasskeyComplete(string $userId, array $credential): array
    {
        $this->requireInitialized();
        return $this->getPasskeyService()->registerComplete($userId, $credential);
    }

    public function authenticatePasskeyBegin(string $userId): array
    {
        $this->requireInitialized();
        return $this->getPasskeyService()->authenticateBegin($userId);
    }

    public function authenticatePasskeyComplete(string $userId, array $assertion): array
    {
        $this->requireInitialized();
        return $this->getPasskeyService()->authenticateComplete($userId, $assertion);
    }

    public function checkWebAuthnSupport(): array
    {
        $this->requireInitialized();
        return $this->getPasskeyService()->checkBrowserSupport();
    }

    /**
     * Gestão de tenants
     */
    public function createTenant(array $tenantData): array
    {
        $this->requireInitialized();
        return $this->getTenantService()->createTenant($tenantData);
    }

    public function getTenant(string $tenantId): array
    {
        $this->requireInitialized();
        return $this->getTenantService()->getTenant($tenantId);
    }

    public function updateTenant(string $tenantId, array $tenantData): array
    {
        $this->requireInitialized();
        return $this->getTenantService()->updateTenant($tenantId, $tenantData);
    }

    /**
     * Gestão de domínios
     */
    public function configureDomain(string $tenantId, array $domainData): array
    {
        $this->requireInitialized();
        return $this->getDomainService()->configureDomain($tenantId, $domainData);
    }

    public function verifyDomain(string $domainId): array
    {
        $this->requireInitialized();
        return $this->getDomainService()->verifyDomain($domainId);
    }

    /**
     * Gestão de API Keys
     */
    public function createApiKey(string $userId, array $keyData): array
    {
        $this->requireInitialized();
        return $this->getApiKeyService()->createApiKey($userId, $keyData);
    }

    public function revokeApiKey(string $keyId): array
    {
        $this->requireInitialized();
        return $this->getApiKeyService()->revokeApiKey($keyId);
    }

    /**
     * Gestão de roles
     */
    public function assignRole(string $userId, string $role): array
    {
        $this->requireInitialized();
        return $this->getRoleService()->assignRole($userId, $role);
    }

    public function checkPermission(string $userId, string $permission): array
    {
        $this->requireInitialized();
        return $this->getRoleService()->checkPermission($userId, $permission);
    }

    /**
     * Re-autenticação
     */
    public function requestReAuth(string $userId, string $operation): array
    {
        $this->requireInitialized();
        return $this->getAuthService()->requestReAuthentication($userId, $operation);
    }

    public function verifyReAuth(string $userId, string $token): array
    {
        $this->requireInitialized();
        return $this->getAuthService()->verifyReAuthentication($userId, $token);
    }

    /**
     * Sessões
     */
    public function createSession(string $userId, array $sessionData): array
    {
        $this->requireInitialized();
        return $this->getSessionService()->createSession($userId, $sessionData);
    }

    public function validateSession(string $sessionId): array
    {
        $this->requireInitialized();
        return $this->getSessionService()->validateSession($sessionId);
    }

    public function revokeSession(string $sessionId): array
    {
        $this->requireInitialized();
        return $this->getSessionService()->revokeSession($sessionId);
    }

    /**
     * Lazy loading dos services
     */
    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = new UserService(
                $this->config,
                $this->logger,
                $this->sdk->getHttpClient(),
                $this->sdk->getCacheManager(),
                $this->sdk->getEventDispatcher()
            );
        }
        return $this->userService;
    }

    private function getTenantService(): TenantService
    {
        if ($this->tenantService === null) {
            $this->tenantService = new TenantService($this->sdk, $this->config, $this->logger);
        }
        return $this->tenantService;
    }

    private function getPasskeyService(): PasskeyService
    {
        if ($this->passkeyService === null) {
            $this->passkeyService = new PasskeyService($this->sdk, $this->config, $this->logger);
        }
        return $this->passkeyService;
    }

    private function getAuthService(): AuthService
    {
        if ($this->authService === null) {
            $this->authService = new AuthService($this->sdk, $this->config, $this->logger);
        }
        return $this->authService;
    }

    private function getApiKeyService(): ApiKeyService
    {
        if ($this->apiKeyService === null) {
            $this->apiKeyService = new ApiKeyService($this->sdk, $this->config, $this->logger);
        }
        return $this->apiKeyService;
    }

    private function getDomainService(): DomainService
    {
        if ($this->domainService === null) {
            $this->domainService = new DomainService($this->sdk, $this->config, $this->logger);
        }
        return $this->domainService;
    }

    private function getRoleService(): RoleService
    {
        if ($this->roleService === null) {
            $this->roleService = new RoleService($this->sdk, $this->config, $this->logger);
        }
        return $this->roleService;
    }

    private function getSessionService(): SessionService
    {
        if ($this->sessionService === null) {
            $this->sessionService = new SessionService($this->sdk, $this->config, $this->logger);
        }
        return $this->sessionService;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('User Management module is not initialized');
        }
    }
}
