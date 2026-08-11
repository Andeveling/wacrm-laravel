<?php

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can invite a new email and invitation row is created with hashed token', function () {
    $account = Account::factory()->create(['name' => 'Acme Co']);
    $admin = User::factory()->create();
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newhire@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('invited', true);

    $invitation = Invitation::withoutGlobalScopes()->first();
    expect($invitation)->not->toBeNull('Expected an invitation row to be persisted.');
    expect($invitation->account_id)->toBe($account->id);
    expect($invitation->role)->toBe(AccountRole::Member->value);
    expect($invitation->email)->toBe('newhire@gmail.com');
    expect($invitation->invited_by)->toBe($admin->id);

    // SHA-256 hex digest is always 64 lowercase hex chars.
    expect($invitation->token_hash)->toMatch('/^[a-f0-9]{64}$/');
});

test('active invitations for the same normalized email are rejected', function () {
    $account = Account::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'Pending@Gmail.com',
            'role' => AccountRole::Member->value,
        ])
        ->assertRedirect();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'pending@gmail.com',
            'role' => AccountRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    expect(Invitation::withoutGlobalScopes()->count())->toBe(1);
});

test('viewer cannot invite', function () {
    $account = Account::factory()->create();
    $viewer = User::factory()->create();
    $account->users()->attach($viewer->id, ['role' => AccountRole::Viewer->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($viewer)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newperson@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('account_invitations', 0);
});

test('non member cannot invite', function () {
    $account = Account::factory()->create();
    $outsider = User::factory()->create();

    $response = $this
        ->actingAs($outsider)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newperson@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('account_invitations', 0);
});

test('email already a member redirects with validation error and creates no invitation', function () {
    $account = Account::factory()->create();
    $admin = User::factory()->create();
    $existing = User::factory()->create(['email' => 'already@gmail.com']);
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
    $account->users()->attach($existing->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'already@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('account_invitations', 0);
});

test('email already a member of a different account is allowed', function () {
    // Cross-account emails must be valid: an invite is an offer to join
    // THIS account, regardless of where else the user has membership.
    $otherAccount = Account::factory()->create();
    $targetAccount = Account::factory()->create();
    $admin = User::factory()->create();
    $existing = User::factory()->create(['email' => 'shared@gmail.com']);

    $otherAccount->users()->attach($existing->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);
    $targetAccount->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $targetAccount->id])
        ->post(route('accounts.members.store', $targetAccount), [
            'email' => 'shared@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('invited', true);
    $this->assertDatabaseCount('account_invitations', 1);
});

test('missing email redirects with validation error', function () {
    $account = Account::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'role' => AccountRole::Member->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('email');
    $this->assertDatabaseCount('account_invitations', 0);
});

test('invalid role redirects with validation error', function () {
    $account = Account::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newperson@gmail.com',
            'role' => 'superuser',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('role');
    $this->assertDatabaseCount('account_invitations', 0);
});

test('owner can invite', function () {
    // Sanity check that the Owner role — also atLeast(Admin) — passes
    // the same authorization gate.
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newperson@gmail.com',
            'role' => AccountRole::Viewer->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('invited', true);
    $this->assertDatabaseCount('account_invitations', 1);
});

test('owner can invite with role owner', function () {
    $account = Account::factory()->create();
    $owner = User::factory()->create();
    $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($owner)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newowner@gmail.com',
            'role' => AccountRole::Owner->value,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('invited', true);
    $this->assertDatabaseHas('account_invitations', [
        'account_id' => $account->id,
        'role' => AccountRole::Owner->value,
        'invited_by' => $owner->id,
    ]);
});

test('admin cannot invite with role owner', function () {
    $account = Account::factory()->create();
    $admin = User::factory()->create();
    $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

    $response = $this
        ->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('accounts.members.store', $account), [
            'email' => 'newowner@gmail.com',
            'role' => AccountRole::Owner->value,
        ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('account_invitations', 0);
});

test('unauthenticated user is redirected to login', function () {
    $account = Account::factory()->create();

    $response = $this->post(route('accounts.members.store', $account), [
        'email' => 'newperson@gmail.com',
        'role' => AccountRole::Member->value,
    ]);

    $response->assertRedirect(route('login'));
    $this->assertDatabaseCount('account_invitations', 0);
});
