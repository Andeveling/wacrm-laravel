<?php

declare(strict_types=1);

namespace App\Domain\Meta\Results;

use App\Domain\Meta\Support\WebhookDeliveryStatus;
use App\Models\WhatsappWebhookDelivery;

/**
 * Immutable outcome of ingesting one Meta webhook delivery. Only the
 * success case carries persisted identifiers; every rejection is the
 * status alone, because nothing was written.
 *
 * The row is flattened to scalars rather than held as an Eloquent model
 * so the Responder cannot lazily reach back into the database while
 * shaping the reply.
 */
final readonly class WebhookDeliveryResult
{
    public function __construct(
        public WebhookDeliveryStatus $status,
        public ?string $deliveryId = null,
        public ?string $deliveryState = null,
    ) {}

    public static function received(WhatsappWebhookDelivery $delivery): self
    {
        return new self(
            WebhookDeliveryStatus::Received,
            (string) $delivery->id,
            $delivery->processing_state,
        );
    }

    public static function payloadTooLarge(): self
    {
        return new self(WebhookDeliveryStatus::PayloadTooLarge);
    }

    public static function signatureInvalid(): self
    {
        return new self(WebhookDeliveryStatus::SignatureInvalid);
    }

    public static function invalidBody(): self
    {
        return new self(WebhookDeliveryStatus::InvalidBody);
    }

    public static function persistenceFailed(): self
    {
        return new self(WebhookDeliveryStatus::PersistenceFailed);
    }
}
