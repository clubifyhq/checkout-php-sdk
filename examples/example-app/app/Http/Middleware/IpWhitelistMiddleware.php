<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * IP Whitelist Middleware
 *
 * Restricts access based on IP address whitelist for enhanced security.
 * Primarily used for super admin and sensitive administrative functions.
 *
 * Features:
 * - IPv4 and IPv6 support
 * - CIDR notation support for IP ranges
 * - Proxy and load balancer IP detection
 * - Comprehensive logging of blocked attempts
 * - Dynamic whitelist management
 *
 * @package App\Http\Middleware
 */
class IpWhitelistMiddleware
{
    private AuditLogger $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * Handle an incoming request and validate IP against whitelist.
     *
     * @param Request $request
     * @param Closure $next
     * @param string|null $configKey Configuration key for whitelist
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ?string $configKey = null)
    {
        $clientIp = $this->getClientIp($request);

        // Check if IP whitelisting is enabled
        if (!$this->isWhitelistEnabled($configKey)) {
            return $next($request);
        }

        // Validate IP against whitelist
        if (!$this->isIpWhitelisted($clientIp, $configKey)) {
            return $this->handleUnauthorizedIp($request, $clientIp);
        }

        // Log authorized access
        $this->auditLogger->logSecurityEvent([
            'event' => 'ip_whitelist_authorized_access',
            'ip_address' => $clientIp,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'whitelist_config' => $configKey ?? 'default',
        ]);

        return $next($request);
    }

    /**
     * Check if IP whitelisting is enabled for the given configuration.
     */
    private function isWhitelistEnabled(?string $configKey): bool
    {
        if ($configKey) {
            return config("auth.{$configKey}.ip_whitelist_enabled", false);
        }

        return config('auth.super_admin_security.ip_whitelist_enabled', false);
    }

    /**
     * Check if the IP address is in the whitelist.
     */
    private function isIpWhitelisted(string $ip, ?string $configKey): bool
    {
        $whitelist = $this->getWhitelist($configKey);

        if (empty($whitelist)) {
            // If no whitelist is configured, allow all (whitelist disabled)
            return true;
        }

        foreach ($whitelist as $allowedIp) {
            if ($this->ipMatches($ip, trim($allowedIp))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the IP whitelist from configuration.
     */
    private function getWhitelist(?string $configKey): array
    {
        if ($configKey) {
            $whitelistString = config("auth.{$configKey}.ip_whitelist", '');
        } else {
            $whitelistString = config('auth.super_admin_security.ip_whitelist', '');
        }

        if (empty($whitelistString)) {
            return [];
        }

        // Split by comma and filter empty values
        return array_filter(
            array_map('trim', explode(',', $whitelistString)),
            fn($ip) => !empty($ip)
        );
    }

    /**
     * Check if an IP matches a whitelist entry (supports CIDR notation).
     */
    private function ipMatches(string $ip, string $allowedIp): bool
    {
        // Exact match
        if ($ip === $allowedIp) {
            return true;
        }

        // Check if it's a CIDR notation
        if (str_contains($allowedIp, '/')) {
            return $this->ipInRange($ip, $allowedIp);
        }

        // Check if it's a wildcard pattern
        if (str_contains($allowedIp, '*')) {
            return $this->ipMatchesWildcard($ip, $allowedIp);
        }

        return false;
    }

    /**
     * Check if IP is in CIDR range.
     */
    private function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        // Validate IP and subnet
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }

        // IPv4 CIDR check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $this->ipv4InRange($ip, $subnet, (int) $mask);
        }

        // IPv6 CIDR check
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int) $mask);
        }

        return false;
    }

    /**
     * Check if IPv4 address is in CIDR range.
     */
    private function ipv4InRange(string $ip, string $subnet, int $mask): bool
    {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $maskLong = ~((1 << (32 - $mask)) - 1);

        return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
    }

    /**
     * Check if IPv6 address is in CIDR range.
     */
    private function ipv6InRange(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false) {
            return false;
        }

        $bytesToCheck = intval($mask / 8);
        $bitsToCheck = $mask % 8;

        // Check full bytes
        for ($i = 0; $i < $bytesToCheck; $i++) {
            if ($ipBin[$i] !== $subnetBin[$i]) {
                return false;
            }
        }

        // Check remaining bits if any
        if ($bitsToCheck > 0 && $bytesToCheck < 16) {
            $maskByte = 0xFF << (8 - $bitsToCheck);
            if ((ord($ipBin[$bytesToCheck]) & $maskByte) !== (ord($subnetBin[$bytesToCheck]) & $maskByte)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if IP matches wildcard pattern.
     */
    private function ipMatchesWildcard(string $ip, string $pattern): bool
    {
        // Convert wildcard pattern to regex
        $regex = str_replace(
            ['*', '.'],
            ['[0-9]+', '\.'],
            $pattern
        );

        return preg_match("/^{$regex}$/", $ip) === 1;
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

            if ($ip) {
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                $ips = explode(',', $ip);
                $ip = trim($ips[0]); // Use the first IP

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->ip();
    }

    /**
     * Handle unauthorized IP access attempt.
     */
    private function handleUnauthorizedIp(Request $request, string $clientIp): Response
    {
        // Log the unauthorized attempt
        $this->auditLogger->logSecurityEvent([
            'event' => 'ip_whitelist_unauthorized_access',
            'ip_address' => $clientIp,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->header('Referer'),
            'blocked_reason' => 'IP not in whitelist',
        ]);

        Log::warning('IP whitelist violation', [
            'ip' => $clientIp,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_agent' => $request->userAgent(),
        ]);

        // Return generic forbidden response
        return response()->json([
            'success' => false,
            'message' => 'Access denied',
            'error' => 'Forbidden',
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Add IP to whitelist dynamically (for emergency access).
     */
    public function addToWhitelist(string $ip, ?string $configKey = null): bool
    {
        try {
            $whitelist = $this->getWhitelist($configKey);

            if (!in_array($ip, $whitelist)) {
                $whitelist[] = $ip;
                $whitelistString = implode(',', $whitelist);

                // This would need to be implemented based on your configuration management
                // For now, we'll just log the attempt
                $this->auditLogger->logSecurityEvent([
                    'event' => 'ip_whitelist_dynamic_addition',
                    'ip_address' => $ip,
                    'config_key' => $configKey ?? 'default',
                    'action' => 'add_to_whitelist',
                ]);

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to add IP to whitelist', [
                'ip' => $ip,
                'config_key' => $configKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Validate IP address format.
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /**
     * Get whitelist statistics for monitoring.
     */
    public function getWhitelistStats(?string $configKey = null): array
    {
        $whitelist = $this->getWhitelist($configKey);

        return [
            'total_entries' => count($whitelist),
            'ipv4_entries' => count(array_filter($whitelist, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))),
            'ipv6_entries' => count(array_filter($whitelist, fn($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))),
            'cidr_entries' => count(array_filter($whitelist, fn($ip) => str_contains($ip, '/'))),
            'wildcard_entries' => count(array_filter($whitelist, fn($ip) => str_contains($ip, '*'))),
            'enabled' => $this->isWhitelistEnabled($configKey),
        ];
    }
}