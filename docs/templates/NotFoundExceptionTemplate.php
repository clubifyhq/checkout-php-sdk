<?php

/**
 * Template para NotFoundException - Clubify Checkout SDK
 *
 * Este template define uma exceção específica para quando uma entidade não é encontrada.
 * Estende Exception com informações específicas do domínio.
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
 * {Entity} Not Found Exception
 *
 * Thrown when a {entity} cannot be found by the specified criteria:
 * - {Entity} ID not found
 * - {Entity} not found by specific field
 * - {Entity} not accessible due to permissions
 * - {Entity} soft-deleted or archived
 *
 * Provides structured error information for proper handling:
 * - HTTP status code (404)
 * - Error type classification
 * - Contextual information
 * - Suggested actions
 *
 * @package Clubify\Checkout\Modules\{ModuleName}\Exceptions
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class {Entity}NotFoundException extends \Exception
{
    /**
     * Error type constants
     */
    public const ERROR_TYPE_NOT_FOUND = '{entity}_not_found';
    public const ERROR_TYPE_INVALID_ID = '{entity}_invalid_id';
    public const ERROR_TYPE_ACCESS_DENIED = '{entity}_access_denied';
    public const ERROR_TYPE_ARCHIVED = '{entity}_archived';
    public const ERROR_TYPE_DELETED = '{entity}_deleted';

    /**
     * HTTP status code for this exception
     */
    public const HTTP_STATUS_CODE = 404;

    /**
     * Error type classification
     */
    private string $errorType;

    /**
     * Additional context information
     */
    private array $context;

    /**
     * {Entity} identifier that was not found
     */
    private ?string ${entity}Id;

    /**
     * Search criteria used
     */
    private array $searchCriteria;

    /**
     * Create exception for {entity} not found by ID
     *
     * @param string ${entity}Id {Entity} ID that was not found
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function byId(string ${entity}Id, array $context = []): static
    {
        $message = "No {entity} found with ID: {${entity}Id}";

        return new static(
            $message,
            self::HTTP_STATUS_CODE,
            null,
            self::ERROR_TYPE_NOT_FOUND,
            array_merge($context, ['{entity}_id' => ${entity}Id]),
            ${entity}Id,
            ['id' => ${entity}Id]
        );
    }

    /**
     * Create exception for {entity} not found by field
     *
     * @param string $field Field name
     * @param mixed $value Field value
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function byField(string $field, $value, array $context = []): static
    {
        $message = "No {entity} found with {$field}: {$value}";

        return new static(
            $message,
            self::HTTP_STATUS_CODE,
            null,
            self::ERROR_TYPE_NOT_FOUND,
            array_merge($context, [$field => $value]),
            null,
            [$field => $value]
        );
    }

    /**
     * Create exception for {entity} not found by multiple criteria
     *
     * @param array $criteria Search criteria
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function byCriteria(array $criteria, array $context = []): static
    {
        $criteriaStr = json_encode($criteria);
        $message = "No {entity} found matching criteria: {$criteriaStr}";

        return new static(
            $message,
            self::HTTP_STATUS_CODE,
            null,
            self::ERROR_TYPE_NOT_FOUND,
            $context,
            null,
            $criteria
        );
    }

    /**
     * Create exception for invalid {entity} ID format
     *
     * @param string $invalidId Invalid ID that was provided
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function invalidId(string $invalidId, array $context = []): static
    {
        $message = "Invalid {entity} ID format: {$invalidId}";

        return new static(
            $message,
            400, // Bad Request for invalid format
            null,
            self::ERROR_TYPE_INVALID_ID,
            array_merge($context, ['invalid_id' => $invalidId]),
            $invalidId,
            ['id' => $invalidId]
        );
    }

    /**
     * Create exception for {entity} access denied
     *
     * @param string ${entity}Id {Entity} ID that access was denied to
     * @param string $reason Reason for access denial
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function accessDenied(string ${entity}Id, string $reason = 'Insufficient permissions', array $context = []): static
    {
        $message = "Access denied to {entity} {${entity}Id}: {$reason}";

        return new static(
            $message,
            403, // Forbidden
            null,
            self::ERROR_TYPE_ACCESS_DENIED,
            array_merge($context, [
                '{entity}_id' => ${entity}Id,
                'reason' => $reason
            ]),
            ${entity}Id,
            ['id' => ${entity}Id]
        );
    }

    /**
     * Create exception for archived {entity}
     *
     * @param string ${entity}Id Archived {entity} ID
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function archived(string ${entity}Id, array $context = []): static
    {
        $message = "{Entity} {${entity}Id} is archived and not accessible";

        return new static(
            $message,
            410, // Gone
            null,
            self::ERROR_TYPE_ARCHIVED,
            array_merge($context, ['{entity}_id' => ${entity}Id]),
            ${entity}Id,
            ['id' => ${entity}Id]
        );
    }

    /**
     * Create exception for soft-deleted {entity}
     *
     * @param string ${entity}Id Deleted {entity} ID
     * @param array $context Additional context information
     * @return static Exception instance
     */
    public static function deleted(string ${entity}Id, array $context = []): static
    {
        $message = "{Entity} {${entity}Id} has been deleted";

        return new static(
            $message,
            410, // Gone
            null,
            self::ERROR_TYPE_DELETED,
            array_merge($context, ['{entity}_id' => ${entity}Id]),
            ${entity}Id,
            ['id' => ${entity}Id]
        );
    }

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code (HTTP status code)
     * @param \Throwable|null $previous Previous exception
     * @param string $errorType Error type classification
     * @param array $context Additional context information
     * @param string|null ${entity}Id {Entity} ID if applicable
     * @param array $searchCriteria Search criteria used
     */
    public function __construct(
        string $message = '',
        int $code = self::HTTP_STATUS_CODE,
        ?\Throwable $previous = null,
        string $errorType = self::ERROR_TYPE_NOT_FOUND,
        array $context = [],
        ?string ${entity}Id = null,
        array $searchCriteria = []
    ) {
        parent::__construct($message, $code, $previous);

        $this->errorType = $errorType;
        $this->context = $context;
        $this->{entity}Id = ${entity}Id;
        $this->searchCriteria = $searchCriteria;
    }

    /**
     * Get error type classification
     *
     * @return string Error type
     */
    public function getErrorType(): string
    {
        return $this->errorType;
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
     * Get {entity} ID that was not found
     *
     * @return string|null {Entity} ID
     */
    public function get{Entity}Id(): ?string
    {
        return $this->{entity}Id;
    }

    /**
     * Get search criteria that were used
     *
     * @return array Search criteria
     */
    public function getSearchCriteria(): array
    {
        return $this->searchCriteria;
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
     * Convert exception to array format (for API responses)
     *
     * @return array Exception data
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'error_type' => $this->errorType,
            'error_code' => $this->getCode(),
            'message' => $this->getMessage(),
            'context' => $this->context,
            '{entity}_id' => $this->{entity}Id,
            'search_criteria' => $this->searchCriteria,
            'timestamp' => time(),
            'suggestions' => $this->getSuggestions()
        ];
    }

    /**
     * Get suggested actions for this error
     *
     * @return array Suggested actions
     */
    public function getSuggestions(): array
    {
        switch ($this->errorType) {
            case self::ERROR_TYPE_INVALID_ID:
                return [
                    'Check the {entity} ID format',
                    'Ensure the ID is a valid UUID or identifier',
                    'Verify the ID was copied correctly'
                ];

            case self::ERROR_TYPE_ACCESS_DENIED:
                return [
                    'Check your permissions for this {entity}',
                    'Verify you are authenticated',
                    'Contact your administrator if you need access'
                ];

            case self::ERROR_TYPE_ARCHIVED:
                return [
                    'This {entity} has been archived',
                    'Use the restore endpoint to restore it',
                    'Check if you have permission to access archived {entity}s'
                ];

            case self::ERROR_TYPE_DELETED:
                return [
                    'This {entity} has been permanently deleted',
                    'Check if there are any backups available',
                    'Consider creating a new {entity} if needed'
                ];

            case self::ERROR_TYPE_NOT_FOUND:
            default:
                return [
                    'Verify the {entity} ID or search criteria',
                    'Check if the {entity} exists in the system',
                    'Ensure you have permission to access this {entity}',
                    'Try searching with different criteria'
                ];
        }
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
     * Check if this is a specific error type
     *
     * @param string $type Error type to check
     * @return bool True if matches error type
     */
    public function isErrorType(string $type): bool
    {
        return $this->errorType === $type;
    }

    /**
     * Check if this exception is retryable
     *
     * @return bool True if operation could be retried
     */
    public function isRetryable(): bool
    {
        // Most not found exceptions are not retryable
        // except for potential race conditions
        return false;
    }

    /**
     * Get localized message for user display
     *
     * @param string $locale Locale for message
     * @return string Localized message
     */
    public function getLocalizedMessage(string $locale = 'en'): string
    {
        // This would typically integrate with a localization system
        switch ($locale) {
            case 'pt':
            case 'pt-BR':
                return $this->getPortugueseMessage();
            case 'es':
                return $this->getSpanishMessage();
            default:
                return $this->getMessage();
        }
    }

    /**
     * Get Portuguese error message
     *
     * @return string Portuguese message
     */
    private function getPortugueseMessage(): string
    {
        switch ($this->errorType) {
            case self::ERROR_TYPE_NOT_FOUND:
                return "Nenhum {entity} encontrado com os critérios especificados";
            case self::ERROR_TYPE_INVALID_ID:
                return "Formato de ID de {entity} inválido";
            case self::ERROR_TYPE_ACCESS_DENIED:
                return "Acesso negado ao {entity}";
            case self::ERROR_TYPE_ARCHIVED:
                return "{Entity} arquivado e não acessível";
            case self::ERROR_TYPE_DELETED:
                return "{Entity} foi excluído";
            default:
                return $this->getMessage();
        }
    }

    /**
     * Get Spanish error message
     *
     * @return string Spanish message
     */
    private function getSpanishMessage(): string
    {
        switch ($this->errorType) {
            case self::ERROR_TYPE_NOT_FOUND:
                return "No se encontró {entity} con los criterios especificados";
            case self::ERROR_TYPE_INVALID_ID:
                return "Formato de ID de {entity} inválido";
            case self::ERROR_TYPE_ACCESS_DENIED:
                return "Acceso denegado al {entity}";
            case self::ERROR_TYPE_ARCHIVED:
                return "{Entity} archivado y no accesible";
            case self::ERROR_TYPE_DELETED:
                return "{Entity} ha sido eliminado";
            default:
                return $this->getMessage();
        }
    }
}