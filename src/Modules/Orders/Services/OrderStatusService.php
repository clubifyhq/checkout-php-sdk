<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Services;

use ClubifyCheckout\Services\BaseService;
use ClubifyCheckout\Exceptions\ValidationException;
use ClubifyCheckout\Exceptions\HttpException;

/**
 * Serviço de gestão de status de pedidos
 *
 * Responsável pelo gerenciamento do ciclo de vida dos pedidos:
 * - Atualização de status
 * - Histórico de mudanças
 * - Validação de transições
 * - Notificações automáticas
 * - Rastreamento de mudanças
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas status de pedidos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de status
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderStatusService extends BaseService
{
    /**
     * Status permitidos e suas transições
     */
    private const ALLOWED_TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled', 'partially_shipped'],
        'shipped' => ['delivered', 'returned'],
        'partially_shipped' => ['shipped', 'delivered', 'cancelled'],
        'delivered' => ['returned', 'exchanged'],
        'cancelled' => [], // Status final
        'refunded' => [], // Status final
        'returned' => ['exchanged', 'refunded'],
        'exchanged' => ['delivered']
    ];

    /**
     * Status que requerem notificação do cliente
     */
    private const NOTIFY_CUSTOMER_STATUSES = [
        'processing', 'shipped', 'delivered', 'cancelled', 'refunded'
    ];

    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'order_status';
    }

    /**
     * Atualiza status do pedido
     */
    public function updateStatus(string $orderId, string $status, array $metadata = []): bool
    {
        return $this->executeWithMetrics('update_order_status', function () use ($orderId, $status, $metadata) {
            // Validar status
            $this->validateStatus($status);

            // Obter pedido atual
            $currentOrder = $this->getCurrentOrder($orderId);
            if (!$currentOrder) {
                throw new ValidationException("Order not found: {$orderId}");
            }

            // Validar transição
            $this->validateStatusTransition($currentOrder['status'], $status);

            // Preparar dados da mudança de status
            $statusData = array_merge($metadata, [
                'status' => $status,
                'previous_status' => $currentOrder['status'],
                'changed_by' => $metadata['changed_by'] ?? 'system',
                'changed_by_type' => $metadata['changed_by_type'] ?? 'api',
                'reason' => $metadata['reason'] ?? $this->getDefaultStatusReason($status),
                'notify_customer' => $this->shouldNotifyCustomer($status),
                'metadata' => $this->generateStatusMetadata($currentOrder, $status, $metadata),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Atualizar status via API
            $response = $this->httpClient->put("/orders/{$orderId}/status", $statusData);

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.status_changed', [
                'order_id' => $orderId,
                'previous_status' => $currentOrder['status'],
                'new_status' => $status,
                'changed_by' => $statusData['changed_by'],
                'reason' => $statusData['reason'],
                'notify_customer' => $statusData['notify_customer']
            ]);

            $this->logger->info('Order status updated successfully', [
                'order_id' => $orderId,
                'previous_status' => $currentOrder['status'],
                'new_status' => $status,
                'changed_by' => $statusData['changed_by']
            ]);

            return $response->getStatusCode() === 200;
        });
    }

    /**
     * Obtém histórico de status do pedido
     */
    public function getStatusHistory(string $orderId): array
    {
        return $this->executeWithMetrics('get_order_status_history', function () use ($orderId) {
            $response = $this->httpClient->get("/orders/{$orderId}/status-history");
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém pedidos por status
     */
    public function getOrdersByStatus(string $status, array $filters = []): array
    {
        return $this->executeWithMetrics('get_orders_by_status', function () use ($status, $filters) {
            $this->validateStatus($status);

            $queryParams = array_merge($filters, ['status' => $status]);

            $response = $this->httpClient->get('/orders', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Atualiza múltiplos pedidos para um status
     */
    public function bulkUpdateStatus(array $orderIds, string $status, array $metadata = []): array
    {
        return $this->executeWithMetrics('bulk_update_order_status', function () use ($orderIds, $status, $metadata) {
            $this->validateStatus($status);

            if (empty($orderIds)) {
                throw new ValidationException('Order IDs cannot be empty');
            }

            $data = array_merge($metadata, [
                'order_ids' => $orderIds,
                'status' => $status,
                'changed_by' => $metadata['changed_by'] ?? 'system',
                'changed_by_type' => $metadata['changed_by_type'] ?? 'api',
                'reason' => $metadata['reason'] ?? $this->getDefaultStatusReason($status),
                'notify_customer' => $this->shouldNotifyCustomer($status)
            ]);

            $response = $this->httpClient->post('/orders/bulk-status-update', $data);
            $result = $response->getData();

            // Invalidar cache de todos os pedidos
            foreach ($orderIds as $orderId) {
                $this->invalidateOrderCache($orderId);
            }

            // Dispatch evento para cada pedido
            foreach ($result['updated'] ?? [] as $update) {
                $this->dispatch('order.status_changed', [
                    'order_id' => $update['order_id'],
                    'previous_status' => $update['previous_status'],
                    'new_status' => $status,
                    'changed_by' => $data['changed_by'],
                    'reason' => $data['reason'],
                    'bulk_operation' => true
                ]);
            }

            $this->logger->info('Bulk order status update completed', [
                'order_count' => count($orderIds),
                'status' => $status,
                'updated_count' => count($result['updated'] ?? []),
                'failed_count' => count($result['failed'] ?? [])
            ]);

            return $result;
        });
    }

    /**
     * Adiciona nota a mudança de status
     */
    public function addStatusNote(string $orderId, string $statusId, string $note, string $author = 'system'): bool
    {
        return $this->executeWithMetrics('add_status_note', function () use ($orderId, $statusId, $note, $author) {
            $data = [
                'note' => $note,
                'author' => $author,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $response = $this->httpClient->post("/orders/{$orderId}/status/{$statusId}/notes", $data);

            $this->dispatch('order.status_note_added', [
                'order_id' => $orderId,
                'status_id' => $statusId,
                'note' => $note,
                'author' => $author
            ]);

            return $response->getStatusCode() === 201;
        });
    }

    /**
     * Atualiza informações de rastreamento
     */
    public function updateTracking(string $orderId, array $trackingData): bool
    {
        return $this->executeWithMetrics('update_order_tracking', function () use ($orderId, $trackingData) {
            $this->validateTrackingData($trackingData);

            $response = $this->httpClient->put("/orders/{$orderId}/tracking", $trackingData);

            // Se tem código de rastreamento, atualizar status para 'shipped' se ainda estiver em 'processing'
            if (!empty($trackingData['tracking_code'])) {
                $order = $this->getCurrentOrder($orderId);
                if ($order && $order['status'] === 'processing') {
                    $this->updateStatus($orderId, 'shipped', [
                        'reason' => 'Tracking code added',
                        'changed_by' => 'system',
                        'external_data' => $trackingData
                    ]);
                }
            }

            $this->dispatch('order.tracking_updated', [
                'order_id' => $orderId,
                'tracking_data' => $trackingData
            ]);

            return $response->getStatusCode() === 200;
        });
    }

    /**
     * Obtém estatísticas de status
     */
    public function getStatusStatistics(array $filters = []): array
    {
        return $this->executeWithMetrics('get_status_statistics', function () use ($filters) {
            $response = $this->httpClient->get('/orders/status-statistics', [
                'query' => $filters
            ]);
            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém transições de status permitidas
     */
    public function getAllowedTransitions(string $currentStatus): array
    {
        return self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
    }

    /**
     * Verifica se transição é permitida
     */
    public function canTransitionTo(string $currentStatus, string $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedTransitions($currentStatus));
    }

    /**
     * Obtém próximos status possíveis
     */
    public function getNextPossibleStatuses(string $orderId): array
    {
        $order = $this->getCurrentOrder($orderId);
        if (!$order) {
            return [];
        }

        return $this->getAllowedTransitions($order['status']);
    }

    /**
     * Obtém pedido atual via cache ou API
     */
    private function getCurrentOrder(string $orderId): ?array
    {
        // Tentar buscar do cache primeiro
        $cacheKey = $this->getCacheKey("order:{$orderId}");
        $order = $this->cache->get($cacheKey);

        if ($order === null) {
            try {
                $response = $this->httpClient->get("/orders/{$orderId}");
                $order = $response->getData();

                // Cache por 5 minutos para status
                $this->cache->set($cacheKey, $order, 300);
            } catch (HttpException $e) {
                if ($e->getStatusCode() === 404) {
                    return null;
                }
                throw $e;
            }
        }

        return $order;
    }

    /**
     * Invalida cache do pedido
     */
    private function invalidateOrderCache(string $orderId): void
    {
        $this->cache->delete($this->getCacheKey("order:{$orderId}"));
    }

    /**
     * Valida status
     */
    private function validateStatus(string $status): void
    {
        $allowedStatuses = array_keys(self::ALLOWED_TRANSITIONS);
        $allowedStatuses[] = 'delivered'; // Status final
        $allowedStatuses[] = 'refunded'; // Status final

        if (!in_array($status, $allowedStatuses)) {
            throw new ValidationException("Invalid status: {$status}");
        }
    }

    /**
     * Valida transição de status
     */
    private function validateStatusTransition(string $currentStatus, string $newStatus): void
    {
        if ($currentStatus === $newStatus) {
            throw new ValidationException("Order is already in status: {$newStatus}");
        }

        if (!$this->canTransitionTo($currentStatus, $newStatus)) {
            throw new ValidationException(
                "Invalid status transition from '{$currentStatus}' to '{$newStatus}'"
            );
        }
    }

    /**
     * Valida dados de rastreamento
     */
    private function validateTrackingData(array $data): void
    {
        if (empty($data)) {
            throw new ValidationException('Tracking data cannot be empty');
        }

        if (isset($data['tracking_code']) && empty($data['tracking_code'])) {
            throw new ValidationException('Tracking code cannot be empty');
        }

        if (isset($data['carrier']) && empty($data['carrier'])) {
            throw new ValidationException('Carrier cannot be empty');
        }
    }

    /**
     * Verifica se deve notificar cliente
     */
    private function shouldNotifyCustomer(string $status): bool
    {
        return in_array($status, self::NOTIFY_CUSTOMER_STATUSES);
    }

    /**
     * Obtém motivo padrão para mudança de status
     */
    private function getDefaultStatusReason(string $status): string
    {
        return match ($status) {
            'processing' => 'Order is being processed',
            'shipped' => 'Order has been shipped',
            'delivered' => 'Order has been delivered',
            'cancelled' => 'Order was cancelled',
            'refunded' => 'Order has been refunded',
            'returned' => 'Order was returned',
            'exchanged' => 'Order was exchanged',
            'partially_shipped' => 'Order has been partially shipped',
            default => "Status changed to {$status}"
        };
    }

    /**
     * Gera metadados para mudança de status
     */
    private function generateStatusMetadata(array $order, string $newStatus, array $metadata): array
    {
        return [
            'order_number' => $order['order_number'],
            'customer_id' => $order['customer_id'],
            'total_amount' => $order['total_amount'],
            'currency' => $order['currency'],
            'items_count' => count($order['items'] ?? []),
            'transition_time' => date('Y-m-d H:i:s'),
            'user_agent' => $metadata['user_agent'] ?? null,
            'ip_address' => $metadata['ip_address'] ?? null,
            'source' => $metadata['source'] ?? 'api'
        ];
    }
}