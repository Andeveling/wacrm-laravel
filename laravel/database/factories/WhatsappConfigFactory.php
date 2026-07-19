<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\WhatsappConfigStatus;
use App\Models\User;
use App\Models\WhatsappConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappConfig>
 */
class WhatsappConfigFactory extends Factory
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
            'phone_number_id' => fake()->unique()->numerify('1#############'),
            'waba_id' => fake()->numerify('1#############'),
            'access_token' => 'test-token-'.fake()->sha256(),
            'verify_token' => fake()->uuid(),
            'status' => WhatsappConfigStatus::Disconnected,
        ];
    }

    /**
     * Config con el número ya conectado y registrado en Meta.
     */
    public function connected(): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappConfigStatus::Connected,
            'connected_at' => now(),
            'registered_at' => now(),
            'subscribed_apps_at' => now(),
        ]);
    }
}
