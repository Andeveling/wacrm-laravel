<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Exercises the ChangeMemberRole ADR Action end-to-end through the
 * HTTP layer. Each test sets up a Team Account with the roles it
 * needs, performs the PATCH, then asserts on the response AND on the
 * `account_user` pivot directly — so a successful HTTP redirect
 * paired with a leaked DB write (or vice versa) is caught.
 *
 * Owner Protection (ADR 0002) is the central invariant: the "sole
 * Owner self-degrade" cases verify the pivot role is UNCHANGED after
 * the failed request, proving the count check happens before the
 * UPDATE, not after.
 */
class ChangeMemberRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_demote_an_admin_to_member(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $admin = $this->attachUserToAccount($account, AccountRole::Admin);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $admin]), [
                'role' => AccountRole::Member->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHas('role_changed');

        $this->assertSame(
            AccountRole::Member,
            $this->roleIn($account, $admin->id),
            'Admin pivot role must be updated to Member after Owner demotes them.',
        );
    }

    public function test_owner_can_promote_a_member_to_owner(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $member = $this->attachUserToAccount($account, AccountRole::Member);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $member]), [
                'role' => AccountRole::Owner->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHas('role_changed');

        $this->assertSame(
            AccountRole::Owner,
            $this->roleIn($account, $member->id),
            'Member pivot role must be updated to Owner after Owner promotes them.',
        );
    }

    public function test_sole_owner_cannot_demote_themselves_to_admin(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $owner]), [
                'role' => AccountRole::Admin->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHasErrors('last_owner_blocked');

        $this->assertSame(
            AccountRole::Owner,
            $this->roleIn($account, $owner->id),
            'Sole Owner pivot role must NOT change — ADR 0002 Owner Protection.',
        );
    }

    public function test_sole_owner_cannot_demote_themselves_to_viewer(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $owner]), [
                'role' => AccountRole::Viewer->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHasErrors('last_owner_blocked');

        $this->assertSame(
            AccountRole::Owner,
            $this->roleIn($account, $owner->id),
            'Sole Owner pivot role must NOT change — ADR 0002 Owner Protection.',
        );
    }

    public function test_admin_can_demote_a_member_to_viewer(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
        $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $member]), [
                'role' => AccountRole::Viewer->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHas('role_changed');

        $this->assertSame(
            AccountRole::Viewer,
            $this->roleIn($account, $member->id),
            'Admin must be able to demote a non-Owner Member to Viewer per the cross-issue contract.',
        );
    }

    public function test_admin_can_demote_another_admin_to_member(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $otherAdmin = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
        $account->users()->attach($otherAdmin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $otherAdmin]), [
                'role' => AccountRole::Member->value,
            ]);

        $response->assertRedirect(route('accounts.members.index', $account));
        $response->assertSessionHas('role_changed');

        $this->assertSame(
            AccountRole::Member,
            $this->roleIn($account, $otherAdmin->id),
            'Admin must be able to demote a peer Admin to Member per the cross-issue contract.',
        );
    }

    public function test_admin_cannot_change_an_owner_role(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $otherOwner = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
        $account->users()->attach($otherOwner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $otherOwner]), [
                'role' => AccountRole::Admin->value,
            ]);

        $response->assertForbidden();

        $this->assertSame(
            AccountRole::Owner,
            $this->roleIn($account, $otherOwner->id),
            'Owner pivot role must NOT change when Admin attempts the mutation.',
        );
    }

    public function test_admin_cannot_promote_a_member_to_owner(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);
        $account->users()->attach($member->id, ['role' => AccountRole::Member->value, 'joined_at' => now()]);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $member]), [
                'role' => AccountRole::Owner->value,
            ]);

        $response->assertForbidden();

        $this->assertSame(
            AccountRole::Member,
            $this->roleIn($account, $member->id),
            'Member pivot role must NOT be promoted to Owner by an Admin.',
        );
    }

    public function test_viewer_cannot_change_anyone_role(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $viewer = $this->attachUserToAccount($account, AccountRole::Viewer);
        $member = $this->attachUserToAccount($account, AccountRole::Member);

        $response = $this
            ->actingAs($viewer)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $member]), [
                'role' => AccountRole::Viewer->value,
            ]);

        $response->assertForbidden();

        $this->assertSame(
            AccountRole::Member,
            $this->roleIn($account, $member->id),
            'Member pivot role must NOT change when Viewer attempts the mutation.',
        );
    }

    public function test_changing_role_of_non_member_returns_404(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $stranger = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->patch(route('accounts.members.update', [$account, $stranger]), [
                'role' => AccountRole::Viewer->value,
            ]);

        $response->assertNotFound();
    }

    /**
     * Read the current role of a pivot row, or null if the user is
     * not a member of the account. Bypasses the role cast to keep the
     * test asserting exactly what the database holds.
     */
    private function roleIn(Account $account, int $userId): ?AccountRole
    {
        $raw = $account->users()->whereKey($userId)->first()?->pivot->role;

        return $raw instanceof AccountRole ? $raw : ($raw !== null ? AccountRole::from((string) $raw) : null);
    }

    /**
     * @return array{0: Account, 1: User}
     */
    private function seedAccountWithRole(AccountRole $role): array
    {
        $account = Account::factory()->create();
        $user = User::factory()->create();
        $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

        return [$account, $user];
    }

    private function attachUserToAccount(Account $account, AccountRole $role): User
    {
        $user = User::factory()->create();
        $account->users()->attach($user->id, ['role' => $role->value, 'joined_at' => now()]);

        return $user;
    }
}
