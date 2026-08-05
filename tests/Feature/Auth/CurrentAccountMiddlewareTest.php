<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use App\Support\CurrentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it binds current account from session', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $account->id])
        ->get(route('dashboard'))
        ->assertOk();

    expect(app()->bound(CurrentAccount::class))->toBeTrue();
    expect(app(CurrentAccount::class)->id())->toBe($account->id);
    expect(app(CurrentAccount::class)->role())->toBe(AccountRole::Owner);
});

test('it returns 403 when session account no longer belongs to user', function () {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $otherAccount->id])
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('it redirects to switcher when no current account is selected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('accounts.switch'));
});
