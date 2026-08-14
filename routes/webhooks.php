<?php

declare(strict_types=1);

use App\Domain\Meta\Actions\ReceiveMetaWebhook;
use App\Domain\Meta\Actions\VerifyMetaWebhook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * Public webhook ingress for the Meta WhatsApp Business Platform
 * (#64). This file is mounted by `bootstrap/app.php` via the routing
 * `then:` callback so it does NOT inherit the `web` middleware group —
 * no session, no CSRF, no cookie encryption. Authentication is provided by
 * Meta's HMAC signature; the body limit and infrastructure controls are the
 * abuse boundary rather than a per-IP limit that could reject shared Meta
 * delivery infrastructure.
 */

RateLimiter::for('meta-webhook', function (Request $request): Limit {
    // Emergency backstop only. HMAC and the body limit remain the real
    // admission controls; the ceiling is intentionally high for Meta's
    // shared delivery infrastructure.
    return Limit::perMinute(10_000)->by('meta-webhook:'.($request->ip() ?? 'unknown'));
});

Route::get('api/whatsapp/webhook', VerifyMetaWebhook::class)
    ->middleware('throttle:meta-webhook')
    ->name('meta.webhook.verify');

Route::post('api/whatsapp/webhook', ReceiveMetaWebhook::class)
    ->middleware('throttle:meta-webhook')
    ->name('meta.webhook.receive');
