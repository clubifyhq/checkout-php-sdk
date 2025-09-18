<?php

/**
 * Template para Repository Interface - Clubify Checkout SDK
 *
 * Este template define o contrato que todos os repositories de Notification devem seguir.
 * Estende RepositoryInterface para ter os métodos CRUD básicos.
 *
 * INSTRUÇÕES DE USO:
 * 1. Substitua Notification pelo nome da entidade (ex: Order)
 * 2. Substitua notification pela versão lowercase (ex: order)
 * 3. Adicione métodos específicos do domínio
 * 4. Documente todos os métodos com @param, @return e @throws
 *
 * EXEMPLO:
 * - Notification = Order
 * - notification = order
 */

namespace Clubify\Checkout\Modules\Notifications\Contracts;

use Clubify\Checkout\Contracts\RepositoryInterface;

/**
 * Notification Repository Interface
 *
 * Define o contrato para repositories que gerenciam Notifications.
 * Estende RepositoryInterface para incluir métodos CRUD básicos:
 * - create(), findById(), update(), delete()
 * - findAll(), count(), exists()
 *
 * Adiciona métodos específicos para operações de Notification:
 *
 * @package Clubify\Checkout\Modules\Notifications\Contracts
 * @version 2.0.0
 * @author Clubify Checkout Team
 */
interface NotificationRepositoryInterface extends RepositoryInterface
{
    // ==============================================
    // DOMAIN-SPECIFIC METHODS
    // Customize these methods for your entity
    // ==============================================

    /**
     * Find notification by specific field (common pattern)
     *
     * @param string $fieldValue The value to search for
     * @return array|null Notification data or null if not found
     * @throws \Exception When search fails
     */
    public function findBy{Field}(string $fieldValue): ?array;

    /**
     * Find notifications by tenant (multi-tenant pattern)
     *
     * @param string $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return array Array of notifications
     * @throws \Exception When search fails
     */
    public function findByTenant(string $tenantId, array $filters = []): array;

    /**
     * Update notification status (common pattern)
     *
     * @param string $notificationId Notification ID
     * @param string $status New status
     * @return bool True if updated successfully
     * @throws \Exception When update fails
     */
    public function updateStatus(string $notificationId, string $status): bool;

    /**
     * Get notification statistics (analytics pattern)
     *
     * @param array $filters Optional filters
     * @return array Statistics data
     * @throws \Exception When calculation fails
     */
    public function getStats(array $filters = []): array;

    /**
     * Bulk create notifications (performance pattern)
     *
     * @param array $notificationsData Array of notification data
     * @return array Result with created notifications
     * @throws \Exception When bulk creation fails
     */
    public function bulkCreate(array $notificationsData): array;

    /**
     * Bulk update notifications (performance pattern)
     *
     * @param array $updates Array of updates with notificationId => data
     * @return array Result with updated notifications
     * @throws \Exception When bulk update fails
     */
    public function bulkUpdate(array $updates): array;

    /**
     * Search notifications with advanced criteria (search pattern)
     *
     * @param array $criteria Search criteria
     * @param array $options Search options (sort, limit, offset)
     * @return array Search results with pagination
     * @throws \Exception When search fails
     */
    public function search(array $criteria, array $options = []): array;

    /**
     * Archive notification (soft delete pattern)
     *
     * @param string $notificationId Notification ID
     * @return bool True if archived successfully
     * @throws \Exception When archiving fails
     */
    public function archive(string $notificationId): bool;

    /**
     * Restore archived notification (soft delete pattern)
     *
     * @param string $notificationId Notification ID
     * @return bool True if restored successfully
     * @throws \Exception When restore fails
     */
    public function restore(string $notificationId): bool;

    /**
     * Get notification history/audit trail (audit pattern)
     *
     * @param string $notificationId Notification ID
     * @param array $options History options
     * @return array History records
     * @throws \Exception When history retrieval fails
     */
    public function getHistory(string $notificationId, array $options = []): array;

    // ==============================================
    // RELATIONSHIP METHODS
    // Add methods for managing relationships
    // ==============================================

    /**
     * Get related entities (relationship pattern)
     *
     * @param string $notificationId Notification ID
     * @param string $relationType Type of relationship
     * @param array $options Relationship options
     * @return array Related entities
     * @throws \Exception When relationship retrieval fails
     */
    public function getRelated(string $notificationId, string $relationType, array $options = []): array;

    /**
     * Add relationship (relationship pattern)
     *
     * @param string $notificationId Notification ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @param array $metadata Optional relationship metadata
     * @return bool True if relationship created successfully
     * @throws \Exception When relationship creation fails
     */
    public function addRelationship(string $notificationId, string $relatedId, string $relationType, array $metadata = []): bool;

    /**
     * Remove relationship (relationship pattern)
     *
     * @param string $notificationId Notification ID
     * @param string $relatedId Related entity ID
     * @param string $relationType Type of relationship
     * @return bool True if relationship removed successfully
     * @throws \Exception When relationship removal fails
     */
    public function removeRelationship(string $notificationId, string $relatedId, string $relationType): bool;

    // ==============================================
    // CACHING METHODS
    // Methods for cache management
    // ==============================================

    /**
     * Invalidate notification cache
     *
     * @param string $notificationId Notification ID
     * @return void
     */
    public function invalidateCache(string $notificationId): void;

    /**
     * Warm up cache for notification
     *
     * @param string $notificationId Notification ID
     * @return void
     */
    public function warmCache(string $notificationId): void;

    /**
     * Clear all notification caches
     *
     * @return void
     */
    public function clearAllCache(): void;
}