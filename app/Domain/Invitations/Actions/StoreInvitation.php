<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Actions;

use App\Domain\Accounts\Actions\InviteMember;
use App\Domain\Invitations\Services\InvitationIssuer;
use App\Http\Requests\Invitations\StoreInvitationRequest;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

/**
 * Create an invite link for the current account (admin+ only, mirroring
 * the Supabase RLS). Token hashing + expiry live in
 * {@see InvitationIssuer} so this Action and the account-scoped
 * {@see InviteMember} share one source of
 * truth.
 *
 * The plaintext token is intentionally not preserved here — this legacy
 * route was already non-functional (it flashed a URL that cannot be
 * reconstructed from the hashed DB row). Behaviour preserved as-is; the
 * account-scoped invite is the supported path.
 */
final readonly class StoreInvitation
{
    public function __construct(
        private InvitationIssuer $issuer,
    ) {}

    public function __invoke(StoreInvitationRequest $request, CurrentAccount $account): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $this->issuer->issue(
            accountId: $account->id(),
            inviter: $request->user(),
            role: $request->validated('role'),
            label: $request->validated('label'),
            expiresInDays: $request->validated('expires_in_days'),
        );

        return back();
    }
}
