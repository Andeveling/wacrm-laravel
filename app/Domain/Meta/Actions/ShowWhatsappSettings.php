<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WhatsappPhoneNumberConnection;
use App\Models\WhatsappWebhookEvent;
use App\Support\CurrentAccount;
use Inertia\Inertia;
use Inertia\Response;

final class ShowWhatsappSettings
{
    public function __invoke(CurrentAccount $account): Response
    {
        $connections = WhatsappPhoneNumberConnection::query()
            ->with('wabaSubscription:id,waba_id')
            ->orderByDesc('is_default')
            ->orderByDesc('created_at')
            ->get([
                'id', 'waba_subscription_id', 'phone_number_id', 'readiness', 'is_default',
                'pending_default', 'connected_at', 'registered_at', 'last_registration_error',
            ]);

        $latestFailures = WhatsappWebhookEvent::query()
            ->withoutGlobalScopes()
            ->where('account_id', $account->id())
            ->where('classification', WhatsappWebhookEvent::CLASSIFICATION_FAILED)
            ->whereIn('connection_id', $connections->pluck('id'))
            ->orderByDesc('created_at')
            ->get(['connection_id', 'classification', 'created_at'])
            ->unique('connection_id')
            ->keyBy('connection_id');

        $connectionPayload = $connections
            ->map(function (WhatsappPhoneNumberConnection $connection) use ($latestFailures): array {
                $failure = $latestFailures->get($connection->id);
                $lastFailure = $connection->last_registration_error
                    ?? ($failure instanceof WhatsappWebhookEvent
                        ? 'Hay eventos de webhook fallidos pendientes de revisión.'
                        : null);

                return [
                    'id' => $connection->id,
                    'phone_number_id' => $connection->phone_number_id,
                    'waba_id' => $connection->wabaSubscription?->waba_id,
                    'readiness' => $connection->readiness->value,
                    'is_default' => $connection->is_default,
                    'pending_default' => $connection->pending_default,
                    'connected_at' => $connection->connected_at?->toIso8601String(),
                    'registered_at' => $connection->registered_at?->toIso8601String(),
                    'last_registration_error' => $connection->last_registration_error,
                    'last_failure' => $lastFailure,
                    'health' => $this->healthLabel($connection->readiness, $lastFailure),
                ];
            })
            ->values()
            ->all();

        $verifyToken = (string) config('services.meta.webhook_verify_token', '');

        return Inertia::render('settings/whatsapp', [
            'canManage' => $account->isAdmin(),
            'connections' => $connectionPayload,
            'webhookUrl' => route('meta.webhook.verify'),
            'verifyToken' => $verifyToken !== '' ? $verifyToken : null,
            'status' => session('whatsapp_status'),
            'error' => session('whatsapp_error'),
        ]);
    }

    private function healthLabel(WhatsappConnectionReadiness $readiness, ?string $lastFailure): string
    {
        if ($lastFailure !== null || $readiness === WhatsappConnectionReadiness::AttentionRequired) {
            return 'attention';
        }

        return match ($readiness) {
            WhatsappConnectionReadiness::Active => 'healthy',
            WhatsappConnectionReadiness::Disconnected => 'disconnected',
            default => 'pending',
        };
    }
}
