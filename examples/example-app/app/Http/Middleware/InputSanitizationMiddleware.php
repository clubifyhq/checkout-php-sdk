<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Input Sanitization and Validation Middleware
 *
 * Provides comprehensive input sanitization and validation for security.
 * Protects against common injection attacks and malicious input patterns.
 *
 * Security Features:
 * - XSS prevention through HTML sanitization
 * - SQL injection pattern detection
 * - Path traversal attack prevention
 * - Script injection detection
 * - Malicious URL pattern detection
 * - Input length validation
 * - Character encoding validation
 *
 * @package App\Http\Middleware
 */
class InputSanitizationMiddleware
{
    private AuditLogger $auditLogger;

    // Configuration constants
    private const MAX_INPUT_LENGTH = 10000; // Maximum input field length
    private const MAX_TOTAL_INPUT_SIZE = 1048576; // 1MB total input size
    private const SUSPICIOUS_PATTERN_THRESHOLD = 3;

    // Dangerous patterns to detect
    private const XSS_PATTERNS = [
        '/<script[^>]*>.*?<\/script>/is',
        '/javascript:/i',
        '/vbscript:/i',
        '/onload\s*=/i',
        '/onerror\s*=/i',
        '/onclick\s*=/i',
        '/onmouseover\s*=/i',
        '/onfocus\s*=/i',
        '/onblur\s*=/i',
        '/onchange\s*=/i',
        '/onsubmit\s*=/i',
        '/expression\s*\(/i',
        '/<iframe[^>]*>/i',
        '/<object[^>]*>/i',
        '/<embed[^>]*>/i',
        '/<link[^>]*>/i',
        '/<meta[^>]*>/i',
    ];

    private const SQL_INJECTION_PATTERNS = [
        '/(\'\s*(or|and)\s*\')/i',
        '/(\'\s*(or|and)\s*\d+\s*=\s*\d+)/i',
        '/(union\s+select)/i',
        '/(drop\s+table)/i',
        '/(delete\s+from)/i',
        '/(insert\s+into)/i',
        '/(update\s+\w+\s+set)/i',
        '/(\|\||&&)/i',
        '/(exec\s*\()/i',
        '/(sp_executesql)/i',
        '/(xp_cmdshell)/i',
        '/(\-\-)/i',
        '/(\/\*.*?\*\/)/i',
    ];

    private const PATH_TRAVERSAL_PATTERNS = [
        '/\.\.\//',
        '/\.\.\\\\/',
        '/%2e%2e%2f/',
        '/%2e%2e%5c/',
        '/\.\.\x2f/',
        '/\.\.\x5c/',
    ];

    private const COMMAND_INJECTION_PATTERNS = [
        '/(\||\;|\&|\$\(|\`)/i',
        '/(nc\s|netcat\s)/i',
        '/(wget\s|curl\s)/i',
        '/(chmod\s|chown\s)/i',
        '/(rm\s|del\s)/i',
        '/(cat\s|type\s)/i',
        '/(echo\s.*\>)/i',
        '/(bash\s|sh\s|cmd\s)/i',
    ];

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle an incoming request with input sanitization.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $mode Sanitization mode: 'strict', 'moderate', 'basic'
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $mode = 'moderate')
    {
        $startTime = microtime(true);
        $detectedThreats = [];

        try {
            // Validate total input size
            if (!$this->validateInputSize($request)) {
                return $this->handleThreat($request, 'input_size_exceeded', [
                    'input_size' => $this->calculateInputSize($request),
                    'max_allowed' => self::MAX_TOTAL_INPUT_SIZE,
                ]);
            }

            // Scan for malicious patterns
            $scanResults = $this->scanForMaliciousPatterns($request);
            if (!empty($scanResults['threats'])) {
                $detectedThreats = $scanResults['threats'];

                // In strict mode, block any detected threats
                if ($mode === 'strict' && !empty($detectedThreats)) {
                    return $this->handleThreat($request, 'malicious_input_detected', [
                        'threats' => $detectedThreats,
                        'mode' => $mode,
                    ]);
                }

                // In moderate mode, sanitize and log
                if ($mode === 'moderate') {
                    $this->sanitizeRequest($request, $scanResults['details']);
                }
            }

            // Log suspicious activity if threats were detected
            if (!empty($detectedThreats)) {
                $this->auditLogger->logSecurityEvent([
                    'event' => 'suspicious_input_detected',
                    'threats' => $detectedThreats,
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'mode' => $mode,
                    'action_taken' => $mode === 'strict' ? 'blocked' : 'sanitized',
                ]);
            }

            // Proceed with the request
            $response = $next($request);

            // Log processing metrics
            $processingTime = round((microtime(true) - $startTime) * 1000, 2);
            if ($processingTime > 100) { // Log slow sanitization
                Log::debug('Slow input sanitization', [
                    'processing_time_ms' => $processingTime,
                    'endpoint' => $request->path(),
                    'input_size' => $this->calculateInputSize($request),
                ]);
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('Input sanitization middleware error', [
                'error' => $e->getMessage(),
                'endpoint' => $request->path(),
                'trace' => $e->getTraceAsString(),
            ]);

            // In case of error, proceed with caution
            return $next($request);
        }
    }

    /**
     * Validate total input size to prevent DoS attacks.
     */
    private function validateInputSize(Request $request): bool
    {
        $totalSize = $this->calculateInputSize($request);
        return $totalSize <= self::MAX_TOTAL_INPUT_SIZE;
    }

    /**
     * Calculate total size of all input data.
     */
    private function calculateInputSize(Request $request): int
    {
        $size = 0;

        // Calculate size of all input data
        foreach ($request->all() as $key => $value) {
            $size += strlen($key);
            $size += $this->getValueSize($value);
        }

        // Add headers size for completeness
        foreach ($request->headers->all() as $name => $values) {
            $size += strlen($name);
            foreach ((array) $values as $value) {
                $size += strlen($value);
            }
        }

        return $size;
    }

    /**
     * Get size of a value (recursive for arrays).
     */
    private function getValueSize($value): int
    {
        if (is_array($value)) {
            $size = 0;
            foreach ($value as $item) {
                $size += $this->getValueSize($item);
            }
            return $size;
        }

        return strlen((string) $value);
    }

    /**
     * Scan input for malicious patterns.
     */
    private function scanForMaliciousPatterns(Request $request): array
    {
        $threats = [];
        $details = [];

        // Scan all input fields
        foreach ($request->all() as $key => $value) {
            $fieldThreats = $this->scanValue($key, $value);
            if (!empty($fieldThreats)) {
                $threats = array_merge($threats, $fieldThreats);
                $details[$key] = $fieldThreats;
            }
        }

        // Scan headers for malicious content
        foreach ($request->headers->all() as $name => $values) {
            foreach ((array) $values as $value) {
                $headerThreats = $this->scanValue("header:{$name}", $value);
                if (!empty($headerThreats)) {
                    $threats = array_merge($threats, $headerThreats);
                    $details["header:{$name}"] = $headerThreats;
                }
            }
        }

        return [
            'threats' => array_unique($threats),
            'details' => $details,
        ];
    }

    /**
     * Scan a single value for threats (recursive for arrays).
     */
    private function scanValue(string $key, $value): array
    {
        $threats = [];

        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                $subThreats = $this->scanValue("{$key}[{$subKey}]", $subValue);
                $threats = array_merge($threats, $subThreats);
            }
            return $threats;
        }

        // Handle different value types safely
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                $stringValue = (string) $value;
            } else {
                // Skip objects that can't be converted to string
                return $threats;
            }
        } elseif (is_null($value)) {
            $stringValue = '';
        } elseif (is_bool($value)) {
            $stringValue = $value ? '1' : '0';
        } else {
            $stringValue = (string) $value;
        }

        // Check input length
        if (strlen($stringValue) > self::MAX_INPUT_LENGTH) {
            $threats[] = 'excessive_input_length';
        }

        // Check for XSS patterns
        foreach (self::XSS_PATTERNS as $pattern) {
            if (preg_match($pattern, $stringValue)) {
                $threats[] = 'xss_attempt';
                break;
            }
        }

        // Check for SQL injection patterns
        foreach (self::SQL_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $stringValue)) {
                $threats[] = 'sql_injection_attempt';
                break;
            }
        }

        // Check for path traversal patterns
        foreach (self::PATH_TRAVERSAL_PATTERNS as $pattern) {
            if (preg_match($pattern, $stringValue)) {
                $threats[] = 'path_traversal_attempt';
                break;
            }
        }

        // Check for command injection patterns
        foreach (self::COMMAND_INJECTION_PATTERNS as $pattern) {
            if (preg_match($pattern, $stringValue)) {
                $threats[] = 'command_injection_attempt';
                break;
            }
        }

        // Check for suspicious encoding
        if ($this->hasSuspiciousEncoding($stringValue)) {
            $threats[] = 'suspicious_encoding';
        }

        // Check for control characters
        if ($this->hasControlCharacters($stringValue)) {
            $threats[] = 'control_characters';
        }

        return $threats;
    }

    /**
     * Check for suspicious character encoding.
     */
    private function hasSuspiciousEncoding(string $value): bool
    {
        // Ensure we have a valid string and not empty
        if (empty($value) || !is_string($value)) {
            return false;
        }

        // Check for URL encoding of dangerous characters
        $suspiciousEncodings = [
            '%3c', '%3e', '%22', '%27', '%28', '%29', '%3b', '%7c', // <>"'();|
            '%00', '%0a', '%0d', '%09', // null, newline, carriage return, tab
            '%2e%2e', '%5c', '%2f', // .., \, /
        ];

        $lowerValue = strtolower((string) $value);
        foreach ($suspiciousEncodings as $encoding) {
            if (str_contains($lowerValue, $encoding)) {
                return true;
            }
        }

        // Check for double encoding
        if (preg_match('/%[0-9a-f]{2}%[0-9a-f]{2}/i', $value)) {
            return true;
        }

        return false;
    }

    /**
     * Check for control characters.
     */
    private function hasControlCharacters(string $value): bool
    {
        // Ensure we have a valid string
        if (empty($value) || !is_string($value)) {
            return false;
        }

        // Check for null bytes and other control characters
        return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', (string) $value) === 1;
    }

    /**
     * Sanitize request data.
     */
    private function sanitizeRequest(Request $request, array $threatDetails): void
    {
        $sanitizedInput = [];

        foreach ($request->all() as $key => $value) {
            if (isset($threatDetails[$key])) {
                $sanitizedInput[$key] = $this->sanitizeValue($value, $threatDetails[$key]);
            } else {
                $sanitizedInput[$key] = $value;
            }
        }

        // Replace request input with sanitized version
        $request->replace($sanitizedInput);
    }

    /**
     * Sanitize a single value based on detected threats.
     */
    private function sanitizeValue($value, array $threats)
    {
        if (is_array($value)) {
            return array_map(fn($item) => $this->sanitizeValue($item, $threats), $value);
        }

        $sanitized = (string) $value;

        // Apply sanitization based on threat types
        if (in_array('xss_attempt', $threats)) {
            $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (in_array('sql_injection_attempt', $threats)) {
            $sanitized = preg_replace('/[\'";\\\\]/', '', $sanitized);
        }

        if (in_array('path_traversal_attempt', $threats)) {
            $sanitized = str_replace(['../', '..\\', '%2e%2e%2f', '%2e%2e%5c'], '', $sanitized);
        }

        if (in_array('command_injection_attempt', $threats)) {
            $sanitized = preg_replace('/[|;&$`]/', '', $sanitized);
        }

        if (in_array('control_characters', $threats)) {
            $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        }

        if (in_array('excessive_input_length', $threats)) {
            $sanitized = substr($sanitized, 0, self::MAX_INPUT_LENGTH);
        }

        return $sanitized;
    }

    /**
     * Handle detected security threats.
     */
    private function handleThreat(Request $request, string $threatType, array $details): \Illuminate\Http\JsonResponse
    {
        $this->auditLogger->logSecurityEvent([
            'event' => 'input_validation_violation',
            'threat_type' => $threatType,
            'details' => $details,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'blocked' => true,
        ]);

        Log::warning('Input validation violation detected', [
            'threat_type' => $threatType,
            'endpoint' => $request->path(),
            'ip' => $request->ip(),
            'details' => $details,
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Invalid input detected',
            'error' => 'Request blocked due to security policy violation',
        ], 400);
    }

    /**
     * Get sanitization statistics.
     */
    public function getSanitizationStats(): array
    {
        // This would require additional tracking mechanisms
        return [
            'total_requests_processed' => 0,
            'threats_detected_today' => 0,
            'requests_blocked_today' => 0,
            'most_common_threats' => [],
        ];
    }

    /**
     * Add custom pattern to threat detection.
     */
    public function addCustomPattern(string $category, string $pattern): bool
    {
        try {
            // Validate pattern is a valid regex
            if (@preg_match($pattern, '') === false) {
                return false;
            }

            // This would need to be implemented based on your configuration management
            $this->auditLogger->logSecurityEvent([
                'event' => 'custom_security_pattern_added',
                'category' => $category,
                'pattern' => $pattern,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to add custom security pattern', [
                'category' => $category,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}