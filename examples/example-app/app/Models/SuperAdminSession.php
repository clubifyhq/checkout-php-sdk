<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Super Admin Session Model
 *
 * Tracks active super admin sessions for security and monitoring purposes.
 *
 * @property int $id
 * @property int $super_admin_id Super admin who owns the session
 * @property string $session_id Laravel session ID
 * @property string $token Super admin authentication token
 * @property array $permissions Session permissions
 * @property string|null $current_tenant_id Currently selected tenant
 * @property string|null $ip_address Session IP address
 * @property string|null $user_agent User agent string
 * @property Carbon $started_at Session start time
 * @property Carbon $expires_at Session expiration time
 * @property Carbon|null $last_activity_at Last activity timestamp
 * @property Carbon|null $ended_at Session end time
 * @property string $status Session status
 * @property array $metadata Additional session metadata
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @package App\Models
 */
class SuperAdminSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'super_admin_id',
        'session_id',
        'token',
        'permissions',
        'current_tenant_id',
        'ip_address',
        'user_agent',
        'started_at',
        'expires_at',
        'last_activity_at',
        'ended_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_TERMINATED = 'terminated';
    public const STATUS_REVOKED = 'revoked';

    /**
     * Get the super admin who owns this session
     *
     * @return BelongsTo
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Check if session is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE &&
               $this->expires_at->isFuture();
    }

    /**
     * Check if session is expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() ||
               $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Update last activity
     *
     * @return void
     */
    public function updateActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Terminate the session
     *
     * @param string $reason Termination reason
     * @return void
     */
    public function terminate(string $reason = 'manual'): void
    {
        $this->update([
            'status' => self::STATUS_TERMINATED,
            'ended_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'termination_reason' => $reason,
                'terminated_at' => now()->toISOString(),
            ]),
        ]);
    }

    /**
     * Extend session expiration
     *
     * @param int $seconds Seconds to extend
     * @return void
     */
    public function extend(int $seconds): void
    {
        $this->update([
            'expires_at' => $this->expires_at->addSeconds($seconds),
            'metadata' => array_merge($this->metadata ?? [], [
                'extended_at' => now()->toISOString(),
                'extended_by_seconds' => $seconds,
            ]),
        ]);
    }

    /**
     * Set current tenant context
     *
     * @param string|null $tenantId Tenant ID
     * @return void
     */
    public function setCurrentTenant(?string $tenantId): void
    {
        $this->update([
            'current_tenant_id' => $tenantId,
            'last_activity_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'tenant_switched_at' => now()->toISOString(),
                'previous_tenant_id' => $this->current_tenant_id,
            ]),
        ]);
    }

    /**
     * Get session duration in seconds
     *
     * @return int
     */
    public function getDurationInSeconds(): int
    {
        $endTime = $this->ended_at ?? now();
        return $endTime->diffInSeconds($this->started_at);
    }

    /**
     * Scope to get active sessions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
                     ->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired sessions
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExpired($query)
    {
        return $query->where(function ($q) {
            $q->where('expires_at', '<=', now())
              ->orWhere('status', self::STATUS_EXPIRED);
        });
    }

    /**
     * Scope to get sessions by IP address
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $ipAddress
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByIpAddress($query, string $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Boot the model
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Set default values
        static::creating(function ($session) {
            if (empty($session->status)) {
                $session->status = self::STATUS_ACTIVE;
            }

            if (empty($session->started_at)) {
                $session->started_at = now();
            }

            if (empty($session->session_id)) {
                $session->session_id = session()->getId();
            }
        });

        // Update activity on model updates
        static::updating(function ($session) {
            if (!$session->isDirty('last_activity_at')) {
                $session->last_activity_at = now();
            }
        });
    }
}