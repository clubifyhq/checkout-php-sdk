<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Repositories;

use Clubify\Checkout\Core\Http\ResponseHelper;
use ClubifyCheckout\Repositories\BaseRepository;
use Clubify\Checkout\Modules\Orders\Contracts\OrderRepositoryInterface;
use ClubifyCheckout\Exceptions\ValidationException;
use ClubifyCheckout\Exceptions\HttpException;

/**
 * Repositório de pedidos
 *
 * Implementa operações de persistência para pedidos:
 * - CRUD básico
 * - Busca e filtros avançados
 * - Operações de status
 * - Gestão de itens e upsells
 * - Estatísticas e relatórios
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas persistência de pedidos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa OrderRepositoryInterface
 * - I: Interface Segregation - Implementa interface específica
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrderRepository extends BaseRepository implements OrderRepositoryInterface
{
    /**
     * Obtém o nome da entidade
     */
    protected function getEntityName(): string
    {
        return 'order';
    }

    /**
     * Obtém o endpoint base
     */
    protected function getEndpoint(): string
    {
        return '/orders';
    }

    /**
     * Cria um novo pedido
     */
    public function create(array $orderData): array
    {
        return $this->executeWithMetrics('create_order', function () use ($orderData) {
            $response = $this->makeHttpRequest('POST', $this->getEndpoint(), $orderData);
            $order = ResponseHelper::getData($response);

            // Cache do pedido
            $this->cacheEntity($order['id'], $order);

            return $order;
        });
    }

    /**
     * Obtém pedido por ID
     */
    public function findById(string $orderId): ?array
    {
        return $this->getCachedOrExecute(
            $orderId,
            fn () => $this->fetchById($orderId)
        );
    }

    /**
     * Obtém pedido por número
     */
    public function findByNumber(string $orderNumber): ?array
    {
        return $this->getCachedOrExecute(
            "number:{$orderNumber}",
            fn () => $this->fetchByField('number', $orderNumber)
        );
    }

    /**
     * Lista pedidos com filtros
     */
    public function findMany(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $queryParams = array_merge($filters, [
            'page' => $page,
            'limit' => $limit
        ]);

        $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Atualiza pedido
     */
    public function update(string $orderId, array $data): array
    {
        return $this->executeWithMetrics('update_order', function () use ($orderId, $data) {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/{$orderId}", $data);
            $order = ResponseHelper::getData($response);

            // Atualizar cache
            $this->cacheEntity($orderId, $order);

            return $order;
        });
    }

    /**
     * Remove pedido
     */
    public function delete(string $orderId): bool
    {
        return $this->executeWithMetrics('delete_order', function () use ($orderId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$orderId}");

                // Invalidar cache
                $this->invalidateCache($orderId);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Busca pedidos por texto
     */
    public function search(string $query, array $filters = []): array
    {
        $queryParams = array_merge($filters, ['q' => $query]);

        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/search", [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém pedidos por cliente
     */
    public function findByCustomer(string $customerId, array $filters = []): array
    {
        $queryParams = array_merge($filters, ['customer_id' => $customerId]);

        $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém pedidos por produto
     */
    public function findByProduct(string $productId, array $filters = []): array
    {
        $queryParams = array_merge($filters, ['product_id' => $productId]);

        $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém pedidos por status
     */
    public function findByStatus(string $status, array $filters = []): array
    {
        $queryParams = array_merge($filters, ['status' => $status]);

        $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém pedidos por período
     */
    public function findByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        $queryParams = array_merge($filters, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $response = $this->makeHttpRequest('GET', $this->getEndpoint(), [
            'query' => $queryParams
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Conta total de pedidos
     */
    public function count(array $filters = []): int
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/count", [
                'query' => $filters
            ]);
            $data = ResponseHelper::getData($response);
            return $data['count'] ?? 0;
        } catch (HttpException $e) {
            return 0;
        }
    }

    /**
     * Obtém estatísticas de pedidos
     */
    public function getStatistics(array $filters = []): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/statistics", [
            'query' => $filters
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém estatísticas de receita
     */
    public function getRevenueStats(array $dateRange = []): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/revenue-stats", [
            'query' => $dateRange
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Atualiza status do pedido
     */
    public function updateStatus(string $orderId, string $status, array $metadata = []): bool
    {
        return $this->executeWithMetrics('update_order_status', function () use ($orderId, $status, $metadata) {
            try {
                $data = array_merge($metadata, ['status' => $status]);
                $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/{$orderId}/status", $data);

                // Invalidar cache do pedido
                $this->invalidateCache($orderId);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Obtém histórico de status
     */
    public function getStatusHistory(string $orderId): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$orderId}/status-history");
        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Cancela pedido
     */
    public function cancel(string $orderId, array $cancelData = []): bool
    {
        return $this->executeWithMetrics('cancel_order', function () use ($orderId, $cancelData) {
            try {
                $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$orderId}/cancel", $cancelData);

                // Invalidar cache do pedido
                $this->invalidateCache($orderId);

                return $response->getStatusCode() === 200;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Adiciona item ao pedido
     */
    public function addItem(string $orderId, array $itemData): array
    {
        return $this->executeWithMetrics('add_order_item', function () use ($orderId, $itemData) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$orderId}/items", $itemData);
            $item = ResponseHelper::getData($response);

            // Invalidar cache do pedido
            $this->invalidateCache($orderId);

            return $item;
        });
    }

    /**
     * Remove item do pedido
     */
    public function removeItem(string $orderId, string $itemId): bool
    {
        return $this->executeWithMetrics('remove_order_item', function () use ($orderId, $itemId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$orderId}/items/{$itemId}");

                // Invalidar cache do pedido
                $this->invalidateCache($orderId);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Atualiza item do pedido
     */
    public function updateItem(string $orderId, string $itemId, array $itemData): array
    {
        return $this->executeWithMetrics('update_order_item', function () use ($orderId, $itemId, $itemData) {
            $response = $this->makeHttpRequest('PUT', "{$this->getEndpoint()}/{$orderId}/items/{$itemId}", $itemData);
            $item = ResponseHelper::getData($response);

            // Invalidar cache do pedido
            $this->invalidateCache($orderId);

            return $item;
        });
    }

    /**
     * Obtém itens do pedido
     */
    public function getItems(string $orderId): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$orderId}/items");
        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Adiciona upsell ao pedido
     */
    public function addUpsell(string $orderId, array $upsellData): array
    {
        return $this->executeWithMetrics('add_order_upsell', function () use ($orderId, $upsellData) {
            $response = $this->makeHttpRequest('POST', "{$this->getEndpoint()}/{$orderId}/upsells", $upsellData);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache do pedido
            $this->invalidateCache($orderId);

            return $upsell;
        });
    }

    /**
     * Remove upsell do pedido
     */
    public function removeUpsell(string $orderId, string $upsellId): bool
    {
        return $this->executeWithMetrics('remove_order_upsell', function () use ($orderId, $upsellId) {
            try {
                $response = $this->makeHttpRequest('DELETE', "{$this->getEndpoint()}/{$orderId}/upsells/{$upsellId}");

                // Invalidar cache do pedido
                $this->invalidateCache($orderId);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                return false;
            }
        });
    }

    /**
     * Obtém upsells do pedido
     */
    public function getUpsells(string $orderId): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$orderId}/upsells");
        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Verifica se pedido existe
     */
    public function exists(string $orderId): bool
    {
        try {
            $order = $this->findById($orderId);
            return $order !== null;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * Obtém top clientes
     */
    public function getTopCustomers(int $limit = 10): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/top-customers", [
            'query' => ['limit' => $limit]
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém top produtos
     */
    public function getTopProducts(int $limit = 10): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/top-products", [
            'query' => ['limit' => $limit]
        ]);

        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Obtém métricas de conversão
     */
    public function getConversionMetrics(): array
    {
        $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/conversion-metrics");
        return ResponseHelper::getData($response) ?? [];
    }

    /**
     * Busca entidade por campo específico
     */
    private function fetchByField(string $field, string $value): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$field}/{$value}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Busca entidade por ID
     */
    private function fetchById(string $id): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "{$this->getEndpoint()}/{$id}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Método centralizado para fazer chamadas HTTP através do Core\Http\Client
     * Garante uso consistente do ResponseHelper
     */
    protected function makeHttpRequest(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, $uri, $options);

            if (!ResponseHelper::isSuccessful($response)) {
                throw new HttpException(
                    "HTTP {$method} request failed to {$uri}",
                    $response->getStatusCode()
                );
            }

            $data = ResponseHelper::getData($response);
            if ($data === null) {
                throw new HttpException("Failed to decode response data from {$uri}");
            }

            return $data;

        } catch (\Exception $e) {
            $this->logger->error("HTTP request failed", [
                'method' => $method,
                'uri' => $uri,
                'error' => $e->getMessage(),
                'service' => static::class
            ]);
            throw $e;
        }
    }

    /**
     * Método para verificar resposta HTTP (compatibilidade)
     */
    protected function isSuccessfulResponse($response): bool
    {
        return ResponseHelper::isSuccessful($response);
    }

    /**
     * Método para extrair dados da resposta (compatibilidade)
     */
    protected function extractResponseData($response): ?array
    {
        return ResponseHelper::getData($response);
    }

}
