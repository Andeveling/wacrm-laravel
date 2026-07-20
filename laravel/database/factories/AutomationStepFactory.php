<?php

namespace Database\Factories;

use App\Models\Automation;
use App\Models\AutomationStep;
use App\Models\Enums\AutomationBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationStep>
 */
class AutomationStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'automation_id' => Automation::factory(),
            'parent_step_id' => null,
            'branch' => null,
            'step_type' => 'send_message',
            'step_config' => [],
            'position' => 0,
        ];
    }

    /**
     * Indicate that this step is the YES branch of a parent condition.
     */
    public function yesBranch(): static
    {
        return $this->state(fn (): array => ['branch' => AutomationBranch::Yes]);
    }

    /**
     * Indicate that this step is the NO branch of a parent condition.
     */
    public function noBranch(): static
    {
        return $this->state(fn (): array => ['branch' => AutomationBranch::No]);
    }
}
