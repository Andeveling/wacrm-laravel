<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\FlowRunStatus;
use Database\Factories\FlowRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ejecución de un flow para un contacto. El UNIQUE parcial
 * idx_one_active_run_per_contact garantiza a lo sumo un run activo por
 * (account_id, contact_id): el runner atrapa el 23505 del segundo INSERT
 * concurrente y lo trata como consumido. Sin created/updated_at propios:
 * el ciclo de vida vive en started/last_advanced/ended_at.
 *
 * @property string $id
 * @property string $flow_id
 * @property int $user_id
 * @property string $account_id
 * @property string|null $contact_id
 * @property string|null $conversation_id
 * @property FlowRunStatus $status
 * @property string|null $current_node_key
 * @property string|null $last_prompt_message_id
 * @property array<string, mixed> $vars
 * @property int $reprompt_count
 * @property Carbon $started_at
 * @property Carbon $last_advanced_at
 * @property Carbon|null $ended_at
 * @property string|null $end_reason
 */
#[Fillable([
    'flow_id', 'user_id', 'account_id', 'contact_id', 'conversation_id',
    'status', 'current_node_key', 'last_prompt_message_id', 'vars',
    'reprompt_count', 'started_at', 'last_advanced_at', 'ended_at', 'end_reason',
])]
class FlowRun extends Model
{
    /** @use HasFactory<FlowRunFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public $timestamps = false;

    /**
     * @return BelongsTo<Flow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return HasMany<FlowRunEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(FlowRunEvent::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FlowRunStatus::class,
            'vars' => 'array',
            'started_at' => 'datetime',
            'last_advanced_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
