<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

use Clubify\Checkout\Core\Http\ResponseHelper;
use Clubify\Checkout\Services\BaseService;
use Clubify\Checkout\Contracts\ServiceInterface;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

/**
 * Serviço de gestão de temas e layouts
 *
 * Responsável pela gestão completa de temas e layouts para ofertas:
 * - CRUD de temas customizados
 * - Biblioteca de temas pré-definidos
 * - Configuração de cores, fontes e estilos
 * - Layouts responsivos
 * - Preview de temas
 * - Exportação/importação de temas
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Gerencia apenas temas e layouts
 * - O: Open/Closed - Extensível via tipos de tema
 * - L: Liskov Substitution - Implementa BaseService
 * - I: Interface Segregation - Métodos específicos de tema
 * - D: Dependency Inversion - Depende de abstrações
 */
class ThemeService extends BaseService implements ServiceInterface
{
    /**
     * Obtém o nome do serviço
     */
    protected function getServiceName(): string
    {
        return 'theme';
    }

    /**
     * Obtém a versão do serviço
     */
    protected function getServiceVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Lista temas disponíveis
     */
    public function list(array $filters = []): array
    {
        return $this->executeWithMetrics('list_themes', function () use ($filters) {
            $response = $this->httpClient->get('/themes', [
                'query' => $filters
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Obtém tema por ID
     */
    public function get(string $themeId): ?array
    {
        return $this->getCachedOrExecute(
            "theme:{$themeId}",
            fn () => $this->fetchThemeById($themeId),
            3600
        );
    }

    /**
     * Cria tema customizado
     */
    public function create(array $themeData): array
    {
        return $this->executeWithMetrics('create_theme', function () use ($themeData) {
            $this->validateThemeData($themeData);

            // Preparar dados do tema
            $data = array_merge($themeData, [
                'type' => 'custom',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'configuration' => $this->buildThemeConfiguration($themeData)
            ]);

            // Criar tema via API
            $response = $this->httpClient->post('/themes', $data);
            $theme = ResponseHelper::getData($response);

            // Cache do tema
            $this->cache->set($this->getCacheKey("theme:{$theme['id']}"), $theme, 3600);

            // Dispatch evento
            $this->dispatch('theme.created', [
                'theme_id' => $theme['id'],
                'name' => $theme['name'],
                'type' => $theme['type']
            ]);

            $this->logger->info('Theme created successfully', [
                'theme_id' => $theme['id'],
                'name' => $theme['name']
            ]);

            return $theme;
        });
    }

    /**
     * Atualiza tema
     */
    public function update(string $themeId, array $data): array
    {
        return $this->executeWithMetrics('update_theme', function () use ($themeId, $data) {
            $this->validateThemeUpdateData($data);

            // Verificar se tema existe
            $currentTheme = $this->get($themeId);
            if (!$currentTheme) {
                throw new ValidationException("Theme not found: {$themeId}");
            }

            $data['updated_at'] = date('Y-m-d H:i:s');

            $response = $this->httpClient->put("/themes/{$themeId}", $data);
            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.updated', [
                'theme_id' => $themeId,
                'updated_fields' => array_keys($data)
            ]);

            return $theme;
        });
    }

    /**
     * Aplica tema a uma oferta
     */
    public function applyToOffer(string $offerId, array $themeData): array
    {
        return $this->executeWithMetrics('apply_theme_to_offer', function () use ($offerId, $themeData) {
            $this->validateOfferThemeData($themeData);

            $response = $this->httpClient->put("/offers/{$offerId}/config/theme", [
                'theme' => $themeData
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('theme.applied_to_offer', [
                'offer_id' => $offerId,
                'theme_id' => $themeData['theme_id'] ?? null,
                'theme_name' => $themeData['name'] ?? 'custom'
            ]);

            return $result;
        });
    }

    /**
     * Configura layout de uma oferta
     */
    public function configureLayout(string $offerId, array $layoutData): array
    {
        return $this->executeWithMetrics('configure_offer_layout', function () use ($offerId, $layoutData) {
            $this->validateLayoutData($layoutData);

            $response = $this->httpClient->put("/offers/{$offerId}/config/layout", [
                'layout' => $layoutData
            ]);

            $result = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('layout.configured', [
                'offer_id' => $offerId,
                'layout_type' => $layoutData['type'] ?? 'custom'
            ]);

            return $result;
        });
    }

    /**
     * Obtém preview do tema
     */
    public function getPreview(string $themeId, array $options = []): array
    {
        return $this->executeWithMetrics('get_theme_preview', function () use ($themeId, $options) {
            $response = $this->httpClient->get("/themes/{$themeId}/preview", [
                'query' => $options
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Duplica tema
     */
    public function duplicate(string $themeId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_theme', function () use ($themeId, $overrideData) {
            $response = $this->httpClient->post("/themes/{$themeId}/duplicate", $overrideData);
            $theme = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('theme.duplicated', [
                'original_id' => $themeId,
                'new_id' => $theme['id']
            ]);

            return $theme;
        });
    }

    /**
     * Exporta tema
     */
    public function export(string $themeId): array
    {
        return $this->executeWithMetrics('export_theme', function () use ($themeId) {
            $response = $this->httpClient->get("/themes/{$themeId}/export");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Importa tema
     */
    public function import(array $themeData): array
    {
        return $this->executeWithMetrics('import_theme', function () use ($themeData) {
            $this->validateImportData($themeData);

            $response = $this->httpClient->post('/themes/import', $themeData);
            $theme = ResponseHelper::getData($response);

            // Cache do tema
            $this->cache->set($this->getCacheKey("theme:{$theme['id']}"), $theme, 3600);

            // Dispatch evento
            $this->dispatch('theme.imported', [
                'theme_id' => $theme['id'],
                'name' => $theme['name']
            ]);

            return $theme;
        });
    }

    /**
     * Obtém temas pré-definidos
     */
    public function getPredefined(string $category = null): array
    {
        return $this->executeWithMetrics('get_predefined_themes', function () use ($category) {
            $query = $category ? ['category' => $category] : [];

            $response = $this->httpClient->get('/themes/predefined', [
                'query' => $query
            ]);
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Configura cores do tema
     */
    public function updateColors(string $themeId, array $colors): array
    {
        return $this->executeWithMetrics('update_theme_colors', function () use ($themeId, $colors) {
            $this->validateColors($colors);

            $response = $this->httpClient->put("/themes/{$themeId}/colors", [
                'colors' => $colors
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.colors_updated', [
                'theme_id' => $themeId,
                'colors_count' => count($colors)
            ]);

            return $theme;
        });
    }

    /**
     * Configura fontes do tema
     */
    public function updateFonts(string $themeId, array $fonts): array
    {
        return $this->executeWithMetrics('update_theme_fonts', function () use ($themeId, $fonts) {
            $this->validateFonts($fonts);

            $response = $this->httpClient->put("/themes/{$themeId}/fonts", [
                'fonts' => $fonts
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.fonts_updated', [
                'theme_id' => $themeId,
                'fonts_count' => count($fonts)
            ]);

            return $theme;
        });
    }

    /**
     * Exclui tema
     */
    public function delete(string $themeId): bool
    {
        return $this->executeWithMetrics('delete_theme', function () use ($themeId) {
            try {
                $response = $this->httpClient->delete("/themes/{$themeId}");

                // Invalidar cache
                $this->invalidateThemeCache($themeId);

                // Dispatch evento
                $this->dispatch('theme.deleted', [
                    'theme_id' => $themeId
                ]);

                return $response->getStatusCode() === 204;
            } catch (HttpException $e) {
                $this->logger->error('Failed to delete theme', [
                    'theme_id' => $themeId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Configura layout responsivo avançado
     */
    public function configureResponsiveLayout(string $themeId, array $responsiveConfig): array
    {
        return $this->executeWithMetrics('configure_responsive_layout', function () use ($themeId, $responsiveConfig) {
            $this->validateResponsiveConfig($responsiveConfig);

            $response = $this->httpClient->put("/themes/{$themeId}/responsive-layout", [
                'responsive_config' => $responsiveConfig
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.responsive_layout_configured', [
                'theme_id' => $themeId,
                'breakpoints_count' => count($responsiveConfig['breakpoints'] ?? [])
            ]);

            return $theme;
        });
    }

    /**
     * Configura grid system customizado
     */
    public function configureGridSystem(string $themeId, array $gridConfig): array
    {
        return $this->executeWithMetrics('configure_grid_system', function () use ($themeId, $gridConfig) {
            $this->validateGridConfig($gridConfig);

            $response = $this->httpClient->put("/themes/{$themeId}/grid-system", [
                'grid_config' => $gridConfig
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.grid_system_configured', [
                'theme_id' => $themeId,
                'columns' => $gridConfig['columns'] ?? 12,
                'gutters' => $gridConfig['gutters'] ?? 'default'
            ]);

            return $theme;
        });
    }

    /**
     * Configura componentes de layout
     */
    public function configureLayoutComponents(string $themeId, array $components): array
    {
        return $this->executeWithMetrics('configure_layout_components', function () use ($themeId, $components) {
            $this->validateLayoutComponents($components);

            $response = $this->httpClient->put("/themes/{$themeId}/layout-components", [
                'components' => $components
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.layout_components_configured', [
                'theme_id' => $themeId,
                'components_count' => count($components)
            ]);

            return $theme;
        });
    }

    /**
     * Configura espaçamentos e margens
     */
    public function configureSpacing(string $themeId, array $spacingConfig): array
    {
        return $this->executeWithMetrics('configure_theme_spacing', function () use ($themeId, $spacingConfig) {
            $this->validateSpacingConfig($spacingConfig);

            $response = $this->httpClient->put("/themes/{$themeId}/spacing", [
                'spacing_config' => $spacingConfig
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.spacing_configured', [
                'theme_id' => $themeId,
                'spacing_units' => array_keys($spacingConfig)
            ]);

            return $theme;
        });
    }

    /**
     * Configura tipografia avançada
     */
    public function configureAdvancedTypography(string $themeId, array $typographyConfig): array
    {
        return $this->executeWithMetrics('configure_advanced_typography', function () use ($themeId, $typographyConfig) {
            $this->validateAdvancedTypography($typographyConfig);

            $response = $this->httpClient->put("/themes/{$themeId}/advanced-typography", [
                'typography_config' => $typographyConfig
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.advanced_typography_configured', [
                'theme_id' => $themeId,
                'font_families_count' => count($typographyConfig['font_families'] ?? [])
            ]);

            return $theme;
        });
    }

    /**
     * Configura sistema de cores avançado
     */
    public function configureAdvancedColorSystem(string $themeId, array $colorSystem): array
    {
        return $this->executeWithMetrics('configure_advanced_color_system', function () use ($themeId, $colorSystem) {
            $this->validateAdvancedColorSystem($colorSystem);

            $response = $this->httpClient->put("/themes/{$themeId}/advanced-colors", [
                'color_system' => $colorSystem
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.advanced_color_system_configured', [
                'theme_id' => $themeId,
                'palettes_count' => count($colorSystem['palettes'] ?? [])
            ]);

            return $theme;
        });
    }

    /**
     * Configura animações e transições
     */
    public function configureAnimations(string $themeId, array $animationConfig): array
    {
        return $this->executeWithMetrics('configure_theme_animations', function () use ($themeId, $animationConfig) {
            $this->validateAnimationConfig($animationConfig);

            $response = $this->httpClient->put("/themes/{$themeId}/animations", [
                'animation_config' => $animationConfig
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.animations_configured', [
                'theme_id' => $themeId,
                'animations_count' => count($animationConfig['animations'] ?? [])
            ]);

            return $theme;
        });
    }

    /**
     * Configura CSS customizado
     */
    public function configureCustomCSS(string $themeId, string $customCSS, array $options = []): array
    {
        return $this->executeWithMetrics('configure_custom_css', function () use ($themeId, $customCSS, $options) {
            $this->validateCustomCSS($customCSS);

            $response = $this->httpClient->put("/themes/{$themeId}/custom-css", [
                'custom_css' => $customCSS,
                'options' => $options
            ]);

            $theme = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.custom_css_configured', [
                'theme_id' => $themeId,
                'css_size' => strlen($customCSS),
                'has_variables' => isset($options['variables']) && $options['variables']
            ]);

            return $theme;
        });
    }

    /**
     * Gera variações do tema
     */
    public function generateThemeVariations(string $themeId, array $variationRules): array
    {
        return $this->executeWithMetrics('generate_theme_variations', function () use ($themeId, $variationRules) {
            $this->validateVariationRules($variationRules);

            $response = $this->httpClient->post("/themes/{$themeId}/generate-variations", [
                'variation_rules' => $variationRules
            ]);

            $variations = ResponseHelper::getData($response);

            // Dispatch evento
            $this->dispatch('theme.variations_generated', [
                'theme_id' => $themeId,
                'variations_count' => count($variations['variations'] ?? [])
            ]);

            return $variations;
        });
    }

    /**
     * Otimiza tema para performance
     */
    public function optimizeForPerformance(string $themeId, array $optimizationOptions = []): array
    {
        return $this->executeWithMetrics('optimize_theme_performance', function () use ($themeId, $optimizationOptions) {
            $response = $this->httpClient->post("/themes/{$themeId}/optimize-performance", [
                'optimization_options' => $optimizationOptions
            ]);

            $result = ResponseHelper::getData($response);

            // Invalidar cache
            $this->invalidateThemeCache($themeId);

            // Dispatch evento
            $this->dispatch('theme.performance_optimized', [
                'theme_id' => $themeId,
                'optimizations_applied' => $result['optimizations_applied'] ?? []
            ]);

            return $result;
        });
    }

    /**
     * Valida tema em diferentes dispositivos
     */
    public function validateAcrossDevices(string $themeId, array $devices = []): array
    {
        return $this->executeWithMetrics('validate_theme_across_devices', function () use ($themeId, $devices) {
            $defaultDevices = ['desktop', 'tablet', 'mobile'];
            $testDevices = empty($devices) ? $defaultDevices : $devices;

            $response = $this->httpClient->post("/themes/{$themeId}/validate-devices", [
                'devices' => $testDevices
            ]);

            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Gera relatório de acessibilidade do tema
     */
    public function generateAccessibilityReport(string $themeId): array
    {
        return $this->executeWithMetrics('generate_accessibility_report', function () use ($themeId) {
            $response = $this->httpClient->get("/themes/{$themeId}/accessibility-report");
            return ResponseHelper::getData($response) ?? [];
        });
    }

    /**
     * Busca tema por ID via API
     */
    private function fetchThemeById(string $themeId): ?array
    {
        try {
            $response = $this->httpClient->get("/themes/{$themeId}");
            return ResponseHelper::getData($response);
        } catch (HttpException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    /**
     * Invalida cache do tema
     */
    private function invalidateThemeCache(string $themeId): void
    {
        $this->cache->delete($this->getCacheKey("theme:{$themeId}"));
    }

    /**
     * Valida dados do tema
     */
    private function validateThemeData(array $data): void
    {
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for theme creation");
            }
        }

        if (isset($data['colors'])) {
            $this->validateColors($data['colors']);
        }

        if (isset($data['fonts'])) {
            $this->validateFonts($data['fonts']);
        }
    }

    /**
     * Valida dados de atualização do tema
     */
    private function validateThemeUpdateData(array $data): void
    {
        if (isset($data['colors'])) {
            $this->validateColors($data['colors']);
        }

        if (isset($data['fonts'])) {
            $this->validateFonts($data['fonts']);
        }
    }

    /**
     * Valida dados de tema para oferta
     */
    private function validateOfferThemeData(array $data): void
    {
        if (isset($data['colors'])) {
            $this->validateColors($data['colors']);
        }

        if (isset($data['fonts'])) {
            $this->validateFonts($data['fonts']);
        }
    }

    /**
     * Valida dados de layout
     */
    private function validateLayoutData(array $data): void
    {
        $required = ['type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for layout configuration");
            }
        }

        $allowedTypes = ['single_column', 'two_column', 'multi_step', 'custom'];
        if (!in_array($data['type'], $allowedTypes)) {
            throw new ValidationException("Invalid layout type: {$data['type']}");
        }
    }

    /**
     * Valida cores
     */
    private function validateColors(array $colors): void
    {
        foreach ($colors as $name => $color) {
            if (!is_string($color) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
                throw new ValidationException("Invalid color format for '{$name}': {$color}");
            }
        }
    }

    /**
     * Valida fontes
     */
    private function validateFonts(array $fonts): void
    {
        foreach ($fonts as $element => $font) {
            if (!is_array($font)) {
                throw new ValidationException("Font configuration for '{$element}' must be an array");
            }

            if (!isset($font['family'])) {
                throw new ValidationException("Font family is required for '{$element}'");
            }
        }
    }

    /**
     * Valida dados de importação
     */
    private function validateImportData(array $data): void
    {
        $required = ['name', 'configuration'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new ValidationException("Field '{$field}' is required for theme import");
            }
        }

        if (!is_array($data['configuration'])) {
            throw new ValidationException('Theme configuration must be an array');
        }
    }

    /**
     * Constrói configuração do tema
     */
    private function buildThemeConfiguration(array $data): array
    {
        return [
            'colors' => $data['colors'] ?? [],
            'fonts' => $data['fonts'] ?? [],
            'layout' => $data['layout'] ?? [],
            'components' => $data['components'] ?? [],
            'responsive' => $data['responsive'] ?? true,
            'animations' => $data['animations'] ?? false,
            'custom_css' => $data['custom_css'] ?? ''
        ];
    }

    /**
     * Valida configuração responsiva
     */
    private function validateResponsiveConfig(array $config): void
    {
        if (!isset($config['breakpoints']) || !is_array($config['breakpoints'])) {
            throw new ValidationException('Responsive configuration must include breakpoints array');
        }

        $allowedBreakpoints = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];
        foreach ($config['breakpoints'] as $breakpoint => $settings) {
            if (!in_array($breakpoint, $allowedBreakpoints)) {
                throw new ValidationException("Invalid breakpoint: {$breakpoint}");
            }

            if (!is_array($settings)) {
                throw new ValidationException("Breakpoint settings must be an array for: {$breakpoint}");
            }
        }
    }

    /**
     * Valida configuração de grid
     */
    private function validateGridConfig(array $config): void
    {
        if (isset($config['columns']) && (!is_numeric($config['columns']) || $config['columns'] < 1 || $config['columns'] > 24)) {
            throw new ValidationException('Grid columns must be between 1 and 24');
        }

        if (isset($config['gutters']) && !in_array($config['gutters'], ['none', 'small', 'default', 'large', 'custom'])) {
            throw new ValidationException('Invalid gutter size');
        }
    }

    /**
     * Valida componentes de layout
     */
    private function validateLayoutComponents(array $components): void
    {
        $allowedComponents = [
            'header', 'footer', 'sidebar', 'content', 'navigation',
            'hero', 'features', 'testimonials', 'pricing', 'cta'
        ];

        foreach ($components as $componentName => $config) {
            if (!in_array($componentName, $allowedComponents)) {
                throw new ValidationException("Invalid layout component: {$componentName}");
            }

            if (!is_array($config)) {
                throw new ValidationException("Component configuration must be an array for: {$componentName}");
            }
        }
    }

    /**
     * Valida configuração de espaçamentos
     */
    private function validateSpacingConfig(array $config): void
    {
        $allowedUnits = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];

        foreach ($config as $unit => $value) {
            if (!in_array($unit, $allowedUnits)) {
                throw new ValidationException("Invalid spacing unit: {$unit}");
            }

            if (!is_string($value) && !is_numeric($value)) {
                throw new ValidationException("Spacing value must be string or numeric for: {$unit}");
            }
        }
    }

    /**
     * Valida tipografia avançada
     */
    private function validateAdvancedTypography(array $config): void
    {
        if (isset($config['font_families'])) {
            foreach ($config['font_families'] as $name => $family) {
                if (!is_array($family) || !isset($family['family'])) {
                    throw new ValidationException("Invalid font family configuration for: {$name}");
                }
            }
        }

        if (isset($config['font_sizes'])) {
            foreach ($config['font_sizes'] as $size => $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    throw new ValidationException("Font size must be string or numeric for: {$size}");
                }
            }
        }
    }

    /**
     * Valida sistema de cores avançado
     */
    private function validateAdvancedColorSystem(array $config): void
    {
        if (isset($config['palettes'])) {
            foreach ($config['palettes'] as $paletteName => $palette) {
                if (!is_array($palette)) {
                    throw new ValidationException("Color palette must be an array for: {$paletteName}");
                }

                foreach ($palette as $colorName => $colorValue) {
                    if (!is_string($colorValue) || !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $colorValue)) {
                        throw new ValidationException("Invalid color format for '{$paletteName}.{$colorName}': {$colorValue}");
                    }
                }
            }
        }
    }

    /**
     * Valida configuração de animações
     */
    private function validateAnimationConfig(array $config): void
    {
        if (isset($config['animations'])) {
            foreach ($config['animations'] as $animationName => $animation) {
                if (!is_array($animation)) {
                    throw new ValidationException("Animation configuration must be an array for: {$animationName}");
                }

                $allowedTypes = ['fade', 'slide', 'scale', 'rotate', 'bounce', 'pulse'];
                if (isset($animation['type']) && !in_array($animation['type'], $allowedTypes)) {
                    throw new ValidationException("Invalid animation type for '{$animationName}': {$animation['type']}");
                }

                if (isset($animation['duration']) && (!is_numeric($animation['duration']) || $animation['duration'] < 0)) {
                    throw new ValidationException("Animation duration must be a positive number for: {$animationName}");
                }
            }
        }
    }

    /**
     * Valida CSS customizado
     */
    private function validateCustomCSS(string $css): void
    {
        // Basic validation - check for potentially dangerous content
        $dangerousPatterns = [
            '/@import\s+url\s*\(\s*["\']?javascript:/i',
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/<script/i',
            '/eval\s*\(/i'
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $css)) {
                throw new ValidationException('Custom CSS contains potentially dangerous content');
            }
        }

        // Check CSS size limit (e.g., 100KB)
        if (strlen($css) > 102400) {
            throw new ValidationException('Custom CSS exceeds maximum size limit (100KB)');
        }
    }

    /**
     * Valida regras de variação
     */
    private function validateVariationRules(array $rules): void
    {
        $allowedVariationTypes = ['color_scheme', 'typography', 'layout', 'spacing', 'components'];

        foreach ($rules as $rule) {
            if (!is_array($rule) || !isset($rule['type']) || !isset($rule['variations'])) {
                throw new ValidationException('Invalid variation rule format');
            }

            if (!in_array($rule['type'], $allowedVariationTypes)) {
                throw new ValidationException("Invalid variation type: {$rule['type']}");
            }

            if (!is_array($rule['variations']) || empty($rule['variations'])) {
                throw new ValidationException('Variation rule must have at least one variation');
            }
        }
    }
}