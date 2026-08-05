<?php

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('invitee can preview a valid invitation', function () {
    $inviter = User::factory()->create(['name' => 'Fabian']);
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $account->users()->attach($inviter->id, ['role' => 'owner', 'joined_at' => now()]);

    $plainToken = Str::random(48);
    $invitation = Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->create([
            'role' => 'admin',
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
            'label' => 'Welcome Fabian',
        ]);

    $response = $this->get(route('invitations.preview', ['token' => $plainToken]));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('invitations/preview')
        ->where('status', 'valid')
        ->where('account_name', 'Acme Co')
        ->where('role', 'admin')
        ->where('inviter_name', 'Fabian')
        ->where('label', 'Welcome Fabian')
        ->where('token', $plainToken)
        ->where('expires_at', $invitation->expires_at->toIso8601String())
    );
});

test('invitee sees expired state when invitation expired', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $inviter = User::factory()->create(['name' => 'Fabian']);
    $plainToken = Str::random(48);

    Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->expired()
        ->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('invitations.preview', ['token' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('invitations/preview')
            ->where('status', 'expired')
            ->where('account_name', 'Acme Co')
            ->where('inviter_name', 'Fabian')
        );
});

test('invitee sees used state when already accepted', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $inviter = User::factory()->create();
    $accepter = User::factory()->create();
    $plainToken = Str::random(48);

    Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->accepted($accepter)
        ->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('invitations.preview', ['token' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('invitations/preview')
            ->where('status', 'used')
            ->where('account_name', 'Acme Co')
        );
});

test('invitee sees invalid state for revoked invitation', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $plainToken = Str::random(48);

    Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->revoked()
        ->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('invitations.preview', ['token' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('invitations/preview')
            ->where('status', 'invalid')
        );
});

test('invitee sees invalid state for unknown token', function () {
    $this->get(route('invitations.preview', ['token' => Str::random(48)]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('invitations/preview')
            ->where('status', 'invalid')
            ->where('account_name', null)
        );
});

test('preview does not require authentication', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $plainToken = Str::random(48);

    Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('invitations.preview', ['token' => $plainToken]))
        ->assertOk();
});

test('preview hashes the token before lookup', function () {
    $plainToken = 'super-secret-plaintext-token';

    Invitation::factory()->create([
        'token_hash' => hash('sha256', $plainToken),
    ]);

    $this->assertDatabaseMissing('account_invitations', [
        'token_hash' => $plainToken,
    ]);
});

test('valid preview links to register with invite token', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $plainToken = Str::random(48);

    Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->create(['token_hash' => hash('sha256', $plainToken)]);

    $this->get(route('invitations.preview', ['token' => $plainToken]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('invitations/preview')
            ->where('token', $plainToken)
        );
});
