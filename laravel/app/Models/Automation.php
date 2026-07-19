<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\AutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Automatización mensaje-level (precedencia: Flows > Automations > IA).
 * `trigger_type` es texto libre en el esquema (sin CHECK en Supabase 006);
 * el motor define el vocabulario. `execution_count` se incrementa atómico
 * en PHP (reemplaza el RPC increment_automation_execution_count de 007).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $name
 * @property string|null $description
 * @property string $trigger_type
 * @property array<string, mixed> $trigger_config
 * @property bool $is_active
 * @property int $execution_count
 * @property Carbon|null $last_executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'name', 'description', 'trigger_type', 'trigger_config', 'is_active',
])]
class Automation extends Model
{
    /** @use HasFactory<AutomationFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return HasMany<AutomationStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(AutomationStep::class);
    }

    /**
     * @return HasMany<AutomationLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(AutomationLog::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'is_active' => 'boolean',
            'last_executed_at' => 'datetime',
        ];
    }
}
