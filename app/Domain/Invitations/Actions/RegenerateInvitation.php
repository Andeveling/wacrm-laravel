<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Actions;

use App\Domain\Invitations\Services\InvitationIssuer;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces an active or expired invitation with a fresh seven-day link.
 * The original row is retained and revoked for auditability.
 */
final readonly class RegenerateInvitation
{
    public function __construct(
        private InvitationIssuer $issuer,
    ) {}

    public function __invoke(Request $request, CurrentAccount $account, string $invitation): RedirectResponse
    {
        abort_unless($account->isAdmin(), 403);

        /** @var User $user */
        $user = $request->user();

        $issued = DB::transaction(function () use ($account, $invitation, $user): array {
            // Serialize against InviteMember's duplicate-active-invitation check.
            // An expired row may otherwise be regenerated after a newer link was
            // issued for its recipient, leaving two simultaneously valid links.
            Account::query()->whereKey($account->id())->lockForUpdate()->firstOrFail();

            $original = Invitation::query()
                ->whereKey($invitation)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->email !== null && Invitation::query()
                ->where('email', $original->email)
                ->whereKeyNot($original->id)
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'This email already has an active invitation for this account.',
                ]);
            }

            $original->update(['revoked_at' => now()]);

            return $this->issuer->issue(
                accountId: $account->id(),
                inviter: $user,
                role: $original->role,
                email: $original->email,
                label: $original->label,
            );
        });

        return back()->with('invitation_url', route('invitations.preview', $issued['token']));
    }
}
