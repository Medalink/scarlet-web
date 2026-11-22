<?php

use App\Http\Middleware\EdgeCacheable;
use App\Http\Middleware\PageStatementTimeout;
use App\Http\Middleware\RateLimitRequests;
use App\Http\Middleware\VerifyInstallSignature;
use App\Support\Exceptions\ApiException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'scarlet.signed' => VerifyInstallSignature::class,
            'scarlet.rate' => RateLimitRequests::class,
            'scarlet.timeout' => PageStatementTimeout::class,
            'scarlet.edge' => EdgeCacheable::class,
        ]);
        $middleware->prependToPriorityList(before: StartSession::class, prepend: EdgeCacheable::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(ApiException::class);
        // An unknown path under /api answers in the shape of every other refusal (docs/API.md, "Errors").
        $exceptions->render(fn (NotFoundHttpException $e, Request $request) => $request->is('api/*')
            ? ApiException::notFound('This address')->render()
            : null);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
