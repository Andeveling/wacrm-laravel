<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\WhatsappWebhookEvent;
use Illuminate\Console\Command;

/**
 * Installation-level inspection of classifiable webhook work. Processed
 * and unsupported events are omitted: they are not pending operator
 * action.
 */
class ListWhatsappWebhookEvents extends Command
{
    /**
     * @var string
     */
    protected $signature = 'whatsapp:list-events';

    /**
     * @var string
     */
    protected $description = 'List failed, unresolved, blocked and uncorrelated WhatsApp webhook events.';

    public function handle(): int
    {
        $rows = WhatsappWebhookEvent::query()
            ->classifiable()
            ->latest('created_at')
            ->get();

        $this->table(
            ['ID', 'Classification', 'Fingerprint', 'Phone number', 'Delivery'],
            $rows->map(fn (WhatsappWebhookEvent $event): array => [
                $event->id,
                $event->classification,
                $event->fingerprint,
                $event->phone_number_id ?? '',
                $event->delivery_id,
            ])->all(),
        );

        return self::SUCCESS;
    }
}
