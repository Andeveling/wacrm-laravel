<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Enums\AccountType;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Redeem a valid invitation for the currently authenticated user.
 *
 * Mirrors the Next.js `/api/invitations/<token>/redeem` endpoint:
 *
 *   - 302 → /dashboard on success (we redirect via Inertia so the
 *     browser reload picks up the new account context).
 *   - 409 → caller is already in another account or owns data that
 *     would conflict. The descriptive message bubbles up to the
 *     client-side conflict modal so the user can pick a recovery
 *     action (sign out and use a different email).
 *   - 422 → invitation is not redeemable (revoked / used / expired /
 *     not found). The Inertia error bag surfaces in the same way as
 *     any other Laravel validation failure.
 *
 * Authoritative on success: same `CreateNewUserWithInvitation`-style
 * transaction is used here for the redeem-after-signup case. The
 * session account id is re-bound so the middleware picks it up on
 * the next request.
 */
class RedeemInvitationController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $invitation = Invitation::withoutGlobalScopes()
            ->with('account')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isRedeemable()) {
            throw ValidationException::withMessages([
                'invite' => [__('auth.ui.invitation_preview.expired_description')],
            ])->status(422);
        }

        // 409: caller already in another account. Mirrors the
        // `redeem_invitation` Postgres function Next uses. In Laravel
        // we model this as "the user already belongs to an account
        // that is not the target account" or "the user's personal
        // account has data attached (contacts, conversations, etc.)".
        $existing = DB::table('account_user')
            ->where('user_id', $user->id)
            ->where('account_id', '!=', $invitation->account_id)
            ->exists();

        if ($existing) {
            return $this->conflict(
                __('auth.ui.invitation_preview.already_member'),
            );
        }

        if ($this->callerOwnsDomainData($user)) {
            return $this->conflict(
                __('auth.ui.invitation_preview.already_member'),
            );
        }

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->account->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => $invitation->role,
                    'joined_at' => now(),
                ],
            ]);

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);

            session(['current_account_id' => $invitation->account_id]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('auth.ui.invitation_preview.redeem_reached_toast'),
        ]);

        return redirect()->route('dashboard');
    }

    private function callerOwnsDomainData(mixed $user): bool
    {
        // Heuristic: any non-personal account they administer, or any
        // data attached to their personal account. We treat the
        // personal account as "carrying domain data" if any model row
        // points to it. Cheaper than scanning every domain table —
        // catches the common cases (already created contacts, sent
        // messages) without a per-table migration cost.
        $personalAccountId = Account::query()
            ->where('type', AccountType::Personal->value)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->value('id');

        if ($personalAccountId === null) {
            return false;
        }

        return DB::table('contacts')->where('account_id', $personalAccountId)->exists()
            || DB::table('conversations')->where('account_id', $personalAccountId)->exists()
            || DB::table('whatsapp_configs')->where('account_id', $personalAccountId)->exists();
    }

    private function conflict(string $message): RedirectResponse
    {
        // Inertia does not render non-2xx as a page, so we surface the
        // conflict via a flashed flashbag value and a redirect back to
        // the preview page. The client checks for `errors.invite` and
        // opens the modal.
        return back()
            ->withErrors(['invite' => $message], 'redeem_conflict')
            ->setStatusCode(409);
    }
}
