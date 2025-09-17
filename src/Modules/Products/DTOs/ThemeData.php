<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de tema
 */
class ThemeData extends BaseData
{
    public string $id;
    public string $name;
    public ?string $description = null;
    public string $category = 'custom';
    public bool $is_active = true;
    public bool $is_premium = false;
    public array $color_palette = [];
    public array $typography = [];
    public array $spacing = [];
    public array $border_radius = [];
    public array $shadows = [];
    public array $animations = [];
    public array $custom_css = [];
    public array $components = [];
    public array $layout_options = [];
    public array $responsive_breakpoints = [];
    public array $dark_mode_support = [];
    public array $accessibility_features = [];
    public array $metadata = [];
    public string $preview_url = '';
    public DateTime $created_at;
    public DateTime $updated_at;

    public function getRules(): array
    {
        return [
            'id' => ['string'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['in:light,dark,modern,premium,custom,seasonal'],
            'is_active' => ['boolean'],
            'is_premium' => ['boolean'],
            'color_palette' => ['array'],
            'typography' => ['array'],
            'spacing' => ['array'],
            'border_radius' => ['array'],
            'shadows' => ['array'],
            'animations' => ['array'],
            'custom_css' => ['array'],
            'components' => ['array'],
            'layout_options' => ['array'],
            'responsive_breakpoints' => ['array'],
            'dark_mode_support' => ['array'],
            'accessibility_features' => ['array'],
            'metadata' => ['array'],
            'preview_url' => ['string'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ];
    }

    public function getDefaultColorPalette(): array
    {
        return [
            'primary' => '#3B82F6',
            'primary_hover' => '#2563EB',
            'secondary' => '#6B7280',
            'success' => '#10B981',
            'warning' => '#F59E0B',
            'error' => '#EF4444',
            'background' => '#FFFFFF',
            'surface' => '#F9FAFB',
            'text_primary' => '#111827',
            'text_secondary' => '#6B7280',
            'text_muted' => '#9CA3AF',
            'border' => '#E5E7EB',
            'border_light' => '#F3F4F6',
        ];
    }

    public function getDefaultTypography(): array
    {
        return [
            'font_family_primary' => 'Inter, system-ui, sans-serif',
            'font_family_secondary' => 'Inter, system-ui, sans-serif',
            'font_size_xs' => '0.75rem',
            'font_size_sm' => '0.875rem',
            'font_size_base' => '1rem',
            'font_size_lg' => '1.125rem',
            'font_size_xl' => '1.25rem',
            'font_size_2xl' => '1.5rem',
            'font_size_3xl' => '1.875rem',
            'font_weight_normal' => '400',
            'font_weight_medium' => '500',
            'font_weight_semibold' => '600',
            'font_weight_bold' => '700',
            'line_height_tight' => '1.25',
            'line_height_normal' => '1.5',
            'line_height_relaxed' => '1.75',
        ];
    }

    public function getDefaultSpacing(): array
    {
        return [
            'xs' => '0.25rem',
            'sm' => '0.5rem',
            'md' => '1rem',
            'lg' => '1.5rem',
            'xl' => '2rem',
            '2xl' => '3rem',
            '3xl' => '4rem',
            '4xl' => '6rem',
        ];
    }

    public function getDefaultBorderRadius(): array
    {
        return [
            'none' => '0',
            'sm' => '0.25rem',
            'md' => '0.375rem',
            'lg' => '0.5rem',
            'xl' => '0.75rem',
            '2xl' => '1rem',
            'full' => '9999px',
        ];
    }

    public function getDefaultShadows(): array
    {
        return [
            'sm' => '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
            'md' => '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06)',
            'lg' => '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
            'xl' => '0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04)',
        ];
    }

    public function hasComponent(string $component): bool
    {
        return isset($this->components[$component]);
    }

    public function getComponent(string $component): ?array
    {
        return $this->components[$component] ?? null;
    }

    public function supportsResponsive(): bool
    {
        return !empty($this->responsive_breakpoints);
    }

    public function supportsDarkMode(): bool
    {
        return !empty($this->dark_mode_support);
    }

    public function hasAccessibilityFeatures(): bool
    {
        return !empty($this->accessibility_features);
    }

    public function isResponsiveForDevice(string $device): bool
    {
        return isset($this->responsive_breakpoints[$device]);
    }

    public function getBreakpoint(string $device): ?string
    {
        return $this->responsive_breakpoints[$device] ?? null;
    }

    public function compileCSSVariables(): array
    {
        $cssVars = [];

        // Color palette
        foreach ($this->color_palette as $key => $value) {
            $cssVars["--color-{$key}"] = $value;
        }

        // Typography
        foreach ($this->typography as $key => $value) {
            $cssVars["--{$key}"] = $value;
        }

        // Spacing
        foreach ($this->spacing as $key => $value) {
            $cssVars["--spacing-{$key}"] = $value;
        }

        // Border radius
        foreach ($this->border_radius as $key => $value) {
            $cssVars["--radius-{$key}"] = $value;
        }

        // Shadows
        foreach ($this->shadows as $key => $value) {
            $cssVars["--shadow-{$key}"] = $value;
        }

        return $cssVars;
    }

    public function generateCSS(): string
    {
        $cssVars = $this->compileCSSVariables();
        $css = ":root {\n";

        foreach ($cssVars as $property => $value) {
            $css .= "  {$property}: {$value};\n";
        }

        $css .= "}\n\n";

        // Add custom CSS
        if (!empty($this->custom_css)) {
            foreach ($this->custom_css as $selector => $rules) {
                $css .= "{$selector} {\n";
                foreach ($rules as $property => $value) {
                    $css .= "  {$property}: {$value};\n";
                }
                $css .= "}\n\n";
            }
        }

        return $css;
    }

    public function toPreviewFormat(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'is_premium' => $this->is_premium,
            'preview_url' => $this->preview_url,
            'color_palette' => $this->color_palette,
            'supports_dark_mode' => $this->supportsDarkMode(),
            'supports_responsive' => $this->supportsResponsive(),
            'has_accessibility' => $this->hasAccessibilityFeatures(),
        ];
    }
}
