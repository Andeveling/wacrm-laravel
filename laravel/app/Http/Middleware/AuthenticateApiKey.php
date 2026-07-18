<?php

namespace App\Http\Middleware;

use App\Auth\ApiKeyGuard;
use App\Models\Scopes\AccountScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a JSON API request via the `api_key` guard.
 *
 * Order of operations:
 *   1. Resolve the guard. Missing/invalid bearer token → `401 unauthorized`
 *      with the public-api.md error envelope.
 *   2. Bind the key's `account_id` to `AccountScope::CONTAINER_KEY` so
 *      tenant-aware models below this route see only the bound account.
 *
 * Routes under this middleware never reach the web session guard; the
 * `ensure.current-account` middleware is intentionally NOT applied.
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
            return response()->json([
                'error' => [
                    'code' => 'invalid_token',
                    'message' => 'Missing, malformed, or unknown API key.',
                ],
            ], 401);
        }

        app()->instance(AccountScope::CONTAINER_KEY, $apiKey->account_id);

        return $next($request);
    }
}
