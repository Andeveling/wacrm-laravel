<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\ApiScope;
use App\Support\ApiKeyToken;
use Database\Factories\ApiKeyFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by
 * @property string $name
 * @property string $key_prefix
 * @property string $key_hash
 * @property array<int, string> $scopes
 * @property Carbon|null $last_used_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
#[Fillable(['account_id', 'created_by', 'name', 'key_prefix', 'key_hash', 'scopes', 'last_used_at', 'expires_at', 'revoked_at'])]
class ApiKey extends Model implements AuthenticatableContract
{
    /** @use HasFactory<ApiKeyFactory> */
    use Authenticatable, BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var string|null The "creator" column is set at creation only — never edited afterwards.
     */
    public const UPDATED_AT = null;

    /**
     * The new key's plaintext token, set only on the instance `rotate()`
     * returns. Never persisted — a real object property, not an Eloquent
     * attribute, so it's absent from `toArray()`/`toJson()`.
     */
    public ?string $plaintextToken = null;

    /**
     * The account this key belongs to. A key is always exactly one account.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The user who minted the key. Audit-only; null when the user is deleted.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Whether the key is usable right now. Revoked and expired keys fail closed.
     */
    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    /**
     * Whether an admin has explicitly revoked this key.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Whether this key's TTL has passed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Revoke immediately — for leaked keys. Unlike `rotate()`, there is no
     * grace window: the key stops authenticating as soon as this commits.
     */
    public function revoke(): void
    {
        $this->forceFill(['revoked_at' => now()])->save();
    }

    /**
     * Mint a replacement key with the same scopes, and give this key a 24h
     * grace window instead of dying immediately, so a client's config can be
     * updated without a hard cutover. Atomic — both writes commit together.
     * The new key's plaintext is available exactly once, on the returned
     * instance's `plaintextToken`.
     */
    public function rotate(): self
    {
        return DB::transaction(function (): self {
            $issued = ApiKeyToken::issue($this->environment());

            $newKey = static::create([
                'account_id' => $this->account_id,
                'created_by' => $this->created_by,
                'name' => $this->name,
                'key_prefix' => $issued['key_prefix'],
                'key_hash' => $issued['key_hash'],
                'scopes' => $this->scopes,
            ]);

            $newKey->plaintextToken = $issued['plaintext'];

            $this->forceFill(['expires_at' => now()->addHours(24)])->save();

            return $newKey;
        });
    }

    /**
     * The `live`/`test` environment this key was issued for, read back off
     * its `key_prefix` so `rotate()` mints the replacement in the same one.
     */
    private function environment(): string
    {
        return ApiKeyToken::environmentFromPrefix($this->key_prefix);
    }

    /**
     * Whether this key is allowed to perform actions under the given scope.
     * `null` scope is always granted — see `docs/public-api.md`: keys with no
     * scopes still authenticate, so `GET /api/v1/me` works for any active key.
     */
    public function hasScope(?ApiScope $scope): bool
    {
        if ($scope === null) {
            return true;
        }

        return in_array($scope->value, $this->scopes ?? [], true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
