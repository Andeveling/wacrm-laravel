<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use Inertia\Inertia;
use Inertia\Response;

final class ShowBroadcast
{
    public function __invoke(string $id): Response
    {
        $broadcast = Broadcast::query()
            ->with([
                'recipients' => fn ($query) => $query
                    ->with('contact:id,name,phone')
                    ->oldest('created_at'),
            ])
            ->findOrFail($id);

        return Inertia::render('broadcasts/show', [
            'broadcast' => [
                'id' => $broadcast->id,
                'name' => $broadcast->name,
                'template_name' => $broadcast->template_name,
                'template_language' => $broadcast->template_language,
                'scheduled_at' => $broadcast->scheduled_at?->toISOString(),
                'status' => $broadcast->status?->value,
                'total_recipients' => $broadcast->total_recipients ?? 0,
                'sent_count' => $broadcast->sent_count ?? 0,
                'delivered_count' => $broadcast->delivered_count ?? 0,
                'read_count' => $broadcast->read_count ?? 0,
                'replied_count' => $broadcast->replied_count ?? 0,
                'failed_count' => $broadcast->failed_count ?? 0,
                'created_at' => $broadcast->created_at?->toISOString(),
            ],
            'recipients' => $broadcast->recipients
                ->map(fn (BroadcastRecipient $recipient): array => [
                    'id' => $recipient->id,
                    'broadcast_id' => $recipient->broadcast_id,
                    'contact_id' => $recipient->contact_id,
                    'contact' => $recipient->contact?->only(['id', 'name', 'phone']),
                    'status' => $recipient->status?->value,
                    'sent_at' => $recipient->sent_at?->toISOString(),
                    'delivered_at' => $recipient->delivered_at?->toISOString(),
                    'read_at' => $recipient->read_at?->toISOString(),
                    'replied_at' => $recipient->replied_at?->toISOString(),
                    'error_message' => $recipient->error_message,
                ])
                ->values()
                ->all(),
        ]);
    }
}
