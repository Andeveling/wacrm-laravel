<?php

namespace Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantScopedFixture>
 */
class TenantScopedFixtureFactory extends Factory
{
    protected $model = TenantScopedFixture::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
