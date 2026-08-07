<?php

declare(strict_types=1);

namespace App\Domain\Pipelines\Actions;

use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Inertia\Inertia;
use Inertia\Response;

final class ShowPipelines
{
    public function __invoke(): Response
    {
        $pipelines = Pipeline::query()
            ->with([
                'stages.deals' => fn ($query) => $query
                    ->with(['contact:id,name,phone', 'assignee:id,name'])
                    ->latest('updated_at'),
            ])
            ->latest('created_at')
            ->get(['id', 'name', 'created_at'])
            ->map(fn (Pipeline $pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'created_at' => $pipeline->created_at?->toISOString(),
                'stages' => $pipeline->stages->map(fn (PipelineStage $stage): array => [
                    'id' => $stage->id,
                    'pipeline_id' => $stage->pipeline_id,
                    'name' => $stage->name,
                    'position' => $stage->position,
                    'color' => $stage->color,
                    'deals' => $stage->deals->map(fn (Deal $deal): array => [
                        'id' => $deal->id,
                        'pipeline_id' => $deal->pipeline_id,
                        'stage_id' => $deal->stage_id,
                        'contact_id' => $deal->contact_id,
                        'title' => $deal->title,
                        'value' => $deal->value,
                        'currency' => $deal->currency,
                        'notes' => $deal->notes,
                        'expected_close_date' => $deal->expected_close_date?->toDateString(),
                        'status' => $deal->status?->value,
                        'created_at' => $deal->created_at?->toISOString(),
                        'updated_at' => $deal->updated_at?->toISOString(),
                        'contact' => $deal->contact?->only(['id', 'name', 'phone']),
                        'assignee' => $deal->assignee?->only(['id', 'name']),
                    ])->values()->all(),
                ])->values()->all(),
            ])->values()->all();

        return Inertia::render('pipelines', [
            'pipelines' => $pipelines,
            'contacts' => Contact::query()
                ->orderBy('name')
                ->orderBy('phone')
                ->get(['id', 'name', 'phone'])
                ->map(fn (Contact $contact): array => $contact->only(['id', 'name', 'phone']))
                ->values()
                ->all(),
        ]);
    }
}
