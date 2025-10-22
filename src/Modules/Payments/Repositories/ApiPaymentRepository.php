<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Payment pelo nome da entidade (ex: Order)
 * 2. Substitua payment pela versão lowercase (ex: order)
 * 3. Substitua Payments pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Payment = Order
 * - payment = order
 * - Payments = OrderManagement
 */

namespace Clubify\Checkout\Modules\Payments\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Payments\Contracts\PaymentRepositoryInterface;
use Clubify\Checkout\Modules\Payments\Exceptions\PaymentNotFoundException;
use Clubify\Checkout\Modules\Payments\Exceptions\PaymentValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Payment Repository
 *
 * Implementa o PaymentRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    payments                 - List payments
 * - POST   payments                 - Create payment
 * - GET    payments/{id}           - Get payment by ID
 * - PUT    payments/{id}           - Update payment
 * - DELETE payments/{id}           - Delete payment
 * - GET    payments/search         - Search payments
 * - PATCH  payments/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Payments\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiPaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    /**
     * Get API endpoint for payments
     */
    protected function getEndpoint(): string
    {
        return 'payments';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'payment';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'payment-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from PaymentRepositoryInterface
    // ==============================================

    /**
     * Find payment by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("payment:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequestAndExtractData('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find payment by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['payments'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find payments by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update payment status
     */
    public function updateStatus(string $paymentId, string $status): bool
    {
        return $this->executeWithMetrics('update_payment_status', function () use ($paymentId, $status) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$paymentId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($paymentId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Payment.StatusUpdated', [
                    'payment_id' => $paymentId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get payment statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("payment:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get payment stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create payments
     */
    public function bulkCreate(array $paymentsData): array
    {
        return $this->executeWithMetrics('bulk_create_payments', function () use ($paymentsData) {
            $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/bulk", [
                'payments' => $paymentsData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create payments: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Payment.BulkCreated', [
                'count' => count($paymentsData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update payments
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_payments', function () use ($updates) {
            $response = $this->makeHttpRequestAndExtractData('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update payments: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Clear cache for updated payments
            foreach (array_keys($updates) as $paymentId) {
                $this->invalidateCache($paymentId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Payment.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search payments with advanced criteria
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("payment:search:" . md5(serialize($filters + $sort + [$limit, $offset])));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters, $sort, $limit, $offset) {
                $payload = [
                    'filters' => $filters,
                    'sort' => $sort,
                    'limit' => $limit,
                    'offset' => $offset
                ];
                $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search payments: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive payment
     */
    public function archive(string $paymentId): bool
    {
        return $this->executeWithMetrics('archive_payment', function () use ($paymentId) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$paymentId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($paymentId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Payment.Archived', [
                    'payment_id' => $paymentId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived payment
     */
    public function restore(string $paymentId): bool
    {
        return $this->executeWithMetrics('restore_payment', function () use ($paymentId) {
            $response = $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$paymentId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($paymentId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Payment.Restored', [
                    'payment_id' => $paymentId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get payment history
     */
    public function getHistory(string $paymentId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("payment:history:{$paymentId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($paymentId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$paymentId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        throw new PaymentNotFoundException("No history found for payment ID: {$paymentId}");
                    }
                    throw new HttpException(
                        "Failed to get payment history: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            900 // 15 minutes cache for history
        );
    }

    // ==============================================
    // RELATIONSHIP METHODS
    // ==============================================

    /**
     * Get related entities
     */
    public function getRelated(string $paymentId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("payment:related:{$paymentId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($paymentId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$paymentId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequestAndExtractData('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get related {$relationType}: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            300 // 5 minutes cache for relationships
        );
    }

    /**
     * Add relationship
     */
    public function addRelationship(string $paymentId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($paymentId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/{$paymentId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("payment:related:{$paymentId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $paymentId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($paymentId, $relatedId, $relationType) {
            $response = $this->makeHttpRequestAndExtractData('DELETE', "{$this->getEndpoint()}/{$paymentId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("payment:related:{$paymentId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate payment cache
     */
    public function invalidateCache(string $paymentId): void
    {
        $patterns = [
            $this->getCacheKey("payment:{$paymentId}"),
            $this->getCacheKey("payment:*:{$paymentId}"),
            $this->getCacheKey("payment:related:{$paymentId}:*"),
            $this->getCacheKey("payment:history:{$paymentId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for payment
     */
    public function warmCache(string $paymentId): void
    {
        try {
            // Preload payment data
            $this->findById($paymentId);

            // Preload common relationships
            // $this->getRelated($paymentId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for payment', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all payment caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('payment:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All payment caches cleared');
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequestAndExtractData(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * Método para verificar resposta HTTP (compatibilidade)
     */
    protected function isSuccessfulResponse($response): bool
    {
        return ResponseHelper::isSuccessful($response);
    }

    /**
     * Método para extrair dados da resposta (compatibilidade)
     */
    protected function extractResponseData($response): ?array
    {
        return ResponseHelper::getData($response);
    }

}
