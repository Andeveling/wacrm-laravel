<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Domain\Meta\Support\MetaGraphException;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\Enums\AutomationConnectionMode;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WabaSubscription;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Support\Facades\Log;

final readonly class DisconnectWhatsappConnectionService
{
    public function __construct(private MetaGraphClientContract $meta) {}

    public function disconnect(string $connectionId, string $accountId): WhatsappConnectionResult
    {
        $connection = WhatsappPhoneNumberConnection::query()
            ->with(['wabaSubscription.integration'])
            ->whereKey($connectionId)
            ->firstOrFail();

        $connection->readiness = WhatsappConnectionReadiness::Disconnected;
        $connection->is_default = false;
        $connection->save();

        $this->pauseOutboundWork($connection);
        $this->unsubscribeWabaIfUnused($connection, $accountId);

        return WhatsappConnectionResult::success('Número desconectado. El historial se conserva.');
    }

    private function pauseOutboundWork(WhatsappPhoneNumberConnection $connection): void
    {
        Broadcast::query()
            ->where(fn ($query) => $query->where('connection_id', $connection->id)->orWhereNull('connection_id'))
            ->whereIn('status', [BroadcastStatus::Draft, BroadcastStatus::Scheduled, BroadcastStatus::Sending])
            ->update(['status' => BroadcastStatus::Paused]);

        Automation::query()
            ->where(function ($query) use ($connection): void {
                $query->where('connection_id', $connection->id)
                    ->orWhere(function ($query): void {
                        $query->whereNull('connection_id')
                            ->where('connection_mode', '!=', AutomationConnectionMode::Trigger);
                    });
            })
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function unsubscribeWabaIfUnused(WhatsappPhoneNumberConnection $connection, string $accountId): void
    {
        $waba = $connection->wabaSubscription;

        if (! $waba instanceof WabaSubscription || $waba->subscribed_apps_at === null || ! is_string($waba->waba_id)) {
            return;
        }

        $hasSiblings = WhatsappPhoneNumberConnection::query()
            ->whereBelongsTo($waba)
            ->where('readiness', '!=', WhatsappConnectionReadiness::Disconnected)
            ->exists();

        if ($hasSiblings) {
            return;
        }

        $token = $waba->integration?->access_token;

        if (! is_string($token) || $token === '') {
            return;
        }

        try {
            $this->meta->unsubscribeWaba($waba->waba_id, $token);
        } catch (MetaGraphException $exception) {
            Log::warning('Meta WhatsApp WABA unsubscribe failed', [
                'operation' => $exception->operation,
                'meta_code' => $exception->metaCode,
                'account_id' => $accountId,
                'waba_id' => $waba->waba_id,
            ]);

            return;
        }

        $waba->subscribed_apps_at = null;
        $waba->save();
    }
}
