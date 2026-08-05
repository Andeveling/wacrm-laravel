<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\QuickReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuickReply>
 */
class QuickReplyFactory extends Factory
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
            'title' => fake()->words(3, true),
            'kind' => 'text',
            'content_text' => fake()->sentence(),
        ];
    }
}
