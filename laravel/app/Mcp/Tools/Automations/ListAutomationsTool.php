<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Automations;

use App\Models\Automation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista las automatizaciones del account con sus métricas de ejecución.')]
class ListAutomationsTool extends Tool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'isActive' => $schema->boolean()
                ->description('Filtrar solo activas/inactivas.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $query = Automation::query()->with('steps')->latest();

        if ($request->has('isActive')) {
            $query->where('is_active', $request->boolean('isActive'));
        }

        $automations = $query->get();

        $data = $automations->map(fn (Automation $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'description' => $a->description,
            'trigger_type' => $a->trigger_type,
            'is_active' => $a->is_active,
            'execution_count' => $a->execution_count,
            'last_executed_at' => $a->last_executed_at?->toIso8601String(),
            'steps_count' => $a->steps->count(),
            'created_at' => $a->created_at?->toIso8601String(),
        ]);

        return Response::structured([
            'data' => $data,
            'total' => $data->count(),
        ]);
    }
}
