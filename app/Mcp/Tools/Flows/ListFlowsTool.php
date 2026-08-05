<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Flows;

use App\Models\Flow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista los flows conversacionales del account.')]
class ListFlowsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(['draft', 'active', 'paused', 'archived'])
                ->description('Filtrar por estado del flow.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $query = Flow::query()->with('nodes')->latest();

        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }

        $flows = $query->get();

        $data = $flows->map(fn (Flow $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'description' => $f->description,
            'status' => $f->status->value,
            'trigger_type' => $f->trigger_type->value,
            'execution_count' => $f->execution_count,
            'last_executed_at' => $f->last_executed_at?->toIso8601String(),
            'nodes_count' => $f->nodes->count(),
            'created_at' => $f->created_at?->toIso8601String(),
        ]);

        return Response::structured([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
