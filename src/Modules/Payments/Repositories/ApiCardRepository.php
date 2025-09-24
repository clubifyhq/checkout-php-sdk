<?php

namespace Clubify\Checkout\Modules\Payments\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Payments\Contracts\CardRepositoryInterface;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Card Repository
 *
 * Implementa o CardRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    cards                 - List cards
 * - POST   cards                 - Create card
 * - GET    cards/{id}           - Get card by ID
 * - PUT    cards/{id}           - Update card
 * - DELETE cards/{id}           - Delete card
 * - GET    cards/search         - Search cards
 * - PATCH  cards/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Payments\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiCardRepository extends BaseRepository implements CardRepositoryInterface
{
    /**
     * Get API endpoint for cards
     */
    protected function getEndpoint(): string
    {
        return 'cards';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'card';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'card-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from CardRepositoryInterface
    // ==============================================

    /**
     * Find card by customer ID
     */
    public function findByCustomerId(string $customerId): array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("card:customer:{$customerId}"),
            function () use ($customerId) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'customer_id' => $customerId
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return [];
                    }
                    throw new HttpException(
                        "Failed to find cards by customer ID: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['cards'] ?? [];
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find card by token
     */
    public function findByToken(string $token): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("card:token:{$token}"),
            function () use ($token) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'token' => $token
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find card by token: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['cards'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Tokenize card
     */
    public function tokenizeCard(array $cardData, string $customerId): array
    {
        return $this->executeWithMetrics('tokenize_card', function () use ($cardData, $customerId) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/tokenize", [
                'card_data' => $cardData,
                'customer_id' => $customerId
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to tokenize card: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->dispatch('Clubify.Checkout.Card.Tokenized', [
                'customer_id' => $customerId,
                'token' => $result['token'] ?? null,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Detokenize card
     */
    public function detokenizeCard(string $token): array
    {
        return $this->executeWithMetrics('detokenize_card', function () use ($token) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/detokenize", [
                'token' => $token
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to detokenize card: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            return ResponseHelper::getData($response);
        });
    }

    /**
     * Validate card
     */
    public function validateCard(array $cardData): array
    {
        return $this->executeWithMetrics('validate_card', function () use ($cardData) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/validate", [
                'card_data' => $cardData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to validate card: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            return ResponseHelper::getData($response);
        });
    }

    /**
     * Update card status
     */
    public function updateStatus(string $cardId, string $status): bool
    {
        return $this->executeWithMetrics('update_card_status', function () use ($cardId, $status) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$cardId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($cardId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Card.StatusUpdated', [
                    'card_id' => $cardId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Archive card
     */
    public function archive(string $cardId): bool
    {
        return $this->executeWithMetrics('archive_card', function () use ($cardId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$cardId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($cardId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Card.Archived', [
                    'card_id' => $cardId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Expire card
     */
    public function expireCard(string $cardId): bool
    {
        return $this->executeWithMetrics('expire_card', function () use ($cardId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$cardId}/expire");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($cardId);

                // Dispatch event
                $this->eventDispatcher?->dispatch('Clubify.Checkout.Card.Expired', [
                    'card_id' => $cardId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get cards by brand
     */
    public function findByBrand(string $brand, array $filters = []): array
    {
        $filters['brand'] = $brand;
        return $this->findAll($filters);
    }

    /**
     * Get expired cards
     */
    public function findExpired(array $filters = []): array
    {
        $filters['status'] = 'expired';
        return $this->findAll($filters);
    }

    /**
     * Get card statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("card:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->makeHttpRequest('GET', $endpoint);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to get card stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            600 // 10 minutes cache for stats
        );
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate card cache
     */
    public function invalidateCache(string $cardId): void
    {
        $patterns = [
            $this->getCacheKey("card:{$cardId}"),
            $this->getCacheKey("card:*:{$cardId}"),
            $this->getCacheKey("card:customer:*"),
            $this->getCacheKey("card:token:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Clear all card caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('card:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All card caches cleared');
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
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
