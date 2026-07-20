<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactTag;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactTag>
 */
class ContactTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'tag_id' => Tag::factory(),
        ];
    }
}
