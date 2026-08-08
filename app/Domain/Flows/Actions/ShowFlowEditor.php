<?php

declare(strict_types=1);

namespace App\Domain\Flows\Actions;

use App\Models\Flow;
use App\Models\FlowNode;
use Inertia\Inertia;
use Inertia\Response;

final class ShowFlowEditor
{
    public function __invoke(string $id): Response
    {
        $flow = Flow::query()
            ->with(['nodes' => fn ($query) => $query->orderBy('position_x')->orderBy('position_y')])
            ->findOrFail($id);

        return Inertia::render('flows/editor', [
            'flow' => [
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
                'nodes' => $flow->nodes->map(fn (FlowNode $node): array => [
                    'id' => $node->id,
                    'node_key' => $node->node_key,
                    'node_type' => $node->node_type->value,
                    'config' => $node->config,
                    'position_x' => $node->position_x,
                    'position_y' => $node->position_y,
                ])->values()->all(),
            ],
        ]);
    }
}
