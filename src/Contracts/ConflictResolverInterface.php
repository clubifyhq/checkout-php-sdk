<?php

declare(strict_types=1);

namespace Clubify\Checkout\Contracts;

use Clubify\Checkout\Exceptions\ConflictException;

interface ConflictResolverInterface
{
    /**
     * Attempt to automatically resolve a conflict by retrieving existing resource
     *
     * @param ConflictException $conflict The conflict to resolve
     * @return array The resolved resource data
     * @throws ConflictException If the conflict cannot be resolved
     */
    public function resolve(ConflictException $conflict): array;

    /**
     * Check if a resource exists before creation
     *
     * @param string $resourceType The type of resource (user, tenant, etc.)
     * @param array $checkData The data to check (email, domain, etc.)
     * @return array|null The existing resource data if found, null otherwise
     */
    public function checkResourceExists(string $resourceType, array $checkData): ?array;

    /**
     * Create resource with automatic conflict resolution
     *
     * @param string $endpoint The API endpoint to create the resource
     * @param array $data The data to create the resource with
     * @param bool $autoResolveConflicts Whether to automatically resolve conflicts
     * @return array The created or existing resource data
     */
    public function createWithConflictResolution(
        string $endpoint,
        array $data,
        bool $autoResolveConflicts = true
    ): array;

    /**
     * Create resource with idempotency support
     *
     * @param string $endpoint The API endpoint to create the resource
     * @param array $data The data to create the resource with
     * @param string $idempotencyKey The idempotency key for the operation
     * @param bool $autoResolveConflicts Whether to automatically resolve conflicts
     * @return array The created or existing resource data
     */
    public function createIdempotent(
        string $endpoint,
        array $data,
        string $idempotencyKey,
        bool $autoResolveConflicts = true
    ): array;
}