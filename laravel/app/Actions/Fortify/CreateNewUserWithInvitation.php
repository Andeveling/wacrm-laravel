<?php

namespace App\Actions\Fortify;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

            $account = Account::createPersonal();
            $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

            session(['current_account_id' => $account->id]);

            return $user;
        });
    }
}
