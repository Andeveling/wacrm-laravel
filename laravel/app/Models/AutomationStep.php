<?php

namespace App\Models;

use App\Models\Enums\AutomationBranch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Step de una automation. Sin account_id: hereda tenencia vía automation.
 * `parent_step_id`+`branch` anidan steps dentro de una rama de un step
 * Condition; NULL en ambos = step raíz ordenado por `position`.
 *
 * @property string $id
 * @property string $automation_id
 * @property string|null $parent_step_id
 * @property AutomationBranch|null $branch
 * @property string $step_type
 * @property array<string, mixed> $step_config
 * @property int $position
 * @property Carbon|null $created_at
 */
#[Fillable([
    'automation_id', 'parent_step_id', 'branch', 'step_type', 'step_config', 'position',
])]
class AutomationStep extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * @return BelongsTo<AutomationStep, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(AutomationStep::class, 'parent_step_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch' => AutomationBranch::class,
            'step_config' => 'array',
        ];
    }
}
