<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
});

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

    $this->visit('/login')
        ->type('input#email', $data['viewer']->email)
        ->type('input#password', 'password')
        ->press('button[type="submit"]');

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Members of')
        ->assertDontSee('Invitar miembro')
        ->assertDontSee('invite-role')
        ->assertDontSee('Remover');
});

test('admin sees full management UI', function () {
    $data = createAccountWithUsers();

    $this->visit('/login')
        ->type('input#email', $data['admin']->email)
        ->type('input#password', 'password')
        ->press('button[type="submit"]');

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Members of')
        ->assertSee('Invitar miembro')
        ->assertSee('invite-role');
});

test('admin not owner does not see owner role in select', function () {
    $data = createAccountWithUsers();

    $this->visit('/login')
        ->type('input#email', $data['admin']->email)
        ->type('input#password', 'password')
        ->press('button[type="submit"]');

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->click('Invitar miembro')
        ->assertDontSee('value="owner"');
});

test('sole owner role selector is locked', function () {
    $data = createAccountWithUsers();

    $this->visit('/login')
        ->type('input#email', $data['owner']->email)
        ->type('input#password', 'password')
        ->press('button[type="submit"]');

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Sos el único Owner')
        ->assertAttribute('[data-testid="member-role-select-'.$data['owner']->id.'"]', 'data-locked', 'true');
});
