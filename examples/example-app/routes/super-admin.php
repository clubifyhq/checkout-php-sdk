<?php

use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| Here are the routes for Super Admin functionality. These routes are
| protected by the super admin middleware and provide multi-tenant
| management capabilities.
|
*/

// Web Authentication Routes (public access)
Route::group([
    'prefix' => 'super-admin',
], function () {
    Route::get('login', [SuperAdminAuthController::class, 'showLoginForm'])->name('super-admin.login');
    Route::post('login', [SuperAdminAuthController::class, 'login']);
    Route::post('logout', [SuperAdminAuthController::class, 'logout'])->name('super-admin.logout');

    // Protected web routes
    Route::middleware(['web'])->group(function () {
        Route::get('/', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');
        Route::get('dashboard', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');
        Route::get('tenants', [SuperAdminController::class, 'tenants'])->name('super-admin.tenants');
        Route::get('create-organization', [SuperAdminController::class, 'createOrganization'])->name('super-admin.create-organization');
    });
});

// API Routes

Route::group([
    'prefix' => 'api/super-admin',
    'middleware' => ['api'],
], function () {
    // Authentication routes (no middleware required)
    Route::post('login', [SuperAdminAuthController::class, 'apiLogin']);

    // Protected routes (require super admin authentication)
    Route::group(['middleware' => 'auth.super_admin'], function () {
        // Session management
        Route::post('logout', [SuperAdminAuthController::class, 'apiLogout']);
        Route::get('me', [SuperAdminAuthController::class, 'me']);
        Route::get('context', [SuperAdminController::class, 'getContext']);

        // Tenant management
        Route::get('tenants', [SuperAdminController::class, 'getTenants']);
        Route::post('tenants', [SuperAdminController::class, 'createTenant']);
        Route::get('tenants/{tenantId}', [SuperAdminController::class, 'getTenantInfo']);
        Route::put('tenants/{tenantId}', [SuperAdminController::class, 'updateTenant']);

        // Tenant context switching
        Route::post('switch-tenant', [SuperAdminController::class, 'switchTenant']);
        Route::post('clear-tenant-context', [SuperAdminController::class, 'clearTenantContext']);

        // Dashboard and monitoring
        Route::get('dashboard-stats', [SuperAdminController::class, 'getDashboardStats']);
    });
});

/*
|--------------------------------------------------------------------------
| Permission-specific routes
|--------------------------------------------------------------------------
|
| These routes require specific permissions in addition to super admin access
|
*/

Route::group([
    'prefix' => 'api/super-admin',
    'middleware' => ['api', 'auth.super_admin'],
], function () {
    // Tenant creation (requires tenant.create permission)
    Route::post('tenants/create', [SuperAdminController::class, 'createTenant'])
         ->middleware('auth.super_admin:tenant.create');

    // Tenant deletion (requires tenant.delete permission)
    Route::delete('tenants/{tenantId}', [SuperAdminController::class, 'deleteTenant'])
         ->middleware('auth.super_admin:tenant.delete');

    // System monitoring (requires system.monitor permission)
    Route::get('system/health', [SuperAdminController::class, 'getSystemHealth'])
         ->middleware('auth.super_admin:system.monitor');

    // Configuration management (requires configuration.manage permission)
    Route::put('system/configuration', [SuperAdminController::class, 'updateSystemConfiguration'])
         ->middleware('auth.super_admin:configuration.manage');
});

/*
|--------------------------------------------------------------------------
| Web Routes for Super Admin UI (if you have a web interface)
|--------------------------------------------------------------------------
|
| These would be for a web-based super admin interface
|
*/

Route::group([
    'prefix' => 'super-admin',
    'middleware' => ['web', 'auth.super_admin'],
], function () {
    // Dashboard
    Route::get('/', function () {
        return view('super-admin.dashboard');
    })->name('super-admin.dashboard');

    // Tenant management
    Route::get('/tenants', function () {
        return view('super-admin.tenants.index');
    })->name('super-admin.tenants.index');

    Route::get('/tenants/{tenantId}', function ($tenantId) {
        return view('super-admin.tenants.show', compact('tenantId'));
    })->name('super-admin.tenants.show');

    // System monitoring
    Route::get('/monitoring', function () {
        return view('super-admin.monitoring');
    })->name('super-admin.monitoring');
});

/*
|--------------------------------------------------------------------------
| Testing and Validation Routes
|--------------------------------------------------------------------------
|
| These routes are for testing and validating the super admin integration.
| They should be disabled in production environments.
|
*/

if (config('super-admin.testing.enabled', false)) {
    Route::group([
        'prefix' => 'api/super-admin/test',
        'middleware' => ['api'],
    ], function () {
        // Public test endpoints (no authentication required)
        Route::get('/', function () {
            return response()->json([
                'status' => 'success',
                'message' => 'Super Admin API is accessible',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'config' => [
                    'enabled' => config('super-admin.enabled'),
                    'api_prefix' => config('super-admin.api.prefix'),
                    'jwt_configured' => !empty(config('super-admin.jwt.secret')),
                    'cache_ttl' => config('super-admin.cache.ttl'),
                ]
            ]);
        })->name('super-admin.test.status');

        Route::get('config', function () {
            return response()->json([
                'status' => 'success',
                'config' => [
                    'enabled' => config('super-admin.enabled'),
                    'session' => config('super-admin.session'),
                    'api' => config('super-admin.api'),
                    'security' => [
                        'require_mfa' => config('super-admin.security.require_mfa'),
                        'max_login_attempts' => config('super-admin.security.max_login_attempts'),
                        'audit_log_enabled' => config('super-admin.security.audit_log.enabled'),
                    ],
                    'multi_tenant' => config('super-admin.multi_tenant'),
                ]
            ]);
        })->name('super-admin.test.config');

        Route::get('middleware', function () {
            $middlewareAliases = app('router')->getMiddleware();
            return response()->json([
                'status' => 'success',
                'middleware' => [
                    'aliases' => array_keys($middlewareAliases),
                    'super_admin_registered' => isset($middlewareAliases['auth.super_admin']),
                    'security_headers_registered' => isset($middlewareAliases['security.headers']),
                    'ip_whitelist_registered' => isset($middlewareAliases['ip.whitelist']),
                ]
            ]);
        })->name('super-admin.test.middleware');

        Route::get('permissions', function () {
            return response()->json([
                'status' => 'success',
                'permissions' => config('super-admin.permissions'),
                'default_user_permissions' => config('super-admin.default_user.permissions'),
            ]);
        })->name('super-admin.test.permissions');

        // Protected test endpoints (require super admin authentication)
        Route::group(['middleware' => 'auth.super_admin'], function () {
            Route::get('authenticated', function () {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Successfully authenticated as super admin',
                    'user' => auth()->user() ? [
                        'id' => auth()->user()->id,
                        'email' => auth()->user()->email,
                        'name' => auth()->user()->name,
                    ] : null,
                    'timestamp' => now()->toISOString(),
                ]);
            })->name('super-admin.test.authenticated');

            Route::get('tenant-context', function () {
                $tenantContext = session('super_admin_tenant_id');
                return response()->json([
                    'status' => 'success',
                    'tenant_context' => $tenantContext,
                    'default_tenant' => config('super-admin.default_tenant'),
                    'multi_tenant_enabled' => config('super-admin.multi_tenant.enabled'),
                ]);
            })->name('super-admin.test.tenant-context');
        });

        // Health check endpoint
        Route::get('health', function () {
            $checks = [
                'config_loaded' => config('super-admin.enabled') !== null,
                'jwt_secret_configured' => !empty(config('super-admin.jwt.secret')),
                'cache_store_accessible' => true,
                'database_connected' => true,
            ];

            try {
                cache()->put('super_admin_health_check', 'ok', 60);
                cache()->forget('super_admin_health_check');
            } catch (Exception $e) {
                $checks['cache_store_accessible'] = false;
            }

            try {
                \DB::connection()->getPdo();
            } catch (Exception $e) {
                $checks['database_connected'] = false;
            }

            $healthy = array_reduce($checks, function ($carry, $check) {
                return $carry && $check;
            }, true);

            return response()->json([
                'status' => $healthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => now()->toISOString(),
            ], $healthy ? 200 : 503);
        })->name('super-admin.test.health');

        // Integration test endpoint
        Route::post('integration', function () {
            $results = [];

            // Test 1: Configuration loading
            try {
                $config = config('super-admin');
                $results['config_loading'] = [
                    'status' => 'pass',
                    'message' => 'Configuration loaded successfully',
                    'enabled' => $config['enabled'] ?? false,
                ];
            } catch (Exception $e) {
                $results['config_loading'] = [
                    'status' => 'fail',
                    'message' => 'Failed to load configuration: ' . $e->getMessage(),
                ];
            }

            // Test 2: Middleware registration
            try {
                $middleware = app('router')->getMiddleware();
                $results['middleware_registration'] = [
                    'status' => isset($middleware['auth.super_admin']) ? 'pass' : 'fail',
                    'message' => isset($middleware['auth.super_admin'])
                        ? 'Super admin middleware registered successfully'
                        : 'Super admin middleware not registered',
                    'registered_middleware' => array_keys($middleware),
                ];
            } catch (Exception $e) {
                $results['middleware_registration'] = [
                    'status' => 'fail',
                    'message' => 'Failed to check middleware: ' . $e->getMessage(),
                ];
            }

            // Test 3: Route registration
            try {
                $routes = collect(app('router')->getRoutes())->map(function ($route) {
                    return $route->getName();
                })->filter(function ($name) {
                    return $name && str_starts_with($name, 'super-admin');
                })->values();

                $results['route_registration'] = [
                    'status' => $routes->count() > 0 ? 'pass' : 'fail',
                    'message' => $routes->count() > 0
                        ? 'Super admin routes registered successfully'
                        : 'No super admin routes found',
                    'routes_count' => $routes->count(),
                    'routes' => $routes->toArray(),
                ];
            } catch (Exception $e) {
                $results['route_registration'] = [
                    'status' => 'fail',
                    'message' => 'Failed to check routes: ' . $e->getMessage(),
                ];
            }

            // Test 4: Environment variables
            try {
                $envVars = [
                    'SUPER_ADMIN_ENABLED' => env('SUPER_ADMIN_ENABLED'),
                    'SUPER_ADMIN_JWT_SECRET' => !empty(env('SUPER_ADMIN_JWT_SECRET')),
                    'SUPER_ADMIN_DEFAULT_TENANT' => env('SUPER_ADMIN_DEFAULT_TENANT'),
                ];

                $results['environment_variables'] = [
                    'status' => 'pass',
                    'message' => 'Environment variables checked',
                    'variables' => $envVars,
                ];
            } catch (Exception $e) {
                $results['environment_variables'] = [
                    'status' => 'fail',
                    'message' => 'Failed to check environment variables: ' . $e->getMessage(),
                ];
            }

            $allPassed = collect($results)->every(function ($result) {
                return $result['status'] === 'pass';
            });

            return response()->json([
                'status' => $allPassed ? 'success' : 'partial_failure',
                'message' => $allPassed
                    ? 'All integration tests passed'
                    : 'Some integration tests failed',
                'results' => $results,
                'timestamp' => now()->toISOString(),
            ], $allPassed ? 200 : 422);
        })->name('super-admin.test.integration');
    });
}