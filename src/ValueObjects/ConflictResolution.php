<?php

declare(strict_types=1);

namespace Clubify\Checkout\ValueObjects;

/**
 * Value object representing a conflict resolution strategy
 */
readonly class ConflictResolution
{
    public function __construct(
        public string $conflictType,
        public ?string $existingResourceId,
        public ?string $checkEndpoint,
        public ?string $retrievalEndpoint,
        public array $suggestions
    ) {
    }

    public function canAutoResolve(): bool
    {
        return $this->existingResourceId !== null && $this->retrievalEndpoint !== null;
    }

    public function toArray(): array
    {
        return [
            'conflict_type' => $this->conflictType,
            'existing_resource_id' => $this->existingResourceId,
            'check_endpoint' => $this->checkEndpoint,
            'retrieval_endpoint' => $this->retrievalEndpoint,
            'suggestions' => $this->suggestions,
            'can_auto_resolve' => $this->canAutoResolve()
        ];
    }
}