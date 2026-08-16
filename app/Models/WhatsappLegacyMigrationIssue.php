<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\WhatsappLegacyMigrationIssueKind;
use Database\Factories\WhatsappLegacyMigrationIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Sanitized, tenant-safe work list for legacy mappings that need explicit
 * remediation. It deliberately contains no tokens or raw webhook payloads.
 *
 * @property string $id
 * @property string $account_id
 * @property string|null $legacy_config_id
 * @property string|null $conversation_id
 * @property WhatsappLegacyMigrationIssueKind $kind
 * @property array<string, mixed> $details
 * @property string $fingerprint
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'account_id', 'legacy_config_id', 'conversation_id', 'kind', 'details', 'fingerprint',
    'resolved_at',
])]
class WhatsappLegacyMigrationIssue extends Model
{
    /** @use HasFactory<WhatsappLegacyMigrationIssueFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => WhatsappLegacyMigrationIssueKind::class,
            'details' => 'array',
            'resolved_at' => 'datetime',
        ];
    }
}
