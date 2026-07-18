<?php

namespace App\Auth;

use App\Models\ApiKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use LogicException;

/**
 * Resolves a request's bearer token to an `ApiKey` model that satisfies
 * `Authenticatable`. Used exclusively by the `api_key` guard.
 *
 * Tokens come in already SHA-256 hashed by the guard, so the lookup is just
 * `where('key_hash', $h)->first()`. `revoked_at` and `expires_at` filter out
 * dead keys before they reach `setUser()` — the guard contract is "the user
 * is whoever the guard sets, already validated", and the provider is the
 * right place to enforce that.
 *
 * `retrieveById` exists only because the framework may issue session
 * re-resolution calls; we keep it because the lookup is cheap and bound to
 * the same row set. Other contract methods are unsupported — bearer-token
 * auth has no remember-me, no rehash, and no other code path.
 */
class ApiKeyUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return ApiKey::query()->withoutGlobalScopes()->find($identifier);
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, #[\SensitiveParameter] $token): void
    {
        // Not applicable — API keys never ride remember-me cookies.
    }

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $hash = $credentials['key_hash'] ?? null;

        if (! is_string($hash) || $hash === '') {
            return null;
        }

        $key = ApiKey::query()->withoutGlobalScopes()->where('key_hash', $hash)->first();

        return ($key !== null && $key->isActive()) ? $key : null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        // Reused by TokenGuard during credential login. Bearer-token auth
        // never goes through validate() — only retrieveByCredentials() —
        // so reaching this branch means the upstream code is misusing the
        // contract. Surface that loudly so the misuse doesn't hide.
        throw new LogicException('ApiKeyUserProvider does not support validateCredentials; bearer auth uses retrieveByCredentials.');
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // ApiKey has no password — sha256 is fixed-length, never needs rehashing.
    }
}
