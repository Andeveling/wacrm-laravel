<?php

declare(strict_types=1);

namespace App\Domain\Meta\Actions;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * GET /api/whatsapp/webhook — Meta's subscription verification handshake.
 * Echoes `hub.challenge` back in plain text when `hub.verify_token`
 * matches `META_WEBHOOK_VERIFY_TOKEN`; 400 on malformed parameters, 403
 * on a token mismatch.
 *
 * There is no domain state to carry here — the reply is a function of
 * the query string alone — so ADR 0001 rule 4 applies: no Result object
 * and no Responder.
 */
final class VerifyMetaWebhook
{
    public function __invoke(Request $request): Response
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
}
