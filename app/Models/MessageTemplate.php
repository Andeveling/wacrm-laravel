<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Models\Enums\MessageTemplateStatus;
use Database\Factories\MessageTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Plantilla de WhatsApp sincronizada con Meta. El upsert del sync matchea
 * por (user_id, name, language) — UNIQUE en DB.
 *
 * @property string $id
 * @property int $user_id
 * @property string $account_id
 * @property string $name
 * @property string $category
 * @property string|null $language
 * @property string|null $header_type
 * @property string|null $header_content
 * @property string $body_text
 * @property string|null $footer_text
 * @property array<array-key, mixed>|null $buttons
 * @property MessageTemplateStatus|null $status
 * @property array<array-key, mixed>|null $sample_values
 * @property string|null $meta_template_id
 * @property string|null $rejection_reason
 * @property string|null $quality_score
 * @property string|null $header_handle
 * @property string|null $header_media_url
 * @property string|null $submission_error
 * @property Carbon|null $last_submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'user_id', 'account_id', 'name', 'category', 'language', 'header_type',
    'header_content', 'body_text', 'footer_text', 'buttons', 'status',
    'sample_values', 'meta_template_id', 'rejection_reason', 'quality_score',
    'header_handle', 'header_media_url', 'submission_error', 'last_submitted_at',
])]
class MessageTemplate extends Model
{
    /** @use HasFactory<MessageTemplateFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'category' => 'Marketing',
        'language' => 'en_US',
        'status' => 'DRAFT',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MessageTemplateStatus::class,
            'buttons' => 'array',
            'sample_values' => 'array',
            'last_submitted_at' => 'datetime',
        ];
    }
}
