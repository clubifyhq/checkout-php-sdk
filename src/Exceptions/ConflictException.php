<?php

declare(strict_types=1);

namespace Clubify\Checkout\Exceptions;

use Clubify\Checkout\Contracts\ConflictResolverInterface;
use Clubify\Checkout\ValueObjects\ConflictResolution;

/**
 * Exception thrown when a resource conflict occurs (HTTP 409)
 *
 * This exception provides structured information about the conflict
 * and suggests resolution strategies to the client.
 */
class ConflictException extends HttpException
{
    private array $conflictFields;
    private array $existingValues;
    private string $conflictType;
    private ?string $existingResourceId;
    private array $resolutionSuggestions;

    public function __construct(
        string $message,
        string $conflictType,
        array $conflictFields = [],
        ?string $existingResourceId = null,
        array $existingValues = [],
        array $resolutionSuggestions = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 409, $previous);

        $this->conflictType = $conflictType;
        $this->conflictFields = $conflictFields;
        $this->existingValues = $existingValues;
        $this->existingResourceId = $existingResourceId;
        $this->resolutionSuggestions = $resolutionSuggestions ?: $this->generateDefaultSuggestions();
    }

    /**
     * Create a ConflictException for email conflicts
     */
    public static function emailExists(
        string $email,
        ?string $existingUserId = null
    ): self {
        return new self(
            "User with email '{$email}' already exists",
            'email_exists',
            ['email'],
            $existingUserId,
            ['email' => $email],
            [
                'Use checkExisting=true parameter to retrieve existing user',
                'Call GET /users/by-email/' . urlencode($email),
                'Use idempotency key to safely retry operation'
            ]
        );
    }

    /**
     * Create a ConflictException for domain conflicts
     */
    public static function domainExists(
        string $domain,
        ?string $existingTenantId = null
    ): self {
        return new self(
            "Domain '{$domain}' is already in use",
            'domain_exists',
            ['domain'],
            $existingTenantId,
            ['domain' => $domain],
            [
                'Use checkExisting=true parameter to retrieve existing tenant',
                'Call GET /tenants/check-domain/' . urlencode($domain),
                'Try alternative domain suggestions'
            ]
        );
    }

    /**
     * Create a ConflictException for subdomain conflicts
     */
    public static function subdomainExists(
        string $subdomain,
        ?string $existingTenantId = null
    ): self {
        return new self(
            "Subdomain '{$subdomain}' is already in use",
            'subdomain_exists',
            ['subdomain'],
            $existingTenantId,
            ['subdomain' => $subdomain],
            [
                'Use checkExisting=true parameter to retrieve existing tenant',
                'Call GET /tenants/check-subdomain/' . urlencode($subdomain),
                'Try alternative subdomain suggestions'
            ]
        );
    }

    public function getConflictType(): string
    {
        return $this->conflictType;
    }

    public function getConflictFields(): array
    {
        return $this->conflictFields;
    }

    public function getExistingValues(): array
    {
        return $this->existingValues;
    }

    public function getExistingResourceId(): ?string
    {
        return $this->existingResourceId;
    }

    public function getResolutionSuggestions(): array
    {
        return $this->resolutionSuggestions;
    }

    /**
     * Check if this conflict can be automatically resolved
     */
    public function isAutoResolvable(): bool
    {
        return in_array($this->conflictType, [
            'email_exists',
            'domain_exists',
            'subdomain_exists',
            'user_exists',
            'tenant_exists'
        ]);
    }

    /**
     * Get the suggested check endpoint for this conflict
     */
    public function getCheckEndpoint(): ?string
    {
        return match ($this->conflictType) {
            'email_exists' => '/users/check-email/' . urlencode($this->existingValues['email'] ?? ''),
            'domain_exists' => '/tenants/check-domain/' . urlencode($this->existingValues['domain'] ?? ''),
            'subdomain_exists' => '/tenants/check-subdomain/' . urlencode($this->existingValues['subdomain'] ?? ''),
            default => null
        };
    }

    /**
     * Get the suggested retrieval endpoint for existing resource
     */
    public function getRetrievalEndpoint(): ?string
    {
        if (!$this->existingResourceId) {
            return null;
        }

        return match ($this->conflictType) {
            'email_exists', 'user_exists' => "/users/{$this->existingResourceId}",
            'domain_exists', 'subdomain_exists', 'tenant_exists' => "/tenants/{$this->existingResourceId}",
            default => null
        };
    }

    /**
     * Create a ConflictResolution object for automatic resolution
     */
    public function createResolution(): ConflictResolution
    {
        return new ConflictResolution(
            $this->conflictType,
            $this->existingResourceId,
            $this->getCheckEndpoint(),
            $this->getRetrievalEndpoint(),
            $this->resolutionSuggestions
        );
    }

    /**
     * Convert to array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'type' => 'conflict',
            'message' => $this->getMessage(),
            'status_code' => $this->getStatusCode(),
            'conflict_type' => $this->conflictType,
            'conflict_fields' => $this->conflictFields,
            'existing_values' => $this->existingValues,
            'existing_resource_id' => $this->existingResourceId,
            'resolution_suggestions' => $this->resolutionSuggestions,
            'auto_resolvable' => $this->isAutoResolvable(),
            'check_endpoint' => $this->getCheckEndpoint(),
            'retrieval_endpoint' => $this->getRetrievalEndpoint(),
            'idempotency_supported' => true
        ];
    }

    private function generateDefaultSuggestions(): array
    {
        return [
            'Use checkExisting=true parameter to retrieve existing resource',
            'Add an idempotency key to safely retry the operation',
            'Check resource availability before attempting creation'
        ];
    }
}