<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Hilo 1:1 con un contacto. Única por (account_id, contact_id) — el
 * webhook entrante depende de ese backstop (Supabase 036).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $contact_id
 * @property string|null $connection_id
 * @property ConversationStatus $status
 * @property int|null $assigned_agent_id
 * @property string|null $last_message_text
 * @property Carbon|null $last_message_at
 * @property int $unread_count
 * @property bool $ai_autoreply_disabled
 * @property int $ai_reply_count
 * @property string|null $ai_handoff_summary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'contact_id', 'connection_id', 'status', 'assigned_agent_id',
    'last_message_text', 'last_message_at', 'unread_count',
    'ai_autoreply_disabled', 'ai_handoff_summary',
])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
        'unread_count' => 0,
        'ai_autoreply_disabled' => false,
        'ai_reply_count' => 0,
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<WhatsappPhoneNumberConnection, $this>
     */
    public function whatsappPhoneNumberConnection(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumberConnection::class, 'connection_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'last_message_at' => 'datetime',
            'unread_count' => 'integer',
            'ai_autoreply_disabled' => 'boolean',
            'ai_reply_count' => 'integer',
        ];
    }
}
