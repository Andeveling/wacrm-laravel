<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\WhatsappConfigStatus;
use Database\Factories\WhatsappConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Config del número de WhatsApp de la cuenta (una por cuenta, y un
 * phone_number_id no puede pertenecer a dos cuentas — el webhook rutea
 * por él).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $phone_number_id
 * @property string|null $waba_id
 * @property string $access_token
 * @property string|null $verify_token
 * @property WhatsappConfigStatus $status
 * @property Carbon|null $connected_at
 * @property Carbon|null $registered_at
 * @property Carbon|null $subscribed_apps_at
 * @property string|null $last_registration_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'phone_number_id', 'waba_id', 'access_token', 'verify_token',
    'status', 'connected_at', 'registered_at', 'subscribed_apps_at', 'last_registration_error',
])]
class WhatsappConfig extends Model
{
    /** @use HasFactory<WhatsappConfigFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    protected $table = 'whatsapp_config';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'disconnected',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WhatsappConfigStatus::class,
            'connected_at' => 'datetime',
            'registered_at' => 'datetime',
            'subscribed_apps_at' => 'datetime',
        ];
    }
}
