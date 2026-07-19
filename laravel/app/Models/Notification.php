<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Notificación in-app. `user_id` es el receptor; `actor_user_id` quien la
 * disparó (null = automatización/sistema). Desde el cliente solo tiene
 * sentido tocar `read_at` — eso lo custodia la Policy, no el modelo.
 *
 * @property string $id
 * @property string $account_id
 * @property int $user_id
 * @property string $type
 * @property string|null $conversation_id
 * @property string|null $contact_id
 * @property int|null $actor_user_id
 * @property string $title
 * @property string|null $body
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'account_id', 'user_id', 'type', 'conversation_id', 'contact_id',
    'actor_user_id', 'title', 'body', 'read_at',
])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'conversation_assigned',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
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
            'read_at' => 'datetime',
        ];
    }
}
