<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Pest\Browser\Playwright\Playwright;
use Tests\BrowserTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->extend(BrowserTestCase::class)
    ->in('Browser')
    ->beforeEach(function () {
        Playwright::setTimeout(15_000);
    });

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
