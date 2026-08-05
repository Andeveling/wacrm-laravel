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
});

test('switch page lists only the users accounts', function () {
    $user = User::factory()->create();
    $myAccount = Account::factory()->create(['name' => 'Mine']);
    $myAccount->users()->attach($user->id, ['role' => 'owner', 'joined_at' => now()]);

    $otherUsersAccount = Account::factory()->create(['name' => 'Not mine']);
    $otherUsersAccount->users()->attach(User::factory()->create()->id, ['role' => 'owner', 'joined_at' => now()]);

    $this->actingAs($user)
        ->get(route('accounts.switch'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('accounts/switch')
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
});

test('user cannot switch to an account they do not belong to', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    $response = $this->actingAs($user)->post(route('accounts.switch.update', $account));

    $response->assertForbidden();
    expect(session('current_account_id'))->toBeNull();
});
