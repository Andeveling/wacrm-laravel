<?php

declare(strict_types=1);

namespace App\Http\Controllers\Meta;

use App\Http\Controllers\Controller;
use App\Models\WhatsappWebhookDelivery;
use App\Services\Meta\VerifyMetaWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use RuntimeException;
use Throwable;

/**
 * Ingress del webhook de Meta WhatsApp Business Platform (#64).
 *
 *   GET  /api/whatsapp/webhook  — verificación de suscripción de Meta:
 *                                 responde el `hub.challenge` en texto
 *                                 plano cuando `hub.verify_token`
 *                                 coincide con `META_WEBHOOK_VERIFY_TOKEN`.
 *
 *   POST /api/whatsapp/webhook  — verificación HMAC + persistencia de la
 *                                 entrega ANTES de responder `200`. Un
 *                                 fallo del worker posterior se resuelve
 *                                 con reintentos internos y dead-letter,
 *                                 nunca con pedir redelivery a Meta.
 *
 * Contrato HTTP:
 *   200 — verificación o entrega persistida.
 *   400 — JSON malformado o cuerpo vacío tras firma válida.
 *   401 — firma ausente / inválida / META_APP_SECRET no configurado.
 *   413 — Content-Length mayor al límite (1 MB).
 *   503 — persistencia fallida; Meta reintenta.
 *
 * El controlador es deliberadamente delgado: no lee el cuerpo con
 * `$request->json()` antes de verificar la firma (re-encodea el payload
 * y rompe el HMAC); usa `$request->getContent()` para los bytes crudos.
 */
class MetaWebhookController extends Controller
{
    /**
     * Maximum body size we accept on the webhook. Meta deliveries are
     * small JSON envelopes — images / videos / documents arrive as
     * media_ids the worker fetches later, so 1 MB is generous.
     */
    private const MAX_BODY_BYTES = 1_048_576;

    public function verify(Request $request, VerifyMetaWebhookSignature $verifier): Response
    {
        // Laravel's request normalisation converts dots in query keys to
        // underscores (e.g. `hub.mode` → `hub_mode`). Meta posts them
        // with dots, so we read the underscored variant and document
        // the mapping here.
        $query = $request->query();
        $mode = $query['hub_mode'] ?? null;
        $challenge = $query['hub_challenge'] ?? null;
        $verifyToken = $query['hub_verify_token'] ?? null;

        if ($mode !== 'subscribe' || ! is_string($challenge) || ! is_string($verifyToken)) {
            return response(
                'Missing or malformed verification parameters.',
                Response::HTTP_BAD_REQUEST,
            )->header('Content-Type', 'text/plain');
        }

        $expected = (string) config('services.meta.webhook_verify_token');

        if ($expected === '' || ! hash_equals($expected, $verifyToken)) {
            return response(
                'Forbidden.',
                Response::HTTP_FORBIDDEN,
            )->header('Content-Type', 'text/plain');
        }

        return response($challenge, Response::HTTP_OK, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function receive(Request $request, VerifyMetaWebhookSignature $verifier): Response|JsonResponse
    {
        $signatureHeader = $request->header('X-Hub-Signature-256');
        $contentLengthHeader = $request->header('Content-Length');

        if (is_string($contentLengthHeader) && (int) $contentLengthHeader > self::MAX_BODY_BYTES) {
            return response()->json([
                'error' => ['code' => 'payload_too_large', 'message' => 'Body exceeds webhook limit.'],
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        // Fail closed when the secret isn't configured — even a
        // well-formed signature cannot pass without it. Logged so
        // operators see the misconfiguration in their dashboard.
        if (! VerifyMetaWebhookSignature::isSecretConfigured()) {
            report(new RuntimeException(
                'Meta webhook hit while META_APP_SECRET is not configured; rejecting.',
            ));

            return response()->json([
                'error' => ['code' => 'signature_invalid', 'message' => 'Signature verification failed.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $rawBody = $request->getContent();

        if (! $verifier->isValid($rawBody, $signatureHeader)) {
            return response()->json([
                'error' => ['code' => 'signature_invalid', 'message' => 'Signature verification failed.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        // After signature validation we can decode safely. Empty body
        // is a 400 — there is nothing to persist as a delivery, since
        // the contract is "raw_payload must be JSON".
        if ($rawBody === '') {
            return response()->json([
                'error' => ['code' => 'invalid_body', 'message' => 'Body must be JSON.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $decoded = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return response()->json([
                'error' => ['code' => 'invalid_body', 'message' => 'Body must be JSON.'],
            ], Response::HTTP_BAD_REQUEST);
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
            return response()->json([
                'error' => ['code' => 'persistence_failed', 'message' => 'Delivery could not be recorded; retry.'],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return response()->json([
            'data' => ['delivery_id' => $delivery->id, 'state' => $delivery->processing_state],
        ], Response::HTTP_OK);
    }
}
