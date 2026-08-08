<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Tests\BrowserTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->extend(BrowserTestCase::class)->in('Browser');

pest()->browser()->timeout(15_000);

/**
 * Submits the login form in the browser. Stops there: where the app sends the
 * user next depends on the account and on two-factor, so each caller asserts
 * its own landing page.
 */
function signIn(User $user, string $password = 'password'): void
{
    test()->visit('/login')
        ->type('input#email', $user->email)
        ->type('input#password', $password)
        ->press('button[type="submit"]');
}

/**
 * Signs the user in and puts `current_account_id` in the session by picking
 * their account in the switcher.
 *
 * Every route behind `ensure.current-account` redirects to the switcher until
 * that session key exists. A Browser test that logs in and jumps straight to
 * such a route therefore renders the switcher, so every selector it looks for
 * misses — and a browser action against a selector that never matches was
 * measured waiting over 400s without returning (see #97).
 */
function signInAndSelectAccount(User $user, string $password = 'password'): void
{
    signIn($user, $password);

    test()->visit('/accounts/switch')
        ->click('[data-testid="accounts-switcher"] button')
        ->assertPathIs('/dashboard');
}

/**
 * @return array{0: Account, 1: User}
 */
function seedAccountWithRole(AccountRole $role): array
{
    $account = Account::factory()->create();
    $user = User::factory()->create();
    $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

    return [$account, $user];
}

function attachUserToAccount(Account $account, AccountRole $role): User
{
    $user = User::factory()->create();
    $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

    return $user;
}

/**
 * @return array{0: User, 1: Account}
 */
function memberWithRole(string $role): array
{
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

    return [$user, $account];
}
