# Cross-Module Integration Examples

## Table of Contents

1. [Overview](#overview)
2. [Cart + Offer Integration](#cart--offer-integration)
3. [E-commerce Workflows](#e-commerce-workflows)
4. [Subscription Management](#subscription-management)
5. [Multi-Step Funnels](#multi-step-funnels)
6. [Advanced Integration Patterns](#advanced-integration-patterns)
7. [Performance Optimization](#performance-optimization)
8. [Error Handling Strategies](#error-handling-strategies)
9. [Best Practices](#best-practices)
10. [Real-World Use Cases](#real-world-use-cases)

## Overview

This guide demonstrates how to integrate the Cart and Offer modules to create comprehensive e-commerce and subscription solutions. The examples show real-world scenarios combining both modules for maximum effectiveness.

### Integration Benefits

- **Seamless User Experience**: Smooth transitions between offer pages and cart
- **Conversion Optimization**: Leverage upsells and flow navigation together
- **Data Consistency**: Shared data models and validation
- **Performance**: Optimized caching and batch operations
- **Analytics**: Unified tracking across the entire customer journey

## Cart + Offer Integration

### Basic Integration Setup

```php
use Clubify\Checkout\ClubifyCheckoutSDK;
use Clubify\Checkout\Core\Config\Configuration;

// Initialize SDK with both modules
$config = new Configuration([
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'environment' => 'sandbox',
    'cache' => [
        'driver' => 'redis',
        'ttl' => 1800
    ]
]);

$sdk = new ClubifyCheckoutSDK($config);

// Get both modules
$cartModule = $sdk->cart();
$offerModule = $sdk->offer();
```

### Offer-to-Cart Workflow

```php
/**
 * Complete workflow from offer view to cart creation
 */
class OfferToCartWorkflow
{
    private $cartModule;
    private $offerModule;

    public function __construct($cartModule, $offerModule)
    {
        $this->cartModule = $cartModule;
        $this->offerModule = $offerModule;
    }

    public function processOfferPurchase($offerSlug, $sessionId, $customerData = [])
    {
        try {
            // 1. Get public offer details
            $offer = $this->offerModule->getPublicOffer($offerSlug);

            if (!$offer) {
                throw new Exception("Offer not found: {$offerSlug}");
            }

            // 2. Validate offer availability
            $availability = $this->offerModule->offers()->checkAvailability($offer['id']);

            if (!$availability['available']) {
                throw new Exception("Offer not available: {$availability['reason']}");
            }

            // 3. Create or get existing cart
            $cart = $this->cartModule->findBySession($sessionId);

            if (!$cart) {
                $cart = $this->cartModule->create($sessionId, [
                    'customer_id' => $customerData['customer_id'] ?? null,
                    'currency' => $offer['pricing']['currency'],
                    'type' => $this->determineCartType($offer),
                    'metadata' => [
                        'source_offer' => $offer['id'],
                        'offer_slug' => $offerSlug,
                        'utm_source' => $_GET['utm_source'] ?? null,
                        'utm_campaign' => $_GET['utm_campaign'] ?? null
                    ]
                ]);
            }

            // 4. Add main offer to cart
            $mainItem = $this->addOfferToCart($cart['id'], $offer);

            // 5. Process upsells if configured
            $upsells = $this->processUpsells($cart['id'], $offer['id']);

            // 6. Apply automatic promotions
            $promotions = $this->applyAutomaticPromotions($cart['id'], $offer);

            // 7. Calculate final totals
            $finalCart = $this->cartModule->cart()->calculateTotals($cart['id']);

            return [
                'success' => true,
                'cart' => $finalCart,
                'main_item' => $mainItem,
                'upsells' => $upsells,
                'promotions' => $promotions,
                'next_step' => $this->determineNextStep($finalCart, $offer)
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback_action' => $this->getFallbackAction($offerSlug)
            ];
        }
    }

    private function addOfferToCart($cartId, $offer)
    {
        // Convert offer to cart item
        $itemData = [
            'product_id' => $offer['id'],
            'name' => $offer['name'],
            'price' => $offer['pricing']['current_price'] ?? $offer['pricing']['base_price'],
            'quantity' => 1,
            'type' => $offer['type'],
            'metadata' => [
                'offer_id' => $offer['id'],
                'original_price' => $offer['pricing']['base_price'],
                'discount_applied' => $offer['pricing']['discount_price'] ?? null,
                'offer_details' => $offer['product_details'] ?? []
            ]
        ];

        // Add subscription details if applicable
        if ($offer['type'] === 'subscription') {
            $itemData['subscription_details'] = [
                'billing_cycle' => $offer['subscription']['billing_cycle'],
                'trial_period' => $offer['subscription']['trial_period_days'] ?? 0
            ];
        }

        return $this->cartModule->addItem($cartId, $itemData);
    }

    private function processUpsells($cartId, $offerId)
    {
        // Get configured upsells for the offer
        $upsells = $this->offerModule->upsells()->getByOffer($offerId);
        $addedUpsells = [];

        foreach ($upsells as $upsell) {
            // Check upsell conditions
            if ($this->shouldShowUpsell($cartId, $upsell)) {
                // For order bumps, auto-add if configured
                if ($upsell['type'] === 'order_bump' && $upsell['settings']['auto_add']) {
                    $upsellItem = $this->cartModule->addItem($cartId, [
                        'product_id' => $upsell['id'],
                        'name' => $upsell['name'],
                        'price' => $upsell['pricing']['upsell_price'],
                        'quantity' => 1,
                        'type' => 'upsell',
                        'metadata' => [
                            'upsell_id' => $upsell['id'],
                            'parent_offer_id' => $offerId,
                            'upsell_type' => $upsell['type']
                        ]
                    ]);

                    $addedUpsells[] = $upsellItem;
                }
            }
        }

        return $addedUpsells;
    }

    private function applyAutomaticPromotions($cartId, $offer)
    {
        $appliedPromotions = [];

        // Check for offer-specific promotions
        if (!empty($offer['automatic_promotions'])) {
            foreach ($offer['automatic_promotions'] as $promoCode) {
                try {
                    $result = $this->cartModule->applyPromotion($cartId, $promoCode);
                    $appliedPromotions[] = $result['promotion'];
                } catch (Exception $e) {
                    // Log promotion failure but continue
                    error_log("Failed to apply automatic promotion {$promoCode}: " . $e->getMessage());
                }
            }
        }

        return $appliedPromotions;
    }

    private function determineCartType($offer)
    {
        return match($offer['type']) {
            'subscription' => 'subscription',
            'course' => 'education',
            'digital_product' => 'digital',
            'service' => 'service',
            default => 'standard'
        };
    }

    private function shouldShowUpsell($cartId, $upsell)
    {
        // Get current cart details
        $cart = $this->cartModule->find($cartId);

        // Check minimum cart value
        if (isset($upsell['trigger_conditions']['minimum_cart_value'])) {
            if ($cart['totals']['subtotal'] < $upsell['trigger_conditions']['minimum_cart_value']) {
                return false;
            }
        }

        // Check customer segments
        if (!empty($upsell['trigger_conditions']['customer_segments'])) {
            $customerSegment = $this->getCustomerSegment($cart['customer_id']);
            if (!in_array($customerSegment, $upsell['trigger_conditions']['customer_segments'])) {
                return false;
            }
        }

        return true;
    }

    private function determineNextStep($cart, $offer)
    {
        // Determine the next step in the flow
        if ($offer['configuration']['require_immediate_payment'] ?? false) {
            return 'checkout';
        }

        if (!empty($cart['items']) && count($cart['items']) > 1) {
            return 'review_cart';
        }

        return 'customize_order';
    }
}
```

## E-commerce Workflows

### Product Catalog Integration

```php
/**
 * E-commerce integration with product catalog
 */
class ECommerceIntegration
{
    private $cartModule;
    private $offerModule;
    private $productCatalog;

    public function createProductOffer($productId, $campaignId = null)
    {
        // Get product from catalog
        $product = $this->productCatalog->getProduct($productId);

        // Create offer based on product
        $offerData = [
            'name' => $product['name'],
            'slug' => $this->generateSlug($product['name']),
            'description' => $product['description'],
            'type' => 'physical_product',
            'pricing' => [
                'base_price' => $product['price'],
                'currency' => $product['currency']
            ],
            'product_details' => [
                'sku' => $product['sku'],
                'weight' => $product['weight'],
                'dimensions' => $product['dimensions'],
                'category' => $product['category'],
                'brand' => $product['brand']
            ],
            'inventory' => [
                'track_inventory' => true,
                'stock_quantity' => $product['stock_quantity'],
                'allow_backorders' => $product['allow_backorders']
            ],
            'shipping' => [
                'requires_shipping' => true,
                'shipping_class' => $product['shipping_class'],
                'free_shipping_threshold' => 100.00
            ]
        ];

        // Apply campaign-specific modifications
        if ($campaignId) {
            $campaign = $this->getCampaign($campaignId);
            $offerData = $this->applyCampaignSettings($offerData, $campaign);
        }

        $offer = $this->offerModule->createOffer($offerData);

        // Configure cross-sells and upsells
        $this->setupProductUpsells($offer['id'], $product);

        return $offer;
    }

    public function addProductsToCart($sessionId, $products, $shippingAddress = null)
    {
        // Create or get cart
        $cart = $this->cartModule->findBySession($sessionId);
        if (!$cart) {
            $cart = $this->cartModule->create($sessionId, [
                'type' => 'ecommerce',
                'currency' => 'BRL'
            ]);
        }

        $addedItems = [];

        foreach ($products as $productData) {
            // Check inventory
            if (!$this->checkInventory($productData['product_id'], $productData['quantity'])) {
                throw new Exception("Insufficient inventory for product {$productData['product_id']}");
            }

            // Get product details
            $product = $this->productCatalog->getProduct($productData['product_id']);

            // Add to cart
            $item = $this->cartModule->addItem($cart['id'], [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $productData['quantity'],
                'sku' => $product['sku'],
                'metadata' => [
                    'weight' => $product['weight'],
                    'dimensions' => $product['dimensions'],
                    'category' => $product['category']
                ]
            ]);

            $addedItems[] = $item;
        }

        // Calculate shipping if address provided
        if ($shippingAddress) {
            $shipping = $this->calculateShipping($cart['id'], $shippingAddress);
            $this->cartModule->cart()->update($cart['id'], [
                'shipping_address' => $shippingAddress,
                'shipping_options' => $shipping['options'],
                'selected_shipping' => $shipping['recommended']
            ]);
        }

        // Apply volume discounts
        $this->applyVolumeDiscounts($cart['id']);

        return [
            'cart' => $this->cartModule->find($cart['id']),
            'added_items' => $addedItems,
            'shipping_options' => $shipping['options'] ?? null
        ];
    }

    private function setupProductUpsells($offerId, $product)
    {
        // Get related products for upselling
        $relatedProducts = $this->productCatalog->getRelatedProducts($product['id']);

        foreach ($relatedProducts as $related) {
            if ($related['relationship_type'] === 'cross_sell') {
                // Create cross-sell upsell
                $this->offerModule->addUpsell($offerId, [
                    'name' => "Add {$related['name']}",
                    'description' => "Complete your purchase with {$related['name']}",
                    'type' => 'cross_sell',
                    'position' => 'cart_summary',
                    'pricing' => [
                        'original_price' => $related['price'],
                        'bundle_price' => $related['price'] * 0.9 // 10% discount
                    ],
                    'product_details' => [
                        'product_id' => $related['id'],
                        'sku' => $related['sku']
                    ]
                ]);
            }
        }
    }
}
```

### Checkout Flow Integration

```php
/**
 * Complete checkout flow with cart and offer integration
 */
class CheckoutFlowIntegration
{
    public function processCheckout($cartId, $checkoutData)
    {
        try {
            // 1. Validate cart
            $cart = $this->validateCart($cartId);

            // 2. Process customer information
            $customer = $this->processCustomerData($checkoutData['customer']);

            // 3. Handle shipping (if required)
            $shipping = null;
            if ($this->cartRequiresShipping($cart)) {
                $shipping = $this->processShipping($cartId, $checkoutData['shipping']);
            }

            // 4. Apply final promotions
            $this->applyCheckoutPromotions($cartId, $checkoutData['promotions'] ?? []);

            // 5. Process payment
            $payment = $this->processPayment($cartId, $checkoutData['payment']);

            // 6. Create order
            $order = $this->createOrder($cartId, $customer, $shipping, $payment);

            // 7. Process post-purchase upsells
            $postPurchaseUpsells = $this->processPostPurchaseUpsells($order);

            // 8. Clear cart
            $this->cartModule->cart()->clear($cartId);

            return [
                'success' => true,
                'order' => $order,
                'customer' => $customer,
                'payment' => $payment,
                'post_purchase_upsells' => $postPurchaseUpsells
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'cart_id' => $cartId
            ];
        }
    }

    private function processPostPurchaseUpsells($order)
    {
        $upsells = [];

        // Get upsells for purchased items
        foreach ($order['items'] as $item) {
            if (isset($item['metadata']['offer_id'])) {
                $offerUpsells = $this->offerModule->upsells()->getPostPurchase($item['metadata']['offer_id']);

                foreach ($offerUpsells as $upsell) {
                    if ($this->shouldShowPostPurchaseUpsell($order, $upsell)) {
                        $upsells[] = [
                            'upsell' => $upsell,
                            'purchase_link' => $this->generateUpsellPurchaseLink($order['id'], $upsell['id']),
                            'expires_at' => date('Y-m-d H:i:s', strtotime('+10 minutes'))
                        ];
                    }
                }
            }
        }

        return $upsells;
    }
}
```

## Subscription Management

### Subscription Offer to Cart Integration

```php
/**
 * Subscription-based integration
 */
class SubscriptionIntegration
{
    public function createSubscriptionFromOffer($offerSlug, $planId, $customerId)
    {
        // Get subscription offer
        $offer = $this->offerModule->getPublicOffer($offerSlug);

        if ($offer['type'] !== 'subscription') {
            throw new Exception("Offer is not a subscription type");
        }

        // Get specific plan
        $plan = $this->offerModule->subscriptionPlans()->find($planId);

        // Create subscription cart
        $sessionId = 'subscription_' . uniqid();
        $cart = $this->cartModule->create($sessionId, [
            'customer_id' => $customerId,
            'type' => 'subscription',
            'currency' => $plan['pricing']['currency'],
            'subscription_config' => [
                'billing_cycle' => $plan['billing_cycle'],
                'trial_period_days' => $plan['pricing']['trial_period_days'] ?? 0,
                'auto_renewal' => true
            ]
        ]);

        // Add subscription plan as cart item
        $this->cartModule->addItem($cart['id'], [
            'product_id' => $plan['id'],
            'name' => $plan['name'],
            'price' => $plan['pricing']['amount'],
            'quantity' => 1,
            'type' => 'subscription_plan',
            'subscription_details' => [
                'plan_id' => $plan['id'],
                'billing_cycle' => $plan['billing_cycle'],
                'features' => $plan['features'],
                'limits' => $plan['limits'],
                'trial_end_date' => $this->calculateTrialEndDate($plan),
                'next_billing_date' => $this->calculateNextBillingDate($plan)
            ]
        ]);

        // Add plan-specific addons
        $this->addPlanAddons($cart['id'], $plan);

        return [
            'cart' => $this->cartModule->find($cart['id']),
            'plan' => $plan,
            'offer' => $offer
        ];
    }

    public function upgradeSubscription($currentPlanId, $newPlanId, $customerId)
    {
        $currentPlan = $this->offerModule->subscriptionPlans()->find($currentPlanId);
        $newPlan = $this->offerModule->subscriptionPlans()->find($newPlanId);

        // Calculate proration
        $proration = $this->calculateProration($currentPlan, $newPlan, $customerId);

        // Create upgrade cart
        $sessionId = 'upgrade_' . uniqid();
        $cart = $this->cartModule->create($sessionId, [
            'customer_id' => $customerId,
            'type' => 'subscription_upgrade',
            'metadata' => [
                'current_plan_id' => $currentPlanId,
                'new_plan_id' => $newPlanId,
                'upgrade_type' => 'immediate'
            ]
        ]);

        // Add upgrade item with proration
        $this->cartModule->addItem($cart['id'], [
            'product_id' => $newPlan['id'],
            'name' => "Upgrade to {$newPlan['name']}",
            'price' => $proration['amount_due'],
            'quantity' => 1,
            'type' => 'subscription_upgrade',
            'metadata' => [
                'proration_credit' => $proration['credit'],
                'proration_charge' => $proration['charge'],
                'effective_date' => 'immediate'
            ]
        ]);

        return [
            'cart' => $this->cartModule->find($cart['id']),
            'proration' => $proration,
            'upgrade_summary' => $this->generateUpgradeSummary($currentPlan, $newPlan, $proration)
        ];
    }

    private function addPlanAddons($cartId, $plan)
    {
        // Add available addons for the plan
        if (!empty($plan['available_addons'])) {
            foreach ($plan['available_addons'] as $addon) {
                if ($addon['auto_include']) {
                    $this->cartModule->addItem($cartId, [
                        'product_id' => $addon['id'],
                        'name' => $addon['name'],
                        'price' => $addon['price'],
                        'quantity' => 1,
                        'type' => 'subscription_addon',
                        'metadata' => [
                            'addon_type' => $addon['type'],
                            'billing_frequency' => $addon['billing_frequency']
                        ]
                    ]);
                }
            }
        }
    }
}
```

## Multi-Step Funnels

### Complex Funnel Implementation

```php
/**
 * Multi-step funnel with cart and offer integration
 */
class MultiFunnelIntegration
{
    public function createEducationFunnel($courseOfferId, $sessionId)
    {
        // Get course offer
        $courseOffer = $this->offerModule->offers()->find($courseOfferId);

        // Start navigation flow
        $flowNavigation = $this->cartModule->startFlowNavigation($courseOfferId, [
            'funnel_type' => 'education',
            'session_id' => $sessionId,
            'user_segment' => $this->identifyUserSegment($sessionId)
        ]);

        return [
            'navigation_id' => $flowNavigation['navigation_id'],
            'current_step' => $flowNavigation['current_step'],
            'funnel_config' => $this->getEducationFunnelConfig($courseOffer),
            'next_action' => 'collect_user_preferences'
        ];
    }

    public function processStepOneUserPreferences($navigationId, $preferences)
    {
        // Continue flow with user preferences
        $stepResult = $this->cartModule->continueFlowNavigation($navigationId, [
            'step' => 'user_preferences',
            'data' => [
                'experience_level' => $preferences['experience_level'],
                'learning_goals' => $preferences['learning_goals'],
                'time_commitment' => $preferences['time_commitment'],
                'preferred_format' => $preferences['preferred_format']
            ]
        ]);

        // Based on preferences, customize the offer
        $customizedOffers = $this->customizeOffersBasedOnPreferences($preferences);

        return [
            'navigation_id' => $navigationId,
            'current_step' => $stepResult['current_step'],
            'customized_offers' => $customizedOffers,
            'recommendations' => $this->generateRecommendations($preferences),
            'next_action' => 'select_course_package'
        ];
    }

    public function processStepTwoPackageSelection($navigationId, $selectedPackage, $addons = [])
    {
        // Create cart with selected package
        $cart = $this->createFunnelCart($navigationId, $selectedPackage, $addons);

        // Continue navigation flow
        $stepResult = $this->cartModule->continueFlowNavigation($navigationId, [
            'step' => 'package_selection',
            'data' => [
                'selected_package' => $selectedPackage,
                'selected_addons' => $addons,
                'cart_id' => $cart['id']
            ]
        ]);

        // Calculate and apply bundle discounts
        $this->applyBundleDiscounts($cart['id'], $selectedPackage, $addons);

        // Prepare upsell opportunities
        $upsellOpportunities = $this->prepareUpsellOpportunities($cart['id'], $selectedPackage);

        return [
            'navigation_id' => $navigationId,
            'current_step' => $stepResult['current_step'],
            'cart' => $this->cartModule->find($cart['id']),
            'upsell_opportunities' => $upsellOpportunities,
            'next_action' => 'review_upsells'
        ];
    }

    public function processStepThreeUpsells($navigationId, $cartId, $acceptedUpsells = [])
    {
        // Add accepted upsells to cart
        foreach ($acceptedUpsells as $upsellId) {
            $upsell = $this->offerModule->upsells()->find($upsellId);

            $this->cartModule->addItem($cartId, [
                'product_id' => $upsell['product_id'],
                'name' => $upsell['name'],
                'price' => $upsell['pricing']['upsell_price'],
                'quantity' => 1,
                'type' => 'upsell',
                'metadata' => [
                    'upsell_id' => $upsellId,
                    'discount_applied' => $upsell['pricing']['discount_amount']
                ]
            ]);
        }

        // Apply bundle promotions for multiple upsells
        if (count($acceptedUpsells) > 1) {
            $this->applyMultiUpsellDiscount($cartId, $acceptedUpsells);
        }

        // Continue flow
        $stepResult = $this->cartModule->continueFlowNavigation($navigationId, [
            'step' => 'upsell_selection',
            'data' => [
                'accepted_upsells' => $acceptedUpsells,
                'cart_value' => $this->cartModule->cart()->calculateTotals($cartId)['total']
            ]
        ]);

        return [
            'navigation_id' => $navigationId,
            'current_step' => $stepResult['current_step'],
            'cart' => $this->cartModule->find($cartId),
            'final_review' => $this->generateFinalReview($cartId),
            'next_action' => 'proceed_to_checkout'
        ];
    }

    private function createFunnelCart($navigationId, $selectedPackage, $addons)
    {
        // Get navigation details
        $navigation = $this->cartModule->navigation()->getDetails($navigationId);

        // Create cart
        $cart = $this->cartModule->create($navigation['session_id'], [
            'type' => 'funnel',
            'currency' => 'BRL',
            'metadata' => [
                'funnel_navigation_id' => $navigationId,
                'funnel_type' => 'education',
                'package_type' => $selectedPackage['type']
            ]
        ]);

        // Add main package
        $this->cartModule->addItem($cart['id'], [
            'product_id' => $selectedPackage['id'],
            'name' => $selectedPackage['name'],
            'price' => $selectedPackage['price'],
            'quantity' => 1,
            'type' => 'course_package',
            'metadata' => [
                'package_details' => $selectedPackage,
                'funnel_step' => 'main_package'
            ]
        ]);

        // Add selected addons
        foreach ($addons as $addon) {
            $this->cartModule->addItem($cart['id'], [
                'product_id' => $addon['id'],
                'name' => $addon['name'],
                'price' => $addon['price'],
                'quantity' => 1,
                'type' => 'course_addon',
                'metadata' => [
                    'addon_details' => $addon,
                    'funnel_step' => 'addon_selection'
                ]
            ]);
        }

        return $cart;
    }
}
```

## Advanced Integration Patterns

### Event-Driven Integration

```php
/**
 * Event-driven integration between cart and offer modules
 */
class EventDrivenIntegration
{
    private $eventDispatcher;

    public function __construct($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->setupEventListeners();
    }

    private function setupEventListeners()
    {
        // Cart events affecting offers
        $this->eventDispatcher->listen('cart.item.added', [$this, 'onCartItemAdded']);
        $this->eventDispatcher->listen('cart.promotion.applied', [$this, 'onPromotionApplied']);
        $this->eventDispatcher->listen('cart.checkout.completed', [$this, 'onCheckoutCompleted']);

        // Offer events affecting carts
        $this->eventDispatcher->listen('offer.price.changed', [$this, 'onOfferPriceChanged']);
        $this->eventDispatcher->listen('offer.upsell.triggered', [$this, 'onUpsellTriggered']);
        $this->eventDispatcher->listen('offer.ab_test.variant_selected', [$this, 'onABTestVariantSelected']);
    }

    public function onCartItemAdded($event)
    {
        $cartId = $event['cart_id'];
        $item = $event['item'];

        // If item is from an offer, update offer analytics
        if (isset($item['metadata']['offer_id'])) {
            $this->offerModule->offers()->trackEvent($item['metadata']['offer_id'], 'item_added', [
                'cart_id' => $cartId,
                'item_value' => $item['price'],
                'timestamp' => time()
            ]);

            // Check for automatic upsells
            $this->checkAutomaticUpsells($cartId, $item['metadata']['offer_id']);
        }
    }

    public function onPromotionApplied($event)
    {
        $cartId = $event['cart_id'];
        $promotion = $event['promotion'];

        // Update offer conversion tracking
        $cart = $this->cartModule->find($cartId);
        foreach ($cart['items'] as $item) {
            if (isset($item['metadata']['offer_id'])) {
                $this->offerModule->offers()->trackEvent($item['metadata']['offer_id'], 'promotion_applied', [
                    'cart_id' => $cartId,
                    'promotion_code' => $promotion['code'],
                    'discount_amount' => $promotion['discount_amount']
                ]);
            }
        }
    }

    public function onCheckoutCompleted($event)
    {
        $order = $event['order'];

        // Process post-purchase workflows
        foreach ($order['items'] as $item) {
            if (isset($item['metadata']['offer_id'])) {
                $offerId = $item['metadata']['offer_id'];

                // Track conversion
                $this->offerModule->offers()->trackConversion($offerId, [
                    'order_id' => $order['id'],
                    'customer_id' => $order['customer_id'],
                    'revenue' => $item['total'],
                    'conversion_timestamp' => time()
                ]);

                // Trigger post-purchase sequence
                $this->triggerPostPurchaseSequence($order, $offerId);
            }
        }
    }

    public function onOfferPriceChanged($event)
    {
        $offerId = $event['offer_id'];
        $newPrice = $event['new_price'];

        // Update existing carts with this offer
        $cartsWithOffer = $this->cartModule->cart()->findByOfferProduct($offerId);

        foreach ($cartsWithOffer as $cart) {
            $this->updateOfferPriceInCart($cart['id'], $offerId, $newPrice);
        }
    }

    private function checkAutomaticUpsells($cartId, $offerId)
    {
        $upsells = $this->offerModule->upsells()->getAutomatic($offerId);

        foreach ($upsells as $upsell) {
            if ($this->shouldTriggerUpsell($cartId, $upsell)) {
                $this->eventDispatcher->dispatch('offer.upsell.triggered', [
                    'cart_id' => $cartId,
                    'offer_id' => $offerId,
                    'upsell_id' => $upsell['id']
                ]);
            }
        }
    }
}
```

### Data Synchronization

```php
/**
 * Data synchronization between cart and offer modules
 */
class DataSynchronization
{
    public function syncOfferToCart($offerId, $cartId)
    {
        $offer = $this->offerModule->offers()->find($offerId);
        $cart = $this->cartModule->find($cartId);

        // Sync pricing
        $this->syncPricing($offer, $cart);

        // Sync availability
        $this->syncAvailability($offer, $cart);

        // Sync promotional data
        $this->syncPromotions($offer, $cart);

        return $this->cartModule->find($cartId);
    }

    private function syncPricing($offer, $cart)
    {
        foreach ($cart['items'] as $item) {
            if ($item['metadata']['offer_id'] === $offer['id']) {
                $currentPrice = $this->offerModule->offers()->getCurrentPrice($offer['id']);

                if ($currentPrice !== $item['price']) {
                    $this->cartModule->updateItem($cart['id'], $item['id'], [
                        'price' => $currentPrice,
                        'metadata' => array_merge($item['metadata'], [
                            'price_updated_at' => date('Y-m-d H:i:s'),
                            'previous_price' => $item['price']
                        ])
                    ]);
                }
            }
        }
    }

    public function batchSyncCarts($offerIds = null)
    {
        // Get active carts
        $activeCarts = $this->cartModule->cart()->getActive([
            'age_limit' => 24 // hours
        ]);

        $syncResults = [
            'processed' => 0,
            'updated' => 0,
            'errors' => 0
        ];

        foreach ($activeCarts as $cart) {
            try {
                $updated = false;

                foreach ($cart['items'] as $item) {
                    if (isset($item['metadata']['offer_id'])) {
                        $offerId = $item['metadata']['offer_id'];

                        // Skip if specific offers specified and this isn't one
                        if ($offerIds && !in_array($offerId, $offerIds)) {
                            continue;
                        }

                        $syncResult = $this->syncOfferToCart($offerId, $cart['id']);
                        if ($syncResult) {
                            $updated = true;
                        }
                    }
                }

                $syncResults['processed']++;
                if ($updated) {
                    $syncResults['updated']++;
                }

            } catch (Exception $e) {
                $syncResults['errors']++;
                error_log("Cart sync error for cart {$cart['id']}: " . $e->getMessage());
            }
        }

        return $syncResults;
    }
}
```

## Performance Optimization

### Caching Strategy for Integrated Operations

```php
/**
 * Optimized caching for cart-offer integration
 */
class IntegratedCaching
{
    private $cache;
    private $cartModule;
    private $offerModule;

    public function optimizeCartOfferOperations($cartId)
    {
        // Pre-load related offer data
        $cart = $this->cartModule->find($cartId);
        $offerIds = $this->extractOfferIds($cart);

        // Batch load offers
        $offers = $this->batchLoadOffers($offerIds);

        // Cache combined data
        $this->cacheCartWithOffers($cartId, $cart, $offers);

        return [
            'cart' => $cart,
            'offers' => $offers,
            'cache_status' => 'optimized'
        ];
    }

    private function batchLoadOffers($offerIds)
    {
        $cacheKeys = array_map(fn($id) => "offer:{$id}", $offerIds);
        $cachedOffers = $this->cache->getMultiple($cacheKeys);

        $uncachedIds = [];
        foreach ($offerIds as $id) {
            if (!isset($cachedOffers["offer:{$id}"])) {
                $uncachedIds[] = $id;
            }
        }

        // Load uncached offers from API
        if (!empty($uncachedIds)) {
            $freshOffers = $this->offerModule->offers()->findMultiple($uncachedIds);

            // Cache fresh offers
            $cacheData = [];
            foreach ($freshOffers as $offer) {
                $cacheData["offer:{$offer['id']}"] = $offer;
            }
            $this->cache->setMultiple($cacheData, 1800); // 30 minutes

            $cachedOffers = array_merge($cachedOffers, $cacheData);
        }

        return $cachedOffers;
    }

    public function preloadCartDependencies($sessionId)
    {
        // Predictive loading based on session patterns
        $cart = $this->cartModule->findBySession($sessionId);

        if ($cart) {
            // Pre-load likely upsells
            $this->preloadUpsells($cart);

            // Pre-load promotion eligibility
            $this->preloadPromotions($cart);

            // Pre-load checkout dependencies
            $this->preloadCheckoutData($cart);
        }
    }

    private function preloadUpsells($cart)
    {
        foreach ($cart['items'] as $item) {
            if (isset($item['metadata']['offer_id'])) {
                $upsells = $this->offerModule->upsells()->getByOffer($item['metadata']['offer_id']);

                // Cache upsells
                $this->cache->set("upsells:offer:{$item['metadata']['offer_id']}", $upsells, 3600);
            }
        }
    }
}
```

## Error Handling Strategies

### Comprehensive Error Recovery

```php
/**
 * Error handling for integrated cart-offer operations
 */
class IntegratedErrorHandling
{
    public function handleOfferToCartFailure($offerId, $cartId, $operation, $error)
    {
        $context = [
            'offer_id' => $offerId,
            'cart_id' => $cartId,
            'operation' => $operation,
            'error' => $error->getMessage(),
            'timestamp' => time()
        ];

        // Log error with context
        $this->logError($context);

        // Attempt recovery based on error type
        return match(get_class($error)) {
            'ValidationException' => $this->handleValidationError($context),
            'HttpException' => $this->handleApiError($context),
            'CacheException' => $this->handleCacheError($context),
            default => $this->handleGenericError($context)
        };
    }

    private function handleValidationError($context)
    {
        // For validation errors, try to fix data and retry
        $fixedData = $this->attemptDataFix($context['offer_id'], $context['cart_id']);

        if ($fixedData) {
            try {
                return $this->retryOperation($context['operation'], $fixedData);
            } catch (Exception $e) {
                // If still failing, degrade gracefully
                return $this->degradeGracefully($context);
            }
        }

        return ['success' => false, 'fallback' => $this->getFallbackAction($context)];
    }

    private function handleApiError($context)
    {
        // For API errors, implement retry with backoff
        $retryCount = 0;
        $maxRetries = 3;
        $backoffMs = 1000; // Start with 1 second

        while ($retryCount < $maxRetries) {
            usleep($backoffMs * 1000); // Convert to microseconds

            try {
                return $this->retryOperation($context['operation'], $context);
            } catch (HttpException $e) {
                $retryCount++;
                $backoffMs *= 2; // Exponential backoff

                if ($retryCount >= $maxRetries) {
                    // Use cached data if available
                    $cachedData = $this->getCachedFallback($context);
                    if ($cachedData) {
                        return ['success' => true, 'data' => $cachedData, 'source' => 'cache'];
                    }
                }
            }
        }

        return ['success' => false, 'exhausted_retries' => true];
    }

    private function degradeGracefully($context)
    {
        // Implement graceful degradation
        switch ($context['operation']) {
            case 'add_upsell':
                // Skip upsells if failing
                return ['success' => true, 'degraded' => true, 'message' => 'Upsells temporarily unavailable'];

            case 'apply_promotion':
                // Continue without promotion
                return ['success' => true, 'degraded' => true, 'message' => 'Promotion temporarily unavailable'];

            case 'calculate_shipping':
                // Use default shipping
                return ['success' => true, 'degraded' => true, 'shipping' => $this->getDefaultShipping()];

            default:
                return ['success' => false, 'requires_manual_intervention' => true];
        }
    }

    public function monitorIntegrationHealth()
    {
        $health = [
            'cart_module' => $this->cartModule->getStatus(),
            'offer_module' => $this->offerModule->getStatus(),
            'integration_points' => $this->checkIntegrationPoints(),
            'error_rate' => $this->calculateErrorRate(),
            'performance_metrics' => $this->getPerformanceMetrics()
        ];

        // Alert if health is degraded
        if ($health['error_rate'] > 0.05) { // 5% error rate threshold
            $this->sendHealthAlert($health);
        }

        return $health;
    }
}
```

## Best Practices

### Integration Best Practices Summary

```php
/**
 * Best practices for cart-offer integration
 */
class IntegrationBestPractices
{
    public function getRecommendations()
    {
        return [
            'data_consistency' => [
                'Always validate data between modules',
                'Implement data synchronization jobs',
                'Use transactions for critical operations',
                'Maintain audit trails for data changes'
            ],

            'performance' => [
                'Implement multi-level caching',
                'Use batch operations when possible',
                'Preload related data predictively',
                'Monitor and optimize slow queries'
            ],

            'error_handling' => [
                'Implement graceful degradation',
                'Use retry logic with exponential backoff',
                'Provide meaningful error messages',
                'Log errors with sufficient context'
            ],

            'security' => [
                'Validate all inputs across modules',
                'Implement CSRF protection',
                'Use rate limiting',
                'Audit security regularly'
            ],

            'user_experience' => [
                'Minimize API calls in user flows',
                'Provide loading states',
                'Handle edge cases gracefully',
                'Test integration thoroughly'
            ]
        ];
    }

    public function validateIntegrationImplementation($implementation)
    {
        $validationResults = [
            'data_validation' => $this->validateDataFlow($implementation),
            'error_handling' => $this->validateErrorHandling($implementation),
            'performance' => $this->validatePerformance($implementation),
            'security' => $this->validateSecurity($implementation)
        ];

        $overallScore = array_sum($validationResults) / count($validationResults);

        return [
            'score' => $overallScore,
            'details' => $validationResults,
            'recommendations' => $this->generateRecommendations($validationResults)
        ];
    }
}
```

## Real-World Use Cases

### Complete E-Learning Platform

```php
/**
 * Complete e-learning platform implementation
 */
class ELearningPlatformIntegration
{
    public function implementCompletePlatform()
    {
        // This demonstrates a complete real-world implementation
        // combining cart and offer modules for an e-learning platform

        return [
            'course_catalog' => $this->setupCourseCatalog(),
            'subscription_plans' => $this->setupSubscriptionPlans(),
            'student_onboarding' => $this->setupStudentOnboarding(),
            'progress_tracking' => $this->setupProgressTracking(),
            'certification' => $this->setupCertification()
        ];
    }

    private function setupCourseCatalog()
    {
        // Implementation details for course catalog with offers
        // This would include course creation, pricing, upsells, etc.
        return 'Course catalog setup complete';
    }

    // Additional methods for other platform components...
}
```

### Multi-Vendor Marketplace

```php
/**
 * Multi-vendor marketplace implementation
 */
class MarketplaceIntegration
{
    public function implementMarketplace()
    {
        return [
            'vendor_management' => $this->setupVendorManagement(),
            'product_listings' => $this->setupProductListings(),
            'order_processing' => $this->setupOrderProcessing(),
            'commission_tracking' => $this->setupCommissionTracking(),
            'dispute_resolution' => $this->setupDisputeResolution()
        ];
    }

    // Implementation methods...
}
```

### SaaS Product Platform

```php
/**
 * SaaS product platform implementation
 */
class SaaSPlatformIntegration
{
    public function implementSaaSPlatform()
    {
        return [
            'trial_management' => $this->setupTrialManagement(),
            'billing_automation' => $this->setupBillingAutomation(),
            'usage_tracking' => $this->setupUsageTracking(),
            'plan_management' => $this->setupPlanManagement(),
            'customer_success' => $this->setupCustomerSuccess()
        ];
    }

    // Implementation methods...
}
```

---

This integration guide provides comprehensive examples of how to effectively combine the Cart and Offer modules for various real-world scenarios. The patterns shown can be adapted and extended based on specific business requirements.

For additional examples and detailed implementations, refer to:
- [`/examples/cart-module-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/cart-module-examples.php)
- [`/examples/offer-module-examples.php`](/Users/vagner/Desenvolvimento/python/clubify-checkout/sdk/php/examples/offer-module-examples.php)