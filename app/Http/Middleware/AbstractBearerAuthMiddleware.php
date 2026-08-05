<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\ApiKeyGuard;
use App\Models\ApiKey;
use App\Models\Scopes\AccountScope;
use App\Support\ApiKeyToken;
use Closure;
use Illuminate\Http\Request;

abstract class AbstractBearerAuthMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var ApiKeyGuard $guard */
        $guard = auth('api_key');

        $apiKey = $guard->apiKey();

        if ($apiKey === null) {
            return $this->unauthorized($request);
        }

        $request->attributes->set('api_key', $apiKey);
        app()->instance(AccountScope::CONTAINER_KEY, $apiKey->account_id);
        $this->afterAuthenticated($request, $apiKey);

        return $next($request);
    }

    protected function afterAuthenticated(Request $request, ApiKey $apiKey): void
    {
        //
    }

    private function unauthorized(Request $request): mixed
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
