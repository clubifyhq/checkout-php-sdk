# Offer Module Guide

## Table of Contents

1. [Overview](#overview)
2. [Installation and Setup](#installation-and-setup)
3. [Basic Usage](#basic-usage)
4. [API Reference](#api-reference)
5. [Advanced Features](#advanced-features)
6. [Theme and Layout Management](#theme-and-layout-management)
7. [Upsell Configuration](#upsell-configuration)
8. [Subscription Plans](#subscription-plans)
9. [Public Offer Management](#public-offer-management)
10. [Performance Optimization](#performance-optimization)
11. [Security Best Practices](#security-best-practices)
12. [Analytics and Reporting](#analytics-and-reporting)
13. [Integration Examples](#integration-examples)
14. [Migration Guide](#migration-guide)
15. [Troubleshooting](#troubleshooting)

## Overview

The Clubify Checkout SDK Offer Module provides comprehensive offer management functionality for creating, managing, and optimizing product offers, courses, subscriptions, and digital products. It supports advanced features like dynamic theming, upsell management, A/B testing, and comprehensive analytics.

### Key Features

- **Complete Offer CRUD**: Create, read, update, and delete offers
- **Advanced Theme System**: Customizable themes and layouts
- **Upsell Management**: Order bumps, post-purchase upsells, and sequences
- **Subscription Plans**: Recurring billing and plan management
- **Public Offer Pages**: SEO-optimized public landing pages
- **A/B Testing**: Built-in testing and optimization
- **Dynamic Pricing**: Time-based, demand-based, and personalized pricing
- **Analytics**: Comprehensive tracking and conversion optimization
- **Affiliate Program**: Built-in affiliate management
- **Multi-language Support**: Internationalization features

### Architecture

The Offer Module follows a service-oriented architecture:

```
OfferModule
├── Services/
│   ├── OfferService           # Core offer operations
│   ├── UpsellService          # Upsell and order bump management
│   ├── ThemeService           # Theme and layout management
│   ├── SubscriptionPlanService # Subscription plan management
│   └── PublicOfferService     # Public offer page management
├── Repositories/
│   └── ApiOfferRepository     # API communication
├── DTOs/
│   ├── OfferData             # Offer data transfer object
│   ├── UpsellData            # Upsell data transfer object
│   └── ThemeData             # Theme data transfer object
└── Factories/
    └── OfferServiceFactory    # Service creation and dependency injection
```

## Installation and Setup

### Prerequisites

- PHP 8.1 or higher
- Composer
- Clubify API credentials

### Installation

```bash
composer require clubify/checkout-sdk
```

### Configuration

```php
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;

$config = new Configuration([
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'environment' => 'sandbox', // or 'production'
    'cache' => [
        'driver' => 'redis', // recommended for offers
        'ttl' => 3600,
        'prefix' => 'clubify_offers'
    ],
    'logging' => [
        'level' => 'info',
        'file' => '/var/log/clubify_offers.log'
    ]
]);

$sdk = new ClubifyCheckoutSDK($config);
$offerModule = $sdk->offer();
```

## Basic Usage

### Creating an Offer

```php
// Basic offer creation
$offerData = [
    'name' => 'Premium Web Development Course',
    'slug' => 'premium-web-dev-course',
    'description' => 'Master modern web development technologies',
    'type' => 'course',
    'status' => 'active',
    'pricing' => [
        'base_price' => 299.99,
        'currency' => 'BRL',
        'discount_price' => 199.99
    ],
    'product_details' => [
        'duration' => '40 hours',
        'level' => 'intermediate',
        'includes' => [
            'Video lessons',
            'Practical exercises',
            'Certificate'
        ]
    ]
];

$offer = $offerModule->createOffer($offerData);
echo "Offer created: {$offer['id']}";
```

### Retrieving Offers

```php
// Get offer by ID
$offer = $offerModule->offers()->find('offer_123');

// Get public offer by slug
$publicOffer = $offerModule->getPublicOffer('premium-web-dev-course');

// List all offers
$offers = $offerModule->offers()->list([
    'status' => 'active',
    'type' => 'course',
    'limit' => 10
]);
```

### Updating Offers

```php
// Update offer details
$updatedOffer = $offerModule->offers()->update($offer['id'], [
    'pricing' => [
        'base_price' => 349.99,
        'discount_price' => 249.99,
        'discount_reason' => 'Limited time offer'
    ],
    'product_details' => [
        'includes' => [
            'Video lessons',
            'Practical exercises',
            'Certificate',
            'Premium support' // Added
        ]
    ]
]);
```

## API Reference

### OfferModule Methods

#### Core Operations

```php
// Create offer
public function createOffer(array $offerData): array

// Get public offer by slug
public function getPublicOffer(string $slug): ?array

// Configure theme
public function configureTheme(string $offerId, array $themeData): array

// Configure layout
public function configureLayout(string $offerId, array $layoutData): array

// Add upsell
public function addUpsell(string $offerId, array $upsellData): array
```

#### Service Access

```php
// Get services
public function offers(): OfferService
public function upsells(): UpsellService
public function themes(): ThemeService
public function subscriptionPlans(): SubscriptionPlanService
public function publicOffers(): PublicOfferService
```

### Service Classes

#### OfferService

Core service for offer operations:

```php
$offerService = $offerModule->offers();

// CRUD operations
$offer = $offerService->create($offerData);
$offer = $offerService->find($offerId);
$offer = $offerService->update($offerId, $updateData);
$deleted = $offerService->delete($offerId);

// Advanced operations
$offers = $offerService->list($filters);
$duplicated = $offerService->duplicate($offerId);
$archived = $offerService->archive($offerId);

// A/B testing
$abTest = $offerService->createABTest($offerId, $testData);
$results = $offerService->getABTestResults($testId);

// Dynamic pricing
$pricingResult = $offerService->configureDynamicPricing($offerId, $pricingConfig);
$currentPrice = $offerService->getCurrentPrice($offerId, $context);
```

#### UpsellService

Service for managing upsells and order bumps:

```php
$upsellService = $offerModule->upsells();

// Create upsells
$upsell = $upsellService->create($upsellData);
$orderBump = $upsellService->createOrderBump($orderBumpData);

// Configure sequences
$sequence = $upsellService->configureSequence($sequenceData);

// Analytics
$performance = $upsellService->getPerformanceAnalytics($upsellId);
```

#### ThemeService

Service for theme and layout management:

```php
$themeService = $offerModule->themes();

// Theme operations
$theme = $themeService->create($themeData);
$applied = $themeService->applyToOffer($offerId, $themeId);

// Custom CSS
$cssResult = $themeService->addCustomCss($offerId, $cssData);

// Responsive design
$responsiveConfig = $themeService->configureResponsive($themeId, $responsiveData);
```

#### SubscriptionPlanService

Service for subscription plan management:

```php
$subscriptionService = $offerModule->subscriptionPlans();

// Plan operations
$plan = $subscriptionService->create($offerId, $planData);
$plans = $subscriptionService->getPlans($offerId);

// Comparison setup
$comparison = $subscriptionService->configureComparison($comparisonData);
```

#### PublicOfferService

Service for public offer page management:

```php
$publicService = $offerModule->publicOffers();

// Public page operations
$publicOffer = $publicService->getBySlug($slug);
$settings = $publicService->updateSettings($offerId, $settingsData);

// Analytics
$analytics = $publicService->getAnalytics($offerId, $params);

// Share links
$shareLink = $publicService->generateShareLink($offerId, $platform);
```

## Advanced Features

### A/B Testing

```php
// Create A/B test
$abTestData = [
    'name' => 'Pricing Strategy Test',
    'description' => 'Test different pricing strategies',
    'variants' => [
        [
            'name' => 'Original Price',
            'weight' => 50,
            'changes' => [
                'pricing.base_price' => 299.99
            ]
        ],
        [
            'name' => 'Discounted Price',
            'weight' => 50,
            'changes' => [
                'pricing.base_price' => 199.99,
                'pricing.discount_badge' => 'Limited Time: 33% OFF!'
            ]
        ]
    ],
    'goals' => [
        'primary' => 'conversion_rate',
        'secondary' => ['revenue_per_visitor', 'cart_abandonment_rate']
    ],
    'duration' => [
        'start_date' => date('Y-m-d H:i:s'),
        'end_date' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'minimum_sample_size' => 1000
    ]
];

$abTest = $offerService->createABTest($offer['id'], $abTestData);
```

### Dynamic Pricing

```php
// Configure dynamic pricing strategies
$dynamicPricing = [
    'enabled' => true,
    'strategies' => [
        [
            'name' => 'Time-based Discount',
            'type' => 'time_decay',
            'params' => [
                'initial_discount' => 0,
                'max_discount' => 30,
                'decay_rate' => 'linear',
                'time_window' => 24 // hours
            ]
        ],
        [
            'name' => 'Demand-based Pricing',
            'type' => 'demand_surge',
            'params' => [
                'base_price' => 299.99,
                'surge_threshold' => 10, // purchases per hour
                'max_surge_multiplier' => 1.2
            ]
        ],
        [
            'name' => 'Personalized Pricing',
            'type' => 'customer_segment',
            'params' => [
                'segments' => [
                    'students' => ['discount' => 20],
                    'professionals' => ['discount' => 10],
                    'enterprises' => ['discount' => 0]
                ]
            ]
        ]
    ]
];

$pricingResult = $offerService->configureDynamicPricing($offer['id'], $dynamicPricing);
```

### Offer Restrictions

```php
// Configure offer restrictions
$restrictions = [
    'geographical' => [
        'allowed_countries' => ['BR', 'US', 'CA'],
        'blocked_regions' => [],
        'ip_verification' => true
    ],
    'temporal' => [
        'available_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'available_hours' => ['09:00', '18:00'],
        'timezone' => 'America/Sao_Paulo',
        'blackout_dates' => ['2024-12-25', '2024-01-01']
    ],
    'customer' => [
        'max_purchases_per_customer' => 1,
        'min_age_requirement' => 18,
        'require_verification' => false,
        'blocked_email_domains' => ['tempmail.com']
    ]
];

$restrictionsResult = $offerService->setRestrictions($offer['id'], $restrictions);
```

## Theme and Layout Management

### Creating Custom Themes

```php
// Create comprehensive theme
$themeData = [
    'name' => 'Modern Education Theme',
    'style' => 'modern',
    'color_scheme' => [
        'primary' => '#2563eb',
        'secondary' => '#1f2937',
        'accent' => '#f59e0b',
        'background' => '#ffffff',
        'surface' => '#f9fafb',
        'text' => '#111827',
        'text_secondary' => '#6b7280'
    ],
    'typography' => [
        'heading_font' => 'Inter',
        'body_font' => 'Inter',
        'font_sizes' => [
            'h1' => '2.25rem',
            'h2' => '1.875rem',
            'h3' => '1.5rem',
            'body' => '1rem',
            'small' => '0.875rem'
        ]
    ],
    'layout' => [
        'header_style' => 'centered',
        'navigation_style' => 'horizontal',
        'content_width' => '1200px',
        'sidebar_position' => 'right'
    ],
    'components' => [
        'buttons' => [
            'style' => 'rounded',
            'size' => 'large',
            'animation' => 'hover_scale'
        ],
        'cards' => [
            'style' => 'shadowed',
            'border_radius' => '8px'
        ],
        'forms' => [
            'style' => 'modern',
            'validation_style' => 'inline'
        ]
    ],
    'animations' => [
        'page_transitions' => true,
        'scroll_animations' => true,
        'loading_animations' => true
    ],
    'responsive' => [
        'mobile_optimized' => true,
        'tablet_layout' => 'stacked',
        'desktop_layout' => 'sidebar'
    ]
];

$theme = $themeService->create($themeData);
$applied = $themeService->applyToOffer($offer['id'], $theme['id']);
```

### Advanced Layout Configuration

```php
// Configure multi-section layout
$layoutData = [
    'structure' => 'multi_section',
    'sections' => [
        [
            'id' => 'hero',
            'type' => 'hero_banner',
            'order' => 1,
            'settings' => [
                'background_type' => 'gradient',
                'background_colors' => ['#2563eb', '#1d4ed8'],
                'text_color' => '#ffffff',
                'height' => '60vh',
                'content_alignment' => 'center'
            ],
            'content' => [
                'headline' => 'Master Web Development in 2024',
                'subheadline' => 'From zero to full-stack developer',
                'cta_text' => 'Enroll Now',
                'background_image' => 'https://example.com/hero-bg.jpg'
            ]
        ],
        [
            'id' => 'features',
            'type' => 'feature_grid',
            'order' => 2,
            'settings' => [
                'columns' => 3,
                'spacing' => 'large',
                'animation' => 'fade_in_up'
            ],
            'content' => [
                'title' => 'What You\'ll Learn',
                'features' => [
                    [
                        'icon' => 'code',
                        'title' => 'Modern JavaScript',
                        'description' => 'ES6+, async/await, modules'
                    ],
                    [
                        'icon' => 'react',
                        'title' => 'React Development',
                        'description' => 'Hooks, Context, Redux'
                    ],
                    [
                        'icon' => 'server',
                        'title' => 'Backend APIs',
                        'description' => 'Node.js, Express, databases'
                    ]
                ]
            ]
        ]
    ]
];

$layoutResult = $offerService->updateLayout($offer['id'], $layoutData);
```

### Custom CSS Integration

```php
// Add custom CSS
$customCss = [
    'css' => '
        .custom-offer-page {
            font-family: "Inter", sans-serif;
        }
        .hero-section {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        }
        .cta-button {
            background: #f59e0b;
            transition: all 0.3s ease;
        }
        .cta-button:hover {
            background: #d97706;
            transform: translateY(-2px);
        }
        @media (max-width: 768px) {
            .hero-section {
                padding: 2rem 1rem;
            }
        }
    ',
    'scope' => 'offer_page',
    'minify' => true
];

$cssResult = $themeService->addCustomCss($offer['id'], $customCss);
```

## Upsell Configuration

### Creating Upsell Sequences

```php
// Primary upsell
$primaryUpsellData = [
    'name' => 'Advanced JavaScript Masterclass',
    'description' => 'Take your JavaScript skills to the next level',
    'type' => 'course_addon',
    'position' => 'checkout',
    'trigger_conditions' => [
        'show_after_main_product' => true,
        'minimum_cart_value' => 100.00,
        'customer_segments' => ['intermediate_learners']
    ],
    'pricing' => [
        'original_price' => 149.99,
        'upsell_price' => 99.99,
        'discount_type' => 'fixed_amount'
    ],
    'content' => [
        'headline' => 'Special Offer: Advanced JavaScript',
        'subheadline' => 'Complete your learning journey',
        'bullet_points' => [
            'Advanced ES6+ features',
            'Design patterns',
            'Performance optimization',
            'Real-world projects'
        ],
        'cta_text' => 'Add Advanced Course',
        'decline_text' => 'No thanks, continue'
    ],
    'settings' => [
        'auto_add_to_cart' => false,
        'show_timer' => true,
        'timer_duration' => 300,
        'max_display_count' => 3
    ]
];

$primaryUpsell = $offerModule->addUpsell($offer['id'], $primaryUpsellData);
```

### Order Bump Configuration

```php
// Order bump
$orderBumpData = [
    'name' => 'Premium Support Package',
    'description' => '1-on-1 mentoring and priority support',
    'type' => 'service_addon',
    'position' => 'order_summary',
    'trigger_conditions' => [
        'show_probability' => 0.8,
        'exclude_mobile' => false
    ],
    'pricing' => [
        'original_price' => 197.00,
        'bump_price' => 97.00,
        'discount_type' => 'percentage',
        'discount_value' => 50.76
    ],
    'content' => [
        'headline' => 'Add Premium Support?',
        'description' => 'Get 1-on-1 mentoring and priority support',
        'benefits' => [
            '4 x 1-hour mentoring sessions',
            'Priority email support',
            'Code review services',
            'Career guidance'
        ],
        'cta_text' => 'Yes, Add Support Package'
    ],
    'display' => [
        'style' => 'checkbox_simple',
        'position_in_summary' => 'before_total',
        'highlight_savings' => true
    ],
    'settings' => [
        'default_checked' => false,
        'require_explicit_acceptance' => true
    ]
];

$orderBump = $offerModule->addUpsell($offer['id'], $orderBumpData);
```

### Upsell Sequence Management

```php
// Configure upsell sequence
$sequenceData = [
    'offer_id' => $offer['id'],
    'sequence' => [
        [
            'upsell_id' => $orderBump['id'],
            'position' => 1,
            'required' => false,
            'auto_progress' => true
        ],
        [
            'upsell_id' => $primaryUpsell['id'],
            'position' => 2,
            'required' => false,
            'conditions' => [
                'show_if_declined_previous' => true
            ]
        ]
    ],
    'global_settings' => [
        'max_upsells_per_customer' => 2,
        'respect_frequency_caps' => true,
        'track_performance' => true
    ]
];

$sequence = $upsellService->configureSequence($sequenceData);
```

## Subscription Plans

### Creating Subscription Plans

```php
// Basic subscription plan
$basicPlanData = [
    'name' => 'Basic Learning Plan',
    'description' => 'Essential courses and basic support',
    'billing_cycle' => 'monthly',
    'pricing' => [
        'amount' => 29.99,
        'currency' => 'BRL',
        'setup_fee' => 0.00,
        'trial_period_days' => 7
    ],
    'features' => [
        'access_to_basic_courses' => true,
        'community_access' => true,
        'email_support' => true,
        'monthly_webinars' => true,
        'downloadable_resources' => false,
        'certification' => false
    ],
    'limits' => [
        'concurrent_enrollments' => 3,
        'monthly_downloads' => 10,
        'support_tickets_per_month' => 5
    ],
    'terms' => [
        'minimum_commitment_months' => 1,
        'cancellation_period' => 'immediate',
        'refund_policy' => 'pro_rated'
    ]
];

$basicPlan = $subscriptionService->create($offer['id'], $basicPlanData);
```

### Premium Subscription Plan

```php
// Premium plan with annual discount
$premiumPlanData = [
    'name' => 'Premium Learning Plan',
    'description' => 'All courses, premium support, exclusive content',
    'billing_cycle' => 'monthly',
    'pricing' => [
        'amount' => 79.99,
        'currency' => 'BRL',
        'trial_period_days' => 14,
        'annual_discount' => [
            'enabled' => true,
            'discount_percentage' => 20,
            'annual_price' => 767.90
        ]
    ],
    'features' => [
        'access_to_all_courses' => true,
        'priority_support' => true,
        'weekly_live_sessions' => true,
        'downloadable_resources' => true,
        'certification' => true,
        'job_placement_assistance' => true,
        'exclusive_content' => true
    ],
    'limits' => [
        'concurrent_enrollments' => 'unlimited',
        'monthly_downloads' => 'unlimited',
        'support_tickets_per_month' => 'unlimited'
    ],
    'bonuses' => [
        'welcome_bonus' => 'Starter kit with templates',
        'monthly_bonus' => 'Exclusive masterclass recording',
        'loyalty_rewards' => true
    ]
];

$premiumPlan = $subscriptionService->create($offer['id'], $premiumPlanData);
```

### Plan Comparison Setup

```php
// Configure plan comparison
$comparisonData = [
    'offer_id' => $offer['id'],
    'display_settings' => [
        'show_comparison_table' => true,
        'highlight_recommended' => $premiumPlan['id'],
        'show_savings_badge' => true,
        'enable_plan_switching' => true
    ],
    'comparison_features' => [
        'Course Access' => [
            'basic' => '3 basic courses',
            'premium' => 'All courses'
        ],
        'Support Level' => [
            'basic' => 'Email support',
            'premium' => 'Priority email + chat'
        ],
        'Certification' => [
            'basic' => false,
            'premium' => true
        ]
    ]
];

$comparison = $subscriptionService->configureComparison($comparisonData);
```

## Public Offer Management

### SEO Optimization

```php
// Configure SEO settings
$seoSettings = [
    'seo_optimization' => [
        'enable_seo' => true,
        'meta_title' => 'Premium Web Development Course - Learn Modern Web Dev',
        'meta_description' => 'Master HTML5, CSS3, JavaScript, React and Node.js',
        'canonical_url' => "https://example.com/offer/{$offer['slug']}",
        'structured_data' => true,
        'keywords' => ['web development', 'javascript', 'react', 'nodejs']
    ],
    'social_sharing' => [
        'enable_sharing' => true,
        'platforms' => ['facebook', 'twitter', 'linkedin', 'whatsapp'],
        'og_image' => 'https://example.com/og-image.jpg',
        'twitter_card' => 'summary_large_image'
    ]
];

$seoResult = $publicService->updateSettings($offer['id'], $seoSettings);
```

### Social Sharing

```php
// Generate share links
$shareLinks = [
    'facebook' => $publicService->generateShareLink($offer['id'], 'facebook'),
    'twitter' => $publicService->generateShareLink($offer['id'], 'twitter'),
    'linkedin' => $publicService->generateShareLink($offer['id'], 'linkedin'),
    'email' => $publicService->generateShareLink($offer['id'], 'email')
];
```

### Analytics Integration

```php
// Configure analytics
$analyticsSettings = [
    'analytics' => [
        'track_page_views' => true,
        'track_engagement' => true,
        'google_analytics' => 'GA-123456789',
        'facebook_pixel' => 'FB-987654321',
        'custom_events' => [
            'video_play' => true,
            'section_scroll' => true,
            'cta_click' => true
        ]
    ]
];

$analyticsResult = $publicService->updateSettings($offer['id'], $analyticsSettings);
```

## Performance Optimization

### Caching Strategies

```php
// Configure multi-layer caching
$cacheOptimization = [
    'strategy' => 'multi_layer',
    'layers' => [
        'browser' => ['ttl' => 3600, 'enabled' => true],
        'cdn' => ['ttl' => 7200, 'enabled' => true],
        'application' => ['ttl' => 1800, 'enabled' => true],
        'database' => ['ttl' => 3600, 'enabled' => true]
    ],
    'invalidation_triggers' => [
        'offer_update',
        'pricing_change',
        'content_modification'
    ]
];

$cacheResult = $offerService->optimizeCache($offer['id'], $cacheOptimization);
```

### Image Optimization

```php
// Optimize images
$imageOptimization = [
    'formats' => ['webp', 'avif', 'jpg'],
    'quality' => 85,
    'responsive_sizes' => [320, 640, 1024, 1920],
    'lazy_loading' => true,
    'cdn_delivery' => true
];

$imageResult = $offerService->optimizeImages($offer['id'], $imageOptimization);
```

### Performance Monitoring

```php
// Setup performance monitoring
$performanceMonitoring = [
    'metrics' => [
        'page_load_time',
        'time_to_first_byte',
        'largest_contentful_paint',
        'cumulative_layout_shift'
    ],
    'thresholds' => [
        'page_load_time' => 3000, // ms
        'time_to_first_byte' => 600 // ms
    ],
    'alerts' => [
        'performance_degradation' => true,
        'webhook_urls' => ['https://example.com/alerts']
    ]
];

$monitoring = $offerService->setupPerformanceMonitoring($offer['id'], $performanceMonitoring);
```

## Security Best Practices

### Input Validation and Sanitization

```php
// Validate and sanitize offer data
$unsafeData = [
    'name' => '<script>alert("xss")</script>Legitimate Course Name',
    'description' => 'Course description with <img src="x" onerror="alert(1)">',
    'metadata' => [
        'instructor' => 'John\'; DROP TABLE offers; --',
        'category' => 'education'
    ]
];

$sanitized = $offerService->validateAndSanitizeInput($unsafeData);
```

### CSRF Protection

```php
use Clubify\Checkout\Core\Security\CsrfProtection;

$csrf = new CsrfProtection();
$token = $csrf->generateToken();

// Validate before operations
if ($csrf->validateToken($_POST['csrf_token'])) {
    $offer = $offerService->update($offerId, $updateData);
}
```

### Rate Limiting

```php
// Check rate limits
$rateLimit = $offerService->checkRateLimit($offer['id'], [
    'requests_per_minute' => 100,
    'requests_per_hour' => 2000,
    'identifier_type' => 'ip_address'
]);

if (!$rateLimit['within_limits']) {
    throw new Exception("Rate limit exceeded");
}
```

### Security Audit

```php
// Run security audit
$audit = $offerService->securityAudit($offer['id'], [
    'check_xss_vulnerabilities' => true,
    'validate_input_sanitization' => true,
    'check_csrf_protection' => true,
    'verify_ssl_configuration' => true
]);

echo "Security issues found: {$audit['issues_found']}";
```

## Analytics and Reporting

### Conversion Funnel Analysis

```php
// Generate conversion funnel
$funnelReport = $offerService->getConversionFunnel($offer['id'], [
    'period' => 'last_30_days',
    'steps' => [
        'landing_page_view',
        'offer_details_view',
        'add_to_cart',
        'checkout_started',
        'payment_completed'
    ]
]);

foreach ($funnelReport['steps'] as $step) {
    echo "{$step['name']}: {$step['conversion_rate']}%\n";
}
```

### Revenue Analytics

```php
// Revenue analysis
$revenueReport = $offerService->getRevenueAnalytics($offer['id'], [
    'period' => 'last_90_days',
    'granularity' => 'daily',
    'include_refunds' => true,
    'segment_by' => ['traffic_source', 'device_type']
]);

echo "Total revenue: {$revenueReport['total_revenue']}\n";
echo "Average order value: {$revenueReport['average_order_value']}\n";
```

### A/B Test Results

```php
// A/B test performance
$abTestReport = $offerService->getABTestReport($abTest['id'], [
    'metrics' => ['conversion_rate', 'revenue_per_visitor'],
    'statistical_significance' => true
]);

foreach ($abTestReport['variants'] as $variant) {
    echo "{$variant['name']}: {$variant['conversion_rate']}%\n";
}
```

## Integration Examples

### E-learning Platform Integration

```php
class ELearningOfferIntegration
{
    private $offerModule;
    private $courseService;
    private $userService;

    public function createCourseOffer($courseId, $instructorId)
    {
        // Get course details
        $course = $this->courseService->getCourse($courseId);
        $instructor = $this->userService->getInstructor($instructorId);

        // Create offer
        $offerData = [
            'name' => $course['title'],
            'slug' => $this->generateSlug($course['title']),
            'description' => $course['description'],
            'type' => 'course',
            'pricing' => [
                'base_price' => $course['price'],
                'currency' => 'BRL'
            ],
            'product_details' => [
                'duration' => $course['duration'],
                'level' => $course['difficulty'],
                'instructor' => $instructor['name'],
                'modules' => count($course['modules']),
                'includes' => [
                    'Video lessons',
                    'Downloadable resources',
                    'Certificate of completion',
                    'Lifetime access'
                ]
            ],
            'metadata' => [
                'course_id' => $courseId,
                'instructor_id' => $instructorId,
                'category' => $course['category']
            ]
        ];

        return $this->offerModule->createOffer($offerData);
    }
}
```

### SaaS Product Integration

```php
class SaaSOfferIntegration
{
    public function createSaaSProductOffer($productId)
    {
        $product = $this->getProduct($productId);

        // Create subscription plans
        $plans = [
            'starter' => [
                'name' => 'Starter Plan',
                'price' => 29.99,
                'features' => ['Basic features', 'Email support'],
                'limits' => ['users' => 5, 'projects' => 10]
            ],
            'professional' => [
                'name' => 'Professional Plan',
                'price' => 79.99,
                'features' => ['All features', 'Priority support'],
                'limits' => ['users' => 25, 'projects' => 100]
            ],
            'enterprise' => [
                'name' => 'Enterprise Plan',
                'price' => 199.99,
                'features' => ['All features', 'Custom integrations'],
                'limits' => ['users' => 'unlimited', 'projects' => 'unlimited']
            ]
        ];

        // Create main offer
        $offer = $this->offerModule->createOffer([
            'name' => $product['name'],
            'type' => 'saas_product',
            'description' => $product['description']
        ]);

        // Add subscription plans
        foreach ($plans as $planKey => $planData) {
            $this->offerModule->subscriptionPlans()->create($offer['id'], $planData);
        }

        return $offer;
    }
}
```

### Marketplace Integration

```php
class MarketplaceOfferIntegration
{
    public function createMarketplaceOffer($vendorId, $productId)
    {
        $vendor = $this->getVendor($vendorId);
        $product = $this->getProduct($productId);

        // Create offer with vendor branding
        $offer = $this->offerModule->createOffer([
            'name' => $product['name'],
            'description' => $product['description'],
            'pricing' => [
                'base_price' => $product['price'],
                'marketplace_fee' => $product['price'] * 0.05 // 5% fee
            ],
            'vendor_info' => [
                'vendor_id' => $vendorId,
                'vendor_name' => $vendor['name'],
                'vendor_rating' => $vendor['rating']
            ]
        ]);

        // Configure vendor-specific theme
        $this->offerModule->configureTheme($offer['id'], [
            'vendor_branding' => true,
            'logo' => $vendor['logo'],
            'color_scheme' => $vendor['brand_colors']
        ]);

        return $offer;
    }
}
```

## Migration Guide

### From Legacy Offer Systems

```php
// Migration utility
class OfferMigrationUtility
{
    public function migrateLegacyOffer($legacyOffer)
    {
        // Map legacy fields to new structure
        $offerData = [
            'name' => $legacyOffer['title'],
            'slug' => $this->generateSlug($legacyOffer['title']),
            'description' => $legacyOffer['content'],
            'type' => $this->mapOfferType($legacyOffer['type']),
            'status' => $legacyOffer['active'] ? 'active' : 'draft',
            'pricing' => [
                'base_price' => $legacyOffer['price'],
                'currency' => $legacyOffer['currency'] ?? 'BRL'
            ],
            'metadata' => [
                'legacy_id' => $legacyOffer['id'],
                'migrated_at' => date('Y-m-d H:i:s')
            ]
        ];

        // Migrate images and assets
        if (!empty($legacyOffer['images'])) {
            $offerData['media'] = $this->migrateImages($legacyOffer['images']);
        }

        return $this->offerModule->createOffer($offerData);
    }

    private function mapOfferType($legacyType)
    {
        $mapping = [
            'product' => 'physical_product',
            'service' => 'service',
            'course' => 'course',
            'subscription' => 'subscription'
        ];

        return $mapping[$legacyType] ?? 'other';
    }
}
```

### Bulk Migration

```php
// Bulk migration with progress tracking
function bulkMigrateOffers($batchSize = 50)
{
    $offset = 0;
    $migrated = 0;
    $errors = 0;

    do {
        $legacyOffers = $this->getLegacyOffers($offset, $batchSize);

        foreach ($legacyOffers as $legacyOffer) {
            try {
                $migrated_offer = $this->migrateLegacyOffer($legacyOffer);
                $migrated++;

                echo "Migrated: {$legacyOffer['title']} -> {$migrated_offer['id']}\n";

            } catch (Exception $e) {
                $errors++;
                echo "Error migrating {$legacyOffer['title']}: {$e->getMessage()}\n";
            }
        }

        $offset += $batchSize;

    } while (count($legacyOffers) === $batchSize);

    echo "Migration complete: {$migrated} migrated, {$errors} errors\n";
}
```

## Troubleshooting

### Common Issues

#### Issue: Offer Not Displaying

```php
// Check offer status and configuration
$offer = $offerModule->offers()->find($offerId);

if (!$offer) {
    throw new Exception("Offer not found");
}

if ($offer['status'] !== 'active') {
    throw new Exception("Offer is not active: {$offer['status']}");
}

// Check public availability
$publicOffer = $offerModule->getPublicOffer($offer['slug']);
if (!$publicOffer) {
    // Check restrictions
    $restrictions = $offerModule->offers()->getRestrictions($offerId);
    // Debug restrictions...
}
```

#### Issue: Theme Not Applying

```php
// Debug theme application
$themeStatus = $themeService->getThemeStatus($offerId);

if (!$themeStatus['applied']) {
    // Check theme validity
    $validation = $themeService->validateTheme($themeStatus['theme_id']);

    if (!$validation['valid']) {
        echo "Theme validation failed: " . implode(', ', $validation['errors']);

        // Reset to default theme
        $themeService->resetToDefault($offerId);
    }
}
```

#### Issue: Performance Problems

```php
// Performance diagnostics
$performance = $offerService->getPerformanceMetrics($offerId);

if ($performance['load_time'] > 3000) { // > 3 seconds
    // Enable optimizations
    $offerService->optimizeCache($offerId);
    $offerService->optimizeImages($offerId);
    $offerService->optimizeQueries($offerId);

    echo "Performance optimizations applied";
}
```

### Debug Mode

```php
// Enable comprehensive debugging
$config = new Configuration([
    'debug' => true,
    'logging' => [
        'level' => 'debug',
        'include_request_response' => true,
        'log_performance_metrics' => true
    ]
]);

// Get debug information
$debugInfo = $offerModule->getDebugInfo();
```

### Support Resources

- **Documentation**: https://docs.clubify.me/offer-module
- **API Reference**: https://api.clubify.me/docs/offers
- **Support Forum**: https://community.clubify.me/offers
- **GitHub Issues**: https://github.com/clubify/checkout-sdk-php/issues

---

For comprehensive examples and real-world implementations, refer to:
- [`/examples/offer-module-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/offer-module-examples.php)
- [`/examples/integration-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/integration-examples.php)