<?php

declare(strict_types=1);

namespace Clubify\Checkout\Modules\Products\DTOs;

use Clubify\Checkout\Data\BaseData;
use DateTime;

/**
 * DTO para dados de layout
 */
class LayoutData extends BaseData
{
    public string $id;
    public string $name;
    public ?string $description = null;
    public string $type = 'single_column';
    public bool $is_active = true;
    public bool $is_default = false;
    public array $structure = [];
    public array $sections = [];
    public array $components = [];
    public array $responsive_config = [];
    public array $validation_rules = [];
    public array $conversion_elements = [];
    public array $seo_config = [];
    public array $analytics_config = [];
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
            'type' => ['in:single_column,two_column,three_column,sidebar_left,sidebar_right,full_width,custom'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'structure' => ['required', 'array'],
            'sections' => ['array'],
            'components' => ['array'],
            'responsive_config' => ['array'],
            'validation_rules' => ['array'],
            'conversion_elements' => ['array'],
            'seo_config' => ['array'],
            'analytics_config' => ['array'],
            'metadata' => ['array'],
            'preview_url' => ['string'],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
        ];
    }

    public function getDefaultStructure(): array
    {
        return match($this->type) {
            'single_column' => [
                'container' => [
                    'width' => 'max-w-2xl',
                    'padding' => 'px-4',
                    'margin' => 'mx-auto',
                ],
                'sections' => [
                    'header',
                    'product_info',
                    'customer_info',
                    'payment_info',
                    'order_summary',
                    'footer',
                ],
            ],
            'two_column' => [
                'container' => [
                    'width' => 'max-w-6xl',
                    'padding' => 'px-4',
                    'margin' => 'mx-auto',
                ],
                'grid' => [
                    'columns' => 2,
                    'gap' => 'gap-8',
                ],
                'left_column' => [
                    'sections' => ['customer_info', 'payment_info'],
                    'width' => 'col-span-1',
                ],
                'right_column' => [
                    'sections' => ['order_summary', 'product_info'],
                    'width' => 'col-span-1',
                ],
            ],
            'three_column' => [
                'container' => [
                    'width' => 'max-w-7xl',
                    'padding' => 'px-4',
                    'margin' => 'mx-auto',
                ],
                'grid' => [
                    'columns' => 3,
                    'gap' => 'gap-6',
                ],
                'left_column' => [
                    'sections' => ['product_info'],
                    'width' => 'col-span-1',
                ],
                'center_column' => [
                    'sections' => ['customer_info', 'payment_info'],
                    'width' => 'col-span-1',
                ],
                'right_column' => [
                    'sections' => ['order_summary', 'testimonials'],
                    'width' => 'col-span-1',
                ],
            ],
            default => []
        };
    }

    public function getDefaultSections(): array
    {
        return [
            'header' => [
                'component' => 'HeaderSection',
                'props' => [
                    'show_logo' => true,
                    'show_progress' => true,
                    'show_security_badges' => true,
                ],
                'order' => 1,
            ],
            'product_info' => [
                'component' => 'ProductInfoSection',
                'props' => [
                    'show_image' => true,
                    'show_description' => true,
                    'show_features' => true,
                    'show_testimonials' => false,
                ],
                'order' => 2,
            ],
            'customer_info' => [
                'component' => 'CustomerInfoSection',
                'props' => [
                    'require_phone' => false,
                    'show_company_fields' => false,
                    'enable_autofill' => true,
                ],
                'order' => 3,
            ],
            'payment_info' => [
                'component' => 'PaymentInfoSection',
                'props' => [
                    'show_payment_methods' => true,
                    'enable_saved_cards' => true,
                    'show_security_info' => true,
                ],
                'order' => 4,
            ],
            'order_summary' => [
                'component' => 'OrderSummarySection',
                'props' => [
                    'show_product_images' => true,
                    'show_quantity_selector' => true,
                    'show_coupon_field' => true,
                    'show_tax_breakdown' => true,
                ],
                'order' => 5,
            ],
            'footer' => [
                'component' => 'FooterSection',
                'props' => [
                    'show_links' => true,
                    'show_security_badges' => true,
                    'show_contact_info' => true,
                ],
                'order' => 6,
            ],
        ];
    }

    public function getDefaultResponsiveConfig(): array
    {
        return [
            'mobile' => [
                'breakpoint' => '0px',
                'columns' => 1,
                'spacing' => 'tight',
                'hide_sections' => [],
                'reorder_sections' => true,
            ],
            'tablet' => [
                'breakpoint' => '768px',
                'columns' => $this->type === 'single_column' ? 1 : 2,
                'spacing' => 'normal',
                'hide_sections' => [],
                'reorder_sections' => false,
            ],
            'desktop' => [
                'breakpoint' => '1024px',
                'columns' => match($this->type) {
                    'single_column' => 1,
                    'two_column' => 2,
                    'three_column' => 3,
                    default => 2
                },
                'spacing' => 'comfortable',
                'hide_sections' => [],
                'reorder_sections' => false,
            ],
        ];
    }

    public function hasSection(string $section): bool
    {
        return isset($this->sections[$section]);
    }

    public function getSection(string $section): ?array
    {
        return $this->sections[$section] ?? null;
    }

    public function addSection(string $name, array $config): void
    {
        $this->sections[$name] = $config;
    }

    public function removeSection(string $name): void
    {
        unset($this->sections[$name]);
    }

    public function reorderSections(array $order): void
    {
        $newSections = [];
        foreach ($order as $sectionName) {
            if (isset($this->sections[$sectionName])) {
                $newSections[$sectionName] = $this->sections[$sectionName];
            }
        }
        $this->sections = $newSections;
    }

    public function isResponsive(): bool
    {
        return !empty($this->responsive_config);
    }

    public function supportsDevice(string $device): bool
    {
        return isset($this->responsive_config[$device]);
    }

    public function getDeviceConfig(string $device): ?array
    {
        return $this->responsive_config[$device] ?? null;
    }

    public function hasConversionElements(): bool
    {
        return !empty($this->conversion_elements);
    }

    public function getConversionElement(string $element): ?array
    {
        return $this->conversion_elements[$element] ?? null;
    }

    public function validateStructure(): array
    {
        $errors = [];

        // Validar seções obrigatórias
        $requiredSections = ['customer_info', 'payment_info'];
        foreach ($requiredSections as $section) {
            if (!$this->hasSection($section)) {
                $errors[] = "Required section '{$section}' is missing";
            }
        }

        // Validar estrutura responsiva
        if ($this->isResponsive()) {
            foreach ($this->responsive_config as $device => $config) {
                if (!isset($config['breakpoint'])) {
                    $errors[] = "Device '{$device}' missing breakpoint configuration";
                }
            }
        }

        return $errors;
    }

    public function generateHTML(): string
    {
        $html = '<div class="checkout-layout ' . $this->type . '">';

        if ($this->type === 'single_column') {
            $html .= $this->generateSingleColumnHTML();
        } elseif ($this->type === 'two_column') {
            $html .= $this->generateTwoColumnHTML();
        } elseif ($this->type === 'three_column') {
            $html .= $this->generateThreeColumnHTML();
        } else {
            $html .= $this->generateCustomHTML();
        }

        $html .= '</div>';

        return $html;
    }

    private function generateSingleColumnHTML(): string
    {
        $html = '<div class="container mx-auto max-w-2xl px-4">';

        foreach ($this->sections as $name => $config) {
            $html .= '<div class="section section-' . $name . '">';
            $html .= '<!-- Section: ' . $name . ' -->';
            $html .= '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    private function generateTwoColumnHTML(): string
    {
        $html = '<div class="container mx-auto max-w-6xl px-4">';
        $html .= '<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">';

        // Left column
        $html .= '<div class="col-span-1">';
        $leftSections = $this->structure['left_column']['sections'] ?? [];
        foreach ($leftSections as $section) {
            if (isset($this->sections[$section])) {
                $html .= '<div class="section section-' . $section . '">';
                $html .= '<!-- Section: ' . $section . ' -->';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        // Right column
        $html .= '<div class="col-span-1">';
        $rightSections = $this->structure['right_column']['sections'] ?? [];
        foreach ($rightSections as $section) {
            if (isset($this->sections[$section])) {
                $html .= '<div class="section section-' . $section . '">';
                $html .= '<!-- Section: ' . $section . ' -->';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function generateThreeColumnHTML(): string
    {
        $html = '<div class="container mx-auto max-w-7xl px-4">';
        $html .= '<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">';

        // Columns
        $columns = ['left_column', 'center_column', 'right_column'];
        foreach ($columns as $column) {
            $html .= '<div class="col-span-1">';
            $sections = $this->structure[$column]['sections'] ?? [];
            foreach ($sections as $section) {
                if (isset($this->sections[$section])) {
                    $html .= '<div class="section section-' . $section . '">';
                    $html .= '<!-- Section: ' . $section . ' -->';
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        return $html;
    }

    private function generateCustomHTML(): string
    {
        return '<!-- Custom layout implementation -->';
    }

    public function toAnalyticsFormat(): array
    {
        return [
            'layout_id' => $this->id,
            'layout_name' => $this->name,
            'layout_type' => $this->type,
            'sections_count' => count($this->sections),
            'is_responsive' => $this->isResponsive(),
            'has_conversion_elements' => $this->hasConversionElements(),
            'structure_complexity' => $this->calculateComplexity(),
        ];
    }

    private function calculateComplexity(): string
    {
        $sectionCount = count($this->sections);
        $hasResponsive = $this->isResponsive();
        $hasConversion = $this->hasConversionElements();

        if ($sectionCount <= 3 && !$hasResponsive && !$hasConversion) {
            return 'simple';
        } elseif ($sectionCount <= 6 && ($hasResponsive || $hasConversion)) {
            return 'moderate';
        } else {
            return 'complex';
        }
    }
}
