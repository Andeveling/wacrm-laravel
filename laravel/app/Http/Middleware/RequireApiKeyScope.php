<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\Enums\ApiScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces that the authenticated API key carries a required scope.
 *
 * Expects to run after `AuthenticateApiKey`, which stashes the resolved key
 * on `$request->attributes['api_key']`. If that attribute is missing —
 * e.g. a route declares `scope:` without `AuthenticateApiKey` ahead of it —
 * this fails closed with `401 invalid_token` rather than fataling on a null
 * key. A key that authenticates fine but lacks the scope fails closed with
 * `403 insufficient_scope`, naming the missing scope in the RFC 6750
 * challenge so the caller knows exactly what to ask an admin for.
 */
class RequireApiKeyScope
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if ($apiKey === null) {
            return response()->json([
                'error' => [
                    'code' => 'invalid_token',
                    'message' => 'Missing, malformed, or unknown API key.',
                ],
            ], 401)->withHeaders(['WWW-Authenticate' => 'Bearer error="invalid_token"']);
        }

        $required = ApiScope::tryFrom($scope);

        if ($required === null || ! $apiKey->hasScope($required)) {
            return response()->json([
                'error' => [
                    'code' => 'insufficient_scope',
                    'message' => "This API key is missing the '{$scope}' scope.",
                ],
            ], 403)->withHeaders([
                'WWW-Authenticate' => sprintf('Bearer error="insufficient_scope", scope="%s"', $scope),
            ]);
        }

        return $next($request);
    }
}
