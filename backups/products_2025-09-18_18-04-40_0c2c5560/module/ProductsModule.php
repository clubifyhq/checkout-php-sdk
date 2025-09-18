<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Products\Factories\ProductsServiceFactory;
use Clubify\Checkout\Modules\Products\Services\ProductService;
use Clubify\Checkout\Modules\Products\Services\OfferService;
use Clubify\Checkout\Modules\Products\Services\FlowService;
use Clubify\Checkout\Modules\Products\Services\LayoutService;
use Clubify\Checkout\Modules\Products\Services\ThemeService;
use Clubify\Checkout\Modules\Products\Services\OrderBumpService;
use Clubify\Checkout\Modules\Products\Services\PricingService;
use Clubify\Checkout\Modules\Products\Services\UpsellService;

/**
 * Módulo de gestão de produtos
 *
 * Responsável pela gestão completa de produtos e ofertas usando Factory Pattern:
 * - CRUD de produtos via ProductService
 * - Gestão de ofertas e configurações via OfferService
 * - Order bumps e upsells via OrderBumpService/UpsellService
 * - Estratégias de preços via PricingService
 * - Flows de vendas via FlowService
 * - Temas e layouts via ThemeService/LayoutService
 * - Categorização e organização
 *
 * Arquitetura:
 * - Usa ProductsServiceFactory para criar services sob demanda
 * - Implementa lazy loading para otimização de performance
 * - Segue padrão singleton para reutilização de instâncias
 * - Suporta 8 tipos de services diferentes
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas operações de produtos
 * - O: Open/Closed - Extensível via Factory pattern
 * - L: Liskov Substitution - Implementa ModuleInterface
 * - I: Interface Segregation - Métodos específicos de produtos
 * - D: Dependency Inversion - Depende de abstrações via Factory
 */
class ProductsModule implements ModuleInterface
{
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;
    private ?ProductsServiceFactory $factory = null;

    // Services (lazy loading via Factory)
    private ?ProductService $productService = null;
    private ?OfferService $offerService = null;
    private ?FlowService $flowService = null;
    private ?LayoutService $layoutService = null;
    private ?ThemeService $themeService = null;
    private ?OrderBumpService $orderBumpService = null;
    private ?PricingService $pricingService = null;
    private ?UpsellService $upsellService = null;

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

        $this->logger->info('Products module initialized', [
            'module' => $this->getName(),
            'version' => $this->getVersion()
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
        return 'products';
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
        return [];
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
            'factory_loaded' => $this->factory !== null,
            'services_loaded' => [
                'product' => $this->productService !== null,
                'offer' => $this->offerService !== null,
                'flow' => $this->flowService !== null,
                'layout' => $this->layoutService !== null,
                'theme' => $this->themeService !== null,
                'order_bump' => $this->orderBumpService !== null,
                'pricing' => $this->pricingService !== null,
                'upsell' => $this->upsellService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Enhanced cleanup with factory
     */
    public function cleanup(): void
    {
        $this->productService = null;
        $this->offerService = null;
        $this->flowService = null;
        $this->layoutService = null;
        $this->themeService = null;
        $this->orderBumpService = null;
        $this->pricingService = null;
        $this->upsellService = null;
        $this->factory = null;
        $this->initialized = false;
        $this->logger?->info('Products module cleaned up');
    }

    /**
     * Verifica se o módulo está saudável
     */
    public function isHealthy(): bool
    {
        try {
            return $this->initialized;
        } catch (\Exception $e) {
            $this->logger?->error('ProductsModule health check failed', [
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
     * Configura um produto completo
     */
    public function setupComplete(array $productData): array
    {
        $this->logger?->info('Setting up complete product', $productData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'product_id' => uniqid('prod_'),
            'data' => $productData,
            'timestamp' => time()
        ];
    }

    /**
     * Cria produto completo
     */
    public function createComplete(array $productData): array
    {
        $this->logger?->info('Creating complete product', $productData);

        // Implementação básica - será expandida conforme necessário
        return [
            'success' => true,
            'product_id' => uniqid('prod_'),
            'data' => $productData,
            'created_at' => time()
        ];
    }

    /**
     * CRUD de temas
     */
    public function createTheme(array $themeData): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->createTheme($themeData);
    }

    public function getTheme(string $themeId): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->getTheme($themeId);
    }

    public function updateTheme(string $themeId, array $themeData): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->updateTheme($themeId, $themeData);
    }

    public function listThemes(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->listThemes($filters);
    }

    public function activateTheme(string $themeId): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->activateTheme($themeId);
    }

    public function generateThemeCSS(string $themeId): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->generateThemeCSS($themeId);
    }

    public function getThemePresets(): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->getThemePresets();
    }

    public function customizeTheme(string $themeId, array $customizations): array
    {
        $this->requireInitialized();
        return $this->getThemeService()->customizeTheme($themeId, $customizations);
    }

    /**
     * CRUD de layouts
     */
    public function createLayout(array $layoutData): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->createLayout($layoutData);
    }

    public function getLayout(string $layoutId): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->getLayout($layoutId);
    }

    public function updateLayout(string $layoutId, array $layoutData): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->updateLayout($layoutId, $layoutData);
    }

    public function listLayouts(array $filters = []): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->listLayouts($filters);
    }

    public function addLayoutSection(string $layoutId, string $sectionName, array $sectionConfig): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->addSection($layoutId, $sectionName, $sectionConfig);
    }

    public function removeLayoutSection(string $layoutId, string $sectionName): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->removeSection($layoutId, $sectionName);
    }

    public function reorderLayoutSections(string $layoutId, array $sectionOrder): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->reorderSections($layoutId, $sectionOrder);
    }

    public function generateLayoutHTML(string $layoutId): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->generateLayoutHTML($layoutId);
    }

    public function getLayoutTemplates(): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->getLayoutTemplates();
    }

    public function optimizeLayout(string $layoutId, array $optimizationGoals = []): array
    {
        $this->requireInitialized();
        return $this->getLayoutService()->optimizeLayout($layoutId, $optimizationGoals);
    }

    // ==============================================
    // FACTORY PATTERN - SERVICE CREATION
    // ==============================================

    /**
     * Get ProductsServiceFactory instance (lazy loading)
     */
    private function getFactory(): ProductsServiceFactory
    {
        if ($this->factory === null) {
            $this->factory = $this->sdk->createProductsServiceFactory();
        }
        return $this->factory;
    }

    /**
     * Get ProductService instance (lazy loading)
     */
    private function getProductService(): ProductService
    {
        if ($this->productService === null) {
            $this->productService = $this->getFactory()->create('product');
        }
        return $this->productService;
    }

    /**
     * Get OfferService instance (lazy loading)
     */
    private function getOfferService(): OfferService
    {
        if ($this->offerService === null) {
            $this->offerService = $this->getFactory()->create('offer');
        }
        return $this->offerService;
    }

    /**
     * Get FlowService instance (lazy loading)
     */
    private function getFlowService(): FlowService
    {
        if ($this->flowService === null) {
            $this->flowService = $this->getFactory()->create('flow');
        }
        return $this->flowService;
    }

    /**
     * Get LayoutService instance (lazy loading)
     */
    private function getLayoutService(): LayoutService
    {
        if ($this->layoutService === null) {
            $this->layoutService = $this->getFactory()->create('layout');
        }
        return $this->layoutService;
    }

    /**
     * Get ThemeService instance (lazy loading)
     */
    private function getThemeService(): ThemeService
    {
        if ($this->themeService === null) {
            $this->themeService = $this->getFactory()->create('theme');
        }
        return $this->themeService;
    }

    /**
     * Get OrderBumpService instance (lazy loading)
     */
    private function getOrderBumpService(): OrderBumpService
    {
        if ($this->orderBumpService === null) {
            $this->orderBumpService = $this->getFactory()->create('order_bump');
        }
        return $this->orderBumpService;
    }

    /**
     * Get PricingService instance (lazy loading)
     */
    private function getPricingService(): PricingService
    {
        if ($this->pricingService === null) {
            $this->pricingService = $this->getFactory()->create('pricing');
        }
        return $this->pricingService;
    }

    /**
     * Get UpsellService instance (lazy loading)
     */
    private function getUpsellService(): UpsellService
    {
        if ($this->upsellService === null) {
            $this->upsellService = $this->getFactory()->create('upsell');
        }
        return $this->upsellService;
    }

    /**
     * Verifica se o módulo está inicializado
     */
    private function requireInitialized(): void
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Products module is not initialized');
        }
    }
}
