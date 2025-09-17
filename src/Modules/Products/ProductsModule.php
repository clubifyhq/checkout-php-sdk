<?php

declare(strict_types=1);

namespace ClubifyCheckout\Modules\Products;

use ClubifyCheckout\Contracts\ModuleInterface;
use ClubifyCheckout\Http\HttpClientInterface;
use ClubifyCheckout\Cache\CacheInterface;
use ClubifyCheckout\Modules\Products\Repositories\ProductRepositoryInterface;
use ClubifyCheckout\Modules\Products\Repositories\ProductRepository;
use ClubifyCheckout\Modules\Products\Services\ProductService;
use ClubifyCheckout\Modules\Products\Services\OfferService;
use ClubifyCheckout\Modules\Products\Services\OrderBumpService;
use ClubifyCheckout\Modules\Products\Services\UpsellService;
use ClubifyCheckout\Modules\Products\Services\PricingService;
use ClubifyCheckout\Modules\Products\Services\FlowService;
use Psr\Log\LoggerInterface;

/**
 * Módulo de gestão de produtos
 *
 * Responsável pela gestão completa de produtos e ofertas:
 * - CRUD de produtos
 * - Gestão de ofertas e configurações
 * - Order bumps e upsells
 * - Estratégias de preços
 * - Flows de vendas
 * - Categorização e organização
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de produtos
 * - O: Open/Closed - Extensível via novos tipos de produto
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de produtos
 * - D: Dependency Inversion - Depende de abstrações
 */
class ProductsModule implements ModuleInterface
{
    private ?ProductRepositoryInterface $productRepository = null;
    private ?ProductService $productService = null;
    private ?OfferService $offerService = null;
    private ?OrderBumpService $orderBumpService = null;
    private ?UpsellService $upsellService = null;
    private ?PricingService $pricingService = null;
    private ?FlowService $flowService = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        private array $config = []
    ) {}

    /**
     * Inicializa o módulo
     */
    public function initialize(): void
    {
        $this->logger->info('Initializing ProductsModule', [
            'module' => 'products',
            'config' => array_keys($this->config)
        ]);
    }

    /**
     * Obtém o repositório de produtos (lazy loading)
     */
    public function getProductRepository(): ProductRepositoryInterface
    {
        if ($this->productRepository === null) {
            $this->productRepository = new ProductRepository(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->productRepository;
    }

    /**
     * Obtém o serviço de produtos (lazy loading)
     */
    public function getProductService(): ProductService
    {
        if ($this->productService === null) {
            $this->productService = new ProductService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->productService;
    }

    /**
     * Obtém o serviço de ofertas (lazy loading)
     */
    public function getOfferService(): OfferService
    {
        if ($this->offerService === null) {
            $this->offerService = new OfferService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->offerService;
    }

    /**
     * Obtém o serviço de order bumps (lazy loading)
     */
    public function getOrderBumpService(): OrderBumpService
    {
        if ($this->orderBumpService === null) {
            $this->orderBumpService = new OrderBumpService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->orderBumpService;
    }

    /**
     * Obtém o serviço de upsells (lazy loading)
     */
    public function getUpsellService(): UpsellService
    {
        if ($this->upsellService === null) {
            $this->upsellService = new UpsellService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->upsellService;
    }

    /**
     * Obtém o serviço de preços (lazy loading)
     */
    public function getPricingService(): PricingService
    {
        if ($this->pricingService === null) {
            $this->pricingService = new PricingService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->pricingService;
    }

    /**
     * Obtém o serviço de flows (lazy loading)
     */
    public function getFlowService(): FlowService
    {
        if ($this->flowService === null) {
            $this->flowService = new FlowService(
                $this->httpClient,
                $this->cache,
                $this->logger
            );
        }

        return $this->flowService;
    }

    /**
     * Configura um produto completo com ofertas
     */
    public function setupProduct(array $productData, array $offerData = []): array
    {
        return $this->executeWithTransaction('setup_product', function () use ($productData, $offerData) {
            // 1. Criar produto
            $product = $this->getProductService()->create($productData);

            $this->logger->info('Product created successfully', [
                'product_id' => $product['id'],
                'name' => $product['name']
            ]);

            // 2. Criar oferta se fornecida
            if (!empty($offerData)) {
                $offerData['product_id'] = $product['id'];
                $offer = $this->getOfferService()->create($offerData);

                $this->logger->info('Offer created for product', [
                    'offer_id' => $offer['id'],
                    'product_id' => $product['id']
                ]);

                $product['offer'] = $offer;
            }

            // 3. Configurar pricing se especificado
            if (isset($productData['pricing_strategy'])) {
                $pricing = $this->getPricingService()->createStrategy(
                    $product['id'],
                    $productData['pricing_strategy']
                );
                $product['pricing'] = $pricing;
            }

            return $product;
        });
    }

    /**
     * Configura flow de vendas completo
     */
    public function setupSalesFlow(array $flowData): array
    {
        return $this->executeWithTransaction('setup_sales_flow', function () use ($flowData) {
            // 1. Criar flow principal
            $flow = $this->getFlowService()->create($flowData);

            // 2. Configurar order bumps se especificados
            if (isset($flowData['order_bumps'])) {
                foreach ($flowData['order_bumps'] as $orderBumpData) {
                    $orderBumpData['flow_id'] = $flow['id'];
                    $this->getOrderBumpService()->create($orderBumpData);
                }
            }

            // 3. Configurar upsells se especificados
            if (isset($flowData['upsells'])) {
                foreach ($flowData['upsells'] as $upsellData) {
                    $upsellData['flow_id'] = $flow['id'];
                    $this->getUpsellService()->create($upsellData);
                }
            }

            $this->logger->info('Sales flow configured successfully', [
                'flow_id' => $flow['id'],
                'order_bumps' => count($flowData['order_bumps'] ?? []),
                'upsells' => count($flowData['upsells'] ?? [])
            ]);

            return $flow;
        });
    }

    /**
     * Duplica produto com todas suas configurações
     */
    public function duplicateProduct(string $productId, array $overrideData = []): array
    {
        return $this->executeWithTransaction('duplicate_product', function () use ($productId, $overrideData) {
            // 1. Obter produto original
            $originalProduct = $this->getProductService()->get($productId);
            if (!$originalProduct) {
                throw new \InvalidArgumentException("Product not found: {$productId}");
            }

            // 2. Preparar dados do novo produto
            $newProductData = array_merge($originalProduct, $overrideData);
            unset($newProductData['id'], $newProductData['created_at'], $newProductData['updated_at']);
            $newProductData['name'] = ($overrideData['name'] ?? $originalProduct['name']) . ' (Copy)';

            // 3. Criar novo produto
            $newProduct = $this->getProductService()->create($newProductData);

            // 4. Duplicar ofertas associadas
            $offers = $this->getOfferService()->getByProduct($productId);
            foreach ($offers as $offer) {
                $newOfferData = $offer;
                unset($newOfferData['id'], $newOfferData['created_at'], $newOfferData['updated_at']);
                $newOfferData['product_id'] = $newProduct['id'];
                $this->getOfferService()->create($newOfferData);
            }

            $this->logger->info('Product duplicated successfully', [
                'original_id' => $productId,
                'new_id' => $newProduct['id'],
                'offers_duplicated' => count($offers)
            ]);

            return $newProduct;
        });
    }

    /**
     * Obtém estatísticas do módulo
     */
    public function getStats(): array
    {
        return [
            'products_count' => $this->getProductService()->count(),
            'offers_count' => $this->getOfferService()->count(),
            'flows_count' => $this->getFlowService()->count(),
            'active_order_bumps' => $this->getOrderBumpService()->countActive(),
            'active_upsells' => $this->getUpsellService()->countActive(),
            'pricing_strategies' => $this->getPricingService()->countStrategies()
        ];
    }

    /**
     * Executa operação com transação simulada
     */
    private function executeWithTransaction(string $operation, callable $callback): mixed
    {
        $startTime = microtime(true);

        try {
            $this->logger->info("Starting {$operation} transaction");

            $result = $callback();

            $duration = microtime(true) - $startTime;
            $this->logger->info("Transaction {$operation} completed successfully", [
                'duration_ms' => round($duration * 1000, 2)
            ]);

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->logger->error("Transaction {$operation} failed", [
                'error' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2)
            ]);
            throw $e;
        }
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            // Verificar conectividade básica
            $this->getProductService()->count();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('ProductsModule health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Limpa recursos do módulo
     */
    public function cleanup(): void
    {
        $this->productRepository = null;
        $this->productService = null;
        $this->offerService = null;
        $this->orderBumpService = null;
        $this->upsellService = null;
        $this->pricingService = null;
        $this->flowService = null;

        $this->logger->info('ProductsModule cleanup completed');
    }
}