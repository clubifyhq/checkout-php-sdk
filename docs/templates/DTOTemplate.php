<?php

/**
 * Template para DTO (Data Transfer Object) - Clubify Checkout SDK
 *
 * Este template define um DTO para transferir e validar dados de {Entity}.
 * Implementa validação, sanitização e transformação de dados.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua {Entity} pelo nome da entidade (ex: Order)
 * 2. Substitua {entity} pela versão lowercase (ex: order)
 * 3. Substitua {ModuleName} pelo nome do módulo (ex: OrderManagement)
 * 4. Defina os campos específicos da entidade
 * 5. Implemente as regras de validação
 *
 * EXEMPLO:
 * - {Entity} = Order
 * - {entity} = order
 * - {ModuleName} = OrderManagement
 */

namespace Clubify\Checkout\Modules\{ModuleName}\DTOs;

use Clubify\Checkout\Modules\{ModuleName}\Exceptions\{Entity}ValidationException;

/**
 * {Entity} Data Transfer Object
 *
 * Encapsula dados de {Entity} com validação e transformação:
 * - Validação de tipos de dados
 * - Sanitização de entrada
 * - Transformação para formato de API
 * - Regras de negócio específicas
 * - Compatibilidade com arrays e JSON
 *
 * Campos disponíveis:
 * - Add field descriptions here
 *
 * @package Clubify\Checkout\Modules\{ModuleName}\DTOs
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class {Entity}Data
{
    /**
     * {Entity} ID (for updates)
     */
    public ?string $id = null;

    /**
     * Define your entity fields here with proper types
     * Example fields - replace with actual entity fields:
     */

    /**
     * {Entity} name/title
     */
    public ?string $name = null;

    /**
     * {Entity} description
     */
    public ?string $description = null;

    /**
     * {Entity} status
     */
    public string $status = 'pending';

    /**
     * Tenant ID (multi-tenant support)
     */
    public ?string $tenantId = null;

    /**
     * Created timestamp
     */
    public ?string $createdAt = null;

    /**
     * Updated timestamp
     */
    public ?string $updatedAt = null;

    /**
     * Additional metadata
     */
    public array $metadata = [];

    // Add more fields specific to your entity

    /**
     * Valid status values
     */
    private const VALID_STATUSES = [
        'pending',
        'active',
        'completed',
        'cancelled'
        // Add your specific status values
    ];

    /**
     * Required fields for creation
     */
    private const REQUIRED_FIELDS = [
        'name',
        // Add your required fields
    ];

    /**
     * Fields that can be updated
     */
    private const UPDATABLE_FIELDS = [
        'name',
        'description',
        'status',
        'metadata'
        // Add your updatable fields
    ];

    /**
     * Maximum field lengths
     */
    private const FIELD_MAX_LENGTHS = [
        'name' => 255,
        'description' => 1000,
        'status' => 50
        // Add your field length limits
    ];

    /**
     * Constructor with optional data initialization
     *
     * @param array $data Initial data
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $this->fromArray($data);
        }
    }

    /**
     * Create instance from array data
     *
     * @param array $data {Entity} data
     * @return static New instance
     */
    public static function from(array $data): static
    {
        return new static($data);
    }

    /**
     * Populate object from array data
     *
     * @param array $data {Entity} data
     * @return $this
     */
    public function fromArray(array $data): static
    {
        // Sanitize and assign basic fields
        $this->id = $this->sanitizeString($data['id'] ?? null);
        $this->name = $this->sanitizeString($data['name'] ?? null);
        $this->description = $this->sanitizeString($data['description'] ?? null);
        $this->status = $this->sanitizeString($data['status'] ?? 'pending');
        $this->tenantId = $this->sanitizeString($data['tenant_id'] ?? $data['tenantId'] ?? null);
        $this->createdAt = $data['created_at'] ?? $data['createdAt'] ?? null;
        $this->updatedAt = $data['updated_at'] ?? $data['updatedAt'] ?? null;
        $this->metadata = $data['metadata'] ?? [];

        // Add more field assignments specific to your entity

        return $this;
    }

    /**
     * Convert to array format
     *
     * @param bool $includeNulls Include null values in output
     * @return array {Entity} data as array
     */
    public function toArray(bool $includeNulls = false): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'tenant_id' => $this->tenantId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'metadata' => $this->metadata
            // Add more fields specific to your entity
        ];

        if (!$includeNulls) {
            $data = array_filter($data, fn($value) => $value !== null);
        }

        return $data;
    }

    /**
     * Convert to JSON string
     *
     * @param bool $includeNulls Include null values
     * @return string JSON representation
     */
    public function toJson(bool $includeNulls = false): string
    {
        return json_encode($this->toArray($includeNulls), JSON_THROW_ON_ERROR);
    }

    /**
     * Get only changed fields for updates
     *
     * @param array $original Original data to compare against
     * @return array Changed fields only
     */
    public function getChangedFields(array $original = []): array
    {
        $current = $this->toArray(true);
        $changes = [];

        foreach (self::UPDATABLE_FIELDS as $field) {
            $currentValue = $current[$field] ?? null;
            $originalValue = $original[$field] ?? null;

            // Convert snake_case for comparison
            $snakeField = $this->toSnakeCase($field);
            $originalSnakeValue = $original[$snakeField] ?? null;

            if ($currentValue !== $originalValue && $currentValue !== $originalSnakeValue) {
                $changes[$snakeField] = $currentValue;
            }
        }

        return $changes;
    }

    /**
     * Validate {entity} data
     *
     * @param bool $isUpdate Whether this is an update operation
     * @throws {Entity}ValidationException When validation fails
     */
    public function validate(bool $isUpdate = false): void
    {
        $errors = [];

        // Required field validation (only for creation)
        if (!$isUpdate) {
            foreach (self::REQUIRED_FIELDS as $field) {
                $value = $this->$field ?? null;
                if (empty($value) && $value !== '0' && $value !== 0) {
                    $errors[$field] = "Field '{$field}' is required";
                }
            }
        }

        // Field-specific validations
        $errors = array_merge($errors, $this->validateFields());

        // Business rule validations
        $errors = array_merge($errors, $this->validateBusinessRules());

        if (!empty($errors)) {
            throw new {Entity}ValidationException('Validation failed', $errors);
        }
    }

    /**
     * Check if data is valid without throwing exception
     *
     * @param bool $isUpdate Whether this is an update
     * @return bool True if valid
     */
    public function isValid(bool $isUpdate = false): bool
    {
        try {
            $this->validate($isUpdate);
            return true;
        } catch ({Entity}ValidationException $e) {
            return false;
        }
    }

    /**
     * Get validation errors without throwing exception
     *
     * @param bool $isUpdate Whether this is an update
     * @return array Validation errors
     */
    public function getValidationErrors(bool $isUpdate = false): array
    {
        try {
            $this->validate($isUpdate);
            return [];
        } catch ({Entity}ValidationException $e) {
            return $e->getErrors();
        }
    }

    /**
     * Sanitize and prepare for API submission
     *
     * @return array Clean data for API
     */
    public function toApiFormat(): array
    {
        $data = $this->toArray();

        // Apply API-specific transformations
        $data = $this->applyApiTransformations($data);

        return $data;
    }

    /**
     * Create from API response data
     *
     * @param array $apiData Data from API response
     * @return static New instance
     */
    public static function fromApiResponse(array $apiData): static
    {
        // Transform API response data to internal format
        $data = static::transformFromApiFormat($apiData);
        return new static($data);
    }

    // ==============================================
    // PRIVATE VALIDATION METHODS
    // ==============================================

    /**
     * Validate individual fields
     *
     * @return array Field validation errors
     */
    private function validateFields(): array
    {
        $errors = [];

        // Name validation
        if ($this->name !== null) {
            if (empty(trim($this->name))) {
                $errors['name'] = 'Name cannot be empty';
            } elseif (strlen($this->name) > self::FIELD_MAX_LENGTHS['name']) {
                $errors['name'] = 'Name cannot exceed ' . self::FIELD_MAX_LENGTHS['name'] . ' characters';
            }
        }

        // Description validation
        if ($this->description !== null && strlen($this->description) > self::FIELD_MAX_LENGTHS['description']) {
            $errors['description'] = 'Description cannot exceed ' . self::FIELD_MAX_LENGTHS['description'] . ' characters';
        }

        // Status validation
        if ($this->status && !in_array($this->status, self::VALID_STATUSES)) {
            $errors['status'] = 'Invalid status. Must be one of: ' . implode(', ', self::VALID_STATUSES);
        }

        // Tenant ID validation
        if ($this->tenantId !== null && !$this->isValidUuid($this->tenantId)) {
            $errors['tenant_id'] = 'Invalid tenant ID format';
        }

        // Add more field validations specific to your entity

        return $errors;
    }

    /**
     * Validate business rules
     *
     * @return array Business rule validation errors
     */
    private function validateBusinessRules(): array
    {
        $errors = [];

        // Add business rule validations here
        // Example:
        // if ($this->status === 'completed' && empty($this->completedAt)) {
        //     $errors['completed_at'] = 'Completed date required when status is completed';
        // }

        return $errors;
    }

    /**
     * Apply API-specific transformations
     *
     * @param array $data Data to transform
     * @return array Transformed data
     */
    private function applyApiTransformations(array $data): array
    {
        // Add API-specific transformations
        // Example: Convert date formats, normalize values, etc.
        return $data;
    }

    /**
     * Transform data from API response format
     *
     * @param array $apiData API response data
     * @return array Internal format data
     */
    private static function transformFromApiFormat(array $apiData): array
    {
        // Transform API response to internal format
        // Example: Handle date formats, nested objects, etc.
        return $apiData;
    }

    // ==============================================
    // UTILITY METHODS
    // ==============================================

    /**
     * Sanitize string input
     *
     * @param mixed $value Input value
     * @return string|null Sanitized string or null
     */
    private function sanitizeString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * Check if string is valid UUID
     *
     * @param string $uuid UUID to validate
     * @return bool True if valid UUID
     */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid);
    }

    /**
     * Convert camelCase to snake_case
     *
     * @param string $input CamelCase string
     * @return string snake_case string
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Convert snake_case to camelCase
     *
     * @param string $input snake_case string
     * @return string camelCase string
     */
    private function toCamelCase(string $input): string
    {
        return lcfirst(str_replace('_', '', ucwords($input, '_')));
    }

    /**
     * Magic method for array access compatibility
     *
     * @param string $key Property name
     * @return mixed Property value
     */
    public function __get(string $key)
    {
        // Handle snake_case to camelCase conversion
        $camelKey = $this->toCamelCase($key);
        if (property_exists($this, $camelKey)) {
            return $this->$camelKey;
        }

        return null;
    }

    /**
     * Magic method for isset() compatibility
     *
     * @param string $key Property name
     * @return bool True if property exists and is not null
     */
    public function __isset(string $key): bool
    {
        $camelKey = $this->toCamelCase($key);
        return property_exists($this, $camelKey) && $this->$camelKey !== null;
    }

    /**
     * Magic method for debugging
     *
     * @return array Debug information
     */
    public function __debugInfo(): array
    {
        return $this->toArray(true);
    }
}