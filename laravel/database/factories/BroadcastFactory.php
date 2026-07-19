<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Broadcast;
use App\Models\Enums\BroadcastStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Broadcast>
 */
class BroadcastFactory extends Factory
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
            'template_name' => fake()->slug(2),
            'template_language' => 'es',
            'status' => BroadcastStatus::Draft,
        ];
    }
}
