<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Meta\Services\WhatsappWebhookDeliveryProcessor;
use App\Models\WhatsappWebhookDelivery;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Reprocess classifiable Webhook Events through the same idempotent
 * processor used by the queue. Already-applied CRM effects are not
 * duplicated. The argument may be an Event id or a Delivery id.
 */
class ReplayWhatsappWebhookEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:replay-events {target : Webhook Event or Delivery id}';

    /**
     * @var string
     */
    protected $description = 'Replay failed, unresolved, blocked or uncorrelated WhatsApp webhook events.';

    public function handle(WhatsappWebhookDeliveryProcessor $processor): int
    {
        $targetId = (string) $this->argument('target');
        $events = $this->eventsFor($targetId);

        if ($events === null) {
            $this->error("Webhook event or delivery [{$targetId}] was not found.");

            return self::FAILURE;
        }

        foreach ($events as $event) {
            if (! in_array($event->classification, WhatsappWebhookEvent::classifiableOutcomes(), true)) {
                $this->info("Webhook event [{$event->id}] is {$event->classification}; nothing to replay.");

                continue;
            }

            $processor->replay($event);
            $event->refresh();
            $this->info("Replayed {$event->id} as {$event->classification}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, WhatsappWebhookEvent>|null
     */
    private function eventsFor(string $targetId): ?Collection
    {
        $event = WhatsappWebhookEvent::query()->whereKey($targetId)->first();

        if ($event !== null) {
            return collect([$event]);
        }

        $delivery = WhatsappWebhookDelivery::query()->whereKey($targetId)->first();

        if ($delivery === null) {
            return null;
        }

        return $delivery->events()->get();
    }
}
