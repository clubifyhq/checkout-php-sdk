<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Orders;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Orders\Services\OrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderStatusService;
use Clubify\Checkout\Modules\Orders\Services\UpsellOrderService;
use Clubify\Checkout\Modules\Orders\Services\OrderAnalyticsService;

/**
 * Módulo de gestão de pedidos
 *
 * Responsável pela gestão completa de pedidos:
 * - CRUD de pedidos
 * - Gestão de status de pedidos
 * - Processamento de upsells em pedidos
 * - Analytics e estatísticas
 * - Histórico de pedidos
 * - Cancelamento de pedidos
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de pedidos
 * - O: Open/Closed - Extensível via novos tipos de pedido
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de pedidos
 * - D: Dependency Inversion - Depende de abstrações
 */
class OrdersModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Services (lazy loading)
    private ?OrderService $orderService = null;
    private ?OrderStatusService $orderStatusService = null;
    private ?UpsellOrderService $upsellOrderService = null;
    private ?OrderAnalyticsService $orderAnalyticsService = null;

    public function __construct(
        private ClubifyCheckoutSDK $sdk
    ) {
    }

    /**
     * Inicializa o módulo com configurações
     */
    public function initialize(Configuration $config, Logger $logger): void
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->initialized = true;

        $this->logger->info('Orders module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'tenant_id' => $this->config->getTenantId()
        ]);
    }

    /**
     * Verifica se o módulo está inicializado
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o nome do módulo
     */
    public function getName(): string
    {
        return 'orders';
    }

    /**
     * Obtém a versão do módulo
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Obtém as dependências do módulo
     */
    public function getDependencies(): array
    {
        return ['customers', 'products', 'payments'];
    }

    /**
     * Verifica se o módulo está disponível
     */
    public function isAvailable(): bool
    {
        return $this->initialized;
    }

    /**
     * Obtém o status do módulo
     */
    public function getStatus(): array
    {
        return [
            'name' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'available' => $this->isAvailable(),
            'services_loaded' => [
                'order' => $this->orderService !== null,
                'order_status' => $this->orderStatusService !== null,
                'upsell_order' => $this->upsellOrderService !== null,
                'order_analytics' => $this->orderAnalyticsService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->orderService = null;
        $this->orderStatusService = null;
        $this->upsellOrderService = null;
        $this->orderAnalyticsService = null;
        $this->initialized = false;
        $this->logger?->info('Orders module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('OrdersModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'module' => $this->getName(),
            'version' => $this->getVersion(),
            'initialized' => $this->initialized,
            'healthy' => $this->isHealthy(),
            'timestamp' => time()
        ];
    }

    /**
     * CRUD de pedidos
     */
    public function createOrder(array $orderData): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->create($orderData);
    }

    public function getOrder(string $orderId): ?array
    {
        $this->requireInitialized();
        return $this->getOrderService()->get($orderId);
    }

    public function listOrders(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->list($filters, $page, $limit);
    }

    public function updateOrder(string $orderId, array $data): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->update($orderId, $data);
    }

    public function cancelOrder(string $orderId, array $cancelData = []): bool
    {
        $this->requireInitialized();
        return $this->getOrderService()->cancel($orderId, $cancelData);
    }

    /**
     * Gestão de status de pedidos
     */
    public function updateOrderStatus(string $orderId, string $status, array $metadata = []): bool
    {
        $this->requireInitialized();
        return $this->getOrderStatusService()->updateStatus($orderId, $status, $metadata);
    }

    public function getOrderStatusHistory(string $orderId): array
    {
        $this->requireInitialized();
        return $this->getOrderStatusService()->getStatusHistory($orderId);
    }

    public function getOrdersByStatus(string $status, array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderStatusService()->getOrdersByStatus($status, $filters);
    }

    /**
     * Processamento de upsells
     */
    public function addUpsellToOrder(string $orderId, array $upsellData): array
    {
        $this->requireInitialized();
        return $this->getUpsellOrderService()->addUpsell($orderId, $upsellData);
    }

    public function removeUpsellFromOrder(string $orderId, string $upsellId): bool
    {
        $this->requireInitialized();
        return $this->getUpsellOrderService()->removeUpsell($orderId, $upsellId);
    }

    public function getOrderUpsells(string $orderId): array
    {
        $this->requireInitialized();
        return $this->getUpsellOrderService()->getOrderUpsells($orderId);
    }

    /**
     * Analytics e estatísticas
     */
    public function getOrderStatistics(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderAnalyticsService()->getStatistics($filters);
    }

    public function getRevenueStats(array $dateRange = []): array
    {
        $this->requireInitialized();
        return $this->getOrderAnalyticsService()->getRevenueStats($dateRange);
    }

    public function getTopCustomers(int $limit = 10): array
    {
        $this->requireInitialized();
        return $this->getOrderAnalyticsService()->getTopCustomers($limit);
    }

    public function getTopProducts(int $limit = 10): array
    {
        $this->requireInitialized();
        return $this->getOrderAnalyticsService()->getTopProducts($limit);
    }

    public function getConversionMetrics(): array
    {
        $this->requireInitialized();
        return $this->getOrderAnalyticsService()->getConversionMetrics();
    }

    /**
     * Métodos de busca avançada
     */
    public function searchOrders(string $query, array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->search($query, $filters);
    }

    public function getOrdersByCustomer(string $customerId, array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->getByCustomer($customerId, $filters);
    }

    public function getOrdersByProduct(string $productId, array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->getByProduct($productId, $filters);
    }

    public function getOrdersByDateRange(string $startDate, string $endDate, array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getOrderService()->getByDateRange($startDate, $endDate, $filters);
    }

    /**
     * Lazy loading dos services
     */
    private function getOrderService(): OrderService
    {
        if ($this->orderService === null) {
            $this->orderService = new OrderService($this->sdk, $this->config, $this->logger);
        }
        return $this->orderService;
    }

    private function getOrderStatusService(): OrderStatusService
    {
        if ($this->orderStatusService === null) {
            $this->orderStatusService = new OrderStatusService($this->sdk, $this->config, $this->logger);
        }
        return $this->orderStatusService;
    }

    private function getUpsellOrderService(): UpsellOrderService
    {
        if ($this->upsellOrderService === null) {
            $this->upsellOrderService = new UpsellOrderService($this->sdk, $this->config, $this->logger);
        }
        return $this->upsellOrderService;
    }

    private function getOrderAnalyticsService(): OrderAnalyticsService
    {
        if ($this->orderAnalyticsService === null) {
            $this->orderAnalyticsService = new OrderAnalyticsService($this->sdk, $this->config, $this->logger);
        }
        return $this->orderAnalyticsService;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Orders module is not initialized');
        }
    }
}
