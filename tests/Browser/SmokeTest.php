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
    signInAndSelectAccount($this->owner);

    $this->visit('/dashboard')->assertNoSmoke();
});

test('settings profile loads without JS errors', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/settings/profile')->assertNoSmoke();
});

test('settings security loads without JS errors', function () {
    signInAndSelectAccount($this->owner);

    $this->visit('/settings/security')->assertNoSmoke();
});
