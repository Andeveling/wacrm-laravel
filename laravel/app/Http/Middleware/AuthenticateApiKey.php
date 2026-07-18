<?php

namespace App\Http\Middleware;

use App\Auth\ApiKeyGuard;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use App\Models\Scopes\AccountScope;
use App\Support\ApiKeyToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a JSON API request via the `api_key` guard.
 *
 * Order of operations:
 *   1. Resolve the guard. Missing/invalid bearer token → `401 invalid_token`
 *      with the public-api.md error envelope and an RFC 6750
 *      `WWW-Authenticate` challenge naming the reason (revoked/expired) when
 *      known.
 *   2. Stash the resolved key on `$request->attributes['api_key']` so
 *      downstream middleware (scope checks, rate limiting) don't re-parse
 *      the bearer token.
 *   3. Bind the key's `account_id` to `AccountScope::CONTAINER_KEY` so
 *      tenant-aware models below this route see only the bound account.
 *
 * Routes under this middleware never reach the web session guard; the
 * `ensure.current-account` middleware is intentionally NOT applied.
 *
 * `terminate()` writes the audit trail (`last_used_at` + an `api_key_requests`
 * row) after the response is generated, so `duration_ms` is accurate and a
 * request that throws mid-flight is still logged. It only runs for requests
 * that got past `handle()` with a resolved key — a 401 from an unknown/bad
 * token isn't a key's request to log.
 */
class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiKeyGuard $guard */
        $guard = auth('api_key');

        $apiKey = $guard->apiKey();

        if ($apiKey === null) {
            return $this->unauthorized($request);
        }

        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_request_started_at', microtime(true));
        app()->instance(AccountScope::CONTAINER_KEY, $apiKey->account_id);

        return $next($request);
    }

    /**
     * Persist the audit trail for this request. Runs after the response is
     * sent to the client, outside the request's perceived latency.
     */
    public function terminate(Request $request, Response $response): void
    {
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey instanceof ApiKey) {
            return;
        }

        $startedAt = $request->attributes->get('api_key_request_started_at', microtime(true));

        $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();

        ApiKeyRequest::create([
            'api_key_id' => $apiKey->id,
            'account_id' => $apiKey->account_id,
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->headers->get('X-Request-Id'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'scope_used' => $request->attributes->get('scope_used'),
        ]);
    }

    /**
     * Build the 401 for a request the guard couldn't authenticate. Looks the
     * raw token up (bypassing the provider's active-only filter) purely to
     * tell a revoked/expired key apart from one that never existed — RFC
     * 6750 callers use `revoked`/`expired` on the challenge to skip pointless
     * retries.
     */
    private function unauthorized(Request $request): Response
    {
        $token = ApiKeyToken::fromAuthorizationHeader($request->headers->get('Authorization'));

        $key = $token !== null
            ? ApiKey::query()->withoutGlobalScopes()->where('key_hash', ApiKeyToken::hash($token))->first()
            : null;

        [$message, $reasonParam] = match (true) {
            $key?->isRevoked() === true => ['This API key has been revoked.', 'revoked="true"'],
            $key?->isExpired() === true => ['This API key has expired.', 'expired="true"'],
            default => ['Missing, malformed, or unknown API key.', null],
        };

        $challenge = 'Bearer error="invalid_token"'.($reasonParam !== null ? ', '.$reasonParam : '');

        return response()->json([
            'error' => [
                'code' => 'invalid_token',
                'message' => $message,
            ],
        ], 401)->withHeaders(['WWW-Authenticate' => $challenge]);
    }
}
