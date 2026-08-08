<?php

declare(strict_types=1);

namespace App\Domain\Flows\Actions;

use App\Models\Flow;
use Inertia\Inertia;
use Inertia\Response;

final class ShowFlows
{
    public function __invoke(): Response
    {
        $flows = Flow::query()
            ->withCount('nodes')
            ->latest('created_at')
            ->get()
            ->map(fn (Flow $flow): array => [
                'id' => $flow->id,
                'name' => $flow->name,
                'description' => $flow->description,
                'status' => $flow->status->value,
                'trigger_type' => $flow->trigger_type->value,
                'trigger_config' => $flow->trigger_config,
                'execution_count' => $flow->execution_count,
                'last_executed_at' => $flow->last_executed_at?->toISOString(),
                'created_at' => $flow->created_at?->toISOString(),
                'updated_at' => $flow->updated_at?->toISOString(),
                'nodes_count' => $flow->nodes_count,
            ])
            ->values()
            ->all();

        return Inertia::render('flows', ['flows' => $flows]);
    }
}
