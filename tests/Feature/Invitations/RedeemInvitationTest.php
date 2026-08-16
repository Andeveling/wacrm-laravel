<?php

use App\Models\Account;
use App\Models\Contact;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function redeemableInvitation(Account $account, User $inviter, string $token): Invitation
{
    return Invitation::factory()
        ->for($account)
        ->for($inviter, 'inviter')
        ->create(['token_hash' => hash('sha256', $token), 'role' => 'member']);
}

test('invitee redeems an active invitation', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $token = Str::random(48);
    $invitation = redeemableInvitation($account, $inviter, $token);

    $this->actingAs($invitee)
        ->post(route('invitations.redeem', $token))
        ->assertRedirect(route('dashboard'));

    $this->assertDatabaseHas('account_user', [
        'account_id' => $account->id,
        'user_id' => $invitee->id,
        'role' => 'member',
    ]);
    $this->assertDatabaseHas('account_invitations', [
        'id' => $invitation->id,
        'accepted_by' => $invitee->id,
    ]);
    expect(session('current_account_id'))->toBe($account->id);
});

test('invitee cannot redeem an unavailable invitation', function () {
    $token = Str::random(48);

    $this->actingAs(User::factory()->create())
        ->from(route('invitations.preview', $token))
        ->post(route('invitations.redeem', $token))
        ->assertInvalid(['invite']);
});

test('invitee cannot redeem an invitation while belonging to another account', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $token = Str::random(48);
    Account::factory()->create()->users()->attach($invitee, ['role' => 'member', 'joined_at' => now()]);
    $invitation = redeemableInvitation($account, $inviter, $token);

    $this->actingAs($invitee)
        ->post(route('invitations.redeem', $token))
        ->assertConflict()
        ->assertSessionHasErrorsIn('redeem_conflict', 'invite');

    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('invitee cannot redeem while their personal account already has domain data', function () {
    $account = Account::factory()->create();
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $personal = Account::factory()->personal()->create();
    $personal->users()->attach($invitee, ['role' => 'owner', 'joined_at' => now()]);
    Contact::factory()->for($personal)->create(['user_id' => $invitee->id]);
    $token = Str::random(48);
    $invitation = redeemableInvitation($account, $inviter, $token);

    $this->actingAs($invitee)
        ->post(route('invitations.redeem', $token))
        ->assertConflict()
        ->assertSessionHasErrorsIn('redeem_conflict', 'invite');

    expect($invitation->fresh()->accepted_at)->toBeNull();
});

test('guest cannot redeem an invitation', function () {
    $this->post(route('invitations.redeem', Str::random(48)))
        ->assertRedirect(route('login'));
});

test('unauthenticated redeem returns 401 at the action seam', function () {
    $this->withoutMiddleware(Authenticate::class)
        ->post(route('invitations.redeem', Str::random(48)))
        ->assertUnauthorized();
});
