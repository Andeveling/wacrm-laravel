<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'type' => AccountType::Team,
        ];
    }

    /**
     * Indicate that the account is a user's Personal account.
     */
    public function personal(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Personal',
            'type' => AccountType::Personal,
        ]);
    }
}
