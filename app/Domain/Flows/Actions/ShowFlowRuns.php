<?php

declare(strict_types=1);

namespace App\Domain\Flows\Actions;

use App\Models\Flow;
use App\Models\FlowRun;
use Inertia\Inertia;
use Inertia\Response;

final class ShowFlowRuns
{
    public function __invoke(string $id): Response
    {
        $flow = Flow::query()
            ->with(['runs' => fn ($query) => $query
                ->with([
                    'contact:id,name,phone',
                    'events' => fn ($query) => $query->oldest('created_at'),
                ])
                ->latest('started_at')])
            ->findOrFail($id);

        $runs = $flow->runs->map(fn (FlowRun $run): array => [
            'id' => $run->id,
            'status' => $run->status->value,
            'current_node_key' => $run->current_node_key,
            'started_at' => $run->started_at->toISOString(),
            'ended_at' => $run->ended_at?->toISOString(),
            'reprompt_count' => $run->reprompt_count,
            'vars' => $run->vars,
            'contact' => $run->contact?->only(['id', 'name', 'phone']),
        ])->values()->all();

        $events = [];

        foreach ($flow->runs as $run) {
            foreach ($run->events as $event) {
                $events[] = [
                    'flow_run_id' => $event->flow_run_id,
                    'event_type' => $event->event_type->value,
                    'node_key' => $event->node_key,
                    'created_at' => $event->created_at?->toISOString(),
                ];
            }
        }

        return Inertia::render('flows/runs', [
            'flow' => ['id' => $flow->id, 'name' => $flow->name],
            'runs' => $runs,
            'events' => $events,
        ]);
    }
}
