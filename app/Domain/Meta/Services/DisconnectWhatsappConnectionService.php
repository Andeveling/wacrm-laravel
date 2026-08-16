<?php

declare(strict_types=1);

namespace App\Domain\Meta\Services;

use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;

final readonly class DisconnectWhatsappConnectionService
{
    public function disconnect(string $connectionId, string $accountId): WhatsappConnectionResult
    {
        $connection = WhatsappPhoneNumberConnection::query()
            ->with(['wabaSubscription.integration'])
            ->whereKey($connectionId)
            ->firstOrFail();

        $connection->readiness = WhatsappConnectionReadiness::Disconnected;
        $connection->is_default = false;
        $connection->pending_default = false;
        $connection->save();

        $this->pauseOutboundWork($connection);

        return WhatsappConnectionResult::success('Número desconectado. El historial se conserva.');
    }

    private function pauseOutboundWork(WhatsappPhoneNumberConnection $connection): void
    {
        Broadcast::query()
            ->where('connection_id', $connection->id)
            ->whereIn('status', [BroadcastStatus::Draft, BroadcastStatus::Scheduled, BroadcastStatus::Sending])
            ->update(['status' => BroadcastStatus::Paused]);

        Automation::query()
            ->where('connection_id', $connection->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }
}
