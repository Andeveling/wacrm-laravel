<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
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
            'type' => 'conversation_assigned',
            'title' => 'New conversation assigned',
            'body' => fake()->sentence(),
        ];
    }
}
