<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Payments\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Payment Repository Interface
 *
 * Define o contrato para repositories que gerenciam Payments.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Payment:
 *
 * @package Clubify\Checkout\Modules\Payments\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface PaymentRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find payment by customer ID
     *
     * @param string $customerId The customer ID to search for
     * @return array Array of payments
     * @throws \Exception When search fails
     */
    public function findByCustomer(string $customerId): array;

    /**
     * Find payment by order ID
     *
     * @param string $orderId The order ID to search for
     * @return array Array of payments
     * @throws \Exception When search fails
     */
    public function findByOrder(string $orderId): array;

    /**
     * Find payment by gateway transaction ID
     *
     * @param string $gatewayTransactionId The gateway transaction ID
     * @return array|null Payment data or null if not found
     * @throws \Exception When search fails
     */
    public function findByGatewayTransactionId(string $gatewayTransactionId): ?array;

    /**
     * Find payments by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of payments
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update payment status (common pattern)
     *
     * @param string $paymentId Payment ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $paymentId, string $status): bool;

    /**
     * Get payment statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create payments (performance pattern)
     *
     * @param array $paymentsData Array of payment data
     * @return array Result with created payments
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $paymentsData): array;

    /**
     * Bulk update payments (performance pattern)
     *
     * @param array $updates Array of updates with paymentId => data
     * @return array Result with updated payments
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Archive payment (soft delete pattern)
     *
     * @param string $paymentId Payment ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $paymentId): bool;

    /**
     * Restore archived payment (soft delete pattern)
     *
     * @param string $paymentId Payment ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $paymentId): bool;

    /**
     * Get payment history/audit trail (audit pattern)
     *
     * @param string $paymentId Payment ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $paymentId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $paymentId Payment ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $paymentId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $paymentId Payment ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $paymentId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $paymentId Payment ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $paymentId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate payment cache
     *
     * @param string $paymentId Payment ID
     * @return void
     */
    public function invalidateCache(string $paymentId): void;

    /**
     * Warm up cache for payment
     *
     * @param string $paymentId Payment ID
     * @return void
     */
    public function warmCache(string $paymentId): void;

    /**
     * Clear all payment caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}