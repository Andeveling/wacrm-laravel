<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reacción emoji sobre un mensaje: una por (message, actor) por UNIQUE.
 * `conversation_id` está desnormalizada para poder suscribirse al hilo
 * (herencia de Supabase 009, útil igual para canales de Reverb).
 *
 * @property string $id
 * @property string $message_id
 * @property string $conversation_id
 * @property string $actor_type
 * @property int|null $actor_id
 * @property string $emoji
 * @property Carbon|null $created_at
 */
#[Fillable(['message_id', 'conversation_id', 'actor_type', 'actor_id', 'emoji'])]
class MessageReaction extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
