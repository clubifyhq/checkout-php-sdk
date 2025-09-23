<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Cache;

/**
 * Cache Strategies for Clubify SDK
 *
 * Defines cache duration policies and strategies for different types of data
 * Based on access patterns, data volatility, and performance requirements.
 *
 * Cache Strategy Implementation:
 * - Public Offer Cache: 7200s (2 hours) for public offers
 * - Cart Session Cache: 1800s (30 min) for active cart sessions
 * - Admin Operations Cache: 3600s (1 hour) for admin operations
 * - Navigation Flow Cache: 900s (15 min) for flow navigation
 * - Promotion Cache: 3600s (1 hour) for promotion rules
 */
class CacheStrategies
{
    // ===============================
    // PUBLIC CACHE STRATEGIES
    // ===============================

    /**
     * Public offer cache strategy (2 hours)
     * - Used for public offer pages, themes, layouts
     * - High cache duration due to low volatility
     * - Can be safely cached for extended periods
     */
    public const PUBLIC_OFFER_CACHE = 7200; // 2 hours

    /**
     * Public offer theme cache (4 hours)
     * - Themes change very rarely
     * - Can be cached longer than content
     */
    public const PUBLIC_OFFER_THEME_CACHE = 14400; // 4 hours

    /**
     * Public offer layout cache (4 hours)
     * - Layouts are structural and change rarely
     */
    public const PUBLIC_OFFER_LAYOUT_CACHE = 14400; // 4 hours

    /**
     * SEO data cache (2 hours)
     * - Meta tags, descriptions, structured data
     * - Moderate cache duration
     */
    public const SEO_DATA_CACHE = 7200; // 2 hours

    // ===============================
    // CART SESSION STRATEGIES
    // ===============================

    /**
     * Active cart session cache (30 minutes)
     * - For active user sessions
     * - Balance between performance and data freshness
     */
    public const CART_SESSION_CACHE = 1800; // 30 minutes

    /**
     * Cart items cache (30 minutes)
     * - Individual cart items
     * - Same as session for consistency
     */
    public const CART_ITEMS_CACHE = 1800; // 30 minutes

    /**
     * Cart totals cache (5 minutes)
     * - Calculated values that can change frequently
     * - Shorter cache for accuracy
     */
    public const CART_TOTALS_CACHE = 300; // 5 minutes

    /**
     * Cart calculations cache (10 minutes)
     * - Complex calculations (taxes, shipping, discounts)
     * - Medium cache for performance vs accuracy balance
     */
    public const CART_CALCULATIONS_CACHE = 600; // 10 minutes

    // ===============================
    // ADMIN OPERATIONS STRATEGIES
    // ===============================

    /**
     * Admin operations cache (1 hour)
     * - Admin dashboard data, reports
     * - Medium duration for admin efficiency
     */
    public const ADMIN_OPERATIONS_CACHE = 3600; // 1 hour

    /**
     * Admin statistics cache (30 minutes)
     * - Performance metrics, conversion rates
     * - More frequent updates for accuracy
     */
    public const ADMIN_STATISTICS_CACHE = 1800; // 30 minutes

    /**
     * Admin configuration cache (2 hours)
     * - Settings that change infrequently
     */
    public const ADMIN_CONFIG_CACHE = 7200; // 2 hours

    // ===============================
    // NAVIGATION FLOW STRATEGIES
    // ===============================

    /**
     * Navigation flow cache (15 minutes)
     * - User flow through checkout process
     * - Short cache for real-time tracking
     */
    public const NAVIGATION_FLOW_CACHE = 900; // 15 minutes

    /**
     * Navigation state cache (5 minutes)
     * - Current user position in flow
     * - Very short for accurate tracking
     */
    public const NAVIGATION_STATE_CACHE = 300; // 5 minutes

    /**
     * Flow configuration cache (1 hour)
     * - Flow definitions and rules
     * - Longer cache as these are stable
     */
    public const FLOW_CONFIG_CACHE = 3600; // 1 hour

    // ===============================
    // PROMOTION STRATEGIES
    // ===============================

    /**
     * Promotion rules cache (1 hour)
     * - Discount rules, coupon logic
     * - Medium duration for rule stability
     */
    public const PROMOTION_CACHE = 3600; // 1 hour

    /**
     * Active promotions cache (30 minutes)
     * - Currently active promotions
     * - Shorter cache for availability accuracy
     */
    public const ACTIVE_PROMOTIONS_CACHE = 1800; // 30 minutes

    /**
     * Promotion validation cache (10 minutes)
     * - Coupon validation results
     * - Short cache to prevent stale validations
     */
    public const PROMOTION_VALIDATION_CACHE = 600; // 10 minutes

    // ===============================
    // PRODUCT AND CATALOG STRATEGIES
    // ===============================

    /**
     * Product catalog cache (2 hours)
     * - Product listings, categories
     * - Medium-long cache for catalog stability
     */
    public const PRODUCT_CATALOG_CACHE = 7200; // 2 hours

    /**
     * Product details cache (1 hour)
     * - Individual product information
     * - Medium cache for detail accuracy
     */
    public const PRODUCT_DETAILS_CACHE = 3600; // 1 hour

    /**
     * Product recommendations cache (30 minutes)
     * - Upsells, cross-sells, recommendations
     * - Shorter cache for personalization accuracy
     */
    public const PRODUCT_RECOMMENDATIONS_CACHE = 1800; // 30 minutes

    // ===============================
    // ANALYTICS AND TRACKING STRATEGIES
    // ===============================

    /**
     * Analytics data cache (10 minutes)
     * - View tracking, interaction data
     * - Short cache for real-time analytics
     */
    public const ANALYTICS_CACHE = 600; // 10 minutes

    /**
     * Performance metrics cache (5 minutes)
     * - System performance data
     * - Very short for monitoring accuracy
     */
    public const PERFORMANCE_METRICS_CACHE = 300; // 5 minutes

    /**
     * Event tracking cache (5 minutes)
     * - User events, conversions
     * - Short cache for event accuracy
     */
    public const EVENT_TRACKING_CACHE = 300; // 5 minutes

    // ===============================
    // API RESPONSE STRATEGIES
    // ===============================

    /**
     * API response cache (15 minutes)
     * - General API responses
     * - Medium-short cache for API efficiency
     */
    public const API_RESPONSE_CACHE = 900; // 15 minutes

    /**
     * External API cache (1 hour)
     * - Third-party API responses
     * - Longer cache to reduce external calls
     */
    public const EXTERNAL_API_CACHE = 3600; // 1 hour

    /**
     * Configuration API cache (2 hours)
     * - Configuration endpoints
     * - Long cache for stable config data
     */
    public const CONFIG_API_CACHE = 7200; // 2 hours

    // ===============================
    // UTILITY METHODS
    // ===============================

    /**
     * Get cache strategy by context
     */
    public static function getStrategy(string $context, string $type = 'default'): int
    {
        $strategies = [
            'public_offer' => [
                'default' => self::PUBLIC_OFFER_CACHE,
                'theme' => self::PUBLIC_OFFER_THEME_CACHE,
                'layout' => self::PUBLIC_OFFER_LAYOUT_CACHE,
                'seo' => self::SEO_DATA_CACHE,
            ],
            'cart' => [
                'default' => self::CART_SESSION_CACHE,
                'session' => self::CART_SESSION_CACHE,
                'items' => self::CART_ITEMS_CACHE,
                'totals' => self::CART_TOTALS_CACHE,
                'calculations' => self::CART_CALCULATIONS_CACHE,
            ],
            'admin' => [
                'default' => self::ADMIN_OPERATIONS_CACHE,
                'operations' => self::ADMIN_OPERATIONS_CACHE,
                'statistics' => self::ADMIN_STATISTICS_CACHE,
                'config' => self::ADMIN_CONFIG_CACHE,
            ],
            'navigation' => [
                'default' => self::NAVIGATION_FLOW_CACHE,
                'flow' => self::NAVIGATION_FLOW_CACHE,
                'state' => self::NAVIGATION_STATE_CACHE,
                'config' => self::FLOW_CONFIG_CACHE,
            ],
            'promotion' => [
                'default' => self::PROMOTION_CACHE,
                'rules' => self::PROMOTION_CACHE,
                'active' => self::ACTIVE_PROMOTIONS_CACHE,
                'validation' => self::PROMOTION_VALIDATION_CACHE,
            ],
            'product' => [
                'default' => self::PRODUCT_DETAILS_CACHE,
                'catalog' => self::PRODUCT_CATALOG_CACHE,
                'details' => self::PRODUCT_DETAILS_CACHE,
                'recommendations' => self::PRODUCT_RECOMMENDATIONS_CACHE,
            ],
            'analytics' => [
                'default' => self::ANALYTICS_CACHE,
                'data' => self::ANALYTICS_CACHE,
                'metrics' => self::PERFORMANCE_METRICS_CACHE,
                'events' => self::EVENT_TRACKING_CACHE,
            ],
            'api' => [
                'default' => self::API_RESPONSE_CACHE,
                'response' => self::API_RESPONSE_CACHE,
                'external' => self::EXTERNAL_API_CACHE,
                'config' => self::CONFIG_API_CACHE,
            ],
        ];

        return $strategies[$context][$type] ?? $strategies[$context]['default'] ?? self::API_RESPONSE_CACHE;
    }

    /**
     * Get all strategies grouped by context
     */
    public static function getAllStrategies(): array
    {
        return [
            'public_offer' => [
                'default_ttl' => self::PUBLIC_OFFER_CACHE,
                'theme_ttl' => self::PUBLIC_OFFER_THEME_CACHE,
                'layout_ttl' => self::PUBLIC_OFFER_LAYOUT_CACHE,
                'seo_ttl' => self::SEO_DATA_CACHE,
                'description' => 'Public facing offer content with high cache duration',
            ],
            'cart' => [
                'session_ttl' => self::CART_SESSION_CACHE,
                'items_ttl' => self::CART_ITEMS_CACHE,
                'totals_ttl' => self::CART_TOTALS_CACHE,
                'calculations_ttl' => self::CART_CALCULATIONS_CACHE,
                'description' => 'Cart data with medium cache for session management',
            ],
            'admin' => [
                'operations_ttl' => self::ADMIN_OPERATIONS_CACHE,
                'statistics_ttl' => self::ADMIN_STATISTICS_CACHE,
                'config_ttl' => self::ADMIN_CONFIG_CACHE,
                'description' => 'Admin interface data with balanced cache duration',
            ],
            'navigation' => [
                'flow_ttl' => self::NAVIGATION_FLOW_CACHE,
                'state_ttl' => self::NAVIGATION_STATE_CACHE,
                'config_ttl' => self::FLOW_CONFIG_CACHE,
                'description' => 'Navigation flow tracking with short cache for accuracy',
            ],
            'promotion' => [
                'rules_ttl' => self::PROMOTION_CACHE,
                'active_ttl' => self::ACTIVE_PROMOTIONS_CACHE,
                'validation_ttl' => self::PROMOTION_VALIDATION_CACHE,
                'description' => 'Promotion system with varied cache based on volatility',
            ],
            'product' => [
                'catalog_ttl' => self::PRODUCT_CATALOG_CACHE,
                'details_ttl' => self::PRODUCT_DETAILS_CACHE,
                'recommendations_ttl' => self::PRODUCT_RECOMMENDATIONS_CACHE,
                'description' => 'Product data with medium-long cache duration',
            ],
            'analytics' => [
                'data_ttl' => self::ANALYTICS_CACHE,
                'metrics_ttl' => self::PERFORMANCE_METRICS_CACHE,
                'events_ttl' => self::EVENT_TRACKING_CACHE,
                'description' => 'Analytics data with short cache for real-time insights',
            ],
            'api' => [
                'response_ttl' => self::API_RESPONSE_CACHE,
                'external_ttl' => self::EXTERNAL_API_CACHE,
                'config_ttl' => self::CONFIG_API_CACHE,
                'description' => 'API responses with varied cache based on source',
            ],
        ];
    }

    /**
     * Get cache strategy recommendations based on data characteristics
     */
    public static function recommendStrategy(array $characteristics): int
    {
        $volatility = $characteristics['volatility'] ?? 'medium'; // low, medium, high
        $importance = $characteristics['importance'] ?? 'medium'; // low, medium, high
        $size = $characteristics['size'] ?? 'medium'; // small, medium, large
        $accessPattern = $characteristics['access_pattern'] ?? 'mixed'; // frequent, occasional, rare, mixed

        // High volatility = shorter cache
        if ($volatility === 'high') {
            return ($importance === 'high') ? 300 : 600; // 5-10 minutes
        }

        // Low volatility = longer cache
        if ($volatility === 'low') {
            return ($accessPattern === 'frequent') ? 7200 : 14400; // 2-4 hours
        }

        // Medium volatility = balanced cache
        if ($importance === 'high') {
            return 1800; // 30 minutes
        }

        return 3600; // 1 hour default
    }

    /**
     * Get warming strategies for different contexts
     */
    public static function getWarmingStrategies(): array
    {
        return [
            'public_offers' => [
                'priority' => 'high',
                'patterns' => ['public_offer:*', 'public_offer_theme:*'],
                'schedule' => 'every_hour',
                'description' => 'Warm public offer data for fast page loads',
            ],
            'popular_products' => [
                'priority' => 'medium',
                'patterns' => ['product_details:popular:*', 'product_catalog:featured'],
                'schedule' => 'every_30_minutes',
                'description' => 'Pre-cache popular product data',
            ],
            'active_promotions' => [
                'priority' => 'high',
                'patterns' => ['promotion:active:*', 'promotion_validation:*'],
                'schedule' => 'every_15_minutes',
                'description' => 'Keep active promotions in cache',
            ],
            'navigation_flows' => [
                'priority' => 'medium',
                'patterns' => ['flow_config:*', 'navigation_flow:common:*'],
                'schedule' => 'every_hour',
                'description' => 'Pre-cache common navigation flows',
            ],
        ];
    }

    /**
     * Get cache invalidation strategies
     */
    public static function getInvalidationStrategies(): array
    {
        return [
            'offer_updated' => [
                'patterns' => ['public_offer:*', 'offer_recommendations:*'],
                'cascade' => true,
                'description' => 'Invalidate offer caches when offer is updated',
            ],
            'cart_modified' => [
                'patterns' => ['cart_totals:*', 'cart_calculations:*'],
                'cascade' => false,
                'description' => 'Invalidate calculated values when cart changes',
            ],
            'promotion_changed' => [
                'patterns' => ['promotion:*', 'active_promotions:*', 'cart_totals:*'],
                'cascade' => true,
                'description' => 'Clear promotion and related caches',
            ],
            'product_updated' => [
                'patterns' => ['product_details:*', 'product_catalog:*', 'recommendations:*'],
                'cascade' => true,
                'description' => 'Invalidate product-related caches',
            ],
        ];
    }
}