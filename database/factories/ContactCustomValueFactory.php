<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\ContactCustomValue;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactCustomValue>
 */
class ContactCustomValueFactory extends Factory
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
            'custom_field_id' => CustomField::factory(),
            'value' => fake()->word(),
        ];
    }
}
