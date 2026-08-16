<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Models\WhatsappLegacyMigrationIssue;
use App\Models\WhatsappPhoneNumberConnection;
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
                'connected_at', 'registered_at', 'last_registration_error',
            ])
            ->map(fn (WhatsappPhoneNumberConnection $connection): array => [
                'id' => $connection->id,
                'phone_number_id' => $connection->phone_number_id,
                'waba_id' => $connection->wabaSubscription?->waba_id,
                'readiness' => $connection->readiness->value,
                'is_default' => $connection->is_default,
                'connected_at' => $connection->connected_at?->toIso8601String(),
                'registered_at' => $connection->registered_at?->toIso8601String(),
                'last_registration_error' => $connection->last_registration_error,
            ])
            ->values()
            ->all();

        $legacyIssues = WhatsappLegacyMigrationIssue::query()
            ->with('conversation.contact:id,name,phone')
            ->whereNull('resolved_at')
            ->latest()
            ->get([
                'id', 'conversation_id', 'kind', 'details', 'created_at',
            ])
            ->map(function (WhatsappLegacyMigrationIssue $issue): array {
                $conversation = $issue->conversation;
                $contact = $conversation === null ? null : $conversation->contact;

                return [
                    'id' => $issue->id,
                    'kind' => $issue->kind->value,
                    'conversation_id' => $issue->conversation_id,
                    'contact_name' => $contact === null
                        ? null
                        : ($contact->name ?? $contact->phone),
                    'action' => is_string($issue->details['action'] ?? null)
                        ? $issue->details['action']
                        : null,
                    'candidate_connections' => is_int($issue->details['candidate_connections'] ?? null)
                        ? $issue->details['candidate_connections']
                        : null,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('settings/whatsapp', [
            'canManage' => $account->isAdmin(),
            'connections' => $connections,
            'legacyIssues' => $legacyIssues,
            'webhookUrl' => route('meta.webhook.verify'),
            'status' => session('whatsapp_status'),
            'error' => session('whatsapp_error'),
        ]);
    }
}
