<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey extends AbstractBearerAuthMiddleware
{
    protected function afterAuthenticated(Request $request, ApiKey $apiKey): void
    {
        $request->attributes->set('api_key_request_started_at', microtime(true));
    }

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
}
