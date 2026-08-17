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

test('it re-resolves when the session account is no longer a membership', function () {
    $user = User::factory()->create();
    $ownAccount = Account::factory()->personal()->create();
    $ownAccount->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $foreignAccount = Account::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $foreignAccount->id])
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('current_account_id'))->toBe($ownAccount->id);
});

test('it returns 403 when session account no longer belongs to user', function () {
    $user = User::factory()->create();
    $otherAccount = Account::factory()->create();

    $this->actingAs($user)
        ->withSession(['current_account_id' => $otherAccount->id])
        ->get(route('dashboard'))
        ->assertForbidden();
});

test('it prefers the only team over personal when nothing else is remembered', function () {
    $user = User::factory()->create();
    $personal = Account::factory()->personal()->create();
    $team = Account::factory()->create(['name' => 'Equipo Acme']);
    $personal->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $team->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('current_account_id'))->toBe($team->id);
});

test('it prefers last_account_id when the session has no current account', function () {
    $user = User::factory()->create();
    $personal = Account::factory()->personal()->create();
    $team = Account::factory()->create(['name' => 'Equipo Acme']);
    $personal->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);
    $team->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);
    $user->forceFill(['last_account_id' => $personal->id])->save();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('current_account_id'))->toBe($personal->id);
});

test('it resolves the only membership when no current account is selected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->personal()->create();
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();

    expect(session('current_account_id'))->toBe($account->id);
    expect(app(CurrentAccount::class)->id())->toBe($account->id);
});
