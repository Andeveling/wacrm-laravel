<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Enums\AccountType;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('register page preserves the invite token', function () {
    $token = Str::random(48);

    $this->get(route('register', ['invite' => $token]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/register')
            ->where('invite', $token)
        );
});

test('registration with invite keeps personal account and joins team', function () {
    $inviter = User::factory()->create();
    $team = Account::factory()->create(['name' => 'Acme Team']);
    $team->users()->attach($inviter->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $token = Str::random(48);

    $invitation = Invitation::factory()
        ->for($team)
        ->for($inviter, 'inviter')
        ->create([
            'role' => AccountRole::Member->value,
            'token_hash' => hash('sha256', $token),
        ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite' => $token,
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $this->assertDatabaseCount('accounts', 2);
    $this->assertDatabaseHas('accounts', ['type' => AccountType::Personal->value]);
    $personal = $user->accounts()->where('type', AccountType::Personal->value)->firstOrFail();
    $teamMembership = $user->accounts()->whereKey($team->id)->firstOrFail();

    expect($personal->pivot->role)->toBe(AccountRole::Owner);
    expect($teamMembership->pivot->role)->toBe(AccountRole::Member);
    expect(session('current_account_id'))->toBe($personal->id);
    expect($user->accounts()->count() === 2)->toBeTrue();
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
    expect($invitation->fresh()->accepted_by)->toBe($user->id);
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Te uniste a Acme Team — Ir']);
});

test('registration with invalid invite rolls back user and personal account', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite' => 'invalid-token',
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseCount('accounts', 0);
    expect(session('current_account_id'))->toBeNull();
});

test('registration with expired invite rolls back user and personal account', function () {
    $inviter = User::factory()->create();
    $team = Account::factory()->create();
    $team->users()->attach($inviter->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
    $token = Str::random(48);

    Invitation::factory()
        ->for($team)
        ->for($inviter, 'inviter')
        ->expired()
        ->create(['token_hash' => hash('sha256', $token)]);

    $response = $this->post(route('register.store'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invite' => $token,
    ]);

    $response->assertSessionHasErrors('invite');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'ada@example.com']);
    $this->assertDatabaseCount('accounts', 1);
    expect(session('current_account_id'))->toBeNull();
});
