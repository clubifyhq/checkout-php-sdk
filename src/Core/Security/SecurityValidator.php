<?php

declare(strict_types=1);

namespace Clubify\Checkout\Core\Security;

use Clubify\Checkout\Exceptions\ValidationException;
use Clubify\Checkout\Exceptions\SecurityException;

/**
 * Security Validator for Clubify SDK
 *
 * Provides comprehensive security validation functions including:
 * - Input sanitization and validation
 * - XSS protection
 * - SQL injection prevention
 * - CSRF token management
 * - Rate limiting helpers
 * - Data encryption/decryption
 */
class SecurityValidator
{
    private const MAX_STRING_LENGTH = 10000;
    private const MAX_ARRAY_DEPTH = 10;
    private const ALLOWED_HTML_TAGS = '<p><br><strong><em><ul><ol><li><a><span>';

    /**
     * Sanitize input to prevent XSS attacks
     */
    public static function sanitizeInput(mixed $input): mixed
    {
        if (is_string($input)) {
            return self::sanitizeString($input);
        }

        if (is_array($input)) {
            return self::sanitizeArray($input);
        }

        if (is_int($input) || is_float($input)) {
            return $input;
        }

        if (is_bool($input)) {
            return $input;
        }

        if (is_null($input)) {
            return null;
        }

        // For other types, convert to string and sanitize
        return self::sanitizeString((string) $input);
    }

    /**
     * Sanitize string input
     */
    private static function sanitizeString(string $input): string
    {
        // Check length limit
        if (strlen($input) > self::MAX_STRING_LENGTH) {
            throw new ValidationException('Input string exceeds maximum allowed length');
        }

        // Remove null bytes
        $input = str_replace("\0", '', $input);

        // Trim whitespace
        $input = trim($input);

        // Convert special characters to HTML entities
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $input;
    }

    /**
     * Sanitize array input recursively
     */
    private static function sanitizeArray(array $input, int $depth = 0): array
    {
        if ($depth > self::MAX_ARRAY_DEPTH) {
            throw new ValidationException('Array nesting depth exceeds maximum allowed');
        }

        $sanitized = [];

        foreach ($input as $key => $value) {
            // Sanitize the key
            $sanitizedKey = self::sanitizeString((string) $key);

            // Sanitize the value
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::sanitizeArray($value, $depth + 1);
            } else {
                $sanitized[$sanitizedKey] = self::sanitizeInput($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize HTML content allowing only safe tags
     */
    public static function sanitizeHtml(string $html): string
    {
        // Remove potentially dangerous tags and attributes
        $html = strip_tags($html, self::ALLOWED_HTML_TAGS);

        // Remove dangerous attributes
        $html = preg_replace('/(<[^>]+)\s(on\w+)="[^"]*"/i', '$1', $html);
        $html = preg_replace('/(<[^>]+)\s(javascript:)/i', '$1', $html);
        $html = preg_replace('/(<[^>]+)\s(data:)/i', '$1', $html);

        return $html;
    }

    /**
     * Validate and sanitize SQL parameters
     */
    public static function sanitizeSqlParameter(mixed $parameter): mixed
    {
        if (is_string($parameter)) {
            // Remove null bytes and control characters
            $parameter = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $parameter);

            // Escape SQL special characters (though parameterized queries should be used)
            return addslashes($parameter);
        }

        if (is_numeric($parameter)) {
            return $parameter;
        }

        if (is_bool($parameter)) {
            return $parameter;
        }

        if (is_null($parameter)) {
            return null;
        }

        throw new ValidationException('Invalid SQL parameter type');
    }

    /**
     * Validate email address
     */
    public static function validateEmail(string $email): bool
    {
        $email = self::sanitizeString($email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     */
    public static function validateUrl(string $url): bool
    {
        $url = self::sanitizeString($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Validate UUID
     */
    public static function validateUuid(string $uuid): bool
    {
        $uuid = self::sanitizeString($uuid);
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Validate API key format
     */
    public static function validateApiKey(string $apiKey): bool
    {
        $apiKey = self::sanitizeString($apiKey);
        // Clubify API keys format: clb_test_* or clb_live_*
        return preg_match('/^clb_(test|live)_[a-f0-9]{32}$/', $apiKey) === 1;
    }

    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate CSRF token
     */
    public static function validateCsrfToken(string $token, string $sessionToken): bool
    {
        return hash_equals($sessionToken, $token);
    }

    /**
     * Encrypt sensitive data
     */
    public static function encryptData(string $data, string $key): string
    {
        $cipher = 'AES-256-GCM';
        $iv = random_bytes(16);
        $tag = '';

        $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public static function decryptData(string $encryptedData, string $key): string
    {
        $cipher = 'AES-256-GCM';
        $data = base64_decode($encryptedData);

        if ($data === false || strlen($data) < 32) {
            throw new \RuntimeException('Invalid encrypted data');
        }

        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);

        $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            throw new \RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    /**
     * Generate secure hash
     */
    public static function generateSecureHash(string $data, string $salt = ''): string
    {
        return hash('sha256', $data . $salt);
    }

    /**
     * Validate password strength
     */
    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Rate limiting helper
     */
    public static function checkRateLimit(string $identifier, int $maxAttempts, int $timeWindow): bool
    {
        $key = 'rate_limit:' . hash('sha256', $identifier);

        // In a real implementation, this would use Redis or another cache
        // For now, we'll use a simple session-based approach
        if (!isset($_SESSION)) {
            session_start();
        }

        $now = time();
        $attempts = $_SESSION[$key] ?? [];

        // Remove old attempts outside the time window
        $attempts = array_filter($attempts, fn($time) => ($now - $time) < $timeWindow);

        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Add current attempt
        $attempts[] = $now;
        $_SESSION[$key] = $attempts;

        return true;
    }

    /**
     * Sanitize file upload
     */
    public static function validateFileUpload(array $file): array
    {
        $errors = [];

        // Check if file was uploaded successfully
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed';
            return ['valid' => false, 'errors' => $errors];
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'File size exceeds maximum allowed (10MB)';
        }

        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'text/plain'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = 'File type not allowed';
        }

        // Sanitize filename
        $filename = self::sanitizeFilename($file['name']);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'sanitized_filename' => $filename,
            'mime_type' => $mimeType
        ];
    }

    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path information
        $filename = basename($filename);

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Limit length
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr($filename, 0, 250 - strlen($extension));
            $filename = $name . '.' . $extension;
        }

        return $filename;
    }

    /**
     * Validate JSON structure
     */
    public static function validateJson(string $json, array $requiredFields = []): array
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'error' => 'Invalid JSON format: ' . json_last_error_msg()
            ];
        }

        $errors = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Required field '{$field}' is missing";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $data
        ];
    }

    /**
     * Secure random string generation
     */
    public static function generateSecureRandomString(int $length = 32): string
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Validate and sanitize phone number
     */
    public static function sanitizePhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Validate basic phone number format
        if (!preg_match('/^\+?[1-9]\d{1,14}$/', $phone)) {
            throw new ValidationException('Invalid phone number format');
        }

        return $phone;
    }
}