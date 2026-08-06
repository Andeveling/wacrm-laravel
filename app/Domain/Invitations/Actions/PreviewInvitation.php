<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Actions;

use App\Models\Invitation;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show the public preview of an invitation. The plaintext token is
 * hashed before lookup; the four outcomes (valid / used / expired /
 * invalid) all render the same page with distinct copy so the
 * invitee always knows why they can't proceed (story 10). No 404:
 * the page returns 200 in every case so the UX stays consistent.
 *
 * When the visitor is already authenticated, we expose their user id
 * so the preview page can swap from "Sign up / Sign in" CTAs into the
 * "Accept invitation" flow. The redeem itself happens client-side
 * via POST /join/{token}/redeem.
 *
 * Single render with no branching outcomes — ADR 0001 rule 4 applies,
 * so there is no Result object and no Responder.
 */
final class PreviewInvitation
{
    public function __invoke(string $token): Response
    {
        $preview = Invitation::previewByTokenHash(hash('sha256', $token));

        return Inertia::render('invitations/preview', [
            'status' => $preview['status']->value,
            'account_name' => $preview['account_name'],
            'role' => $preview['role'],
            'inviter_name' => $preview['inviter_name'],
            'label' => $preview['label'],
            'expires_at' => $preview['expires_at']?->toIso8601String(),
            'token' => $token,
            'authed_user_id' => auth()->id(),
            'locale' => app()->getLocale(),
        ]);
    }
}
