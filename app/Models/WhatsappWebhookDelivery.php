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
 * `raw_body` guarda el cuerpo HTTP byte-exacto que Meta firmó — el
 * `raw_payload jsonb` es un derivado para queries tipadas (#66+);
 * `raw_body` es la fuente de verdad (auditoría, replay, evidencia).
 *
 * @property string $id
 * @property string|null $signature_header
 * @property string $raw_body
 * @property array<string, mixed>|null $raw_payload
 * @property int $content_length
 * @property Carbon $received_at
 * @property Carbon|null $processed_at
 * @property string $processing_state
 * @property Carbon|null $created_at
 */
#[Fillable([
    'signature_header', 'raw_body', 'raw_payload', 'content_length', 'received_at',
    'processed_at', 'processing_state',
])]
class WhatsappWebhookDelivery extends Model
{
    /** @use HasFactory<WhatsappWebhookDeliveryFactory> */
    use HasFactory, HasUuids;

    public const UPDATED_AT = null;

    public const STATE_RECEIVED = 'received';

    public const STATE_QUEUED = 'queued';

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
