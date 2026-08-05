<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Automation;
use App\Models\AutomationPendingExecution;
use App\Models\Enums\PendingExecutionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationPendingExecution>
 */
class AutomationPendingExecutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'automation_id' => Automation::factory(),
            'user_id' => User::factory(),
            'next_step_position' => 0,
            'context' => [],
            'status' => PendingExecutionStatus::Pending,
            'run_at' => now()->addHour(),
        ];
    }
}
