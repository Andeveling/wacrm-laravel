<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users with a current account can visit the dashboard', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $response = $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'));

    $response->assertOk();
});

test('authenticated users without a current account are redirected to the switcher', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('accounts.switch'));
});
