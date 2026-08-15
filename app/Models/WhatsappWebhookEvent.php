<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WhatsappWebhookEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Individual change extracted from a global Webhook Delivery. It is not
 * tenant-scoped: unresolved work has no Account yet, and operator tools
 * must still see it.
 *
 * @property string $id
 * @property string $delivery_id
 * @property string|null $account_id
 * @property string|null $connection_id
 * @property string|null $phone_number_id
 * @property string $fingerprint
 * @property string $classification
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
#[Fillable([
    'delivery_id', 'account_id', 'connection_id', 'phone_number_id',
    'fingerprint', 'classification', 'payload',
])]
class WhatsappWebhookEvent extends Model
{
    /** @use HasFactory<WhatsappWebhookEventFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const CLASSIFICATION_PROCESSED = 'processed';

    public const CLASSIFICATION_UNRESOLVED = 'unresolved';

    public const CLASSIFICATION_UNSUPPORTED = 'unsupported';

    public const CLASSIFICATION_BLOCKED = 'blocked';

    public const CLASSIFICATION_UNCORRELATED = 'uncorrelated';

    public const CLASSIFICATION_FAILED = 'failed';

    /**
     * @return list<string>
     */
    public static function classifiableOutcomes(): array
    {
        return [
            self::CLASSIFICATION_FAILED,
            self::CLASSIFICATION_UNRESOLVED,
            self::CLASSIFICATION_BLOCKED,
            self::CLASSIFICATION_UNCORRELATED,
        ];
    }

    /**
     * @param  Builder<WhatsappWebhookEvent>  $query
     * @return Builder<WhatsappWebhookEvent>
     */
    public function scopeClassifiable(Builder $query): Builder
    {
        return $query->whereIn('classification', self::classifiableOutcomes());
    }

    /**
     * @return BelongsTo<WhatsappWebhookDelivery, $this>
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(WhatsappWebhookDelivery::class, 'delivery_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
