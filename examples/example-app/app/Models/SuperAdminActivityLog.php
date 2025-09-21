<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Super Admin Activity Log Model
 *
 * Tracks activities performed by super administrators for audit and security purposes.
 *
 * @property int $id
 * @property int $super_admin_id Super admin who performed the action
 * @property string $action Action performed
 * @property string $description Human-readable description
 * @property array $context Action context and parameters
 * @property array $metadata Additional metadata
 * @property string|null $tenant_id Related tenant ID (if applicable)
 * @property string|null $ip_address IP address of the action performer
 * @property string|null $user_agent User agent string
 * @property string $severity Action severity level
 * @property Carbon $created_at
 *
 * @package App\Models
 */
class SuperAdminActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'super_admin_id',
        'action',
        'description',
        'context',
        'metadata',
        'tenant_id',
        'ip_address',
        'user_agent',
        'severity',
    ];

    protected $casts = [
        'context' => 'array',
        'metadata' => 'array',
    ];

    public $timestamps = ['created_at'];
    public const UPDATED_AT = null;

    // Action constants
    public const ACTION_LOGIN = 'super_admin.login';
    public const ACTION_LOGOUT = 'super_admin.logout';
    public const ACTION_LOGIN_FAILED = 'super_admin.login_failed';
    public const ACTION_TENANT_SWITCHED = 'super_admin.tenant_switched';
    public const ACTION_TENANT_CREATED = 'super_admin.tenant_created';
    public const ACTION_TENANT_UPDATED = 'super_admin.tenant_updated';
    public const ACTION_TENANT_DELETED = 'super_admin.tenant_deleted';
    public const ACTION_PERMISSION_GRANTED = 'super_admin.permission_granted';
    public const ACTION_PERMISSION_REVOKED = 'super_admin.permission_revoked';
    public const ACTION_CONFIGURATION_CHANGED = 'super_admin.configuration_changed';
    public const ACTION_SYSTEM_MONITORED = 'super_admin.system_monitored';
    public const ACTION_USER_IMPERSONATED = 'super_admin.user_impersonated';

    // Severity constants
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

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
     * Get all valid severity levels
     *
     * @return array
     */
    public static function getValidSeverities(): array
    {
        return [
            self::SEVERITY_LOW,
            self::SEVERITY_MEDIUM,
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ];
    }

    /**
     * Create a new activity log entry
     *
     * @param int $superAdminId Super admin ID
     * @param string $action Action performed
     * @param string $description Description of the action
     * @param array $context Action context
     * @param string $severity Severity level
     * @param string|null $tenantId Related tenant ID
     * @param array $metadata Additional metadata
     * @return static
     */
    public static function logActivity(
        int $superAdminId,
        string $action,
        string $description,
        array $context = [],
        string $severity = self::SEVERITY_MEDIUM,
        ?string $tenantId = null,
        array $metadata = []
    ): self {
        return static::create([
            'super_admin_id' => $superAdminId,
            'action' => $action,
            'description' => $description,
            'context' => $context,
            'metadata' => $metadata,
            'tenant_id' => $tenantId,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'severity' => $severity,
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
     * Scope to get logs by severity
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $severity
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get logs related to specific tenant
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $tenantId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
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

    /**
     * Scope to get high severity logs
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    /**
     * Boot the model
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default severity
        static::creating(function ($log) {
            if (empty($log->severity)) {
                $log->severity = self::SEVERITY_MEDIUM;
            }
        });
    }
}