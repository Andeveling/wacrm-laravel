<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\MessageTemplateStatus;
use App\Models\MessageTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageTemplate>
 */
class MessageTemplateFactory extends Factory
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
            // Único por (user_id, name, language) en DB.
            'name' => fake()->unique()->slug(2),
            'category' => 'Marketing',
            'language' => 'en_US',
            'body_text' => fake()->sentence(),
            'status' => MessageTemplateStatus::Draft,
        ];
    }
}
