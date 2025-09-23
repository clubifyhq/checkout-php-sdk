<?php

/**
 * CLUBIFY CHECKOUT SDK - OFFER MODULE EXAMPLES
 *
 * Comprehensive examples demonstrating all Offer Module features:
 * - Offer CRUD operations
 * - Theme and layout management
 * - Upsell configuration
 * - Subscription plans
 * - Public offer access
 * - Advanced offer features
 * - Security and validation
 * - Performance optimization
 * - Error handling
 * - Best practices
 *
 * @version 1.0.0
 * @author Clubify Development Team
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;
use Clubify\Checkout\Core\Logger\Logger;
use Clubify\Checkout\Core\Security\CsrfProtection;
use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\HttpException;

// ===========================================
// SETUP AND CONFIGURATION
// ===========================================

echo "=== CLUBIFY CHECKOUT SDK - OFFER MODULE EXAMPLES ===\n\n";

try {
    // Initialize SDK with configuration
    $config = new Configuration([
        'api_key' => $_ENV['CLUBIFY_API_KEY'] ?? 'your-api-key-here',
        'api_secret' => $_ENV['CLUBIFY_API_SECRET'] ?? 'your-api-secret-here',
        'environment' => $_ENV['CLUBIFY_ENV'] ?? 'sandbox',
        'api_base_url' => $_ENV['CLUBIFY_API_URL'] ?? 'https://api.clubify.me',
        'timeout' => 30,
        'retry_attempts' => 3,
        'cache' => [
            'driver' => 'file',
            'ttl' => 1800, // 30 minutes
            'path' => sys_get_temp_dir() . '/clubify_cache'
        ],
        'security' => [
            'csrf_protection' => true,
            'rate_limiting' => true,
            'input_validation' => true
        ],
        'logging' => [
            'level' => 'info',
            'file' => sys_get_temp_dir() . '/clubify_offer_examples.log'
        ]
    ]);

    $logger = new Logger($config->get('logging', []));
    $sdk = new ClubifyCheckoutSDK($config, $logger);

    // Get Offer Module
    $offerModule = $sdk->offer();

    echo "✓ SDK initialized successfully\n";
    echo "✓ Offer module loaded\n\n";

} catch (Exception $e) {
    echo "✗ Setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ===========================================
// EXAMPLE 1: BASIC OFFER OPERATIONS
// ===========================================

echo "=== EXAMPLE 1: BASIC OFFER OPERATIONS ===\n";

try {
    // Create a complete offer
    echo "Creating a comprehensive offer...\n";

    $offerData = [
        'name' => 'Premium Web Development Course 2024',
        'slug' => 'premium-web-dev-2024',
        'description' => 'Master modern web development with our comprehensive course',
        'type' => 'course',
        'status' => 'active',
        'pricing' => [
            'base_price' => 299.99,
            'currency' => 'BRL',
            'discount_price' => 199.99,
            'discount_type' => 'percentage',
            'discount_value' => 33.33
        ],
        'product_details' => [
            'duration' => '40 hours',
            'level' => 'intermediate',
            'format' => 'online',
            'language' => 'portuguese',
            'includes' => [
                'Video lessons',
                'Practical exercises',
                'Certificate of completion',
                '6 months support'
            ]
        ],
        'availability' => [
            'start_date' => date('Y-m-d H:i:s'),
            'end_date' => date('Y-m-d H:i:s', strtotime('+6 months')),
            'timezone' => 'America/Sao_Paulo',
            'max_enrollments' => 100
        ],
        'seo' => [
            'meta_title' => 'Premium Web Development Course - Learn Modern Web Dev',
            'meta_description' => 'Master HTML5, CSS3, JavaScript, React and Node.js in our comprehensive course',
            'keywords' => ['web development', 'javascript', 'react', 'nodejs'],
            'og_image' => 'https://example.com/course-image.jpg'
        ],
        'configuration' => [
            'allow_quantity_selection' => false,
            'require_immediate_payment' => true,
            'enable_installments' => true,
            'max_installments' => 12
        ],
        'metadata' => [
            'category' => 'education',
            'instructor' => 'João Silva',
            'difficulty' => 'intermediate',
            'created_by' => 'admin_user_123'
        ]
    ];

    $offer = $offerModule->createOffer($offerData);
    echo "✓ Offer created with ID: {$offer['id']}\n";
    echo "✓ Offer slug: {$offer['slug']}\n";

    // Get offer by ID
    echo "Retrieving offer by ID...\n";
    $retrievedOffer = $offerModule->offers()->find($offer['id']);
    echo "✓ Offer retrieved: {$retrievedOffer['name']}\n";

    // Update offer
    echo "Updating offer...\n";
    $updateData = [
        'description' => 'Updated: Master modern web development with our comprehensive course including new React 18 features',
        'pricing' => array_merge($offer['pricing'], [
            'discount_price' => 179.99,
            'discount_value' => 40.00
        ]),
        'product_details' => array_merge($offer['product_details'], [
            'includes' => array_merge($offer['product_details']['includes'], [
                'React 18 exclusive content',
                'Advanced TypeScript module'
            ])
        ]),
        'metadata' => array_merge($offer['metadata'], [
            'updated_at' => date('Y-m-d H:i:s'),
            'version' => '2.0'
        ])
    ];

    $updatedOffer = $offerModule->offers()->update($offer['id'], $updateData);
    echo "✓ Offer updated successfully\n";

    echo "Example 1 completed successfully!\n\n";

} catch (ValidationException $e) {
    echo "✗ Validation error: " . $e->getMessage() . "\n\n";
} catch (HttpException $e) {
    echo "✗ API error: " . $e->getMessage() . "\n\n";
} catch (Exception $e) {
    echo "✗ Unexpected error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 2: THEME AND LAYOUT MANAGEMENT
// ===========================================

echo "=== EXAMPLE 2: THEME AND LAYOUT MANAGEMENT ===\n";

try {
    // Configure offer theme
    echo "Configuring offer theme...\n";

    $themeData = [
        'name' => 'Modern Education Theme',
        'style' => 'modern',
        'color_scheme' => [
            'primary' => '#2563eb',      // Blue
            'secondary' => '#1f2937',    // Dark gray
            'accent' => '#f59e0b',       // Orange
            'background' => '#ffffff',    // White
            'surface' => '#f9fafb',      // Light gray
            'text' => '#111827',         // Dark text
            'text_secondary' => '#6b7280' // Gray text
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

    $themeResult = $offerModule->configureTheme($offer['id'], $themeData);
    echo "✓ Theme configured successfully\n";

    // Configure advanced layout
    echo "Configuring advanced layout...\n";

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
                    'subheadline' => 'From zero to full-stack developer in 10 weeks',
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
                            'description' => 'ES6+, async/await, modules and more'
                        ],
                        [
                            'icon' => 'react',
                            'title' => 'React Development',
                            'description' => 'Hooks, Context, Redux and best practices'
                        ],
                        [
                            'icon' => 'server',
                            'title' => 'Backend APIs',
                            'description' => 'Node.js, Express, databases and deployment'
                        ]
                    ]
                ]
            ],
            [
                'id' => 'pricing',
                'type' => 'pricing_section',
                'order' => 3,
                'settings' => [
                    'style' => 'card_based',
                    'highlight_recommended' => true,
                    'show_comparison' => true
                ],
                'content' => [
                    'title' => 'Choose Your Plan',
                    'subtitle' => 'Flexible options for every learning style'
                ]
            ],
            [
                'id' => 'testimonials',
                'type' => 'testimonial_carousel',
                'order' => 4,
                'settings' => [
                    'autoplay' => true,
                    'show_avatars' => true,
                    'items_per_view' => 2
                ]
            ],
            [
                'id' => 'faq',
                'type' => 'faq_accordion',
                'order' => 5,
                'settings' => [
                    'expand_first' => true,
                    'allow_multiple' => false
                ]
            ]
        ],
        'global_settings' => [
            'container_width' => '1200px',
            'section_spacing' => '80px',
            'mobile_spacing' => '40px'
        ]
    ];

    $layoutResult = $offerModule->configureLayout($offer['id'], $layoutData);
    echo "✓ Advanced layout configured\n";

    // Apply custom CSS
    echo "Applying custom CSS...\n";
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

    $themeService = $offerModule->themes();
    $cssResult = $themeService->addCustomCss($offer['id'], $customCss);
    echo "✓ Custom CSS applied\n";

    echo "Example 2 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Theme/Layout error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 3: UPSELL CONFIGURATION
// ===========================================

echo "=== EXAMPLE 3: UPSELL CONFIGURATION ===\n";

try {
    // Create primary upsell
    echo "Creating primary upsell...\n";

    $primaryUpsellData = [
        'name' => 'Advanced JavaScript Masterclass',
        'description' => 'Take your JavaScript skills to the next level with advanced concepts',
        'type' => 'course_addon',
        'position' => 'checkout',
        'trigger_conditions' => [
            'show_after_main_product' => true,
            'minimum_cart_value' => 100.00,
            'customer_segments' => ['intermediate_learners', 'returning_customers']
        ],
        'pricing' => [
            'original_price' => 149.99,
            'upsell_price' => 99.99,
            'discount_type' => 'fixed_amount',
            'discount_reason' => 'bundle_discount'
        ],
        'content' => [
            'headline' => 'Special Offer: Advanced JavaScript',
            'subheadline' => 'Complete your learning journey with advanced concepts',
            'bullet_points' => [
                'Advanced ES6+ features',
                'Design patterns and architecture',
                'Performance optimization',
                'Testing strategies',
                'Real-world projects'
            ],
            'cta_text' => 'Add Advanced Course',
            'decline_text' => 'No thanks, continue with basic course'
        ],
        'media' => [
            'image' => 'https://example.com/advanced-js-course.jpg',
            'video_preview' => 'https://example.com/preview-video.mp4'
        ],
        'settings' => [
            'auto_add_to_cart' => false,
            'show_timer' => true,
            'timer_duration' => 300, // 5 minutes
            'allow_quantity_change' => false,
            'max_display_count' => 3
        ]
    ];

    $primaryUpsell = $offerModule->addUpsell($offer['id'], $primaryUpsellData);
    echo "✓ Primary upsell created: {$primaryUpsell['name']}\n";

    // Create order bump
    echo "Creating order bump...\n";

    $orderBumpData = [
        'name' => 'Premium Support Package',
        'description' => '1-on-1 mentoring and priority support',
        'type' => 'service_addon',
        'position' => 'order_summary',
        'trigger_conditions' => [
            'show_probability' => 0.8, // Show to 80% of users
            'exclude_mobile' => false,
            'a_b_test_variant' => 'variant_a'
        ],
        'pricing' => [
            'original_price' => 197.00,
            'bump_price' => 97.00,
            'discount_type' => 'percentage',
            'discount_value' => 50.76
        ],
        'content' => [
            'headline' => 'Add Premium Support?',
            'description' => 'Get 1-on-1 mentoring sessions and priority email support',
            'benefits' => [
                '4 x 1-hour mentoring sessions',
                'Priority email support',
                'Code review services',
                'Career guidance'
            ],
            'cta_text' => 'Yes, Add Support Package',
            'small_print' => 'Limited time offer - 50% off regular price'
        ],
        'display' => [
            'style' => 'checkbox_simple',
            'position_in_summary' => 'before_total',
            'highlight_savings' => true
        ],
        'settings' => [
            'default_checked' => false,
            'require_explicit_acceptance' => true,
            'show_on_mobile' => true
        ]
    ];

    $orderBump = $offerModule->addUpsell($offer['id'], $orderBumpData);
    echo "✓ Order bump created: {$orderBump['name']}\n";

    // Create post-purchase upsell
    echo "Creating post-purchase upsell...\n";

    $postPurchaseData = [
        'name' => 'Complete Developer Toolkit',
        'description' => 'Essential tools and resources for professional development',
        'type' => 'tool_bundle',
        'position' => 'thank_you_page',
        'trigger_conditions' => [
            'show_after_successful_purchase' => true,
            'purchase_amount_minimum' => 200.00,
            'first_time_customer' => false
        ],
        'pricing' => [
            'original_price' => 299.99,
            'special_price' => 149.99,
            'payment_options' => [
                'one_time' => true,
                'installments' => [3, 6, 12]
            ]
        ],
        'content' => [
            'headline' => 'Complete Your Setup!',
            'subheadline' => 'Get professional tools at 50% off',
            'included_items' => [
                'Premium code editor themes',
                'Development workflow templates',
                'Project starter kits',
                'Exclusive job board access',
                'Monthly developer newsletter'
            ],
            'urgency_text' => 'This offer expires in 10 minutes!',
            'social_proof' => '2,847 developers already upgraded'
        ],
        'settings' => [
            'one_time_offer' => true,
            'expires_in_minutes' => 10,
            'show_countdown' => true,
            'allow_later_purchase' => false
        ]
    ];

    $postPurchaseUpsell = $offerModule->addUpsell($offer['id'], $postPurchaseData);
    echo "✓ Post-purchase upsell created: {$postPurchaseUpsell['name']}\n";

    // Configure upsell sequence
    echo "Configuring upsell sequence...\n";

    $upsellService = $offerModule->upsells();
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
                'auto_progress' => false,
                'conditions' => [
                    'show_if_declined_previous' => true
                ]
            ],
            [
                'upsell_id' => $postPurchaseUpsell['id'],
                'position' => 3,
                'required' => false,
                'trigger' => 'post_purchase'
            ]
        ],
        'global_settings' => [
            'max_upsells_per_customer' => 2,
            'respect_frequency_caps' => true,
            'track_performance' => true
        ]
    ];

    $sequenceResult = $upsellService->configureSequence($sequenceData);
    echo "✓ Upsell sequence configured\n";

    echo "Example 3 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Upsell configuration error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 4: SUBSCRIPTION PLANS
// ===========================================

echo "=== EXAMPLE 4: SUBSCRIPTION PLANS ===\n";

try {
    // Create basic subscription plan
    echo "Creating basic subscription plan...\n";

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
            'certification' => false,
            'priority_support' => false
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

    $subscriptionService = $offerModule->subscriptionPlans();
    $basicPlan = $subscriptionService->create($offer['id'], $basicPlanData);
    echo "✓ Basic plan created: {$basicPlan['name']}\n";

    // Create premium subscription plan
    echo "Creating premium subscription plan...\n";

    $premiumPlanData = [
        'name' => 'Premium Learning Plan',
        'description' => 'All courses, premium support, and exclusive content',
        'billing_cycle' => 'monthly',
        'pricing' => [
            'amount' => 79.99,
            'currency' => 'BRL',
            'setup_fee' => 0.00,
            'trial_period_days' => 14,
            'annual_discount' => [
                'enabled' => true,
                'discount_percentage' => 20,
                'annual_price' => 767.90 // 12 months with 20% discount
            ]
        ],
        'features' => [
            'access_to_all_courses' => true,
            'community_access' => true,
            'email_support' => true,
            'priority_support' => true,
            'monthly_webinars' => true,
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
            'welcome_bonus' => 'Starter kit with templates and tools',
            'monthly_bonus' => 'Exclusive masterclass recording',
            'loyalty_rewards' => true
        ],
        'terms' => [
            'minimum_commitment_months' => 3,
            'cancellation_period' => '30_days_notice',
            'refund_policy' => 'first_30_days_full_refund'
        ]
    ];

    $premiumPlan = $subscriptionService->create($offer['id'], $premiumPlanData);
    echo "✓ Premium plan created: {$premiumPlan['name']}\n";

    // Create enterprise plan
    echo "Creating enterprise subscription plan...\n";

    $enterprisePlanData = [
        'name' => 'Enterprise Learning Plan',
        'description' => 'Team-based learning with custom solutions',
        'billing_cycle' => 'annual',
        'pricing' => [
            'amount' => 2499.99,
            'currency' => 'BRL',
            'pricing_model' => 'per_team',
            'minimum_seats' => 10,
            'price_per_additional_seat' => 99.99,
            'setup_fee' => 500.00,
            'custom_pricing_available' => true
        ],
        'features' => [
            'all_premium_features' => true,
            'dedicated_account_manager' => true,
            'custom_branding' => true,
            'advanced_analytics' => true,
            'api_access' => true,
            'sso_integration' => true,
            'custom_content_creation' => true,
            'team_management_tools' => true
        ],
        'support' => [
            'level' => 'white_glove',
            'dedicated_support_team' => true,
            'phone_support' => true,
            'response_time_sla' => '2_hours',
            'implementation_support' => true
        ],
        'terms' => [
            'minimum_commitment_months' => 12,
            'auto_renewal' => true,
            'custom_contract' => true,
            'volume_discounts' => true
        ]
    ];

    $enterprisePlan = $subscriptionService->create($offer['id'], $enterprisePlanData);
    echo "✓ Enterprise plan created: {$enterprisePlan['name']}\n";

    // Configure plan comparison
    echo "Configuring plan comparison...\n";

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
                'premium' => 'All courses',
                'enterprise' => 'All courses + custom content'
            ],
            'Support Level' => [
                'basic' => 'Email support',
                'premium' => 'Priority email + chat',
                'enterprise' => 'Dedicated team + phone'
            ],
            'Certification' => [
                'basic' => false,
                'premium' => true,
                'enterprise' => true
            ],
            'Team Features' => [
                'basic' => false,
                'premium' => false,
                'enterprise' => true
            ]
        ]
    ];

    $comparisonResult = $subscriptionService->configureComparison($comparisonData);
    echo "✓ Plan comparison configured\n";

    echo "Example 4 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Subscription plans error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 5: PUBLIC OFFER ACCESS
// ===========================================

echo "=== EXAMPLE 5: PUBLIC OFFER ACCESS ===\n";

try {
    // Get public offer by slug
    echo "Accessing public offer by slug...\n";

    $publicOffer = $offerModule->getPublicOffer($offer['slug']);

    if ($publicOffer) {
        echo "✓ Public offer retrieved: {$publicOffer['name']}\n";
        echo "✓ Public URL: {$publicOffer['public_url']}\n";

        // Configure public offer settings
        echo "Configuring public offer settings...\n";

        $publicSettings = [
            'seo_optimization' => [
                'enable_seo' => true,
                'meta_title' => $publicOffer['seo']['meta_title'] ?? $publicOffer['name'],
                'meta_description' => $publicOffer['seo']['meta_description'] ?? $publicOffer['description'],
                'canonical_url' => "https://example.com/offer/{$publicOffer['slug']}",
                'structured_data' => true
            ],
            'social_sharing' => [
                'enable_sharing' => true,
                'platforms' => ['facebook', 'twitter', 'linkedin', 'whatsapp'],
                'og_image' => 'https://example.com/og-image.jpg',
                'twitter_card' => 'summary_large_image'
            ],
            'analytics' => [
                'track_page_views' => true,
                'track_engagement' => true,
                'google_analytics' => 'GA-123456789',
                'facebook_pixel' => 'FB-987654321'
            ],
            'security' => [
                'rate_limiting' => true,
                'ddos_protection' => true,
                'geo_restrictions' => [],
                'require_https' => true
            ]
        ];

        $publicOfferService = $offerModule->publicOffers();
        $settingsResult = $publicOfferService->updateSettings($offer['id'], $publicSettings);
        echo "✓ Public offer settings configured\n";

        // Generate shareable links
        echo "Generating shareable links...\n";

        $shareLinks = [
            'facebook' => $publicOfferService->generateShareLink($offer['id'], 'facebook'),
            'twitter' => $publicOfferService->generateShareLink($offer['id'], 'twitter'),
            'linkedin' => $publicOfferService->generateShareLink($offer['id'], 'linkedin'),
            'email' => $publicOfferService->generateShareLink($offer['id'], 'email')
        ];

        echo "✓ Share links generated for " . count($shareLinks) . " platforms\n";

        // Get public offer analytics
        echo "Retrieving public offer analytics...\n";

        $analytics = $publicOfferService->getAnalytics($offer['id'], [
            'period' => 'last_30_days',
            'metrics' => ['views', 'conversions', 'revenue', 'traffic_sources']
        ]);

        echo "✓ Analytics retrieved: {$analytics['views']} views, {$analytics['conversions']} conversions\n";

    } else {
        echo "! Public offer not found or not published\n";
    }

    echo "Example 5 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Public offer access error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 6: ADVANCED OFFER FEATURES
// ===========================================

echo "=== EXAMPLE 6: ADVANCED OFFER FEATURES ===\n";

try {
    // A/B testing setup
    echo "Setting up A/B testing...\n";

    $abTestData = [
        'name' => 'Pricing Strategy Test',
        'description' => 'Test different pricing strategies for the course offer',
        'variants' => [
            [
                'name' => 'Original Price',
                'weight' => 50,
                'changes' => [
                    'pricing.base_price' => 299.99,
                    'content.headline' => 'Premium Web Development Course'
                ]
            ],
            [
                'name' => 'Discounted Price',
                'weight' => 50,
                'changes' => [
                    'pricing.base_price' => 199.99,
                    'pricing.discount_badge' => 'Limited Time: 33% OFF!',
                    'content.headline' => 'Limited Time: Premium Web Development Course'
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

    $offerService = $offerModule->offers();
    $abTest = $offerService->createABTest($offer['id'], $abTestData);
    echo "✓ A/B test created: {$abTest['name']}\n";

    // Configure offer restrictions
    echo "Configuring offer restrictions...\n";

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
            'blocked_email_domains' => ['tempmail.com', '10minutemail.com']
        ],
        'device' => [
            'allowed_devices' => ['desktop', 'mobile', 'tablet'],
            'browser_requirements' => [
                'min_version' => ['chrome' => 90, 'firefox' => 88, 'safari' => 14]
            ]
        ]
    ];

    $restrictionsResult = $offerService->setRestrictions($offer['id'], $restrictions);
    echo "✓ Offer restrictions configured\n";

    // Setup dynamic pricing
    echo "Setting up dynamic pricing...\n";

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
                    'surge_threshold' => 10, // enrollments per hour
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

    $dynamicPricingResult = $offerService->configureDynamicPricing($offer['id'], $dynamicPricing);
    echo "✓ Dynamic pricing configured\n";

    // Configure affiliate program
    echo "Setting up affiliate program...\n";

    $affiliateProgram = [
        'enabled' => true,
        'commission_structure' => [
            'type' => 'percentage',
            'rate' => 30.0,
            'minimum_payout' => 50.00,
            'payout_schedule' => 'monthly'
        ],
        'tracking' => [
            'cookie_duration' => 30, // days
            'attribution_model' => 'first_click',
            'cross_device_tracking' => true
        ],
        'requirements' => [
            'approval_required' => true,
            'minimum_followers' => 1000,
            'content_guidelines' => true
        ],
        'materials' => [
            'banners' => true,
            'email_templates' => true,
            'social_media_content' => true,
            'product_descriptions' => true
        ]
    ];

    $affiliateResult = $offerService->setupAffiliateProgram($offer['id'], $affiliateProgram);
    echo "✓ Affiliate program configured\n";

    echo "Example 6 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Advanced features error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 7: PERFORMANCE OPTIMIZATION
// ===========================================

echo "=== EXAMPLE 7: PERFORMANCE OPTIMIZATION ===\n";

try {
    // Cache optimization
    echo "Optimizing cache strategies...\n";

    $offerService = $offerModule->offers();
    $cacheOptimization = $offerService->optimizeCache($offer['id'], [
        'strategy' => 'multi_layer',
        'layers' => [
            'browser' => ['ttl' => 3600, 'enabled' => true],
            'cdn' => ['ttl' => 7200, 'enabled' => true],
            'application' => ['ttl' => 1800, 'enabled' => true],
            'database' => ['ttl' => 3600, 'enabled' => true]
        ],
        'invalidation_triggers' => [
            'offer_update', 'pricing_change', 'content_modification'
        ]
    ]);

    echo "✓ Cache optimization completed\n";

    // Image optimization
    echo "Optimizing images...\n";

    $imageOptimization = [
        'formats' => ['webp', 'avif', 'jpg'],
        'quality' => 85,
        'responsive_sizes' => [320, 640, 1024, 1920],
        'lazy_loading' => true,
        'cdn_delivery' => true
    ];

    $imageResult = $offerService->optimizeImages($offer['id'], $imageOptimization);
    echo "✓ Images optimized: {$imageResult['processed_count']} images\n";

    // Database query optimization
    echo "Optimizing database queries...\n";

    $queryOptimization = $offerService->optimizeQueries($offer['id'], [
        'enable_eager_loading' => true,
        'cache_query_results' => true,
        'optimize_joins' => true,
        'index_recommendations' => true
    ]);

    echo "✓ Query optimization completed\n";

    // Performance monitoring setup
    echo "Setting up performance monitoring...\n";

    $performanceMonitoring = [
        'metrics' => [
            'page_load_time',
            'time_to_first_byte',
            'largest_contentful_paint',
            'cumulative_layout_shift',
            'first_input_delay'
        ],
        'thresholds' => [
            'page_load_time' => 3000, // ms
            'time_to_first_byte' => 600, // ms
            'largest_contentful_paint' => 2500 // ms
        ],
        'alerts' => [
            'email_notifications' => false, // Disabled for example
            'webhook_urls' => [],
            'performance_degradation' => true
        ]
    ];

    $monitoringResult = $offerService->setupPerformanceMonitoring($offer['id'], $performanceMonitoring);
    echo "✓ Performance monitoring configured\n";

    echo "Example 7 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Performance optimization error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 8: SECURITY AND VALIDATION
// ===========================================

echo "=== EXAMPLE 8: SECURITY AND VALIDATION ===\n";

try {
    // Security audit
    echo "Running security audit...\n";

    $offerService = $offerModule->offers();
    $securityAudit = $offerService->securityAudit($offer['id'], [
        'check_xss_vulnerabilities' => true,
        'validate_input_sanitization' => true,
        'check_csrf_protection' => true,
        'verify_ssl_configuration' => true,
        'audit_file_permissions' => true
    ]);

    echo "✓ Security audit completed: {$securityAudit['issues_found']} issues found\n";

    // Input validation and sanitization
    echo "Testing input validation...\n";

    $testInputs = [
        'name' => '<script>alert("xss")</script>Legitimate Course Name',
        'description' => 'Course description with <img src="x" onerror="alert(1)"> malicious content',
        'metadata' => [
            'instructor' => 'John\'; DROP TABLE offers; --',
            'category' => 'education'
        ]
    ];

    $sanitizedInputs = $offerService->validateAndSanitizeInput($testInputs);
    echo "✓ Input validation and sanitization completed\n";

    // CSRF protection
    echo "Implementing CSRF protection...\n";

    $csrfProtection = new CsrfProtection();
    $csrfToken = $csrfProtection->generateToken();

    // Simulate form submission with CSRF token
    $formData = [
        'offer_id' => $offer['id'],
        'action' => 'update_offer',
        'csrf_token' => $csrfToken,
        'data' => ['name' => 'Updated Course Name']
    ];

    if ($csrfProtection->validateToken($formData['csrf_token'])) {
        echo "✓ CSRF token validation passed\n";
    } else {
        echo "! CSRF token validation failed\n";
    }

    // Rate limiting
    echo "Testing rate limiting...\n";

    $rateLimitResult = $offerService->checkRateLimit($offer['id'], [
        'requests_per_minute' => 60,
        'requests_per_hour' => 1000,
        'identifier_type' => 'ip_address'
    ]);

    if ($rateLimitResult['within_limits']) {
        echo "✓ Within rate limits: {$rateLimitResult['requests_remaining']} remaining\n";
    } else {
        echo "! Rate limit exceeded\n";
    }

    echo "Example 8 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Security validation error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 9: ANALYTICS AND REPORTING
// ===========================================

echo "=== EXAMPLE 9: ANALYTICS AND REPORTING ===\n";

try {
    // Configure analytics tracking
    echo "Setting up analytics tracking...\n";

    $offerService = $offerModule->offers();
    $analyticsConfig = [
        'events' => [
            'page_view' => true,
            'offer_view' => true,
            'add_to_cart' => true,
            'checkout_started' => true,
            'purchase_completed' => true,
            'upsell_presented' => true,
            'upsell_accepted' => true
        ],
        'custom_events' => [
            'video_play' => ['trigger' => 'video_interaction'],
            'section_scroll' => ['trigger' => 'scroll_milestone'],
            'time_on_page' => ['trigger' => 'time_threshold']
        ],
        'integrations' => [
            'google_analytics' => 'GA-123456789',
            'facebook_pixel' => 'FB-987654321',
            'hotjar' => 'HJ-123456'
        ]
    ];

    $analyticsResult = $offerService->configureAnalytics($offer['id'], $analyticsConfig);
    echo "✓ Analytics tracking configured\n";

    // Generate conversion funnel report
    echo "Generating conversion funnel report...\n";

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

    echo "✓ Conversion funnel generated:\n";
    foreach ($funnelReport['steps'] as $step) {
        echo "  - {$step['name']}: {$step['count']} ({$step['conversion_rate']}%)\n";
    }

    // A/B test performance report
    echo "Generating A/B test performance report...\n";

    $abTestReport = $offerService->getABTestReport($abTest['id'], [
        'metrics' => ['conversion_rate', 'revenue_per_visitor', 'average_order_value'],
        'statistical_significance' => true
    ]);

    echo "✓ A/B test report generated\n";
    foreach ($abTestReport['variants'] as $variant) {
        echo "  - {$variant['name']}: {$variant['conversion_rate']}% conversion\n";
    }

    // Revenue analytics
    echo "Generating revenue analytics...\n";

    $revenueReport = $offerService->getRevenueAnalytics($offer['id'], [
        'period' => 'last_90_days',
        'granularity' => 'daily',
        'include_refunds' => true,
        'segment_by' => ['traffic_source', 'device_type', 'geographic_region']
    ]);

    echo "✓ Revenue analytics generated:\n";
    echo "  - Total revenue: {$revenueReport['total_revenue']}\n";
    echo "  - Average order value: {$revenueReport['average_order_value']}\n";
    echo "  - Total transactions: {$revenueReport['transaction_count']}\n";

    echo "Example 9 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Analytics and reporting error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// EXAMPLE 10: ERROR HANDLING AND RECOVERY
// ===========================================

echo "=== EXAMPLE 10: ERROR HANDLING AND RECOVERY ===\n";

try {
    // Graceful error handling
    echo "Demonstrating graceful error handling...\n";

    $offerService = $offerModule->offers();

    // Simulate API timeout
    try {
        $result = $offerService->findWithTimeout($offer['id'], 1); // 1 second timeout
    } catch (HttpException $e) {
        echo "! API timeout handled: {$e->getMessage()}\n";

        // Implement retry logic
        echo "Retrying with exponential backoff...\n";
        $retryResult = $offerService->findWithRetry($offer['id'], [
            'max_attempts' => 3,
            'initial_delay' => 1000,
            'backoff_multiplier' => 2
        ]);
        echo "✓ Operation succeeded after retry\n";
    }

    // Data consistency validation
    echo "Validating data consistency...\n";

    $consistencyCheck = $offerService->validateConsistency($offer['id']);
    if ($consistencyCheck['consistent']) {
        echo "✓ Offer data is consistent\n";
    } else {
        echo "! Data inconsistency detected:\n";
        foreach ($consistencyCheck['issues'] as $issue) {
            echo "  - {$issue}\n";
        }

        // Attempt automatic repair
        echo "Attempting automatic data repair...\n";
        $repairResult = $offerService->repairData($offer['id']);
        echo "✓ Data repair completed\n";
    }

    // Backup and restore functionality
    echo "Creating offer backup...\n";

    $backupData = $offerService->createBackup($offer['id'], [
        'include_analytics' => false,
        'include_media' => true,
        'compress' => true
    ]);

    echo "✓ Backup created: {$backupData['backup_id']}\n";

    // Simulate data corruption and restore
    echo "Simulating data restoration...\n";

    $restoreResult = $offerService->restoreFromBackup($offer['id'], $backupData['backup_id'], [
        'overwrite_existing' => false,
        'validate_before_restore' => true
    ]);

    echo "✓ Data restoration simulated successfully\n";

    echo "Example 10 completed successfully!\n\n";

} catch (Exception $e) {
    echo "✗ Error handling demonstration error: " . $e->getMessage() . "\n\n";
}

// ===========================================
// CLEANUP AND BEST PRACTICES
// ===========================================

echo "=== CLEANUP AND BEST PRACTICES ===\n";

try {
    // Archive offer instead of deleting
    echo "Archiving offer...\n";

    $offerService = $offerModule->offers();
    $archiveResult = $offerService->archive($offer['id'], [
        'reason' => 'example_completion',
        'retain_analytics' => true,
        'preserve_public_access' => false
    ]);

    echo "✓ Offer archived successfully\n";

    // Generate performance summary
    echo "Generating performance summary...\n";

    $performanceSummary = $offerService->getPerformanceSummary();
    echo "✓ Performance Summary:\n";
    echo "  - Total API calls: {$performanceSummary['total_api_calls']}\n";
    echo "  - Average response time: {$performanceSummary['avg_response_time']}ms\n";
    echo "  - Cache hit ratio: {$performanceSummary['cache_hit_ratio']}%\n";
    echo "  - Error rate: {$performanceSummary['error_rate']}%\n";

    // Module status check
    $moduleStatus = $offerModule->getStatus();
    echo "✓ Module status: " . ($moduleStatus['available'] ? 'Available' : 'Unavailable') . "\n";

    echo "\n=== BEST PRACTICES SUMMARY ===\n";
    echo "1. Always validate and sanitize user input\n";
    echo "2. Implement comprehensive error handling and retry logic\n";
    echo "3. Use appropriate caching strategies for different content types\n";
    echo "4. Configure proper security measures (CSRF, rate limiting)\n";
    echo "5. Monitor performance metrics and optimize accordingly\n";
    echo "6. Implement A/B testing for optimization\n";
    echo "7. Use analytics to track user behavior and conversions\n";
    echo "8. Archive data instead of deleting when possible\n";
    echo "9. Implement proper backup and restore procedures\n";
    echo "10. Follow SEO best practices for public offers\n";

    echo "\nAll examples completed successfully!\n";

} catch (Exception $e) {
    echo "✗ Cleanup error: " . $e->getMessage() . "\n";
} finally {
    // Final cleanup
    if (isset($offerModule)) {
        $offerModule->cleanup();
    }
    echo "✓ Final cleanup completed\n";
}

echo "\n=== END OF OFFER MODULE EXAMPLES ===\n";

/**
 * ADDITIONAL INTEGRATION EXAMPLES
 *
 * For real-world applications, consider these integration patterns:
 *
 * 1. Content Management Integration:
 *    - Dynamic content updates
 *    - Version control for offers
 *    - Multi-language support
 *    - Rich media management
 *
 * 2. Marketing Automation:
 *    - Email campaign integration
 *    - Social media automation
 *    - Lead nurturing workflows
 *    - Customer segmentation
 *
 * 3. Sales Funnel Optimization:
 *    - Multi-step funnels
 *    - Exit-intent offers
 *    - Retargeting campaigns
 *    - Conversion optimization
 *
 * 4. Business Intelligence:
 *    - Advanced analytics
 *    - Predictive modeling
 *    - Customer lifetime value
 *    - Market segmentation
 *
 * 5. Third-party Integrations:
 *    - CRM systems
 *    - Payment processors
 *    - Email marketing platforms
 *    - Analytics tools
 */