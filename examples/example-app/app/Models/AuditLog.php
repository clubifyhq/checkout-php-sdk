<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'category',
        'event_type',
        'severity',
        'sensitivity',
        'user_id',
        'super_admin_id',
        'session_id',
        'impersonated_user_id',
        'ip_address',
        'user_agent',
        'endpoint',
        'method',
        'headers',
        'request_id',
        'event_data',
        'before_state',
        'after_state',
        'metadata',
        'integrity_hash',
        'threat_indicators',
        'anomaly_detected',
        'success',
        'error_code',
        'error_message',
        'result_status',
        'compliance_category',
        'retention_until',
        'archived',
        'duration_ms',
        'memory_usage_kb',
    ];

    protected $casts = [
        'event_data' => 'array',
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
        'headers' => 'array',
        'threat_indicators' => 'array',
        'anomaly_detected' => 'boolean',
        'success' => 'boolean',
        'archived' => 'boolean',
        'retention_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the super admin that performed the action.
     */
    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class);
    }

    /**
     * Scope for security events.
     */
    public function scopeSecurity($query)
    {
        return $query->where('category', 'security');
    }

    /**
     * Scope for authentication events.
     */
    public function scopeAuthentication($query)
    {
        return $query->where('category', 'authentication');
    }

    /**
     * Scope for failed events.
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Scope for anomalies.
     */
    public function scopeAnomalies($query)
    {
        return $query->where('anomaly_detected', true);
    }

    /**
     * Scope for high severity events.
     */
    public function scopeHighSeverity($query)
    {
        return $query->whereIn('severity', ['high', 'critical']);
    }

    /**
     * Scope for recent events.
     */
    public function scopeRecent($query, $minutes = 60)
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    /**
     * Scope for specific IP address.
     */
    public function scopeByIp($query, $ipAddress)
    {
        return $query->where('ip_address', $ipAddress);
    }

    /**
     * Get formatted event data for display.
     */
    public function getFormattedEventDataAttribute()
    {
        if (empty($this->event_data)) {
            return null;
        }

        return json_encode($this->event_data, JSON_PRETTY_PRINT);
    }

    /**
     * Check if this audit log entry is suspicious.
     */
    public function isSuspicious(): bool
    {
        return $this->anomaly_detected ||
               $this->severity === 'critical' ||
               !$this->success ||
               !empty($this->threat_indicators);
    }

    /**
     * Get the retention period for this audit log.
     */
    public function getRetentionPeriod(): int
    {
        return match($this->compliance_category) {
            'sox' => 7 * 365, // 7 years for SOX compliance
            'hipaa' => 6 * 365, // 6 years for HIPAA
            'pci' => 1 * 365, // 1 year for PCI DSS
            'gdpr' => 3 * 365, // 3 years for GDPR (conservative)
            default => 2 * 365, // 2 years for general compliance
        };
    }

    /**
     * Mark this audit log as archived.
     */
    public function archive(): bool
    {
        return $this->update(['archived' => true]);
    }

    /**
     * Check if the integrity hash is valid.
     */
    public function hasValidIntegrity(string $secretKey): bool
    {
        if (empty($this->integrity_hash)) {
            return false;
        }

        $data = [
            'event_id' => $this->event_id,
            'category' => $this->category,
            'event_type' => $this->event_type,
            'user_id' => $this->user_id,
            'super_admin_id' => $this->super_admin_id,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toISOString(),
        ];

        $expectedHash = hash_hmac('sha256', json_encode($data), $secretKey);
        return hash_equals($this->integrity_hash, $expectedHash);
    }
}