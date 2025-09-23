<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Security;

use Clubify\Checkout\Exceptions\SecurityException;

/**
 * CSRF Protection for Clubify SDK
 *
 * Provides Cross-Site Request Forgery protection through:
 * - Token generation and validation
 * - Session-based token storage
 * - Double submit cookie pattern
 * - SameSite cookie attributes
 */
class CsrfProtection
{
    private const TOKEN_LENGTH = 32;
    private const SESSION_KEY = '_csrf_tokens';
    private const COOKIE_NAME = '_csrf_token';
    private const MAX_TOKENS_PER_SESSION = 10;

    /**
     * Generate a new CSRF token
     */
    public static function generateToken(): string
    {
        $token = SecurityValidator::generateSecureRandomString(self::TOKEN_LENGTH);
        self::storeTokenInSession($token);
        return $token;
    }

    /**
     * Validate CSRF token from request
     */
    public static function validateToken(string $token, bool $removeAfterValidation = true): bool
    {
        if (empty($token)) {
            return false;
        }

        // Get stored tokens from session
        $storedTokens = self::getStoredTokens();

        $isValid = false;
        foreach ($storedTokens as $index => $storedToken) {
            if (hash_equals($storedToken, $token)) {
                $isValid = true;

                if ($removeAfterValidation) {
                    self::removeTokenFromSession($index);
                }
                break;
            }
        }

        return $isValid;
    }

    /**
     * Get CSRF token from various sources (header, POST, GET)
     */
    public static function getTokenFromRequest(): ?string
    {
        // Check X-CSRF-Token header
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

        if (!$token) {
            // Check POST data
            $token = $_POST['_csrf_token'] ?? null;
        }

        if (!$token) {
            // Check GET parameter (less secure, should be avoided)
            $token = $_GET['_csrf_token'] ?? null;
        }

        return $token;
    }

    /**
     * Validate request has valid CSRF token
     */
    public static function validateRequest(): bool
    {
        $token = self::getTokenFromRequest();

        if (!$token) {
            return false;
        }

        return self::validateToken($token);
    }

    /**
     * Require valid CSRF token or throw exception
     */
    public static function requireValidToken(): void
    {
        if (!self::validateRequest()) {
            throw new SecurityException('Invalid or missing CSRF token');
        }
    }

    /**
     * Set CSRF token in cookie for double submit pattern
     */
    public static function setCsrfCookie(string $token): void
    {
        $options = [
            'expires' => time() + 3600, // 1 hour
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => false, // JS needs to read this for AJAX
            'samesite' => 'Strict'
        ];

        setcookie(self::COOKIE_NAME, $token, $options);
    }

    /**
     * Validate double submit cookie pattern
     */
    public static function validateDoubleSubmit(): bool
    {
        $cookieToken = $_COOKIE[self::COOKIE_NAME] ?? null;
        $requestToken = self::getTokenFromRequest();

        if (!$cookieToken || !$requestToken) {
            return false;
        }

        return hash_equals($cookieToken, $requestToken);
    }

    /**
     * Generate token and set cookie in one call
     */
    public static function initializeProtection(): string
    {
        $token = self::generateToken();
        self::setCsrfCookie($token);
        return $token;
    }

    /**
     * Get HTML input field for forms
     */
    public static function getHtmlTokenField(): string
    {
        $token = self::generateToken();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Get token for JavaScript AJAX requests
     */
    public static function getTokenForAjax(): array
    {
        $token = self::generateToken();
        self::setCsrfCookie($token);

        return [
            'token' => $token,
            'header_name' => 'X-CSRF-Token',
            'cookie_name' => self::COOKIE_NAME
        ];
    }

    /**
     * Clear all CSRF tokens from session
     */
    public static function clearTokens(): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);

        // Clear cookie
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', time() - 3600, '/');
        }
    }

    /**
     * Store token in session
     */
    private static function storeTokenInSession(string $token): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }

        // Add new token
        $_SESSION[self::SESSION_KEY][] = $token;

        // Limit number of tokens to prevent session bloat
        if (count($_SESSION[self::SESSION_KEY]) > self::MAX_TOKENS_PER_SESSION) {
            array_shift($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * Get stored tokens from session
     */
    private static function getStoredTokens(): array
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        return $_SESSION[self::SESSION_KEY] ?? [];
    }

    /**
     * Remove token from session by index
     */
    private static function removeTokenFromSession(int $index): void
    {
        if (!isset($_SESSION)) {
            session_start();
        }

        if (isset($_SESSION[self::SESSION_KEY][$index])) {
            unset($_SESSION[self::SESSION_KEY][$index]);
            $_SESSION[self::SESSION_KEY] = array_values($_SESSION[self::SESSION_KEY]);
        }
    }

    /**
     * Check if current request method requires CSRF protection
     */
    public static function requiresProtection(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $safeMethods = ['GET', 'HEAD', 'OPTIONS', 'TRACE'];

        return !in_array(strtoupper($method), $safeMethods);
    }

    /**
     * Middleware-style CSRF protection
     */
    public static function protect(): void
    {
        if (self::requiresProtection()) {
            self::requireValidToken();
        }
    }

    /**
     * Generate meta tag for HTML head
     */
    public static function getMetaTag(): string
    {
        $token = self::generateToken();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Refresh token (for long-running sessions)
     */
    public static function refreshToken(): string
    {
        return self::generateToken();
    }

    /**
     * Check token age and refresh if needed
     */
    public static function checkAndRefreshToken(string $token, int $maxAge = 3600): string
    {
        // In a more sophisticated implementation, you'd store creation time
        // For now, just generate a new token if validation fails
        if (!self::validateToken($token, false)) {
            return self::generateToken();
        }

        return $token;
    }
}