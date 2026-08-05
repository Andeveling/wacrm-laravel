<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\AutomationBranch;
use App\Models\Enums\PendingExecutionStatus;
use Database\Factories\AutomationPendingExecutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cola de reanudación tras un step `wait`: el scheduler drena filas con
 * run_at <= now() y status pending, las pasa a running y reanuda desde
 * `next_step_position` con el `context` guardado (Supabase 006).
 *
 * @property string $id
 * @property string $automation_id
 * @property int $user_id
 * @property string $account_id
 * @property string|null $contact_id
 * @property string|null $log_id
 * @property string|null $parent_step_id
 * @property AutomationBranch|null $branch
 * @property int $next_step_position
 * @property array<string, mixed> $context
 * @property PendingExecutionStatus $status
 * @property Carbon $run_at
 * @property Carbon|null $created_at
 */
#[Fillable([
    'automation_id', 'user_id', 'account_id', 'contact_id', 'log_id',
    'parent_step_id', 'branch', 'next_step_position', 'context', 'status', 'run_at',
])]
class AutomationPendingExecution extends Model
{
    /** @use HasFactory<AutomationPendingExecutionFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Automation, $this>
     */
    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    /**
     * @return BelongsTo<AutomationLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(AutomationLog::class, 'log_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch' => AutomationBranch::class,
            'context' => 'array',
            'status' => PendingExecutionStatus::class,
            'run_at' => 'datetime',
        ];
    }
}
