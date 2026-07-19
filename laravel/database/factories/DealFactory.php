<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Deal;
use App\Models\Enums\DealStatus;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Deal>
 */
class DealFactory extends Factory
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
            'user_id' => User::factory(),
            'pipeline_id' => Pipeline::factory(),
            // El closure ve pipeline_id ya resuelto: el stage pertenece al
            // mismo pipeline que el deal (la DB no lo exige, pero los datos
            // deben tener sentido).
            'stage_id' => fn (array $attributes): string => PipelineStage::factory()
                ->create(['pipeline_id' => $attributes['pipeline_id']])
                ->id,
            'title' => fake()->sentence(3),
            'value' => fake()->randomFloat(2, 0, 5000),
            'currency' => 'USD',
            'status' => DealStatus::Open,
        ];
    }
}
