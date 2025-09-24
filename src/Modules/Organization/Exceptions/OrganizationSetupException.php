<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Exceptions;

use Clubify\Checkout\Exceptions\SDKException;

/**
 * Exception thrown during organization setup operations
 *
 * This exception captures the complete state of a failed organization setup,
 * enabling automatic rollback and manual recovery procedures.
 */
class OrganizationSetupException extends SDKException
{
    private string $setupStep;
    private array $rollbackData;
    private array $completedSteps;
    private ?string $organizationId;
    private ?string $tenantId;
    private ?string $adminId;
    private array $createdResources;
    private bool $rollbackRequired;
    private array $rollbackProcedures;

    public function __construct(
        string $message,
        string $setupStep,
        array $rollbackData = [],
        array $completedSteps = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);

        $this->setupStep = $setupStep;
        $this->rollbackData = $rollbackData;
        $this->completedSteps = $completedSteps;
        $this->organizationId = $rollbackData['organization_id'] ?? null;
        $this->tenantId = $rollbackData['tenant_id'] ?? null;
        $this->adminId = $rollbackData['admin_id'] ?? null;
        $this->createdResources = $rollbackData['created_resources'] ?? [];
        $this->rollbackRequired = !empty($this->createdResources);
        $this->rollbackProcedures = $this->generateRollbackProcedures();
    }

    /**
     * Create exception for tenant creation failure
     */
    public static function tenantCreationFailed(
        string $organizationId,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Failed to create tenant: {$reason}",
            'tenant_creation',
            [
                'organization_id' => $organizationId,
                'created_resources' => ['organization' => $organizationId]
            ],
            ['organization_created'],
            $previous
        );
    }

    /**
     * Create exception for admin user creation failure
     */
    public static function adminCreationFailed(
        string $organizationId,
        string $tenantId,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Failed to create admin user: {$reason}",
            'admin_creation',
            [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'created_resources' => [
                    'organization' => $organizationId,
                    'tenant' => $tenantId
                ]
            ],
            ['organization_created', 'tenant_created'],
            $previous
        );
    }

    /**
     * Create exception for API key generation failure
     */
    public static function apiKeyGenerationFailed(
        string $organizationId,
        string $tenantId,
        string $adminId,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Failed to generate API keys: {$reason}",
            'api_key_generation',
            [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'admin_id' => $adminId,
                'created_resources' => [
                    'organization' => $organizationId,
                    'tenant' => $tenantId,
                    'admin' => $adminId
                ]
            ],
            ['organization_created', 'tenant_created', 'admin_created'],
            $previous
        );
    }

    /**
     * Create exception for domain configuration failure
     */
    public static function domainConfigurationFailed(
        string $organizationId,
        string $tenantId,
        string $adminId,
        array $apiKeys,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Failed to configure domain: {$reason}",
            'domain_configuration',
            [
                'organization_id' => $organizationId,
                'tenant_id' => $tenantId,
                'admin_id' => $adminId,
                'api_keys' => $apiKeys,
                'created_resources' => [
                    'organization' => $organizationId,
                    'tenant' => $tenantId,
                    'admin' => $adminId,
                    'api_keys' => $apiKeys
                ]
            ],
            ['organization_created', 'tenant_created', 'admin_created', 'api_keys_generated'],
            $previous
        );
    }

    /**
     * Create exception for network/API failure at any step
     */
    public static function networkFailure(
        string $setupStep,
        array $rollbackData,
        array $completedSteps,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        return new self(
            "Network failure during {$setupStep}: {$reason}",
            $setupStep,
            $rollbackData,
            $completedSteps,
            $previous
        );
    }

    public function getSetupStep(): string
    {
        return $this->setupStep;
    }

    public function getRollbackData(): array
    {
        return $this->rollbackData;
    }

    public function getCompletedSteps(): array
    {
        return $this->completedSteps;
    }

    public function getOrganizationId(): ?string
    {
        return $this->organizationId;
    }

    public function getTenantId(): ?string
    {
        return $this->tenantId;
    }

    public function getAdminId(): ?string
    {
        return $this->adminId;
    }

    public function getCreatedResources(): array
    {
        return $this->createdResources;
    }

    public function isRollbackRequired(): bool
    {
        return $this->rollbackRequired;
    }

    public function getRollbackProcedures(): array
    {
        return $this->rollbackProcedures;
    }

    /**
     * Check if the failure is recoverable (can be retried)
     */
    public function isRecoverable(): bool
    {
        $recoverableSteps = [
            'api_key_generation',
            'domain_configuration'
        ];

        return in_array($this->setupStep, $recoverableSteps) ||
               $this->isNetworkFailure();
    }

    /**
     * Check if the failure was due to network issues
     */
    public function isNetworkFailure(): bool
    {
        if ($this->getPrevious() === null) {
            return false;
        }

        $networkExceptions = [
            'GuzzleHttp\Exception\ConnectException',
            'GuzzleHttp\Exception\RequestException',
            'Clubify\Checkout\Exceptions\HttpException'
        ];

        foreach ($networkExceptions as $exceptionClass) {
            if ($this->getPrevious() instanceof $exceptionClass) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get suggested retry delay in seconds
     */
    public function getRetryDelay(): int
    {
        if (!$this->isRecoverable()) {
            return 0;
        }

        // Exponential backoff based on network failures
        if ($this->isNetworkFailure()) {
            return min(300, 5 * pow(2, count($this->completedSteps))); // Max 5 minutes
        }

        // Standard retry for recoverable operations
        return 30;
    }

    /**
     * Get maximum retry attempts
     */
    public function getMaxRetries(): int
    {
        if (!$this->isRecoverable()) {
            return 0;
        }

        return $this->isNetworkFailure() ? 5 : 3;
    }

    /**
     * Generate specific rollback procedures based on completed steps
     */
    private function generateRollbackProcedures(): array
    {
        $procedures = [];

        // Reverse order of completed steps for proper cleanup
        $reversedSteps = array_reverse($this->completedSteps);

        foreach ($reversedSteps as $step) {
            switch ($step) {
                case 'api_keys_generated':
                    $procedures[] = [
                        'step' => 'revoke_api_keys',
                        'endpoint' => "/api-keys",
                        'method' => 'DELETE',
                        'description' => 'Revoke generated API keys',
                        'required' => true,
                        'order' => count($procedures) + 1
                    ];
                    break;

                case 'admin_created':
                    $procedures[] = [
                        'step' => 'delete_admin',
                        'endpoint' => "/admins/{$this->adminId}",
                        'method' => 'DELETE',
                        'description' => 'Delete created admin user',
                        'required' => true,
                        'order' => count($procedures) + 1
                    ];
                    break;

                case 'tenant_created':
                    $procedures[] = [
                        'step' => 'delete_tenant',
                        'endpoint' => "/tenants/{$this->tenantId}",
                        'method' => 'DELETE',
                        'description' => 'Delete created tenant',
                        'required' => true,
                        'order' => count($procedures) + 1
                    ];
                    break;

                case 'organization_created':
                    $procedures[] = [
                        'step' => 'delete_organization',
                        'endpoint' => "/organizations/{$this->organizationId}",
                        'method' => 'DELETE',
                        'description' => 'Delete created organization',
                        'required' => true,
                        'order' => count($procedures) + 1
                    ];
                    break;
            }
        }

        return $procedures;
    }

    /**
     * Get recovery options for partial success scenarios
     */
    public function getRecoveryOptions(): array
    {
        $options = [];

        switch ($this->setupStep) {
            case 'api_key_generation':
                $options[] = [
                    'type' => 'retry_from_step',
                    'step' => 'api_key_generation',
                    'description' => 'Retry API key generation with existing organization, tenant, and admin',
                    'endpoint' => "/api-keys",
                    'method' => 'POST'
                ];
                break;

            case 'domain_configuration':
                $options[] = [
                    'type' => 'retry_from_step',
                    'step' => 'domain_configuration',
                    'description' => 'Retry domain configuration with existing resources',
                    'endpoint' => "/domains",
                    'method' => 'POST'
                ];
                $options[] = [
                    'type' => 'complete_without_domain',
                    'description' => 'Complete setup without custom domain configuration',
                    'requires_confirmation' => true
                ];
                break;

            case 'tenant_creation':
            case 'admin_creation':
                $options[] = [
                    'type' => 'full_rollback_and_retry',
                    'description' => 'Rollback all changes and retry complete setup',
                    'requires_confirmation' => true
                ];
                break;
        }

        if ($this->isNetworkFailure()) {
            $options[] = [
                'type' => 'retry_with_backoff',
                'description' => 'Retry after network connectivity is restored',
                'delay_seconds' => $this->getRetryDelay(),
                'max_attempts' => $this->getMaxRetries()
            ];
        }

        return $options;
    }

    /**
     * Convert to array for logging and API responses
     */
    public function toArray(): array
    {
        return [
            'type' => 'organization_setup_failure',
            'message' => $this->getMessage(),
            'setup_step' => $this->setupStep,
            'completed_steps' => $this->completedSteps,
            'rollback_required' => $this->rollbackRequired,
            'rollback_procedures' => $this->rollbackProcedures,
            'recovery_options' => $this->getRecoveryOptions(),
            'created_resources' => $this->createdResources,
            'is_recoverable' => $this->isRecoverable(),
            'is_network_failure' => $this->isNetworkFailure(),
            'retry_delay' => $this->getRetryDelay(),
            'max_retries' => $this->getMaxRetries(),
            'organization_id' => $this->organizationId,
            'tenant_id' => $this->tenantId,
            'admin_id' => $this->adminId,
            'timestamp' => time()
        ];
    }
}