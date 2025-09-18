# 🔄 Guia de Migração para Arquitetura Híbrida

## 📋 Visão Geral

Este documento fornece um **guia completo** para migrar módulos existentes do SDK Clubify Checkout para a nova **arquitetura híbrida (Repository + Factory Pattern)**. O processo é **incremental** e **compatível** com o código existente.

---

## ⚠️ CORREÇÕES CRÍTICAS OBRIGATÓRIAS

**ANTES de migrar qualquer módulo**, você DEVE aplicar estas correções críticas para evitar erros 404:

### 🚨 Problema: URLs Incorretas

**Sintoma**: Requisições retornam 404 com redirects para `/c/checkout/`
```
GET /users/search → 307 → GET /c/checkout/users/search → 404
```

### ✅ Solução 1: Configuration.php

**Arquivo**: `src/Core/Config/Configuration.php`

```php
public function getBaseUrl(): string
{
    // ✅ Aceita múltiplos formatos de configuração
    $customUrl = $this->get('endpoints.base_url')
              ?? $this->get('api.base_url')
              ?? $this->get('base_url');

    if ($customUrl) {
        $normalizedUrl = rtrim($customUrl, '/');

        // ✅ Automaticamente adiciona /api/v1 se necessário
        if (!str_ends_with($normalizedUrl, '/api/v1')) {
            $normalizedUrl .= '/api/v1';
        }

        return $normalizedUrl;
    }

    $environment = Environment::from($this->getEnvironment());
    return $environment->getBaseUrl();
}
```

### ✅ Solução 2: Endpoints Relativos

**TODOS os repositories devem usar paths relativos:**

```php
// ❌ ERRO - Path absoluto
protected function getEndpoint(): string {
    return '/users'; // Quebra o Guzzle base_uri
}

// ✅ CORRETO - Path relativo
protected function getEndpoint(): string {
    return 'users'; // Respeita o base_uri
}
```

### ✅ Solução 3: Chamadas HTTP

**TODAS as chamadas HTTP devem usar paths relativos:**

```php
// ❌ ERRO
$this->httpClient->get('/users/search', $params);

// ✅ CORRETO
$this->httpClient->get('users/search', $params);
```

### 🔍 Como Verificar

Execute este teste:
```bash
php debug-url-construction.php
```

**URL correta**: `https://checkout.svelve.com/api/v1/users/search` (401 é esperado)
**URL incorreta**: `https://checkout.svelve.com/users/search` (404)

---

## 🎯 Estratégia de Migração

### Princípios da Migração

1. **🔄 Incremental**: Migre um módulo por vez
2. **🔒 Backward Compatible**: Mantenha APIs existentes funcionando
3. **🧪 Test-Driven**: Testes garantem que nada quebre
4. **📊 Gradual Rollout**: Feature flags para controle
5. **📝 Well Documented**: Documente todas as mudanças

### Fases da Migração

```
Fase 1: Análise → Fase 2: Preparação → Fase 3: Implementação → Fase 4: Validação → Fase 5: Cleanup
```

---

## 🔍 Fase 1: Análise do Módulo Atual

### 1.1 Inventário do Módulo

Antes de iniciar a migração, faça um inventário completo:

**Checklist de Análise**:
- [ ] Identificar todas as classes do módulo
- [ ] Mapear métodos públicos (API surface)
- [ ] Identificar dependências externas
- [ ] Localizar testes existentes
- [ ] Documentar fluxos de dados
- [ ] Identificar acoplamentos

**Script de Análise**:
```bash
# Encontre todos os arquivos do módulo
find src/Modules/UserManagement -name "*.php" | xargs wc -l

# Analise dependências
grep -r "use " src/Modules/UserManagement/ | sort | uniq

# Encontre métodos públicos
grep -r "public function" src/Modules/UserManagement/
```

### 1.2 Análise de Complexidade

**Exemplo: UserManagement (ANTES)**:
```php
class UserManagementModule extends BaseModule
{
    private HttpClient $client;

    public function __construct(ClubifyCheckoutSDK $sdk) {
        parent::__construct($sdk);
        $this->client = $sdk->getHttpClient();
    }

    // ❌ Problema: HTTP calls diretas no módulo
    public function createUser(array $userData): array {
        $response = $this->client->post('users', $userData);
        return $response->getData();
    }

    // ❌ Problema: Sem abstração de dados
    public function getUser(string $userId): array {
        $response = $this->client->get("/users/{$userId}");
        return $response->getData();
    }

    // ❌ Problema: Sem validação estruturada
    public function updateUser(string $userId, array $data): array {
        if (empty($data)) {
            throw new Exception('Data cannot be empty');
        }

        $response = $this->client->put("/users/{$userId}", $data);
        return $response->getData();
    }
}
```

**Problemas Identificados**:
- ❌ HTTP calls diretas (sem abstração)
- ❌ Sem layer de repository
- ❌ Validação inconsistente
- ❌ Difícil de testar (mock do HttpClient)
- ❌ Business logic misturada com I/O
- ❌ Sem factory pattern

---

## 🛠️ Fase 2: Preparação

### 2.1 Backup e Branching

```bash
# Crie uma branch específica para migração
git checkout -b migration/user-management-hybrid-architecture

# Backup do módulo atual
cp -r src/Modules/UserManagement src/Modules/UserManagement.backup
```

### 2.2 Preparação do Ambiente

```bash
# Instale dependências de teste se necessário
composer require --dev mockery/mockery phpunit/phpunit

# Execute testes existentes para baseline
./vendor/bin/phpunit tests/Unit/UserManagement/

# Documente coverage atual
./vendor/bin/phpunit --coverage-html coverage/
```

### 2.3 Análise de Impacto

**Identifique Usos do Módulo**:
```bash
# Encontre onde o módulo é usado
grep -r "userManagement()" src/
grep -r "UserManagementModule" src/
grep -r "createUser\|getUser\|updateUser" src/
```

---

## 🚀 Fase 3: Implementação da Migração

### 3.1 Passo 1: Criar Repository Interface

```php
<?php
// src/Modules/UserManagement/Contracts/UserRepositoryInterface.php

namespace Clubify\Checkout\Modules\UserManagement\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

interface UserRepositoryInterface extends RepositoryInterface
{
    // Migre métodos existentes para interface
    public function findByEmail(string $email): ?array;
    public function findByTenant(string $tenantId, array $filters = []): array;
    public function updateProfile(string $userId, array $profileData): array;

    // Adicione métodos que estavam no módulo antigo
    public function changePassword(string $userId, string $newPassword): bool;
    public function activateUser(string $userId): bool;
    public function deactivateUser(string $userId): bool;
}
```

### 3.2 Passo 2: Implementar Repository

```php
<?php
// src/Modules/UserManagement/Repositories/ApiUserRepository.php

namespace Clubify\Checkout\Modules\UserManagement\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;

class ApiUserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected function getEndpoint(): string
    {
        return 'users';
    }

    protected function getResourceName(): string
    {
        return 'user';
    }

    protected function getServiceName(): string
    {
        return 'user-management';
    }

    // ✅ Migre lógica HTTP do módulo antigo para cá
    public function findByEmail(string $email): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("user:email:{$email}"),
            function () use ($email) {
                $response = $this->httpClient->get("/users/search", [
                    'email' => $email
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find user by email: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['users'][0] ?? null;
            },
            300
        );
    }

    // ... implemente outros métodos
}
```

### 3.3 Passo 3: Criar Service Layer

```php
<?php
// src/Modules/UserManagement/Services/UserService.php

namespace Clubify\Checkout\Modules\UserManagement\Services;

use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Modules\UserManagement\Contracts\UserRepositoryInterface;

class UserService implements ServiceInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private Logger $logger
    ) {}

    // ✅ Migre business logic do módulo antigo
    public function createUser(array $userData): array
    {
        $this->logger->info('Creating user', [
            'email' => $userData['email'] ?? 'unknown'
        ]);

        try {
            // ✅ Adicione validação que não existia
            $this->validateUserData($userData);

            // ✅ Use repository ao invés de HTTP direto
            $createdUser = $this->repository->create($userData);

            $this->logger->info('User created successfully', [
                'user_id' => $createdUser['id']
            ]);

            return [
                'success' => true,
                'user_id' => $createdUser['id'],
                'user' => $createdUser
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // ✅ Melhore métodos existentes
    public function getUser(string $userId): array
    {
        try {
            $user = $this->repository->findById($userId);

            if (!$user) {
                throw new UserNotFoundException("User with ID {$userId} not found");
            }

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (UserNotFoundException $e) {
            $this->logger->warning('User not found', ['user_id' => $userId]);
            throw $e;
        }
    }

    // ServiceInterface methods
    public function getName(): string { return 'user_service'; }
    public function getVersion(): string { return '2.0.0'; }
    public function isHealthy(): bool { /* ... */ }
    public function getMetrics(): array { /* ... */ }

    private function validateUserData(array $userData): void
    {
        // ✅ Adicione validação robusta
        if (empty($userData['email'])) {
            throw new UserValidationException('Email is required');
        }

        if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
            throw new UserValidationException('Invalid email format');
        }

        // Verificar duplicatas
        if ($this->repository->findByEmail($userData['email'])) {
            throw new UserValidationException('Email already exists');
        }
    }
}
```

### 3.4 Passo 4: Criar Factory

```php
<?php
// src/Modules/UserManagement/Factories/UserServiceFactory.php

namespace Clubify\Checkout\Modules\UserManagement\Factories;

use Clubify\Checkout\Contracts\FactoryInterface;
use Clubify\Checkout\Modules\UserManagement\Services\UserService;
use Clubify\Checkout\Modules\UserManagement\Repositories\ApiUserRepository;

class UserServiceFactory implements FactoryInterface
{
    private array $services = [];
    private array $repositories = [];

    public function __construct(
        private Configuration $config,
        private Logger $logger,
        private Client $httpClient,
        private CacheManagerInterface $cache,
        private EventDispatcherInterface $eventDispatcher
    ) {}

    public function create(string $type, array $config = []): object
    {
        if (isset($this->services[$type])) {
            return $this->services[$type];
        }

        switch ($type) {
            case 'user':
                $repository = $this->createRepository('user');
                $this->services[$type] = new UserService($repository, $this->logger);
                break;

            default:
                throw new \InvalidArgumentException("Service type '{$type}' is not supported");
        }

        return $this->services[$type];
    }

    public function getSupportedTypes(): array
    {
        return ['user'];
    }

    private function createRepository(string $type): object
    {
        if (isset($this->repositories[$type])) {
            return $this->repositories[$type];
        }

        $this->repositories[$type] = new ApiUserRepository(
            $this->config,
            $this->logger,
            $this->httpClient,
            $this->cache,
            $this->eventDispatcher
        );

        return $this->repositories[$type];
    }
}
```

### 3.5 Passo 5: Migrar Módulo Gradualmente

**Estratégia: Adapter Pattern para Compatibilidade**

```php
<?php
// src/Modules/UserManagement/UserManagementModule.php

class UserManagementModule implements ModuleInterface
{
    private bool $useNewArchitecture;

    // Dependências antigas (para compatibilidade)
    private HttpClient $client;

    // Dependências novas
    private ?UserServiceFactory $factory = null;
    private ?UserService $userService = null;

    public function __construct(private ClubifyCheckoutSDK $sdk)
    {
        // ✅ Feature flag para controlar migração
        $this->useNewArchitecture = $sdk->getConfig()->get('use_hybrid_architecture', false);

        // Manter compatibilidade com versão antiga
        if (!$this->useNewArchitecture) {
            $this->client = $sdk->getHttpClient();
        }
    }

    /**
     * ✅ Método migrado com fallback para compatibilidade
     */
    public function createUser(array $userData): array
    {
        if ($this->useNewArchitecture) {
            // Nova implementação
            return $this->getUserService()->createUser($userData);
        } else {
            // Implementação antiga (mantida para compatibilidade)
            return $this->createUserLegacy($userData);
        }
    }

    /**
     * ✅ Método migrado com fallback
     */
    public function getUser(string $userId): array
    {
        if ($this->useNewArchitecture) {
            return $this->getUserService()->getUser($userId);
        } else {
            return $this->getUserLegacy($userId);
        }
    }

    // Métodos da nova arquitetura
    private function getUserService(): UserService
    {
        if ($this->userService === null) {
            $this->userService = $this->getFactory()->create('user');
        }
        return $this->userService;
    }

    private function getFactory(): UserServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->createUserServiceFactory();
        }
        return $this->factory;
    }

    // Métodos legados (manter por enquanto)
    private function createUserLegacy(array $userData): array
    {
        $response = $this->client->post('users', $userData);
        return $response->getData();
    }

    private function getUserLegacy(string $userId): array
    {
        $response = $this->client->get("/users/{$userId}");
        return $response->getData();
    }
}
```

### 3.6 Passo 6: Atualizar SDK Principal

```php
<?php
// src/ClubifyCheckoutSDK.php

class ClubifyCheckoutSDK
{
    /**
     * ✅ Adicione factory method
     */
    public function createUserServiceFactory(): UserServiceFactory
    {
        return new UserServiceFactory(
            $this->config,
            $this->getLogger(),
            $this->getHttpClient(),
            $this->getCache(),
            $this->getEventDispatcher()
        );
    }

    /**
     * ✅ Método userManagement() continua funcionando igual
     */
    public function userManagement(): UserManagementModule
    {
        if ($this->userManagement === null) {
            $this->userManagement = new UserManagementModule($this);
            $this->userManagement->initialize($this->config, $this->getLogger());
        }
        return $this->userManagement;
    }
}
```

---

## 🧪 Fase 4: Testes e Validação

### 4.1 Criar Testes para Nova Arquitetura

```php
<?php
// tests/Unit/UserManagement/Services/UserServiceTest.php

class UserServiceTest extends TestCase
{
    public function testCreateUserWithNewArchitecture(): void
    {
        // Arrange
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $mockLogger = Mockery::mock(Logger::class);

        $mockRepository->shouldReceive('findByEmail')
            ->with('john@example.com')
            ->once()
            ->andReturn(null); // Não existe

        $mockRepository->shouldReceive('create')
            ->with($userData)
            ->once()
            ->andReturn(['id' => 'user_123', 'email' => 'john@example.com']);

        $mockLogger->shouldReceive('info')->twice();

        $userService = new UserService($mockRepository, $mockLogger);

        // Act
        $result = $userService->createUser($userData);

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('user_123', $result['user_id']);
    }
}
```

### 4.2 Testes de Compatibilidade

```php
<?php
// tests/Integration/UserManagementCompatibilityTest.php

class UserManagementCompatibilityTest extends TestCase
{
    public function testBackwardCompatibilityWithLegacyAPI(): void
    {
        // Test both old and new architecture return same results

        $config = [
            'credentials' => ['tenant_id' => 'test', 'api_key' => 'test'],
            'environment' => 'test'
        ];

        // Test with old architecture
        $config['use_hybrid_architecture'] = false;
        $sdkOld = new ClubifyCheckoutSDK($config);
        $resultOld = $sdkOld->userManagement()->createUser($userData);

        // Test with new architecture
        $config['use_hybrid_architecture'] = true;
        $sdkNew = new ClubifyCheckoutSDK($config);
        $resultNew = $sdkNew->userManagement()->createUser($userData);

        // Both should have same structure (backward compatible)
        $this->assertEquals($resultOld['user']['email'], $resultNew['user']['email']);

        // New architecture should have enhanced response
        $this->assertTrue($resultNew['success']);
        $this->assertArrayHasKey('user_id', $resultNew);
    }
}
```

### 4.3 Testes de Performance

```php
<?php

public function testPerformanceComparison(): void
{
    $iterations = 100;

    // Benchmark old architecture
    $startOld = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $this->sdkOld->userManagement()->getUser('user_123');
    }
    $timeOld = microtime(true) - $startOld;

    // Benchmark new architecture
    $startNew = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        $this->sdkNew->userManagement()->getUser('user_123');
    }
    $timeNew = microtime(true) - $startNew;

    // New architecture should be similar or better (with caching)
    $this->assertLessThanOrEqual($timeOld * 1.1, $timeNew,
        'New architecture should not be significantly slower');
}
```

---

## 🔧 Fase 5: Rollout e Cleanup

### 5.1 Rollout Gradual

**Etapa 1: Internal Testing**
```php
// Configuração para testes internos
$config = [
    'use_hybrid_architecture' => true, // Habilitado para testes
    'hybrid_rollout_percentage' => 0   // Ainda 0% para produção
];
```

**Etapa 2: Canary Release**
```php
// 10% dos usuários usam nova arquitetura
$config = [
    'use_hybrid_architecture' => true,
    'hybrid_rollout_percentage' => 10
];

// Lógica no módulo
public function createUser(array $userData): array
{
    $rolloutPercentage = $this->config->get('hybrid_rollout_percentage', 0);
    $userHash = crc32($userData['email'] ?? '') % 100;

    $useNewArchitecture = $this->useNewArchitecture &&
                         ($userHash < $rolloutPercentage);

    if ($useNewArchitecture) {
        return $this->getUserService()->createUser($userData);
    } else {
        return $this->createUserLegacy($userData);
    }
}
```

**Etapa 3: Full Rollout**
```php
// 100% nova arquitetura
$config = [
    'use_hybrid_architecture' => true,
    'hybrid_rollout_percentage' => 100
];
```

### 5.2 Monitoramento Durante Rollout

```php
<?php

class MigrationMetrics
{
    public function trackArchitectureUsage(string $method, bool $useNewArch): void
    {
        $this->metrics->increment('architecture_usage', 1, [
            'method' => $method,
            'architecture' => $useNewArch ? 'hybrid' : 'legacy'
        ]);
    }

    public function trackPerformance(string $method, float $duration, bool $useNewArch): void
    {
        $this->metrics->histogram('method_duration', $duration, [
            'method' => $method,
            'architecture' => $useNewArch ? 'hybrid' : 'legacy'
        ]);
    }

    public function trackErrors(string $method, \Exception $e, bool $useNewArch): void
    {
        $this->metrics->increment('architecture_errors', 1, [
            'method' => $method,
            'architecture' => $useNewArch ? 'hybrid' : 'legacy',
            'error_type' => get_class($e)
        ]);
    }
}
```

### 5.3 Cleanup Phase

**Depois de 30 dias com 100% rollout bem-sucedido:**

```php
<?php

class UserManagementModule implements ModuleInterface
{
    // ✅ Remove código legado
    public function __construct(private ClubifyCheckoutSDK $sdk) {}

    // ✅ Simplifica implementação
    public function createUser(array $userData): array
    {
        return $this->getUserService()->createUser($userData);
    }

    public function getUser(string $userId): array
    {
        return $this->getUserService()->getUser($userId);
    }

    // ✅ Remove métodos legados
    // private function createUserLegacy() -> DELETED
    // private function getUserLegacy() -> DELETED
}
```

**Remover configurações de feature flag:**
```php
// ✅ Remove do config
// 'use_hybrid_architecture' => REMOVED (sempre true agora)
// 'hybrid_rollout_percentage' => REMOVED
```

---

## 📊 Checklist de Migração Completa

### ✅ Pré-Migração
- [ ] Inventário completo do módulo atual
- [ ] Identificação de todos os usos externos
- [ ] Backup completo do código
- [ ] Baseline de testes funcionando
- [ ] Métricas de performance documentadas

### ✅ Implementação
- [ ] Repository Interface criada
- [ ] Repository Implementation funcional
- [ ] Service Layer implementado
- [ ] Factory Pattern aplicado
- [ ] Module refatorado com compatibilidade
- [ ] SDK atualizado com novos métodos

### ✅ Testing
- [ ] Testes unitários para Repository
- [ ] Testes unitários para Service
- [ ] Testes unitários para Factory
- [ ] Testes de integração fim-a-fim
- [ ] Testes de compatibilidade backward
- [ ] Testes de performance comparativos

### ✅ Rollout
- [ ] Feature flag implementado
- [ ] Canary release (10%) executado
- [ ] Métricas monitoradas por 7 dias
- [ ] Rollout gradual (25%, 50%, 75%)
- [ ] Full rollout (100%) por 30 dias
- [ ] Zero regressões confirmadas

### ✅ Cleanup
- [ ] Código legado removido
- [ ] Feature flags removidos
- [ ] Documentação atualizada
- [ ] Testes legados removidos
- [ ] Métricas de migração arquivadas

---

## 🚨 Troubleshooting Common Issues

### Issue 1: "Method not found" após migração

**Causa**: Método foi renomeado ou movido para service layer.

**Solução**:
```php
// Se método foi movido para service
public function oldMethodName(...$args) {
    return $this->getUserService()->newMethodName(...$args);
}
```

### Issue 2: Performance degradation

**Causa**: Múltiplas chamadas HTTP ou cache não configurado.

**Solução**:
```php
// Verificar se cache está habilitado
$this->cache->isEnabled();

// Batch operations quando possível
$users = $this->repository->findByIds($userIds); // Batch ao invés de loop
```

### Issue 3: Testes falhando

**Causa**: Mocks não atualizados para nova interface.

**Solução**:
```php
// Atualizar mocks para usar interfaces corretas
$mockRepository = Mockery::mock(UserRepositoryInterface::class);
// ao invés de
$mockHttpClient = Mockery::mock(HttpClient::class);
```

---

Este guia garante uma **migração segura**, **gradual** e **bem testada** para a nova arquitetura híbrida, mantendo **compatibilidade total** durante todo o processo! 🎉