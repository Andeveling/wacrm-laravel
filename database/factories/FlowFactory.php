<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\FlowStatus;
use App\Models\Enums\FlowTriggerType;
use App\Models\Flow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flow>
 */
class FlowFactory extends Factory
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
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'status' => FlowStatus::Draft,
            'trigger_type' => FlowTriggerType::Keyword,
            'trigger_config' => [],
            'fallback_policy' => [
                'on_unknown_reply' => 'reprompt',
                'max_reprompts' => 2,
                'on_timeout_hours' => 24,
                'on_exhaust' => 'handoff',
            ],
        ];
    }

    /**
     * Indicate that the flow is currently active and processing inbound events.
     */
    public function active(): static
    {
        return $this->state(fn (): array => ['status' => FlowStatus::Active]);
    }

    /**
     * Indicate that the flow has been archived and is no longer discoverable.
     */
    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => FlowStatus::Archived]);
    }
}
