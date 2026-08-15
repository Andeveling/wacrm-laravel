<?php

use App\Domain\Invitations\Responders\RedeemInvitationResponder;
use App\Domain\Invitations\Results\RedeemInvitationResult;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

    $this->withoutExceptionHandling();

    $this->actingAs(User::factory()->create())
        ->post(route('invitations.redeem', $token));
})->throws(ValidationException::class);

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

test('redeem responder rejects unauthenticated results', function () {
    app(RedeemInvitationResponder::class)(RedeemInvitationResult::unauthenticated());
})->throws(HttpException::class);
