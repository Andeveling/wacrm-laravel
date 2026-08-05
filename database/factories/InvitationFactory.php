<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
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
            'token_hash' => hash('sha256', Str::random(48)),
            'role' => 'member',
            'invited_by' => User::factory(),
            'label' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * Indicate that the invitation has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the invitation has already been accepted.
     */
    public function accepted(?User $by = null): static
    {
        return $this->state(fn (array $attributes) => [
            'accepted_at' => now(),
            'accepted_by' => $by !== null ? $by->id : User::factory(),
        ]);
    }

    /**
     * Indicate that the invitation has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
