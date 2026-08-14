<?php

declare(strict_types=1);

namespace App\Jobs;

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
 * Event extraction and tenant routing belong to the processing seam that
 * consumes this job. For now the job records that the durable delivery has
 * entered the queue without exposing its raw payload to queue metadata.
 */
final class ProcessWhatsappWebhookDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $deliveryId) {}

    public function handle(): void
    {
        WhatsappWebhookDelivery::query()
            ->whereKey($this->deliveryId)
            ->where('processing_state', WhatsappWebhookDelivery::STATE_RECEIVED)
            ->update(['processing_state' => WhatsappWebhookDelivery::STATE_QUEUED]);
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
