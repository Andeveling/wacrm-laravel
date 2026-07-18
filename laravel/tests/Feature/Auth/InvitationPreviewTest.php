<?php

namespace Tests\Feature\Auth;

use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class InvitationPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitee_can_preview_a_valid_invitation(): void
    {
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
    }

    public function test_invitee_sees_expired_state_when_invitation_expired(): void
    {
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
    }

    public function test_invitee_sees_used_state_when_already_accepted(): void
    {
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
    }

    public function test_invitee_sees_invalid_state_for_revoked_invitation(): void
    {
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
    }

    public function test_invitee_sees_invalid_state_for_unknown_token(): void
    {
        $this->get(route('invitations.preview', ['token' => Str::random(48)]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('invitations/preview')
                ->where('status', 'invalid')
                ->where('account_name', null)
            );
    }

    public function test_preview_does_not_require_authentication(): void
    {
        $account = Account::factory()->create();
        $inviter = User::factory()->create();
        $plainToken = Str::random(48);

        Invitation::factory()
            ->for($account)
            ->for($inviter, 'inviter')
            ->create(['token_hash' => hash('sha256', $plainToken)]);

        $this->get(route('invitations.preview', ['token' => $plainToken]))
            ->assertOk();
    }

    public function test_preview_hashes_the_token_before_lookup(): void
    {
        $plainToken = 'super-secret-plaintext-token';

        Invitation::factory()->create([
            'token_hash' => hash('sha256', $plainToken),
        ]);

        $this->assertDatabaseMissing('account_invitations', [
            'token_hash' => $plainToken,
        ]);
    }

    public function test_valid_preview_links_to_register_with_invite_token(): void
    {
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
    }
}
