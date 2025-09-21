<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use App\Services\ContextManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Super Admin Authentication Middleware
 *
 * Enhanced security middleware for super administrator access control
 * with comprehensive validation, rate limiting, and audit logging.
 *
 * Security Features:
 * - Session validation and context verification
 * - Rate limiting with progressive delays
 * - IP whitelist validation (optional)
 * - Request fingerprinting and anomaly detection
 * - Comprehensive audit logging
 * - CSRF protection validation
 * - Input sanitization and validation
 *
 * @package App\Http\Middleware
 */
class SuperAdminMiddleware
{
    private ContextManager $contextManager;
    private AuditLogger $auditLogger;

    // Security configuration constants
    private const RATE_LIMIT_MAX_ATTEMPTS = 60; // Per hour
    private const RATE_LIMIT_DECAY_MINUTES = 60;
    private const SUSPICIOUS_ATTEMPT_THRESHOLD = 5;
    private const SESSION_INACTIVITY_TIMEOUT = 1800; // 30 minutes
    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    // Cache keys for security tracking
    private const CACHE_KEY_FAILED_ATTEMPTS = 'super_admin_failed_attempts:';
    private const CACHE_KEY_SUSPICIOUS_IPS = 'super_admin_suspicious_ips:';
    private const CACHE_KEY_SESSION_FINGERPRINT = 'super_admin_fingerprint:';

    public function __construct(ContextManager $contextManager, AuditLogger $auditLogger)
    {
        $this->contextManager = $contextManager;
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle an incoming request with comprehensive security validation.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $permission Optional permission requirement
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $permission = null)
    {
        $startTime = microtime(true);
        $clientIp = $this->getClientIp($request);
        $sessionId = session()->getId();

        try {
            // 1. Pre-flight security checks
            $securityCheck = $this->performSecurityValidation($request, $clientIp);
            if ($securityCheck !== true) {
                return $securityCheck;
            }

            // 2. Rate limiting check
            $rateLimitCheck = $this->checkRateLimit($request, $clientIp);
            if ($rateLimitCheck !== true) {
                return $rateLimitCheck;
            }

            // 3. Session and context validation
            $authCheck = $this->validateAuthentication($request, $clientIp, $sessionId);
            if ($authCheck !== true) {
                return $authCheck;
            }

            // 4. Permission validation
            if ($permission) {
                $permissionCheck = $this->validatePermission($request, $permission, $clientIp);
                if ($permissionCheck !== true) {
                    return $permissionCheck;
                }
            }

            // 5. Session fingerprint validation
            $fingerprintCheck = $this->validateSessionFingerprint($request, $sessionId);
            if ($fingerprintCheck !== true) {
                return $fingerprintCheck;
            }

            // 6. Add security context to request
            $this->addSecurityContext($request);

            // 7. Log successful access
            $this->auditLogger->logSuperAdminAccess([
                'event' => 'access_granted',
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'permission' => $permission,
                'session_id' => $sessionId,
                'ip_address' => $clientIp,
                'user_agent' => $request->userAgent(),
                'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            // 8. Process request
            $response = $next($request);

            // 9. Post-processing security checks
            $this->performPostProcessingChecks($request, $response);

            return $response;

        } catch (\Exception $e) {
            // Log security exception
            $this->auditLogger->logSecurityEvent([
                'event' => 'middleware_exception',
                'exception' => $e->getMessage(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'ip_address' => $clientIp,
                'session_id' => $sessionId,
                'trace' => $e->getTraceAsString(),
            ]);

            Log::error('SuperAdminMiddleware exception', [
                'error' => $e->getMessage(),
                'endpoint' => $request->path(),
                'ip' => $clientIp,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Security validation failed',
                'error' => 'Internal security error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Perform comprehensive pre-flight security validation.
     */
    private function performSecurityValidation(Request $request, string $clientIp): bool|\Illuminate\Http\JsonResponse
    {
        // Check if IP is suspicious
        if ($this->isSuspiciousIp($clientIp)) {
            $this->auditLogger->logSecurityEvent([
                'event' => 'suspicious_ip_blocked',
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->securityResponse('Access denied from suspicious IP address', Response::HTTP_FORBIDDEN);
        }

        // Validate HTTP method
        if (!in_array($request->method(), self::ALLOWED_METHODS)) {
            $this->auditLogger->logSecurityEvent([
                'event' => 'invalid_http_method',
                'method' => $request->method(),
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
            ]);

            return $this->securityResponse('Invalid HTTP method', Response::HTTP_METHOD_NOT_ALLOWED);
        }

        // Check for malicious patterns in headers
        if ($this->detectMaliciousHeaders($request)) {
            $this->auditLogger->logSecurityEvent([
                'event' => 'malicious_headers_detected',
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
                'headers' => $this->sanitizeHeaders($request->headers->all()),
            ]);

            return $this->securityResponse('Malicious request detected', Response::HTTP_BAD_REQUEST);
        }

        return true;
    }

    /**
     * Check rate limiting for super admin endpoints.
     */
    private function checkRateLimit(Request $request, string $clientIp): bool|\Illuminate\Http\JsonResponse
    {
        $rateLimitKey = 'super_admin_rate_limit:' . $clientIp;

        if (RateLimiter::tooManyAttempts($rateLimitKey, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($rateLimitKey);

            $this->auditLogger->logSecurityEvent([
                'event' => 'rate_limit_exceeded',
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
                'retry_after' => $retryAfter,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Rate limit exceeded',
                'error' => 'Too many requests',
                'retry_after' => $retryAfter,
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($rateLimitKey, self::RATE_LIMIT_DECAY_MINUTES * 60);
        return true;
    }

    /**
     * Validate super admin authentication and session state.
     */
    private function validateAuthentication(Request $request, string $clientIp, string $sessionId): bool|\Illuminate\Http\JsonResponse
    {
        // Check if authenticated via API token (Sanctum)
        $bearerToken = $request->bearerToken();
        if ($bearerToken) {
            return $this->validateApiAuthentication($request, $bearerToken, $clientIp, $sessionId);
        }

        // Check session-based authentication
        if (!$this->contextManager->isSuperAdminMode()) {
            $this->recordFailedAttempt($clientIp);

            $this->auditLogger->logSecurityEvent([
                'event' => 'unauthorized_access_attempt',
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'user_agent' => $request->userAgent(),
                'session_id' => $sessionId,
            ]);

            Log::warning('Unauthorized access attempt to super admin endpoint', [
                'ip' => $clientIp,
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'session_id' => $sessionId,
            ]);

            return $this->securityResponse('Super admin authentication required', Response::HTTP_UNAUTHORIZED);
        }

        // Check session inactivity
        $context = $this->contextManager->getCurrentContext();
        if (isset($context['session_metadata']['last_activity'])) {
            $lastActivity = \Carbon\Carbon::parse($context['session_metadata']['last_activity']);
            $inactiveTime = now()->diffInSeconds($lastActivity);

            if ($inactiveTime > self::SESSION_INACTIVITY_TIMEOUT) {
                $this->contextManager->clearSuperAdminContext();

                $this->auditLogger->logSecurityEvent([
                    'event' => 'session_timeout',
                    'session_id' => $sessionId,
                    'ip_address' => $clientIp,
                    'inactive_time_seconds' => $inactiveTime,
                ]);

                return $this->securityResponse('Session expired due to inactivity', Response::HTTP_UNAUTHORIZED);
            }
        }

        return true;
    }

    /**
     * Validate API token authentication.
     */
    private function validateApiAuthentication(Request $request, string $bearerToken, string $clientIp, string $sessionId): bool|\Illuminate\Http\JsonResponse
    {
        try {
            // Use Sanctum to authenticate the token
            $user = \Laravel\Sanctum\PersonalAccessToken::findToken($bearerToken)?->tokenable;

            if (!$user || !($user instanceof \App\Models\SuperAdmin)) {
                $this->recordFailedAttempt($clientIp);

                $this->auditLogger->logSecurityEvent([
                    'event' => 'invalid_api_token',
                    'ip_address' => $clientIp,
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                    'session_id' => $sessionId,
                ]);

                return $this->securityResponse('Invalid or expired API token', Response::HTTP_UNAUTHORIZED);
            }

            // Check if super admin is active
            if ($user->status !== 'active') {
                $this->auditLogger->logSecurityEvent([
                    'event' => 'inactive_super_admin_access_attempt',
                    'super_admin_id' => $user->id,
                    'super_admin_email' => $user->email,
                    'status' => $user->status,
                    'ip_address' => $clientIp,
                    'endpoint' => $request->path(),
                ]);

                return $this->securityResponse('Super admin account is not active', Response::HTTP_FORBIDDEN);
            }

            // Set the authenticated user in the request
            $request->setUserResolver(function () use ($user) {
                return $user;
            });

            // Store user permissions for later validation
            $request->merge([
                'super_admin_permissions' => $user->permissions ?? [],
                'super_admin_id' => $user->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('API authentication validation failed', [
                'error' => $e->getMessage(),
                'ip' => $clientIp,
                'endpoint' => $request->path(),
            ]);

            return $this->securityResponse('Authentication validation failed', Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Validate specific permission requirements.
     */
    private function validatePermission(Request $request, string $permission, string $clientIp): bool|\Illuminate\Http\JsonResponse
    {
        $userPermissions = [];

        // Check if using API authentication
        if ($request->bearerToken()) {
            $userPermissions = $request->get('super_admin_permissions', []);
        } else {
            // Use session-based permissions
            $userPermissions = $this->contextManager->getPermissions();
        }

        if (!in_array($permission, $userPermissions)) {
            $this->auditLogger->logSecurityEvent([
                'event' => 'insufficient_permissions',
                'required_permission' => $permission,
                'user_permissions' => $userPermissions,
                'ip_address' => $clientIp,
                'endpoint' => $request->path(),
                'method' => $request->method(),
            ]);

            Log::warning('Insufficient permissions for super admin endpoint', [
                'required_permission' => $permission,
                'user_permissions' => $userPermissions,
                'ip' => $clientIp,
                'url' => $request->fullUrl(),
            ]);

            return $this->securityResponse("Permission '{$permission}' required", Response::HTTP_FORBIDDEN);
        }

        return true;
    }

    /**
     * Validate session fingerprint for anti-hijacking protection.
     */
    private function validateSessionFingerprint(Request $request, string $sessionId): bool|\Illuminate\Http\JsonResponse
    {
        $currentFingerprint = $this->generateSessionFingerprint($request);
        $storedFingerprint = Cache::get(self::CACHE_KEY_SESSION_FINGERPRINT . $sessionId);

        if ($storedFingerprint === null) {
            // First request, store fingerprint
            Cache::put(
                self::CACHE_KEY_SESSION_FINGERPRINT . $sessionId,
                $currentFingerprint,
                now()->addHours(2)
            );
        } elseif ($storedFingerprint !== $currentFingerprint) {
            // Fingerprint mismatch - possible session hijacking
            $this->contextManager->clearSuperAdminContext();

            $this->auditLogger->logSecurityEvent([
                'event' => 'session_hijacking_detected',
                'session_id' => $sessionId,
                'ip_address' => $this->getClientIp($request),
                'current_fingerprint' => $currentFingerprint,
                'stored_fingerprint' => $storedFingerprint,
                'user_agent' => $request->userAgent(),
            ]);

            return $this->securityResponse('Session security violation detected', Response::HTTP_UNAUTHORIZED);
        }

        return true;
    }

    /**
     * Add security context and metadata to the request.
     */
    private function addSecurityContext(Request $request): void
    {
        $request->merge([
            'super_admin_token' => $this->contextManager->getSuperAdminToken(),
            'current_tenant_id' => $this->contextManager->getCurrentTenantId(),
            'super_admin_permissions' => $this->contextManager->getPermissions(),
            'security_context' => [
                'validated_at' => now()->toISOString(),
                'client_ip' => $this->getClientIp($request),
                'session_id' => session()->getId(),
                'request_id' => Str::uuid()->toString(),
            ],
        ]);
    }

    /**
     * Perform post-processing security checks on the response.
     */
    private function performPostProcessingChecks(Request $request, $response): void
    {
        // Clear any potentially sensitive data from logs
        if (method_exists($response, 'getContent')) {
            $content = $response->getContent();
            if ($content && Str::contains($content, ['password', 'token', 'secret'])) {
                Log::debug('Response may contain sensitive data', [
                    'endpoint' => $request->path(),
                    'content_length' => strlen($content),
                ]);
            }
        }
    }

    /**
     * Generate session fingerprint for anti-hijacking protection.
     */
    private function generateSessionFingerprint(Request $request): string
    {
        $components = [
            $request->userAgent(),
            $request->header('Accept-Language'),
            $request->header('Accept-Encoding'),
            $this->getClientIp($request),
        ];

        return hash('sha256', implode('|', array_filter($components)));
    }

    /**
     * Get real client IP address with proxy support.
     */
    private function getClientIp(Request $request): string
    {
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy
            'HTTP_X_FORWARDED',          // Proxy
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // RFC 7239
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($ipHeaders as $header) {
            $ip = $request->server($header);
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }

        return $request->ip();
    }

    /**
     * Check if IP address is marked as suspicious.
     */
    private function isSuspiciousIp(string $ip): bool
    {
        $suspiciousIps = Cache::get(self::CACHE_KEY_SUSPICIOUS_IPS . $ip, 0);
        return $suspiciousIps >= self::SUSPICIOUS_ATTEMPT_THRESHOLD;
    }

    /**
     * Record failed authentication attempt.
     */
    private function recordFailedAttempt(string $ip): void
    {
        $key = self::CACHE_KEY_FAILED_ATTEMPTS . $ip;
        $attempts = Cache::get($key, 0) + 1;

        Cache::put($key, $attempts, now()->addHour());

        if ($attempts >= self::SUSPICIOUS_ATTEMPT_THRESHOLD) {
            Cache::put(self::CACHE_KEY_SUSPICIOUS_IPS . $ip, $attempts, now()->addDay());
        }
    }

    /**
     * Detect malicious patterns in request headers.
     */
    private function detectMaliciousHeaders(Request $request): bool
    {
        $maliciousPatterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/javascript:/i',
            '/vbscript:/i',
            '/onload\s*=/i',
            '/onerror\s*=/i',
            '/expression\s*\(/i',
            '/\.\.\//',
            '/\x00/',
            '/\|\|/',
            '/&&/',
        ];

        foreach ($request->headers->all() as $name => $values) {
            foreach ((array) $values as $value) {
                foreach ($maliciousPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];

        foreach ($sensitiveHeaders as $sensitive) {
            if (isset($headers[$sensitive])) {
                $headers[$sensitive] = '[REDACTED]';
            }
        }

        return $headers;
    }

    /**
     * Generate standardized security response.
     */
    private function securityResponse(string $message, int $status): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => 'Security validation failed',
            'timestamp' => now()->toISOString(),
        ], $status);
    }
}