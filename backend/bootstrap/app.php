<?php

use App\Http\Middleware\ActiveAccount;
use App\Http\Middleware\PlatformAdmin;
use App\Http\Middleware\ResolveVendor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The OVH edge nginx talks to this app over HTTP and sets X-Forwarded-Proto.
        $middleware->trustProxies(at: '*');

        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveVendor::class);
        $middleware->alias([
            'vendor' => ResolveVendor::class,
            'active' => ActiveAccount::class,
            'platform' => PlatformAdmin::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
