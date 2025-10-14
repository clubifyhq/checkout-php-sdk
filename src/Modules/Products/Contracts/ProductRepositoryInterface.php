<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Product devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Product pelo nome da entidade (ex: Order)
 * 2. Substitua product pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Product = Order
 * - product = order
 */

namespace Clubify\Checkout\Modules\Products\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Product Repository Interface
 *
 * Define o contrato para repositories que gerenciam Products.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Product:
 *
 * @package Clubify\Checkout\Modules\Products\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface ProductRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find product by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Product data or null if not found
     * @throws \Exception When search fails
     */
    public function findByEmail(string $fieldValue): ?array;

    /**
     * Find products by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of products
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update product status (common pattern)
     *
     * @param string $productId Product ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $productId, string $status): bool;

    /**
     * Get product statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create products (performance pattern)
     *
     * @param array $productsData Array of product data
     * @return array Result with created products
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $productsData): array;

    /**
     * Bulk update products (performance pattern)
     *
     * @param array $updates Array of updates with productId => data
     * @return array Result with updated products
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search products with advanced criteria (search pattern)
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
     * Archive product (soft delete pattern)
     *
     * @param string $productId Product ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $productId): bool;

    /**
     * Restore archived product (soft delete pattern)
     *
     * @param string $productId Product ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $productId): bool;

    /**
     * Get product history/audit trail (audit pattern)
     *
     * @param string $productId Product ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $productId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $productId Product ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $productId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $productId Product ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $productId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $productId Product ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $productId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate product cache
     *
     * @param string $productId Product ID
     * @return void
     */
    public function invalidateCache(string $productId): void;

    /**
     * Warm up cache for product
     *
     * @param string $productId Product ID
     * @return void
     */
    public function warmCache(string $productId): void;

    /**
     * Clear all product caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}
