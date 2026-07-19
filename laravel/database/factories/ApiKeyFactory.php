<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\ApiKeyToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKey>
 */
class ApiKeyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issued = ApiKeyToken::issue('live');

        return [
            'account_id' => Account::factory(),
            'created_by' => User::factory(),
            'name' => fake()->word().' '.fake()->word().' integration',
            'key_prefix' => $issued['key_prefix'],
            'key_hash' => $issued['key_hash'],
            'scopes' => [],
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the key has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * Indicate that the key has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Indicate that the key grants a particular scope.
     */
    public function withScopes(string ...$scopes): static
    {
        return $this->state(fn (): array => [
            'scopes' => array_values($scopes),
        ]);
    }
}
