<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Models\Broadcast;
use Inertia\Inertia;
use Inertia\Response;

final class ShowBroadcasts
{
    public function __invoke(): Response
    {
        $broadcasts = Broadcast::query()
            ->latest('created_at')
            ->get([
                'id',
                'name',
                'template_name',
                'template_language',
                'scheduled_at',
                'status',
                'total_recipients',
                'sent_count',
                'delivered_count',
                'read_count',
                'replied_count',
                'failed_count',
                'created_at',
            ])
            ->map(fn (Broadcast $broadcast): array => [
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
            ])
            ->values()
            ->all();

        return Inertia::render('broadcasts', ['broadcasts' => $broadcasts]);
    }
}
