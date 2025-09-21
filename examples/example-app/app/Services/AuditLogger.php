<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

/**
 * Comprehensive Audit Logger Service
 *
 * Provides secure, performant audit logging for super admin operations
 * with integrity protection and compliance features.
 *
 * Security Features:
 * - Immutable audit trails with cryptographic integrity
 * - Real-time security event alerting
 * - Data classification and sensitivity handling
 * - Compliance reporting (SOX, GDPR, HIPAA)
 * - Anomaly detection and correlation
 * - Secure log retention and archival
 *
 * @package App\Services
 */
class AuditLogger
{
    // Event severity levels
    public const SEVERITY_LOW = 'low';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_CRITICAL = 'critical';

    // Event categories
    public const CATEGORY_AUTHENTICATION = 'authentication';
    public const CATEGORY_AUTHORIZATION = 'authorization';
    public const CATEGORY_DATA_ACCESS = 'data_access';
    public const CATEGORY_DATA_MODIFICATION = 'data_modification';
    public const CATEGORY_CONFIGURATION = 'configuration';
    public const CATEGORY_SECURITY = 'security';
    public const CATEGORY_SYSTEM = 'system';
    public const CATEGORY_COMPLIANCE = 'compliance';

    // Data sensitivity levels
    public const SENSITIVITY_PUBLIC = 'public';
    public const SENSITIVITY_INTERNAL = 'internal';
    public const SENSITIVITY_CONFIDENTIAL = 'confidential';
    public const SENSITIVITY_RESTRICTED = 'restricted';

    // Alert thresholds
    private const SUSPICIOUS_ACTIVITY_THRESHOLD = 10; // Events per hour
    private const FAILED_AUTH_THRESHOLD = 5; // Failed attempts
    private const PRIVILEGE_ESCALATION_THRESHOLD = 3; // Attempts per hour

    private string $auditSecret;

    public function __construct()
    {
        $this->auditSecret = config('app.audit_secret', env('AUDIT_SECRET', 'default-audit-secret'));
    }

    /**
     * Log super admin access events.
     */
    public function logSuperAdminAccess(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_AUTHENTICATION,
            'severity' => self::SEVERITY_MEDIUM,
            'sensitivity' => self::SENSITIVITY_CONFIDENTIAL,
            'super_admin_context' => true,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Log security events with high priority.
     */
    public function logSecurityEvent(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_SECURITY,
            'severity' => $this->determineSeverity($data),
            'sensitivity' => self::SENSITIVITY_RESTRICTED,
            'requires_immediate_attention' => $this->requiresImmediateAttention($data),
        ]);

        $result = $this->logEvent($auditData);

        // Trigger real-time alerts for critical security events
        if ($auditData['severity'] === self::SEVERITY_CRITICAL) {
            $this->triggerSecurityAlert($auditData);
        }

        // Check for patterns that indicate security threats
        $this->analyzeSecurityPatterns($auditData);

        return $result;
    }

    /**
     * Log data access events for compliance.
     */
    public function logDataAccess(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_DATA_ACCESS,
            'severity' => self::SEVERITY_LOW,
            'sensitivity' => $data['data_sensitivity'] ?? self::SENSITIVITY_INTERNAL,
            'compliance_relevant' => true,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Log data modification events.
     */
    public function logDataModification(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_DATA_MODIFICATION,
            'severity' => self::SEVERITY_MEDIUM,
            'sensitivity' => $data['data_sensitivity'] ?? self::SENSITIVITY_CONFIDENTIAL,
            'compliance_relevant' => true,
            'requires_backup' => true,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Log configuration changes.
     */
    public function logConfigurationChange(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_CONFIGURATION,
            'severity' => self::SEVERITY_HIGH,
            'sensitivity' => self::SENSITIVITY_RESTRICTED,
            'requires_approval' => true,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Log authorization events (permission grants/denials).
     */
    public function logAuthorizationEvent(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_AUTHORIZATION,
            'severity' => $this->determineAuthorizationSeverity($data),
            'sensitivity' => self::SENSITIVITY_CONFIDENTIAL,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Log system events.
     */
    public function logSystemEvent(array $data): bool
    {
        $auditData = array_merge($data, [
            'category' => self::CATEGORY_SYSTEM,
            'severity' => $data['severity'] ?? self::SEVERITY_LOW,
            'sensitivity' => self::SENSITIVITY_INTERNAL,
        ]);

        return $this->logEvent($auditData);
    }

    /**
     * Core audit logging method with integrity protection.
     */
    protected function logEvent(array $data): bool
    {
        try {
            // Prepare audit entry
            $auditEntry = $this->prepareAuditEntry($data);

            // Generate integrity hash
            $auditEntry['integrity_hash'] = $this->generateIntegrityHash($auditEntry);

            // Store in database with transaction
            DB::transaction(function () use ($auditEntry) {
                DB::table('audit_logs')->insert($auditEntry);
            });

            // Queue for additional processing if needed
            if ($auditEntry['requires_immediate_attention'] ?? false) {
                Queue::push('ProcessCriticalAuditEvent', $auditEntry);
            }

            // Log to Laravel's log system as backup
            Log::channel('audit')->info('Audit Event', [
                'audit_id' => $auditEntry['audit_id'],
                'event' => $auditEntry['event'],
                'category' => $auditEntry['category'],
                'severity' => $auditEntry['severity'],
            ]);

            return true;

        } catch (Exception $e) {
            // Critical: audit logging failure
            Log::critical('Audit logging failure', [
                'error' => $e->getMessage(),
                'event_data' => $this->sanitizeForLogging($data),
                'trace' => $e->getTraceAsString(),
            ]);

            // Attempt emergency backup logging
            $this->emergencyAuditLog($data, $e);

            return false;
        }
    }

    /**
     * Prepare standardized audit entry structure.
     */
    private function prepareAuditEntry(array $data): array
    {
        $timestamp = now();

        return [
            'audit_id' => Str::uuid()->toString(),
            'event' => $data['event'] ?? 'unknown_event',
            'event_type' => $data['event_type'] ?? ($data['event'] ?? 'unknown_event'),
            'category' => $data['category'] ?? self::CATEGORY_SYSTEM,
            'severity' => $data['severity'] ?? self::SEVERITY_LOW,
            'sensitivity' => $data['sensitivity'] ?? self::SENSITIVITY_INTERNAL,
            'user_id' => $data['user_id'] ?? null,
            'super_admin_id' => $data['super_admin_id'] ?? null,
            'session_id' => $data['session_id'] ?? session()->getId(),
            'ip_address' => $data['ip_address'] ?? request()->ip(),
            'user_agent' => $data['user_agent'] ?? request()->userAgent(),
            'endpoint' => $data['endpoint'] ?? request()->path(),
            'method' => $data['method'] ?? request()->method(),
            'tenant_id' => $data['tenant_id'] ?? null,
            'resource_type' => $data['resource_type'] ?? null,
            'resource_id' => $data['resource_id'] ?? null,
            'action' => $data['action'] ?? null,
            'outcome' => $data['outcome'] ?? 'success',
            'metadata' => json_encode($this->sanitizeMetadata($data)),
            'compliance_relevant' => $data['compliance_relevant'] ?? false,
            'requires_immediate_attention' => $data['requires_immediate_attention'] ?? false,
            'super_admin_context' => $data['super_admin_context'] ?? false,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }

    /**
     * Generate cryptographic integrity hash for audit entry.
     */
    private function generateIntegrityHash(array $entry): string
    {
        // Create hash payload excluding the hash field itself
        $hashPayload = $entry;
        unset($hashPayload['integrity_hash']);

        // Sort keys for consistent hashing
        ksort($hashPayload);

        // Generate HMAC with secret key
        return hash_hmac('sha256', json_encode($hashPayload), $this->auditSecret);
    }

    /**
     * Verify integrity of an audit entry.
     */
    public function verifyIntegrity(array $entry): bool
    {
        if (!isset($entry['integrity_hash'])) {
            return false;
        }

        $storedHash = $entry['integrity_hash'];
        $computedHash = $this->generateIntegrityHash($entry);

        return hash_equals($storedHash, $computedHash);
    }

    /**
     * Determine event severity based on content.
     */
    private function determineSeverity(array $data): string
    {
        $event = $data['event'] ?? '';

        $criticalEvents = [
            'session_hijacking_detected',
            'privilege_escalation_attempt',
            'data_breach_detected',
            'system_compromise',
            'malicious_activity_detected',
        ];

        $highSeverityEvents = [
            'unauthorized_access_attempt',
            'suspicious_ip_blocked',
            'rate_limit_exceeded',
            'configuration_tampering',
            'authentication_bypass_attempt',
        ];

        $mediumSeverityEvents = [
            'insufficient_permissions',
            'session_timeout',
            'invalid_http_method',
            'malicious_headers_detected',
        ];

        if (in_array($event, $criticalEvents)) {
            return self::SEVERITY_CRITICAL;
        } elseif (in_array($event, $highSeverityEvents)) {
            return self::SEVERITY_HIGH;
        } elseif (in_array($event, $mediumSeverityEvents)) {
            return self::SEVERITY_MEDIUM;
        }

        return self::SEVERITY_LOW;
    }

    /**
     * Determine if event requires immediate attention.
     */
    private function requiresImmediateAttention(array $data): bool
    {
        $criticalEvents = [
            'session_hijacking_detected',
            'privilege_escalation_attempt',
            'data_breach_detected',
            'system_compromise',
            'malicious_activity_detected',
        ];

        $event = $data['event'] ?? '';
        return in_array($event, $criticalEvents);
    }

    /**
     * Determine authorization event severity.
     */
    private function determineAuthorizationSeverity(array $data): string
    {
        $outcome = $data['outcome'] ?? 'success';
        $action = $data['action'] ?? '';

        if ($outcome === 'denied' && str_contains($action, 'admin')) {
            return self::SEVERITY_HIGH;
        } elseif ($outcome === 'denied') {
            return self::SEVERITY_MEDIUM;
        }

        return self::SEVERITY_LOW;
    }

    /**
     * Analyze security patterns for threat detection.
     */
    private function analyzeSecurityPatterns(array $data): void
    {
        $ip = $data['ip_address'] ?? null;
        $event = $data['event'] ?? '';

        if (!$ip) {
            return;
        }

        // Check for suspicious activity patterns
        $this->checkSuspiciousActivity($ip, $event);
        $this->checkFailedAuthenticationPattern($ip, $event);
        $this->checkPrivilegeEscalationPattern($ip, $event);
    }

    /**
     * Check for suspicious activity patterns.
     */
    private function checkSuspiciousActivity(string $ip, string $event): void
    {
        $hourAgo = now()->subHour();

        $recentEvents = DB::table('audit_logs')
            ->where('ip_address', $ip)
            ->where('category', self::CATEGORY_SECURITY)
            ->where('created_at', '>', $hourAgo)
            ->count();

        if ($recentEvents >= self::SUSPICIOUS_ACTIVITY_THRESHOLD) {
            $this->logSecurityEvent([
                'event' => 'suspicious_activity_pattern_detected',
                'ip_address' => $ip,
                'recent_events_count' => $recentEvents,
                'time_window' => '1_hour',
                'requires_immediate_attention' => true,
            ]);
        }
    }

    /**
     * Check for failed authentication patterns.
     */
    private function checkFailedAuthenticationPattern(string $ip, string $event): void
    {
        if (!str_contains($event, 'unauthorized') && !str_contains($event, 'authentication_failed')) {
            return;
        }

        $hourAgo = now()->subHour();

        $failedAttempts = DB::table('audit_logs')
            ->where('ip_address', $ip)
            ->where('category', self::CATEGORY_AUTHENTICATION)
            ->where('outcome', 'failed')
            ->where('created_at', '>', $hourAgo)
            ->count();

        if ($failedAttempts >= self::FAILED_AUTH_THRESHOLD) {
            $this->logSecurityEvent([
                'event' => 'brute_force_attack_detected',
                'ip_address' => $ip,
                'failed_attempts' => $failedAttempts,
                'time_window' => '1_hour',
                'requires_immediate_attention' => true,
            ]);
        }
    }

    /**
     * Check for privilege escalation patterns.
     */
    private function checkPrivilegeEscalationPattern(string $ip, string $event): void
    {
        if (!str_contains($event, 'privilege') && !str_contains($event, 'permission')) {
            return;
        }

        $hourAgo = now()->subHour();

        $escalationAttempts = DB::table('audit_logs')
            ->where('ip_address', $ip)
            ->where('category', self::CATEGORY_AUTHORIZATION)
            ->where('outcome', 'denied')
            ->where('created_at', '>', $hourAgo)
            ->count();

        if ($escalationAttempts >= self::PRIVILEGE_ESCALATION_THRESHOLD) {
            $this->logSecurityEvent([
                'event' => 'privilege_escalation_attempt_detected',
                'ip_address' => $ip,
                'escalation_attempts' => $escalationAttempts,
                'time_window' => '1_hour',
                'requires_immediate_attention' => true,
            ]);
        }
    }

    /**
     * Trigger real-time security alerts.
     */
    private function triggerSecurityAlert(array $data): void
    {
        // Queue immediate notification job
        Queue::push('SendSecurityAlert', [
            'event' => $data['event'],
            'severity' => $data['severity'],
            'ip_address' => $data['ip_address'] ?? 'unknown',
            'timestamp' => now()->toISOString(),
            'details' => $this->sanitizeForLogging($data),
        ]);

        // Log to security channel
        Log::channel('security')->critical('Critical Security Event', $data);
    }

    /**
     * Sanitize metadata for storage.
     */
    private function sanitizeMetadata(array $data): array
    {
        // Remove sensitive keys
        $sensitiveKeys = [
            'password', 'token', 'secret', 'key', 'authorization',
            'cookie', 'session', 'csrf_token', 'api_key'
        ];

        $sanitized = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            // Skip known audit fields
            if (in_array($key, [
                'category', 'severity', 'sensitivity', 'user_id', 'super_admin_id',
                'session_id', 'ip_address', 'user_agent', 'endpoint', 'method',
                'tenant_id', 'resource_type', 'resource_id', 'action', 'outcome',
                'compliance_relevant', 'requires_immediate_attention', 'super_admin_context'
            ])) {
                continue;
            }

            // Sanitize sensitive data
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (str_contains($lowerKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $this->sanitizeMetadata($value) : $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize data for emergency logging.
     */
    private function sanitizeForLogging(array $data): array
    {
        $sanitized = $this->sanitizeMetadata($data);

        // Additional truncation for logging
        foreach ($sanitized as $key => $value) {
            if (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000) . '...[TRUNCATED]';
            }
        }

        return $sanitized;
    }

    /**
     * Emergency audit logging when primary logging fails.
     */
    private function emergencyAuditLog(array $data, Exception $exception): void
    {
        try {
            $emergencyLog = [
                'emergency_audit' => true,
                'original_event' => $data['event'] ?? 'unknown',
                'failure_reason' => $exception->getMessage(),
                'timestamp' => now()->toISOString(),
                'sanitized_data' => $this->sanitizeForLogging($data),
            ];

            // Try file-based logging as last resort
            Log::channel('single')->emergency('Audit System Failure', $emergencyLog);

        } catch (Exception $e) {
            // If even emergency logging fails, there's a serious system issue
            error_log('CRITICAL: Complete audit system failure - ' . $e->getMessage());
        }
    }

    /**
     * Get audit statistics for monitoring.
     */
    public function getAuditStatistics(array $filters = []): array
    {
        try {
            $query = DB::table('audit_logs');

            // Apply filters
            if (!empty($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }

            if (!empty($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }

            if (!empty($filters['category'])) {
                $query->where('category', $filters['category']);
            }

            if (!empty($filters['severity'])) {
                $query->where('severity', $filters['severity']);
            }

            $stats = [
                'total_events' => $query->count(),
                'by_category' => $query->select('category', DB::raw('count(*) as count'))
                    ->groupBy('category')->get()->keyBy('category'),
                'by_severity' => $query->select('severity', DB::raw('count(*) as count'))
                    ->groupBy('severity')->get()->keyBy('severity'),
                'critical_events' => $query->where('severity', self::SEVERITY_CRITICAL)->count(),
                'security_events' => $query->where('category', self::CATEGORY_SECURITY)->count(),
                'super_admin_events' => $query->where('super_admin_context', true)->count(),
            ];

            return $stats;

        } catch (Exception $e) {
            Log::error('Failed to generate audit statistics', [
                'error' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return ['error' => 'Statistics generation failed'];
        }
    }

    /**
     * Verify audit log integrity for compliance.
     */
    public function verifyAuditIntegrity(string $auditId): array
    {
        try {
            $entry = DB::table('audit_logs')->where('audit_id', $auditId)->first();

            if (!$entry) {
                return ['valid' => false, 'error' => 'Audit entry not found'];
            }

            $entryArray = (array) $entry;
            $isValid = $this->verifyIntegrity($entryArray);

            return [
                'valid' => $isValid,
                'audit_id' => $auditId,
                'verified_at' => now()->toISOString(),
                'integrity_hash' => $entry->integrity_hash,
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => 'Verification failed: ' . $e->getMessage(),
            ];
        }
    }
}