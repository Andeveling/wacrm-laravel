<?php

declare(strict_types=1);

namespace App\Domain\Meta\Support;

/**
 * Outcomes of a Meta webhook POST. The values double as the `error.code`
 * the Responder emits, so the wire contract and the domain vocabulary
 * cannot drift apart. HTTP status codes deliberately live in the
 * Responder, not here.
 */
enum WebhookDeliveryStatus: string
{
    case Received = 'received';
    case PayloadTooLarge = 'payload_too_large';
    case SignatureInvalid = 'signature_invalid';
    case InvalidBody = 'invalid_body';
    case PersistenceFailed = 'persistence_failed';
}
