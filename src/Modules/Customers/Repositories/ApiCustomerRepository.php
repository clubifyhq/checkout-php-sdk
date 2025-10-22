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

use Clubify\Checkout\Core\Http\ResponseHelper;
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
 * - GET    customers/email/{email}  - Find customer by email
 * - POST   customers/search         - Search customers (advanced)
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
                try {
                    $data = $this->makeHttpRequestAndExtractData('GET', "{$this->getEndpoint()}/email/{$fieldValue}");
                    return $data['customer'] ?? $data;
                } catch (HttpException $e) {
                    // If 404, customer not found - return null
                    if ($e->getCode() === 404) {
                        return null;
                    }
                    throw $e;
                }
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
            try {
                $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$customerId}/status", [
                    'status' => $status
                ]);

                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Customer.StatusUpdated', [
                    'customer_id' => $customerId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            } catch (HttpException $e) {
                return false;
            }
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

                return $this->makeHttpRequestAndExtractData('GET', $endpoint);
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
            $result = $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/bulk", [
                'customers' => $customersData
            ]);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Customer.BulkCreated', [
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
            $result = $this->makeHttpRequestAndExtractData('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            // Clear cache for updated customers
            foreach (array_keys($updates) as $customerId) {
                $this->invalidateCache($customerId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Customer.BulkUpdated', [
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
                return $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/search", $payload);
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
            try {
                $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$customerId}/archive");

                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Customer.Archived', [
                    'customer_id' => $customerId,
                    'timestamp' => time()
                ]);

                return true;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Restore archived customer
     */
    public function restore(string $customerId): bool
    {
        return $this->executeWithMetrics('restore_customer', function () use ($customerId) {
            try {
                $this->makeHttpRequestAndExtractData('PATCH', "{$this->getEndpoint()}/{$customerId}/restore");

                // Invalidate cache
                $this->invalidateCache($customerId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Customer.Restored', [
                    'customer_id' => $customerId,
                    'timestamp' => time()
                ]);

                return true;
            } catch (HttpException $e) {
                return false;
            }
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

                try {
                    return $this->makeHttpRequestAndExtractData('GET', $endpoint);
                } catch (HttpException $e) {
                    if ($e->getCode() === 404) {
                        throw new CustomerNotFoundException("No history found for customer ID: {$customerId}");
                    }
                    throw $e;
                }
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

                return $this->makeHttpRequestAndExtractData('GET', $endpoint);
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
            try {
                $this->makeHttpRequestAndExtractData('POST', "{$this->getEndpoint()}/{$customerId}/{$relationType}", [
                    'related_id' => $relatedId,
                    'metadata' => $metadata
                ]);

                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("customer:related:{$customerId}:{$relationType}:*"));

                return true;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $customerId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($customerId, $relatedId, $relationType) {
            try {
                $this->makeHttpRequestAndExtractData('DELETE', "{$this->getEndpoint()}/{$customerId}/{$relationType}/{$relatedId}");

                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("customer:related:{$customerId}:{$relationType}:*"));

                return true;
            } catch (HttpException $e) {
                return false;
            }
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
