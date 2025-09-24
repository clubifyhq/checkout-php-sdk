<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;
use Clubify\Checkout\Contracts\ServiceInterface;

/**
 * Serviço de gestão de upsells em pedidos
 *
 * Responsável pelo gerenciamento de upsells aplicados aos pedidos:
 * - Adição e remoção de upsells
 * - Validação de regras de upsell
 * - Cálculo de preços e descontos
 * - Rastreamento de conversões
 * - Analytics de upsells
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas upsells de pedidos
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de upsells
 * - D: Dependency Inversion - Depende de abstrações
 */
class UpsellOrderService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'order_upsell';
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
        return 'upsell_order_service';
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
            // Test basic functionality with a simple metrics call
            $this->getUpsellStatistics(['limit' => 1]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('UpsellOrderService health check failed', [
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
                'add_upsell' => '/orders/{id}/upsells',
                'remove_upsell' => '/orders/{id}/upsells/{upsell_id}',
                'list_upsells' => '/orders/{id}/upsells',
                'statistics' => '/orders/upsell-statistics'
            ],
            'features' => [
                'upsell_management' => true,
                'conversion_tracking' => true,
                'pricing_calculations' => true,
                'analytics' => true,
                'validation_rules' => true
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
     * Adiciona upsell ao pedido
     */
    public function addUpsell(string $orderId, array $upsellData): array
    {
        return $this->executeWithMetrics('add_order_upsell', function () use ($orderId, $upsellData) {
            $this->validateUpsellData($upsellData);

            // Verificar se pedido existe e pode receber upsells
            $order = $this->getCurrentOrder($orderId);
            if (!$order) {
                throw new ValidationException("Order not found: {$orderId}");
            }

            if (!$this->canAddUpsell($order)) {
                throw new ValidationException("Cannot add upsell to order in status: {$order['status']}");
            }

            // Verificar se upsell já foi aplicado
            if ($this->hasUpsellProduct($orderId, $upsellData['product_id'])) {
                throw new ValidationException("Upsell product already added to order");
            }

            // Calcular preço do upsell
            $upsellData = $this->calculateUpsellPrice($upsellData, $order);

            // Preparar dados do upsell
            $data = array_merge($upsellData, [
                'order_id' => $orderId,
                'applied_at' => date('Y-m-d H:i:s'),
                'applied_by' => $upsellData['applied_by'] ?? 'system',
                'source' => $upsellData['source'] ?? 'api',
                'metadata' => $this->generateUpsellMetadata($order, $upsellData)
            ]);

            // Adicionar upsell via API
            $response = $this->makeHttpRequest('POST', "/orders/{$orderId}/upsells", $data);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.upsell_added', [
                'order_id' => $orderId,
                'upsell_id' => $upsell['id'],
                'product_id' => $upsell['product_id'],
                'amount' => $upsell['amount'],
                'applied_by' => $data['applied_by']
            ]);

            $this->logger->info('Upsell added to order successfully', [
                'order_id' => $orderId,
                'upsell_id' => $upsell['id'],
                'product_id' => $upsell['product_id'],
                'amount' => $upsell['amount']
            ]);

            return $upsell;
        });
    }

    /**
     * Remove upsell do pedido
     */
    public function removeUpsell(string $orderId, string $upsellId): bool
    {
        return $this->executeWithMetrics('remove_order_upsell', function () use ($orderId, $upsellId) {
            // Verificar se pedido existe
            $order = $this->getCurrentOrder($orderId);
            if (!$order) {
                throw new ValidationException("Order not found: {$orderId}");
            }

            if (!$this->canRemoveUpsell($order)) {
                throw new ValidationException("Cannot remove upsell from order in status: {$order['status']}");
            }

            // Obter dados do upsell antes de remover
            $upsell = $this->getUpsellById($orderId, $upsellId);
            if (!$upsell) {
                throw new ValidationException("Upsell not found: {$upsellId}");
            }

            $response = $this->makeHttpRequest('DELETE', "/orders/{$orderId}/upsells/{$upsellId}");

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.upsell_removed', [
                'order_id' => $orderId,
                'upsell_id' => $upsellId,
                'product_id' => $upsell['product_id'],
                'amount' => $upsell['amount']
            ]);

            $this->logger->info('Upsell removed from order successfully', [
                'order_id' => $orderId,
                'upsell_id' => $upsellId,
                'product_id' => $upsell['product_id']
            ]);

            return $response->getStatusCode() === 204;
        });
    }

    /**
     * Obtém upsells do pedido
     */
    public function getOrderUpsells(string $orderId): array
    {
        return $this->executeWithMetrics('get_order_upsells', function () use ($orderId) {
            $response = $this->makeHttpRequest('GET', "/orders/{$orderId}/upsells");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém upsell específico
     */
    public function getUpsellById(string $orderId, string $upsellId): ?array
    {
        try {
            $response = $this->makeHttpRequest('GET', "/orders/{$orderId}/upsells/{$upsellId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Atualiza upsell do pedido
     */
    public function updateUpsell(string $orderId, string $upsellId, array $data): array
    {
        return $this->executeWithMetrics('update_order_upsell', function () use ($orderId, $upsellId, $data) {
            $this->validateUpsellUpdateData($data);

            // Verificar se upsell existe
            $currentUpsell = $this->getUpsellById($orderId, $upsellId);
            if (!$currentUpsell) {
                throw new ValidationException("Upsell not found: {$upsellId}");
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->makeHttpRequest('PUT', "/orders/{$orderId}/upsells/{$upsellId}", $data);
            $upsell = ResponseHelper::getData($response);

            // Invalidar cache do pedido
            $this->invalidateOrderCache($orderId);

            // Dispatch evento
            $this->dispatch('order.upsell_updated', [
                'order_id' => $orderId,
                'upsell_id' => $upsellId,
                'updated_fields' => array_keys($data)
            ]);

            return $upsell;
        });
    }

    /**
     * Obtém upsells disponíveis para o pedido
     */
    public function getAvailableUpsells(string $orderId): array
    {
        return $this->executeWithMetrics('get_available_upsells', function () use ($orderId) {
            $response = $this->makeHttpRequest('GET', "/orders/{$orderId}/available-upsells");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém upsells recomendados baseado no pedido
     */
    public function getRecommendedUpsells(string $orderId, int $limit = 5): array
    {
        return $this->executeWithMetrics('get_recommended_upsells', function () use ($orderId, $limit) {
            $response = $this->makeHttpRequest('GET', "/orders/{$orderId}/recommended-upsells", [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Aplica múltiplos upsells ao pedido
     */
    public function addMultipleUpsells(string $orderId, array $upsells): array
    {
        return $this->executeWithMetrics('add_multiple_upsells', function () use ($orderId, $upsells) {
            if (empty($upsells)) {
                throw new ValidationException('Upsells array cannot be empty');
            }

            $results = [
                'added' => [],
                'failed' => []
            ];

            foreach ($upsells as $index => $upsellData) {
                try {
                    $upsell = $this->addUpsell($orderId, $upsellData);
                    $results['added'][] = $upsell;
                } catch (\Exception $e) {
                    $results['failed'][] = [
                        'index' => $index,
                        'data' => $upsellData,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $this->logger->info('Multiple upsells processed', [
                'order_id' => $orderId,
                'total_upsells' => count($upsells),
                'added_count' => count($results['added']),
                'failed_count' => count($results['failed'])
            ]);

            return $results;
        });
    }

    /**
     * Obtém estatísticas de upsells
     */
    public function getUpsellStatistics(array $filters = []): array
    {
        return $this->executeWithMetrics('get_upsell_statistics', function () use ($filters) {
            $response = $this->makeHttpRequest('GET', '/orders/upsell-statistics', [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém conversão de upsells por período
     */
    public function getUpsellConversion(array $dateRange = []): array
    {
        return $this->executeWithMetrics('get_upsell_conversion', function () use ($dateRange) {
            $response = $this->makeHttpRequest('GET', '/orders/upsell-conversion', [
                'query' => $dateRange
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém produtos mais vendidos como upsell
     */
    public function getTopUpsellProducts(int $limit = 10): array
    {
        return $this->executeWithMetrics('get_top_upsell_products', function () use ($limit) {
            $response = $this->makeHttpRequest('GET', '/orders/top-upsell-products', [
                'query' => ['limit' => $limit]
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Verifica se pedido já tem produto como upsell
     */
    public function hasUpsellProduct(string $orderId, string $productId): bool
    {
        $upsells = $this->getOrderUpsells($orderId);

        foreach ($upsells as $upsell) {
            if ($upsell['product_id'] === $productId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula valor total dos upsells do pedido
     */
    public function calculateUpsellTotal(string $orderId): float
    {
        $upsells = $this->getOrderUpsells($orderId);

        return array_sum(array_column($upsells, 'amount'));
    }

    /**
     * Obtém pedido atual via cache ou API
     */
    private function getCurrentOrder(string $orderId): ?array
    {
        $cacheKey = $this->getCacheKey("order:{$orderId}");
        $order = $this->cache->get($cacheKey);

        if ($order === null) {
            try {
                $response = $this->makeHttpRequest('GET', "/orders/{$orderId}");
                $order = ResponseHelper::getData($response);

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
     * Verifica se pode adicionar upsell ao pedido
     */
    private function canAddUpsell(array $order): bool
    {
        $allowedStatuses = ['pending', 'processing'];
        return in_array($order['status'], $allowedStatuses);
    }

    /**
     * Verifica se pode remover upsell do pedido
     */
    private function canRemoveUpsell(array $order): bool
    {
        $allowedStatuses = ['pending', 'processing'];
        return in_array($order['status'], $allowedStatuses);
    }

    /**
     * Valida dados do upsell
     */
    private function validateUpsellData(array $data): void
    {
        $required = ['product_id', 'amount'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for upsell");
            }
        }

        if (!is_numeric($data['amount']) || $data['amount'] <= 0) {
            throw new ValidationException('Upsell amount must be a positive number');
        }

        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 1)) {
            throw new ValidationException('Upsell quantity must be a positive integer');
        }

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Valida dados de atualização do upsell
     */
    private function validateUpsellUpdateData(array $data): void
    {
        if (isset($data['amount']) && (!is_numeric($data['amount']) || $data['amount'] <= 0)) {
            throw new ValidationException('Upsell amount must be a positive number');
        }

        if (isset($data['quantity']) && (!is_numeric($data['quantity']) || $data['quantity'] < 1)) {
            throw new ValidationException('Upsell quantity must be a positive integer');
        }

        if (isset($data['discount_percentage']) && (!is_numeric($data['discount_percentage']) || $data['discount_percentage'] < 0 || $data['discount_percentage'] > 100)) {
            throw new ValidationException('Discount percentage must be between 0 and 100');
        }
    }

    /**
     * Calcula preço do upsell com descontos
     */
    private function calculateUpsellPrice(array $upsellData, array $order): array
    {
        $baseAmount = $upsellData['amount'];
        $quantity = $upsellData['quantity'] ?? 1;
        $discountPercentage = $upsellData['discount_percentage'] ?? 0;

        // Calcular preço unitário
        $unitPrice = $baseAmount;

        // Aplicar desconto se houver
        if ($discountPercentage > 0) {
            $discountAmount = ($baseAmount * $discountPercentage) / 100;
            $unitPrice = $baseAmount - $discountAmount;
            $upsellData['discount_amount'] = $discountAmount * $quantity;
        }

        // Calcular total
        $totalAmount = $unitPrice * $quantity;

        $upsellData['unit_price'] = $unitPrice;
        $upsellData['total_amount'] = $totalAmount;
        $upsellData['quantity'] = $quantity;

        return $upsellData;
    }

    /**
     * Gera metadados do upsell
     */
    private function generateUpsellMetadata(array $order, array $upsellData): array
    {
        return [
            'order_number' => $order['order_number'],
            'customer_id' => $order['customer_id'],
            'original_total' => $order['total_amount'],
            'currency' => $order['currency'],
            'applied_at' => date('Y-m-d H:i:s'),
            'source' => $upsellData['source'] ?? 'api',
            'applied_by' => $upsellData['applied_by'] ?? 'system'
        ];
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
