<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\PresenceStatus;
use App\Models\MemberPresence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberPresence>
 */
class MemberPresenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'status' => PresenceStatus::Online,
            'last_seen_at' => now(),
        ];
    }
}
