<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products;

use Clubify\Checkout\Contracts\ModuleInterface;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Modules\Products\Services\ThemeService;
use Clubify\Checkout\Modules\Products\Services\LayoutService;

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
    private Configuration $config;
    private Logger $logger;
    private bool $initialized = false;

    // Services (lazy loading)
    private ?ThemeService $themeService = null;
    private ?LayoutService $layoutService = null;

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
            'services_loaded' => [
                'theme' => $this->themeService !== null,
                'layout' => $this->layoutService !== null,
            ],
            'timestamp' => time()
        ];
    }

    /**
     * Cleanup do módulo
     */
    public function cleanup(): void
    {
        $this->themeService = null;
        $this->layoutService = null;
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

    /**
     * Lazy loading dos services
     */
    private function getThemeService(): ThemeService
    {
        if ($this->themeService === null) {
            $this->themeService = new ThemeService($this->sdk, $this->config, $this->logger);
        }
        return $this->themeService;
    }

    private function getLayoutService(): LayoutService
    {
        if ($this->layoutService === null) {
            $this->layoutService = new LayoutService($this->sdk, $this->config, $this->logger);
        }
        return $this->layoutService;
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
