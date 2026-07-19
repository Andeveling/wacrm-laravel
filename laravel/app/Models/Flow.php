<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\FlowStatus;
use App\Models\Enums\FlowTriggerType;
use Database\Factories\FlowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Flow conversacional (precedencia máxima entre responders).
 * `entry_node_id` referencia flow_nodes.node_key (string, no UUID) y es
 * NULL mientras se autorea — el validador lo exige antes de activar.
 * `execution_count` se incrementa atómico en PHP (reemplaza el RPC de 012).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $name
 * @property string|null $description
 * @property FlowStatus $status
 * @property FlowTriggerType $trigger_type
 * @property array<string, mixed> $trigger_config
 * @property string|null $entry_node_id
 * @property array<string, mixed> $fallback_policy
 * @property int $execution_count
 * @property Carbon|null $last_executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'name', 'description', 'status',
    'trigger_type', 'trigger_config', 'entry_node_id', 'fallback_policy',
])]
class Flow extends Model
{
    /** @use HasFactory<FlowFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<FlowNode, $this>
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(FlowNode::class);
    }

    /**
     * @return HasMany<FlowRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FlowStatus::class,
            'trigger_type' => FlowTriggerType::class,
            'trigger_config' => 'array',
            'fallback_policy' => 'array',
            'last_executed_at' => 'datetime',
        ];
    }
}
