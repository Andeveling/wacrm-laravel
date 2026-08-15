<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Meta\Services\WhatsappWebhookDeliveryProcessor;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Console\Command;

/**
 * Reprocess a classifiable Webhook Event through the same idempotent
 * processor used by the queue. Already-applied CRM effects are not
 * duplicated.
 */
class ReplayWhatsappWebhookEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:replay-events {event : Webhook Event id}';

    /**
     * @var string
     */
    protected $description = 'Replay a failed, unresolved, blocked or uncorrelated WhatsApp webhook event.';

    public function handle(WhatsappWebhookDeliveryProcessor $processor): int
    {
        $eventId = (string) $this->argument('event');
        $event = WhatsappWebhookEvent::query()->whereKey($eventId)->first();

        if ($event === null) {
            $this->error("Webhook event [{$eventId}] was not found.");

            return self::FAILURE;
        }

        if (! in_array($event->classification, WhatsappWebhookEvent::classifiableOutcomes(), true)) {
            $this->info("Webhook event [{$eventId}] is {$event->classification}; nothing to replay.");

            return self::SUCCESS;
        }

        $processor->replay($event);

        $event->refresh();
        $this->info("Replayed {$event->id} as {$event->classification}.");

        return self::SUCCESS;
    }
}
