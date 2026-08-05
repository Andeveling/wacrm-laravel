<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AiConfig;
use App\Models\Enums\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiConfig>
 */
class AiConfigFactory extends Factory
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
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o-mini',
            'api_key' => 'sk-test-'.fake()->sha256(),
            'is_active' => false,
            'auto_reply_enabled' => false,
        ];
    }
}
