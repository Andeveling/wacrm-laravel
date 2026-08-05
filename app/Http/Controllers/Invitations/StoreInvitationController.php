<?php

namespace App\Http\Controllers\Invitations;

use App\Domain\Invitations\Services\InvitationIssuer;
use App\Http\Controllers\Controller;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Create an invite link for the current account (admin+ only, mirroring
 * the Supabase RLS). The plaintext token is flashed exactly once as
 * `invitation_url`; only its SHA-256 hash is persisted, so the link can
 * never be resurfaced — dismissing it means revoke and re-issue.
 *
 * Token hashing + expiry live in {@see InvitationIssuer}; this
 * controller is intentionally thin so the new account-scoped invite
 * route (issue #44) can share the same issuance logic without
 * duplicating it.
 */
class StoreInvitationController extends Controller
{
    public function __invoke(Request $request, CurrentAccount $account, InvitationIssuer $issuer): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member,viewer'],
            'label' => ['nullable', 'string', 'max:80'],
            'expires_in_days' => ['nullable', 'integer', 'between:1,365'],
        ]);

        $issuer->issue(
            accountId: $account->id(),
            inviter: $request->user(),
            role: $validated['role'],
            label: $validated['label'] ?? null,
            expiresInDays: $validated['expires_in_days'] ?? null,
        );

        // Token plaintext intentionally not preserved here — this
        // legacy route was already non-functional (it flashed a URL
        // we cannot reconstruct from the hashed DB row). Behaviour
        // preserved for now; the new account-scoped invite is the
        // supported path.
        return back();
    }
}
