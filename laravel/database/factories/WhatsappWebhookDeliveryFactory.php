<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WhatsappWebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappWebhookDelivery>
 */
class WhatsappWebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'signature_header' => 'sha256='.fake()->sha256(),
            'raw_payload' => ['object' => 'whatsapp_business_account', 'entry' => []],
            'content_length' => fake()->numberBetween(64, 4096),
            'received_at' => now(),
            'processed_at' => now(),
            'processing_state' => WhatsappWebhookDelivery::STATE_RECEIVED,
        ];
    }
}
