<?php

namespace App\Models;

use App\Models\Enums\FlowNodeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Nodo de un flow. Sin account_id: hereda tenencia vía flow.
 * `node_key` es único por flow y es la referencia que usan
 * entry_node_id y las transiciones en config. position_x/y quedan
 * reservados para el canvas react-flow v2 (Supabase 010).
 *
 * @property string $id
 * @property string $flow_id
 * @property string $node_key
 * @property FlowNodeType $node_type
 * @property array<string, mixed> $config
 * @property int $position_x
 * @property int $position_y
 * @property Carbon|null $created_at
 */
#[Fillable([
    'flow_id', 'node_key', 'node_type', 'config', 'position_x', 'position_y',
])]
class FlowNode extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Flow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'node_type' => FlowNodeType::class,
            'config' => 'array',
        ];
    }
}
