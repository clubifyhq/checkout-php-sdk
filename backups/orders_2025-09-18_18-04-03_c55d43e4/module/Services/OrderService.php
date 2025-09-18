<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Services;

use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Modules\Orders\DTOs\OrderData;

/**
 * Serviço de gestão de pedidos
 *
 * Responsável pelas operações CRUD e lógica de negócio de pedidos:
 * - Criação e edição de pedidos
 * - Gestão de itens do pedido
 * - Operações de busca e filtros
 * - Cancelamento de pedidos
 * - Validação de dados
 * - Cache e performance
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de pedido
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de pedido
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'order';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Obtém o nome do serviço (ServiceInterface)
     */
    public function getName(): string
    {
        return 'order_service';
    }

    /**
     * Obtém a versão do serviço
     */
    public function getVersion(): string
    {
        return '2.0.0';
    }

    /**
     * Verifica se o serviço está saudável
     */
    public function isHealthy(): bool
    {
        try {
            // Test basic functionality with a count operation
            $this->count([]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('OrderService health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém métricas do serviço
     */
    public function getMetrics(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'service_name' => $this->getServiceName(),
            'timestamp' => time()
        ];
    }

    /**
     * Obtém configurações específicas do serviço
     */
    public function getConfig(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'endpoints' => [
                'create' => '/orders',
                'list' => '/orders',
                'get' => '/orders/{id}',
                'update' => '/orders/{id}',
                'cancel' => '/orders/{id}/cancel',
                'search' => '/orders/search'
            ],
            'features' => [
                'crud_operations' => true,
                'search' => true,
                'analytics' => true,
                'caching' => true,
                'events' => true
            ]
        ];
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->isHealthy();
    }

    /**
     * Obtém o status do serviço
     */
    public function getStatus(): array
    {
        return [
            'service' => $this->getName(),
            'version' => $this->getVersion(),
            'healthy' => $this->isHealthy(),
            'available' => $this->isAvailable(),
            'config' => $this->getConfig(),
            'metrics' => $this->getMetrics(),
            'timestamp' => time()
        ];
    }

    /**
     * Cria um novo pedido
     */
    public function create(array $orderData): array
    {
        return $this->executeWithMetrics('create_order', function () use ($orderData) {
            $this->validateOrderData($orderData);

            // Gerar número do pedido se não fornecido
            if (empty($orderData['order_number'])) {
                $orderData['order_number'] = $this->generateOrderNumber();
            }

            // Verificar unicidade do número do pedido
            if ($this->orderNumberExists($orderData['order_number'])) {
                $orderData['order_number'] = $this->generateUniqueOrderNumber();
            }

            // Calcular totais
            $orderData = $this->calculateOrderTotals($orderData);

            // Preparar dados do pedido
            $data = array_merge($orderData, [
                'status' => $orderData['status'] ?? 'pending',
                'payment_status' => $orderData['payment_status'] ?? 'pending',
                'fulfillment_status' => $orderData['fulfillment_status'] ?? 'pending',
                'source' => $orderData['source'] ?? 'api',
                'created_at' => date('Y-m-d H:i:s'),
                'metadata' => $this->generateOrderMetadata($orderData)
            ]);

            // Criar pedido via API
            $response = $this->httpClient->post('/orders', $data);
            $order = $response->getData();

            // Cache do pedido
            $this->cache->set($this->getCacheKey("order:{$order['id']}"), $order, 3600);
            $this->cache->set($this->getCacheKey("order_number:{$order['order_number']}"), $order, 3600);

            // Dispatch evento
            $this->dispatch('order.created', [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'customer_id' => $order['customer_id'],
                'total_amount' => $order['total_amount'],
                'status' => $order['status']
            ]);

            $this->logger->info('Order created successfully', [
                'order_id' => $order['id'],
                'order_number' => $order['order_number'],
                'customer_id' => $order['customer_id'],
                'total_amount' => $order['total_amount']
            ]);

            return $order;
        });
    }

    /**
     * Obtém um pedido por ID
     */
    public function get(string $orderId): ?array
    {
        return $this->getCachedOrExecute(
            "order:{$orderId}",
            fn () => $this->fetchOrderById($orderId),
            3600
        );
    }

    /**
     * Obtém pedido por número
     */
    public function getByNumber(string $orderNumber): ?array
    {
        return $this->getCachedOrExecute(
            "order_number:{$orderNumber}",
            fn () => $this->fetchOrderByNumber($orderNumber),
            3600
        );
    }

    /**
     * Lista pedidos com filtros
     */
    public function list(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->executeWithMetrics('list_orders', function () use ($filters, $page, $limit) {
            $queryParams = array_merge($filters, [
                'page' => $page,
                'limit' => $limit
            ]);

            $response = $this->httpClient->get('/orders', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Atualiza pedido
     */
    public function update(string $orderId, array $data): array
    {
        return $this->executeWithMetrics('update_order', function () use ($orderId, $data) {
            $this->validateOrderUpdateData($data);

            // Verificar se pedido existe
            $currentOrder = $this->get($orderId);
            if (!$currentOrder) {
                throw new ValidationException("Order not found: {$orderId}");
            }

            // Verificar se pode ser atualizado
            if (!$this->canUpdateOrder($currentOrder)) {
                throw new ValidationException("Order cannot be updated in current status: {$currentOrder['status']}");
            }

            // Recalcular totais se itens foram alterados
            if (isset($data['items'])) {
                $data = $this->calculateOrderTotals($data);
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/orders/{$orderId}", $data);
            $order = $response->getData();

            // Invalidar cache
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.updated', [
                'order_id' => $orderId,
                'updated_fields' => array_keys($data)
            ]);

            $this->logger->info('Order updated successfully', [
                'order_id' => $orderId,
                'updated_fields' => array_keys($data)
            ]);

            return $order;
        });
    }

    /**
     * Cancela pedido
     */
    public function cancel(string $orderId, array $cancelData = []): bool
    {
        return $this->executeWithMetrics('cancel_order', function () use ($orderId, $cancelData) {
            // Verificar se pedido existe
            $order = $this->get($orderId);
            if (!$order) {
                throw new ValidationException("Order not found: {$orderId}");
            }

            // Verificar se pode ser cancelado
            if (!$this->canCancelOrder($order)) {
                throw new ValidationException("Order cannot be cancelled in current status: {$order['status']}");
            }

            $data = array_merge($cancelData, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $cancelData['reason'] ?? 'Cancelled by user'
            ]);

            $response = $this->httpClient->post("/orders/{$orderId}/cancel", $data);

            // Invalidar cache
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.cancelled', [
                'order_id' => $orderId,
                'reason' => $data['cancellation_reason']
            ]);

            $this->logger->info('Order cancelled successfully', [
                'order_id' => $orderId,
                'reason' => $data['cancellation_reason']
            ]);

            return $response->getStatusCode() === 200;
        });
    }

    /**
     * Busca pedidos por texto
     */
    public function search(string $query, array $filters = []): array
    {
        return $this->executeWithMetrics('search_orders', function () use ($query, $filters) {
            $queryParams = array_merge($filters, ['q' => $query]);

            $response = $this->httpClient->get('/orders/search', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém pedidos por cliente
     */
    public function getByCustomer(string $customerId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_orders_by_customer', function () use ($customerId, $filters) {
            $queryParams = array_merge($filters, ['customer_id' => $customerId]);

            $response = $this->httpClient->get('/orders', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém pedidos por produto
     */
    public function getByProduct(string $productId, array $filters = []): array
    {
        return $this->executeWithMetrics('get_orders_by_product', function () use ($productId, $filters) {
            $queryParams = array_merge($filters, ['product_id' => $productId]);

            $response = $this->httpClient->get('/orders', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Obtém pedidos por período
     */
    public function getByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        return $this->executeWithMetrics('get_orders_by_date_range', function () use ($startDate, $endDate, $filters) {
            $queryParams = array_merge($filters, [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);

            $response = $this->httpClient->get('/orders', [
                'query' => $queryParams
            ]);

            return $response->getData() ?? [];
        });
    }

    /**
     * Adiciona item ao pedido
     */
    public function addItem(string $orderId, array $itemData): array
    {
        return $this->executeWithMetrics('add_order_item', function () use ($orderId, $itemData) {
            $this->validateOrderItemData($itemData);

            $response = $this->httpClient->post("/orders/{$orderId}/items", $itemData);
            $item = $response->getData();

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.item_added', [
                'order_id' => $orderId,
                'item_id' => $item['id'],
                'product_id' => $item['product_id']
            ]);

            return $item;
        });
    }

    /**
     * Remove item do pedido
     */
    public function removeItem(string $orderId, string $itemId): bool
    {
        return $this->executeWithMetrics('remove_order_item', function () use ($orderId, $itemId) {
            $response = $this->httpClient->delete("/orders/{$orderId}/items/{$itemId}");

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.item_removed', [
                'order_id' => $orderId,
                'item_id' => $itemId
            ]);

            return $response->getStatusCode() === 204;
        });
    }

    /**
     * Atualiza item do pedido
     */
    public function updateItem(string $orderId, string $itemId, array $itemData): array
    {
        return $this->executeWithMetrics('update_order_item', function () use ($orderId, $itemId, $itemData) {
            $this->validateOrderItemUpdateData($itemData);

            $response = $this->httpClient->put("/orders/{$orderId}/items/{$itemId}", $itemData);
            $item = $response->getData();

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.item_updated', [
                'order_id' => $orderId,
                'item_id' => $itemId,
                'updated_fields' => array_keys($itemData)
            ]);

            return $item;
        });
    }

    /**
     * Obtém itens do pedido
     */
    public function getItems(string $orderId): array
    {
        return $this->executeWithMetrics('get_order_items', function () use ($orderId) {
            $response = $this->httpClient->get("/orders/{$orderId}/items");
            return $response->getData() ?? [];
        });
    }

    /**
     * Conta total de pedidos
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->httpClient->get('/orders/count', [
                'query' => $filters
            ]);
            $data = $response->getData();
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            $this->logger->error('Failed to count orders', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Busca pedido por ID via API
     */
    private function fetchOrderById(string $orderId): ?array
    {
        try {
            $response = $this->httpClient->get("/orders/{$orderId}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca pedido por número via API
     */
    private function fetchOrderByNumber(string $orderNumber): ?array
    {
        try {
            $response = $this->httpClient->get("/orders/number/{$orderNumber}");
            return $response->getData();
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Invalida cache do pedido
     */
    private function invalidateOrderCache(string $orderId): void
    {
        $order = $this->get($orderId);

        $this->cache->delete($this->getCacheKey("order:{$orderId}"));

        if ($order && isset($order['order_number'])) {
            $this->cache->delete($this->getCacheKey("order_number:{$order['order_number']}"));
        }
    }

    /**
     * Valida dados do pedido
     */
    private function validateOrderData(array $data): void
    {
        $required = ['customer_id', 'items', 'total_amount', 'currency'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for order creation");
            }
        }

        if (!is_numeric($data['total_amount']) || $data['total_amount'] < 0) {
            throw new ValidationException('Total amount must be a positive number');
        }

        $allowedCurrencies = ['BRL', 'USD', 'EUR', 'GBP'];
        if (!in_array($data['currency'], $allowedCurrencies)) {
            throw new ValidationException("Invalid currency: {$data['currency']}");
        }

        if (!is_array($data['items']) || empty($data['items'])) {
            throw new ValidationException('Order must have at least one item');
        }

        // Validar cada item
        foreach ($data['items'] as $index => $item) {
            $this->validateOrderItemData($item, $index);
        }
    }

    /**
     * Valida dados de atualização do pedido
     */
    private function validateOrderUpdateData(array $data): void
    {
        if (isset($data['total_amount']) && (!is_numeric($data['total_amount']) || $data['total_amount'] < 0)) {
            throw new ValidationException('Total amount must be a positive number');
        }

        if (isset($data['currency'])) {
            $allowedCurrencies = ['BRL', 'USD', 'EUR', 'GBP'];
            if (!in_array($data['currency'], $allowedCurrencies)) {
                throw new ValidationException("Invalid currency: {$data['currency']}");
            }
        }

        if (isset($data['status'])) {
            $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
            if (!in_array($data['status'], $allowedStatuses)) {
                throw new ValidationException("Invalid status: {$data['status']}");
            }
        }

        if (isset($data['items'])) {
            if (!is_array($data['items']) || empty($data['items'])) {
                throw new ValidationException('Order must have at least one item');
            }

            foreach ($data['items'] as $index => $item) {
                $this->validateOrderItemData($item, $index);
            }
        }
    }

    /**
     * Valida dados do item do pedido
     */
    private function validateOrderItemData(array $item, int $index = 0): void
    {
        $required = ['product_id', 'name', 'quantity', 'unit_price'];
        foreach ($required as $field) {
            if (empty($item[$field])) {
                throw new ValidationException("Item {$index}: Field '{$field}' is required");
            }
        }

        if (!is_numeric($item['quantity']) || $item['quantity'] < 1) {
            throw new ValidationException("Item {$index}: Quantity must be a positive integer");
        }

        if (!is_numeric($item['unit_price']) || $item['unit_price'] < 0) {
            throw new ValidationException("Item {$index}: Unit price must be a positive number");
        }
    }

    /**
     * Valida dados de atualização do item
     */
    private function validateOrderItemUpdateData(array $data): void
    {
        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 1)) {
            throw new ValidationException('Quantity must be a positive integer');
        }

        if (isset($data['unit_price']) && (!is_numeric($data['unit_price']) || $data['unit_price'] < 0)) {
            throw new ValidationException('Unit price must be a positive number');
        }
    }

    /**
     * Verifica se número do pedido já existe
     */
    private function orderNumberExists(string $orderNumber): bool
    {
        try {
            $order = $this->fetchOrderByNumber($orderNumber);
            return $order !== null;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * Gera número do pedido
     */
    private function generateOrderNumber(): string
    {
        $prefix = strtoupper(substr($this->config->getTenantId() ?? 'ORD', 0, 3));
        $timestamp = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}-{$timestamp}-{$random}";
    }

    /**
     * Gera número único do pedido
     */
    private function generateUniqueOrderNumber(): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $orderNumber = $this->generateOrderNumber();
            $attempts++;
        } while ($this->orderNumberExists($orderNumber) && $attempts < $maxAttempts);

        if ($attempts >= $maxAttempts) {
            throw new ValidationException('Unable to generate unique order number');
        }

        return $orderNumber;
    }

    /**
     * Calcula totais do pedido
     */
    private function calculateOrderTotals(array $data): array
    {
        if (!isset($data['items']) || !is_array($data['items'])) {
            return $data;
        }

        $subtotal = 0;
        $totalQuantity = 0;

        foreach ($data['items'] as &$item) {
            $quantity = $item['quantity'] ?? 1;
            $unitPrice = $item['unit_price'] ?? 0;
            $itemTotal = $quantity * $unitPrice;

            $item['total_price'] = $itemTotal;
            $subtotal += $itemTotal;
            $totalQuantity += $quantity;
        }

        $data['subtotal'] = $subtotal;
        $data['tax_amount'] = $data['tax_amount'] ?? 0;
        $data['shipping_amount'] = $data['shipping_amount'] ?? 0;
        $data['discount_amount'] = $data['discount_amount'] ?? 0;

        $data['total_amount'] = $subtotal
            + $data['tax_amount']
            + $data['shipping_amount']
            - $data['discount_amount'];

        return $data;
    }

    /**
     * Verifica se pedido pode ser atualizado
     */
    private function canUpdateOrder(array $order): bool
    {
        $nonEditableStatuses = ['delivered', 'cancelled', 'refunded'];
        return !in_array($order['status'], $nonEditableStatuses);
    }

    /**
     * Verifica se pedido pode ser cancelado
     */
    private function canCancelOrder(array $order): bool
    {
        $cancellableStatuses = ['pending', 'processing'];
        return in_array($order['status'], $cancellableStatuses);
    }

    /**
     * Gera metadados do pedido
     */
    private function generateOrderMetadata(array $orderData): array
    {
        return [
            'created_by' => 'sdk',
            'version' => '1.0',
            'source' => $orderData['source'] ?? 'api',
            'channel' => $orderData['channel'] ?? 'web',
            'total_items' => count($orderData['items'] ?? []),
            'has_upsells' => !empty($orderData['upsells']),
            'has_discounts' => !empty($orderData['discounts']) || ($orderData['discount_amount'] ?? 0) > 0
        ];
    }
}
