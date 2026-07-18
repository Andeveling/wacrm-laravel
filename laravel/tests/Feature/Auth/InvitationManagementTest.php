<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a user attached to a fresh Team account with the given role,
     * returning both. Requests act with the account as current session.
     *
     * @return array{0: User, 1: Account}
     */
    private function memberWithRole(string $role): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->create();
        $account->users()->attach($user->id, ['role' => $role, 'joined_at' => now()]);

        return [$user, $account];
    }

    public function test_admin_can_send_an_invitation_and_sees_the_link_once(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

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
    }

    public function test_invitation_token_is_stored_hashed_and_link_resolves_to_it(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

        $response = $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'member']);

        $url = session('invitation_url');
        $this->assertIsString($url);

        $token = basename(parse_url($url, PHP_URL_PATH));

        $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertDatabaseMissing('account_invitations', ['token_hash' => $token]);
    }

    public function test_invitation_expiry_defaults_to_seven_days(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'viewer']);

        $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(7)));
    }

    public function test_invitation_accepts_a_custom_expiry_within_bounds(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'member', 'expires_in_days' => 365]);

        $invitation = Invitation::withoutGlobalScopes()->firstOrFail();
        $this->assertTrue($invitation->expires_at->isSameDay(now()->addDays(365)));

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'member', 'expires_in_days' => 366])
            ->assertSessionHasErrors('expires_in_days');
    }

    public function test_already_revoked_invitation_cannot_be_revoked_again(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $invitation = Invitation::factory()->for($account)->revoked()->create();
        $originalRevokedAt = $invitation->revoked_at;

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('invitations.revoke', $invitation))
            ->assertNotFound();

        $this->assertTrue($invitation->fresh()->revoked_at->equalTo($originalRevokedAt));
    }

    public function test_owner_role_cannot_be_invited(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'owner'])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseCount('account_invitations', 0);
    }

    public function test_member_cannot_send_invitations(): void
    {
        [$member, $account] = $this->memberWithRole('member');

        $this->actingAs($member)
            ->withSession(['current_account_id' => $account->id])
            ->post(route('invitations.store'), ['role' => 'viewer'])
            ->assertForbidden();

        $this->assertDatabaseCount('account_invitations', 0);
    }

    public function test_guest_cannot_send_invitations(): void
    {
        $this->post(route('invitations.store'), ['role' => 'member'])
            ->assertRedirect(route('login'));
    }

    public function test_admin_can_revoke_a_pending_invitation(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $invitation = Invitation::factory()->for($account)->for($admin, 'inviter')->create();

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('invitations.revoke', $invitation))
            ->assertRedirect();

        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_revoke_is_scoped_to_the_current_account(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $otherAccount = Account::factory()->create();
        $foreign = Invitation::factory()->for($otherAccount)->create();

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('invitations.revoke', $foreign))
            ->assertNotFound();

        $this->assertNull($foreign->fresh()->revoked_at);
    }

    public function test_member_cannot_revoke_invitations(): void
    {
        [$member, $account] = $this->memberWithRole('member');
        $invitation = Invitation::factory()->for($account)->create();

        $this->actingAs($member)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('invitations.revoke', $invitation))
            ->assertForbidden();

        $this->assertNull($invitation->fresh()->revoked_at);
    }

    public function test_accepted_invitation_cannot_be_revoked(): void
    {
        [$admin, $account] = $this->memberWithRole('admin');
        $invitation = Invitation::factory()->for($account)->accepted()->create();

        $this->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('invitations.revoke', $invitation))
            ->assertNotFound();

        $this->assertNull($invitation->fresh()->revoked_at);
    }
}
