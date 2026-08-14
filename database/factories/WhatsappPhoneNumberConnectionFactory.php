<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Enums\WhatsappConnectionReadiness;
use App\Models\WabaSubscription;
use App\Models\WhatsappPhoneNumberConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappPhoneNumberConnection>
 */
class WhatsappPhoneNumberConnectionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            // Nullable here also models a legacy row waiting for its WABA
            // ownership conflict to be remediated.
            'waba_subscription_id' => null,
            'phone_number_id' => fake()->unique()->numerify('1#############'),
            'readiness' => WhatsappConnectionReadiness::CredentialsVerified,
            'is_default' => false,
        ];
    }

    public function forWaba(WabaSubscription $waba): static
    {
        return $this->state(fn (): array => [
            'account_id' => $waba->account_id,
            'waba_subscription_id' => $waba->id,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'readiness' => WhatsappConnectionReadiness::Active,
        ]);
    }
}
