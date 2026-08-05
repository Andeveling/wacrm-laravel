<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;

/**
 * Maps the role a user holds in an account (account_user pivot) to
 * capabilities — the single source of truth for "what can this role
 * do?". Route guards and UI gates call these abilities instead of
 * comparing role strings inline.
 */
class AccountPolicy
{
    /**
     * Determine whether the user can switch their current account to this one.
     */
    public function switchTo(User $user, Account $account): bool
    {
        return $account->users()->whereKey($user->id)->exists();
    }

    /**
     * Owner/admin: invite, remove, and change roles of members.
     */
    public function manageMembers(User $user, Account $account): bool
    {
        return $user->roleIn($account)?->atLeast(AccountRole::Admin) ?? false;
    }

    /**
     * Owner/admin: edit account-wide settings.
     */
    public function editSettings(User $user, Account $account): bool
    {
        return $user->roleIn($account)?->atLeast(AccountRole::Admin) ?? false;
    }

    /**
     * Owner only: manage billing and plan.
     */
    public function manageBilling(User $user, Account $account): bool
    {
        return $user->roleIn($account) === AccountRole::Owner;
    }

    /**
     * Owner only: delete the account.
     */
    public function delete(User $user, Account $account): bool
    {
        return $user->roleIn($account) === AccountRole::Owner;
    }

    /**
     * Owner/admin/member: write operational data (contacts, deals,
     * conversations). Viewers are read-only.
     */
    public function writeOperationalData(User $user, Account $account): bool
    {
        return $user->roleIn($account)?->atLeast(AccountRole::Member) ?? false;
    }

    /**
     * Any member, viewer included: read operational data.
     */
    public function viewOperationalData(User $user, Account $account): bool
    {
        return $user->roleIn($account) !== null;
    }
}
