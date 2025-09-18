# 🏗️ Arquitetura do SDK Clubify Checkout

## 📋 Visão Geral

O SDK Clubify Checkout implementa uma **arquitetura híbrida robusta** que combina **Repository Pattern** + **Factory Method Pattern** para garantir:

- ✅ **Robustez Arquitetural** - Padrões consolidados e testados
- ✅ **Testabilidade Completa** - 100% testável com mocks
- ✅ **Manutenibilidade** - Código limpo e bem estruturado
- ✅ **Extensibilidade** - Fácil adição de novos módulos
- ✅ **Consistência** - Padrões uniformes em todo o SDK

---

## 🏛️ Padrões Arquiteturais

### 1. Repository Pattern

**Objetivo**: Abstrair a camada de dados e providenciar interface consistente para acesso aos dados.

**Benefícios**:
- **Testabilidade**: Fácil mock para testes unitários
- **Flexibilidade**: Múltiplas implementações (API, Cache, Database)
- **Manutenibilidade**: Lógica de acesso centralizada
- **Separação de Responsabilidades**: Business logic separada da persistência

**Implementação**:
```php
// Interface define o contrato
interface UserRepositoryInterface extends RepositoryInterface {
    public function findByEmail(string $email): ?array;
    public function findByTenant(string $tenantId): array;
}

// Implementação concreta com HTTP calls
class ApiUserRepository extends BaseRepository implements UserRepositoryInterface {
    protected function getEndpoint(): string { return '/users'; }

    public function findByEmail(string $email): ?array {
        // HTTP call implementation with cache
    }
}
```

### 2. Factory Method Pattern

**Objetivo**: Controlar a criação de objetos complexos com dependency injection.

**Benefícios**:
- **Encapsulamento**: Criação de objetos centralizada
- **Consistency**: Configuração uniforme de dependências
- **Extensibilidade**: Fácil adição de novos tipos
- **Singleton Management**: Reutilização inteligente de instâncias

**Implementação**:
```php
class UserServiceFactory implements FactoryInterface {
    public function create(string $type, array $config = []): object {
        switch ($type) {
            case 'user':
                $repository = $this->createRepository('user');
                return new UserService($repository, $this->logger);
        }
    }

    private function createRepository(string $type): object {
        return new ApiUserRepository(
            $this->config, $this->logger, $this->httpClient,
            $this->cache, $this->eventDispatcher
        );
    }
}
```

### 3. Dependency Injection

**Objetivo**: Implementar inversão de controle para baixo acoplamento.

**Benefícios**:
- **Testabilidade**: Fácil substituição por mocks
- **Acoplamento Fraco**: Componentes independentes
- **Flexibilidade**: Configuração externa de dependências
- **Manutenibilidade**: Mudanças isoladas

**Implementação**:
```php
// Constructor injection
class UserService implements ServiceInterface {
    public function __construct(
        private UserRepositoryInterface $repository,
        private Logger $logger
    ) {}
}

// Factory injection
$service = $factory->create('user'); // Todas as dependências injetadas
```

### 4. Strategy Pattern

**Objetivo**: Permitir múltiplas implementações de repositories.

**Exemplos**:
- `ApiUserRepository` - Chamadas HTTP para API
- `CacheUserRepository` - Cache-first implementation
- `CompositeUserRepository` - Fallback entre múltiplos repositórios

### 5. Chain of Responsibility

**Objetivo**: Implementar fallback entre diferentes implementações.

**Implementação**:
```php
class CompositeUserRepository implements UserRepositoryInterface {
    private array $repositories = [];

    public function findById(string $id): ?array {
        foreach ($this->repositories as $repo) {
            try {
                if ($result = $repo->findById($id)) {
                    return $result;
                }
            } catch (Exception $e) {
                // Continue to next repository
                continue;
            }
        }
        return null;
    }
}
```

### 6. Observer Pattern

**Objetivo**: Implementar eventos e notificações para auditoria.

**Implementação**:
```php
// No repository
$this->eventDispatcher->dispatch('Clubify.Checkout.User.Created', [
    'user_id' => $user['id'],
    'timestamp' => time()
]);

// No service
$this->postCreationProcessing($createdUser); // Trigger events
```

---

## 📁 Estrutura Arquitetural

### Visão em Camadas

```
┌─────────────────────────────────────┐
│           SDK LAYER                 │ ← ClubifyCheckoutSDK
├─────────────────────────────────────┤
│           MODULE LAYER              │ ← UserManagementModule
├─────────────────────────────────────┤
│          FACTORY LAYER              │ ← UserServiceFactory
├─────────────────────────────────────┤
│          SERVICE LAYER              │ ← UserService (Business Logic)
├─────────────────────────────────────┤
│         REPOSITORY LAYER            │ ← UserRepositoryInterface
├─────────────────────────────────────┤
│        IMPLEMENTATION LAYER         │ ← ApiUserRepository, CacheUserRepository
├─────────────────────────────────────┤
│           CORE LAYER                │ ← BaseService, BaseRepository, Client
└─────────────────────────────────────┘
```

### Fluxo de Dados

```
User Request
    ↓
SDK → Module → Factory → Service → Repository → API
    ↓                      ↓           ↓
  Logger                Events       Cache
```

### Estrutura de Diretórios

```
src/
├── Contracts/                          # 🔗 Interfaces Base
│   ├── ModuleInterface.php
│   ├── ServiceInterface.php
│   ├── RepositoryInterface.php
│   └── FactoryInterface.php
├── Core/                               # 🛠️ Componentes Centrais
│   ├── Repository/
│   │   └── BaseRepository.php          # CRUD + Cache + Events
│   ├── Factory/
│   │   └── RepositoryFactory.php       # Factory Pattern Base
│   └── Services/
│       └── BaseService.php             # Service Base
├── Modules/
│   └── {ModuleName}/                   # 🎯 Módulo Específico
│       ├── {ModuleName}Module.php      # Module Implementation
│       ├── Contracts/
│       │   ├── {Entity}RepositoryInterface.php
│       │   └── {Entity}ServiceInterface.php
│       ├── Services/
│       │   └── {Entity}Service.php     # Business Logic
│       ├── Repositories/
│       │   ├── Api{Entity}Repository.php    # HTTP Implementation
│       │   ├── Cache{Entity}Repository.php  # Cache Implementation
│       │   └── Composite{Entity}Repository.php # Fallback Chain
│       ├── Factories/
│       │   └── {ModuleName}ServiceFactory.php
│       ├── DTOs/
│       │   └── {Entity}Data.php        # Data Transfer Object
│       └── Exceptions/
│           ├── {Entity}NotFoundException.php
│           └── {Entity}ValidationException.php
└── ClubifyCheckoutSDK.php              # SDK Entry Point
```

---

## 🔧 Componentes Principais

### 1. Base Repository

**Responsabilidades**:
- Operações CRUD padronizadas
- Cache automático com TTL
- Event dispatching
- Metrics e monitoring
- Error handling consistente

**Características**:
```php
abstract class BaseRepository extends BaseService implements RepositoryInterface {
    // CRUD operations
    public function create(array $data): array;
    public function findById(string $id): ?array;
    public function update(string $id, array $data): array;
    public function delete(string $id): bool;

    // Advanced operations
    public function findAll(array $filters = [], array $options = []): array;
    public function count(array $filters = []): int;

    // Cache integration
    protected function getCachedOrExecute(string $key, callable $callback, int $ttl);

    // Metrics
    protected function executeWithMetrics(string $operation, callable $callback);
}
```

### 2. Service Layer

**Responsabilidades**:
- Business logic implementation
- Data validation
- Error handling específico do domínio
- Orchestração de operações complexas
- Integration com outros services

**Características**:
```php
class UserService implements ServiceInterface {
    // Service interface
    public function getName(): string;
    public function getVersion(): string;
    public function isHealthy(): bool;
    public function getMetrics(): array;

    // Business operations
    public function createUser(array $userData): array;
    public function updateUser(string $userId, array $userData): array;

    // Domain-specific operations
    public function changeUserStatus(string $userId, string $status): array;
    public function getUserRoles(string $userId): array;
}
```

### 3. Factory Pattern

**Responsabilidades**:
- Criação controlada de services
- Dependency injection automática
- Singleton management
- Configuration handling
- Statistics e monitoring

**Características**:
```php
class UserServiceFactory implements FactoryInterface {
    // Factory interface
    public function create(string $type, array $config = []): object;
    public function getSupportedTypes(): array;

    // Management
    public function clearCache(): void;
    public function getStats(): array;

    // Internal
    private function createRepository(string $type): object;
    private function resolveRepositoryClass(string $type): string;
}
```

### 4. Module Layer

**Responsabilidades**:
- API pública do módulo
- Lifecycle management
- Factory coordination
- Health monitoring
- Lazy loading de services

**Características**:
```php
class UserManagementModule implements ModuleInterface {
    // Module interface
    public function initialize(Configuration $config, Logger $logger): void;
    public function getName(): string;
    public function getVersion(): string;
    public function isHealthy(): bool;

    // Business facade
    public function createUser(array $userData): array;
    public function getUser(string $userId): array;

    // Internal management
    private function getFactory(): UserServiceFactory;
    private function getUserService(): UserService;
}
```

---

## 🔄 Fluxos de Operação

### Fluxo de Criação

```
1. SDK.userManagement().createUser($data)
2. UserManagementModule.createUser($data)
3. UserManagementModule.getUserService() → Factory.create('user')
4. UserService.createUser($data)
5. UserService validates data → UserData.validate()
6. UserService.repository.create($data)
7. ApiUserRepository.create($data) → HTTP POST
8. BaseRepository dispatches events
9. Response bubbles back through layers
```

### Fluxo de Consulta com Cache

```
1. SDK.userManagement().getUser($id)
2. UserManagementModule.getUser($id)
3. UserService.getUser($id)
4. ApiUserRepository.findById($id)
5. BaseRepository.getCachedOrExecute()
6. Cache HIT → return cached data
7. Cache MISS → HTTP GET → cache result
8. Response formatted through layers
```

### Fluxo de Error Handling

```
1. HTTP call fails in Repository
2. Repository throws HttpException
3. Service catches, logs, and re-throws domain exception
4. Module catches and formats for API response
5. SDK returns structured error response
```

---

## 🧪 Estratégia de Testes

### 1. Testes Unitários - Repository

```php
class ApiUserRepositoryTest extends TestCase {
    public function testCreateUserSuccess(): void {
        // Mock HTTP client
        $this->httpClient->shouldReceive('post')
            ->with('/users', $userData)
            ->andReturn($mockResponse);

        // Test repository
        $result = $this->repository->create($userData);

        // Assertions
        $this->assertArrayHasKey('id', $result);
    }
}
```

### 2. Testes Unitários - Service

```php
class UserServiceTest extends TestCase {
    public function testCreateUserWithValidation(): void {
        // Mock repository
        $this->mockRepository->shouldReceive('findByEmail')
            ->andReturn(null);
        $this->mockRepository->shouldReceive('create')
            ->andReturn($expectedUser);

        // Test service
        $result = $this->userService->createUser($userData);

        // Assertions
        $this->assertTrue($result['success']);
    }
}
```

### 3. Testes de Integração

```php
class UserManagementIntegrationTest extends TestCase {
    public function testFullUserLifecycle(): void {
        // Real SDK with mocked HTTP
        $sdk = new ClubifyCheckoutSDK($testConfig);

        // Test complete flow
        $createResult = $sdk->userManagement()->createUser($userData);
        $getResult = $sdk->userManagement()->getUser($createResult['user_id']);

        // Verify integration
        $this->assertEquals($userData['email'], $getResult['user']['email']);
    }
}
```

---

## 📊 Performance e Cache

### Estratégia Multi-Layer Cache

```
L1: In-Memory (Service Layer)    → 100ms TTL
L2: Redis/Memcached (Repository) → 5-15min TTL
L3: HTTP Cache Headers (API)     → 1-60min TTL
```

### Cache Keys Strategy

```php
// Pattern: {resource}:{operation}:{identifier}:{hash}
"user:id:123"                    // Single user by ID
"user:email:john@example.com"    // User by email
"user:list:abc123def456"         // List with filter hash
"user:stats:tenant_123"          // Statistics by tenant
```

### Performance Optimizations

1. **Lazy Loading**: Services criados apenas quando necessários
2. **Singleton Pattern**: Repositories reutilizados
3. **Bulk Operations**: Suporte a operações em lote
4. **Query Optimization**: Filtros eficientes
5. **Connection Pooling**: Reutilização de conexões HTTP

---

## 🛡️ Segurança e Compliance

### 1. Data Protection

- **Input Sanitization**: Todos os inputs sanitizados
- **Output Escaping**: Dados escapados na saída
- **SQL Injection Prevention**: Prepared statements
- **XSS Protection**: Output encoding

### 2. Authentication & Authorization

- **API Key Management**: Seguro e rotativo
- **Token Validation**: JWT validation
- **Permission Checking**: RBAC implementation
- **Audit Logging**: Todas as operações logadas

### 3. Compliance

- **GDPR**: Right to be forgotten, data portability
- **PCI DSS**: Secure payment data handling
- **SOC 2**: Security controls implementation
- **Audit Trails**: Immutable logging

---

## 📈 Monitoring e Observabilidade

### 1. Logging Strategy

```php
// Structured logging with context
$this->logger->info('User created successfully', [
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'service' => $this->getName(),
    'operation' => 'create_user',
    'duration_ms' => $duration,
    'timestamp' => time()
]);
```

### 2. Metrics Collection

- **Business Metrics**: User operations, conversion rates
- **Technical Metrics**: Response times, error rates
- **Infrastructure Metrics**: Memory usage, HTTP connections
- **Custom Metrics**: Domain-specific measurements

### 3. Health Checks

```php
public function isHealthy(): bool {
    try {
        // Test critical dependencies
        $this->repository->count(); // Database connectivity
        return true;
    } catch (Exception $e) {
        $this->logger->error('Health check failed', [
            'error' => $e->getMessage(),
            'service' => $this->getName()
        ]);
        return false;
    }
}
```

---

## 🚀 Extensibilidade e Evolução

### 1. Adicionando Novos Módulos

1. **Copiar Templates**: Usar templates da pasta `docs/templates/`
2. **Implementar Interfaces**: Repository, Service, Factory
3. **Adicionar ao SDK**: Método de acesso no SDK principal
4. **Implementar Testes**: Unit + Integration tests
5. **Documentar APIs**: Swagger/OpenAPI specs

### 2. Versionamento e Compatibilidade

- **Semantic Versioning**: Major.Minor.Patch
- **Backward Compatibility**: Manter APIs existentes
- **Deprecation Strategy**: Warnings + migration guides
- **Feature Flags**: Rollout controlado de features

### 3. Plugin Architecture

```php
// Extensibility hooks
interface RepositoryPluginInterface {
    public function beforeCreate(array $data): array;
    public function afterCreate(array $result): array;
}

// Plugin registration
$repository->addPlugin(new AuditPlugin());
$repository->addPlugin(new ValidationPlugin());
```

---

## 📚 Referências e Padrões

### Design Patterns Utilizados

- **Repository Pattern** - Data Access Layer
- **Factory Method** - Object Creation
- **Strategy Pattern** - Algorithm Selection
- **Chain of Responsibility** - Request Processing
- **Observer Pattern** - Event Handling
- **Singleton Pattern** - Resource Management
- **Facade Pattern** - Simplified Interface

### Princípios SOLID

- **S** - Single Responsibility: Cada classe tem uma responsabilidade
- **O** - Open/Closed: Aberto para extensão, fechado para modificação
- **L** - Liskov Substitution: Substituição transparente de implementações
- **I** - Interface Segregation: Interfaces específicas e coesas
- **D** - Dependency Inversion: Dependências em abstrações

### Clean Architecture

- **Independence**: Framework, UI, Database independence
- **Testability**: Business rules testáveis
- **UI Independence**: Business rules não dependem da UI
- **Database Independence**: Business rules não dependem do banco
- **External Agency Independence**: Business rules isoladas

---

Esta arquitetura garante que o SDK Clubify Checkout seja **robusto**, **testável**, **extensível** e **maintível** para suportar o crescimento da plataforma a longo prazo.