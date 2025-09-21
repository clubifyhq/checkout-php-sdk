<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'clubify/test-module/*',
            'clubify/test-all-methods',
            'api/super-admin/*',
        ]);

        // Register Super Admin middleware
        $middleware->alias([
            'auth.super_admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'security.headers' => \App\Http\Middleware\SecurityHeadersMiddleware::class,
            'ip.whitelist' => \App\Http\Middleware\IpWhitelistMiddleware::class,
            'input.sanitization' => \App\Http\Middleware\InputSanitizationMiddleware::class,
            'api.csrf' => \App\Http\Middleware\ApiCsrfMiddleware::class,
        ]);

        // Apply security middleware globally to API routes
        $middleware->group('api', [
            'security.headers',
            'input.sanitization',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
