# 📖 Guidelines de Desenvolvimento - SDK Clubify Checkout

## 📋 Visão Geral

Este documento define os **padrões**, **convenções** e **boas práticas** para desenvolvimento no SDK Clubify Checkout. Seguir essas diretrizes garante **consistência**, **qualidade** e **manutenibilidade** do código.

---

## 🎯 Princípios Fundamentais

### 1. SOLID Principles

**Single Responsibility Principle (SRP)**
```php
// ❌ Ruim - múltiplas responsabilidades
class UserManager {
    public function createUser($data) { /* ... */ }
    public function sendEmail($user) { /* ... */ }
    public function validateInput($data) { /* ... */ }
}

// ✅ Bom - responsabilidade única
class UserService {
    public function createUser(array $userData): array { /* ... */ }
}

class EmailService {
    public function sendWelcomeEmail(User $user): void { /* ... */ }
}

class UserValidator {
    public function validate(array $userData): void { /* ... */ }
}
```

**Open/Closed Principle (OCP)**
```php
// ✅ Aberto para extensão, fechado para modificação
abstract class BaseRepository implements RepositoryInterface {
    // Funcionalidade base fechada para modificação
    public function findById(string $id): ?array { /* ... */ }
}

class ApiUserRepository extends BaseRepository {
    // Extensão específica
    protected function getEndpoint(): string { return 'users'; }
}
```

**Liskov Substitution Principle (LSP)**
```php
// ✅ Qualquer implementação de RepositoryInterface deve ser substituível
function processUser(UserRepositoryInterface $repo, string $id) {
    return $repo->findById($id); // Funciona com qualquer implementação
}

$apiRepo = new ApiUserRepository();
$cacheRepo = new CacheUserRepository();

processUser($apiRepo, '123');   // ✅ Funciona
processUser($cacheRepo, '123'); // ✅ Funciona
```

**Interface Segregation Principle (ISP)**
```php
// ❌ Ruim - interface muito ampla
interface UserManagerInterface {
    public function createUser(array $data): array;
    public function sendEmail(string $userId): void;
    public function generateReport(array $filters): array;
    public function exportToCsv(array $data): string;
}

// ✅ Bom - interfaces específicas
interface UserServiceInterface {
    public function createUser(array $data): array;
}

interface EmailServiceInterface {
    public function sendEmail(string $userId): void;
}

interface ReportServiceInterface {
    public function generateReport(array $filters): array;
    public function exportToCsv(array $data): string;
}
```

**Dependency Inversion Principle (DIP)**
```php
// ✅ Depende de abstração, não de implementação concreta
class UserService {
    public function __construct(
        private UserRepositoryInterface $repository, // Abstração
        private LoggerInterface $logger              // Abstração
    ) {}
}

// Injeção de dependência
$service = new UserService(
    new ApiUserRepository(),  // Implementação concreta
    new FileLogger()          // Implementação concreta
);
```

### 2. Clean Code Principles

**Nomes Significativos**
```php
// ❌ Ruim
class UM {
    public function cr($d) { /* ... */ }
}

// ✅ Bom
class UserManagementModule {
    public function createUser(array $userData): array { /* ... */ }
}
```

**Funções Pequenas e Focadas**
```php
// ❌ Ruim - função muito grande
public function processUser($userData) {
    // 50+ linhas de código
}

// ✅ Bom - funções pequenas e focadas
public function createUser(array $userData): array {
    $this->validateUserData($userData);
    $user = $this->buildUserObject($userData);
    return $this->saveUser($user);
}

private function validateUserData(array $userData): void { /* ... */ }
private function buildUserObject(array $userData): UserData { /* ... */ }
private function saveUser(UserData $user): array { /* ... */ }
```

---

## 📝 Convenções de Nomenclatura

### 1. Classes

| Tipo | Padrão | Exemplo |
|------|---------|---------|
| Module | `{ModuleName}Module` | `UserManagementModule` |
| Service | `{Entity}Service` | `UserService`, `OrderService` |
| Repository Interface | `{Entity}RepositoryInterface` | `UserRepositoryInterface` |
| Repository Implementation | `{Implementation}{Entity}Repository` | `ApiUserRepository`, `CacheUserRepository` |
| Factory | `{ModuleName}ServiceFactory` | `UserServiceFactory` |
| DTO | `{Entity}Data` | `UserData`, `OrderData` |
| Exception | `{Entity}{Type}Exception` | `UserNotFoundException`, `UserValidationException` |

### 2. Métodos

| Tipo | Padrão | Exemplo |
|------|---------|---------|
| CRUD Operations | `create{Entity}`, `get{Entity}`, `update{Entity}`, `delete{Entity}` | `createUser`, `getUser` |
| Finder Methods | `findBy{Field}`, `findBy{Criteria}` | `findByEmail`, `findByTenant` |
| Validation | `validate{Context}` | `validateUserData`, `validateBusinessRules` |
| Business Operations | `{verb}{Entity}{Context}` | `activateUser`, `changeUserStatus` |
| Helper Methods | `{verb}{Object}` | `sanitizeInput`, `formatResponse` |

### 3. Variáveis e Propriedades

```php
// ✅ Bom - camelCase para propriedades e variáveis
class UserService {
    private UserRepositoryInterface $userRepository;
    private LoggerInterface $logger;

    public function createUser(array $userData): array {
        $validatedData = $this->validateInput($userData);
        $createdUser = $this->userRepository->create($validatedData);
        return $createdUser;
    }
}
```

### 4. Constantes

```php
class UserService {
    // ✅ SCREAMING_SNAKE_CASE para constantes
    public const DEFAULT_STATUS = 'pending';
    public const MAX_RETRY_ATTEMPTS = 3;
    private const CACHE_TTL_SECONDS = 300;
}
```

---

## 🏗️ Padrões de Estrutura

### 1. Estrutura de Módulo

```
src/Modules/{ModuleName}/
├── {ModuleName}Module.php           # Entry point do módulo
├── Contracts/                       # Interfaces
│   ├── {Entity}RepositoryInterface.php
│   └── {Entity}ServiceInterface.php
├── Services/                        # Business logic
│   ├── {Entity}Service.php
│   └── {Additional}Service.php
├── Repositories/                    # Data access
│   ├── Api{Entity}Repository.php
│   ├── Cache{Entity}Repository.php
│   └── Composite{Entity}Repository.php
├── Factories/                       # Object creation
│   └── {ModuleName}ServiceFactory.php
├── DTOs/                           # Data transfer objects
│   ├── {Entity}Data.php
│   └── {Additional}Data.php
└── Exceptions/                     # Domain exceptions
    ├── {Entity}NotFoundException.php
    └── {Entity}ValidationException.php
```

### 2. Implementação de Repository

```php
class ApiUserRepository extends BaseRepository implements UserRepositoryInterface {
    // ✅ Métodos abstratos implementados
    protected function getEndpoint(): string { return 'users'; }
    protected function getResourceName(): string { return 'user'; }
    protected function getServiceName(): string { return 'user-management'; }

    // ✅ Implementação de métodos da interface
    public function findByEmail(string $email): ?array {
        return $this->getCachedOrExecute(
            $this->getCacheKey("user:email:{$email}"),
            fn() => $this->httpClient->get("users/search", ['email' => $email]),
            300
        );
    }

    // ✅ Métodos privados para organização
    private function validateUserId(string $userId): void {
        if (!$this->isValidUuid($userId)) {
            throw new InvalidArgumentException("Invalid user ID format: {$userId}");
        }
    }
}
```

### 3. Configuração de URLs e Endpoints

**IMPORTANTE:** Esta seção contém correções críticas para problemas de URL.

#### 3.1. Configuração do Base URL

```php
// ✅ Configuration.php - Suporte a múltiplas configurações
public function getBaseUrl(): string
{
    // Aceita múltiplos formatos de configuração
    $customUrl = $this->get('endpoints.base_url')
              ?? $this->get('api.base_url')
              ?? $this->get('base_url');

    if ($customUrl) {
        $normalizedUrl = rtrim($customUrl, '/');

        // Automaticamente adiciona /api/v1 se não estiver presente
        if (!str_ends_with($normalizedUrl, '/api/v1')) {
            $normalizedUrl .= '/api/v1';
        }

        return $normalizedUrl;
    }

    // Fallback para Environment padrão
    $environment = Environment::from($this->getEnvironment());
    return $environment->getBaseUrl();
}
```

#### 3.2. Endpoints Relativos (CRÍTICO)

```php
// ❌ ERRO - Caminho absoluto quebra o base_uri do Guzzle
class ApiUserRepository extends BaseRepository {
    protected function getEndpoint(): string {
        return 'users'; // ❌ Gera: https://checkout.svelve.com/users
    }
}

// ✅ CORRETO - Caminho relativo respeita o base_uri
class ApiUserRepository extends BaseRepository {
    protected function getEndpoint(): string {
        return 'users'; // ✅ Gera: https://checkout.svelve.com/api/v1/users
    }
}
```

#### 3.3. Chamadas HTTP nos Repositórios

```php
// ❌ ERRO - Paths absolutos em chamadas HTTP
public function findByEmail(string $email): ?array {
    $response = $this->httpClient->get("/users/search", ['email' => $email]);
    // ❌ Resultado: https://checkout.svelve.com/users/search (404)
}

// ✅ CORRETO - Paths relativos
public function findByEmail(string $email): ?array {
    $response = $this->httpClient->get("users/search", ['email' => $email]);
    // ✅ Resultado: https://checkout.svelve.com/api/v1/users/search (200/401)
}
```

#### 3.4. Configurações de Exemplo

```php
// ✅ Configuração recomendada
$config = [
    'credentials' => [
        'environment' => 'development'
    ],
    'endpoints' => [
        'base_url' => 'https://checkout.svelve.com' // SEM /api/v1
    ]
];

// ✅ Também funciona (para compatibilidade)
$config = [
    'api' => [
        'base_url' => 'https://checkout.svelve.com'
    ]
];
```

#### 3.5. Guzzle HTTP Client Behavior

O Guzzle trata URLs diferentes dependendo se começam com `/`:

```php
// Base URI: https://checkout.svelve.com/api/v1/

// Path absoluto (com /) - SUBSTITUI todo o path da base_uri
$client->get('/users/search');
// Resultado: https://checkout.svelve.com/users/search ❌

// Path relativo (sem /) - ADICIONA ao path da base_uri
$client->get('users/search');
// Resultado: https://checkout.svelve.com/api/v1/users/search ✅
```

### 4. Implementação de Service

```php
class UserService implements ServiceInterface {
    public function __construct(
        private UserRepositoryInterface $repository,
        private Logger $logger
    ) {}

    // ✅ Interface methods
    public function getName(): string { return 'user_service'; }
    public function getVersion(): string { return '2.0.0'; }
    public function isHealthy(): bool { /* ... */ }
    public function getMetrics(): array { /* ... */ }

    // ✅ Business methods
    public function createUser(array $userData): array {
        $this->logger->info('Creating user', ['email' => $userData['email'] ?? 'unknown']);

        try {
            $user = new UserData($userData);
            $user->validate();

            $this->validateBusinessRules($user);
            $createdUser = $this->repository->create($user->toArray());

            $this->postCreationProcessing($createdUser);

            return [
                'success' => true,
                'user_id' => $createdUser['id'],
                'user' => $createdUser
            ];
        } catch (UserValidationException $e) {
            $this->logger->warning('User validation failed', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors()
            ]);
            throw $e;
        }
    }

    // ✅ Private helper methods
    private function validateBusinessRules(UserData $user): void { /* ... */ }
    private function postCreationProcessing(array $user): void { /* ... */ }
}
```

---

## 🧪 Padrões de Testes

### 1. Estrutura de Testes

```
tests/
├── Unit/                           # Testes unitários
│   └── {ModuleName}/
│       ├── Services/
│       │   └── {Entity}ServiceTest.php
│       ├── Repositories/
│       │   └── Api{Entity}RepositoryTest.php
│       ├── Factories/
│       │   └── {ModuleName}ServiceFactoryTest.php
│       └── DTOs/
│           └── {Entity}DataTest.php
├── Integration/                    # Testes de integração
│   └── {ModuleName}IntegrationTest.php
└── TestCase.php                   # Base test class
```

### 2. Padrões de Teste Unitário

```php
class UserServiceTest extends TestCase {
    private UserService $userService;
    private UserRepositoryInterface $mockRepository;
    private Logger $mockLogger;

    protected function setUp(): void {
        parent::setUp();

        $this->mockRepository = Mockery::mock(UserRepositoryInterface::class);
        $this->mockLogger = Mockery::mock(Logger::class);

        $this->userService = new UserService($this->mockRepository, $this->mockLogger);
    }

    protected function tearDown(): void {
        Mockery::close();
    }

    public function testCreateUserSuccess(): void {
        // ✅ Arrange - setup test data
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        $expectedUser = array_merge($userData, ['id' => 'user_123']);

        // ✅ Mock setup with expectations
        $this->mockRepository
            ->shouldReceive('findByEmail')
            ->with('john@example.com')
            ->once()
            ->andReturn(null);

        $this->mockRepository
            ->shouldReceive('create')
            ->once()
            ->andReturn($expectedUser);

        $this->mockLogger
            ->shouldReceive('info')
            ->twice();

        // ✅ Act - execute the method
        $result = $this->userService->createUser($userData);

        // ✅ Assert - verify results
        $this->assertTrue($result['success']);
        $this->assertEquals('user_123', $result['user_id']);
        $this->assertEquals($expectedUser, $result['user']);
    }

    public function testCreateUserValidationFailure(): void {
        // ✅ Test error scenarios
        $invalidData = ['email' => 'invalid-email'];

        $this->mockLogger
            ->shouldReceive('info')
            ->once();

        $this->mockLogger
            ->shouldReceive('warning')
            ->once();

        $this->expectException(UserValidationException::class);
        $this->userService->createUser($invalidData);
    }
}
```

### 3. Padrões de Teste de Integração

```php
class UserManagementIntegrationTest extends TestCase {
    private ClubifyCheckoutSDK $sdk;
    private UserManagementModule $userManagement;

    protected function setUp(): void {
        parent::setUp();

        $config = [
            'credentials' => [
                'tenant_id' => 'test_tenant',
                'api_key' => 'test_api_key'
            ],
            'environment' => 'test'
        ];

        $this->sdk = new ClubifyCheckoutSDK($config);
        $this->sdk->initialize();

        $this->userManagement = $this->sdk->userManagement();
    }

    public function testFullUserLifecycle(): void {
        // ✅ Test complete workflow
        $userData = [
            'name' => 'Integration Test User',
            'email' => 'integration.test.' . uniqid() . '@example.com'
        ];

        // Create
        $createResult = $this->userManagement->createUser($userData);
        $this->assertTrue($createResult['success']);
        $userId = $createResult['user_id'];

        // Read
        $getResult = $this->userManagement->getUser($userId);
        $this->assertTrue($getResult['success']);
        $this->assertEquals($userData['email'], $getResult['user']['email']);

        // Update
        $updateResult = $this->userManagement->updateUser($userId, ['name' => 'Updated Name']);
        $this->assertTrue($updateResult['success']);

        // Delete
        $deleteResult = $this->userManagement->deleteUser($userId);
        $this->assertTrue($deleteResult['success']);
    }
}
```

---

## 📊 Padrões de Logging

### 1. Levels de Log

| Level | Uso | Exemplo |
|-------|-----|---------|
| `error` | Erros que precisam atenção | Falhas de API, exceptions não tratadas |
| `warning` | Problemas não críticos | Validação falhou, tentativa de retry |
| `info` | Informações importantes | Operação completada, milestones |
| `debug` | Informações detalhadas | Parâmetros de entrada, estados internos |

### 2. Structured Logging

```php
// ✅ Bom - structured logging com contexto
$this->logger->info('User created successfully', [
    'user_id' => $userId,
    'tenant_id' => $tenantId,
    'service' => $this->getName(),
    'operation' => 'create_user',
    'duration_ms' => $duration,
    'timestamp' => time()
]);

// ✅ Logging de erros com contexto completo
$this->logger->error('Failed to create user', [
    'error' => $e->getMessage(),
    'error_code' => $e->getCode(),
    'user_data' => $userData,
    'service' => $this->getName(),
    'stack_trace' => $e->getTraceAsString()
]);
```

### 3. Padrões de Log Messages

```php
class UserService {
    public function createUser(array $userData): array {
        // ✅ Start operation
        $this->logger->info('Creating user', [
            'email' => $userData['email'] ?? 'unknown'
        ]);

        try {
            // ... business logic

            // ✅ Success
            $this->logger->info('User created successfully', [
                'user_id' => $createdUser['id'],
                'email' => $createdUser['email']
            ]);

            return $result;
        } catch (UserValidationException $e) {
            // ✅ Business error
            $this->logger->warning('User validation failed', [
                'error' => $e->getMessage(),
                'errors' => $e->getErrors(),
                'user_data' => $userData
            ]);
            throw $e;
        } catch (Exception $e) {
            // ✅ Technical error
            $this->logger->error('Failed to create user', [
                'error' => $e->getMessage(),
                'user_data' => $userData
            ]);
            throw $e;
        }
    }
}
```

---

## 🔧 Error Handling

### 1. Exception Hierarchy

```php
// ✅ Domain exceptions específicas
class UserNotFoundException extends Exception {
    public static function byId(string $userId): static {
        return new static("User with ID {$userId} not found");
    }

    public static function byEmail(string $email): static {
        return new static("User with email {$email} not found");
    }
}

class UserValidationException extends Exception {
    private array $errors;

    public function __construct(string $message, array $errors = []) {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}
```

### 2. Error Handling Patterns

```php
class UserService {
    public function getUser(string $userId): array {
        try {
            $user = $this->repository->findById($userId);

            if (!$user) {
                throw UserNotFoundException::byId($userId);
            }

            return ['success' => true, 'user' => $user];

        } catch (UserNotFoundException $e) {
            // ✅ Re-throw domain exceptions
            $this->logger->warning('User not found', ['user_id' => $userId]);
            throw $e;

        } catch (HttpException $e) {
            // ✅ Convert technical exceptions to domain exceptions
            $this->logger->error('Failed to get user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to retrieve user');

        } catch (Exception $e) {
            // ✅ Handle unexpected exceptions
            $this->logger->error('Unexpected error getting user', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

---

## 📈 Performance Guidelines

### 1. Cache Strategy

```php
class ApiUserRepository extends BaseRepository {
    public function findById(string $id): ?array {
        // ✅ Cache pattern com TTL apropriado
        return $this->getCachedOrExecute(
            $this->getCacheKey("user:{$id}"),
            fn() => $this->httpClient->get("/users/{$id}"),
            300 // 5 minutes TTL
        );
    }

    public function findAll(array $filters = []): array {
        // ✅ Cache com hash dos filtros
        $cacheKey = $this->getCacheKey("user:list:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            fn() => $this->httpClient->get('users', $filters),
            180 // 3 minutes TTL para listas
        );
    }
}
```

### 2. Lazy Loading

```php
class UserManagementModule {
    private ?UserService $userService = null;

    // ✅ Lazy loading - criado apenas quando necessário
    private function getUserService(): UserService {
        if ($this->userService === null) {
            $this->userService = $this->getFactory()->create('user');
        }
        return $this->userService;
    }

    public function createUser(array $userData): array {
        // Service criado apenas quando método é chamado
        return $this->getUserService()->createUser($userData);
    }
}
```

### 3. Batch Operations

```php
class UserService {
    // ✅ Batch operations para performance
    public function createUsers(array $usersData): array {
        $validUsers = [];
        $errors = [];

        // Validate all first
        foreach ($usersData as $index => $userData) {
            try {
                $user = new UserData($userData);
                $user->validate();
                $validUsers[] = $user;
            } catch (UserValidationException $e) {
                $errors[$index] = $e->getErrors();
            }
        }

        if (!empty($errors)) {
            throw new UserValidationException('Bulk validation failed', $errors);
        }

        // Bulk create
        return $this->repository->bulkCreate(
            array_map(fn($user) => $user->toArray(), $validUsers)
        );
    }
}
```

---

## 🔒 Security Guidelines

### 1. Input Validation

```php
class UserData {
    public function validate(bool $isUpdate = false): void {
        $errors = [];

        // ✅ Validate required fields
        if (!$isUpdate && empty($this->name)) {
            $errors['name'] = 'Name is required';
        }

        // ✅ Validate format
        if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        // ✅ Validate length
        if ($this->name && strlen($this->name) > 255) {
            $errors['name'] = 'Name cannot exceed 255 characters';
        }

        // ✅ Validate business rules
        if ($this->status && !in_array($this->status, self::VALID_STATUSES)) {
            $errors['status'] = 'Invalid status';
        }

        if (!empty($errors)) {
            throw new UserValidationException('Validation failed', $errors);
        }
    }

    // ✅ Input sanitization
    private function sanitizeString($value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        return trim(strip_tags((string) $value));
    }
}
```

### 2. Output Security

```php
class UserService {
    public function getUser(string $userId): array {
        $user = $this->repository->findById($userId);

        // ✅ Remove sensitive data
        unset($user['password']);
        unset($user['internal_notes']);

        // ✅ Format for output
        return [
            'success' => true,
            'user' => $this->sanitizeForOutput($user)
        ];
    }

    private function sanitizeForOutput(array $data): array {
        // Remove null values, format dates, etc.
        return array_filter($data, fn($value) => $value !== null);
    }
}
```

---

## 📚 Documentação Guidelines

### 1. PHPDoc Standards

```php
/**
 * Create a new user with validation and business rules
 *
 * This method validates the input data, checks business rules,
 * and creates a new user through the repository layer.
 *
 * @param array $userData User data with name, email, etc.
 * @return array Result with success status and user data
 * @throws UserValidationException When validation fails
 * @throws UserNotFoundException When duplicate email found
 * @throws Exception When creation fails
 *
 * @example
 * $result = $service->createUser([
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com',
 *     'role' => 'user'
 * ]);
 */
public function createUser(array $userData): array {
    // Implementation
}
```

### 2. Code Comments

```php
class UserService {
    public function createUser(array $userData): array {
        // Validate input data first to fail fast
        $user = new UserData($userData);
        $user->validate();

        // Check business constraints (e.g., unique email)
        $this->validateBusinessConstraints($user);

        // Create through repository with automatic caching
        $createdUser = $this->repository->create($user->toArray());

        // Trigger post-creation workflows (emails, notifications, etc.)
        $this->postCreationProcessing($createdUser);

        return [
            'success' => true,
            'user_id' => $createdUser['id'],
            'user' => $createdUser
        ];
    }
}
```

---

## ✅ Code Review Checklist

### 1. Arquitetura e Design

- [ ] Seguiu os padrões arquiteturais (Repository + Factory)
- [ ] Implementou interfaces apropriadas
- [ ] Respeitou Single Responsibility Principle
- [ ] Usou dependency injection corretamente
- [ ] Aplicou Liskov Substitution Principle

### 2. Código

- [ ] Nomes são claros e significativos
- [ ] Funções são pequenas e focadas
- [ ] Não há duplicação de código
- [ ] Error handling apropriado
- [ ] Logging estruturado implementado

### 3. Testes

- [ ] Testes unitários cobrem casos principais
- [ ] Testes de erro implementados
- [ ] Mocks utilizados apropriadamente
- [ ] Testes de integração para fluxos principais
- [ ] Coverage adequado (>90%)

### 4. Performance

- [ ] Cache implementado onde apropriado
- [ ] Lazy loading utilizado
- [ ] Operações em lote consideradas
- [ ] N+1 queries evitadas

### 5. Security

- [ ] Input validation implementada
- [ ] Output sanitization aplicada
- [ ] Dados sensíveis removidos/masked
- [ ] SQL injection prevention (se aplicável)

### 6. Documentação

- [ ] PHPDoc completa e precisa
- [ ] Comments explicativos onde necessário
- [ ] README atualizado se necessário
- [ ] Exemplos de uso documentados

---

Seguir essas guidelines garante **consistência**, **qualidade** e **manutenibilidade** do código no SDK Clubify Checkout!