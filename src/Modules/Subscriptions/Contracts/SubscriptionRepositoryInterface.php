<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Subscription devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Subscription pelo nome da entidade (ex: Order)
 * 2. Substitua subscription pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Subscription = Order
 * - subscription = order
 */

namespace Clubify\Checkout\Modules\Subscriptions\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Subscription Repository Interface
 *
 * Define o contrato para repositories que gerenciam Subscriptions.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Subscription:
 *
 * @package Clubify\Checkout\Modules\Subscriptions\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface SubscriptionRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find subscription by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Subscription data or null if not found
     * @throws \Exception When search fails
     */
    public function findByEmail(string $fieldValue): ?array;

    /**
     * Find subscriptions by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of subscriptions
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update subscription status (common pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $subscriptionId, string $status): bool;

    /**
     * Get subscription statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create subscriptions (performance pattern)
     *
     * @param array $subscriptionsData Array of subscription data
     * @return array Result with created subscriptions
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $subscriptionsData): array;

    /**
     * Bulk update subscriptions (performance pattern)
     *
     * @param array $updates Array of updates with subscriptionId => data
     * @return array Result with updated subscriptions
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search subscriptions with advanced criteria (search pattern)
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
     * Archive subscription (soft delete pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $subscriptionId): bool;

    /**
     * Restore archived subscription (soft delete pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $subscriptionId): bool;

    /**
     * Get subscription history/audit trail (audit pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $subscriptionId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $subscriptionId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $subscriptionId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $subscriptionId Subscription ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $subscriptionId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate subscription cache
     *
     * @param string $subscriptionId Subscription ID
     * @return void
     */
    public function invalidateCache(string $subscriptionId): void;

    /**
     * Warm up cache for subscription
     *
     * @param string $subscriptionId Subscription ID
     * @return void
     */
    public function warmCache(string $subscriptionId): void;

    /**
     * Clear all subscription caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}
