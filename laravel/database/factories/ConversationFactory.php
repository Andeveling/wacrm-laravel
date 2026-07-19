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
            // Contacto propio por conversación: hay UNIQUE (account_id, contact_id).
            'contact_id' => Contact::factory(),
            'status' => ConversationStatus::Open,
        ];
    }
}
