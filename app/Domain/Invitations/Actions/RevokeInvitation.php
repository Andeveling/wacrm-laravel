<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Actions;

use App\Models\Invitation;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;

/**
 * Soft-revoke a pending invitation (admin+ only). The id arrives as a
 * plain string — implicit binding would resolve before EnsureCurrentAccount
 * binds the tenant, and the fail-closed AccountScope would 404 every
 * request. Looking it up here runs the query with the scope active, so a
 * cross-account id 404s without leaking existence.
 *
 * Single outcome plus guard clauses — ADR 0001 rule 4 applies, so no
 * Result object and no Responder.
 */
final class RevokeInvitation
{
    public function __invoke(CurrentAccount $account, string $invitation): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        Invitation::query()
            ->whereKey($invitation)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->firstOrFail()
            ->update(['revoked_at' => now()]);

        return back();
    }
}
