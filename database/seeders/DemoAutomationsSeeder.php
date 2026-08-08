<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Automatizaciones demo: una inactiva y una activa con su secuencia de
 * pasos, incluida una condición con ramas.
 */
class DemoAutomationsSeeder extends Seeder
{
    public function run(Account $team, User $owner): void
    {
        Automation::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Encuesta post-compra'],
            [
                'user_id' => $owner->id,
                'trigger_type' => 'message_received',
                'is_active' => false,
            ],
        );

        $active = Automation::firstOrCreate(
            ['account_id' => $team->id, 'name' => 'Bienvenida primer mensaje'],
            [
                'user_id' => $owner->id,
                'trigger_type' => 'message_received',
                'is_active' => true,
            ],
        );

        if (AutomationStep::where('automation_id', $active->id)->exists()) {
            return;
        }

        foreach ($this->stepDefinitions() as $position => $step) {
            AutomationStep::create([
                'automation_id' => $active->id,
                'parent_step_id' => null,
                'branch' => $step['branch'],
                'step_type' => $step['step_type'],
                'step_config' => $step['step_config'],
                'position' => $position,
            ]);
        }
    }

    /**
     * @return list<array{step_type: string, step_config: array<string, mixed>, branch: string|null}>
     */
    private function stepDefinitions(): array
    {
        return [
            [
                'step_type' => 'send_message',
                'step_config' => ['text' => '¡Hola! Bienvenido a WACRM, ¿en qué te ayudamos?'],
                'branch' => null,
            ],
            [
                'step_type' => 'collect_input',
                'step_config' => ['variable' => 'motivo', 'timeout_seconds' => 300],
                'branch' => null,
            ],
            [
                'step_type' => 'condition',
                'step_config' => ['expression' => '{{motivo}} == "compra"'],
                'branch' => null,
            ],
            [
                'step_type' => 'set_tag',
                'step_config' => ['tag' => 'Prospecto'],
                'branch' => 'yes',
            ],
            [
                'step_type' => 'handoff',
                'step_config' => ['queue' => 'sales'],
                'branch' => 'no',
            ],
        ];
    }
}
