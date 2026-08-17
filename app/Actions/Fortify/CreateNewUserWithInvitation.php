<?php

namespace App\Actions\Fortify;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUserWithInvitation implements CreatesNewUsers
{
    public function __construct(private CreateNewUser $createNewUser) {}

    /**
     * Create the user and their Personal account in one transaction, so a
     * partial failure never leaves a User without a home account.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        return DB::transaction(function () use ($input): User {
            $user = $this->createNewUser->create($input);

            $personal = Account::createPersonal();
            $personal->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

            $this->redeemInviteFor($user, $input['invite'] ?? null);

            $user->forceFill(['last_account_id' => $personal->id])->save();

            session(['current_account_id' => $personal->id]);

            return $user;
        });
    }

    /**
     * Redeems an invitation token during registration.
     *
     * Looks up the invitation by its token hash, validates it hasn't been
     * revoked, accepted, or expired, then joins the user to the team with the
     * role specified in the invitation. Throws ValidationException on any
     * failure so the DB::transaction rolls back the user + personal account.
     */
    private function redeemInviteFor(User $user, ?string $token): void
    {
        if ($token === null || $token === '') {
            return;
        }

        $invitation = Invitation::withoutGlobalScopes()
            ->with('account')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null || ! $invitation->isRedeemable()) {
            throw ValidationException::withMessages([
                'invite' => [__('This invitation is invalid or has expired.')],
            ]);
        }

        $team = $invitation->account;
        $team->users()->attach($user->id, ['role' => $invitation->role, 'joined_at' => now()]);

        $invitation->update([
            'accepted_at' => now(),
            'accepted_by' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Te uniste a {$team->name} — Ir"]);
    }
}
