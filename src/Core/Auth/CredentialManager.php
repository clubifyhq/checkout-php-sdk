<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Auth;

use Clubify\Checkout\Exceptions\AuthenticationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Gerenciador de credenciais multi-contexto com storage seguro
 *
 * Permite alternar entre diferentes contextos de autenticação:
 * - Super Admin: Credenciais globais para gerenciar tenants
 * - Tenant Admin: Credenciais específicas de cada tenant
 *
 * SECURITY: Utiliza storage criptografado para persistência segura
 */
class CredentialManager
{
    private CredentialStorageInterface $storage;
    private array $contexts = []; // Cache em memória
    private string $activeContext = 'default';
    private bool $autoSync = true; // Auto-sincronização com storage

    public function __construct(CredentialStorageInterface $storage)
    {
        $this->storage = $storage;
        $this->loadContextsFromStorage();
    }

    /**
     * Adicionar contexto de super admin
     */
    public function addSuperAdminContext(array $credentials): void
    {
        $this->validateSuperAdminCredentials($credentials);

        $contextData = [
            'type' => 'super_admin',
            'api_key' => $credentials['api_key'] ?? null,
            'access_token' => $credentials['access_token'] ?? null,
            'refresh_token' => $credentials['refresh_token'] ?? null,
            'username' => $credentials['username'] ?? $credentials['email'] ?? null,
            'email' => $credentials['email'] ?? null,
            'password' => $credentials['password'] ?? null,
            'role' => 'super_admin',
            'created_at' => time(),
            'last_used' => time()
        ];

        $this->contexts['super_admin'] = $contextData;

        if ($this->autoSync) {
            $this->storage->store('super_admin', $contextData);
        }
    }

    /**
     * Adicionar contexto de tenant
     */
    public function addTenantContext(string $tenantId, array $credentials): void
    {
        $this->validateTenantCredentials($credentials);

        // Se já existe, merge com os dados existentes para preservar informações
        $existingContext = $this->contexts[$tenantId] ?? [];

        $contextData = [
            'type' => 'tenant_admin',
            'tenant_id' => $tenantId,
            'api_key' => $credentials['api_key'] ?? $existingContext['api_key'] ?? null,
            'access_token' => $credentials['access_token'] ?? $existingContext['access_token'] ?? null,
            'refresh_token' => $credentials['refresh_token'] ?? $existingContext['refresh_token'] ?? null,
            'name' => $credentials['name'] ?? $existingContext['name'] ?? null,
            'domain' => $credentials['domain'] ?? $existingContext['domain'] ?? null,
            'subdomain' => $credentials['subdomain'] ?? $existingContext['subdomain'] ?? null,
            'role' => 'tenant_admin',
            'created_at' => $existingContext['created_at'] ?? time(),
            'last_used' => time(),
            'updated_at' => time()
        ];

        $this->contexts[$tenantId] = $contextData;

        if ($this->autoSync) {
            try {
                $this->storage->store($tenantId, $contextData);
            } catch (\Exception $e) {
                // Log error but don't fail the operation
                error_log("Warning: Failed to store tenant context '{$tenantId}': " . $e->getMessage());
            }
        }
    }

    /**
     * Alternar contexto ativo
     */
    public function switchContext(string $contextId): void
    {
        // Tentar carregar do storage se não estiver no cache
        if (!isset($this->contexts[$contextId]) && $this->storage->exists($contextId)) {
            $this->loadContextFromStorage($contextId);
        }

        if (!isset($this->contexts[$contextId])) {
            throw new InvalidArgumentException("Context {$contextId} not found");
        }

        $this->activeContext = $contextId;
        $this->contexts[$contextId]['last_used'] = time();

        // Atualizar no storage
        if ($this->autoSync) {
            $this->storage->store($contextId, $this->contexts[$contextId]);
        }
    }

    /**
     * Obter credenciais do contexto ativo
     */
    public function getCurrentCredentials(): array
    {
        return $this->contexts[$this->activeContext] ?? [];
    }

    /**
     * Obter contexto ativo
     */
    public function getActiveContext(): ?string
    {
        return $this->activeContext;
    }

    /**
     * Obter todos os contextos disponíveis
     */
    public function getAvailableContexts(): array
    {
        return array_keys($this->contexts);
    }

    /**
     * Verificar se contexto existe
     */
    public function hasContext(string $contextId): bool
    {
        return isset($this->contexts[$contextId]);
    }

    /**
     * Verificar se contexto tem API key válida
     */
    public function hasValidApiKey(string $contextId): bool
    {
        if (!$this->hasContext($contextId)) {
            return false;
        }

        $apiKey = $this->contexts[$contextId]['api_key'] ?? null;

        // Basic validation
        if (empty($apiKey) || !is_string($apiKey) || strlen($apiKey) < 10) {
            return false;
        }

        // Enhanced validation for Clubify API key format
        return $this->validateApiKeyFormat($apiKey);
    }

    /**
     * Validate API key format (Clubify specific)
     */
    private function validateApiKeyFormat(string $apiKey): bool
    {
        // Clubify API keys follow pattern: clb_(test|live)_[32-char-hex]
        $pattern = '/^clb_(test|live)_[a-f0-9]{32}$/';

        if (preg_match($pattern, $apiKey)) {
            return true;
        }

        // Fallback: Accept any key longer than 20 characters for compatibility
        return strlen($apiKey) >= 20;
    }

    /**
     * Verificar se está em modo super admin
     */
    public function isSuperAdminMode(): bool
    {
        $credentials = $this->getCurrentCredentials();
        return isset($credentials['type']) && $credentials['type'] === 'super_admin';
    }

    /**
     * Verificar se está em modo tenant
     */
    public function isTenantMode(): bool
    {
        $credentials = $this->getCurrentCredentials();
        return isset($credentials['type']) && $credentials['type'] === 'tenant_admin';
    }

    /**
     * Obter tenant ID atual (se em modo tenant)
     */
    public function getCurrentTenantId(): ?string
    {
        $credentials = $this->getCurrentCredentials();
        return $credentials['tenant_id'] ?? null;
    }

    /**
     * Remover contexto
     */
    public function removeContext(string $contextId): void
    {
        if ($contextId === $this->activeContext && $contextId !== 'default') {
            $this->activeContext = 'default';
        }

        unset($this->contexts[$contextId]);

        // Remover do storage
        if ($this->autoSync) {
            $this->storage->remove($contextId);
        }
    }

    /**
     * Limpar todos os contextos
     */
    public function clearAllContexts(): void
    {
        $this->contexts = [];
        $this->activeContext = 'default';
    }

    /**
     * Obter estatísticas dos contextos
     */
    public function getContextStats(): array
    {
        return [
            'total_contexts' => count($this->contexts),
            'active_context' => $this->activeContext,
            'contexts' => array_map(function ($context) {
                return [
                    'type' => $context['type'],
                    'role' => $context['role'],
                    'tenant_id' => $context['tenant_id'] ?? null,
                    'created_at' => $context['created_at'],
                    'last_used' => $context['last_used'],
                    'has_tokens' => !empty($context['access_token'])
                ];
            }, $this->contexts)
        ];
    }

    /**
     * Atualizar tokens de um contexto
     */
    public function updateContextTokens(string $contextId, string $accessToken, ?string $refreshToken = null): void
    {
        if (!isset($this->contexts[$contextId])) {
            throw new InvalidArgumentException("Context {$contextId} not found");
        }

        $this->contexts[$contextId]['access_token'] = $accessToken;
        if ($refreshToken) {
            $this->contexts[$contextId]['refresh_token'] = $refreshToken;
        }
        $this->contexts[$contextId]['last_used'] = time();
    }

    /**
     * Validar credenciais de super admin
     */
    private function validateSuperAdminCredentials(array $credentials): void
    {
        // Aceitar tanto API key quanto email/senha para super admin
        $hasApiKey = isset($credentials['api_key']) && !empty($credentials['api_key']);
        $hasEmailPassword = isset($credentials['email']) && !empty($credentials['email']) &&
                           isset($credentials['password']) && !empty($credentials['password']);

        if (!$hasApiKey && !$hasEmailPassword) {
            throw new AuthenticationException("Super admin credentials must include either 'api_key' or both 'email' and 'password'");
        }

        // Validar formato da API key se presente
        if ($hasApiKey && !preg_match('/^clb_(test|live)_[a-f0-9]{32}$/', $credentials['api_key'])) {
            throw new AuthenticationException('Invalid super admin API key format');
        }
    }

    /**
     * Validar credenciais de tenant
     */
    private function validateTenantCredentials(array $credentials): void
    {
        // API key é opcional para contextos criados durante tenant creation
        // A API key pode ser adicionada posteriormente quando necessária
        $hasApiKey = isset($credentials['api_key']) && !empty($credentials['api_key']);
        $hasBasicInfo = isset($credentials['tenant_id']) || isset($credentials['name']);

        if (!$hasApiKey && !$hasBasicInfo) {
            throw new AuthenticationException("Tenant credentials must include either 'api_key' or basic tenant information");
        }

        // Validar formato da API key se presente
        if ($hasApiKey && !preg_match('/^clb_(test|live)_[a-f0-9]{32}$/', $credentials['api_key'])) {
            throw new AuthenticationException('Invalid tenant API key format');
        }
    }

    /**
     * Carregar todos os contextos do storage
     */
    private function loadContextsFromStorage(): void
    {
        try {
            $contextIds = $this->storage->listContexts();

            foreach ($contextIds as $contextId) {
                $this->loadContextFromStorage($contextId);
            }
        } catch (RuntimeException $e) {
            // Log error but continue - storage might not be initialized yet
            error_log("Warning: Failed to load contexts from storage: " . $e->getMessage());
        }
    }

    /**
     * Carregar contexto específico do storage
     */
    private function loadContextFromStorage(string $contextId): void
    {
        try {
            $contextData = $this->storage->retrieve($contextId);

            if ($contextData !== null) {
                $this->contexts[$contextId] = $contextData;
            }
        } catch (RuntimeException $e) {
            error_log("Warning: Failed to load context '{$contextId}' from storage: " . $e->getMessage());
        }
    }

    /**
     * Sincronizar todos os contextos com o storage
     */
    public function syncToStorage(): void
    {
        foreach ($this->contexts as $contextId => $contextData) {
            try {
                $this->storage->store($contextId, $contextData);
            } catch (RuntimeException $e) {
                error_log("Warning: Failed to sync context '{$contextId}' to storage: " . $e->getMessage());
            }
        }
    }

    /**
     * Verificar saúde do storage
     */
    public function isStorageHealthy(): bool
    {
        return $this->storage->isHealthy();
    }

    /**
     * Habilitar/desabilitar sincronização automática
     */
    public function setAutoSync(bool $enabled): void
    {
        $this->autoSync = $enabled;
    }

    /**
     * CORREÇÃO: Limpa cache de contextos para evitar reutilização incorreta
     */
    public function clearCache(): void
    {
        try {
            // Limpar cache de contextos em memória
            $currentContext = $this->activeContext;
            $this->contexts = [];

            // Recarregar apenas o contexto ativo do storage
            $this->loadContextsFromStorage();

            // Manter o contexto ativo se ainda existir
            if (isset($this->contexts[$currentContext])) {
                $this->activeContext = $currentContext;
            } else {
                $this->activeContext = 'default';
            }
        } catch (\Exception $e) {
            // Não falhar se limpeza de cache não funcionar
        }
    }
}