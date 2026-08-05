<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CustomField;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
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
            'field_name' => fake()->unique()->word(),
            'field_type' => 'text',
        ];
    }
}
