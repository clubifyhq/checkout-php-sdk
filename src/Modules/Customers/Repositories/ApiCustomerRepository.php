<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Customer pelo nome da entidade (ex: Order)
 * 2. Substitua customer pela versão lowercase (ex: order)
 * 3. Substitua Customers pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Customer = Order
 * - customer = order
 * - Customers = OrderManagement
 */

namespace Clubify\Checkout\Modules\Customers\Repositories;

use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Customers\Contracts\CustomerRepositoryInterface;
use Clubify\Checkout\Modules\Customers\Exceptions\CustomerNotFoundException;
use Clubify\Checkout\Modules\Customers\Exceptions\CustomerValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Customer Repository
 *
 * Implementa o CustomerRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    customers                 - List customers
 * - POST   customers                 - Create customer
 * - GET    customers/{id}           - Get customer by ID
 * - PUT    customers/{id}           - Update customer
 * - DELETE customers/{id}           - Delete customer
 * - GET    customers/search         - Search customers
 * - PATCH  customers/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Customers\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiCustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    /**
     * Get API endpoint for customers
     */
    protected function getEndpoint(): string
    {
        return 'customers';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'customer';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'customer-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from CustomerRepositoryInterface
    // ==============================================

    /**
     * Find customer by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("customer:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->httpClient->get("{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find customer by email: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                $data = $response->getData();
                return $data['customers'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find customers by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update customer status
     */
    public function updateStatus(string $customerId, string $status): bool
    {
        return $this->executeWithMetrics('update_customer_status', function () use ($customerId, $status) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$customerId}/status", [
                'status' => $status
            ]);

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Customer.StatusUpdated', [
                    'customer_id' => $customerId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get customer statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("customer:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get customer stats: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create customers
     */
    public function bulkCreate(array $customersData): array
    {
        return $this->executeWithMetrics('bulk_create_customers', function () use ($customersData) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/bulk", [
                'customers' => $customersData
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk create customers: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Customer.BulkCreated', [
                'count' => count($customersData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update customers
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_customers', function () use ($updates) {
            $response = $this->httpClient->put("{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!$response->isSuccessful()) {
                throw new HttpException(
                    "Failed to bulk update customers: " . $response->getError(),
                    $response->getStatusCode()
                );
            }

            $result = $response->getData();

            // Clear cache for updated customers
            foreach (array_keys($updates) as $customerId) {
                $this->invalidateCache($customerId);
            }

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Customer.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search customers with advanced criteria
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        $cacheKey = $this->getCacheKey("customer:search:" . md5(serialize($filters + $sort + [$limit, $offset])));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters, $sort, $limit, $offset) {
                $payload = array_merge(['filters' => $filters], [
                    'sort' => $sort,
                    'limit' => $limit,
                    'offset' => $offset
                ]);
                $response = $this->httpClient->post("{$this->getEndpoint()}/search", $payload);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to search customers: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive customer
     */
    public function archive(string $customerId): bool
    {
        return $this->executeWithMetrics('archive_customer', function () use ($customerId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$customerId}/archive");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Customer.Archived', [
                    'customer_id' => $customerId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived customer
     */
    public function restore(string $customerId): bool
    {
        return $this->executeWithMetrics('restore_customer', function () use ($customerId) {
            $response = $this->httpClient->patch("{$this->getEndpoint()}/{$customerId}/restore");

            if ($response->isSuccessful()) {
                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Customer.Restored', [
                    'customer_id' => $customerId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get customer history
     */
    public function getHistory(string $customerId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("customer:history:{$customerId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($customerId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$customerId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    if ($response->getStatusCode() === 404) {
                        throw new CustomerNotFoundException("No history found for customer ID: {$customerId}");
                    }
                    throw new HttpException(
                        "Failed to get customer history: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
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
    public function getRelated(string $customerId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("customer:related:{$customerId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($customerId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$customerId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->httpClient->get($endpoint);

                if (!$response->isSuccessful()) {
                    throw new HttpException(
                        "Failed to get related {$relationType}: " . $response->getError(),
                        $response->getStatusCode()
                    );
                }

                return $response->getData();
            },
            300 // 5 minutes cache for relationships
        );
    }

    /**
     * Add relationship
     */
    public function addRelationship(string $customerId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($customerId, $relatedId, $relationType, $metadata) {
            $response = $this->httpClient->post("{$this->getEndpoint()}/{$customerId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("customer:related:{$customerId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $customerId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($customerId, $relatedId, $relationType) {
            $response = $this->httpClient->delete("{$this->getEndpoint()}/{$customerId}/{$relationType}/{$relatedId}");

            if ($response->isSuccessful()) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("customer:related:{$customerId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate customer cache
     */
    public function invalidateCache(string $customerId): void
    {
        $patterns = [
            $this->getCacheKey("customer:{$customerId}"),
            $this->getCacheKey("customer:*:{$customerId}"),
            $this->getCacheKey("customer:related:{$customerId}:*"),
            $this->getCacheKey("customer:history:{$customerId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for customer
     */
    public function warmCache(string $customerId): void
    {
        try {
            // Preload customer data
            $this->findById($customerId);

            // Preload common relationships
            // $this->getRelated($customerId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for customer', [
                'customer_id' => $customerId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all customer caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('customer:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All customer caches cleared');
    }
}
