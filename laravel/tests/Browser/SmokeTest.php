<?php

use App\Models\Account;
use App\Models\AccountUser;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;

beforeEach(function () {
    $this->password = 'password';
    $this->owner = User::factory()->create(['password' => $this->password]);
    $this->account = Account::factory()->create(['type' => AccountType::Team]);
    AccountUser::create(['account_id' => $this->account->id, 'user_id' => $this->owner->id, 'role' => AccountRole::Owner]);
});

test('dashboard loads without JS errors', function () {
    $this->visit('/login')
        ->type('input#email', $this->owner->email)
        ->type('input#password', $this->password)
        ->press('button[type="submit"]');

    $this->visit('/dashboard')->assertNoSmoke();
});

test('settings profile loads without JS errors', function () {
    $this->visit('/login')
        ->type('input#email', $this->owner->email)
        ->type('input#password', $this->password)
        ->press('button[type="submit"]');

    $this->visit('/settings/profile')->assertNoSmoke();
});

test('settings security loads without JS errors', function () {
    $this->visit('/login')
        ->type('input#email', $this->owner->email)
        ->type('input#password', $this->password)
        ->press('button[type="submit"]');

    $this->visit('/settings/security')->assertNoSmoke();
});
