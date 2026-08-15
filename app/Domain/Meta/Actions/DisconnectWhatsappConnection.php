<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WhatsappConnectionResponder;
use App\Domain\Meta\Results\WhatsappConnectionResult;
use App\Domain\Meta\Services\MetaGraphClientContract;
use App\Domain\Meta\Support\MetaGraphException;
use App\Models\Automation;
use App\Models\Broadcast;
use App\Models\Enums\BroadcastStatus;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WabaSubscription;
use App\Models\WhatsappPhoneNumberConnection;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Disable a phone-number connection without deleting CRM history.
 * The id arrives as a plain string — implicit binding would resolve
 * before EnsureCurrentAccount binds the tenant.
 *
 * A WABA stays subscribed while any sibling connection still uses it.
 */
final class DisconnectWhatsappConnection
{
    public function __invoke(
        CurrentAccount $account,
        string $connection,
        MetaGraphClientContract $meta,
        WhatsappConnectionResponder $responder,
    ): RedirectResponse {
        abort_unless($account->isAdmin(), 403);

        $phoneConnection = WhatsappPhoneNumberConnection::query()
            ->with(['wabaSubscription.integration'])
            ->whereKey($connection)
            ->firstOrFail();

        $phoneConnection->readiness = WhatsappConnectionReadiness::Disconnected;
        $phoneConnection->is_default = false;
        $phoneConnection->save();

        $this->pausePinnedBroadcasts($phoneConnection);
        $this->pausePinnedAutomations($phoneConnection);
        $this->unsubscribeWabaIfUnused($phoneConnection, $meta, $account);

        return $responder->respond(WhatsappConnectionResult::success(
            'Número desconectado. El historial se conserva.',
        ));
    }

    private function pausePinnedBroadcasts(WhatsappPhoneNumberConnection $phoneConnection): void
    {
        Broadcast::query()
            ->whereBelongsTo($phoneConnection, 'whatsappPhoneNumberConnection')
            ->whereIn('status', [
                BroadcastStatus::Draft,
                BroadcastStatus::Scheduled,
                BroadcastStatus::Sending,
            ])
            ->update(['status' => BroadcastStatus::Paused]);
    }

    private function pausePinnedAutomations(WhatsappPhoneNumberConnection $phoneConnection): void
    {
        Automation::query()
            ->whereBelongsTo($phoneConnection, 'whatsappPhoneNumberConnection')
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function unsubscribeWabaIfUnused(
        WhatsappPhoneNumberConnection $phoneConnection,
        MetaGraphClientContract $meta,
        CurrentAccount $account,
    ): void {
        $waba = $phoneConnection->wabaSubscription;

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
            $meta->unsubscribeWaba($waba->waba_id, $token);
        } catch (MetaGraphException $exception) {
            Log::warning('Meta WhatsApp WABA unsubscribe failed', [
                'operation' => $exception->operation,
                'meta_code' => $exception->metaCode,
                'account_id' => $account->id(),
                'waba_id' => $waba->waba_id,
            ]);

            return;
        }

        $waba->subscribed_apps_at = null;
        $waba->save();
    }
}
