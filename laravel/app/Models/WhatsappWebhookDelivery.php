<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WhatsappWebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Inbox durable del webhook de Meta WhatsApp Business (#64). Cada entrega
 * firmada se persiste ANTES de devolver 200 para que un fallo posterior
 * del worker no provoque reentregas innecesarias. La retención del
 * payload crudo es de 30 días (limpieza vía comando Artisan diario).
 *
 * `raw_payload` se almacena tal cual llegó desde Meta (jsonb en pgsql).
 * Cualquier columna derivada (e.g. `phone_number_id`, `wamid`) se añade
 * en tickets posteriores una vez la normalización de eventos (#66) las
 * necesite de verdad.
 *
 * @property string $id
 * @property string|null $signature_header
 * @property array<string, mixed> $raw_payload
 * @property int $content_length
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property string $processing_state
 * @property string|null $last_error
 * @property Carbon|null $created_at
 */
#[Fillable([
    'signature_header', 'raw_payload', 'content_length', 'received_at',
    'processed_at', 'processing_state', 'last_error',
])]
class WhatsappWebhookDelivery extends Model
{
    /** @use HasFactory<WhatsappWebhookDeliveryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const STATE_RECEIVED = 'received';

    public const STATE_PERSISTENCE_FAILED = 'persistence_failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'processing_state' => self::STATE_RECEIVED,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'content_length' => 'integer',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
