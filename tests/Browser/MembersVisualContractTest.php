<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;

function createAccountWithUsers(): array
{
    $owner = User::factory()->create(['password' => 'password']);
    $admin = User::factory()->create(['password' => 'password']);
    $member = User::factory()->create(['password' => 'password']);
    $viewer = User::factory()->create(['password' => 'password']);

    $account = Account::factory()->create(['type' => AccountType::Team]);

    AccountUser::create(['account_id' => $account->id, 'user_id' => $owner->id, 'role' => AccountRole::Owner]);
    AccountUser::create(['account_id' => $account->id, 'user_id' => $admin->id, 'role' => AccountRole::Admin]);
    AccountUser::create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => AccountRole::Member]);
    AccountUser::create(['account_id' => $account->id, 'user_id' => $viewer->id, 'role' => AccountRole::Viewer]);

    return compact('account', 'owner', 'admin', 'member', 'viewer');
}

test('viewer sees member list without management controls', function () {
    $data = createAccountWithUsers();

    signInAndSelectAccount($data['viewer']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Members of')
        ->assertNotPresent('[data-testid="invite-member-form"]')
        ->assertNotPresent('[data-testid="remove-member-'.$data['member']->id.'"]')
        ->assertNotPresent('[data-testid="member-role-select-'.$data['member']->id.'"]');
});

test('admin sees full management UI', function () {
    $data = createAccountWithUsers();

    signInAndSelectAccount($data['admin']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Members of')
        ->assertSee('Invitar miembro')
        ->assertSee('No hay invitaciones pendientes.')
        ->assertPresent('#invite-role')
        ->assertPresent('[data-testid="remove-member-'.$data['member']->id.'"]');
});

test('admin not owner does not see owner role in select', function () {
    $data = createAccountWithUsers();

    signInAndSelectAccount($data['admin']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->click('[data-testid="member-role-select-'.$data['member']->id.'"]')
        ->assertPresent('[data-testid="member-role-option-'.$data['member']->id.'-admin"]')
        ->assertNotPresent('[data-testid="member-role-option-'.$data['member']->id.'-owner"]');
});

test('sole owner role selector is locked', function () {
    $data = createAccountWithUsers();

    signInAndSelectAccount($data['owner']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Eres el único Owner')
        ->assertDisabled('[data-testid="member-role-select-'.$data['owner']->id.'"]')
        ->assertAttribute('[data-testid="member-role-select-'.$data['owner']->id.'"]', 'data-locked', 'true');
});
