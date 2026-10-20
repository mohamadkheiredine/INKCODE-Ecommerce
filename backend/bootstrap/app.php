<?php

use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\UseRequestedLocale;
use App\Http\Responses\ApiExceptionRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The SPA authenticates with Sanctum's session cookie + CSRF token.
        $middleware->statefulApi();

        // Global, so even "route not found" errors come back in the SPA's language.
        $middleware->append(UseRequestedLocale::class);

        $middleware->alias(['idempotent' => EnsureIdempotency::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(new ApiExceptionRenderer);
    })->create();
