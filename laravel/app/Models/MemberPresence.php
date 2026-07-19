<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\PresenceStatus;
use Database\Factories\MemberPresenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Heartbeat de presencia por miembro (PK = user_id, una fila por usuario).
 * "Offline" se deriva de la antigüedad de last_seen_at, no se persiste.
 *
 * @property int $user_id
 * @property string $account_id
 * @property PresenceStatus $status
 * @property Carbon $last_seen_at
 */
#[Fillable(['user_id', 'account_id', 'status', 'last_seen_at'])]
class MemberPresence extends Model
{
    /** @use HasFactory<MemberPresenceFactory> */
    use BelongsToAccount, HasFactory;

    protected $table = 'member_presence';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'online',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

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
            'status' => PresenceStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
