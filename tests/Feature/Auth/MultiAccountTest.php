<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

test('registration creates a personal account owned by the user', function () {
    $this->skipUnlessFortifyHas(Features::registration());

    $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $account = $user->accounts()->sole();

    expect($account->name)->toBe('Personal');
    expect($account->type === AccountType::Personal)->toBeTrue();
    expect($account->pivot->role)->toBe(AccountRole::Owner);
    expect(session('current_account_id'))->toBe($account->id);
    expect($user->last_account_id)->toBe($account->id);
});

test('the switch page no longer exists', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Mine']);
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get('/accounts/switch')
        ->assertNotFound();
});

test('settings pages share the current account without the tenant middleware', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Mine']);
    $account->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('settings.overview'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentAccount.id', $account->id)
            ->where('currentAccount.name', 'Mine')
            ->where('currentAccount.type', 'team')
            ->where('currentAccount.role', 'owner')
            ->has('accounts', 1)
        );
});

test('authenticated pages share the current account and memberships', function () {
    $user = User::factory()->create();
    $mine = Account::factory()->create(['name' => 'Mine']);
    $mine->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $foreign = Account::factory()->create(['name' => 'Not mine']);
    $foreign->users()->attach(User::factory()->create()->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->withSession(['current_account_id' => $mine->id])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('currentAccount.id', $mine->id)
            ->where('currentAccount.name', 'Mine')
            ->where('currentAccount.type', 'team')
            ->where('currentAccount.role', 'owner')
            ->has('accounts', 1)
            ->where('accounts.0.name', 'Mine')
        );
});

test('user can switch to an account they belong to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $account->users()->attach($user->id, ['role' => 'member', 'joined_at' => now()]);

    $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

    $response->assertRedirect(route('dashboard'));
    expect(session('current_account_id'))->toBe($account->id);
    expect($user->fresh()->last_account_id)->toBe($account->id);
});

test('user cannot switch to an account they do not belong to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

    $response->assertForbidden();
    expect(session('current_account_id'))->toBeNull();
});
