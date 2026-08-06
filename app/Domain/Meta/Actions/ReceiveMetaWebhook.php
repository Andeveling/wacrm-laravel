<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WebhookDeliveryResponder;
use App\Domain\Meta\Results\WebhookDeliveryResult;
use App\Domain\Meta\Support\VerifyMetaWebhookSignature;
use App\Models\WhatsappWebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * POST /api/whatsapp/webhook — HMAC verification plus persistence of the
 * delivery BEFORE replying `200`. A later worker failure is resolved
 * with internal retries and a dead-letter queue, never by asking Meta
 * for a redelivery.
 *
 * The Action never touches `$request->json()` before verifying the
 * signature: that re-encodes the payload and breaks the HMAC. It reads
 * `$request->getContent()` for the raw bytes instead.
 *
 * Every outcome becomes a {@see WebhookDeliveryResult}; the Responder is
 * the only place that knows the status codes.
 */
final readonly class ReceiveMetaWebhook
{
    /**
     * Maximum body size we accept on the webhook. Meta deliveries are
     * small JSON envelopes — images / videos / documents arrive as
     * media_ids the worker fetches later, so 1 MB is generous.
     */
    private const MAX_BODY_BYTES = 1_048_576;

    public function __construct(
        private WebhookDeliveryResponder $responder,
    ) {}

    public function __invoke(Request $request, VerifyMetaWebhookSignature $verifier): JsonResponse
    {
        $signatureHeader = $request->header('X-Hub-Signature-256');
        $contentLengthHeader = $request->header('Content-Length');

        if (is_string($contentLengthHeader) && (int) $contentLengthHeader > self::MAX_BODY_BYTES) {
            return ($this->responder)(WebhookDeliveryResult::payloadTooLarge());
        }

        // Fail closed when the secret isn't configured — even a
        // well-formed signature cannot pass without it. Logged so
        // operators see the misconfiguration in their dashboard.
        if (! VerifyMetaWebhookSignature::isSecretConfigured()) {
            report(new RuntimeException(
                'Meta webhook hit while META_APP_SECRET is not configured; rejecting.',
            ));

            return ($this->responder)(WebhookDeliveryResult::signatureInvalid());
        }

        $rawBody = $request->getContent();

        if (! $verifier->isValid($rawBody, $signatureHeader)) {
            return ($this->responder)(WebhookDeliveryResult::signatureInvalid());
        }

        // After signature validation we can decode safely. An empty body
        // is a 400 — there is nothing to persist as a delivery, since
        // the contract is "raw_payload must be JSON".
        if ($rawBody === '') {
            return ($this->responder)(WebhookDeliveryResult::invalidBody());
        }

        try {
            $decoded = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ($this->responder)(WebhookDeliveryResult::invalidBody());
        }

        try {
            $delivery = WhatsappWebhookDelivery::create([
                'signature_header' => $signatureHeader,
                'raw_body' => $rawBody,
                'raw_payload' => $decoded,
                'content_length' => strlen($rawBody),
                'received_at' => now(),
                'processed_at' => now(),
                'processing_state' => WhatsappWebhookDelivery::STATE_RECEIVED,
            ]);
        } catch (Throwable $e) {
            report($e);

            // We could not persist — Meta should retry. We deliberately
            // do not record a `persistence_failed` row because that
            // would itself require a DB write. The webhook returns
            // 503 so the operator's Meta delivery dashboard flags it.
            return ($this->responder)(WebhookDeliveryResult::persistenceFailed());
        }

        return ($this->responder)(WebhookDeliveryResult::received($delivery));
    }
}
