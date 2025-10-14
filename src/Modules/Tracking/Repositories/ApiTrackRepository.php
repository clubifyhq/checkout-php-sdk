<?php

/**
 * Template para Repository Implementation - Clubify Checkout SDK
 *
 * Este template implementa a interface do repository usando API HTTP calls.
 * Estende BaseRepository para ter funcionalidades comuns (cache, events, metrics).
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Track pelo nome da entidade (ex: Order)
 * 2. Substitua track pela versão lowercase (ex: order)
 * 3. Substitua Tracking pelo nome do módulo (ex: OrderManagement)
 * 4. Ajuste os endpoints da API
 * 5. Implemente os métodos específicos do domínio
 *
 * EXEMPLO:
 * - Track = Order
 * - track = order
 * - Tracking = OrderManagement
 */

namespace Clubify\Checkout\Modules\Tracking\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Core\Repository\BaseRepository;
use Clubify\Checkout\Modules\Tracking\Contracts\TrackRepositoryInterface;
use Clubify\Checkout\Modules\Tracking\Exceptions\TrackNotFoundException;
use Clubify\Checkout\Modules\Tracking\Exceptions\TrackValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * API Track Repository
 *
 * Implementa o TrackRepositoryInterface usando chamadas HTTP para a API.
 * Estende BaseRepository para funcionalidades de:
 * - Cache automático com TTL configurável
 * - Event dispatching para auditoria
 * - Metrics e monitoring
 * - Error handling padronizado
 * - HTTP client management
 *
 * Endpoints utilizados (relativos à base_uri):
 * - GET    tracks                 - List tracks
 * - POST   tracks                 - Create track
 * - GET    tracks/{id}           - Get track by ID
 * - PUT    tracks/{id}           - Update track
 * - DELETE tracks/{id}           - Delete track
 * - GET    tracks/search         - Search tracks
 * - PATCH  tracks/{id}/status    - Update status
 *
 * @package Clubify\Checkout\Modules\Tracking\Repositories
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
class ApiTrackRepository extends BaseRepository implements TrackRepositoryInterface
{
    /**
     * Get API endpoint for tracks
     */
    protected function getEndpoint(): string
    {
        return 'tracks';
    }

    /**
     * Get resource name for logging and metrics
     */
    protected function getResourceName(): string
    {
        return 'track';
    }

    /**
     * Get service name for monitoring
     */
    protected function getServiceName(): string
    {
        return 'track-management';
    }

    // ==============================================
    // DOMAIN-SPECIFIC IMPLEMENTATIONS
    // Implement methods from TrackRepositoryInterface
    // ==============================================

    /**
     * Find track by specific field
     */
    public function findByEmail(string $fieldValue): ?array
    {
        return $this->getCachedOrExecute(
            $this->getCacheKey("track:email:{$fieldValue}"),
            function () use ($fieldValue) {
                $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
                    'email' => $fieldValue
                ]);

                if (!ResponseHelper::isSuccessful($response)) {
                    if ($response->getStatusCode() === 404) {
                        return null;
                    }
                    throw new HttpException(
                        "Failed to find track by email: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                $data = ResponseHelper::getData($response);
                return $data['tracks'][0] ?? null;
            },
            300 // 5 minutes cache
        );
    }

    /**
     * Find tracks by tenant
     */
    public function findByTenant(string $tenantId, array $filters = []): array
    {
        $filters['tenant_id'] = $tenantId;
        return $this->findAll($filters);
    }

    /**
     * Update track status
     */
    public function updateStatus(string $trackId, string $status): bool
    {
        return $this->executeWithMetrics('update_track_status', function () use ($trackId, $status) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$trackId}/status", [
                'status' => $status
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($trackId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Track.StatusUpdated', [
                    'track_id' => $trackId,
                    'status' => $status,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get track statistics
     */
    public function getStats(array $filters = []): array
    {
        $cacheKey = $this->getCacheKey("track:stats:" . md5(serialize($filters)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($filters) {
                $queryParams = array_merge($filters, ['stats' => 'true']);
                $endpoint = "{$this->getEndpoint()}/stats?" . http_build_query($queryParams);

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    throw new HttpException(
                        "Failed to get track stats: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            600 // 10 minutes cache for stats
        );
    }

    /**
     * Bulk create tracks
     */
    public function bulkCreate(array $tracksData): array
    {
        return $this->executeWithMetrics('bulk_create_tracks', function () use ($tracksData) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/bulk", [
                'tracks' => $tracksData
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk create tracks: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Track.BulkCreated', [
                'count' => count($tracksData),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Bulk update tracks
     */
    public function bulkUpdate(array $updates): array
    {
        return $this->executeWithMetrics('bulk_update_tracks', function () use ($updates) {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/bulk", [
                'updates' => $updates
            ]);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "Failed to bulk update tracks: " . "HTTP request failed",
                    $response->getStatusCode()
                );
            }

            $result = ResponseHelper::getData($response);

            // Clear cache for updated tracks
            foreach (array_keys($updates) as $trackId) {
                $this->invalidateCache($trackId);
            }

            // Dispatch event
            $this->eventDispatcher?->emit('Clubify.Checkout.Track.BulkUpdated', [
                'count' => count($updates),
                'result' => $result,
                'timestamp' => time()
            ]);

            return $result;
        });
    }

    /**
     * Search tracks with advanced criteria
     */
    public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array
    {
        // Adapt parameters to API format
        $criteria = $filters;
        $options = [
            'sort' => $sort,
            'limit' => $limit,
            'offset' => $offset
        ];

        $cacheKey = $this->getCacheKey("track:search:" . md5(serialize($criteria + $options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($criteria, $options) {
                $payload = array_merge(['criteria' => $criteria], $options);
                $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/search", $payload);

                if (!ResponseHelper::isSuccessful($response)) {
                    throw new HttpException(
                        "Failed to search tracks: " . "HTTP request failed",
                        $response->getStatusCode()
                    );
                }

                return ResponseHelper::getData($response);
            },
            180 // 3 minutes cache for search results
        );
    }

    /**
     * Archive track
     */
    public function archive(string $trackId): bool
    {
        return $this->executeWithMetrics('archive_track', function () use ($trackId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$trackId}/archive");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($trackId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Track.Archived', [
                    'track_id' => $trackId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Restore archived track
     */
    public function restore(string $trackId): bool
    {
        return $this->executeWithMetrics('restore_track', function () use ($trackId) {
            $response = $this->makeHttpRequest('PATCH', "{$this->getEndpoint()}/{$trackId}/restore");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate cache
                $this->invalidateCache($trackId);

                // Dispatch event
                $this->eventDispatcher?->emit('Clubify.Checkout.Track.Restored', [
                    'track_id' => $trackId,
                    'timestamp' => time()
                ]);

                return true;
            }

            return false;
        });
    }

    /**
     * Get track history
     */
    public function getHistory(string $trackId, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("track:history:{$trackId}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($trackId, $options) {
                $endpoint = "{$this->getEndpoint()}/{$trackId}/history";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
                    if ($response->getStatusCode() === 404) {
                        throw new TrackNotFoundException("No history found for track ID: {$trackId}");
                    }
                    throw new HttpException(
                        "Failed to get track history: " . "HTTP request failed",
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
    public function getRelated(string $trackId, string $relationType, array $options = []): array
    {
        $cacheKey = $this->getCacheKey("track:related:{$trackId}:{$relationType}:" . md5(serialize($options)));

        return $this->getCachedOrExecute(
            $cacheKey,
            function () use ($trackId, $relationType, $options) {
                $endpoint = "{$this->getEndpoint()}/{$trackId}/{$relationType}";
                if ($options) {
                    $endpoint .= '?' . http_build_query($options);
                }

                $response = $this->makeHttpRequest('GET', $endpoint); if (!$response) {
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
    public function addRelationship(string $trackId, string $relatedId, string $relationType, array $metadata = []): bool
    {
        return $this->executeWithMetrics('add_relationship', function () use ($trackId, $relatedId, $relationType, $metadata) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$trackId}/{$relationType}", [
                'related_id' => $relatedId,
                'metadata' => $metadata
            ]);

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("track:related:{$trackId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    /**
     * Remove relationship
     */
    public function removeRelationship(string $trackId, string $relatedId, string $relationType): bool
    {
        return $this->executeWithMetrics('remove_relationship', function () use ($trackId, $relatedId, $relationType) {
            $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$trackId}/{$relationType}/{$relatedId}");

            if (ResponseHelper::isSuccessful($response)) {
                // Invalidate relationship cache
                $this->cache?->delete($this->getCacheKey("track:related:{$trackId}:{$relationType}:*"));

                return true;
            }

            return false;
        });
    }

    // ==============================================
    // CACHE MANAGEMENT METHODS
    // ==============================================

    /**
     * Invalidate track cache
     */
    public function invalidateCache(string $trackId): void
    {
        $patterns = [
            $this->getCacheKey("track:{$trackId}"),
            $this->getCacheKey("track:*:{$trackId}"),
            $this->getCacheKey("track:related:{$trackId}:*"),
            $this->getCacheKey("track:history:{$trackId}:*")
        ];

        foreach ($patterns as $pattern) {
            $this->cache?->delete($pattern);
        }
    }

    /**
     * Warm cache for track
     */
    public function warmCache(string $trackId): void
    {
        try {
            // Preload track data
            $this->findById($trackId);

            // Preload common relationships
            // $this->getRelated($trackId, 'common_relation');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to warm cache for track', [
                'track_id' => $trackId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear all track caches
     */
    public function clearAllCache(): void
    {
        $pattern = $this->getCacheKey('track:*');
        $this->cache?->delete($pattern);

        $this->logger->info('All track caches cleared');
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
