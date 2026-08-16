<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use App\Domain\Meta\Responders\WebhookDeliveryResponder;
use App\Domain\Meta\Results\WebhookDeliveryResult;
use App\Domain\Meta\Support\VerifyMetaWebhookSignature;
use App\Jobs\ProcessWhatsappWebhookDelivery;
use App\Models\WhatsappWebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * POST /api/whatsapp/webhook — HMAC verification plus persistence and
 * after-commit queue handoff BEFORE replying `200`. Later processing is
 * deliberately decoupled from Meta's acknowledgement path.
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
     * media_ids the worker fetches later. Meta documents a 3 MB limit.
     */
    private const MAX_BODY_BYTES = 3_145_728;

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

        // Content-Length is only an early rejection hint. The actual body
        // size is authoritative because clients can omit or falsify it.
        if (strlen($rawBody) > self::MAX_BODY_BYTES) {
            return ($this->responder)(WebhookDeliveryResult::payloadTooLarge());
        }

        if (! $verifier->isValid($rawBody, $signatureHeader)) {
            return ($this->responder)(WebhookDeliveryResult::signatureInvalid());
        }

        // Decode only after authenticating the exact bytes. A malformed
        // signed body is still retained for asynchronous classification and
        // diagnostics; rejecting it here would make Meta retry it forever.
        $decoded = null;
        try {
            if ($rawBody !== '') {
                $decoded = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
            }
        } catch (JsonException) {
            $decoded = null;
        }

        try {
            $delivery = DB::transaction(function () use ($signatureHeader, $rawBody, $decoded): WhatsappWebhookDelivery {
                $delivery = WhatsappWebhookDelivery::create([
                    'signature_header' => $signatureHeader,
                    'raw_body' => $rawBody,
                    'raw_payload' => $decoded,
                    'content_length' => strlen($rawBody),
                    'received_at' => now(),
                    'processing_state' => WhatsappWebhookDelivery::STATE_RECEIVED,
                ]);

                ProcessWhatsappWebhookDelivery::dispatch($delivery->id)->afterCommit();

                return $delivery;
            });
        } catch (Throwable) {
            logger()->error('Meta webhook delivery could not be persisted.');

            // We could not persist — Meta should retry. We deliberately
            // do not record a `persistence_failed` row because that
            // would itself require a DB write. Do not report the caught
            // exception: database exceptions can contain the raw signed
            // payload as a query binding. The webhook returns 503 so the
            // operator's Meta delivery dashboard flags it.
            return ($this->responder)(WebhookDeliveryResult::persistenceFailed());
        }

        return ($this->responder)(WebhookDeliveryResult::received($delivery));
    }
}
