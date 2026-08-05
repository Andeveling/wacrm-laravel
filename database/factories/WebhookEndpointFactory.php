<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
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
            'url' => 'https://'.fake()->domainName().'/webhooks',
            'secret' => fake()->sha256(),
            'events' => '{}',
            'is_active' => true,
        ];
    }
}
