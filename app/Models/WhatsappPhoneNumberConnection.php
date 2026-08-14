<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\WhatsappConnectionReadiness;
use Database\Factories\WhatsappPhoneNumberConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A routable Meta phone number. It remains historical and queryable after
 * disconnecting, while readiness controls whether runtime operations may use it.
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $waba_subscription_id
 * @property string|null $phone_number_id
 * @property WhatsappConnectionReadiness $readiness
 * @property bool $is_default
 * @property string|null $legacy_config_id
 * @property Carbon|null $connected_at
 * @property Carbon|null $registered_at
 * @property string|null $last_registration_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_id', 'waba_subscription_id', 'phone_number_id', 'readiness', 'is_default',
    'legacy_config_id', 'connected_at', 'registered_at', 'last_registration_error',
])]
class WhatsappPhoneNumberConnection extends Model
{
    /** @use HasFactory<WhatsappPhoneNumberConnectionFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'readiness' => WhatsappConnectionReadiness::CredentialsVerified->value,
        'is_default' => false,
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<WabaSubscription, $this>
     */
    public function wabaSubscription(): BelongsTo
    {
        return $this->belongsTo(WabaSubscription::class);
    }

    /**
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'connection_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'readiness' => WhatsappConnectionReadiness::class,
            'is_default' => 'boolean',
            'connected_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }
}
