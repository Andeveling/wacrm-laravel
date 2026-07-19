<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $phone
 * @property string|null $name
 * @property string|null $email
 * @property string|null $company
 * @property string|null $avatar_url
 * @property string|null $phone_normalized
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['user_id', 'account_id', 'phone', 'name', 'email', 'company', 'avatar_url'])]
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    protected static function booted(): void
    {
        // En pgsql phone_normalized es columna GENERATED (solo dígitos, 022);
        // sqlite no tiene regexp_replace, así que ahí la calcula el modelo.
        static::saving(function (Contact $contact): void {
            if ($contact->getConnection()->getDriverName() !== 'pgsql') {
                $contact->phone_normalized = preg_replace('/\D/', '', $contact->phone ?? '');
            }
        });
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Tag, $this, ContactTag>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'contact_tags')->using(ContactTag::class);
    }

    /**
     * @return HasMany<ContactNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class);
    }

    /**
     * @return HasMany<ContactCustomValue, $this>
     */
    public function customValues(): HasMany
    {
        return $this->hasMany(ContactCustomValue::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
}
