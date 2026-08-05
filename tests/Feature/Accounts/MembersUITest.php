<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('owner gets member management ui contract with a locked sole owner row', function () {
    [$account, $owner, $member] = accountViewedBy(AccountRole::Owner);

    getMembersPage($owner, $account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/members')
            ->where('is_owner', true)
            ->where('is_admin', true)
            ->has('members', 2)
            ->where('members.0.id', $member->id)
            ->where('members.1.id', $owner->id)
            ->where('members.1.role', AccountRole::Owner->value)
            ->where('members.1.is_you', true)
        );
});

test('admin gets member management ui contract without owner privileges', function () {
    [$account, $admin] = accountViewedBy(AccountRole::Admin);

    getMembersPage($admin, $account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/members')
            ->where('is_owner', false)
            ->where('is_admin', true)
        );
});

test('member gets read only ui contract', function () {
    [$account, $member] = accountViewedBy(AccountRole::Member);

    getMembersPage($member, $account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/members')
            ->where('is_owner', false)
            ->where('is_admin', false)
        );
});

test('viewer gets read only ui contract', function () {
    [$account, $viewer] = accountViewedBy(AccountRole::Viewer);

    getMembersPage($viewer, $account)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/members')
            ->where('is_owner', false)
            ->where('is_admin', false)
        );
});

/**
 * @return array{Account, User, User}
 */
function accountViewedBy(AccountRole $role): array
{
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $viewer = User::factory()->create(['name' => 'Zoe Viewer']);
    $member = User::factory()->create(['name' => 'Alice Member']);

    $account->users()->attach($viewer->id, ['role' => $role->value, 'joined_at' => now()]);
    $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

    return [$account, $viewer, $member];
}

function getMembersPage(User $viewer, Account $account): TestResponse
{
    return test()
        ->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('accounts.members.index', $account));
}
