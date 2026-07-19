<?php

namespace App\Models;

use App\Models\Enums\MessageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Mensaje de un hilo. Sin account_id propio: se scopea vía su
 * conversación (igual que en Supabase). `message_id` es el id de Meta
 * (texto, deliberadamente no único entre números).
 *
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_type
 * @property int|null $sender_id
 * @property string $content_type
 * @property string|null $content_text
 * @property string|null $media_url
 * @property string|null $template_name
 * @property string|null $message_id
 * @property MessageStatus $status
 * @property string|null $reply_to_message_id
 * @property string|null $interactive_reply_id
 * @property array<array-key, mixed>|null $interactive_payload
 * @property bool $ai_generated
 * @property Carbon|null $created_at
 */
#[Fillable([
    'conversation_id', 'sender_type', 'sender_id', 'content_type', 'content_text',
    'media_url', 'template_name', 'message_id', 'status', 'reply_to_message_id',
    'interactive_reply_id', 'interactive_payload', 'ai_generated',
])]
class Message extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'content_type' => 'text',
        'status' => 'sent',
        'ai_generated' => false,
    ];

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * El mensaje al que este responde (self-FK interna, no el id de Meta).
     *
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /**
     * @return HasMany<MessageReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MessageStatus::class,
            'interactive_payload' => 'array',
            'ai_generated' => 'boolean',
        ];
    }
}
