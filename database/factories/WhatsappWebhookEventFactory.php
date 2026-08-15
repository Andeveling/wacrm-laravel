<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappWebhookEvent>
 */
class WhatsappWebhookEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => WhatsappWebhookDelivery::factory(),
            'fingerprint' => 'message:'.fake()->unique()->uuid(),
            'classification' => WhatsappWebhookEvent::CLASSIFICATION_PROCESSED,
            'payload' => ['type' => 'text'],
        ];
    }
}
