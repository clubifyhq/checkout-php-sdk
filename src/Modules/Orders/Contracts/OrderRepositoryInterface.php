<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Order devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Order pelo nome da entidade (ex: Order)
 * 2. Substitua order pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Order = Order
 * - order = order
 */

namespace Clubify\Checkout\Modules\Orders\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Order Repository Interface
 *
 * Define o contrato para repositories que gerenciam Orders.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Order:
 *
 * @package Clubify\Checkout\Modules\Orders\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface OrderRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find order by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Order data or null if not found
     * @throws \Exception When search fails
     */
    public function findByEmail(string $fieldValue): ?array;

    /**
     * Find orders by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of orders
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update order status (common pattern)
     *
     * @param string $orderId Order ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $orderId, string $status): bool;

    /**
     * Get order statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create orders (performance pattern)
     *
     * @param array $ordersData Array of order data
     * @return array Result with created orders
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $ordersData): array;

    /**
     * Bulk update orders (performance pattern)
     *
     * @param array $updates Array of updates with orderId => data
     * @return array Result with updated orders
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search orders with advanced criteria (search pattern)
     *
     * @param array $criteria Search criteria
     * @param array $options Search options (sort, limit, offset)
     * @return array Search results with pagination
     * @throws \Exception When search fails
     */
    public function search(array $criteria, array $options = []): array;

    /**
     * Archive order (soft delete pattern)
     *
     * @param string $orderId Order ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $orderId): bool;

    /**
     * Restore archived order (soft delete pattern)
     *
     * @param string $orderId Order ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $orderId): bool;

    /**
     * Get order history/audit trail (audit pattern)
     *
     * @param string $orderId Order ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $orderId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $orderId Order ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $orderId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $orderId Order ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $orderId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $orderId Order ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $orderId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate order cache
     *
     * @param string $orderId Order ID
     * @return void
     */
    public function invalidateCache(string $orderId): void;

    /**
     * Warm up cache for order
     *
     * @param string $orderId Order ID
     * @return void
     */
    public function warmCache(string $orderId): void;

    /**
     * Clear all order caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}
