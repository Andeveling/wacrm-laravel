<?php

namespace Tests\Feature\Accounts;

use App\Models\Account;
use App\Models\Enums\AccountRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Exercises the RemoveMember ADR Action end-to-end through the HTTP layer.
 * Each test sets up a Team Account with the required roles, performs the
 * DELETE, then asserts on the response AND on the `account_user` pivot
 * directly (so we catch a successful HTTP redirect paired with a leaked DB
 * write, or vice versa).
 *
 * Owner Protection (ADR 0002) and self-removal are first-class assertions
 * here — they live in the Action, not the Responder, but verifying them
 * through the request boundary makes sure the route wires the Action
 * correctly.
 *
 * The "members page" route name is constructed via `route()` defensively
 * so the tests don't blow up if the read-only list route (#43) hasn't
 * landed yet — we still set `from()` so the Responder's `back()`
 * resolves to a real URL we can assert against.
 */
class RemoveMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_remove_an_admin(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $admin = $this->attachUserToAccount($account, AccountRole::Admin);
        $from = $this->membersIndexUrl($account);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->from($from)
            ->delete(route('accounts.members.destroy', [$account, $admin]));

        $response->assertRedirect($from);
        $response->assertSessionHas('member_removed');
        $this->assertFalse(
            $account->users()->whereKey($admin->id)->exists(),
            'Admin pivot row must be deleted after Owner removes them.',
        );
    }

    public function test_owner_can_remove_a_member(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $member = $this->attachUserToAccount($account, AccountRole::Member);
        $from = $this->membersIndexUrl($account);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->from($from)
            ->delete(route('accounts.members.destroy', [$account, $member]));

        $response->assertRedirect($from);
        $response->assertSessionHas('member_removed');
        $this->assertFalse(
            $account->users()->whereKey($member->id)->exists(),
            'Member pivot row must be deleted after Owner removes them.',
        );
    }

    public function test_sole_owner_cannot_remove_themselves(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('accounts.members.destroy', [$account, $owner]));

        $response->assertForbidden();
        $this->assertTrue(
            $account->users()->whereKey($owner->id)->exists(),
            'Sole Owner must remain a member of the Account (self-removal blocked).',
        );
    }

    public function test_admin_cannot_remove_the_sole_owner(): void
    {
        $account = Account::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $account->users()->attach($owner->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

        $from = $this->membersIndexUrl($account);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->from($from)
            ->delete(route('accounts.members.destroy', [$account, $owner]));

        $response->assertRedirect($from);
        $response->assertSessionHasErrors();
        $this->assertTrue(
            $account->users()->whereKey($owner->id)->exists(),
            'Sole Owner must NOT be removed — ADR 0002 Owner Protection.',
        );
        $this->assertSame(2, DB::table('account_user')->where('account_id', $account->id)->count(), 'Both Owner and Admin pivots must remain — LastOwnerBlocked writes nothing.');
    }

    public function test_admin_can_remove_an_owner_when_another_owner_exists(): void
    {
        $account = Account::factory()->create();
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $admin = User::factory()->create();
        $account->users()->attach($ownerA->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($ownerB->id, ['role' => AccountRole::Owner->value, 'joined_at' => now()]);
        $account->users()->attach($admin->id, ['role' => AccountRole::Admin->value, 'joined_at' => now()]);

        $from = $this->membersIndexUrl($account);

        $response = $this
            ->actingAs($admin)
            ->withSession(['current_account_id' => $account->id])
            ->from($from)
            ->delete(route('accounts.members.destroy', [$account, $ownerB]));

        $response->assertRedirect($from);
        $response->assertSessionHas('member_removed');
        $this->assertFalse(
            $account->users()->whereKey($ownerB->id)->exists(),
            'Non-sole Owner must be removable when another Owner remains.',
        );
        $this->assertTrue($account->users()->whereKey($ownerA->id)->exists());
    }

    public function test_viewer_cannot_remove_anyone(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $viewer = $this->attachUserToAccount($account, AccountRole::Viewer);
        $admin = $this->attachUserToAccount($account, AccountRole::Admin);

        $response = $this
            ->actingAs($viewer)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('accounts.members.destroy', [$account, $admin]));

        $response->assertForbidden();
        $this->assertTrue(
            $account->users()->whereKey($admin->id)->exists(),
            'Viewer must not be able to remove anyone.',
        );
    }

    public function test_removing_nonexistent_member_returns_404(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $stranger = User::factory()->create();

        $response = $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->delete(route('accounts.members.destroy', [$account, $stranger]));

        $response->assertNotFound();
    }

    public function test_personal_account_of_removed_user_is_intact(): void
    {
        [$account, $owner] = $this->seedAccountWithRole(AccountRole::Owner);
        $admin = $this->attachUserToAccount($account, AccountRole::Admin);

        // Each user auto-owns a Personal account; create one for the admin
        // so we can verify it survives a Team-Account removal.
        $personal = Account::factory()->personal()->create();
        DB::table('account_user')->insert([
            'account_id' => $personal->id,
            'user_id' => $admin->id,
            'role' => AccountRole::Owner->value,
            'joined_at' => now(),
        ]);

        $from = $this->membersIndexUrl($account);

        $this
            ->actingAs($owner)
            ->withSession(['current_account_id' => $account->id])
            ->from($from)
            ->delete(route('accounts.members.destroy', [$account, $admin]))
            ->assertRedirect($from);

        $this->assertFalse(
            $account->users()->whereKey($admin->id)->exists(),
            'Team-Account membership must be deleted.',
        );
        $this->assertTrue(
            $personal->users()->whereKey($admin->id)->exists(),
            'Personal Account of the removed user must remain intact.',
        );
        $this->assertDatabaseHas('accounts', ['id' => $personal->id]);
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

    /**
     * Build the "back" URL the Responder redirects to. We prefer the
     * `accounts.members.index` named route when present; otherwise fall
     * back to the raw path so tests can run before #43 lands.
     */
    private function membersIndexUrl(Account $account): string
    {
        if (app('router')->getRoutes()->hasNamedRoute('accounts.members.index')) {
            return route('accounts.members.index', $account);
        }

        return "/accounts/{$account->id}/members";
    }
}
