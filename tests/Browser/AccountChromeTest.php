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
