<?php

namespace App\Models;

use App\Models\Enums\BroadcastRecipientStatus;
use Database\Factories\BroadcastRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Destinatario de una difusión. Sin account_id propio: hereda tenencia
 * vía broadcast. `whatsapp_message_id` correlaciona los webhooks de
 * estado de Meta (único parcial); cambiar `status` dispara el trigger
 * incremental de contadores del padre.
 *
 * @property string $id
 * @property string $broadcast_id
 * @property string|null $contact_id
 * @property BroadcastRecipientStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $replied_at
 * @property string|null $error_message
 * @property string|null $whatsapp_message_id
 * @property Carbon|null $created_at
 */
#[Fillable([
    'broadcast_id', 'contact_id', 'status', 'sent_at', 'delivered_at',
    'read_at', 'replied_at', 'error_message', 'whatsapp_message_id',
])]
class BroadcastRecipient extends Model
{
    /** @use HasFactory<BroadcastRecipientFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Broadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BroadcastRecipientStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'replied_at' => 'datetime',
        ];
    }
}
