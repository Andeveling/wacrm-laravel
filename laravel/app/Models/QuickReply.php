<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\QuickReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snippet reusable del composer: texto plano (`content_text`) o mensaje
 * interactivo guardado (`interactive_payload`), según `kind`. `user_id`
 * es autor/auditoría; la tenencia es por cuenta.
 *
 * @property string $id
 * @property string $account_id
 * @property int $user_id
 * @property string $title
 * @property string $kind
 * @property string|null $content_text
 * @property array<array-key, mixed>|null $interactive_payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['account_id', 'user_id', 'title', 'kind', 'content_text', 'interactive_payload'])]
class QuickReply extends Model
{
    /** @use HasFactory<QuickReplyFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'text',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interactive_payload' => 'array',
        ];
    }
}
