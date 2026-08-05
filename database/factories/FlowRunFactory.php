<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\FlowRunStatus;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FlowRun>
 */
class FlowRunFactory extends Factory
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
            // El closure ve account_id ya resuelto: el flow pertenece a la
            // misma cuenta que el run (el aislamiento de tenant lo exige).
            'flow_id' => fn (array $attributes) => Flow::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'user_id' => User::factory(),
            'status' => FlowRunStatus::Completed,
            'vars' => [],
            'started_at' => now(),
            'last_advanced_at' => now(),
        ];
    }
}
