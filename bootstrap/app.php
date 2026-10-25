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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
        ]);

        // Payment gateways post back to these from their own servers.
        $middleware->validateCsrfTokens(except: [
            'payment/*/callback',
            'payment/*/webhook',
            't', // visitor beacon (navigator.sendBeacon cannot send the CSRF header)
        ]);

        // Set by the storefront script and read by the server to link shop actions to a visit.
        $middleware->encryptCookies(except: ['kn_sid', 'kn_vid']);

        $middleware->redirectGuestsTo(fn () => route('admin.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
