<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Endpoint saliente al que la cuenta suscribe eventos (firma HMAC con
 * `secret`, cifrado en reposo). `events` es text[] en pgsql — sin cast
 * Eloquent: el cast `array` escribe JSON, incompatible con el literal
 * `{a,b}`; el módulo de webhooks (#27) definirá su acceso tipado.
 * `failure_count` son fallos consecutivos; vuelve a 0 con cada entrega.
 *
 * @property string $id
 * @property string $account_id
 * @property int|null $created_by
 * @property string $url
 * @property string $secret
 * @property string $events
 * @property bool $is_active
 * @property Carbon|null $last_delivery_at
 * @property int $failure_count
 * @property Carbon|null $created_at
 */
#[Fillable([
    'account_id', 'created_by', 'url', 'secret', 'events', 'is_active',
])]
class WebhookEndpoint extends Model
{
    /** @use HasFactory<WebhookEndpointFactory> */
    use BelongsToAccount, HasFactory, HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'last_delivery_at' => 'datetime',
        ];
    }
}
