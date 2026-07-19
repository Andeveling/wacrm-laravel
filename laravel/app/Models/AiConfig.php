<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\AiProvider;
use Database\Factories\AiConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Configuración BYOK del asistente IA — una por cuenta (UNIQUE). Las
 * keys van cifradas en reposo (cast encrypted) y se inyectan por request
 * al SDK laravel/ai (#37); nunca hay keys de aplicación en config/ai.php.
 * `model` es texto libre: cualquier modelo que la key tenga disponible.
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by
 * @property AiProvider $provider
 * @property string $model
 * @property string $api_key
 * @property string|null $system_prompt
 * @property bool $is_active
 * @property bool $auto_reply_enabled
 * @property int $auto_reply_max_per_conversation
 * @property string|null $embeddings_api_key
 * @property int|null $handoff_agent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_id', 'created_by', 'provider', 'model', 'api_key', 'system_prompt',
    'is_active', 'auto_reply_enabled', 'auto_reply_max_per_conversation',
    'embeddings_api_key', 'handoff_agent_id',
])]
class AiConfig extends Model
{
    /** @use HasFactory<AiConfigFactory> */
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
    public function handoffAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handoff_agent_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => AiProvider::class,
            'api_key' => 'encrypted',
            'embeddings_api_key' => 'encrypted',
            'is_active' => 'boolean',
            'auto_reply_enabled' => 'boolean',
        ];
    }
}
