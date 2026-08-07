<?php

declare(strict_types=1);

namespace App\Domain\Automations\Actions;

use App\Models\Automation;
use App\Models\AutomationLog;
use Inertia\Inertia;
use Inertia\Response;

final class ShowAutomationLogs
{
    public function __invoke(Automation $automation): Response
    {
        $automation->load([
            'logs' => fn ($query) => $query->with('contact:id,name,phone')->latest('created_at'),
        ]);

        return Inertia::render('automations/logs', [
            'automation' => [
                'id' => $automation->id,
                'name' => $automation->name,
            ],
            'logs' => $automation->logs
                ->map(fn (AutomationLog $log): array => [
                    'id' => $log->id,
                    'automation_id' => $log->automation_id,
                    'contact_id' => $log->contact_id,
                    'contact' => $log->contact?->only(['id', 'name', 'phone']),
                    'trigger_event' => $log->trigger_event,
                    'steps_executed' => $log->steps_executed,
                    'status' => $log->status->value,
                    'error_message' => $log->error_message,
                    'created_at' => $log->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }
}
