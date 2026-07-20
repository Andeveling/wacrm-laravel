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

    /**
     * Indicate that the broadcast is still a draft (default).
     */
    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => BroadcastStatus::Draft]);
    }

    /**
     * Indicate that the broadcast has been sent and the totals reflect that.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastStatus::Sent,
            'total_recipients' => 5,
            'sent_count' => 5,
            'delivered_count' => 4,
            'read_count' => 3,
            'replied_count' => 1,
            'failed_count' => 1,
        ]);
    }

    /**
     * Indicate that the broadcast failed at the gateway.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => ['status' => BroadcastStatus::Failed]);
    }
}
