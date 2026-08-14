<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Enums\ConversationStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
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
            // Contacto propio por conversación; the connection is nullable for
            // legacy rows awaiting explicit assignment.
            'contact_id' => Contact::factory(),
            'status' => ConversationStatus::Open,
        ];
    }

    /**
     * Indicate that the conversation is in the `open` state (default).
     */
    public function open(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Open]);
    }

    /**
     * Indicate that the conversation is waiting on something (no agent action yet).
     */
    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Pending]);
    }

    /**
     * Indicate that the conversation has been closed out.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => ConversationStatus::Closed]);
    }
}
