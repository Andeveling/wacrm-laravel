<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\AuthenticateMcp;
use App\Http\Middleware\EnsureCurrentAccount;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireApiKeyScope;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function (): void {
            // Public webhook ingress for Meta WhatsApp (#64). Mounted
            // via `then` so the file does NOT inherit the `web`
            // middleware group: no session, no CSRF, no cookies.
            require __DIR__.'/../routes/webhooks.php';
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'auth.mcp' => AuthenticateMcp::class,
            'ensure.current-account' => EnsureCurrentAccount::class,
            'scope' => RequireApiKeyScope::class,
        ]);

        // ThrottleRequests is in Laravel's default middlewarePriority list;
        // AuthenticateApiKey isn't, so the framework was free to run the
        // throttle before it despite the route's declared order — the
        // rate limiter then never saw the resolved api_key and fell back to
        // IP, letting one key's throttle bleed into another's. Force the
        // order explicitly.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: AuthenticateApiKey::class,
        );

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureCurrentAccount::class,
        );

        $middleware->prependToPriorityList(
            before: EnsureCurrentAccount::class,
            prepend: HandleInertiaRequests::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'rate_limited',
                    'message' => 'Too many requests. Try again later.',
                ],
            ], 429)->withHeaders($e->getHeaders());
        });
    })->create();
