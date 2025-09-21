<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

/**
 * Context Manager Service
 *
 * Manages session context for Super Admin functionality, including
 * tenant switching, authentication state, and context preservation.
 *
 * @package App\Services
 */
class ContextManager
{
    private const SESSION_PREFIX = 'clubify_context_';
    private const CACHE_PREFIX = 'clubify_ctx_';
    private const SUPER_ADMIN_SESSION_KEY = 'super_admin_session';
    private const CURRENT_TENANT_KEY = 'current_tenant_id';
    private const PERMISSIONS_KEY = 'super_admin_permissions';
    private const SESSION_METADATA_KEY = 'session_metadata';

    private const DEFAULT_SESSION_TTL = 3600; // 1 hour
    private const TENANT_SWITCH_TTL = 1800; // 30 minutes

    /**
     * Set Super Admin context in session
     *
     * @param string $superAdminToken Super admin authentication token
     * @param array $permissions List of super admin permissions
     * @param int|null $ttl Session TTL in seconds
     * @return bool Success status
     */
    public function setSuperAdminContext(string $superAdminToken, array $permissions, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? self::DEFAULT_SESSION_TTL;
            $expiresAt = Carbon::now()->addSeconds($ttl);

            $sessionData = [
                'token' => $superAdminToken,
                'permissions' => $permissions,
                'mode' => 'super_admin',
                'started_at' => Carbon::now()->toISOString(),
                'expires_at' => $expiresAt->toISOString(),
                'ttl' => $ttl,
            ];

            // Store in Laravel session
            Session::put(self::SESSION_PREFIX . self::SUPER_ADMIN_SESSION_KEY, $sessionData);

            // Also store in cache for cross-request persistence
            Cache::put(
                self::CACHE_PREFIX . self::SUPER_ADMIN_SESSION_KEY . '_' . session()->getId(),
                $sessionData,
                $ttl
            );

            // Store session metadata
            $this->setSessionMetadata([
                'super_admin_active' => true,
                'last_activity' => Carbon::now()->toISOString(),
                'session_id' => session()->getId(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('Super Admin context established', [
                'session_id' => session()->getId(),
                'permissions_count' => count($permissions),
                'expires_at' => $expiresAt->toISOString(),
                'ttl' => $ttl,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to set Super Admin context', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return false;
        }
    }

    /**
     * Get Super Admin token from current session
     *
     * @return string|null Super admin token or null if not in super admin mode
     */
    public function getSuperAdminToken(): ?string
    {
        try {
            $sessionData = $this->getSuperAdminSessionData();

            if (!$sessionData || !$this->isSessionValid($sessionData)) {
                return null;
            }

            // Update last activity
            $this->updateLastActivity();

            return $sessionData['token'] ?? null;

        } catch (Exception $e) {
            Log::error('Failed to get Super Admin token', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return null;
        }
    }

    /**
     * Check if current session is in Super Admin mode
     *
     * @return bool True if in super admin mode
     */
    public function isSuperAdminMode(): bool
    {
        $sessionData = $this->getSuperAdminSessionData();

        return $sessionData &&
               ($sessionData['mode'] ?? '') === 'super_admin' &&
               $this->isSessionValid($sessionData);
    }

    /**
     * Set current tenant context
     *
     * @param string $tenantId Tenant ID to switch to
     * @param int|null $ttl Context TTL in seconds
     * @return bool Success status
     */
    public function setCurrentTenant(string $tenantId, ?int $ttl = null): bool
    {
        if (!$this->isSuperAdminMode()) {
            Log::warning('Attempted to set tenant context without super admin mode', [
                'tenant_id' => $tenantId,
                'session_id' => session()->getId(),
            ]);

            return false;
        }

        try {
            $ttl = $ttl ?? self::TENANT_SWITCH_TTL;

            $tenantContext = [
                'tenant_id' => $tenantId,
                'switched_at' => Carbon::now()->toISOString(),
                'expires_at' => Carbon::now()->addSeconds($ttl)->toISOString(),
                'ttl' => $ttl,
            ];

            Session::put(self::SESSION_PREFIX . self::CURRENT_TENANT_KEY, $tenantContext);

            // Update session metadata
            $metadata = $this->getSessionMetadata();
            $metadata['current_tenant_id'] = $tenantId;
            $metadata['tenant_switched_at'] = Carbon::now()->toISOString();
            $this->setSessionMetadata($metadata);

            Log::info('Tenant context set', [
                'tenant_id' => $tenantId,
                'session_id' => session()->getId(),
                'expires_at' => $tenantContext['expires_at'],
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to set tenant context', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return false;
        }
    }

    /**
     * Get current tenant ID
     *
     * @return string|null Current tenant ID or null if not set
     */
    public function getCurrentTenantId(): ?string
    {
        try {
            $tenantContext = Session::get(self::SESSION_PREFIX . self::CURRENT_TENANT_KEY);

            if (!$tenantContext || !$this->isTenantContextValid($tenantContext)) {
                return null;
            }

            return $tenantContext['tenant_id'] ?? null;

        } catch (Exception $e) {
            Log::error('Failed to get current tenant ID', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return null;
        }
    }

    /**
     * Check if currently in tenant context
     *
     * @return bool True if in tenant context
     */
    public function isInTenantContext(): bool
    {
        return !empty($this->getCurrentTenantId());
    }

    /**
     * Get current context information
     *
     * @return array Current context data
     */
    public function getCurrentContext(): array
    {
        try {
            $superAdminData = $this->getSuperAdminSessionData();
            $tenantContext = Session::get(self::SESSION_PREFIX . self::CURRENT_TENANT_KEY);
            $metadata = $this->getSessionMetadata();

            $context = [
                'mode' => 'normal',
                'super_admin_active' => false,
                'tenant_context_active' => false,
                'session_valid' => false,
                'permissions' => [],
                'current_tenant_id' => null,
                'session_metadata' => $metadata,
            ];

            if ($superAdminData && $this->isSessionValid($superAdminData)) {
                $context['mode'] = 'super_admin';
                $context['super_admin_active'] = true;
                $context['session_valid'] = true;
                $context['permissions'] = $superAdminData['permissions'] ?? [];
                $context['session_expires_at'] = $superAdminData['expires_at'] ?? null;
                $context['session_started_at'] = $superAdminData['started_at'] ?? null;

                if ($tenantContext && $this->isTenantContextValid($tenantContext)) {
                    $context['mode'] = 'tenant_impersonation';
                    $context['tenant_context_active'] = true;
                    $context['current_tenant_id'] = $tenantContext['tenant_id'] ?? null;
                    $context['tenant_switched_at'] = $tenantContext['switched_at'] ?? null;
                    $context['tenant_context_expires_at'] = $tenantContext['expires_at'] ?? null;
                }
            }

            return $context;

        } catch (Exception $e) {
            Log::error('Failed to get current context', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return [
                'mode' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear tenant context (return to super admin mode)
     *
     * @return bool Success status
     */
    public function clearTenantContext(): bool
    {
        try {
            Session::forget(self::SESSION_PREFIX . self::CURRENT_TENANT_KEY);

            // Update session metadata
            $metadata = $this->getSessionMetadata();
            unset($metadata['current_tenant_id'], $metadata['tenant_switched_at']);
            $metadata['tenant_context_cleared_at'] = Carbon::now()->toISOString();
            $this->setSessionMetadata($metadata);

            Log::info('Tenant context cleared', [
                'session_id' => session()->getId(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to clear tenant context', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return false;
        }
    }

    /**
     * Clear all Super Admin context
     *
     * @return bool Success status
     */
    public function clearSuperAdminContext(): bool
    {
        try {
            $sessionId = session()->getId();

            // Clear session data
            Session::forget(self::SESSION_PREFIX . self::SUPER_ADMIN_SESSION_KEY);
            Session::forget(self::SESSION_PREFIX . self::CURRENT_TENANT_KEY);
            Session::forget(self::SESSION_PREFIX . self::PERMISSIONS_KEY);
            Session::forget(self::SESSION_PREFIX . self::SESSION_METADATA_KEY);

            // Clear cache data
            Cache::forget(self::CACHE_PREFIX . self::SUPER_ADMIN_SESSION_KEY . '_' . $sessionId);

            Log::info('Super Admin context cleared', [
                'session_id' => $sessionId,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to clear Super Admin context', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return false;
        }
    }

    /**
     * Check if user has specific permission
     *
     * @param string $permission Permission to check
     * @return bool True if permission exists
     */
    public function hasPermission(string $permission): bool
    {
        $sessionData = $this->getSuperAdminSessionData();

        if (!$sessionData || !$this->isSessionValid($sessionData)) {
            return false;
        }

        $permissions = $sessionData['permissions'] ?? [];

        return in_array($permission, $permissions, true);
    }

    /**
     * Get all current permissions
     *
     * @return array List of permissions
     */
    public function getPermissions(): array
    {
        $sessionData = $this->getSuperAdminSessionData();

        if (!$sessionData || !$this->isSessionValid($sessionData)) {
            return [];
        }

        return $sessionData['permissions'] ?? [];
    }

    /**
     * Extend session TTL
     *
     * @param int $additionalSeconds Additional seconds to add
     * @return bool Success status
     */
    public function extendSession(int $additionalSeconds): bool
    {
        try {
            $sessionData = $this->getSuperAdminSessionData();

            if (!$sessionData) {
                return false;
            }

            $currentExpiry = Carbon::parse($sessionData['expires_at']);
            $newExpiry = $currentExpiry->addSeconds($additionalSeconds);

            $sessionData['expires_at'] = $newExpiry->toISOString();
            $sessionData['ttl'] = ($sessionData['ttl'] ?? 0) + $additionalSeconds;

            Session::put(self::SESSION_PREFIX . self::SUPER_ADMIN_SESSION_KEY, $sessionData);

            Log::info('Session TTL extended', [
                'session_id' => session()->getId(),
                'additional_seconds' => $additionalSeconds,
                'new_expiry' => $newExpiry->toISOString(),
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to extend session TTL', [
                'error' => $e->getMessage(),
                'session_id' => session()->getId(),
            ]);

            return false;
        }
    }

    /**
     * Get Super Admin session data
     *
     * @return array|null Session data or null if not found
     */
    private function getSuperAdminSessionData(): ?array
    {
        // Try session first
        $sessionData = Session::get(self::SESSION_PREFIX . self::SUPER_ADMIN_SESSION_KEY);

        // Fallback to cache if session data is not available
        if (!$sessionData) {
            $sessionData = Cache::get(self::CACHE_PREFIX . self::SUPER_ADMIN_SESSION_KEY . '_' . session()->getId());
        }

        return $sessionData;
    }

    /**
     * Check if session data is valid (not expired)
     *
     * @param array $sessionData Session data to validate
     * @return bool True if valid
     */
    private function isSessionValid(array $sessionData): bool
    {
        if (!isset($sessionData['expires_at'])) {
            return false;
        }

        try {
            $expiresAt = Carbon::parse($sessionData['expires_at']);
            return Carbon::now()->isBefore($expiresAt);

        } catch (Exception $e) {
            Log::warning('Invalid session expiry date', [
                'expires_at' => $sessionData['expires_at'] ?? 'null',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if tenant context is valid (not expired)
     *
     * @param array $tenantContext Tenant context data to validate
     * @return bool True if valid
     */
    private function isTenantContextValid(array $tenantContext): bool
    {
        if (!isset($tenantContext['expires_at'])) {
            return false;
        }

        try {
            $expiresAt = Carbon::parse($tenantContext['expires_at']);
            return Carbon::now()->isBefore($expiresAt);

        } catch (Exception $e) {
            Log::warning('Invalid tenant context expiry date', [
                'expires_at' => $tenantContext['expires_at'] ?? 'null',
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update last activity timestamp
     *
     * @return void
     */
    private function updateLastActivity(): void
    {
        try {
            $metadata = $this->getSessionMetadata();
            $metadata['last_activity'] = Carbon::now()->toISOString();
            $this->setSessionMetadata($metadata);

        } catch (Exception $e) {
            Log::debug('Failed to update last activity', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get session metadata
     *
     * @return array Session metadata
     */
    private function getSessionMetadata(): array
    {
        return Session::get(self::SESSION_PREFIX . self::SESSION_METADATA_KEY, []);
    }

    /**
     * Set session metadata
     *
     * @param array $metadata Metadata to store
     * @return void
     */
    private function setSessionMetadata(array $metadata): void
    {
        Session::put(self::SESSION_PREFIX . self::SESSION_METADATA_KEY, $metadata);
    }
}