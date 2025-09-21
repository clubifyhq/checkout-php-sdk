<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * API CSRF Protection Middleware
 *
 * Enhanced CSRF protection for API endpoints with support for:
 * - Token-based CSRF protection
 * - Double-submit cookie pattern
 * - Custom header validation
 * - Origin and referer checking
 * - Rate limiting for token generation
 *
 * @package App\Http\Middleware
 */
class ApiCsrfMiddleware
{
    private AuditLogger $auditLogger;

    // Configuration constants
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 hour
    private const MAX_TOKENS_PER_IP = 10;
    private const CSRF_HEADER_NAME = 'X-CSRF-Token';
    private const CSRF_COOKIE_NAME = 'csrf_token';

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle an incoming request with CSRF validation.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $method HTTP methods that require CSRF (default: POST,PUT,PATCH,DELETE)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $methods = null)
    {
        $protectedMethods = $methods
            ? explode(',', strtoupper($methods))
            : ['POST', 'PUT', 'PATCH', 'DELETE'];

        // Check if current method requires CSRF protection
        if (!in_array($request->method(), $protectedMethods)) {
            return $next($request);
        }

        // Skip CSRF for certain safe endpoints if configured
        if ($this->shouldSkipCsrf($request)) {
            return $next($request);
        }

        // Validate CSRF token
        $validation = $this->validateCsrfToken($request);
        if (!$validation['valid']) {
            return $this->handleCsrfFailure($request, $validation['reason']);
        }

        // Log successful CSRF validation
        $this->auditLogger->logSecurityEvent([
            'event' => 'csrf_validation_success',
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $next($request);
    }

    /**
     * Validate CSRF token using multiple methods.
     */
    private function validateCsrfToken(Request $request): array
    {
        // Method 1: Header-based token validation
        $headerToken = $request->header(self::CSRF_HEADER_NAME);
        if ($headerToken && $this->isValidToken($headerToken, $request)) {
            return ['valid' => true, 'method' => 'header'];
        }

        // Method 2: Double-submit cookie pattern
        $cookieToken = $request->cookie(self::CSRF_COOKIE_NAME);
        $submittedToken = $request->input('_token') ?? $request->header(self::CSRF_HEADER_NAME);

        if ($cookieToken && $submittedToken && $this->validateDoubleSubmit($cookieToken, $submittedToken, $request)) {
            return ['valid' => true, 'method' => 'double_submit'];
        }

        // Method 3: Origin/Referer validation (fallback)
        if ($this->validateOrigin($request)) {
            return ['valid' => true, 'method' => 'origin'];
        }

        // Determine specific failure reason
        $reason = $this->determineCsrfFailureReason($request, $headerToken, $cookieToken, $submittedToken);

        return ['valid' => false, 'reason' => $reason];
    }

    /**
     * Validate token against cache and request context.
     */
    private function isValidToken(string $token, Request $request): bool
    {
        $cacheKey = $this->getTokenCacheKey($token);
        $tokenData = Cache::get($cacheKey);

        if (!$tokenData) {
            return false;
        }

        // Validate token hasn't expired
        if (now()->isAfter($tokenData['expires_at'])) {
            Cache::forget($cacheKey);
            return false;
        }

        // Validate token is bound to same session/IP for security
        if ($tokenData['ip'] !== $request->ip()) {
            return false;
        }

        if ($tokenData['session_id'] !== session()->getId()) {
            return false;
        }

        // Update last used timestamp
        $tokenData['last_used_at'] = now()->toISOString();
        Cache::put($cacheKey, $tokenData, self::TOKEN_LIFETIME);

        return true;
    }

    /**
     * Validate double-submit cookie pattern.
     */
    private function validateDoubleSubmit(string $cookieToken, string $submittedToken, Request $request): bool
    {
        // Tokens must match exactly
        if (!hash_equals($cookieToken, $submittedToken)) {
            return false;
        }

        // Validate the token itself
        return $this->isValidToken($cookieToken, $request);
    }

    /**
     * Validate request origin and referer headers.
     */
    private function validateOrigin(Request $request): bool
    {
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');
        $host = $request->getHost();

        // Check Origin header
        if ($origin) {
            $originHost = parse_url($origin, PHP_URL_HOST);
            if ($originHost === $host) {
                return true;
            }
        }

        // Check Referer header as fallback
        if ($referer) {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            if ($refererHost === $host) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine specific CSRF failure reason for better logging.
     */
    private function determineCsrfFailureReason(
        Request $request,
        ?string $headerToken,
        ?string $cookieToken,
        ?string $submittedToken
    ): string {
        if (!$headerToken && !$submittedToken) {
            return 'no_token_provided';
        }

        if ($headerToken && !$this->tokenExistsInCache($headerToken)) {
            return 'invalid_header_token';
        }

        if ($submittedToken && !$this->tokenExistsInCache($submittedToken)) {
            return 'invalid_submitted_token';
        }

        if ($cookieToken && $submittedToken && $cookieToken !== $submittedToken) {
            return 'token_mismatch';
        }

        if (!$this->validateOrigin($request)) {
            return 'invalid_origin';
        }

        return 'unknown_csrf_failure';
    }

    /**
     * Check if token exists in cache (without validating other conditions).
     */
    private function tokenExistsInCache(string $token): bool
    {
        return Cache::has($this->getTokenCacheKey($token));
    }

    /**
     * Handle CSRF validation failure.
     */
    private function handleCsrfFailure(Request $request, string $reason): Response
    {
        // Log the CSRF failure
        $this->auditLogger->logSecurityEvent([
            'event' => 'csrf_validation_failure',
            'reason' => $reason,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'origin' => $request->header('Origin'),
            'referer' => $request->header('Referer'),
            'has_header_token' => $request->hasHeader(self::CSRF_HEADER_NAME),
            'has_cookie_token' => $request->hasCookie(self::CSRF_COOKIE_NAME),
        ]);

        Log::warning('CSRF validation failed', [
            'reason' => $reason,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'CSRF validation failed',
            'error' => 'Invalid or missing CSRF token',
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Check if CSRF validation should be skipped for this request.
     */
    private function shouldSkipCsrf(Request $request): bool
    {
        $skipPaths = config('auth.csrf_skip_paths', []);

        foreach ($skipPaths as $path) {
            if (Str::is($path, $request->path())) {
                return true;
            }
        }

        // Skip for certain content types that are typically safe
        $contentType = $request->header('Content-Type', '');
        $safeMimeTypes = [
            'application/json',
            'application/xml',
            'text/xml',
        ];

        foreach ($safeMimeTypes as $mimeType) {
            if (str_starts_with($contentType, $mimeType)) {
                // Only skip if request has valid API authentication
                return $this->hasValidApiAuthentication($request);
            }
        }

        return false;
    }

    /**
     * Check if request has valid API authentication.
     */
    private function hasValidApiAuthentication(Request $request): bool
    {
        // Check for Bearer token
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return true;
        }

        // Check for API key header
        if ($request->header('X-API-Key')) {
            return true;
        }

        // Check for other authentication methods as needed
        return false;
    }

    /**
     * Generate CSRF token cache key.
     */
    private function getTokenCacheKey(string $token): string
    {
        return 'csrf_token:' . hash('sha256', $token);
    }

    /**
     * Generate new CSRF token.
     */
    public function generateToken(Request $request): array
    {
        $ip = $request->ip();
        $sessionId = session()->getId();

        // Check rate limiting
        if (!$this->canGenerateToken($ip)) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded for token generation',
            ];
        }

        // Generate secure token
        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $expiresAt = now()->addSeconds(self::TOKEN_LIFETIME);

        // Store token data
        $tokenData = [
            'token' => $token,
            'ip' => $ip,
            'session_id' => $sessionId,
            'created_at' => now()->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
            'last_used_at' => null,
        ];

        $cacheKey = $this->getTokenCacheKey($token);
        Cache::put($cacheKey, $tokenData, self::TOKEN_LIFETIME);

        // Track token generation for rate limiting
        $this->trackTokenGeneration($ip);

        // Log token generation
        $this->auditLogger->logSecurityEvent([
            'event' => 'csrf_token_generated',
            'ip_address' => $ip,
            'session_id' => $sessionId,
            'expires_at' => $expiresAt->toISOString(),
        ]);

        return [
            'success' => true,
            'token' => $token,
            'expires_at' => $expiresAt->toISOString(),
        ];
    }

    /**
     * Check if IP can generate more tokens (rate limiting).
     */
    private function canGenerateToken(string $ip): bool
    {
        $cacheKey = 'csrf_token_count:' . $ip;
        $tokenCount = Cache::get($cacheKey, 0);

        return $tokenCount < self::MAX_TOKENS_PER_IP;
    }

    /**
     * Track token generation for rate limiting.
     */
    private function trackTokenGeneration(string $ip): void
    {
        $cacheKey = 'csrf_token_count:' . $ip;
        $currentCount = Cache::get($cacheKey, 0);
        Cache::put($cacheKey, $currentCount + 1, 3600); // 1 hour window
    }

    /**
     * Revoke a specific CSRF token.
     */
    public function revokeToken(string $token): bool
    {
        $cacheKey = $this->getTokenCacheKey($token);

        if (Cache::has($cacheKey)) {
            Cache::forget($cacheKey);

            $this->auditLogger->logSecurityEvent([
                'event' => 'csrf_token_revoked',
                'token_hash' => hash('sha256', $token),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Get CSRF token statistics.
     */
    public function getTokenStatistics(): array
    {
        // This would require additional tracking mechanisms
        // For now, return basic structure
        return [
            'total_active_tokens' => 0, // Would need to count cache entries
            'tokens_generated_today' => 0, // Would need daily tracking
            'tokens_validated_today' => 0, // Would need validation tracking
            'failed_validations_today' => 0, // Would need failure tracking
        ];
    }
}