<?php

/**
 * Super Admin Integration Validation Script
 *
 * This script validates that all Super Admin integration components
 * are properly configured and accessible.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Foundation\Application;

// Colors for console output
function colorOutput($text, $color = 'white') {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'white' => "\033[37m",
        'reset' => "\033[0m"
    ];

    return $colors[$color] . $text . $colors['reset'];
}

function checkPassed($message) {
    echo colorOutput("✓ " . $message, 'green') . "\n";
}

function checkFailed($message) {
    echo colorOutput("✗ " . $message, 'red') . "\n";
}

function checkWarning($message) {
    echo colorOutput("⚠ " . $message, 'yellow') . "\n";
}

function sectionHeader($title) {
    echo "\n" . colorOutput("=== " . $title . " ===", 'blue') . "\n";
}

echo colorOutput("Super Admin Integration Validation", 'blue') . "\n";
echo colorOutput("=====================================", 'blue') . "\n";

$errors = [];
$warnings = [];

// Check 1: Files exist
sectionHeader("File Structure Validation");

$requiredFiles = [
    'routes/super-admin.php' => 'Super Admin routes file',
    'config/super-admin.php' => 'Super Admin configuration file',
    'bootstrap/providers.php' => 'Service providers file',
    '.env.example' => 'Environment template file'
];

foreach ($requiredFiles as $file => $description) {
    if (file_exists(__DIR__ . '/' . $file)) {
        checkPassed("$description exists ($file)");
    } else {
        checkFailed("$description missing ($file)");
        $errors[] = "Missing file: $file";
    }
}

// Check 2: Service provider registration
sectionHeader("Service Provider Registration");

$providersFile = __DIR__ . '/bootstrap/providers.php';
if (file_exists($providersFile)) {
    $providersContent = file_get_contents($providersFile);
    if (strpos($providersContent, 'SuperAdminServiceProvider') !== false) {
        checkPassed("SuperAdminServiceProvider is registered");
    } else {
        checkFailed("SuperAdminServiceProvider not found in providers.php");
        $errors[] = "SuperAdminServiceProvider not registered";
    }
} else {
    checkFailed("providers.php file not found");
    $errors[] = "providers.php file missing";
}

// Check 3: Route inclusion
sectionHeader("Route Configuration");

$webRoutesFile = __DIR__ . '/routes/web.php';
if (file_exists($webRoutesFile)) {
    $webRoutesContent = file_get_contents($webRoutesFile);
    if (strpos($webRoutesContent, "require __DIR__.'/super-admin.php'") !== false) {
        checkPassed("Super Admin routes are included in web.php");
    } else {
        checkFailed("Super Admin routes not included in web.php");
        $errors[] = "Super Admin routes not included";
    }
} else {
    checkFailed("web.php routes file not found");
    $errors[] = "web.php file missing";
}

// Check 4: Environment variables in .env.example
sectionHeader("Environment Configuration");

$envExampleFile = __DIR__ . '/.env.example';
if (file_exists($envExampleFile)) {
    $envContent = file_get_contents($envExampleFile);

    $requiredEnvVars = [
        'SUPER_ADMIN_ENABLED',
        'SUPER_ADMIN_JWT_SECRET',
        'SUPER_ADMIN_DEFAULT_TENANT',
        'SUPER_ADMIN_API_PREFIX'
    ];

    foreach ($requiredEnvVars as $envVar) {
        if (strpos($envContent, $envVar) !== false) {
            checkPassed("Environment variable $envVar is documented");
        } else {
            checkWarning("Environment variable $envVar not found in .env.example");
            $warnings[] = "Missing environment variable: $envVar";
        }
    }
} else {
    checkFailed(".env.example file not found");
    $errors[] = ".env.example file missing";
}

// Check 5: Middleware registration in bootstrap/app.php
sectionHeader("Middleware Configuration");

$appBootstrapFile = __DIR__ . '/bootstrap/app.php';
if (file_exists($appBootstrapFile)) {
    $appContent = file_get_contents($appBootstrapFile);

    $requiredMiddleware = [
        'auth.super_admin',
        'security.headers',
        'ip.whitelist'
    ];

    foreach ($requiredMiddleware as $middleware) {
        if (strpos($appContent, $middleware) !== false) {
            checkPassed("Middleware alias '$middleware' is registered");
        } else {
            checkWarning("Middleware alias '$middleware' not found");
            $warnings[] = "Missing middleware alias: $middleware";
        }
    }

    // Check CSRF exclusions
    if (strpos($appContent, 'api/super-admin/*') !== false) {
        checkPassed("Super Admin routes excluded from CSRF protection");
    } else {
        checkWarning("Super Admin routes may not be excluded from CSRF protection");
        $warnings[] = "CSRF exclusion for super admin routes may be missing";
    }
} else {
    checkFailed("bootstrap/app.php file not found");
    $errors[] = "bootstrap/app.php file missing";
}

// Check 6: Configuration file structure
sectionHeader("Configuration Structure");

$configFile = __DIR__ . '/config/super-admin.php';
if (file_exists($configFile)) {
    try {
        $config = include $configFile;

        $requiredConfigSections = [
            'enabled',
            'jwt',
            'api',
            'security',
            'permissions',
            'multi_tenant'
        ];

        foreach ($requiredConfigSections as $section) {
            if (isset($config[$section])) {
                checkPassed("Configuration section '$section' exists");
            } else {
                checkFailed("Configuration section '$section' missing");
                $errors[] = "Missing configuration section: $section";
            }
        }

        // Check specific critical configurations
        if (isset($config['permissions']) && is_array($config['permissions']) && count($config['permissions']) > 0) {
            checkPassed("Permissions are defined (" . count($config['permissions']) . " permissions)");
        } else {
            checkWarning("No permissions defined or permissions section is empty");
            $warnings[] = "Permissions configuration may be incomplete";
        }

    } catch (Exception $e) {
        checkFailed("Error loading configuration file: " . $e->getMessage());
        $errors[] = "Configuration file error: " . $e->getMessage();
    }
} else {
    checkFailed("super-admin.php configuration file not found");
    $errors[] = "Configuration file missing";
}

// Check 7: Route file structure
sectionHeader("Route Structure");

$superAdminRoutesFile = __DIR__ . '/routes/super-admin.php';
if (file_exists($superAdminRoutesFile)) {
    $routesContent = file_get_contents($superAdminRoutesFile);

    $expectedRouteGroups = [
        'api/super-admin',
        'super-admin/test',
        'middleware.*auth.super_admin'
    ];

    foreach ($expectedRouteGroups as $routePattern) {
        if (preg_match('/' . str_replace('/', '\\/', $routePattern) . '/', $routesContent)) {
            checkPassed("Route pattern '$routePattern' found");
        } else {
            checkWarning("Route pattern '$routePattern' not found");
            $warnings[] = "Route pattern may be missing: $routePattern";
        }
    }

    // Check for testing routes
    if (strpos($routesContent, 'super-admin.test') !== false) {
        checkPassed("Testing routes are defined");
    } else {
        checkWarning("Testing routes not found");
        $warnings[] = "Testing routes may not be configured";
    }

} else {
    checkFailed("super-admin.php routes file not found");
    $errors[] = "Routes file missing";
}

// Summary
sectionHeader("Validation Summary");

if (empty($errors)) {
    checkPassed("All critical validations passed!");
} else {
    checkFailed("Found " . count($errors) . " error(s):");
    foreach ($errors as $error) {
        echo "  - " . colorOutput($error, 'red') . "\n";
    }
}

if (!empty($warnings)) {
    checkWarning("Found " . count($warnings) . " warning(s):");
    foreach ($warnings as $warning) {
        echo "  - " . colorOutput($warning, 'yellow') . "\n";
    }
}

echo "\n" . colorOutput("Validation Complete", 'blue') . "\n";

if (empty($errors)) {
    echo colorOutput("✓ Integration appears to be properly configured!", 'green') . "\n";
    echo colorOutput("Next steps:", 'blue') . "\n";
    echo "  1. Copy environment variables from .env.example to .env\n";
    echo "  2. Generate a strong JWT secret\n";
    echo "  3. Create the SuperAdminController\n";
    echo "  4. Test the endpoints\n";
} else {
    echo colorOutput("✗ Please fix the errors above before proceeding.", 'red') . "\n";
    exit(1);
}

exit(0);