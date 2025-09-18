<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Customer devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Customer pelo nome da entidade (ex: Order)
 * 2. Substitua customer pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Customer = Order
 * - customer = order
 */

namespace Clubify\Checkout\Modules\Customers\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Customer Repository Interface
 *
 * Define o contrato para repositories que gerenciam Customers.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Customer:
 *
 * @package Clubify\Checkout\Modules\Customers\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface CustomerRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find customer by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Customer data or null if not found
     * @throws \Exception When search fails
     */
    public function findByEmail(string $fieldValue): ?array;

    /**
     * Find customers by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of customers
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update customer status (common pattern)
     *
     * @param string $customerId Customer ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $customerId, string $status): bool;

    /**
     * Get customer statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create customers (performance pattern)
     *
     * @param array $customersData Array of customer data
     * @return array Result with created customers
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $customersData): array;

    /**
     * Bulk update customers (performance pattern)
     *
     * @param array $updates Array of updates with customerId => data
     * @return array Result with updated customers
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search customers with advanced criteria (search pattern)
     *
     * @param array $criteria Search criteria
     * @param array $options Search options (sort, limit, offset)
     * @return array Search results with pagination
     * @throws \Exception When search fails
     */
    public function search(array $criteria, array $options = []): array;

    /**
     * Archive customer (soft delete pattern)
     *
     * @param string $customerId Customer ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $customerId): bool;

    /**
     * Restore archived customer (soft delete pattern)
     *
     * @param string $customerId Customer ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $customerId): bool;

    /**
     * Get customer history/audit trail (audit pattern)
     *
     * @param string $customerId Customer ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $customerId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $customerId Customer ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $customerId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $customerId Customer ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $customerId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $customerId Customer ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $customerId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate customer cache
     *
     * @param string $customerId Customer ID
     * @return void
     */
    public function invalidateCache(string $customerId): void;

    /**
     * Warm up cache for customer
     *
     * @param string $customerId Customer ID
     * @return void
     */
    public function warmCache(string $customerId): void;

    /**
     * Clear all customer caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}
