<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Meta\Services\WhatsappWebhookDeliveryProcessor;
use App\Models\WhatsappWebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Asynchronous handoff for the global Meta webhook inbox.
 *
 * Event extraction and tenant routing run after Meta's acknowledgement.
 * The job only carries the delivery id so the raw payload never enters
 * queue metadata.
 */
final class ProcessWhatsappWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $deliveryId) {}

    public function handle(WhatsappWebhookDeliveryProcessor $processor): void
    {
        $delivery = WhatsappWebhookDelivery::query()->whereKey($this->deliveryId)->first();

        if ($delivery === null) {
            return;
        }

        $processor->process($delivery);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception !== null) {
            report($exception);
        }

        logger()->error('Meta webhook delivery processing failed.', [
            'delivery_id' => $this->deliveryId,
        ]);
    }
}
