<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Track devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Track pelo nome da entidade (ex: Order)
 * 2. Substitua track pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Track = Order
 * - track = order
 */

namespace Clubify\Checkout\Modules\Tracking\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Track Repository Interface
 *
 * Define o contrato para repositories que gerenciam Tracks.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Track:
 *
 * @package Clubify\Checkout\Modules\Tracking\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface TrackRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find track by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Track data or null if not found
     * @throws \Exception When search fails
     */
    public function findByEmail(string $fieldValue): ?array;

    /**
     * Find tracks by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of tracks
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update track status (common pattern)
     *
     * @param string $trackId Track ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $trackId, string $status): bool;

    /**
     * Get track statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create tracks (performance pattern)
     *
     * @param array $tracksData Array of track data
     * @return array Result with created tracks
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $tracksData): array;

    /**
     * Bulk update tracks (performance pattern)
     *
     * @param array $updates Array of updates with trackId => data
     * @return array Result with updated tracks
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search tracks with advanced criteria (search pattern)
     * Inherited from RepositoryInterface with standard signature
     *
     * @param array $filters Search filters/criteria
     * @param array $sort Sort options (e.g., ['field' => 'asc'])
     * @param int $limit Maximum number of results
     * @param int $offset Number of results to skip
     * @return array Search results with pagination
     * @throws \Exception When search fails
     */
    // Method inherited from RepositoryInterface - no need to redeclare
    // public function search(array $filters, array $sort = [], int $limit = 100, int $offset = 0): array;

    /**
     * Archive track (soft delete pattern)
     *
     * @param string $trackId Track ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $trackId): bool;

    /**
     * Restore archived track (soft delete pattern)
     *
     * @param string $trackId Track ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $trackId): bool;

    /**
     * Get track history/audit trail (audit pattern)
     *
     * @param string $trackId Track ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $trackId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $trackId Track ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $trackId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $trackId Track ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $trackId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $trackId Track ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $trackId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate track cache
     *
     * @param string $trackId Track ID
     * @return void
     */
    public function invalidateCache(string $trackId): void;

    /**
     * Warm up cache for track
     *
     * @param string $trackId Track ID
     * @return void
     */
    public function warmCache(string $trackId): void;

    /**
     * Clear all track caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}
