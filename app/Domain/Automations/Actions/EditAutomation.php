<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Models\Automation;
use App\Models\AutomationStep;
use Inertia\Inertia;
use Inertia\Response;

final class EditAutomation
{
    public function __invoke(Automation $automation): Response
    {
        $automation->load('steps');

        return Inertia::render('automations/edit', [
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
                'description' => $automation->description,
                'trigger_type' => $automation->trigger_type,
                'connection_mode' => $automation->connection_mode->value,
                'connection_id' => $automation->connection_id,
                'is_active' => $automation->is_active,
                'execution_count' => $automation->execution_count,
                'last_executed_at' => $automation->last_executed_at?->toISOString(),
                'created_at' => $automation->created_at?->toISOString(),
                'updated_at' => $automation->updated_at?->toISOString(),
                'steps' => $automation->steps
                    ->sortBy('position')
                    ->map(fn (AutomationStep $step): array => [
                        'id' => $step->id,
                        'step_type' => $step->step_type,
                        'step_config' => $step->step_config,
                        'position' => $step->position,
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
