<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // The app runs behind the hosting platform's HTTPS proxy in production.
        $middleware->trustProxies(at: '*');

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');

        $middleware->alias(['admin' => \App\Http\Middleware\EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
