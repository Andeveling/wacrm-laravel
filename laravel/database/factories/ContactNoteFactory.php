<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactNote>
 */
class ContactNoteFactory extends Factory
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
            'contact_id' => Contact::factory(),
            'user_id' => User::factory(),
            'note_text' => fake()->sentence(),
        ];
    }
}
