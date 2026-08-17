<?php

declare(strict_types=1);

namespace App\Domain\Invitations\Actions;

use App\Domain\Invitations\Responders\RedeemInvitationResponder;
use App\Domain\Invitations\Results\RedeemInvitationResult;
use App\Models\Account;
use App\Models\Enums\AccountType;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Redeem a valid invitation for the currently authenticated user.
 *
 * The Action decides which of the four outcomes applies and hands a
 * {@see RedeemInvitationResult} to the Responder, which owns the HTTP
 * shape (302 / 401 / 409 / 422).
 *
 * On success the membership pivot, the invitation row and the session
 * account id are written in one transaction, so the middleware picks up
 * the new tenant on the next request.
 */
final readonly class RedeemInvitation
{
    public function __construct(
        private RedeemInvitationResponder $responder,
    ) {}

    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ($this->responder)(RedeemInvitationResult::unauthenticated());
        }

        $invitation = Invitation::withoutGlobalScopes()
            ->with('account')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isRedeemable()) {
            return ($this->responder)(RedeemInvitationResult::notRedeemable());
        }

        // Mirrors the `redeem_invitation` Postgres function Next used:
        // the user already belongs to an account that is not the target,
        // or their personal account already carries domain data.
        $belongsElsewhere = DB::table('account_user')
            ->where('user_id', $user->id)
            ->where('account_id', '!=', $invitation->account_id)
            ->exists();

        if ($belongsElsewhere || $this->ownsDomainData($user)) {
            return ($this->responder)(RedeemInvitationResult::conflict());
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

        return ($this->responder)(RedeemInvitationResult::redeemed());
    }

    /**
     * Heuristic: the personal account "carries domain data" if any row in
     * the tables users touch first points at it. Cheaper than scanning
     * every domain table and catches the common cases (contacts created,
     * conversations started, WhatsApp connected).
     */
    private function ownsDomainData(User $user): bool
    {
        $personalAccountId = Account::query()
            ->where('type', AccountType::Personal->value)
            ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
            ->value('id');

        if ($personalAccountId === null) {
            return false;
        }

        return DB::table('contacts')->where('account_id', $personalAccountId)->exists()
            || DB::table('conversations')->where('account_id', $personalAccountId)->exists()
            || DB::table('whatsapp_phone_number_connections')->where('account_id', $personalAccountId)->exists();
    }
}
