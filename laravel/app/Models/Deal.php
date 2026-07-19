<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\DealStatus;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Oportunidad en un pipeline. `contact_id` es SET NULL al borrar el
 * contacto para no perder historial (Supabase 004).
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $pipeline_id
 * @property string $stage_id
 * @property string|null $contact_id
 * @property string|null $conversation_id
 * @property int|null $assigned_to
 * @property string $title
 * @property numeric-string $value
 * @property string|null $currency
 * @property string|null $notes
 * @property Carbon|null $expected_close_date
 * @property DealStatus|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'pipeline_id', 'stage_id', 'contact_id', 'conversation_id',
    'assigned_to', 'title', 'value', 'currency', 'notes', 'expected_close_date', 'status',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'value' => 0,
        'currency' => 'USD',
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Pipeline, $this>
     */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /**
     * @return BelongsTo<PipelineStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'stage_id');
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
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'expected_close_date' => 'date',
            'status' => DealStatus::class,
        ];
    }
}
