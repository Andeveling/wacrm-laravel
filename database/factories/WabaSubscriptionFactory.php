<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\WabaSubscription;
use App\Models\WhatsappIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WabaSubscription>
 */
class WabaSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'integration_id' => null,
            'waba_id' => fake()->unique()->numerify('1#############'),
            'subscribed_apps_at' => now(),
        ];
    }

    public function forIntegration(WhatsappIntegration $integration): static
    {
        return $this->state(fn (): array => [
            'account_id' => $integration->account_id,
            'integration_id' => $integration->id,
        ]);
    }
}
