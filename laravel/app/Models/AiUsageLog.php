<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\AiProvider;
use App\Models\Enums\AiUsageMode;
use Database\Factories\AiUsageLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Registro de gasto de tokens por invocación IA; se puebla del usage de
 * las responses del SDK (#37). Solo lectura account-scoped newest-first
 * (dashboards de gasto).
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $conversation_id
 * @property AiUsageMode $mode
 * @property AiProvider $provider
 * @property string $model
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property Carbon|null $created_at
 */
#[Fillable([
    'account_id', 'conversation_id', 'mode', 'provider', 'model',
    'prompt_tokens', 'completion_tokens', 'total_tokens',
])]
class AiUsageLog extends Model
{
    /** @use HasFactory<AiUsageLogFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'ai_usage_log';

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => AiUsageMode::class,
            'provider' => AiProvider::class,
        ];
    }
}
