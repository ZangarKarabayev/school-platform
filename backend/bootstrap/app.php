<?php

use App\Http\Middleware\EnsureBasicAuth;
use App\Http\Middleware\EnsureKitchenAccess;
use App\Http\Middleware\EnsureKitchenAuthenticated;
use App\Http\Middleware\EnsureSchoolBoundForSchoolRoles;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(HandleCors::class);

        $middleware->appendToGroup('web', [
            SetLocale::class,
        ]);

        $middleware->alias([
            'basic.auth' => EnsureBasicAuth::class,
            'kitchen.auth' => EnsureKitchenAuthenticated::class,
            'kitchen.role' => EnsureKitchenAccess::class,
            'school.bound' => EnsureSchoolBoundForSchoolRoles::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
