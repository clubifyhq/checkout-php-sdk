<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tenant Activity Log Model
 *
 * Tracks activities performed on tenants for audit and monitoring purposes.
 *
 * @property int $id
 * @property int $tenant_id Related tenant ID
 * @property int|null $super_admin_id Super admin who performed the action
 * @property string $action Action performed
 * @property string $description Human-readable description
 * @property array $changes Data changes (before/after)
 * @property array $metadata Additional metadata
 * @property string|null $ip_address IP address of the action performer
 * @property string|null $user_agent User agent string
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class TenantActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'super_admin_id',
        'action',
        'description',
        'changes',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
        'metadata' => 'array',
    ];

    public $timestamps = ['created_at'];
    public const UPDATED_AT = null;

    // Action constants
    public const ACTION_CREATED = 'tenant.created';
    public const ACTION_UPDATED = 'tenant.updated';
    public const ACTION_DELETED = 'tenant.deleted';
    public const ACTION_SUSPENDED = 'tenant.suspended';
    public const ACTION_ACTIVATED = 'tenant.activated';
    public const ACTION_PLAN_CHANGED = 'tenant.plan_changed';
    public const ACTION_FEATURES_UPDATED = 'tenant.features_updated';
    public const ACTION_CONFIGURATION_UPDATED = 'tenant.configuration_updated';
    public const ACTION_SWITCHED_TO = 'tenant.switched_to';
    public const ACTION_SWITCHED_FROM = 'tenant.switched_from';

    /**
     * Get the tenant that this log belongs to
     *
     * @return BelongsTo
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the super admin who performed the action
     *
     * @return BelongsTo
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Create a new activity log entry
     *
     * @param int $tenantId Tenant ID
     * @param string $action Action performed
     * @param string $description Description of the action
     * @param array $changes Data changes
     * @param int|null $superAdminId Super admin ID
     * @param array $metadata Additional metadata
     * @return static
     */
    public static function logActivity(
        int $tenantId,
        string $action,
        string $description,
        array $changes = [],
        ?int $superAdminId = null,
        array $metadata = []
    ): self {
        return static::create([
            'tenant_id' => $tenantId,
            'super_admin_id' => $superAdminId,
            'action' => $action,
            'description' => $description,
            'changes' => $changes,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Scope to get logs for specific action
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $action
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to get logs by super admin
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $superAdminId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySuperAdmin($query, int $superAdminId)
    {
        return $query->where('super_admin_id', $superAdminId);
    }

    /**
     * Scope to get recent logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $hours Number of hours
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}