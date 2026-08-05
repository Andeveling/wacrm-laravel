<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\ApiKey;
use App\Models\ApiKeyRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiKeyRequest>
 */
class ApiKeyRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_key_id' => ApiKey::factory(),
            'account_id' => Account::factory(),
            'method' => 'GET',
            'path' => '/api/v1/me',
            'status' => 200,
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'request_id' => fake()->uuid(),
            'duration_ms' => fake()->numberBetween(5, 200),
            'scope_used' => null,
            'created_at' => now(),
        ];
    }
}
