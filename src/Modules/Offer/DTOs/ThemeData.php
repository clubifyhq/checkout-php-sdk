<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Offer\DTOs;

use Clubify\Checkout\Data\BaseData;

/**
 * DTO para dados de Tema
 *
 * Representa um tema com configurações de cores, fontes, layouts,
 * componentes e customizações visuais para ofertas.
 *
 * Segue os princípios SOLID:
 * - S: Single Responsibility - Representa apenas dados de tema
 * - O: Open/Closed - Extensível via herança
 * - L: Liskov Substitution - Pode substituir BaseData
 * - I: Interface Segregation - Implementa interfaces específicas
 * - D: Dependency Inversion - Depende de abstrações
 */
class ThemeData extends BaseData
{
    public ?string $id = null;
    public ?string $name = null;
    public ?string $description = null;
    public ?string $type = null;
    public ?string $category = null;
    public ?string $status = null;
    public ?array $colors = null;
    public ?array $fonts = null;
    public ?array $layout = null;
    public ?array $components = null;
    public ?array $responsive = null;
    public ?array $animations = null;
    public ?string $custom_css = null;
    public ?array $assets = null;
    public ?array $preview = null;
    public ?array $configuration = null;
    public ?bool $is_predefined = null;
    public ?string $author = null;
    public ?string $version = null;
    public ?array $compatibility = null;
    public ?string $created_at = null;
    public ?string $updated_at = null;

    /**
     * Obtém as regras de validação
     */
    public function getRules(): array
    {
        return [
            'name' => ['required', 'string', ['min', 2], ['max', 255]],
            'description' => ['string', ['max', 1000]],
            'type' => ['string', ['in', ['predefined', 'custom', 'imported']]],
            'category' => ['string', ['in', ['business', 'creative', 'minimal', 'modern', 'classic']]],
            'status' => ['string', ['in', ['active', 'inactive', 'draft']]],
            'colors' => ['array'],
            'fonts' => ['array'],
            'layout' => ['array'],
            'components' => ['array'],
            'responsive' => ['array'],
            'animations' => ['array'],
            'custom_css' => ['string'],
            'assets' => ['array'],
            'preview' => ['array'],
            'configuration' => ['array'],
            'is_predefined' => ['boolean'],
            'author' => ['string'],
            'version' => ['string'],
            'compatibility' => ['array'],
            'created_at' => ['date'],
            'updated_at' => ['date']
        ];
    }

    /**
     * Obtém configuração de cores
     */
    public function getColors(): array
    {
        return $this->colors ?? [];
    }

    /**
     * Define configuração de cores
     */
    public function setColors(array $colors): self
    {
        $this->colors = $colors;
        $this->data['colors'] = $colors;
        return $this;
    }

    /**
     * Obtém cor específica
     */
    public function getColor(string $name): ?string
    {
        $colors = $this->getColors();
        return $colors[$name] ?? null;
    }

    /**
     * Define cor específica
     */
    public function setColor(string $name, string $color): self
    {
        if (!is_array($this->colors)) {
            $this->colors = [];
        }
        $this->colors[$name] = $color;
        $this->data['colors'] = $this->colors;
        return $this;
    }

    /**
     * Obtém cor primária
     */
    public function getPrimaryColor(): string
    {
        return $this->getColor('primary') ?? '#007bff';
    }

    /**
     * Obtém cor secundária
     */
    public function getSecondaryColor(): string
    {
        return $this->getColor('secondary') ?? '#6c757d';
    }

    /**
     * Obtém cor de sucesso
     */
    public function getSuccessColor(): string
    {
        return $this->getColor('success') ?? '#28a745';
    }

    /**
     * Obtém cor de alerta
     */
    public function getWarningColor(): string
    {
        return $this->getColor('warning') ?? '#ffc107';
    }

    /**
     * Obtém cor de erro
     */
    public function getDangerColor(): string
    {
        return $this->getColor('danger') ?? '#dc3545';
    }

    /**
     * Obtém configuração de fontes
     */
    public function getFonts(): array
    {
        return $this->fonts ?? [];
    }

    /**
     * Define configuração de fontes
     */
    public function setFonts(array $fonts): self
    {
        $this->fonts = $fonts;
        $this->data['fonts'] = $fonts;
        return $this;
    }

    /**
     * Obtém fonte específica
     */
    public function getFont(string $element): ?array
    {
        $fonts = $this->getFonts();
        return $fonts[$element] ?? null;
    }

    /**
     * Define fonte específica
     */
    public function setFont(string $element, array $font): self
    {
        if (!is_array($this->fonts)) {
            $this->fonts = [];
        }
        $this->fonts[$element] = $font;
        $this->data['fonts'] = $this->fonts;
        return $this;
    }

    /**
     * Obtém configuração de layout
     */
    public function getLayout(): array
    {
        return $this->layout ?? [];
    }

    /**
     * Define configuração de layout
     */
    public function setLayout(array $layout): self
    {
        $this->layout = $layout;
        $this->data['layout'] = $layout;
        return $this;
    }

    /**
     * Obtém configuração de componentes
     */
    public function getComponents(): array
    {
        return $this->components ?? [];
    }

    /**
     * Define configuração de componentes
     */
    public function setComponents(array $components): self
    {
        $this->components = $components;
        $this->data['components'] = $components;
        return $this;
    }

    /**
     * Obtém configuração responsiva
     */
    public function getResponsive(): array
    {
        return $this->responsive ?? [];
    }

    /**
     * Define configuração responsiva
     */
    public function setResponsive(array $responsive): self
    {
        $this->responsive = $responsive;
        $this->data['responsive'] = $responsive;
        return $this;
    }

    /**
     * Obtém configuração de animações
     */
    public function getAnimations(): array
    {
        return $this->animations ?? [];
    }

    /**
     * Define configuração de animações
     */
    public function setAnimations(array $animations): self
    {
        $this->animations = $animations;
        $this->data['animations'] = $animations;
        return $this;
    }

    /**
     * Obtém CSS customizado
     */
    public function getCustomCss(): string
    {
        return $this->custom_css ?? '';
    }

    /**
     * Define CSS customizado
     */
    public function setCustomCss(string $css): self
    {
        $this->custom_css = $css;
        $this->data['custom_css'] = $css;
        return $this;
    }

    /**
     * Obtém assets do tema
     */
    public function getAssets(): array
    {
        return $this->assets ?? [];
    }

    /**
     * Define assets do tema
     */
    public function setAssets(array $assets): self
    {
        $this->assets = $assets;
        $this->data['assets'] = $assets;
        return $this;
    }

    /**
     * Obtém configuração de preview
     */
    public function getPreview(): array
    {
        return $this->preview ?? [];
    }

    /**
     * Define configuração de preview
     */
    public function setPreview(array $preview): self
    {
        $this->preview = $preview;
        $this->data['preview'] = $preview;
        return $this;
    }

    /**
     * Obtém configuração geral
     */
    public function getConfiguration(): array
    {
        return $this->configuration ?? [];
    }

    /**
     * Define configuração geral
     */
    public function setConfiguration(array $configuration): self
    {
        $this->configuration = $configuration;
        $this->data['configuration'] = $configuration;
        return $this;
    }

    /**
     * Verifica se é tema pré-definido
     */
    public function isPredefined(): bool
    {
        return $this->is_predefined === true || $this->type === 'predefined';
    }

    /**
     * Verifica se é tema customizado
     */
    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    /**
     * Verifica se é tema importado
     */
    public function isImported(): bool
    {
        return $this->type === 'imported';
    }

    /**
     * Verifica se está ativo
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Verifica se tem animações habilitadas
     */
    public function hasAnimations(): bool
    {
        $animations = $this->getAnimations();
        return !empty($animations) && ($animations['enabled'] ?? false);
    }

    /**
     * Verifica se é responsivo
     */
    public function isResponsive(): bool
    {
        $responsive = $this->getResponsive();
        return !empty($responsive) && ($responsive['enabled'] ?? true);
    }

    /**
     * Verifica se tem CSS customizado
     */
    public function hasCustomCss(): bool
    {
        return !empty($this->getCustomCss());
    }

    /**
     * Obtém compatibilidade
     */
    public function getCompatibility(): array
    {
        return $this->compatibility ?? [];
    }

    /**
     * Verifica compatibilidade com versão
     */
    public function isCompatibleWith(string $version): bool
    {
        $compatibility = $this->getCompatibility();
        if (empty($compatibility)) {
            return true; // Assume compatibilidade se não especificado
        }

        $minVersion = $compatibility['min_version'] ?? '1.0.0';
        $maxVersion = $compatibility['max_version'] ?? null;

        if (version_compare($version, $minVersion, '<')) {
            return false;
        }

        if ($maxVersion && version_compare($version, $maxVersion, '>')) {
            return false;
        }

        return true;
    }

    /**
     * Obtém URL do preview
     */
    public function getPreviewUrl(): ?string
    {
        $preview = $this->getPreview();
        return $preview['url'] ?? null;
    }

    /**
     * Obtém thumbnail do tema
     */
    public function getThumbnail(): ?string
    {
        $preview = $this->getPreview();
        return $preview['thumbnail'] ?? null;
    }

    /**
     * Obtém CSS compilado do tema
     */
    public function getCompiledCss(): string
    {
        $css = '';

        // CSS das cores
        $colors = $this->getColors();
        if (!empty($colors)) {
            $css .= ":root {\n";
            foreach ($colors as $name => $color) {
                $css .= "  --color-{$name}: {$color};\n";
            }
            $css .= "}\n\n";
        }

        // CSS das fontes
        $fonts = $this->getFonts();
        if (!empty($fonts)) {
            foreach ($fonts as $element => $font) {
                $css .= ".{$element} {\n";
                $css .= "  font-family: {$font['family']};\n";
                if (isset($font['size'])) {
                    $css .= "  font-size: {$font['size']};\n";
                }
                if (isset($font['weight'])) {
                    $css .= "  font-weight: {$font['weight']};\n";
                }
                $css .= "}\n\n";
            }
        }

        // CSS customizado
        $css .= $this->getCustomCss();

        return $css;
    }

    /**
     * Obtém dados para exibição pública
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'category' => $this->category,
            'colors' => $this->getColors(),
            'fonts' => $this->getFonts(),
            'layout' => $this->getLayout(),
            'components' => $this->getComponents(),
            'responsive' => $this->getResponsive(),
            'animations' => $this->getAnimations(),
            'preview' => $this->getPreview(),
            'is_predefined' => $this->isPredefined(),
            'is_responsive' => $this->isResponsive(),
            'has_animations' => $this->hasAnimations(),
            'preview_url' => $this->getPreviewUrl(),
            'thumbnail' => $this->getThumbnail()
        ];
    }

    /**
     * Obtém dados para administração
     */
    public function toAdmin(): array
    {
        return array_merge($this->toPublic(), [
            'status' => $this->status,
            'custom_css' => $this->getCustomCss(),
            'assets' => $this->getAssets(),
            'configuration' => $this->getConfiguration(),
            'author' => $this->author,
            'version' => $this->version,
            'compatibility' => $this->getCompatibility(),
            'has_custom_css' => $this->hasCustomCss(),
            'compiled_css' => $this->getCompiledCss(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ]);
    }

    /**
     * Cria instância com dados mínimos para criação
     */
    public static function forCreation(
        string $name,
        string $type = 'custom',
        array $additionalData = []
    ): self {
        return new self(array_merge([
            'name' => $name,
            'type' => $type,
            'category' => 'custom',
            'status' => 'active',
            'colors' => [
                'primary' => '#007bff',
                'secondary' => '#6c757d',
                'success' => '#28a745',
                'warning' => '#ffc107',
                'danger' => '#dc3545',
                'light' => '#f8f9fa',
                'dark' => '#343a40'
            ],
            'fonts' => [
                'heading' => ['family' => 'Inter', 'weight' => '600'],
                'body' => ['family' => 'Inter', 'weight' => '400'],
                'button' => ['family' => 'Inter', 'weight' => '500']
            ],
            'layout' => ['type' => 'single_column'],
            'components' => [],
            'responsive' => ['enabled' => true],
            'animations' => ['enabled' => false],
            'custom_css' => '',
            'assets' => [],
            'preview' => [],
            'configuration' => [],
            'is_predefined' => false,
            'compatibility' => ['min_version' => '1.0.0']
        ], $additionalData));
    }

    /**
     * Cria instância a partir de dados da API
     */
    public static function fromApi(array $apiData): self
    {
        return new self($apiData);
    }
}