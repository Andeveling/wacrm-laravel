<?php

declare(strict_types=1);

namespace App\Domain\Meta\Responders;

use App\Domain\Meta\Results\WebhookDeliveryResult;
use App\Domain\Meta\Support\WebhookDeliveryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Maps a {@see WebhookDeliveryResult} to the HTTP contract Meta expects:
 *
 *   200 — delivery persisted.
 *   400 — empty or malformed JSON body after a valid signature.
 *   401 — signature absent / invalid / META_APP_SECRET not configured.
 *   413 — Content-Length above the 1 MB limit.
 *   503 — persistence failed; Meta retries.
 *
 * Transport only: it never re-checks a signature or re-reads the body.
 * The `error.code` is the status enum value, so the wire contract and
 * the domain vocabulary cannot drift apart.
 */
final readonly class WebhookDeliveryResponder
{
    public function __invoke(WebhookDeliveryResult $result): JsonResponse
    {
        return match ($result->status) {
            WebhookDeliveryStatus::Received => response()->json([
                'data' => ['delivery_id' => $result->deliveryId, 'state' => $result->deliveryState],
            ], Response::HTTP_OK),

            WebhookDeliveryStatus::PayloadTooLarge => $this->error(
                $result->status,
                'Body exceeds webhook limit.',
                Response::HTTP_REQUEST_ENTITY_TOO_LARGE,
            ),

            WebhookDeliveryStatus::SignatureInvalid => $this->error(
                $result->status,
                'Signature verification failed.',
                Response::HTTP_UNAUTHORIZED,
            ),

            WebhookDeliveryStatus::InvalidBody => $this->error(
                $result->status,
                'Body must be JSON.',
                Response::HTTP_BAD_REQUEST,
            ),

            WebhookDeliveryStatus::PersistenceFailed => $this->error(
                $result->status,
                'Delivery could not be recorded; retry.',
                Response::HTTP_SERVICE_UNAVAILABLE,
            ),
        };
    }

    private function error(WebhookDeliveryStatus $status, string $message, int $httpStatus): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $status->value, 'message' => $message],
        ], $httpStatus);
    }
}
