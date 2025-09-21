<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Super Admin Model
 *
 * Represents a super administrator user with elevated privileges
 * for managing multiple tenants and system-wide operations.
 *
 * @property int $id
 * @property string $name Super admin full name
 * @property string $email Super admin email address
 * @property string $password Hashed password
 * @property array $permissions Global permissions list
 * @property array $metadata Additional admin metadata
 * @property string $status Admin status (active, inactive, suspended)
 * @property bool $require_2fa Whether 2FA is required
 * @property Carbon|null $last_login_at Last login timestamp
 * @property string|null $last_login_ip Last login IP address
 * @property Carbon|null $email_verified_at Email verification timestamp
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 *
 * @package App\Models
 */
class SuperAdmin extends Model
{
    use HasFactory, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
        'metadata',
        'status',
        'require_2fa',
        'last_login_at',
        'last_login_ip',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'require_2fa' => 'boolean',
        'last_login_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    // Permission constants
    public const PERMISSION_TENANT_LIST = 'tenant.list';
    public const PERMISSION_TENANT_CREATE = 'tenant.create';
    public const PERMISSION_TENANT_READ = 'tenant.read';
    public const PERMISSION_TENANT_UPDATE = 'tenant.update';
    public const PERMISSION_TENANT_DELETE = 'tenant.delete';
    public const PERMISSION_TENANT_SWITCH = 'tenant.switch';
    public const PERMISSION_USER_IMPERSONATE = 'user.impersonate';
    public const PERMISSION_SYSTEM_MONITOR = 'system.monitor';
    public const PERMISSION_ANALYTICS_VIEW = 'analytics.view';
    public const PERMISSION_CONFIGURATION_MANAGE = 'configuration.manage';
    public const PERMISSION_BILLING_MANAGE = 'billing.manage';
    public const PERMISSION_SUPPORT_ACCESS = 'support.access';

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
        ];
    }

    /**
     * Get all available permissions
     *
     * @return array
     */
    public static function getAllPermissions(): array
    {
        return [
            self::PERMISSION_TENANT_LIST,
            self::PERMISSION_TENANT_CREATE,
            self::PERMISSION_TENANT_READ,
            self::PERMISSION_TENANT_UPDATE,
            self::PERMISSION_TENANT_DELETE,
            self::PERMISSION_TENANT_SWITCH,
            self::PERMISSION_USER_IMPERSONATE,
            self::PERMISSION_SYSTEM_MONITOR,
            self::PERMISSION_ANALYTICS_VIEW,
            self::PERMISSION_CONFIGURATION_MANAGE,
            self::PERMISSION_BILLING_MANAGE,
            self::PERMISSION_SUPPORT_ACCESS,
        ];
    }

    /**
     * Get tenants that this super admin can access
     *
     * @return BelongsToMany
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'super_admin_tenant_access')
                    ->withPivot(['permissions', 'granted_at', 'granted_by'])
                    ->withTimestamps();
    }

    /**
     * Get super admin activity logs
     *
     * @return HasMany
     */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(SuperAdminActivityLog::class);
    }

    /**
     * Get super admin sessions
     *
     * @return HasMany
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(SuperAdminSession::class);
    }

    /**
     * Check if super admin is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if super admin has specific permission
     *
     * @param string $permission Permission to check
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions ?? [], true);
    }

    /**
     * Check if super admin has access to specific tenant
     *
     * @param string $tenantId Tenant ID to check
     * @return bool
     */
    public function hasAccessToTenant(string $tenantId): bool
    {
        return $this->tenants()->where('tenant_id', $tenantId)->exists();
    }

    /**
     * Get permissions for specific tenant
     *
     * @param string $tenantId Tenant ID
     * @return array
     */
    public function getTenantPermissions(string $tenantId): array
    {
        $tenant = $this->tenants()->where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return [];
        }

        return $tenant->pivot->permissions ?? [];
    }

    /**
     * Grant access to tenant with specific permissions
     *
     * @param string $tenantId Tenant ID
     * @param array $permissions Permissions to grant
     * @param int|null $grantedBy ID of admin who granted access
     * @return bool
     */
    public function grantTenantAccess(string $tenantId, array $permissions, ?int $grantedBy = null): bool
    {
        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return false;
        }

        $this->tenants()->syncWithoutDetaching([
            $tenant->id => [
                'permissions' => $permissions,
                'granted_at' => now(),
                'granted_by' => $grantedBy,
            ]
        ]);

        return true;
    }

    /**
     * Revoke access to tenant
     *
     * @param string $tenantId Tenant ID
     * @return bool
     */
    public function revokeTenantAccess(string $tenantId): bool
    {
        $tenant = Tenant::where('tenant_id', $tenantId)->first();

        if (!$tenant) {
            return false;
        }

        $this->tenants()->detach($tenant->id);

        return true;
    }

    /**
     * Record login activity
     *
     * @param string|null $ipAddress IP address
     * @return void
     */
    public function recordLogin(?string $ipAddress = null): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => $ipAddress ?? request()->ip(),
        ]);
    }

    /**
     * Get metadata value
     *
     * @param string $key Metadata key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getMetadata(string $key, $default = null)
    {
        return data_get($this->metadata, $key, $default);
    }

    /**
     * Set metadata value
     *
     * @param string $key Metadata key
     * @param mixed $value Metadata value
     * @return void
     */
    public function setMetadata(string $key, $value): void
    {
        $metadata = $this->metadata ?? [];
        data_set($metadata, $key, $value);
        $this->metadata = $metadata;
    }

    /**
     * Scope to get active super admins
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to get super admins with specific permission
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $permission
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithPermission($query, string $permission)
    {
        return $query->whereJsonContains('permissions', $permission);
    }

    /**
     * Set the password attribute
     *
     * @param string $value
     * @return void
     */
    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = Hash::make($value);
    }

    /**
     * Boot the model
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default permissions for new super admins
        static::creating(function ($superAdmin) {
            if (empty($superAdmin->permissions)) {
                $superAdmin->permissions = [
                    self::PERMISSION_TENANT_LIST,
                    self::PERMISSION_TENANT_READ,
                    self::PERMISSION_TENANT_SWITCH,
                ];
            }

            if (empty($superAdmin->status)) {
                $superAdmin->status = self::STATUS_ACTIVE;
            }
        });
    }
}