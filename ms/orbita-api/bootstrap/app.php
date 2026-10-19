<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up',
        then: function (): void {
            \Illuminate\Support\Facades\Route::get('/health', \App\Modules\Schema\HealthController::class);
            \Illuminate\Support\Facades\Route::get('/media/{hash}', [\App\Modules\Media\MediaController::class, 'show'])->middleware('throttle:240,1');
        })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['imageHash', '*.imageHash']);
        $middleware->statefulApi();
        $middleware->trustProxies(at: array_filter(explode(',', (string) env('TRUSTED_PROXIES', ''))));
        $middleware->redirectGuestsTo(fn () => '/auth/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('auth/*', 'api/*', 'graphql') || $request->expectsJson());
    })->create();
