<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Organization\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Modules\Organization\Exceptions\OrganizationSetupException;
use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Exceptions\ConflictException;

/**
 * Service for handling organization setup retry logic with idempotency
 *
 * This service provides comprehensive retry mechanisms for failed organization
 * setup operations, including exponential backoff, idempotency handling,
 * and intelligent recovery strategies.
 */
class OrganizationSetupRetryService extends BaseService
{
    private array $retryHistory = [];
    private int $maxRetryAttempts = 5;
    private int $baseDelaySeconds = 1;
    private int $maxDelaySeconds = 300; // 5 minutes
    private float $backoffMultiplier = 2.0;
    private float $jitterFactor = 0.1;

    /**
     * Get service name
     */
    protected function getServiceName(): string
    {
        return 'organization_setup_retry';
    }

    /**
     * Get service version
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Execute setup operation with retry logic and idempotency
     */
    public function executeWithRetry(
        callable $setupOperation,
        string $idempotencyKey,
        array $setupData,
        ?string $fromStep = null
    ): array {
        return $this->executeWithMetrics('execute_with_retry', function () use ($setupOperation, $idempotencyKey, $setupData, $fromStep) {
            $attempt = 1;
            $lastException = null;
            $retryContext = [
                'idempotency_key' => $idempotencyKey,
                'from_step' => $fromStep,
                'start_time' => time()
            ];

            // Check for existing successful operation with same idempotency key
            $existingResult = $this->checkIdempotencyKey($idempotencyKey);
            if ($existingResult) {
                $this->logger->info('Found existing successful operation', [
                    'idempotency_key' => $idempotencyKey,
                    'result' => $existingResult
                ]);
                return $existingResult;
            }

            while ($attempt <= $this->maxRetryAttempts) {
                try {
                    $this->logger->info('Attempting organization setup', [
                        'attempt' => $attempt,
                        'idempotency_key' => $idempotencyKey,
                        'from_step' => $fromStep
                    ]);

                    // Execute the setup operation
                    $result = $setupOperation($setupData, $retryContext);

                    // Store successful result with idempotency key
                    $this->storeIdempotentResult($idempotencyKey, $result);

                    // Record successful retry
                    $this->recordRetryAttempt($idempotencyKey, $attempt, true, null);

                    $this->logger->info('Organization setup completed successfully', [
                        'attempt' => $attempt,
                        'idempotency_key' => $idempotencyKey,
                        'organization_id' => $result['organization']['id'] ?? null
                    ]);

                    return $result;

                } catch (OrganizationSetupException $e) {
                    $lastException = $e;

                    // Record failed attempt
                    $this->recordRetryAttempt($idempotencyKey, $attempt, false, $e);

                    if (!$e->isRecoverable() || $attempt >= $this->maxRetryAttempts) {
                        $this->logger->error('Organization setup failed permanently', [
                            'attempt' => $attempt,
                            'idempotency_key' => $idempotencyKey,
                            'error' => $e->getMessage(),
                            'is_recoverable' => $e->isRecoverable()
                        ]);
                        throw $e;
                    }

                    // Handle conflict resolution for existing resources
                    if ($this->isConflictRecoverable($e)) {
                        $recoveredData = $this->handleConflictRecovery($e, $setupData);
                        if ($recoveredData) {
                            return $recoveredData;
                        }
                    }

                    // Calculate delay before next attempt
                    $delay = $this->calculateRetryDelay($attempt, $e);

                    $this->logger->warning('Retrying organization setup after delay', [
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'error' => $e->getMessage()
                    ]);

                    sleep($delay);
                    $attempt++;

                } catch (\Exception $e) {
                    $lastException = $e;

                    // Record failed attempt
                    $this->recordRetryAttempt($idempotencyKey, $attempt, false, $e);

                    $this->logger->error('Unexpected error during organization setup', [
                        'attempt' => $attempt,
                        'idempotency_key' => $idempotencyKey,
                        'error' => $e->getMessage(),
                        'exception_type' => get_class($e)
                    ]);

                    throw $e;
                }
            }

            // All retry attempts exhausted
            throw new OrganizationSetupException(
                "Organization setup failed after {$this->maxRetryAttempts} attempts: " .
                ($lastException ? $lastException->getMessage() : 'Unknown error'),
                'max_retries_exceeded',
                [],
                [],
                $lastException
            );
        });
    }

    /**
     * Check for existing operation result using idempotency key
     */
    private function checkIdempotencyKey(string $idempotencyKey): ?array
    {
        $cacheKey = $this->getCacheKey("idempotent_result:{$idempotencyKey}");

        if ($this->cache->has($cacheKey)) {
            return $this->cache->get($cacheKey);
        }

        // Also check via API if cache miss
        try {
            $response = $this->httpClient->request('GET', "/setup/idempotency/{$idempotencyKey}");
            if (ResponseHelper::isSuccessful($response)) {
                $result = ResponseHelper::getData($response);
                if ($result && $result['status'] === 'completed') {
                    // Cache the result for future use
                    $this->cache->set($cacheKey, $result['data'], 3600);
                    return $result['data'];
                }
            }
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 404) {
                $this->logger->warning('Failed to check idempotency key', [
                    'idempotency_key' => $idempotencyKey,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return null;
    }

    /**
     * Store successful result with idempotency key
     */
    private function storeIdempotentResult(string $idempotencyKey, array $result): void
    {
        $cacheKey = $this->getCacheKey("idempotent_result:{$idempotencyKey}");

        // Store in cache for quick access
        $this->cache->set($cacheKey, $result, 3600);

        // Also store via API for persistence
        try {
            $this->httpClient->request('POST', '/setup/idempotency', [
                'idempotency_key' => $idempotencyKey,
                'status' => 'completed',
                'data' => $result,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to store idempotent result via API', [
                'idempotency_key' => $idempotencyKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Record retry attempt for monitoring and debugging
     */
    private function recordRetryAttempt(string $idempotencyKey, int $attempt, bool $success, ?\Exception $exception): void
    {
        $record = [
            'idempotency_key' => $idempotencyKey,
            'attempt' => $attempt,
            'success' => $success,
            'timestamp' => time(),
            'error' => $exception ? $exception->getMessage() : null,
            'exception_type' => $exception ? get_class($exception) : null
        ];

        $this->retryHistory[] = $record;

        // Dispatch retry event
        $this->dispatch('organization_setup.retry_attempt', $record);
    }

    /**
     * Calculate retry delay with exponential backoff and jitter
     */
    private function calculateRetryDelay(int $attempt, OrganizationSetupException $exception): int
    {
        // Use exception-specific delay if available
        $baseDelay = $exception->getRetryDelay() ?: $this->baseDelaySeconds;

        // Exponential backoff
        $delay = $baseDelay * pow($this->backoffMultiplier, $attempt - 1);

        // Apply jitter to avoid thundering herd
        $jitter = $delay * $this->jitterFactor * (mt_rand() / mt_getrandmax() - 0.5);
        $delay += $jitter;

        // Cap at maximum delay
        $delay = min($delay, $this->maxDelaySeconds);

        return (int) max(1, $delay);
    }

    /**
     * Check if a conflict can be recovered by using existing resources
     */
    private function isConflictRecoverable(OrganizationSetupException $exception): bool
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof ConflictException) {
            return false;
        }

        $recoverableConflicts = [
            'email_exists',
            'domain_exists',
            'subdomain_exists',
            'organization_exists'
        ];

        return in_array($previous->getConflictType(), $recoverableConflicts);
    }

    /**
     * Handle conflict recovery by retrieving existing resources
     */
    private function handleConflictRecovery(OrganizationSetupException $exception, array $setupData): ?array
    {
        $previous = $exception->getPrevious();

        if (!$previous instanceof ConflictException) {
            return null;
        }

        try {
            $this->logger->info('Attempting conflict recovery', [
                'conflict_type' => $previous->getConflictType(),
                'existing_resource_id' => $previous->getExistingResourceId()
            ]);

            switch ($previous->getConflictType()) {
                case 'email_exists':
                    return $this->recoverFromEmailConflict($previous, $setupData);

                case 'domain_exists':
                case 'subdomain_exists':
                    return $this->recoverFromDomainConflict($previous, $setupData);

                case 'organization_exists':
                    return $this->recoverFromOrganizationConflict($previous, $setupData);

                default:
                    return null;
            }

        } catch (\Exception $e) {
            $this->logger->error('Conflict recovery failed', [
                'conflict_type' => $previous->getConflictType(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Recover from email conflict by retrieving existing user
     */
    private function recoverFromEmailConflict(ConflictException $conflict, array $setupData): ?array
    {
        if (!$conflict->getExistingResourceId()) {
            return null;
        }

        $endpoint = $conflict->getRetrievalEndpoint();
        if (!$endpoint) {
            return null;
        }

        $response = $this->httpClient->request('GET', $endpoint);
        if (!ResponseHelper::isSuccessful($response)) {
            return null;
        }

        $existingUser = ResponseHelper::getData($response);

        // Verify the user belongs to the same organization context
        if (isset($setupData['organization_id']) &&
            $existingUser['organization_id'] !== $setupData['organization_id']) {
            return null;
        }

        $this->logger->info('Successfully recovered from email conflict', [
            'existing_user_id' => $existingUser['id'],
            'email' => $existingUser['email']
        ]);

        return $this->buildRecoveredResult($existingUser, $setupData, 'user_recovered');
    }

    /**
     * Recover from domain/subdomain conflict
     */
    private function recoverFromDomainConflict(ConflictException $conflict, array $setupData): ?array
    {
        if (!$conflict->getExistingResourceId()) {
            return null;
        }

        $endpoint = $conflict->getRetrievalEndpoint();
        if (!$endpoint) {
            return null;
        }

        $response = $this->httpClient->request('GET', $endpoint);
        if (!ResponseHelper::isSuccessful($response)) {
            return null;
        }

        $existingTenant = ResponseHelper::getData($response);

        $this->logger->info('Successfully recovered from domain conflict', [
            'existing_tenant_id' => $existingTenant['id'],
            'domain' => $existingTenant['domain'] ?? $existingTenant['subdomain']
        ]);

        return $this->buildRecoveredResult($existingTenant, $setupData, 'tenant_recovered');
    }

    /**
     * Recover from organization conflict
     */
    private function recoverFromOrganizationConflict(ConflictException $conflict, array $setupData): ?array
    {
        if (!$conflict->getExistingResourceId()) {
            return null;
        }

        $endpoint = $conflict->getRetrievalEndpoint();
        if (!$endpoint) {
            return null;
        }

        $response = $this->httpClient->request('GET', $endpoint);
        if (!ResponseHelper::isSuccessful($response)) {
            return null;
        }

        $existingOrganization = ResponseHelper::getData($response);

        $this->logger->info('Successfully recovered from organization conflict', [
            'existing_organization_id' => $existingOrganization['id'],
            'name' => $existingOrganization['name']
        ]);

        return $this->buildRecoveredResult($existingOrganization, $setupData, 'organization_recovered');
    }

    /**
     * Build recovery result based on existing resource
     */
    private function buildRecoveredResult(array $existingResource, array $setupData, string $recoveryType): array
    {
        return [
            'success' => true,
            'recovery_type' => $recoveryType,
            'existing_resource' => $existingResource,
            'recovered_at' => time(),
            'original_setup_data' => $setupData,
            'message' => "Successfully recovered existing resource instead of creating new one"
        ];
    }

    /**
     * Get retry statistics
     */
    public function getRetryStats(): array
    {
        $totalAttempts = count($this->retryHistory);
        $successfulRetries = count(array_filter($this->retryHistory, fn($record) => $record['success']));
        $failedRetries = $totalAttempts - $successfulRetries;

        return [
            'total_attempts' => $totalAttempts,
            'successful_retries' => $successfulRetries,
            'failed_retries' => $failedRetries,
            'success_rate' => $totalAttempts > 0 ? ($successfulRetries / $totalAttempts) * 100 : 0,
            'max_retry_attempts' => $this->maxRetryAttempts,
            'base_delay_seconds' => $this->baseDelaySeconds,
            'max_delay_seconds' => $this->maxDelaySeconds,
            'backoff_multiplier' => $this->backoffMultiplier,
            'recent_attempts' => array_slice($this->retryHistory, -10)
        ];
    }

    /**
     * Configure retry parameters
     */
    public function configureRetryParams(array $params): void
    {
        if (isset($params['max_retry_attempts'])) {
            $this->maxRetryAttempts = max(1, (int) $params['max_retry_attempts']);
        }

        if (isset($params['base_delay_seconds'])) {
            $this->baseDelaySeconds = max(1, (int) $params['base_delay_seconds']);
        }

        if (isset($params['max_delay_seconds'])) {
            $this->maxDelaySeconds = max(1, (int) $params['max_delay_seconds']);
        }

        if (isset($params['backoff_multiplier'])) {
            $this->backoffMultiplier = max(1.0, (float) $params['backoff_multiplier']);
        }

        if (isset($params['jitter_factor'])) {
            $this->jitterFactor = max(0.0, min(1.0, (float) $params['jitter_factor']));
        }
    }

    /**
     * Clear retry history
     */
    public function clearRetryHistory(): void
    {
        $this->retryHistory = [];
    }
}