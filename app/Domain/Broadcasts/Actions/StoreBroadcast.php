<?php

declare(strict_types=1);

namespace App\Domain\Broadcasts\Actions;

use App\Domain\Broadcasts\Services\StoreBroadcastService;
use App\Http\Requests\Broadcasts\StoreBroadcastRequest;
use Illuminate\Http\RedirectResponse;

final readonly class StoreBroadcast
{
    public function __invoke(StoreBroadcastRequest $request, StoreBroadcastService $service): RedirectResponse
    {
        /** @var array{name: string, template_id: string, connection_id: string, audience_type: 'all'|'tags', tag_ids?: list<string>, template_variables: array<string, string>, scheduled_at?: string|null} $data */
        $data = $request->validated();

        $broadcast = $service->store($data, $request->user()->id);

        return to_route('broadcasts.show', $broadcast);
    }
}
