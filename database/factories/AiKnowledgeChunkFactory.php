<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiKnowledgeChunk>
 */
class AiKnowledgeChunkFactory extends Factory
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
            // account_id denormalizado: el chunk comparte cuenta con su
            // documento, como lo escribe el chunker del módulo IA.
            'document_id' => fn (array $attributes) => AiKnowledgeDocument::factory()
                ->create(['account_id' => $attributes['account_id']])
                ->id,
            'chunk_index' => 0,
            'content' => fake()->paragraph(),
        ];
    }
}
