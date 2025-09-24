<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Modules\Organization\Exceptions\OrganizationSetupException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Service for handling organization setup rollback procedures
 *
 * This service provides comprehensive rollback capabilities for failed
 * organization setup operations, ensuring proper cleanup and state consistency.
 */
class OrganizationSetupRollbackService extends BaseService
{
    private array $rollbackLog = [];
    private int $maxRollbackAttempts = 3;
    private int $rollbackDelaySeconds = 5;

    /**
     * Get service name
     */
    protected function getServiceName(): string
    {
        return 'organization_setup_rollback';
    }

    /**
     * Get service version
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Execute automatic rollback based on setup exception
     */
    public function executeRollback(OrganizationSetupException $exception): array
    {
        return $this->executeWithMetrics('execute_rollback', function () use ($exception) {
            $this->logger->warning('Starting organization setup rollback', [
                'organization_id' => $exception->getOrganizationId(),
                'setup_step' => $exception->getSetupStep(),
                'completed_steps' => $exception->getCompletedSteps(),
                'rollback_required' => $exception->isRollbackRequired()
            ]);

            if (!$exception->isRollbackRequired()) {
                return [
                    'rollback_performed' => false,
                    'reason' => 'No rollback required',
                    'created_resources' => []
                ];
            }

            $rollbackResults = [];
            $procedures = $exception->getRollbackProcedures();

            foreach ($procedures as $procedure) {
                $result = $this->executeProcedure($procedure, $exception);
                $rollbackResults[] = $result;

                $this->rollbackLog[] = [
                    'procedure' => $procedure['step'],
                    'result' => $result,
                    'timestamp' => time()
                ];

                // Add delay between procedures to avoid rate limits
                if (count($procedures) > 1 && $procedure !== end($procedures)) {
                    sleep($this->rollbackDelaySeconds);
                }
            }

            $success = $this->allProceduresSuccessful($rollbackResults);

            // Dispatch rollback completion event
            $this->dispatch('organization_setup.rollback_completed', [
                'organization_id' => $exception->getOrganizationId(),
                'success' => $success,
                'procedures_executed' => count($rollbackResults),
                'rollback_log' => $this->rollbackLog
            ]);

            return [
                'rollback_performed' => true,
                'success' => $success,
                'procedures_executed' => count($rollbackResults),
                'results' => $rollbackResults,
                'rollback_log' => $this->rollbackLog
            ];
        });
    }

    /**
     * Execute a specific rollback procedure
     */
    private function executeProcedure(array $procedure, OrganizationSetupException $exception): array
    {
        $attempt = 1;
        $lastException = null;

        while ($attempt <= $this->maxRollbackAttempts) {
            try {
                $this->logger->info("Executing rollback procedure: {$procedure['step']}", [
                    'attempt' => $attempt,
                    'endpoint' => $procedure['endpoint'],
                    'method' => $procedure['method']
                ]);

                $result = $this->executeHttpProcedure($procedure);

                return [
                    'step' => $procedure['step'],
                    'success' => true,
                    'attempt' => $attempt,
                    'response' => $result,
                    'description' => $procedure['description']
                ];

            } catch (\Exception $e) {
                $lastException = $e;
                $this->logger->error("Rollback procedure failed: {$procedure['step']}", [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'endpoint' => $procedure['endpoint']
                ]);

                if ($attempt < $this->maxRollbackAttempts) {
                    $delay = $this->calculateBackoffDelay($attempt);
                    sleep($delay);
                }

                $attempt++;
            }
        }

        return [
            'step' => $procedure['step'],
            'success' => false,
            'attempts' => $this->maxRollbackAttempts,
            'last_error' => $lastException ? $lastException->getMessage() : 'Unknown error',
            'description' => $procedure['description'],
            'requires_manual_cleanup' => true
        ];
    }

    /**
     * Execute HTTP-based rollback procedure
     */
    private function executeHttpProcedure(array $procedure): array
    {
        $method = strtoupper($procedure['method']);
        $endpoint = $procedure['endpoint'];

        switch ($method) {
            case 'DELETE':
                $response = $this->httpClient->request('DELETE', $endpoint);
                break;

            case 'PUT':
                $response = $this->httpClient->request('PUT', $endpoint, [
                    'status' => 'deleted',
                    'deleted_at' => date('Y-m-d H:i:s'),
                    'deleted_reason' => 'setup_rollback'
                ]);
                break;

            case 'POST':
                // For revocation endpoints
                $response = $this->httpClient->request('POST', $endpoint . '/revoke', [
                    'reason' => 'setup_rollback'
                ]);
                break;

            default:
                throw new \InvalidArgumentException("Unsupported HTTP method: {$method}");
        }

        if (!ResponseHelper::isSuccessful($response)) {
            throw new HttpException(
                "Rollback procedure failed: {$procedure['step']}",
                $response->getStatusCode()
            );
        }

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Check if all rollback procedures were successful
     */
    private function allProceduresSuccessful(array $results): bool
    {
        foreach ($results as $result) {
            if (!$result['success']) {
                return false;
            }
        }
        return true;
    }

    /**
     * Calculate backoff delay for retry attempts
     */
    private function calculateBackoffDelay(int $attempt): int
    {
        return min(60, $this->rollbackDelaySeconds * pow(2, $attempt - 1));
    }

    /**
     * Perform partial rollback for specific resources
     */
    public function rollbackSpecificResources(array $resources, string $organizationId): array
    {
        return $this->executeWithMetrics('rollback_specific_resources', function () use ($resources, $organizationId) {
            $results = [];

            foreach ($resources as $resourceType => $resourceId) {
                $procedure = $this->createProcedureForResource($resourceType, $resourceId, $organizationId);
                if ($procedure) {
                    $result = $this->executeProcedure($procedure, null);
                    $results[] = $result;
                }
            }

            return [
                'resources_processed' => count($resources),
                'results' => $results,
                'success' => $this->allProceduresSuccessful($results)
            ];
        });
    }

    /**
     * Create rollback procedure for specific resource type
     */
    private function createProcedureForResource(string $resourceType, string $resourceId, string $organizationId): ?array
    {
        switch ($resourceType) {
            case 'organization':
                return [
                    'step' => 'delete_organization',
                    'endpoint' => "/organizations/{$resourceId}",
                    'method' => 'DELETE',
                    'description' => 'Delete organization',
                    'required' => true
                ];

            case 'tenant':
                return [
                    'step' => 'delete_tenant',
                    'endpoint' => "/tenants/{$resourceId}",
                    'method' => 'DELETE',
                    'description' => 'Delete tenant',
                    'required' => true
                ];

            case 'admin':
                return [
                    'step' => 'delete_admin',
                    'endpoint' => "/admins/{$resourceId}",
                    'method' => 'DELETE',
                    'description' => 'Delete admin user',
                    'required' => true
                ];

            case 'api_keys':
                return [
                    'step' => 'revoke_api_keys',
                    'endpoint' => "/organizations/{$organizationId}/api-keys",
                    'method' => 'DELETE',
                    'description' => 'Revoke API keys',
                    'required' => true
                ];

            default:
                return null;
        }
    }

    /**
     * Generate rollback report for manual cleanup
     */
    public function generateManualCleanupReport(OrganizationSetupException $exception): array
    {
        $report = [
            'organization_id' => $exception->getOrganizationId(),
            'failure_point' => $exception->getSetupStep(),
            'cleanup_required' => [],
            'verification_steps' => [],
            'manual_procedures' => []
        ];

        foreach ($exception->getCreatedResources() as $resourceType => $resourceId) {
            $report['cleanup_required'][] = [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'cleanup_endpoint' => $this->getCleanupEndpoint($resourceType, $resourceId),
                'verification_endpoint' => $this->getVerificationEndpoint($resourceType, $resourceId)
            ];

            $report['verification_steps'][] = $this->getVerificationStep($resourceType, $resourceId);
            $report['manual_procedures'][] = $this->getManualProcedure($resourceType, $resourceId);
        }

        return $report;
    }

    /**
     * Get cleanup endpoint for resource type
     */
    private function getCleanupEndpoint(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            'organization' => "/organizations/{$resourceId}",
            'tenant' => "/tenants/{$resourceId}",
            'admin' => "/admins/{$resourceId}",
            'api_keys' => "/api-keys/{$resourceId}/revoke",
            default => "/resources/{$resourceType}/{$resourceId}"
        };
    }

    /**
     * Get verification endpoint for resource type
     */
    private function getVerificationEndpoint(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            'organization' => "/organizations/{$resourceId}",
            'tenant' => "/tenants/{$resourceId}",
            'admin' => "/admins/{$resourceId}",
            'api_keys' => "/api-keys/{$resourceId}/status",
            default => "/resources/{$resourceType}/{$resourceId}"
        };
    }

    /**
     * Get verification step description
     */
    private function getVerificationStep(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            'organization' => "Verify organization {$resourceId} is deleted",
            'tenant' => "Verify tenant {$resourceId} is deleted",
            'admin' => "Verify admin user {$resourceId} is deleted",
            'api_keys' => "Verify API keys {$resourceId} are revoked",
            default => "Verify {$resourceType} {$resourceId} is cleaned up"
        };
    }

    /**
     * Get manual procedure description
     */
    private function getManualProcedure(string $resourceType, string $resourceId): string
    {
        return match ($resourceType) {
            'organization' => "DELETE /organizations/{$resourceId} - Remove organization and all associated data",
            'tenant' => "DELETE /tenants/{$resourceId} - Remove tenant configuration and isolation",
            'admin' => "DELETE /admins/{$resourceId} - Remove admin user account and permissions",
            'api_keys' => "POST /api-keys/{$resourceId}/revoke - Revoke all API keys for the organization",
            default => "Manual cleanup required for {$resourceType} {$resourceId}"
        };
    }

    /**
     * Get rollback statistics
     */
    public function getRollbackStats(): array
    {
        return [
            'total_rollbacks' => count($this->rollbackLog),
            'successful_procedures' => count(array_filter($this->rollbackLog, fn($log) => $log['result']['success'])),
            'failed_procedures' => count(array_filter($this->rollbackLog, fn($log) => !$log['result']['success'])),
            'last_rollback' => end($this->rollbackLog)['timestamp'] ?? null,
            'max_attempts_per_procedure' => $this->maxRollbackAttempts,
            'delay_between_procedures' => $this->rollbackDelaySeconds
        ];
    }

    /**
     * Clear rollback log
     */
    public function clearRollbackLog(): void
    {
        $this->rollbackLog = [];
    }
}