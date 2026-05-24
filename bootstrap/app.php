<?php

declare(strict_types=1);

use App\Exceptions\WebhookException;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireWorkspace;
use App\Http\Middleware\SetCurrentWorkspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('reviews:retry')->hourly();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'current_workspace' => SetCurrentWorkspace::class,
            'require_workspace' => RequireWorkspace::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->group('auth', [
            'current_workspace',
            'require_workspace',
        ]);
        // $middleware->preventRequestForgery(['/webhooks/github']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'trace' => app()->isLocal() ? $e->getTraceAsString() : null,
                ], $e instanceof HttpException ? $e->getStatusCode() : 500);
            }

            return null;
        });
        $exceptions->render(fn (WebhookException $exception, Request $request) => response()->json(['message' => $exception->getMessage(), $exception->getCode() ?: 500]));
    })->create();
