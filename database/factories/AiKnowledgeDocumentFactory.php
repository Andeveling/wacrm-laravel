<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeDocument>
 */
class AiKnowledgeDocumentFactory extends Factory
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
            'title' => fake()->sentence(4),
            'content' => fake()->paragraphs(2, true),
        ];
    }
}
