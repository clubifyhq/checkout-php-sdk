<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\Services;

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
            return $response->getData() ?? [];
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
            $theme = $response->getData();

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
            $theme = $response->getData();

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

            $result = $response->getData();

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

            $result = $response->getData();

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
            return $response->getData() ?? [];
        });
    }

    /**
     * Duplica tema
     */
    public function duplicate(string $themeId, array $overrideData = []): array
    {
        return $this->executeWithMetrics('duplicate_theme', function () use ($themeId, $overrideData) {
            $response = $this->httpClient->post("/themes/{$themeId}/duplicate", $overrideData);
            $theme = $response->getData();

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
            return $response->getData() ?? [];
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
            $theme = $response->getData();

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
            return $response->getData() ?? [];
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

            $theme = $response->getData();

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

            $theme = $response->getData();

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
     * Busca tema por ID via API
     */
    private function fetchThemeById(string $themeId): ?array
    {
        try {
            $response = $this->httpClient->get("/themes/{$themeId}");
            return $response->getData();
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
}