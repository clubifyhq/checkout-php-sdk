<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'super_admin' => [
            'driver' => 'session',
            'provider' => 'super_admins',
        ],

        'super_admin_api' => [
            'driver' => 'token',
            'provider' => 'super_admins',
            'hash' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        'super_admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\SuperAdmin::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'super_admins' => [
            'provider' => 'super_admins',
            'table' => 'super_admin_password_resets',
            'expire' => 15, // Shorter expiry for security
            'throttle' => 300, // 5 minutes throttle
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the amount of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Super Admin Security Configuration
    |--------------------------------------------------------------------------
    |
    | Enhanced security settings specifically for super admin authentication
    | and session management. These settings provide additional protection
    | for elevated privilege accounts.
    |
    */

    'super_admin_security' => [
        // Session Configuration
        'session_lifetime' => env('SUPER_ADMIN_SESSION_LIFETIME', 60), // minutes
        'session_inactivity_timeout' => env('SUPER_ADMIN_INACTIVITY_TIMEOUT', 30), // minutes
        'require_session_fingerprinting' => env('SUPER_ADMIN_REQUIRE_FINGERPRINTING', true),
        'max_concurrent_sessions' => env('SUPER_ADMIN_MAX_CONCURRENT_SESSIONS', 2),

        // Authentication Configuration
        'require_2fa' => env('SUPER_ADMIN_REQUIRE_2FA', true),
        'password_min_length' => env('SUPER_ADMIN_PASSWORD_MIN_LENGTH', 12),
        'password_require_special_chars' => env('SUPER_ADMIN_PASSWORD_REQUIRE_SPECIAL', true),
        'password_expiry_days' => env('SUPER_ADMIN_PASSWORD_EXPIRY_DAYS', 90),

        // Rate Limiting
        'login_rate_limit' => env('SUPER_ADMIN_LOGIN_RATE_LIMIT', 5), // attempts per hour
        'api_rate_limit' => env('SUPER_ADMIN_API_RATE_LIMIT', 100), // requests per hour
        'failed_login_lockout_duration' => env('SUPER_ADMIN_LOCKOUT_DURATION', 60), // minutes

        // IP Security
        'ip_whitelist_enabled' => env('SUPER_ADMIN_IP_WHITELIST_ENABLED', false),
        'ip_whitelist' => env('SUPER_ADMIN_IP_WHITELIST', ''), // comma-separated IPs
        'block_suspicious_ips' => env('SUPER_ADMIN_BLOCK_SUSPICIOUS_IPS', true),
        'suspicious_ip_threshold' => env('SUPER_ADMIN_SUSPICIOUS_IP_THRESHOLD', 5),

        // Audit and Monitoring
        'enable_comprehensive_logging' => env('SUPER_ADMIN_COMPREHENSIVE_LOGGING', true),
        'log_all_requests' => env('SUPER_ADMIN_LOG_ALL_REQUESTS', true),
        'real_time_monitoring' => env('SUPER_ADMIN_REAL_TIME_MONITORING', true),
        'security_alert_threshold' => env('SUPER_ADMIN_SECURITY_ALERT_THRESHOLD', 3),

        // Data Protection
        'encrypt_session_data' => env('SUPER_ADMIN_ENCRYPT_SESSION_DATA', true),
        'secure_cookies_only' => env('SUPER_ADMIN_SECURE_COOKIES_ONLY', true),
        'csrf_protection_required' => env('SUPER_ADMIN_CSRF_PROTECTION', true),

        // Compliance
        'audit_retention_days' => env('SUPER_ADMIN_AUDIT_RETENTION_DAYS', 2555), // 7 years
        'compliance_mode' => env('SUPER_ADMIN_COMPLIANCE_MODE', 'strict'),
        'data_classification_required' => env('SUPER_ADMIN_DATA_CLASSIFICATION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Headers Configuration
    |--------------------------------------------------------------------------
    |
    | Security headers to be applied to super admin responses for enhanced
    | protection against common web vulnerabilities.
    |
    */

    'security_headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'",
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
    ],

];
