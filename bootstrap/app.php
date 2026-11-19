<?php

use App\Http\Middleware\ApiGate;
use App\Http\Middleware\ApiIdempotency;
use App\Http\Middleware\EnsureUserIsActive;
use App\Services\Api\ApiExceptions;
use Illuminate\Http\Request;
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
    ->withMiddleware(function (Middleware $middleware) {
        // Register the 'active' middleware alias
        $middleware->alias([
            'active'          => EnsureUserIsActive::class,
            'api.gate'        => ApiGate::class,
            'api.idempotency' => ApiIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Everything under /api/ answers in the API's JSON envelope, errors included
        $exceptions->shouldRenderJsonWhen(fn(Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson());
        $exceptions->render(fn(Throwable $e, Request $request) => $request->is('api/*') ? ApiExceptions::render($e) : null);
    })->create();
