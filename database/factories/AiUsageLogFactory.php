<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AiUsageLog;
use App\Models\Enums\AiProvider;
use App\Models\Enums\AiUsageMode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsageLog>
 */
class AiUsageLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prompt = fake()->numberBetween(100, 2000);
        $completion = fake()->numberBetween(20, 500);

        return [
            'account_id' => Account::factory(),
            'mode' => AiUsageMode::Draft,
            'provider' => AiProvider::OpenAi,
            'model' => 'gpt-4o-mini',
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'total_tokens' => $prompt + $completion,
        ];
    }
}
