<?php

declare(strict_types=1);

namespace Clubify\Checkout\Services;

use Clubify\Checkout\Contracts\ConflictResolverInterface;
use Clubify\Checkout\Exceptions\ConflictException;
use Clubify\Checkout\ValueObjects\ConflictResolution;
use Clubify\Checkout\Core\Http\Client;
use Psr\Log\LoggerInterface;

/**
 * Service for automatically resolving resource conflicts
 */
class ConflictResolverService implements ConflictResolverInterface
{
    public function __construct(
        private readonly Client $httpClient,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Attempt to automatically resolve a conflict by retrieving existing resource
     */
    public function resolve(ConflictException $conflict): array
    {
        $resolution = $conflict->createResolution();

        if (!$resolution->canAutoResolve()) {
            $this->logger->warning('Cannot auto-resolve conflict', [
                'conflict_type' => $conflict->getConflictType(),
                'reason' => 'Missing existing resource ID or retrieval endpoint'
            ]);
            throw $conflict;
        }

        try {
            $this->logger->info('Attempting automatic conflict resolution', [
                'conflict_type' => $conflict->getConflictType(),
                'existing_resource_id' => $resolution->existingResourceId,
                'retrieval_endpoint' => $resolution->retrievalEndpoint
            ]);

            $response = $this->httpClient->get($resolution->retrievalEndpoint);
            $existingResource = $response->getData();

            $this->logger->info('Conflict resolved successfully', [
                'conflict_type' => $conflict->getConflictType(),
                'resolved_resource_id' => $existingResource['id'] ?? 'unknown'
            ]);

            return $existingResource;

        } catch (\Exception $e) {
            $this->logger->error('Failed to auto-resolve conflict', [
                'conflict_type' => $conflict->getConflictType(),
                'error' => $e->getMessage(),
                'retrieval_endpoint' => $resolution->retrievalEndpoint
            ]);

            // Re-throw original conflict if resolution fails
            throw $conflict;
        }
    }

    /**
     * Check if a resource exists before creation
     */
    public function checkResourceExists(string $resourceType, array $checkData): ?array
    {
        $checkEndpoint = $this->getCheckEndpoint($resourceType, $checkData);

        if (!$checkEndpoint) {
            return null;
        }

        try {
            $response = $this->httpClient->get($checkEndpoint);
            $result = $response->getData();

            // If the check endpoint returns availability info
            if (isset($result['available']) && $result['available'] === false) {
                return $result['existing_resource'] ?? null;
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to check resource existence', [
                'resource_type' => $resourceType,
                'check_endpoint' => $checkEndpoint,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Create resource with automatic conflict resolution
     */
    public function createWithConflictResolution(
        string $endpoint,
        array $data,
        bool $autoResolveConflicts = true
    ): array {
        try {
            $response = $this->httpClient->post($endpoint, $data);
            return $response->getData();

        } catch (ConflictException $conflict) {
            if (!$autoResolveConflicts) {
                throw $conflict;
            }

            $this->logger->info('Detected conflict, attempting resolution', [
                'endpoint' => $endpoint,
                'conflict_type' => $conflict->getConflictType()
            ]);

            return $this->resolve($conflict);
        }
    }

    /**
     * Create resource with idempotency support
     */
    public function createIdempotent(
        string $endpoint,
        array $data,
        string $idempotencyKey,
        bool $autoResolveConflicts = true
    ): array {
        // Add idempotency key to request data
        $data['idempotency_key'] = $idempotencyKey;

        return $this->createWithConflictResolution($endpoint, $data, $autoResolveConflicts);
    }

    private function getCheckEndpoint(string $resourceType, array $checkData): ?string
    {
        return match ($resourceType) {
            'user' => isset($checkData['email'])
                ? '/users/check-email/' . urlencode($checkData['email'])
                : null,
            'tenant' => isset($checkData['domain'])
                ? '/tenants/check-domain/' . urlencode($checkData['domain'])
                : (isset($checkData['subdomain'])
                    ? '/tenants/check-subdomain/' . urlencode($checkData['subdomain'])
                    : null),
            default => null
        };
    }
}