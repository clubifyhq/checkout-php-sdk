<?php

/**
 * Template para ValidationException - Clubify Checkout SDK
 *
 * Este template define uma exceção específica para erros de validação.
 * Contém informações detalhadas sobre campos inválidos e regras violadas.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua {Entity} pelo nome da entidade (ex: Order)
 * 2. Substitua {entity} pela versão lowercase (ex: order)
 * 3. Substitua {ModuleName} pelo nome do módulo (ex: OrderManagement)
 * 4. Customize as mensagens de erro conforme necessário
 *
 * EXEMPLO:
 * - {Entity} = Order
 * - {entity} = order
 * - {ModuleName} = OrderManagement
 */

namespace Clubify\Checkout\Modules\{ModuleName}\Exceptions;

/**
 * {Entity} Validation Exception
 *
 * Thrown when {entity} data fails validation:
 * - Required field missing
 * - Invalid field format
 * - Business rule violation
 * - Data type mismatch
 * - Field length constraints
 * - Cross-field validation errors
 *
 * Provides detailed validation error information:
 * - Field-specific error messages
 * - Error type classification
 * - Suggested corrections
 * - Validation rule context
 *
 * @package Clubify\Checkout\Modules\{ModuleName}\Exceptions
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class {Entity}ValidationException extends \Exception
{
    /**
     * Validation error types
     */
    public const ERROR_TYPE_REQUIRED_FIELD = 'required_field';
    public const ERROR_TYPE_INVALID_FORMAT = 'invalid_format';
    public const ERROR_TYPE_INVALID_LENGTH = 'invalid_length';
    public const ERROR_TYPE_INVALID_VALUE = 'invalid_value';
    public const ERROR_TYPE_INVALID_TYPE = 'invalid_type';
    public const ERROR_TYPE_BUSINESS_RULE = 'business_rule';
    public const ERROR_TYPE_DUPLICATE_VALUE = 'duplicate_value';
    public const ERROR_TYPE_CROSS_FIELD = 'cross_field';

    /**
     * HTTP status code for validation errors
     */
    public const HTTP_STATUS_CODE = 422; // Unprocessable Entity

    /**
     * Validation errors by field
     */
    private array $errors;

    /**
     * Additional context information
     */
    private array $context;

    /**
     * Failed validation rules
     */
    private array $failedRules;

    /**
     * Input data that failed validation
     */
    private array $inputData;

    /**
     * Create validation exception for single field error
     *
     * @param string $field Field name
     * @param string $message Error message
     * @param string $errorType Error type
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function forField(string $field, string $message, string $errorType = self::ERROR_TYPE_INVALID_VALUE, array $context = []): static
    {
        $errors = [$field => $message];
        $failedRules = [$field => [$errorType]];

        return new static(
            "Validation failed for field '{$field}': {$message}",
            $errors,
            $context,
            $failedRules
        );
    }

    /**
     * Create validation exception for multiple field errors
     *
     * @param array $errors Field errors array
     * @param array $context Additional context
     * @param array $failedRules Failed rules by field
     * @return static Exception instance
     */
    public static function forFields(array $errors, array $context = [], array $failedRules = []): static
    {
        $message = 'Validation failed for ' . count($errors) . ' field(s): ' . implode(', ', array_keys($errors));

        return new static(
            $message,
            $errors,
            $context,
            $failedRules
        );
    }

    /**
     * Create validation exception for required field
     *
     * @param string $field Required field name
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function requiredField(string $field, array $context = []): static
    {
        $message = "Field '{$field}' is required";
        return self::forField($field, $message, self::ERROR_TYPE_REQUIRED_FIELD, $context);
    }

    /**
     * Create validation exception for invalid format
     *
     * @param string $field Field name
     * @param string $expectedFormat Expected format description
     * @param mixed $actualValue Actual value provided
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function invalidFormat(string $field, string $expectedFormat, $actualValue, array $context = []): static
    {
        $message = "Field '{$field}' has invalid format. Expected: {$expectedFormat}, Got: " . json_encode($actualValue);
        return self::forField($field, $message, self::ERROR_TYPE_INVALID_FORMAT, array_merge($context, [
            'expected_format' => $expectedFormat,
            'actual_value' => $actualValue
        ]));
    }

    /**
     * Create validation exception for invalid length
     *
     * @param string $field Field name
     * @param int $actualLength Actual length
     * @param int|null $minLength Minimum length
     * @param int|null $maxLength Maximum length
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function invalidLength(string $field, int $actualLength, ?int $minLength = null, ?int $maxLength = null, array $context = []): static
    {
        $constraints = [];
        if ($minLength !== null) $constraints[] = "minimum {$minLength}";
        if ($maxLength !== null) $constraints[] = "maximum {$maxLength}";

        $message = "Field '{$field}' has invalid length ({$actualLength}). Expected: " . implode(', ', $constraints);

        return self::forField($field, $message, self::ERROR_TYPE_INVALID_LENGTH, array_merge($context, [
            'actual_length' => $actualLength,
            'min_length' => $minLength,
            'max_length' => $maxLength
        ]));
    }

    /**
     * Create validation exception for invalid value
     *
     * @param string $field Field name
     * @param mixed $value Invalid value
     * @param array $allowedValues Allowed values
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function invalidValue(string $field, $value, array $allowedValues, array $context = []): static
    {
        $message = "Field '{$field}' has invalid value '" . json_encode($value) . "'. Allowed values: " . implode(', ', $allowedValues);

        return self::forField($field, $message, self::ERROR_TYPE_INVALID_VALUE, array_merge($context, [
            'invalid_value' => $value,
            'allowed_values' => $allowedValues
        ]));
    }

    /**
     * Create validation exception for duplicate value
     *
     * @param string $field Field name
     * @param mixed $value Duplicate value
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function duplicateValue(string $field, $value, array $context = []): static
    {
        $message = "Field '{$field}' must be unique. Value '" . json_encode($value) . "' already exists";

        return self::forField($field, $message, self::ERROR_TYPE_DUPLICATE_VALUE, array_merge($context, [
            'duplicate_value' => $value
        ]));
    }

    /**
     * Create validation exception for business rule violation
     *
     * @param string $rule Business rule name
     * @param string $message Rule violation message
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function businessRule(string $rule, string $message, array $context = []): static
    {
        return new static(
            "Business rule violation ({$rule}): {$message}",
            ['_business_rule' => $message],
            array_merge($context, ['rule' => $rule]),
            ['_business_rule' => [self::ERROR_TYPE_BUSINESS_RULE]]
        );
    }

    /**
     * Create validation exception for cross-field validation
     *
     * @param array $fields Related fields
     * @param string $message Cross-field validation message
     * @param array $context Additional context
     * @return static Exception instance
     */
    public static function crossField(array $fields, string $message, array $context = []): static
    {
        $fieldNames = implode(', ', $fields);
        $errors = [];
        $failedRules = [];

        foreach ($fields as $field) {
            $errors[$field] = $message;
            $failedRules[$field] = [self::ERROR_TYPE_CROSS_FIELD];
        }

        return new static(
            "Cross-field validation failed for fields ({$fieldNames}): {$message}",
            $errors,
            array_merge($context, ['related_fields' => $fields]),
            $failedRules
        );
    }

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param array $errors Validation errors by field
     * @param array $context Additional context
     * @param array $failedRules Failed validation rules
     * @param array $inputData Input data that failed validation
     */
    public function __construct(
        string $message,
        array $errors = [],
        array $context = [],
        array $failedRules = [],
        array $inputData = []
    ) {
        parent::__construct($message, self::HTTP_STATUS_CODE);

        $this->errors = $errors;
        $this->context = $context;
        $this->failedRules = $failedRules;
        $this->inputData = $inputData;
    }

    /**
     * Get validation errors by field
     *
     * @return array Validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get additional context information
     *
     * @return array Context data
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Get failed validation rules by field
     *
     * @return array Failed rules
     */
    public function getFailedRules(): array
    {
        return $this->failedRules;
    }

    /**
     * Get input data that failed validation
     *
     * @return array Input data
     */
    public function getInputData(): array
    {
        return $this->inputData;
    }

    /**
     * Set input data that failed validation
     *
     * @param array $inputData Input data
     * @return $this
     */
    public function setInputData(array $inputData): self
    {
        $this->inputData = $inputData;
        return $this;
    }

    /**
     * Check if specific field has error
     *
     * @param string $field Field name
     * @return bool True if field has error
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get error message for specific field
     *
     * @param string $field Field name
     * @return string|null Error message or null if no error
     */
    public function getFieldError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Get failed rules for specific field
     *
     * @param string $field Field name
     * @return array Failed rules for field
     */
    public function getFieldFailedRules(string $field): array
    {
        return $this->failedRules[$field] ?? [];
    }

    /**
     * Get all fields that have errors
     *
     * @return array Field names with errors
     */
    public function getFieldsWithErrors(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Get count of validation errors
     *
     * @return int Number of errors
     */
    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    /**
     * Add additional validation error
     *
     * @param string $field Field name
     * @param string $message Error message
     * @param string $errorType Error type
     * @return $this
     */
    public function addError(string $field, string $message, string $errorType = self::ERROR_TYPE_INVALID_VALUE): self
    {
        $this->errors[$field] = $message;

        if (!isset($this->failedRules[$field])) {
            $this->failedRules[$field] = [];
        }
        $this->failedRules[$field][] = $errorType;

        return $this;
    }

    /**
     * Merge with another validation exception
     *
     * @param {Entity}ValidationException $other Other exception to merge
     * @return $this
     */
    public function merge({Entity}ValidationException $other): self
    {
        $this->errors = array_merge($this->errors, $other->getErrors());
        $this->context = array_merge($this->context, $other->getContext());
        $this->failedRules = array_merge($this->failedRules, $other->getFailedRules());

        return $this;
    }

    /**
     * Convert exception to array format (for API responses)
     *
     * @return array Exception data
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'error_type' => 'validation_failed',
            'error_code' => $this->getCode(),
            'message' => $this->getMessage(),
            'validation_errors' => $this->formatErrorsForApi(),
            'failed_rules' => $this->failedRules,
            'context' => $this->context,
            'error_count' => $this->getErrorCount(),
            'fields_with_errors' => $this->getFieldsWithErrors(),
            'suggestions' => $this->getSuggestions(),
            'timestamp' => time()
        ];
    }

    /**
     * Format errors for API response
     *
     * @return array Formatted errors
     */
    public function formatErrorsForApi(): array
    {
        $formatted = [];

        foreach ($this->errors as $field => $message) {
            $formatted[] = [
                'field' => $field,
                'message' => $message,
                'error_types' => $this->failedRules[$field] ?? [],
                'suggestions' => $this->getFieldSuggestions($field)
            ];
        }

        return $formatted;
    }

    /**
     * Get suggestions for fixing validation errors
     *
     * @return array General suggestions
     */
    public function getSuggestions(): array
    {
        $suggestions = [];

        // Add general suggestions based on error types
        $errorTypes = array_unique(array_merge(...array_values($this->failedRules)));

        if (in_array(self::ERROR_TYPE_REQUIRED_FIELD, $errorTypes)) {
            $suggestions[] = 'Ensure all required fields are provided';
        }

        if (in_array(self::ERROR_TYPE_INVALID_FORMAT, $errorTypes)) {
            $suggestions[] = 'Check field formats and data types';
        }

        if (in_array(self::ERROR_TYPE_INVALID_LENGTH, $errorTypes)) {
            $suggestions[] = 'Verify field length constraints';
        }

        if (in_array(self::ERROR_TYPE_BUSINESS_RULE, $errorTypes)) {
            $suggestions[] = 'Review business rules and constraints';
        }

        if (empty($suggestions)) {
            $suggestions[] = 'Review the validation errors and correct the data';
        }

        return $suggestions;
    }

    /**
     * Get suggestions for specific field
     *
     * @param string $field Field name
     * @return array Field-specific suggestions
     */
    public function getFieldSuggestions(string $field): array
    {
        $suggestions = [];
        $rules = $this->failedRules[$field] ?? [];

        foreach ($rules as $rule) {
            switch ($rule) {
                case self::ERROR_TYPE_REQUIRED_FIELD:
                    $suggestions[] = "Provide a value for {$field}";
                    break;
                case self::ERROR_TYPE_INVALID_FORMAT:
                    $suggestions[] = "Check the format of {$field}";
                    break;
                case self::ERROR_TYPE_INVALID_LENGTH:
                    $suggestions[] = "Adjust the length of {$field}";
                    break;
                case self::ERROR_TYPE_INVALID_VALUE:
                    $suggestions[] = "Use a valid value for {$field}";
                    break;
                case self::ERROR_TYPE_DUPLICATE_VALUE:
                    $suggestions[] = "Use a unique value for {$field}";
                    break;
            }
        }

        return array_unique($suggestions);
    }

    /**
     * Convert exception to JSON string
     *
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Get HTTP status code for this exception
     *
     * @return int HTTP status code
     */
    public function getHttpStatusCode(): int
    {
        return $this->getCode();
    }

    /**
     * Check if validation can be retried with corrected data
     *
     * @return bool True if retryable
     */
    public function isRetryable(): bool
    {
        // Validation errors are typically retryable with corrected data
        return true;
    }

    /**
     * Get localized error messages
     *
     * @param string $locale Locale code
     * @return array Localized error messages by field
     */
    public function getLocalizedErrors(string $locale = 'en'): array
    {
        $localized = [];

        foreach ($this->errors as $field => $message) {
            $localized[$field] = $this->getLocalizedFieldMessage($field, $message, $locale);
        }

        return $localized;
    }

    /**
     * Get localized message for specific field
     *
     * @param string $field Field name
     * @param string $message Original message
     * @param string $locale Locale code
     * @return string Localized message
     */
    private function getLocalizedFieldMessage(string $field, string $message, string $locale): string
    {
        // This would typically integrate with a localization system
        switch ($locale) {
            case 'pt':
            case 'pt-BR':
                return $this->translateToPortuguese($field, $message);
            case 'es':
                return $this->translateToSpanish($field, $message);
            default:
                return $message;
        }
    }

    /**
     * Translate message to Portuguese
     *
     * @param string $field Field name
     * @param string $message Original message
     * @return string Portuguese message
     */
    private function translateToPortuguese(string $field, string $message): string
    {
        // Basic translation examples - integrate with proper translation system
        $translations = [
            'is required' => 'é obrigatório',
            'must be unique' => 'deve ser único',
            'has invalid format' => 'tem formato inválido',
            'has invalid length' => 'tem comprimento inválido',
            'has invalid value' => 'tem valor inválido'
        ];

        $translated = $message;
        foreach ($translations as $english => $portuguese) {
            $translated = str_replace($english, $portuguese, $translated);
        }

        return $translated;
    }

    /**
     * Translate message to Spanish
     *
     * @param string $field Field name
     * @param string $message Original message
     * @return string Spanish message
     */
    private function translateToSpanish(string $field, string $message): string
    {
        // Basic translation examples - integrate with proper translation system
        $translations = [
            'is required' => 'es requerido',
            'must be unique' => 'debe ser único',
            'has invalid format' => 'tiene formato inválido',
            'has invalid length' => 'tiene longitud inválida',
            'has invalid value' => 'tiene valor inválido'
        ];

        $translated = $message;
        foreach ($translations as $english => $spanish) {
            $translated = str_replace($english, $spanish, $translated);
        }

        return $translated;
    }
}