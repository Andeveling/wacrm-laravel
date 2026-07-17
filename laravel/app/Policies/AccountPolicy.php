<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

class AccountPolicy
{
    /**
     * Determine whether the user can switch their current account to this one.
     */
    public function switchTo(User $user, Account $account): bool
    {
        return $account->users()->whereKey($user->id)->exists();
    }
}
