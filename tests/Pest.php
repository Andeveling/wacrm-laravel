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
 * Signs the user in through the browser and puts `current_account_id` in the
 * session by picking their account in the switcher.
 *
 * Every route behind `ensure.current-account` redirects to the switcher until
 * that session key exists. A Browser test that logs in and jumps straight to
 * such a route therefore renders the switcher, so every selector it looks for
 * misses — and a Playwright locator call against a selector that never matches
 * never returns (see #97).
 */
function signInAndSelectAccount(User $user, string $password = 'password'): void
{
    test()->visit('/login')
        ->type('input#email', $user->email)
        ->type('input#password', $password)
        ->press('button[type="submit"]');

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
