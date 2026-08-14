<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\WhatsappIntegrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned WhatsApp credentials. One integration groups all WABAs and
 * phone-number connections belonging to an Account.
 *
 * The access token is encrypted at rest and is never part of a serialized
 * tenant-facing model projection.
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by
 * @property string|null $access_token
 * @property string|null $legacy_config_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account_id', 'created_by', 'access_token', 'legacy_config_id'])]
#[Hidden(['access_token'])]
class WhatsappIntegration extends Model
{
    /** @use HasFactory<WhatsappIntegrationFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<WabaSubscription, $this>
     */
    public function wabaSubscriptions(): HasMany
    {
        return $this->hasMany(WabaSubscription::class, 'integration_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
        ];
    }
}
