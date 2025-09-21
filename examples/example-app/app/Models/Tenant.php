<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Tenant Model
 *
 * Represents a tenant in the multi-tenant system, containing
 * tenant-specific configuration, subscription info, and relationships.
 *
 * @property int $id
 * @property string $tenant_id Unique tenant identifier
 * @property string $name Tenant display name
 * @property string $email Tenant primary email
 * @property string $status Tenant status (active, inactive, suspended)
 * @property string $plan Subscription plan (basic, premium, enterprise)
 * @property array $features Available features for this tenant
 * @property array $configuration Tenant-specific configuration
 * @property array $limits Resource limits and quotas
 * @property array $metadata Additional tenant metadata
 * @property Carbon $subscription_expires_at Subscription expiration date
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @package App\Models
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'status',
        'plan',
        'features',
        'configuration',
        'limits',
        'metadata',
        'subscription_expires_at',
    ];

    protected $casts = [
        'features' => 'array',
        'configuration' => 'array',
        'limits' => 'array',
        'metadata' => 'array',
        'subscription_expires_at' => 'datetime',
    ];

    protected $dates = [
        'subscription_expires_at',
        'deleted_at',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PENDING = 'pending';

    // Plan constants
    public const PLAN_BASIC = 'basic';
    public const PLAN_PREMIUM = 'premium';
    public const PLAN_ENTERPRISE = 'enterprise';

    /**
     * Get all valid status values
     *
     * @return array
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_SUSPENDED,
            self::STATUS_PENDING,
        ];
    }

    /**
     * Get all valid plan values
     *
     * @return array
     */
    public static function getValidPlans(): array
    {
        return [
            self::PLAN_BASIC,
            self::PLAN_PREMIUM,
            self::PLAN_ENTERPRISE,
        ];
    }

    /**
     * Get super admin users who can manage this tenant
     *
     * @return BelongsToMany
     */
    public function superAdmins(): BelongsToMany
    {
        return $this->belongsToMany(SuperAdmin::class, 'super_admin_tenant_access')
                    ->withPivot(['permissions', 'granted_at', 'granted_by'])
                    ->withTimestamps();
    }

    /**
     * Get tenant activity logs
     *
     * @return HasMany
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(TenantActivityLog::class);
    }

    /**
     * Check if tenant is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if tenant subscription is expired
     *
     * @return bool
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_expires_at && $this->subscription_expires_at->isPast();
    }

    /**
     * Check if tenant has specific feature
     *
     * @param string $feature Feature name to check
     * @return bool
     */
    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getConfiguration(string $key, $default = null)
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public function setConfiguration(string $key, $value): void
    {
        $configuration = $this->configuration ?? [];
        data_set($configuration, $key, $value);
        $this->configuration = $configuration;
    }

    /**
     * Get limit value
     *
     * @param string $limit Limit key
     * @param int $default Default value if limit not found
     * @return int
     */
    public function getLimit(string $limit, int $default = 0): int
    {
        return data_get($this->limits, $limit, $default);
    }

    /**
     * Check if tenant can perform action based on limits
     *
     * @param string $action Action to check
     * @param int $currentUsage Current usage count
     * @return bool
     */
    public function canPerformAction(string $action, int $currentUsage): bool
    {
        $limit = $this->getLimit($action, PHP_INT_MAX);
        return $currentUsage < $limit;
    }

    /**
     * Get tenant statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        // This would typically be calculated from actual usage data
        return [
            'users_count' => 0,
            'products_count' => 0,
            'orders_count' => 0,
            'revenue' => 0,
            'last_activity' => null,
        ];
    }

    /**
     * Scope to get active tenants
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get tenants by plan
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $plan
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPlan($query, string $plan)
    {
        return $query->where('plan', $plan);
    }

    /**
     * Scope to get tenants with non-expired subscriptions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithValidSubscription($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('subscription_expires_at')
              ->orWhere('subscription_expires_at', '>', now());
        });
    }

    /**
     * Boot the model
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Generate unique tenant_id if not provided
        static::creating(function ($tenant) {
            if (empty($tenant->tenant_id)) {
                $tenant->tenant_id = 'tenant_' . uniqid() . '_' . time();
            }

            // Set default features and limits based on plan
            if (empty($tenant->features)) {
                $tenant->features = self::getDefaultFeaturesForPlan($tenant->plan);
            }

            if (empty($tenant->limits)) {
                $tenant->limits = self::getDefaultLimitsForPlan($tenant->plan);
            }
        });
    }

    /**
     * Get default features for a plan
     *
     * @param string $plan
     * @return array
     */
    private static function getDefaultFeaturesForPlan(string $plan): array
    {
        $features = [
            self::PLAN_BASIC => [
                'checkout',
                'payments',
                'customers',
            ],
            self::PLAN_PREMIUM => [
                'checkout',
                'payments',
                'customers',
                'advanced_checkout',
                'multi_payment_gateway',
                'analytics',
            ],
            self::PLAN_ENTERPRISE => [
                'checkout',
                'payments',
                'customers',
                'advanced_checkout',
                'multi_payment_gateway',
                'analytics',
                'webhooks',
                'api_access',
                'white_label',
                'priority_support',
            ],
        ];

        return $features[$plan] ?? $features[self::PLAN_BASIC];
    }

    /**
     * Get default limits for a plan
     *
     * @param string $plan
     * @return array
     */
    private static function getDefaultLimitsForPlan(string $plan): array
    {
        $limits = [
            self::PLAN_BASIC => [
                'max_products' => 100,
                'max_orders_per_month' => 1000,
                'max_users' => 5,
                'max_webhooks' => 0,
                'api_calls_per_minute' => 60,
            ],
            self::PLAN_PREMIUM => [
                'max_products' => 1000,
                'max_orders_per_month' => 10000,
                'max_users' => 25,
                'max_webhooks' => 10,
                'api_calls_per_minute' => 300,
            ],
            self::PLAN_ENTERPRISE => [
                'max_products' => -1, // unlimited
                'max_orders_per_month' => -1, // unlimited
                'max_users' => -1, // unlimited
                'max_webhooks' => -1, // unlimited
                'api_calls_per_minute' => 1000,
            ],
        ];

        return $limits[$plan] ?? $limits[self::PLAN_BASIC];
    }
}