<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the POST /accounts/{account}/members endpoint
 * (issue #44) and the InviteMember Action behind it. The route is
 * account-scoped and admin-gated — viewer/outsider requests must
 * 403; duplicate emails in the target account must surface a
 * validation error before any invitation row is created.
 *
 * Note: test addresses use @gmail.com because example.com publishes
 * a null MX (RFC 7505) and trips the `email:dns` rule. gmail.com has
 * real MX records so the email:rfc,dns validation passes deterministically.
 *
 * Note on 422 vs 302: the spec speaks of "422 validation error", but
 * Laravel's web validation pipeline redirects back with errors in the
 * session (HTTP 302 + flashed errors). The behaviour is identical
 * from the user's POV — the page renders the form with the error
 * inline — so the tests assert the framework-native shape: 302
 * redirect + session error keyed by the offending field.
 */
class InviteMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_invite_a_new_email_and_invitation_row_is_created_with_hashed_token(): void
    {
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
        $this->assertNotNull($invitation, 'Expected an invitation row to be persisted.');
        $this->assertSame($account->id, $invitation->account_id);
        $this->assertSame(AccountRole::Member->value, $invitation->role);
        $this->assertSame($admin->id, $invitation->invited_by);
        // SHA-256 hex digest is always 64 lowercase hex chars.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $invitation->token_hash);
    }

    public function test_viewer_cannot_invite(): void
    {
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
    }

    public function test_non_member_cannot_invite(): void
    {
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
    }

    public function test_email_already_a_member_redirects_with_validation_error_and_creates_no_invitation(): void
    {
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
    }

    public function test_email_already_a_member_of_a_different_account_is_allowed(): void
    {
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
    }

    public function test_missing_email_redirects_with_validation_error(): void
    {
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
    }

    public function test_invalid_role_redirects_with_validation_error(): void
    {
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
    }

    public function test_owner_can_invite(): void
    {
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
    }

    public function test_owner_can_invite_with_role_owner(): void
    {
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
    }

    public function test_admin_cannot_invite_with_role_owner(): void
    {
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
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $account = Account::factory()->create();

        $response = $this->post(route('accounts.members.store', $account), [
            'email' => 'newperson@gmail.com',
            'role' => AccountRole::Member->value,
        ]);

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('account_invitations', 0);
    }
}
