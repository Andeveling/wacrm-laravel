<?php

namespace App\Auth;

use App\Models\ApiKey;
use App\Support\ApiKeyToken;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Traits\Macroable;

/**
 * Bearer-token guard for the public REST API.
 *
 * Reads `Authorization: Bearer wacrm_(live|test)_<hex>` off the request,
 * hashes the plaintext with SHA-256, and hands the hash to the configured
 * user provider. The provider returns the matching ApiKey (or `null` if
 * the key is unknown, revoked, or expired) and we set it as the request's
 * authenticated principal.
 *
 * Coexists with the `web` guard (Fortify session). The web dashboard auth
 * path never reaches here; this guard activates only on routes that include
 * the `auth.api_key` middleware.
 */
class ApiKeyGuard implements Guard
{
    use GuardHelpers, Macroable;

    /**
     * Current request — held for the lifetime of the guard so the bearer
     * lookup can read `Authorization` without depending on a global.
     */
    protected Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request = $request;
    }

    /**
     * Hash the request's bearer token and look the principal up in storage.
     * Cached per-request so subsequent `auth('api_key')->user()` calls are O(1).
     */
    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = ApiKeyToken::fromAuthorizationHeader($this->request->headers->get('Authorization'));

        if ($token === null) {
            return null;
        }

        $user = $this->provider->retrieveByCredentials([
            'key_hash' => ApiKeyToken::hash($token),
        ]);

        return $this->user = $user;
    }

    public function validate(array $credentials = []): bool
    {
        if (! isset($credentials['token'])) {
            return false;
        }

        $token = (string) $credentials['token'];

        if (ApiKeyToken::fromAuthorizationHeader('Bearer '.$token) === null) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials([
            'key_hash' => ApiKeyToken::hash($token),
        ]);

        if ($user === null) {
            return false;
        }

        $this->setUser($user);

        return true;
    }

    /**
     * Touch `last_used_at` on the resolved key. Currently a no-op here —
     * the middleware does the touch in its `terminate()` step so a request
     * that crashes mid-flight still records an attempt.
     *
     * @param  Authenticatable&\App\Models\ApiKey|null  $user
     */
    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Expose the resolved principal — handy in middleware that needs to
     * bind the account context after auth succeeds.
     */
    public function apiKey(): ?ApiKey
    {
        $user = $this->user();

        return $user instanceof ApiKey ? $user : null;
    }
}
