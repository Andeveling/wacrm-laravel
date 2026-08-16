<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\BroadcastStatus;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Difusión de plantilla WhatsApp a una audiencia. Los contadores
 * (sent/delivered/read/replied/failed) los mantiene el trigger SQL
 * incremental de broadcast_recipients (Supabase 005) — nunca escribirlos
 * desde PHP; ante deriva, ops corre recompute_broadcast_counts(id).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string|null $connection_id
 * @property string $name
 * @property string $template_name
 * @property string $template_language
 * @property array<string, mixed>|null $template_variables
 * @property array<string, mixed>|null $audience_filter
 * @property Carbon|null $scheduled_at
 * @property BroadcastStatus $status
 * @property int|null $total_recipients
 * @property int|null $sent_count
 * @property int|null $delivered_count
 * @property int|null $read_count
 * @property int|null $replied_count
 * @property int|null $failed_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'connection_id', 'name', 'template_name', 'template_language',
    'template_variables', 'audience_filter', 'scheduled_at', 'status', 'total_recipients',
])]
class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<WhatsappPhoneNumberConnection, $this>
     */
    public function whatsappPhoneNumberConnection(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumberConnection::class, 'connection_id');
    }

    /**
     * @return HasMany<BroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_variables' => 'array',
            'audience_filter' => 'array',
            'scheduled_at' => 'datetime',
            'status' => BroadcastStatus::class,
        ];
    }
}
