<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\ApiScope;
use Database\Factories\ApiKeyFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
