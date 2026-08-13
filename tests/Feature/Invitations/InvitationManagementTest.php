<?php

use App\Models\Account;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can send an invitation and sees the link once', function () {
    [$admin, $account] = memberWithRole('admin');

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), [
            'role' => 'member',
            'label' => 'Nuevo agente',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('invitation_url');

    $this->assertDatabaseHas('account_invitations', [
        'account_id' => $account->id,
        'role' => 'member',
        'label' => 'Nuevo agente',
        'invited_by' => $admin->id,
    ]);
});

test('invitation token is stored hashed and link resolves to it', function () {
    [$admin, $account] = memberWithRole('admin');

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'member']);

    $url = session('invitation_url');
    expect($url)->toBeString();

    $token = basename(parse_url($url, PHP_URL_PATH));

    $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
    expect($invitation->token_hash)->toBe(hash('sha256', $token));
    $this->assertDatabaseMissing('account_invitations', ['token_hash' => $token]);
});

test('invitation expiry defaults to seven days', function () {
    [$admin, $account] = memberWithRole('admin');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'viewer']);

    $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
    expect($invitation->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

test('invitation accepts a custom expiry within bounds', function () {
    [$admin, $account] = memberWithRole('admin');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'member', 'expires_in_days' => 365]);

    $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
    expect($invitation->expires_at->isSameDay(now()->addDays(365)))->toBeTrue();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'member', 'expires_in_days' => 366])
        ->assertSessionHasErrors('expires_in_days');
});

test('already revoked invitation cannot be revoked again', function () {
    [$admin, $account] = memberWithRole('admin');
    $invitation = Invitation::factory()->for($account)->revoked()->create();
    $originalRevokedAt = $invitation->revoked_at;

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $invitation))
        ->assertNotFound();

    expect($invitation->fresh()->revoked_at->equalTo($originalRevokedAt))->toBeTrue();
});

test('owner role cannot be invited', function () {
    [$admin, $account] = memberWithRole('admin');

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'owner'])
        ->assertSessionHasErrors('role');

    $this->assertDatabaseCount('account_invitations', 0);
});

test('member cannot send invitations', function () {
    [$member, $account] = memberWithRole('member');

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.store'), ['role' => 'viewer'])
        ->assertForbidden();

    $this->assertDatabaseCount('account_invitations', 0);
});

test('guest cannot send invitations', function () {
    $this->post(route('invitations.store'), ['role' => 'member'])
        ->assertRedirect(route('login'));
});

test('admin can revoke a pending invitation', function () {
    [$admin, $account] = memberWithRole('admin');
    $invitation = Invitation::factory()->for($account)->for($admin, 'inviter')->create();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
});

test('revoked invitation token cannot be previewed', function () {
    [$admin, $account] = memberWithRole('admin');
    $token = 'known-invitation-token';
    $invitation = Invitation::factory()->for($account)->create([
        'token_hash' => hash('sha256', $token),
    ]);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $invitation))
        ->assertRedirect();

    $this->get(route('invitations.preview', ['token' => $token]))
        ->assertInertia(fn ($page) => $page
            ->component('invitations/preview')
            ->where('status', 'invalid')
        );
});

test('admin can regenerate an active invitation, retaining its audit row', function () {
    [$admin, $account] = memberWithRole('admin');
    $invitation = Invitation::factory()
        ->for($account)
        ->for($admin, 'inviter')
        ->create(['email' => 'persona@example.com', 'role' => 'viewer', 'label' => 'Equipo soporte']);

    $response = $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.regenerate', $invitation));

    $response->assertRedirect();
    $response->assertSessionHas('invitation_url');
    $original = $invitation->fresh();
    expect($original->revoked_at)->not->toBeNull();

    $replacement = Invitation::withoutGlobalScopes()
        ->where('account_id', $account->id)
        ->where('id', '!=', $invitation->id)
        ->sole();

    expect($replacement->email)->toBe('persona@example.com')
        ->and($replacement->role)->toBe('viewer')
        ->and($replacement->label)->toBe('Equipo soporte')
        ->and($replacement->token_hash)->not->toBe($original->token_hash)
        ->and($replacement->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

test('admin can regenerate an expired invitation', function () {
    [$admin, $account] = memberWithRole('admin');
    $invitation = Invitation::factory()
        ->for($account)
        ->for($admin, 'inviter')
        ->expired()
        ->create(['email' => 'persona@example.com']);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.regenerate', $invitation))
        ->assertRedirect();

    expect($invitation->fresh()->revoked_at)->not->toBeNull();
    $this->assertDatabaseCount('account_invitations', 2);
});

test('expired invitation cannot be regenerated when its recipient already has an active invitation', function () {
    [$admin, $account] = memberWithRole('admin');
    $expired = Invitation::factory()
        ->for($account)
        ->for($admin, 'inviter')
        ->expired()
        ->create(['email' => 'persona@example.com']);
    Invitation::factory()->for($account)->for($admin, 'inviter')->create(['email' => 'persona@example.com']);

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.regenerate', $expired))
        ->assertSessionHasErrors('email');

    expect($expired->fresh()->revoked_at)->toBeNull();
    $this->assertDatabaseCount('account_invitations', 2);
});

test('member cannot regenerate invitations', function () {
    [$member, $account] = memberWithRole('member');
    $invitation = Invitation::factory()->for($account)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.regenerate', $invitation))
        ->assertForbidden();

    expect($invitation->fresh()->revoked_at)->toBeNull();
});

test('regenerate is scoped to the current account', function () {
    [$admin, $account] = memberWithRole('admin');
    $otherAccount = Account::factory()->create();
    $foreign = Invitation::factory()->for($otherAccount)->create();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->post(route('invitations.regenerate', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->revoked_at)->toBeNull();
});

test('revoked and accepted invitations cannot be regenerated', function () {
    [$admin, $account] = memberWithRole('admin');

    foreach ([Invitation::factory()->for($account)->revoked()->create(), Invitation::factory()->for($account)->accepted()->create()] as $invitation) {
        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.regenerate', $invitation))
            ->assertNotFound();
    }

    $this->assertDatabaseCount('account_invitations', 2);
});

test('revoke is scoped to the current account', function () {
    [$admin, $account] = memberWithRole('admin');
    $otherAccount = Account::factory()->create();
    $foreign = Invitation::factory()->for($otherAccount)->create();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $foreign))
        ->assertNotFound();

    expect($foreign->fresh()->revoked_at)->toBeNull();
});

test('member cannot revoke invitations', function () {
    [$member, $account] = memberWithRole('member');
    $invitation = Invitation::factory()->for($account)->create();

    $this->actingAs($member)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $invitation))
        ->assertForbidden();

    expect($invitation->fresh()->revoked_at)->toBeNull();
});

test('accepted invitation cannot be revoked', function () {
    [$admin, $account] = memberWithRole('admin');
    $invitation = Invitation::factory()->for($account)->accepted()->create();

    $this->actingAs($admin)
        ->withSession(['current_account_id' => $account->id])
        ->delete(route('invitations.revoke', $invitation))
        ->assertNotFound();

    expect($invitation->fresh()->revoked_at)->toBeNull();
});
