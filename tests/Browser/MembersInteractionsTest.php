<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;

function createSimpleAccount(): array
{
    $owner = User::factory()->create(['password' => 'password']);
    $member = User::factory()->create(['password' => 'password']);
    $account = Account::factory()->create(['type' => AccountType::Team]);

    AccountUser::create(['account_id' => $account->id, 'user_id' => $owner->id, 'role' => AccountRole::Owner]);
    AccountUser::create(['account_id' => $account->id, 'user_id' => $member->id, 'role' => AccountRole::Member]);

    return compact('account', 'owner', 'member');
}

test('invite member shows success feedback', function () {
    $data = createSimpleAccount();

    signInAndSelectAccount($data['owner']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertSee('Invitar miembro')
        ->type('input#invite-email', 'nuevo@gmail.com')
        ->click('[data-testid="invite-member-submit"]')
        ->assertSee('Invitación creada');
});

test('change member role persists the change', function () {
    $data = createSimpleAccount();

    signInAndSelectAccount($data['owner']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->click('[data-testid="member-role-select-'.$data['member']->id.'"]')
        ->click('[data-testid="member-role-option-'.$data['member']->id.'-admin"]')
        ->assertSeeIn('[data-testid="member-role-select-'.$data['member']->id.'"]', 'Admin');

    expect($data['member']->fresh()->roleIn($data['account']))->toBe(AccountRole::Admin);
});

test('remove member via dialog removes from list', function () {
    $data = createSimpleAccount();

    signInAndSelectAccount($data['owner']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->click('[data-testid="remove-member-'.$data['member']->id.'"]')
        ->assertSee('¿Remover miembro?')
        ->click('[data-testid="confirm-remove-member"]')
        ->assertNotPresent('[data-testid="member-row-'.$data['member']->id.'"]');

    expect($data['member']->fresh()->roleIn($data['account']))->toBeNull();
});

test('owner cannot remove themselves — remove button is disabled', function () {
    $data = createSimpleAccount();

    signInAndSelectAccount($data['owner']);

    $this->visit('/accounts/'.$data['account']->id.'/members')
        ->assertNoSmoke()
        ->assertPresent('[data-testid="remove-member-'.$data['owner']->id.'"]')
        ->assertDisabled('[data-testid="remove-member-'.$data['owner']->id.'"]');
});
