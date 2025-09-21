<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\SuperAdminMiddleware;
use App\Services\ContextManager;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Super Admin Service Provider
 *
 * Registers super admin services, middleware, and configurations.
 *
 * @package App\Providers
 */
class SuperAdminServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register ContextManager as singleton
        $this->app->singleton(ContextManager::class, function ($app) {
            return new ContextManager();
        });

        // Bind interface if needed
        $this->app->bind('context.manager', ContextManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register middleware
        $this->registerMiddleware();

        // Register routes if needed
        $this->registerRoutes();

        // Register view composers if needed
        $this->registerViewComposers();
    }

    /**
     * Register middleware aliases
     */
    private function registerMiddleware(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];

        $router->aliasMiddleware('auth.super_admin', SuperAdminMiddleware::class);
    }

    /**
     * Register super admin routes
     */
    private function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // You can define routes here or in a separate routes file
        // Example route group for super admin endpoints
        /*
        Route::group([
            'prefix' => 'api/super-admin',
            'middleware' => ['api', 'auth.super_admin'],
            'namespace' => 'App\Http\Controllers',
        ], function () {
            Route::post('login', 'SuperAdminController@login')->withoutMiddleware('auth.super_admin');
            Route::post('logout', 'SuperAdminController@logout');
            Route::get('context', 'SuperAdminController@getContext');
            Route::get('tenants', 'SuperAdminController@getTenants');
            Route::post('tenants', 'SuperAdminController@createTenant');
            Route::get('tenants/{tenantId}', 'SuperAdminController@getTenantInfo');
            Route::put('tenants/{tenantId}', 'SuperAdminController@updateTenant');
            Route::post('switch-tenant', 'SuperAdminController@switchTenant');
            Route::post('clear-tenant-context', 'SuperAdminController@clearTenantContext');
            Route::get('dashboard-stats', 'SuperAdminController@getDashboardStats');
        });
        */
    }

    /**
     * Register view composers for super admin views
     */
    private function registerViewComposers(): void
    {
        // Register view composers if you have super admin views
        // View::composer('super-admin.*', function ($view) {
        //     $contextManager = app(ContextManager::class);
        //     $view->with('superAdminContext', $contextManager->getCurrentContext());
        // });
    }
}