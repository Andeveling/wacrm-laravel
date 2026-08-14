<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\WhatsappIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappIntegration>
 */
class WhatsappIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'created_by' => null,
            'access_token' => 'test-token-'.fake()->sha256(),
        ];
    }
}
