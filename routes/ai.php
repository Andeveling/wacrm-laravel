<?php

use App\Mcp\Servers\WacrmServer;
use App\Models\ApiKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Facades\Mcp;

/*
 * The `mcp` limiter is defined here, next to the only route that names
 * it, the way routes/webhooks.php defines `meta-webhook`. Without it
 * every request to the MCP server died with MissingRateLimiterException
 * before reaching a tool.
 *
 * Keyed by the API key `auth.mcp` resolved, so one account's agents can
 * never spend another's budget. 60/min matches the REST API: the tools
 * are read-only, and an agent that needs more than one call per second
 * is looping.
 */
RateLimiter::for('mcp', function (Request $request): Limit {
    /** @var ApiKey|null $apiKey */
    $apiKey = $request->attributes->get('api_key');

    return Limit::perMinute(60)->by('mcp:'.($apiKey->id ?? $request->ip() ?? 'unknown'));
});

Mcp::web('/mcp/wacrm', WacrmServer::class)
    ->middleware(['auth.mcp', 'throttle:mcp']);

Mcp::local('wacrm', WacrmServer::class);
