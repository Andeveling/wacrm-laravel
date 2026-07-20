<?php

namespace Database\Factories;

use App\Models\Broadcast;
use App\Models\BroadcastRecipient;
use App\Models\Contact;
use App\Models\Enums\BroadcastRecipientStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BroadcastRecipient>
 */
class BroadcastRecipientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'broadcast_id' => Broadcast::factory(),
            'contact_id' => Contact::factory(),
            'status' => BroadcastRecipientStatus::Pending,
        ];
    }

    /**
     * Indicate that the recipient is at the `sent` step.
     */
    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastRecipientStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the recipient is at the `delivered` step.
     */
    public function delivered(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastRecipientStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Indicate that the recipient is at the `read` step.
     */
    public function read(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastRecipientStatus::Read,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => now(),
        ]);
    }

    /**
     * Indicate that the recipient is at the `replied` step.
     */
    public function replied(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastRecipientStatus::Replied,
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => now(),
            'replied_at' => now(),
        ]);
    }

    /**
     * Indicate that the recipient failed delivery.
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => BroadcastRecipientStatus::Failed,
            'error_message' => 'Recipient phone unreachable.',
        ]);
    }
}
