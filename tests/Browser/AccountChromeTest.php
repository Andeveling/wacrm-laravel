<?php

use App\Models\Account;
use App\Models\User;

test('login with one membership lands on the dashboard and shows the account name', function () {
    $password = 'password';
    $user = User::factory()->create(['password' => $password]);
    $account = Account::factory()->create(['name' => 'Equipo Acme']);
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    signInAndSelectAccount($user, $password);

    $this->visit('/dashboard')
        ->assertSee('Equipo Acme')
        ->assertSee('Equipo')
        ->assertNoSmoke();
});

test('choosing another membership updates the trigger name', function () {
    $password = 'password';
    $user = User::factory()->create(['password' => $password]);
    $acme = Account::factory()->create(['name' => 'Equipo Acme']);
    $beta = Account::factory()->create(['name' => 'Equipo Beta']);
    $acme->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $beta->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
    $user->forceFill(['last_account_id' => $acme->id])->save();

    signInAndSelectAccount($user, $password);

    $this->visit('/dashboard')
        ->assertSeeIn('[data-testid="accounts-switcher"]', 'Equipo Acme')
        ->click('[data-testid="accounts-switcher"]')
        ->click('[data-testid="account-switcher-item-'.$beta->id.'"]')
        ->assertSeeIn('[data-testid="accounts-switcher"]', 'Equipo Beta')
        ->assertNoSmoke();
});
