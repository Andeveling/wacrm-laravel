<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\WabaSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A WABA subscribed to the installation's Meta App for one Account.
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $integration_id
 * @property string|null $waba_id
 * @property Carbon|null $subscribed_apps_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account_id', 'integration_id', 'waba_id', 'subscribed_apps_at'])]
class WabaSubscription extends Model
{
    /** @use HasFactory<WabaSubscriptionFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<WhatsappIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(WhatsappIntegration::class, 'integration_id');
    }

    /**
     * @return HasMany<WhatsappPhoneNumberConnection, $this>
     */
    public function phoneNumberConnections(): HasMany
    {
        return $this->hasMany(WhatsappPhoneNumberConnection::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscribed_apps_at' => 'datetime',
        ];
    }
}
