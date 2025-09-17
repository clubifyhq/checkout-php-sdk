<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Contracts;

/**
 * Interface para repositório de pedidos
 *
 * Define operações de persistência para pedidos:
 * - CRUD básico de pedidos
 * - Busca por filtros
 * - Operações de status
 * - Estatísticas e relatórios
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Define apenas operações de repositório
 * - I: Interface Segregation - Interface específica para pedidos
 * - D: Dependency Inversion - Permite inversão de dependências
 */
interface OrderRepositoryInterface
{
    /**
     * Cria um novo pedido
     */
    public function create(array $orderData): array;

    /**
     * Obtém pedido por ID
     */
    public function findById(string $orderId): ?array;

    /**
     * Obtém pedido por número
     */
    public function findByNumber(string $orderNumber): ?array;

    /**
     * Lista pedidos com filtros
     */
    public function findMany(array $filters = [], int $page = 1, int $limit = 20): array;

    /**
     * Atualiza pedido
     */
    public function update(string $orderId, array $data): array;

    /**
     * Remove pedido
     */
    public function delete(string $orderId): bool;

    /**
     * Busca pedidos por texto
     */
    public function search(string $query, array $filters = []): array;

    /**
     * Obtém pedidos por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array;

    /**
     * Obtém pedidos por produto
     */
    public function findByProduct(string $productId, array $filters = []): array;

    /**
     * Obtém pedidos por status
     */
    public function findByStatus(string $status, array $filters = []): array;

    /**
     * Obtém pedidos por período
     */
    public function findByDateRange(string $startDate, string $endDate, array $filters = []): array;

    /**
     * Conta total de pedidos
     */
    public function count(array $filters = []): int;

    /**
     * Obtém estatísticas de pedidos
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Obtém estatísticas de receita
     */
    public function getRevenueStats(array $dateRange = []): array;

    /**
     * Atualiza status do pedido
     */
    public function updateStatus(string $orderId, string $status, array $metadata = []): bool;

    /**
     * Obtém histórico de status
     */
    public function getStatusHistory(string $orderId): array;

    /**
     * Cancela pedido
     */
    public function cancel(string $orderId, array $cancelData = []): bool;

    /**
     * Adiciona item ao pedido
     */
    public function addItem(string $orderId, array $itemData): array;

    /**
     * Remove item do pedido
     */
    public function removeItem(string $orderId, string $itemId): bool;

    /**
     * Atualiza item do pedido
     */
    public function updateItem(string $orderId, string $itemId, array $itemData): array;

    /**
     * Obtém itens do pedido
     */
    public function getItems(string $orderId): array;

    /**
     * Adiciona upsell ao pedido
     */
    public function addUpsell(string $orderId, array $upsellData): array;

    /**
     * Remove upsell do pedido
     */
    public function removeUpsell(string $orderId, string $upsellId): bool;

    /**
     * Obtém upsells do pedido
     */
    public function getUpsells(string $orderId): array;

    /**
     * Verifica se pedido existe
     */
    public function exists(string $orderId): bool;

    /**
     * Obtém top clientes
     */
    public function getTopCustomers(int $limit = 10): array;

    /**
     * Obtém top produtos
     */
    public function getTopProducts(int $limit = 10): array;

    /**
     * Obtém métricas de conversão
     */
    public function getConversionMetrics(): array;
}