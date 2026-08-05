<?php

declare(strict_types=1);

use App\Http\Controllers\Meta\MetaWebhookController;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/*
 * Public webhook ingress for the Meta WhatsApp Business Platform
 * (#64). This file is mounted by `bootstrap/app.php` via the routing
 * `then:` callback so it does NOT inherit the `web` middleware group —
 * no session, no CSRF, no cookie encryption. The route still applies a
 * per-IP throttle so a misconfigured client cannot flood the endpoint.
 */

RateLimiter::for('meta-webhook', function (Request $request): Limit {
    $key = $request->ip() ?? 'unknown';

    // Generous ceiling. Meta delivers bursts of small messages per
    // minute; anything beyond 600/min from a single IP is anomalous and
    // worth rejecting fast.
    return Limit::perMinute(600)->by('meta-webhook:'.$key);
});

Route::match(['get', 'post'], 'api/whatsapp/webhook', [MetaWebhookController::class, 'verify'])
    ->middleware('throttle:meta-webhook')
    ->name('meta.webhook.verify');

Route::post('api/whatsapp/webhook', [MetaWebhookController::class, 'receive'])
    ->middleware('throttle:meta-webhook')
    ->name('meta.webhook.receive');
