<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Responders;

use App\Domain\Invitations\Actions\RedeemInvitation;
use App\Domain\Invitations\Results\RedeemInvitationResult;
use App\Domain\Invitations\Support\RedeemInvitationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Maps a {@see RedeemInvitationResult} from {@see RedeemInvitation} to
 * the HTTP contract the client-side redeem flow expects:
 *
 *   - 302 → /dashboard on success (the browser reload picks up the new
 *     account context).
 *   - 409 → the caller is already in another account or owns data that
 *     would conflict. Inertia does not render non-2xx as a page, so the
 *     message travels in the `redeem_conflict` error bag and the client
 *     opens its recovery modal from `errors.invite`.
 *   - 422 → invitation is not redeemable. Surfaces through the standard
 *     Inertia error bag like any validation failure.
 *   - 401 → no authenticated user.
 *
 * Transport only: never re-evaluates the Action's rules.
 */
final readonly class RedeemInvitationResponder
{
    public function __invoke(RedeemInvitationResult $result): RedirectResponse
    {
        return match ($result->status) {
            RedeemInvitationStatus::Redeemed => $this->redeemed(),
            RedeemInvitationStatus::Unauthenticated => abort(401),
            RedeemInvitationStatus::NotRedeemable => throw ValidationException::withMessages([
                'invite' => [$result->message],
            ])->status(422),
            RedeemInvitationStatus::Conflict => back()
                ->withErrors(['invite' => $result->message], 'redeem_conflict')
                ->setStatusCode(409),
        };
    }

    private function redeemed(): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('auth.ui.invitation_preview.redeem_reached_toast'),
        ]);

        return redirect()->route('dashboard');
    }
}
