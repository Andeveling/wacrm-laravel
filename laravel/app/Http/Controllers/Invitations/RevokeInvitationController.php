<?php

namespace App\Http\Controllers\Invitations;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RevokeInvitationController extends Controller
{
    /**
     * Soft-revoke a pending invitation (admin+ only). The id arrives as a
     * plain string — implicit binding would resolve before EnsureCurrentAccount
     * binds the tenant, and the fail-closed AccountScope would 404 every
     * request. Looking it up here runs the query with the scope active, so a
     * cross-account id 404s without leaking existence.
     */
    public function __invoke(Request $request, CurrentAccount $account, string $invitation): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        $pending = Invitation::query()
            ->whereKey($invitation)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $pending->update(['revoked_at' => now()]);

        return back();
    }
}
