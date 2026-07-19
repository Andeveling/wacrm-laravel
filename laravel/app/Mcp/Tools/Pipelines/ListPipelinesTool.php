<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Pipelines;

use App\Models\Pipeline;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Lista los pipelines del account con sus stages.')]
class ListPipelinesTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $pipelines = Pipeline::query()
            ->with('stages:id,pipeline_id,name,position')
            ->get();

        /** @phpstan-ignore-next-line Collection template covariance with nested map */
        $data = $pipelines->map(fn (Pipeline $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'stages' => $p->stages->map(static fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'position' => $s->position,
            ]),
            'created_at' => $p->created_at?->toIso8601String(),
        ]);

        return Response::structured(['data' => $data, 'total' => $data->count()]);
    }
}
