<?php

namespace App\Models;

use App\Models\Enums\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property AccountType $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccountUser|null $pivot
 */
#[Fillable(['name', 'type'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory, HasUuids;

    /**
     * Display name every user's auto-created Personal account gets.
     */
    public const PERSONAL_NAME = 'Personal';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
        ];
    }

    /**
     * The users that belong to the account.
     *
     * @return BelongsToMany<User, $this, AccountUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')
            ->using(AccountUser::class)
            ->withPivot('role', 'joined_at');
    }

    /**
     * @return HasOne<WhatsappIntegration, $this>
     */
    public function whatsappIntegration(): HasOne
    {
        return $this->hasOne(WhatsappIntegration::class);
    }

    /**
     * Create a new Personal account. Every user gets exactly one, auto-created
     * at registration; the name/type pair is canonical here so the factory's
     * `personal()` state and registration don't drift out of sync.
     */
    public static function createPersonal(): self
    {
        return static::create(['name' => self::PERSONAL_NAME, 'type' => AccountType::Personal]);
    }
}
