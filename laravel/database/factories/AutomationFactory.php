<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Automation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Automation>
 */
class AutomationFactory extends Factory
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
            'trigger_type' => 'message_received',
            'trigger_config' => [],
            'is_active' => false,
        ];
    }
}
