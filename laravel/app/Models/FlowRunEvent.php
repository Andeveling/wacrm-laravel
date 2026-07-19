<?php

namespace App\Models;

use App\Models\Enums\FlowRunEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Audit trail de un run. Sin account_id: hereda tenencia vía run→flow.
 * El runner deduplica webhooks buscando por (flow_run_id, event_type) +
 * payload->meta_message_id (Supabase 010).
 *
 * @property string $id
 * @property string $flow_run_id
 * @property FlowRunEventType $event_type
 * @property string|null $node_key
 * @property array<string, mixed> $payload
 * @property Carbon|null $created_at
 */
#[Fillable(['flow_run_id', 'event_type', 'node_key', 'payload'])]
class FlowRunEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<FlowRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(FlowRun::class, 'flow_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => FlowRunEventType::class,
            'payload' => 'array',
        ];
    }
}
