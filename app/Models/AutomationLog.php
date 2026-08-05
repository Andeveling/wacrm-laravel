<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\AutomationLogStatus;
use Database\Factories\AutomationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Historial de ejecución de una automation. `contact_id` es SET NULL al
 * borrar el contacto para no perder el audit trail (Supabase 004/006).
 *
 * @property string $id
 * @property string $automation_id
 * @property int $user_id
 * @property string $account_id
 * @property string|null $contact_id
 * @property string $trigger_event
 * @property array<int, mixed> $steps_executed
 * @property AutomationLogStatus $status
 * @property string|null $error_message
 * @property Carbon|null $created_at
 */
#[Fillable([
    'automation_id', 'user_id', 'account_id', 'contact_id',
    'trigger_event', 'steps_executed', 'status', 'error_message',
])]
class AutomationLog extends Model
{
    /** @use HasFactory<AutomationLogFactory> */
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
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'steps_executed' => 'array',
            'status' => AutomationLogStatus::class,
        ];
    }
}
