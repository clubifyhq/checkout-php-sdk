# 📡 API Contracts & Interface Documentation

## 📋 Visão Geral

Este documento define todos os **contratos**, **interfaces** e **APIs** utilizados na arquitetura híbrida do SDK Clubify Checkout. Serve como referência definitiva para implementação e manutenção.

---

## 🔗 Core Interfaces

### 1. ModuleInterface

**Localização**: `src/Contracts/ModuleInterface.php`

**Propósito**: Define o contrato base que todos os módulos do SDK devem seguir.

```php
interface ModuleInterface
{
    /**
     * Initialize module with configuration and logger
     */
    public function initialize(Configuration $config, Logger $logger): void;

    /**
     * Check if module is initialized
     */
    public function isInitialized(): bool;

    /**
     * Get module name (snake_case)
     */
    public function getName(): string;

    /**
     * Get module version (semantic versioning)
     */
    public function getVersion(): string;

    /**
     * Get module dependencies
     */
    public function getDependencies(): array;

    /**
     * Check if module is available for use
     */
    public function isAvailable(): bool;

    /**
     * Get module status information
     */
    public function getStatus(): array;

    /**
     * Cleanup module resources
     */
    public function cleanup(): void;

    /**
     * Check if module is healthy
     */
    public function isHealthy(): bool;
}
```

**Status Response Format**:
```php
[
    'name' => 'user_management',
    'version' => '2.0.0',
    'initialized' => true,
    'available' => true,
    'services_loaded' => [
        'user' => true,
        'auth' => false
    ],
    'factory_stats' => [...],
    'timestamp' => 1640995200
]
```

### 2. ServiceInterface

**Localização**: `src/Contracts/ServiceInterface.php`

**Propósito**: Define o contrato para todos os services de business logic.

```php
interface ServiceInterface
{
    /**
     * Get service name (snake_case)
     */
    public function getName(): string;

    /**
     * Get service version (semantic versioning)
     */
    public function getVersion(): string;

    /**
     * Check if service is healthy
     */
    public function isHealthy(): bool;

    /**
     * Get service metrics and monitoring data
     */
    public function getMetrics(): array;
}
```

**Metrics Response Format**:
```php
[
    'service' => 'user_service',
    'version' => '2.0.0',
    'healthy' => true,
    'repository_type' => 'ApiUserRepository',
    'config' => [...],
    'timestamp' => 1640995200
]
```

### 3. RepositoryInterface

**Localização**: `src/Contracts/RepositoryInterface.php`

**Propósito**: Define operações CRUD básicas para todos os repositories.

```php
interface RepositoryInterface
{
    /**
     * Create new entity
     */
    public function create(array $data): array;

    /**
     * Find entity by ID
     */
    public function findById(string $id): ?array;

    /**
     * Update existing entity
     */
    public function update(string $id, array $data): array;

    /**
     * Delete entity
     */
    public function delete(string $id): bool;

    /**
     * Find all entities with filters
     */
    public function findAll(array $filters = [], array $options = []): array;

    /**
     * Count entities with filters
     */
    public function count(array $filters = []): int;

    /**
     * Check if entity exists
     */
    public function exists(string $id): bool;
}
```

**Standard Response Formats**:

*Single Entity Response*:
```php
[
    'id' => 'entity_123',
    'name' => 'Entity Name',
    'status' => 'active',
    'created_at' => '2024-01-01T00:00:00Z',
    'updated_at' => '2024-01-01T00:00:00Z',
    // ... entity-specific fields
]
```

*Collection Response*:
```php
[
    'data' => [
        ['id' => 'entity_1', /* ... */],
        ['id' => 'entity_2', /* ... */]
    ],
    'pagination' => [
        'current_page' => 1,
        'per_page' => 20,
        'total' => 100,
        'last_page' => 5
    ]
]
```

### 4. FactoryInterface

**Localização**: `src/Contracts/FactoryInterface.php`

**Propósito**: Define o contrato para factories de criação de objetos.

```php
interface FactoryInterface
{
    /**
     * Create object by type
     */
    public function create(string $type, array $config = []): object;

    /**
     * Get supported object types
     */
    public function getSupportedTypes(): array;
}
```

---

## 🏗️ Domain-Specific Interfaces

### UserRepositoryInterface

**Localização**: `src/Modules/UserManagement/Contracts/UserRepositoryInterface.php`

```php
interface UserRepositoryInterface extends RepositoryInterface
{
    /**
     * Find user by email address
     */
    public function findByEmail(string $email): ?array;

    /**
     * Find users by tenant ID
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update user profile data
     */
    public function updateProfile(string $userId, array $profileData): array;

    /**
     * Change user password
     */
    public function changePassword(string $userId, string $newPassword): bool;

    /**
     * Activate user account
     */
    public function activateUser(string $userId): bool;

    /**
     * Deactivate user account
     */
    public function deactivateUser(string $userId): bool;

    /**
     * Get user roles and permissions
     */
    public function getUserRoles(string $userId): array;

    /**
     * Assign role to user
     */
    public function assignRole(string $userId, string $role): bool;

    /**
     * Remove role from user
     */
    public function removeRole(string $userId, string $role): bool;
}
```

**Method-Specific Response Formats**:

*User Entity*:
```php
[
    'id' => 'user_123',
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'status' => 'active',
    'tenant_id' => 'tenant_456',
    'profile' => [
        'bio' => 'User bio',
        'avatar_url' => 'https://example.com/avatar.jpg'
    ],
    'created_at' => '2024-01-01T00:00:00Z',
    'updated_at' => '2024-01-01T00:00:00Z'
]
```

*User Roles Response*:
```php
[
    'user_id' => 'user_123',
    'roles' => ['user', 'admin'],
    'permissions' => ['read', 'write', 'delete']
]
```

---

## 📊 Service API Contracts

### UserService Public Methods

**Localização**: `src/Modules/UserManagement/Services/UserService.php`

#### Create User
```php
public function createUser(array $userData): array
```

**Input Format**:
```php
[
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'securePassword123',
    'role' => 'user',
    'tenant_id' => 'tenant_123'
]
```

**Success Response**:
```php
[
    'success' => true,
    'user_id' => 'user_123',
    'user' => [
        'id' => 'user_123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'pending',
        'created_at' => '2024-01-01T00:00:00Z'
    ]
]
```

**Error Response**:
```php
// Throws UserValidationException with:
[
    'message' => 'Validation failed',
    'errors' => [
        'email' => 'Email already exists',
        'password' => 'Password too weak'
    ]
]
```

#### Get User
```php
public function getUser(string $userId): array
```

**Success Response**:
```php
[
    'success' => true,
    'user' => [
        'id' => 'user_123',
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'status' => 'active',
        'tenant_id' => 'tenant_456',
        'profile' => [...],
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-01T12:00:00Z'
    ]
]
```

**Error Response**:
```php
// Throws UserNotFoundException
```

#### Update User
```php
public function updateUser(string $userId, array $userData): array
```

**Input Format**:
```php
[
    'name' => 'Jane Doe',
    'profile' => [
        'bio' => 'Updated bio'
    ]
]
```

**Success Response**:
```php
[
    'success' => true,
    'user_id' => 'user_123',
    'user' => [
        // Updated user data
    ],
    'updated_at' => '2024-01-01T12:00:00Z'
]
```

#### List Users
```php
public function listUsers(array $filters = []): array
```

**Filter Options**:
```php
[
    'status' => 'active',
    'tenant_id' => 'tenant_123',
    'role' => 'user',
    'limit' => 20,
    'offset' => 0,
    'sort' => 'created_at',
    'order' => 'desc'
]
```

**Success Response**:
```php
[
    'success' => true,
    'users' => [
        ['id' => 'user_1', /* ... */],
        ['id' => 'user_2', /* ... */]
    ],
    'total' => 150,
    'filters' => [
        'status' => 'active',
        'tenant_id' => 'tenant_123'
    ],
    'pagination' => [
        'current_page' => 1,
        'per_page' => 20,
        'total' => 150,
        'last_page' => 8
    ]
]
```

---

## 🏭 Factory Contracts

### UserServiceFactory

**Localização**: `src/Modules/UserManagement/Factories/UserServiceFactory.php`

#### Supported Types
```php
public function getSupportedTypes(): array
```

**Response**:
```php
['user', 'auth', 'passkey', 'tenant', 'role', 'session']
```

#### Create Service
```php
public function create(string $type, array $config = []): object
```

**Supported Types and Returns**:

| Type | Returns | Description |
|------|---------|-------------|
| `user` | `UserService` | Main user management service |
| `auth` | `AuthService` | Authentication service |
| `passkey` | `PasskeyService` | Passkey/WebAuthn service |

#### Factory Statistics
```php
public function getStats(): array
```

**Response Format**:
```php
[
    'supported_types' => 6,
    'created_services' => 2,
    'created_repositories' => 2,
    'types' => ['user', 'auth', 'passkey', 'tenant', 'role', 'session'],
    'created_service_types' => ['user', 'auth'],
    'created_repository_types' => ['user', 'auth'],
    'cached_services' => 2,
    'cached_repositories' => 2,
    'memory_usage' => 1024000,
    'timestamp' => 1640995200
]
```

---

## 🎯 DTO Contracts

### UserData

**Localização**: `src/Modules/UserManagement/DTOs/UserData.php`

#### Validation Rules

**Required Fields (Create)**:
- `name`: string, max 255 chars
- `email`: valid email format, unique
- `password`: min 8 chars (creation only)

**Optional Fields**:
- `role`: enum ['user', 'admin', 'manager']
- `status`: enum ['pending', 'active', 'inactive']
- `tenant_id`: UUID format
- `profile`: array with bio, avatar_url

#### Methods

**From Array**:
```php
public function fromArray(array $data): static
```

**To Array**:
```php
public function toArray(bool $includeNulls = false): array
```

**Validate**:
```php
public function validate(bool $isUpdate = false): void
// Throws UserValidationException on failure
```

**Get Changed Fields**:
```php
public function getChangedFields(array $original = []): array
```

**To API Format**:
```php
public function toApiFormat(): array
```

---

## ⚠️ Exception Contracts

### UserNotFoundException

**Localização**: `src/Modules/UserManagement/Exceptions/UserNotFoundException.php`

#### Static Factory Methods

```php
// By ID
UserNotFoundException::byId(string $userId, array $context = [])

// By Field
UserNotFoundException::byField(string $field, $value, array $context = [])

// By Criteria
UserNotFoundException::byCriteria(array $criteria, array $context = [])

// Access Denied
UserNotFoundException::accessDenied(string $userId, string $reason = '', array $context = [])
```

#### Methods

```php
public function getErrorType(): string;
public function getContext(): array;
public function getUserId(): ?string;
public function getSearchCriteria(): array;
public function toArray(): array;
public function getSuggestions(): array;
```

**Exception Response Format**:
```php
[
    'error' => true,
    'error_type' => 'user_not_found',
    'error_code' => 404,
    'message' => 'User with ID user_123 not found',
    'context' => ['user_id' => 'user_123'],
    'search_criteria' => ['id' => 'user_123'],
    'suggestions' => [
        'Verify the user ID or search criteria',
        'Check if the user exists in the system'
    ],
    'timestamp' => 1640995200
]
```

### UserValidationException

**Localização**: `src/Modules/UserManagement/Exceptions/UserValidationException.php`

#### Static Factory Methods

```php
// Single Field Error
UserValidationException::forField(string $field, string $message, string $errorType = '', array $context = [])

// Multiple Field Errors
UserValidationException::forFields(array $errors, array $context = [], array $failedRules = [])

// Required Field
UserValidationException::requiredField(string $field, array $context = [])

// Invalid Format
UserValidationException::invalidFormat(string $field, string $expectedFormat, $actualValue, array $context = [])

// Business Rule Violation
UserValidationException::businessRule(string $rule, string $message, array $context = [])
```

#### Methods

```php
public function getErrors(): array;
public function getContext(): array;
public function getFailedRules(): array;
public function hasFieldError(string $field): bool;
public function getFieldError(string $field): ?string;
public function addError(string $field, string $message, string $errorType = ''): self;
public function toArray(): array;
```

**Validation Exception Response Format**:
```php
[
    'error' => true,
    'error_type' => 'validation_failed',
    'error_code' => 422,
    'message' => 'Validation failed for 2 field(s): email, password',
    'validation_errors' => [
        [
            'field' => 'email',
            'message' => 'Email already exists',
            'error_types' => ['duplicate_value'],
            'suggestions' => ['Use a unique value for email']
        ],
        [
            'field' => 'password',
            'message' => 'Password must be at least 8 characters',
            'error_types' => ['invalid_length'],
            'suggestions' => ['Adjust the length of password']
        ]
    ],
    'failed_rules' => [
        'email' => ['duplicate_value'],
        'password' => ['invalid_length']
    ],
    'error_count' => 2,
    'fields_with_errors' => ['email', 'password'],
    'suggestions' => [
        'Ensure all required fields are provided',
        'Check field formats and data types'
    ],
    'timestamp' => 1640995200
]
```

---

## 🔄 Event Contracts

### Event Dispatching

**Dispatcher Interface**:
```php
interface EventDispatcherInterface
{
    public function dispatch(string $eventName, array $payload): void;
}
```

### Standard Events

#### User Management Events

| Event Name | Payload | Trigger |
|------------|---------|---------|
| `Clubify.Checkout.User.Created` | `['user_id', 'email', 'tenant_id', 'timestamp']` | After user creation |
| `Clubify.Checkout.User.Updated` | `['user_id', 'updated_fields', 'timestamp']` | After user update |
| `Clubify.Checkout.User.Deleted` | `['user_id', 'timestamp']` | After user deletion |
| `Clubify.Checkout.User.StatusChanged` | `['user_id', 'old_status', 'new_status', 'timestamp']` | Status change |
| `Clubify.Checkout.User.RoleAssigned` | `['user_id', 'role', 'timestamp']` | Role assignment |

**Event Payload Format**:
```php
[
    'user_id' => 'user_123',
    'email' => 'john@example.com',
    'tenant_id' => 'tenant_456',
    'timestamp' => 1640995200,
    'metadata' => [
        'source' => 'user_service',
        'version' => '2.0.0',
        'request_id' => 'req_789'
    ]
]
```

---

## 📋 HTTP API Contracts

### Repository HTTP Calls

#### User Endpoints

| Method | Endpoint | Purpose | Response |
|--------|----------|---------|----------|
| `GET` | `/users/{id}` | Get user by ID | User entity |
| `POST` | `/users` | Create user | Created user |
| `PUT` | `/users/{id}` | Update user | Updated user |
| `DELETE` | `/users/{id}` | Delete user | `204 No Content` |
| `GET` | `/users` | List users | Users collection |
| `GET` | `/users/search` | Search users | Users collection |
| `PATCH` | `/users/{id}/status` | Update status | User entity |
| `GET` | `/users/{id}/roles` | Get user roles | Roles data |
| `POST` | `/users/{id}/roles` | Assign role | Success response |
| `DELETE` | `/users/{id}/roles/{role}` | Remove role | `204 No Content` |

#### Standard HTTP Response Codes

| Code | Meaning | Usage |
|------|---------|-------|
| `200` | OK | Successful GET, PUT, PATCH |
| `201` | Created | Successful POST |
| `204` | No Content | Successful DELETE |
| `400` | Bad Request | Invalid request format |
| `401` | Unauthorized | Authentication required |
| `403` | Forbidden | Insufficient permissions |
| `404` | Not Found | Entity not found |
| `409` | Conflict | Duplicate entity |
| `422` | Unprocessable Entity | Validation errors |
| `500` | Internal Server Error | Server error |

---

## 🧪 Testing Contracts

### Mock Interface Standards

**Repository Mocking**:
```php
$mockRepository = Mockery::mock(UserRepositoryInterface::class);

$mockRepository->shouldReceive('findById')
    ->with('user_123')
    ->once()
    ->andReturn($userData);

$mockRepository->shouldReceive('create')
    ->with(Mockery::type('array'))
    ->once()
    ->andReturn($createdUser);
```

**Service Mocking**:
```php
$mockService = Mockery::mock(UserService::class);

$mockService->shouldReceive('createUser')
    ->with($userData)
    ->once()
    ->andReturn([
        'success' => true,
        'user_id' => 'user_123',
        'user' => $userData
    ]);
```

### Test Response Standards

**Success Test Assert Pattern**:
```php
// Assert success response structure
$this->assertTrue($result['success']);
$this->assertArrayHasKey('user_id', $result);
$this->assertArrayHasKey('user', $result);
$this->assertEquals('user_123', $result['user_id']);
```

**Exception Test Pattern**:
```php
$this->expectException(UserNotFoundException::class);
$this->expectExceptionMessage('User with ID user_123 not found');
$this->service->getUser('user_123');
```

---

Esta documentação serve como **contrato definitivo** para todas as interfaces e APIs do SDK Clubify Checkout, garantindo **consistência** e **compatibilidade** entre todos os componentes da arquitetura híbrida!