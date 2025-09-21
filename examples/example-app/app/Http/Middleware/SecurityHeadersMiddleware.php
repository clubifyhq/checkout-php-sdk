<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Security Headers Middleware
 *
 * Applies comprehensive security headers to protect against common
 * web vulnerabilities and enhance overall application security.
 *
 * Security Headers Applied:
 * - Content Security Policy (CSP)
 * - X-Frame-Options (Clickjacking protection)
 * - X-Content-Type-Options (MIME sniffing protection)
 * - X-XSS-Protection (XSS filtering)
 * - Strict-Transport-Security (HTTPS enforcement)
 * - Referrer-Policy (Referrer information control)
 * - Permissions-Policy (Feature policy)
 *
 * @package App\Http\Middleware
 */
class SecurityHeadersMiddleware
{
    /**
     * Handle an incoming request and apply security headers.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Apply security headers
        $this->applySecurityHeaders($response, $request);

        return $response;
    }

    /**
     * Apply comprehensive security headers to the response.
     */
    private function applySecurityHeaders($response, Request $request): void
    {
        $headers = config('auth.security_headers', []);

        // Apply configured headers
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // Apply conditional headers based on context
        $this->applyConditionalHeaders($response, $request);

        // Remove potentially revealing headers
        $this->removeRevealingHeaders($response);
    }

    /**
     * Apply conditional security headers based on request context.
     */
    private function applyConditionalHeaders($response, Request $request): void
    {
        // Enhanced CSP for super admin routes
        if ($this->isSuperAdminRoute($request)) {
            $response->headers->set(
                'Content-Security-Policy',
                $this->getSuperAdminCSP()
            );

            // Stricter frame options for admin
            $response->headers->set('X-Frame-Options', 'DENY');

            // Add cache control for sensitive pages
            $response->headers->set(
                'Cache-Control',
                'no-store, no-cache, must-revalidate, private'
            );
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        // HTTPS enforcement
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // API-specific headers
        if ($this->isApiRoute($request)) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
        }
    }

    /**
     * Get enhanced Content Security Policy for super admin routes.
     */
    private function getSuperAdminCSP(): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // May need adjustment based on admin UI
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "connect-src 'self'",
            "media-src 'none'",
            "object-src 'none'",
            "child-src 'none'",
            "frame-src 'none'",
            "worker-src 'none'",
            "manifest-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests",
        ];

        return implode('; ', $directives);
    }

    /**
     * Remove headers that might reveal server information.
     */
    private function removeRevealingHeaders($response): void
    {
        $headersToRemove = [
            'Server',
            'X-Powered-By',
            'X-AspNet-Version',
            'X-AspNetMvc-Version',
            'X-Generator',
        ];

        foreach ($headersToRemove as $header) {
            $response->headers->remove($header);
        }
    }

    /**
     * Check if the current route is a super admin route.
     */
    private function isSuperAdminRoute(Request $request): bool
    {
        $path = $request->path();

        return str_starts_with($path, 'super-admin') ||
               str_starts_with($path, 'api/super-admin') ||
               str_contains($path, '/super-admin/');
    }

    /**
     * Check if the current route is an API route.
     */
    private function isApiRoute(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/');
    }
}